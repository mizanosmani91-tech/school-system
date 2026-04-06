<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once '../../includes/functions.php';
startSession();

// Parent login check — supports both old (user_id) and new (parent_student_id) session
if ($_SESSION['role_slug'] !== 'parent') {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

$db = getDB();

// New login: directly know the student from session
if (!empty($_SESSION['parent_student_id'])) {
    $stmt = $db->prepare("SELECT s.*, c.class_name_bn, sec.section_name FROM students s
        LEFT JOIN classes c ON s.class_id=c.id
        LEFT JOIN sections sec ON s.section_id=sec.id
        WHERE s.id=? AND s.status='active'");
    $stmt->execute([$_SESSION['parent_student_id']]);
    $myStudents = $stmt->fetchAll();

// Old login: look up via users table phone (backward compatible)
} elseif (!empty($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
    $user = $db->prepare("SELECT * FROM users WHERE id=?");
    $user->execute([$userId]);
    $userInfo = $user->fetch();

    $stmt = $db->prepare("SELECT s.*, c.class_name_bn, sec.section_name FROM students s
        LEFT JOIN classes c ON s.class_id=c.id
        LEFT JOIN sections sec ON s.section_id=sec.id
        WHERE (s.father_phone=? OR s.guardian_phone=?) AND s.status='active'");
    $stmt->execute([$userInfo['phone'], $userInfo['phone']]);
    $myStudents = $stmt->fetchAll();

} else {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

$activeStudentId = (int)($_GET['student_id'] ?? ($myStudents[0]['id'] ?? 0));
$activeStudent = null;
foreach ($myStudents as $s) { if ($s['id'] == $activeStudentId) { $activeStudent = $s; break; } }

$tab = $_GET['tab'] ?? 'overview';

// Notifications
$notifications = [];
$unreadNotifCount = 0;
if ($activeStudentId) {
    $notifStmt = $db->prepare("SELECT pn.*, s.subject_name_bn, t.name_bn as teacher_name_bn
        FROM parent_notifications pn
        LEFT JOIN subjects s ON pn.subject_id = s.id
        LEFT JOIN teachers t ON pn.teacher_id = t.id
        WHERE pn.student_id = ? ORDER BY pn.created_at DESC LIMIT 20");
    $notifStmt->execute([$activeStudentId]);
    $notifications = $notifStmt->fetchAll();

    $unreadStmt = $db->prepare("SELECT COUNT(*) FROM parent_notifications WHERE student_id=? AND is_read=0");
    $unreadStmt->execute([$activeStudentId]);
    $unreadNotifCount = $unreadStmt->fetchColumn();

    if ($tab === 'notifications') {
        $db->prepare("UPDATE parent_notifications SET is_read=1 WHERE student_id=?")->execute([$activeStudentId]);
    }
}

// Send message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $msg = trim($_POST['message'] ?? '');
    if ($msg && $activeStudentId) {
        // Get parent phone — from userInfo (old login) or from student record (new login)
        $parentPhone = $userInfo['phone'] ?? '';
        if (!$parentPhone && $activeStudent) {
            $parentPhone = $activeStudent['father_phone'] ?? $activeStudent['guardian_phone'] ?? '';
        }
        $stmt = $db->prepare("INSERT INTO parent_messages (student_id, parent_phone, message) VALUES (?,?,?)");
        $stmt->execute([$activeStudentId, $parentPhone, $msg]);
        setFlash('success', 'আপনার বার্তা পাঠানো হয়েছে।');
    }
    header("Location: portal.php?student_id=$activeStudentId&tab=messages");
    exit;
}

// Load data based on tab
$attendanceData = $examData = $feeData = $noticesData = $messagesData = [];
if ($activeStudent) {
    $today = date('Y-m-d');
    $thisMonth = date('Y-m');
    $thisYear = date('Y');

    // Attendance summary
    $attStmt = $db->prepare("SELECT
        COUNT(*) as total,
        SUM(status='present') as present,
        SUM(status='absent') as absent,
        SUM(status='late') as late
        FROM attendance WHERE student_id=? AND date BETWEEN ? AND ?");
    $attStmt->execute([$activeStudent['id'], "$thisYear-01-01", $today]);
    $attSummary = $attStmt->fetch();

    // Recent attendance
    $attRecent = $db->prepare("SELECT * FROM attendance WHERE student_id=? ORDER BY date DESC LIMIT 30");
    $attRecent->execute([$activeStudent['id']]);
    $attendanceData = $attRecent->fetchAll();

    // Exam results
    $examStmt = $db->prepare("SELECT em.*, e.exam_name_bn, s.subject_name_bn FROM exam_marks em
        JOIN exams e ON em.exam_id=e.id
        JOIN subjects s ON em.subject_id=s.id
        WHERE em.student_id=? ORDER BY e.start_date DESC, s.subject_name_bn");
    $examStmt->execute([$activeStudent['id']]);
    $examData = $examStmt->fetchAll();

    // Fee info
    $feeStmt = $db->prepare("SELECT fc.*, ft.fee_name_bn FROM fee_collections fc
        JOIN fee_types ft ON fc.fee_type_id=ft.id
        WHERE fc.student_id=? ORDER BY fc.payment_date DESC LIMIT 12");
    $feeStmt->execute([$activeStudent['id']]);
    $feeData = $feeStmt->fetchAll();

    // Total fees this year
    $totalPaid = $db->prepare("SELECT COALESCE(SUM(paid_amount),0) FROM fee_collections WHERE student_id=? AND YEAR(payment_date)=?");
    $totalPaid->execute([$activeStudent['id'], $thisYear]);
    $totalFeesPaid = $totalPaid->fetchColumn();

    // Notices
    $noticesData = $db->query("SELECT * FROM notices WHERE is_published=1 ORDER BY created_at DESC LIMIT 10")->fetchAll();

    // Messages
    $msgStmt = $db->prepare("SELECT * FROM parent_messages WHERE student_id=? ORDER BY created_at DESC LIMIT 20");
    $msgStmt->execute([$activeStudent['id']]);
    $messagesData = $msgStmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>অভিভাবক পোর্টাল | <?= e(getSetting('institute_name')) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
:root {
    --primary: #1a5276; --accent: #e67e22; --success: #27ae60;
    --danger: #c0392b; --warning: #f39c12; --info: #2980b9;
    --bg: #f0f4f8; --card: #fff; --border: #e2e8f0;
    --font: 'Hind Siliguri', sans-serif;
}
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: var(--font); background: var(--bg); color: #1a202c; font-size: 15px; }
.portal-header {
    background: linear-gradient(135deg, #0d2137, #1a5276);
    color: #fff; padding: 0 24px; height: 60px;
    display: flex; align-items: center; justify-content: space-between;
    position: sticky; top: 0; z-index: 100;
}
.portal-header h1 { font-size: 18px; }
.portal-header-right { display: flex; align-items: center; gap: 12px; font-size: 13px; }
.content { max-width: 900px; margin: 0 auto; padding: 20px 16px; }
.card { background: var(--card); border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,.1); margin-bottom: 16px; }
.card-header { padding: 14px 18px; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 8px; }
.card-title { font-size: 15px; font-weight: 700; color: #0d2137; }
.card-body { padding: 18px; }
.student-card {
    background: linear-gradient(135deg, var(--primary), #0d2137);
    color: #fff; padding: 20px 18px; border-radius: 12px; margin-bottom: 16px;
}
.student-name { font-size: 20px; font-weight: 700; }
.tabs { display: flex; gap: 4px; overflow-x: auto; padding: 0 16px;
    background: #fff; border-bottom: 1px solid var(--border); position: sticky; top: 60px; z-index: 90; }
.tab { padding: 14px 16px; color: #718096; font-size: 13px; font-weight: 600;
    cursor: pointer; white-space: nowrap; border-bottom: 3px solid transparent;
    text-decoration: none; display: flex; align-items: center; gap: 6px; transition: all .2s; }
.tab.active, .tab:hover { color: var(--primary); border-bottom-color: var(--primary); }
.badge { display: inline-flex; align-items: center; padding: 2px 8px;
    border-radius: 20px; font-size: 11px; font-weight: 600; }
.badge-success { background: #d4edda; color: #155724; }
.badge-danger { background: #f8d7da; color: #721c24; }
.badge-warning { background: #fff3cd; color: #856404; }
.badge-info { background: #d1ecf1; color: #0c5460; }
.stat-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 16px; }
.stat-mini { background: var(--card); border-radius: 10px; padding: 14px;
    text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
.stat-mini .val { font-size: 22px; font-weight: 700; }
.stat-mini .lbl { font-size: 11px; color: #718096; margin-top: 3px; }
table { width: 100%; border-collapse: collapse; font-size: 13px; }
thead th { background: var(--primary); color: #fff; padding: 10px 12px; text-align: left; }
tbody tr { border-bottom: 1px solid var(--border); }
tbody td { padding: 10px 12px; }
tbody tr:hover { background: #f7fafc; }
.form-control { width: 100%; padding: 9px 12px; border: 1.5px solid var(--border);
    border-radius: 8px; font-family: var(--font); font-size: 14px; outline: none; }
.form-control:focus { border-color: var(--primary); }
.btn { display: inline-flex; align-items: center; gap: 6px; padding: 9px 18px;
    border-radius: 8px; border: none; cursor: pointer; font-family: var(--font);
    font-size: 14px; font-weight: 600; text-decoration: none; transition: all .2s; }
.btn-primary { background: var(--primary); color: #fff; }
.btn-outline { background: transparent; border: 1.5px solid var(--border); color: #1a202c; }
.alert { padding: 10px 14px; border-radius: 8px; margin-bottom: 12px; font-size: 13px; }
.alert-success { background: #d4edda; color: #155724; }
.notice-item { padding: 12px 0; border-bottom: 1px solid var(--border); }
.notice-item:last-child { border: none; }
.msg-box { display: flex; flex-direction: column; gap: 10px; }
.msg-item { padding: 10px 14px; border-radius: 8px; max-width: 85%; }
.msg-item.sent { background: var(--primary); color: #fff; align-self: flex-end; border-radius: 12px 12px 0 12px; }
.msg-item.received { background: var(--bg); border: 1px solid var(--border); align-self: flex-start; border-radius: 12px 12px 12px 0; }
.msg-meta { font-size: 11px; opacity: .7; margin-top: 4px; }
@media (max-width: 500px) { .stat-row { grid-template-columns: repeat(2,1fr); } }
</style>
</head>
<body>
<header class="portal-header">
    <div>
        <i class="fas fa-mosque" style="margin-right:8px;color:#f0a500;"></i>
        <strong><?= e(getSetting('institute_name')) ?></strong>
    </div>
    <div class="portal-header-right">
        <span><?= e($userInfo['name'] ?? $_SESSION['user_name'] ?? $activeStudent['guardian_name'] ?? '') ?></span>
        <a href="<?= BASE_URL ?>/logout.php" style="color:#a8c0d4;text-decoration:none;"><i class="fas fa-sign-out-alt"></i></a>
    </div>
</header>

<div style="padding:0 16px;background:#fff;border-bottom:1px solid var(--border);">
    <div style="max-width:900px;margin:0 auto;padding:10px 0;display:flex;gap:8px;overflow-x:auto;">
        <?php foreach ($myStudents as $ms): ?>
        <a href="?student_id=<?= $ms['id'] ?>&tab=<?= $tab ?>"
            style="text-decoration:none;padding:8px 14px;border-radius:8px;font-size:13px;font-weight:600;white-space:nowrap;
            background:<?= $ms['id'] == $activeStudentId ? 'var(--primary)' : 'var(--bg)' ?>;
            color:<?= $ms['id'] == $activeStudentId ? '#fff' : '#718096' ?>;">
            <?= e($ms['name_bn'] ?? $ms['name']) ?>
        </a>
        <?php endforeach; ?>
    </div>
</div>

<?php if (empty($myStudents)): ?>
<div style="text-align:center;padding:60px 20px;color:#718096;">
    <i class="fas fa-user-graduate" style="font-size:48px;margin-bottom:16px;"></i>
    <p>আপনার ফোন নম্বরে কোনো ছাত্র নথিভুক্ত নেই।<br>প্রতিষ্ঠানের সাথে যোগাযোগ করুন।</p>
</div>
<?php elseif ($activeStudent): ?>

<div class="tabs">
    <?php $navTabs = ['overview'=>'<i class="fas fa-home"></i> সংক্ষিপ্ত','attendance'=>'<i class="fas fa-clipboard-check"></i> উপস্থিতি','results'=>'<i class="fas fa-file-alt"></i> ফলাফল','fees'=>'<i class="fas fa-money-bill"></i> ফি','notices'=>'<i class="fas fa-bullhorn"></i> নোটিশ','messages'=>'<i class="fas fa-comments"></i> বার্তা'];
    foreach ($navTabs as $k=>$v): ?>
    <a href="?student_id=<?= $activeStudentId ?>&tab=<?= $k ?>" class="tab <?= $tab === $k ? 'active' : '' ?>"><?= $v ?></a>
    <?php endforeach; ?>
    <a href="?student_id=<?=$activeStudentId?>&tab=notifications" class="tab <?=$tab==='notifications'?'active':''?>">
        <i class="fas fa-bell"></i> সতর্কতা
        <?php if ($unreadNotifCount > 0): ?>
        <span style="background:#c0392b;color:#fff;font-size:10px;padding:1px 6px;border-radius:10px;font-weight:700;margin-left:2px;"><?= $unreadNotifCount ?></span>
        <?php endif; ?>
    </a>
</div>

<div class="content">
    <?php
    $flash = getFlash();
    if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] ?>"><?= e($flash['msg']) ?></div>
    <?php endif; ?>

    <?php if ($tab === 'overview'): ?>
    <!-- OVERVIEW -->
    <div class="student-card">
        <div style="display:flex;align-items:center;gap:14px;">
            <div style="width:56px;height:56px;background:rgba(255,255,255,.2);border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:24px;font-weight:700;">
                <?= mb_substr($activeStudent['name_bn'] ?? $activeStudent['name'], 0, 1) ?>
            </div>
            <div>
                <div class="student-name"><?= e($activeStudent['name_bn'] ?? $activeStudent['name']) ?></div>
                <div style="opacity:.8;font-size:13px;margin-top:4px;">
                    <?= e($activeStudent['class_name_bn']) ?> &bull; রোল: <?= e($activeStudent['roll_number']) ?> &bull; ID: <?= e($activeStudent['student_id']) ?>
                </div>
            </div>
        </div>
    </div>

    <div class="stat-row">
        <?php $rate = $attSummary['total'] > 0 ? round(($attSummary['present']/$attSummary['total'])*100) : 0; ?>
        <div class="stat-mini">
            <div class="val" style="color:var(--success);"><?= $rate ?>%</div>
            <div class="lbl">উপস্থিতি হার</div>
        </div>
        <div class="stat-mini">
            <div class="val" style="color:var(--danger);"><?= $attSummary['absent'] ?? 0 ?></div>
            <div class="lbl">অনুপস্থিতি</div>
        </div>
        <div class="stat-mini">
            <div class="val" style="color:var(--primary);">৳<?= number_format($totalFeesPaid ?? 0) ?></div>
            <div class="lbl">ফি পরিশোধ</div>
        </div>
    </div>

    <!-- Today's attendance -->
    <?php
    $todayAtt = $db->prepare("SELECT * FROM attendance WHERE student_id=? AND date=?");
    $todayAtt->execute([$activeStudent['id'], date('Y-m-d')]);
    $todayStatus = $todayAtt->fetch();
    ?>
    <div class="card">
        <div class="card-header"><span class="card-title"><i class="fas fa-calendar-day"></i> আজকের অবস্থা — <?= banglaDate() ?></span></div>
        <div class="card-body">
            <?php if ($todayStatus): ?>
            <span class="badge badge-<?= $todayStatus['status'] === 'present' ? 'success' : ($todayStatus['status'] === 'absent' ? 'danger' : 'warning') ?>" style="font-size:14px;padding:6px 16px;">
                <?= ['present'=>'✓ উপস্থিত','absent'=>'✗ অনুপস্থিত','late'=>'⏰ দেরি','excused'=>'ছুটি'][$todayStatus['status']] ?? $todayStatus['status'] ?>
            </span>
            <?php else: ?>
            <span style="color:#718096;font-size:14px;">আজকের উপস্থিতি এখনো নেওয়া হয়নি</span>
            <?php endif; ?>
        </div>
    </div>

    <?php elseif ($tab === 'attendance'): ?>
    <!-- ATTENDANCE -->
    <div class="card">
        <div class="card-header">
            <span class="card-title"><i class="fas fa-clipboard-check"></i> উপস্থিতি বিবরণ</span>
        </div>
        <div class="card-body" style="padding:0;">
            <?php if (empty($attendanceData)): ?>
            <div style="text-align:center;padding:30px;color:#718096;">কোনো তথ্য নেই</div>
            <?php else: ?>
            <table>
                <thead><tr><th>তারিখ</th><th>অবস্থা</th><th>মন্তব্য</th></tr></thead>
                <tbody>
                    <?php foreach ($attendanceData as $a): ?>
                    <tr>
                        <td><?= banglaDate($a['date']) ?></td>
                        <td>
                            <span class="badge badge-<?= $a['status']==='present'?'success':($a['status']==='absent'?'danger':'warning') ?>">
                                <?= ['present'=>'উপস্থিত','absent'=>'অনুপস্থিত','late'=>'দেরি','excused'=>'ছুটি'][$a['status']] ?? $a['status'] ?>
                            </span>
                        </td>
                        <td style="font-size:12px;color:#718096;"><?= e($a['note'] ?? '') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

    <?php elseif ($tab === 'results'): ?>
    <!-- RESULTS -->
    <div class="card">
        <div class="card-header"><span class="card-title"><i class="fas fa-file-alt"></i> পরীক্ষার ফলাফল</span></div>
        <div class="card-body" style="padding:0;">
            <?php if (empty($examData)): ?>
            <div style="text-align:center;padding:30px;color:#718096;">কোনো ফলাফল নেই</div>
            <?php else:
                $grouped = [];
                foreach ($examData as $e) { $grouped[$e['exam_name_bn']][] = $e; }
                foreach ($grouped as $examName => $marks):
            ?>
            <div style="padding:12px 16px;background:#ebf5fb;font-weight:700;color:var(--primary);font-size:14px;">
                <?= e($examName) ?>
            </div>
            <table>
                <thead><tr><th>বিষয়</th><th>প্রাপ্ত নম্বর</th><th>গ্রেড</th></tr></thead>
                <tbody>
                    <?php foreach ($marks as $m): ?>
                    <tr>
                        <td><?= e($m['subject_name_bn']) ?></td>
                        <td style="font-weight:700;"><?= $m['is_absent'] ? 'অনুপস্থিত' : e($m['total_marks']) ?></td>
                        <td>
                            <span class="badge badge-<?= $m['grade']==='F'?'danger':($m['grade']==='A+'?'success':'info') ?>">
                                <?= e($m['grade']) ?> (<?= e($m['grade_point']) ?>)
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endforeach; endif; ?>
        </div>
    </div>

    <?php elseif ($tab === 'fees'): ?>
    <!-- FEES -->
    <div class="stat-mini" style="margin-bottom:16px;text-align:left;padding:16px;">
        <div style="font-size:22px;font-weight:700;color:var(--success);">৳<?= number_format($totalFeesPaid ?? 0) ?></div>
        <div style="font-size:12px;color:#718096;">এই বছর মোট পরিশোধ</div>
    </div>
    <div class="card">
        <div class="card-header"><span class="card-title"><i class="fas fa-money-bill"></i> পরিশোধের ইতিহাস</span></div>
        <div class="card-body" style="padding:0;">
            <?php if (empty($feeData)): ?>
            <div style="text-align:center;padding:30px;color:#718096;">কোনো পরিশোধ তথ্য নেই</div>
            <?php else: ?>
            <table>
                <thead><tr><th>তারিখ</th><th>ফির ধরন</th><th>পরিমাণ</th><th>পদ্ধতি</th><th>রসিদ</th></tr></thead>
                <tbody>
                    <?php foreach ($feeData as $f): ?>
                    <tr>
                        <td style="font-size:12px;"><?= banglaDate($f['payment_date']) ?></td>
                        <td><?= e($f['fee_name_bn']) ?></td>
                        <td style="font-weight:700;color:var(--success);">৳<?= number_format($f['paid_amount']) ?></td>
                        <td><span class="badge badge-info" style="font-size:10px;"><?= e($f['payment_method']) ?></span></td>
                        <td style="font-size:11px;color:#718096;"><?= e($f['receipt_number']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

    <?php elseif ($tab === 'notices'): ?>
    <!-- NOTICES -->
    <div class="card">
        <div class="card-header"><span class="card-title"><i class="fas fa-bullhorn"></i> নোটিশ ও বিজ্ঞপ্তি</span></div>
        <div class="card-body">
            <?php if (empty($noticesData)): ?>
            <div style="text-align:center;padding:20px;color:#718096;">কোনো নোটিশ নেই</div>
            <?php else: foreach ($noticesData as $n): ?>
            <div class="notice-item">
                <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:8px;">
                    <div>
                        <div style="font-weight:700;font-size:14px;"><?= e($n['title_bn'] ?? $n['title']) ?></div>
                        <div style="font-size:13px;color:#4a5568;margin-top:4px;line-height:1.5;"><?= nl2br(e($n['content'])) ?></div>
                    </div>
                    <span class="badge badge-<?= $n['notice_type']==='urgent'?'danger':'info' ?>" style="font-size:10px;flex-shrink:0;">
                        <?= e($n['notice_type']) ?>
                    </span>
                </div>
                <div style="font-size:11px;color:#718096;margin-top:6px;"><?= banglaDate($n['created_at']) ?></div>
            </div>
            <?php endforeach; endif; ?>
        </div>
    </div>

    <?php elseif ($tab === 'messages'): ?>
    <!-- MESSAGES -->
    <div class="card">
        <div class="card-header"><span class="card-title"><i class="fas fa-comments"></i> শিক্ষকের সাথে যোগাযোগ</span></div>
        <div class="card-body">
            <div class="msg-box" style="min-height:200px;max-height:350px;overflow-y:auto;margin-bottom:16px;">
                <?php if (empty($messagesData)): ?>
                <div style="text-align:center;padding:30px;color:#718096;">কোনো বার্তা নেই</div>
                <?php else: foreach (array_reverse($messagesData) as $m): ?>
                <div class="msg-item sent">
                    <div><?= nl2br(e($m['message'])) ?></div>
                    <div class="msg-meta"><?= banglaDate($m['created_at']) ?></div>
                </div>
                <?php if ($m['reply']): ?>
                <div class="msg-item received">
                    <div style="font-size:11px;font-weight:700;color:#718096;margin-bottom:4px;">শিক্ষকের উত্তর:</div>
                    <div><?= nl2br(e($m['reply'])) ?></div>
                    <div class="msg-meta"><?= banglaDate($m['replied_at'] ?? '') ?></div>
                </div>
                <?php endif; ?>
                <?php endforeach; endif; ?>
            </div>
            <form method="POST">
                <input type="hidden" name="send_message" value="1">
                <textarea name="message" class="form-control" rows="3" placeholder="শিক্ষকের কাছে বার্তা পাঠান..." required></textarea>
                <button type="submit" class="btn btn-primary" style="margin-top:10px;width:100%;">
                    <i class="fas fa-paper-plane"></i> বার্তা পাঠান
                </button>
            </form>
        </div>
    </div>
    <?php elseif ($tab === 'notifications'): ?>
    <!-- NOTIFICATIONS -->
    <div class="card">
        <div class="card-header" style="justify-content:space-between;">
            <span class="card-title"><i class="fas fa-bell"></i> শিক্ষকের সতর্কতা বার্তা</span>
            <?php if ($unreadNotifCount > 0): ?>
            <span class="badge badge-danger"><?= $unreadNotifCount ?> টি নতুন</span>
            <?php endif; ?>
        </div>
        <div class="card-body" style="padding:0;">
            <?php if (empty($notifications)): ?>
            <div style="text-align:center;padding:40px;color:#718096;">
                <i class="fas fa-check-circle" style="font-size:40px;color:#27ae60;display:block;margin-bottom:12px;opacity:.7;"></i>
                কোনো সতর্কতা নেই — সব ঠিকঠাক আছে!
            </div>
            <?php else: foreach ($notifications as $n): ?>
            <div style="padding:14px 18px;border-bottom:1px solid var(--border);display:flex;gap:14px;align-items:flex-start;background:<?= $n['is_read'] ? '#fff' : '#fff8f0' ?>;">
                <div style="width:40px;height:40px;border-radius:10px;background:#fff3cd;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="fas fa-exclamation-triangle" style="color:#e67e22;"></i>
                </div>
                <div style="flex:1;">
                    <div style="font-size:14px;line-height:1.7;"><?= e($n['message']) ?></div>
                    <div style="display:flex;gap:8px;margin-top:7px;flex-wrap:wrap;align-items:center;">
                        <?php if ($n['subject_name_bn']): ?>
                        <span class="badge badge-warning"><?= e($n['subject_name_bn']) ?></span>
                        <?php endif; ?>
                        <?php if ($n['teacher_name_bn']): ?>
                        <span class="badge badge-info"><i class="fas fa-chalkboard-teacher" style="margin-right:3px;"></i><?= e($n['teacher_name_bn']) ?></span>
                        <?php endif; ?>
                        <span style="font-size:11px;color:#718096;"><?= banglaDate($n['created_at']) ?></span>
                        <?php if (!$n['is_read']): ?>
                        <span class="badge badge-danger" style="font-size:10px;">নতুন</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; endif; ?>
        </div>
    </div>

    <?php endif; ?>

</div><!-- /content -->
<?php endif; ?>

</body>
</html>
