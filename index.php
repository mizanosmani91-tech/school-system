<?php
require_once 'includes/functions.php';
requireLogin();
$pageTitle = 'ড্যাশবোর্ড';

$db = getDB();
$year = date('Y');

// Stats
$totalStudents = $db->query("SELECT COUNT(*) FROM students WHERE status='active' AND academic_year='$year'")->fetchColumn();
$totalTeachers = $db->query("SELECT COUNT(*) FROM teachers WHERE is_active=1")->fetchColumn();
$today = date('Y-m-d');

// আজকের উপস্থিতি
$presentToday = $db->query("SELECT COUNT(*) FROM attendance WHERE date='$today' AND status='present'")->fetchColumn();
$totalToday = $db->query("SELECT COUNT(*) FROM attendance WHERE date='$today'")->fetchColumn();
$attendanceRate = $totalToday > 0 ? round(($presentToday / $totalToday) * 100) : 0;

// এই মাসের ফি
$thisMonth = date('Y-m');
$monthlyFee = $db->query("SELECT COALESCE(SUM(paid_amount),0) FROM fee_collections WHERE month_year='$thisMonth'")->fetchColumn();

// বকেয়া (rough estimate)
$dueCount = $db->query("SELECT COUNT(DISTINCT student_id) FROM students s WHERE status='active' AND NOT EXISTS (SELECT 1 FROM fee_collections f WHERE f.student_id = s.id AND f.month_year='$thisMonth')")->fetchColumn();

// সাম্প্রতিক ভর্তি
$recentStudents = $db->query("SELECT s.*, c.class_name_bn FROM students s LEFT JOIN classes c ON s.class_id = c.id ORDER BY s.created_at DESC LIMIT 6")->fetchAll();

// নোটিশ
$notices = $db->query("SELECT * FROM notices WHERE is_published=1 ORDER BY created_at DESC LIMIT 5")->fetchAll();

// শিক্ষক আজকের চেক ইন স্ট্যাটাস
$teacherCheckedIn = 0;
$teacherNotCheckedIn = 0;
$teacherCheckedOut = 0;
try {
    $teacherCheckedIn = $db->query("SELECT COUNT(*) FROM teacher_attendance WHERE date='$today' AND check_in IS NOT NULL")->fetchColumn();
    $teacherCheckedOut = $db->query("SELECT COUNT(*) FROM teacher_attendance WHERE date='$today' AND check_out IS NOT NULL")->fetchColumn();
    $teacherNotCheckedIn = $db->query("SELECT COUNT(*) FROM teachers WHERE is_active=1")->fetchColumn() - $teacherCheckedIn;
} catch(Exception $e) {}

// আজকের শিক্ষক উপস্থিতি তালিকা
$teacherAttendanceToday = [];
try {
    $teacherAttendanceToday = $db->query("
        SELECT ta.*, t.name_bn, t.name, t.designation_bn, t.teacher_id_no
        FROM teacher_attendance ta
        JOIN teachers t ON ta.teacher_id = t.id
        WHERE ta.date = '$today'
        ORDER BY ta.check_in ASC
    ")->fetchAll();
} catch(Exception $e) {}

// উপস্থিতি ডেটা (শেষ ৭ দিন)
$attendanceChart = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $p = $db->prepare("SELECT COUNT(*) FROM attendance WHERE date=? AND status='present'");
    $p->execute([$d]);
    $a = $db->prepare("SELECT COUNT(*) FROM attendance WHERE date=?");
    $a->execute([$d]);
    $attendanceChart[] = ['date' => date('d/m', strtotime($d)), 'present' => $p->fetchColumn(), 'total' => $a->fetchColumn()];
}

require_once 'includes/header.php';
?>

<!-- STAT CARDS -->
<div class="stat-grid">
    <div class="stat-card blue">
        <div class="stat-icon"><i class="fas fa-user-graduate"></i></div>
        <div>
            <div class="stat-value"><?= toBanglaNumber($totalStudents) ?></div>
            <div class="stat-label">মোট ছাত্র (সক্রিয়)</div>
        </div>
    </div>
    <div class="stat-card green">
        <div class="stat-icon"><i class="fas fa-chalkboard-teacher"></i></div>
        <div>
            <div class="stat-value"><?= toBanglaNumber($totalTeachers) ?></div>
            <div class="stat-label">মোট শিক্ষক</div>
        </div>
    </div>
    <div class="stat-card orange">
        <div class="stat-icon"><i class="fas fa-clipboard-check"></i></div>
        <div>
            <div class="stat-value"><?= toBanglaNumber($attendanceRate) ?>%</div>
            <div class="stat-label">আজকের উপস্থিতি</div>
        </div>
    </div>
    <div class="stat-card purple">
        <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
        <div>
            <div class="stat-value">৳<?= number_format($monthlyFee) ?></div>
            <div class="stat-label">এই মাসের ফি আদায়</div>
        </div>
    </div>
    <div class="stat-card red">
        <div class="stat-icon"><i class="fas fa-exclamation-circle"></i></div>
        <div>
            <div class="stat-value"><?= toBanglaNumber($dueCount) ?></div>
            <div class="stat-label">ফি বকেয়া ছাত্র</div>
        </div>
    </div>
    <div class="stat-card green">
        <div class="stat-icon"><i class="fas fa-fingerprint"></i></div>
        <div>
            <div class="stat-value"><?= toBanglaNumber($teacherCheckedIn) ?></div>
            <div class="stat-label">শিক্ষক আজ চেক ইন</div>
        </div>
    </div>
    <div class="stat-card red">
        <div class="stat-icon"><i class="fas fa-user-times"></i></div>
        <div>
            <div class="stat-value"><?= toBanglaNumber($teacherNotCheckedIn) ?></div>
            <div class="stat-label">এখনো আসেননি</div>
        </div>
    </div>
</div>

<div class="grid-2 mb-24">
    <!-- সাম্প্রতিক ভর্তি -->
    <div class="card">
        <div class="card-header">
            <span class="card-title"><i class="fas fa-user-plus"></i> সাম্প্রতিক ভর্তি</span>
            <a href="modules/student/list.php" class="btn btn-outline btn-sm">সব দেখুন</a>
        </div>
        <div class="card-body" style="padding:0;">
            <table>
                <thead>
                    <tr><th>নাম</th><th>শ্রেণী</th><th>ভর্তির তারিখ</th><th>অবস্থা</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($recentStudents)): ?>
                    <tr><td colspan="4" style="text-align:center;padding:20px;color:#718096;">কোনো তথ্য নেই</td></tr>
                    <?php else: foreach ($recentStudents as $s): ?>
                    <tr>
                        <td>
                            <div style="display:flex;align-items:center;gap:8px;">
                                <div class="avatar" style="font-size:12px;">
                                    <?= mb_substr($s['name_bn'] ?? $s['name'], 0, 1) ?>
                                </div>
                                <div>
                                    <div style="font-weight:600;font-size:13px;"><?= e($s['name_bn'] ?? $s['name']) ?></div>
                                    <div style="font-size:11px;color:#718096;"><?= e($s['student_id'] ?? '') ?></div>
                                </div>
                            </div>
                        </td>
                        <td><span class="badge badge-primary" style="font-size:11px;"><?= e($s['class_name_bn'] ?? '') ?></span></td>
                        <td style="font-size:13px;"><?= banglaDate($s['admission_date']) ?></td>
                        <td><span class="badge badge-success" style="font-size:11px;">সক্রিয়</span></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- নোটিশ বোর্ড -->
    <div class="card">
        <div class="card-header">
            <span class="card-title"><i class="fas fa-bullhorn"></i> নোটিশ বোর্ড</span>
            <a href="modules/notice/index.php" class="btn btn-outline btn-sm">নতুন নোটিশ</a>
        </div>
        <div class="card-body" style="padding:0;">
            <?php if (empty($notices)): ?>
            <div style="text-align:center;padding:30px;color:#718096;">কোনো নোটিশ নেই</div>
            <?php else: ?>
            <div style="display:flex;flex-direction:column;">
                <?php foreach ($notices as $n): ?>
                <div style="padding:14px 18px;border-bottom:1px solid var(--border);display:flex;gap:12px;align-items:flex-start;">
                    <div style="width:8px;height:8px;border-radius:50%;background:<?= $n['notice_type'] === 'urgent' ? 'var(--danger)' : 'var(--primary-light)' ?>;margin-top:6px;flex-shrink:0;"></div>
                    <div>
                        <div style="font-weight:600;font-size:14px;"><?= e($n['title_bn'] ?? $n['title']) ?></div>
                        <div style="font-size:12px;color:#718096;margin-top:3px;"><?= banglaDate($n['created_at']) ?></div>
                    </div>
                    <span class="badge badge-<?= $n['notice_type'] === 'urgent' ? 'danger' : 'info' ?>" style="font-size:10px;margin-left:auto;">
                        <?= e($n['notice_type']) ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- শিক্ষক উপস্থিতি টেবিল -->
<div class="card mb-24">
    <div class="card-header">
        <span class="card-title"><i class="fas fa-chalkboard-teacher"></i> আজকের শিক্ষক উপস্থিতি</span>
        <a href="modules/attendance/checkin.php" class="btn btn-outline btn-sm">বিস্তারিত</a>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>নাম</th><th>পদবী</th><th>চেক ইন</th><th>চেক আউট</th><th>মোট সময়</th><th>অবস্থা</th></tr>
            </thead>
            <tbody>
                <?php if(empty($teacherAttendanceToday)): ?>
                <tr><td colspan="6" style="text-align:center;padding:24px;color:var(--text-muted);">আজ এখনো কেউ চেক ইন করেননি</td></tr>
                <?php else: foreach($teacherAttendanceToday as $ta):
                    $totalH = '';
                    if($ta['check_in'] && $ta['check_out']) {
                        $d = strtotime($ta['check_out']) - strtotime($ta['check_in']);
                        $totalH = toBanglaNumber(floor($d/3600)).'ঘ '.toBanglaNumber(floor(($d%3600)/60)).'মি';
                    }
                ?>
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <div class="avatar" style="font-size:12px;"><?= mb_substr($ta['name_bn']??$ta['name'],0,1) ?></div>
                            <div>
                                <div style="font-weight:600;font-size:13px;"><?= e($ta['name_bn']??$ta['name']) ?></div>
                                <div style="font-size:11px;color:var(--text-muted);"><?= e($ta['teacher_id_no']??'')?></div>
                            </div>
                        </div>
                    </td>
                    <td style="font-size:12px;color:var(--text-muted);"><?= e($ta['designation_bn']??'')?></td>
                    <td style="color:var(--success);font-weight:600;font-size:13px;"><?= $ta['check_in'] ? date('h:i A',strtotime($ta['check_in'])) : '-' ?></td>
                    <td style="color:var(--danger);font-size:13px;"><?= $ta['check_out'] ? date('h:i A',strtotime($ta['check_out'])) : '<span style="color:var(--text-muted);">এখনো নেই</span>' ?></td>
                    <td style="font-size:13px;font-weight:600;"><?= $totalH ?: '-' ?></td>
                    <td>
                        <?php if($ta['check_out']): ?>
                        <span class="badge badge-success">সম্পন্ন</span>
                        <?php elseif($ta['check_in']): ?>
                        <span class="badge badge-warning">চেক ইন</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Quick Actions -->
<div class="card mb-24">
    <div class="card-header">
        <span class="card-title"><i class="fas fa-bolt"></i> দ্রুত অ্যাকশন</span>
    </div>
    <div class="card-body">
        <div style="display:flex;flex-wrap:wrap;gap:12px;">
            <a href="modules/student/admission.php" class="btn btn-primary"><i class="fas fa-user-plus"></i> নতুন ভর্তি</a>
            <a href="modules/attendance/index.php" class="btn btn-success"><i class="fas fa-clipboard-check"></i> উপস্থিতি নিন</a>
            <a href="modules/fees/collection.php" class="btn btn-warning"><i class="fas fa-money-bill"></i> ফি নিন</a>
            <a href="modules/exam/marks.php" class="btn btn-info"><i class="fas fa-pencil-alt"></i> নম্বর দিন</a>
            <a href="modules/notice/add.php" class="btn btn-accent"><i class="fas fa-bullhorn"></i> নোটিশ দিন</a>
            <a href="modules/ai/assistant.php" class="btn btn-outline"><i class="fas fa-robot"></i> AI সহকারী</a>
        </div>
    </div>
</div>

<!-- Attendance Chart (simple CSS) -->
<div class="card">
    <div class="card-header">
        <span class="card-title"><i class="fas fa-chart-bar"></i> শেষ ৭ দিনের উপস্থিতি</span>
    </div>
    <div class="card-body">
        <div style="display:flex;align-items:flex-end;gap:8px;height:120px;border-bottom:2px solid var(--border);padding-bottom:8px;">
            <?php foreach ($attendanceChart as $d):
                $h = $d['total'] > 0 ? ($d['present'] / $d['total']) * 100 : 0;
                $color = $h >= 80 ? 'var(--success)' : ($h >= 60 ? 'var(--warning)' : 'var(--danger)');
            ?>
            <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px;">
                <div style="font-size:10px;color:var(--text-muted);"><?= round($h) ?>%</div>
                <div style="width:100%;background:<?= $color ?>;border-radius:4px 4px 0 0;height:<?= max(4, $h) ?>px;transition:height .3s;"></div>
                <div style="font-size:10px;color:var(--text-muted);"><?= $d['date'] ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <div style="display:flex;gap:16px;margin-top:12px;font-size:12px;">
            <div style="display:flex;align-items:center;gap:4px;"><div style="width:12px;height:12px;background:var(--success);border-radius:2px;"></div> ৮০%+</div>
            <div style="display:flex;align-items:center;gap:4px;"><div style="width:12px;height:12px;background:var(--warning);border-radius:2px;"></div> ৬০-৮০%</div>
            <div style="display:flex;align-items:center;gap:4px;"><div style="width:12px;height:12px;background:var(--danger);border-radius:2px;"></div> ৬০%</div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
