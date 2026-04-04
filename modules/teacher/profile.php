<?php
require_once '../../includes/functions.php';
requireLogin(['super_admin','principal','teacher']);
$pageTitle = 'শিক্ষকের প্রোফাইল';
$db = getDB();

$currentRole = $_SESSION['role'] ?? '';
$currentUserId = $_SESSION['user_id'];

// Admin হলে ?id= দিয়ে যেকোনো শিক্ষক দেখতে পারবে
// Teacher হলে শুধু নিজেরটা
$isAdmin = in_array($currentRole, ['super_admin','principal']);

if ($isAdmin && isset($_GET['id'])) {
    $teacherDbId = (int)$_GET['id'];
    $stmt = $db->prepare("SELECT t.*, u.username, u.last_login FROM teachers t JOIN users u ON t.user_id=u.id WHERE t.id=?");
    $stmt->execute([$teacherDbId]);
} else {
    $stmt = $db->prepare("SELECT t.*, u.username, u.last_login FROM teachers t JOIN users u ON t.user_id=u.id WHERE t.user_id=?");
    $stmt->execute([$currentUserId]);
}

$teacher = $stmt->fetch();

if (!$teacher) {
    setFlash('danger', 'প্রোফাইল পাওয়া যায়নি।');
    header('Location: ' . ($isAdmin ? 'list.php' : 'dashboard.php')); exit;
}

// teacher_leaves টেবিল তৈরি (না থাকলে)
$db->exec("CREATE TABLE IF NOT EXISTS teacher_leaves (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    leave_type ENUM('sick','casual','emergency','other') DEFAULT 'casual',
    from_date DATE NOT NULL,
    to_date DATE NOT NULL,
    days INT NOT NULL DEFAULT 1,
    reason TEXT,
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    reviewed_by INT NULL,
    reviewed_at DATETIME NULL,
    review_note TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id)
)");

// ছুটির আবেদন সাবমিট (শুধু teacher নিজে করতে পারবে)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_leave']) && !$isAdmin) {
    if (!verifyCsrf($_POST['csrf'] ?? '')) die('CSRF');
    $leaveType = $_POST['leave_type'] ?? 'casual';
    $fromDate  = $_POST['from_date'] ?? '';
    $toDate    = $_POST['to_date'] ?? '';
    $reason    = trim($_POST['reason'] ?? '');

    if ($fromDate && $toDate) {
        $days = (int)((strtotime($toDate) - strtotime($fromDate)) / 86400) + 1;
        $db->prepare("INSERT INTO teacher_leaves (teacher_id, leave_type, from_date, to_date, days, reason) VALUES (?,?,?,?,?,?)")
           ->execute([$teacher['id'], $leaveType, $fromDate, $toDate, $days, $reason]);
        logActivity($currentUserId, 'leave_apply', 'teacher', "ছুটির আবেদন: $fromDate থেকে $toDate");
        setFlash('success', 'ছুটির আবেদন সফলভাবে জমা হয়েছে!');
    } else {
        setFlash('danger', 'তারিখ পূরণ করুন।');
    }
    header('Location: profile.php?' . ($isAdmin ? 'id='.$teacher['id'] : '')); exit;
}

// ছুটির অনুমোদন/প্রত্যাখ্যান (শুধু admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['review_leave']) && $isAdmin) {
    if (!verifyCsrf($_POST['csrf'] ?? '')) die('CSRF');
    $leaveId    = (int)($_POST['leave_id'] ?? 0);
    $action     = $_POST['action'] ?? '';
    $reviewNote = trim($_POST['review_note'] ?? '');
    if ($leaveId && in_array($action, ['approved','rejected'])) {
        $db->prepare("UPDATE teacher_leaves SET status=?, reviewed_by=?, reviewed_at=NOW(), review_note=? WHERE id=?")
           ->execute([$action, $currentUserId, $reviewNote, $leaveId]);
        setFlash('success', $action === 'approved' ? 'ছুটি অনুমোদন করা হয়েছে।' : 'ছুটি প্রত্যাখ্যাত হয়েছে।');
    }
    header('Location: profile.php?id='.$teacher['id']); exit;
}

// পাসওয়ার্ড পরিবর্তন (teacher নিজে)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password']) && !$isAdmin) {
    if (!verifyCsrf($_POST['csrf'] ?? '')) die('CSRF');
    $currentPw  = $_POST['current_password'] ?? '';
    $newPw      = $_POST['new_password'] ?? '';
    $confirmPw  = $_POST['confirm_password'] ?? '';

    $userStmt = $db->prepare("SELECT password FROM users WHERE id=?");
    $userStmt->execute([$currentUserId]);
    $user = $userStmt->fetch();

    if (!password_verify($currentPw, $user['password'])) {
        setFlash('danger', 'বর্তমান পাসওয়ার্ড ভুল।');
    } elseif (strlen($newPw) < 6) {
        setFlash('danger', 'নতুন পাসওয়ার্ড কমপক্ষে ৬ অক্ষর হতে হবে।');
    } elseif ($newPw !== $confirmPw) {
        setFlash('danger', 'পাসওয়ার্ড মিলছে না।');
    } else {
        $db->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($newPw, PASSWORD_DEFAULT), $currentUserId]);
        logActivity($currentUserId, 'password_change', 'users', 'পাসওয়ার্ড পরিবর্তন');
        setFlash('success', 'পাসওয়ার্ড সফলভাবে পরিবর্তন হয়েছে!');
    }
    header('Location: profile.php'); exit;
}

// Admin পাসওয়ার্ড রিসেট
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password']) && $isAdmin) {
    if (!verifyCsrf($_POST['csrf'] ?? '')) die('CSRF');
    $newRaw = substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789'), 0, 8);
    $db->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($newRaw, PASSWORD_DEFAULT), $teacher['user_id']]);
    $_SESSION['reset_pw_info'] = ['name' => $teacher['name_bn'] ?: $teacher['name'], 'pass' => $newRaw, 'phone' => $teacher['phone']];
    setFlash('success', 'পাসওয়ার্ড রিসেট হয়েছে!');
    header('Location: profile.php?id='.$teacher['id']); exit;
}

// এই মাসের উপস্থিতি
$month = date('Y-m');
$attStmt = $db->prepare("SELECT COUNT(*) FROM teacher_attendance WHERE teacher_id=? AND DATE_FORMAT(date,'%Y-%m')=? AND status='present'");
$attStmt->execute([$teacher['id'], $month]);
$presentDays = $attStmt->fetchColumn();

// মোট কার্যদিবস এই মাসে
$totalWorkDays = $db->query("SELECT COUNT(DISTINCT date) FROM teacher_attendance WHERE DATE_FORMAT(date,'%Y-%m')='$month'")->fetchColumn() ?: 0;

// বেতনের ইতিহাস (শেষ ৬ মাস)
$salaryHistory = $db->prepare("SELECT * FROM salary_payments WHERE teacher_id=? ORDER BY payment_date DESC LIMIT 6");
$salaryHistory->execute([$teacher['id']]);
$salaryHistory = $salaryHistory->fetchAll();

// ছুটির আবেদন তালিকা
$leavesStmt = $db->prepare("SELECT * FROM teacher_leaves WHERE teacher_id=? ORDER BY created_at DESC LIMIT 15");
$leavesStmt->execute([$teacher['id']]);
$leaves = $leavesStmt->fetchAll();

// পাসওয়ার্ড রিসেট info
$resetPwInfo = null;
if (!empty($_SESSION['reset_pw_info'])) {
    $resetPwInfo = $_SESSION['reset_pw_info'];
    unset($_SESSION['reset_pw_info']);
}

$headerFile = $isAdmin ? '../../includes/header.php' : '../../includes/teacher_header.php';
require_once $headerFile;
?>

<!-- Admin পাসওয়ার্ড রিসেট Copy-Box -->
<?php if ($resetPwInfo): ?>
<div class="modal-overlay" id="resetPwModal" style="display:flex;">
    <div class="modal-box" style="max-width:440px;">
        <div class="modal-header" style="background:var(--warning);color:#fff;border-radius:10px 10px 0 0;">
            <span style="font-weight:700;"><i class="fas fa-key"></i> পাসওয়ার্ড রিসেট হয়েছে</span>
        </div>
        <div class="card-body" style="padding:24px;">
            <p style="color:var(--text-muted);font-size:13px;margin-bottom:16px;">নতুন পাসওয়ার্ডটি শিক্ষককে জানিয়ে দিন।</p>
            <div style="background:var(--bg);border:2px dashed var(--border);border-radius:10px;padding:16px;font-size:14px;line-height:2;" id="resetPwText">
                <div><span style="color:var(--text-muted);">নাম:</span> <strong><?= e($resetPwInfo['name']) ?></strong></div>
                <div><span style="color:var(--text-muted);">ফোন:</span> <strong><?= e($resetPwInfo['phone']) ?></strong></div>
                <div><span style="color:var(--text-muted);">নতুন পাসওয়ার্ড:</span> <strong style="color:var(--danger);font-size:16px;letter-spacing:1px;"><?= e($resetPwInfo['pass']) ?></strong></div>
            </div>
            <div style="display:flex;gap:10px;margin-top:16px;">
                <button onclick="copyResetPw()" class="btn btn-primary" style="flex:1;" id="copyResetBtn"><i class="fas fa-copy"></i> কপি করুন</button>
                <button onclick="document.getElementById('resetPwModal').style.display='none'" class="btn btn-outline" style="flex:1;"><i class="fas fa-times"></i> বন্ধ করুন</button>
            </div>
        </div>
    </div>
</div>
<script>
function copyResetPw() {
    const text = `নাম: <?= e($resetPwInfo['name']) ?>\nফোন: <?= e($resetPwInfo['phone']) ?>\nনতুন পাসওয়ার্ড: <?= e($resetPwInfo['pass']) ?>`;
    navigator.clipboard.writeText(text).then(() => {
        const btn = document.getElementById('copyResetBtn');
        btn.innerHTML = '<i class="fas fa-check"></i> কপি হয়েছে!';
        btn.style.background = 'var(--success)';
        setTimeout(() => { btn.innerHTML = '<i class="fas fa-copy"></i> কপি করুন'; btn.style.background=''; }, 2500);
    });
}
</script>
<?php endif; ?>

<div class="section-header">
    <h2 class="section-title">
        <i class="fas fa-user-circle"></i>
        <?= $isAdmin ? e($teacher['name_bn'] ?: $teacher['name']).' — প্রোফাইল' : 'আমার প্রোফাইল' ?>
    </h2>
    <a href="<?= $isAdmin ? 'list.php' : 'dashboard.php' ?>" class="btn btn-outline btn-sm">
        <i class="fas fa-arrow-left"></i> <?= $isAdmin ? 'তালিকায় ফিরুন' : 'ড্যাশবোর্ড' ?>
    </a>
</div>

<div class="grid-2 mb-24">
    <!-- বাম: ব্যক্তিগত তথ্য -->
    <div>
        <!-- প্রোফাইল কার্ড -->
        <div class="card mb-16">
            <div class="card-body" style="text-align:center;padding:32px;">
                <div style="width:80px;height:80px;background:var(--primary);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:32px;font-weight:700;color:#fff;margin:0 auto 16px;">
                    <?= mb_substr($teacher['name_bn'] ?? $teacher['name'], 0, 1) ?>
                </div>
                <div style="font-size:20px;font-weight:700;"><?= e($teacher['name_bn'] ?? $teacher['name']) ?></div>
                <div style="color:var(--text-muted);font-size:14px;margin:4px 0;"><?= e($teacher['designation_bn'] ?? 'শিক্ষক') ?></div>
                <div style="background:var(--bg);border-radius:8px;padding:8px 16px;display:inline-block;font-size:13px;margin-top:8px;">
                    <strong>ID:</strong> <?= e($teacher['teacher_id_no']) ?>
                </div>
            </div>
        </div>

        <!-- ব্যক্তিগত তথ্য -->
        <div class="card mb-16">
            <div class="card-header"><span class="card-title"><i class="fas fa-info-circle"></i> ব্যক্তিগত তথ্য</span></div>
            <div class="card-body">
                <table style="width:100%;font-size:14px;">
                    <tr style="border-bottom:1px solid var(--border);">
                        <td style="padding:10px 0;color:var(--text-muted);width:45%;">ফোন নম্বর</td>
                        <td style="padding:10px 0;font-weight:600;"><?= e($teacher['phone']) ?></td>
                    </tr>
                    <tr style="border-bottom:1px solid var(--border);">
                        <td style="padding:10px 0;color:var(--text-muted);">যোগদানের তারিখ</td>
                        <td style="padding:10px 0;font-weight:600;"><?= banglaDate($teacher['joining_date'] ?? '') ?></td>
                    </tr>
                    <tr style="border-bottom:1px solid var(--border);">
                        <td style="padding:10px 0;color:var(--text-muted);">শিক্ষাগত যোগ্যতা</td>
                        <td style="padding:10px 0;font-weight:600;"><?= e($teacher['qualification'] ?? '-') ?></td>
                    </tr>
                    <tr style="border-bottom:1px solid var(--border);">
                        <td style="padding:10px 0;color:var(--text-muted);">মাসিক বেতন</td>
                        <td style="padding:10px 0;font-weight:600;color:var(--success);">৳ <?= number_format($teacher['salary'] ?? 0) ?></td>
                    </tr>
                    <tr style="border-bottom:1px solid var(--border);">
                        <td style="padding:10px 0;color:var(--text-muted);">এই মাসে উপস্থিতি</td>
                        <td style="padding:10px 0;font-weight:600;color:var(--primary);"><?= toBanglaNumber($presentDays) ?><?= $totalWorkDays ? '/'.toBanglaNumber($totalWorkDays) : '' ?> দিন</td>
                    </tr>
                    <?php if($teacher['last_login']): ?>
                    <tr>
                        <td style="padding:10px 0;color:var(--text-muted);">সর্বশেষ লগইন</td>
                        <td style="padding:10px 0;font-size:13px;color:var(--text-muted);"><?= banglaDate($teacher['last_login']) ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>

        <!-- CV ডাউনলোড -->
        <div class="card mb-16">
            <div class="card-body" style="text-align:center;padding:20px;">
                <a href="cv.php?id=<?= $teacher['id'] ?>" target="_blank" class="btn btn-primary" style="width:100%;">
                    <i class="fas fa-file-pdf"></i> CV ডাউনলোড করুন
                </a>
            </div>
        </div>

        <!-- বেতনের ইতিহাস -->
        <?php if(!empty($salaryHistory)): ?>
        <div class="card mb-16">
            <div class="card-header"><span class="card-title"><i class="fas fa-money-bill-wave"></i> বেতনের ইতিহাস</span></div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>মাস</th><th>পরিমাণ</th><th>অবস্থা</th></tr></thead>
                    <tbody>
                        <?php foreach($salaryHistory as $s): ?>
                        <tr>
                            <td style="font-size:13px;"><?= banglaDate($s['payment_date'] ?? '') ?></td>
                            <td style="font-weight:700;color:var(--success);">৳<?= number_format($s['amount'] ?? 0) ?></td>
                            <td><span class="badge badge-success">পরিশোধিত</span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- ডান কলাম -->
    <div>
        <!-- Admin: পাসওয়ার্ড রিসেট -->
        <?php if($isAdmin): ?>
        <div class="card mb-16">
            <div class="card-header"><span class="card-title"><i class="fas fa-key"></i> পাসওয়ার্ড রিসেট</span></div>
            <div class="card-body">
                <p style="font-size:13px;color:var(--text-muted);margin-bottom:16px;">শিক্ষক পাসওয়ার্ড ভুলে গেলে এখান থেকে রিসেট করুন। নতুন পাসওয়ার্ড কপি করে শিক্ষককে জানান।</p>
                <form method="POST" onsubmit="return confirm('পাসওয়ার্ড রিসেট করবেন?')">
                    <input type="hidden" name="csrf" value="<?= getCsrfToken() ?>">
                    <input type="hidden" name="reset_password" value="1">
                    <button type="submit" class="btn btn-warning" style="width:100%;">
                        <i class="fas fa-redo"></i> নতুন পাসওয়ার্ড তৈরি করুন
                    </button>
                </form>
            </div>
        </div>

        <!-- Admin: ছুটির আবেদন রিভিউ -->
        <?php if(!empty($leaves)): ?>
        <div class="card mb-16">
            <div class="card-header"><span class="card-title"><i class="fas fa-calendar-check"></i> ছুটির আবেদন রিভিউ</span></div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>ধরন</th><th>তারিখ</th><th>দিন</th><th>অবস্থা</th><th>অ্যাকশন</th></tr></thead>
                    <tbody>
                    <?php foreach($leaves as $l):
                        $typeLabel = ['casual'=>'সাধারণ','sick'=>'অসুস্থতা','emergency'=>'জরুরি','other'=>'অন্যান্য'][$l['leave_type']] ?? $l['leave_type'];
                        $statusBadge = ['pending'=>'<span class="badge badge-warning">অপেক্ষমান</span>','approved'=>'<span class="badge badge-success">অনুমোদিত</span>','rejected'=>'<span class="badge badge-danger">প্রত্যাখ্যাত</span>'][$l['status']] ?? $l['status'];
                    ?>
                    <tr>
                        <td style="font-size:12px;"><?= $typeLabel ?></td>
                        <td style="font-size:12px;"><?= banglaDate($l['from_date']) ?><br><span style="color:var(--text-muted);">→ <?= banglaDate($l['to_date']) ?></span></td>
                        <td style="font-weight:700;"><?= toBanglaNumber($l['days']) ?></td>
                        <td><?= $statusBadge ?></td>
                        <td>
                            <?php if($l['status'] === 'pending'): ?>
                            <button onclick="openModal('reviewLeave<?= $l['id'] ?>')" class="btn btn-primary btn-xs"><i class="fas fa-gavel"></i></button>
                            <?php else: ?>
                            <span style="font-size:11px;color:var(--text-muted);"><?= e($l['review_note'] ?? '') ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <!-- Teacher: পাসওয়ার্ড পরিবর্তন -->
        <div class="card mb-16">
            <div class="card-header"><span class="card-title"><i class="fas fa-lock"></i> পাসওয়ার্ড পরিবর্তন</span></div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf" value="<?= getCsrfToken() ?>">
                    <input type="hidden" name="change_password" value="1">
                    <div class="form-group mb-16">
                        <label>বর্তমান পাসওয়ার্ড</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    <div class="form-group mb-16">
                        <label>নতুন পাসওয়ার্ড</label>
                        <input type="password" name="new_password" class="form-control" required minlength="6">
                    </div>
                    <div class="form-group mb-16">
                        <label>পাসওয়ার্ড নিশ্চিত করুন</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width:100%;">
                        <i class="fas fa-save"></i> পাসওয়ার্ড পরিবর্তন করুন
                    </button>
                </form>
            </div>
        </div>

        <!-- Teacher: ছুটির আবেদন -->
        <div class="card mb-16">
            <div class="card-header"><span class="card-title"><i class="fas fa-calendar-times"></i> ছুটির আবেদন</span></div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf" value="<?= getCsrfToken() ?>">
                    <input type="hidden" name="apply_leave" value="1">
                    <div class="form-grid mb-16">
                        <div class="form-group">
                            <label>ছুটির ধরন</label>
                            <select name="leave_type" class="form-control">
                                <option value="casual">সাধারণ ছুটি</option>
                                <option value="sick">অসুস্থতাজনিত ছুটি</option>
                                <option value="emergency">জরুরি ছুটি</option>
                                <option value="other">অন্যান্য</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>শুরুর তারিখ</label>
                            <input type="date" name="from_date" class="form-control" required min="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="form-group">
                            <label>শেষের তারিখ</label>
                            <input type="date" name="to_date" class="form-control" required min="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="form-group" style="grid-column:1/-1;">
                            <label>কারণ</label>
                            <textarea name="reason" class="form-control" rows="3" placeholder="ছুটির কারণ লিখুন..."></textarea>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-warning" style="width:100%;">
                        <i class="fas fa-paper-plane"></i> আবেদন জমা দিন
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ছুটির আবেদন তালিকা (Teacher view) -->
<?php if(!$isAdmin && !empty($leaves)): ?>
<div class="card">
    <div class="card-header"><span class="card-title"><i class="fas fa-list"></i> আবেদনের তালিকা</span></div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>ছুটির ধরন</th><th>শুরু</th><th>শেষ</th><th>দিন</th><th>কারণ</th><th>অবস্থা</th><th>মন্তব্য</th></tr>
            </thead>
            <tbody>
                <?php foreach($leaves as $l):
                    $typeLabel = ['casual'=>'সাধারণ','sick'=>'অসুস্থতা','emergency'=>'জরুরি','other'=>'অন্যান্য'][$l['leave_type']] ?? $l['leave_type'];
                    $statusLabel = ['pending'=>'<span class="badge badge-warning">অপেক্ষমান</span>','approved'=>'<span class="badge badge-success">অনুমোদিত</span>','rejected'=>'<span class="badge badge-danger">প্রত্যাখ্যাত</span>'][$l['status']] ?? $l['status'];
                ?>
                <tr>
                    <td><?= $typeLabel ?></td>
                    <td><?= banglaDate($l['from_date']) ?></td>
                    <td><?= banglaDate($l['to_date']) ?></td>
                    <td style="font-weight:700;"><?= toBanglaNumber($l['days']) ?></td>
                    <td style="font-size:13px;max-width:150px;"><?= e($l['reason'] ?? '-') ?></td>
                    <td><?= $statusLabel ?></td>
                    <td style="font-size:13px;color:var(--text-muted);"><?= e($l['review_note'] ?? '-') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Leave Review Modals (Admin) -->
<?php if($isAdmin): foreach($leaves as $l): if($l['status']==='pending'): ?>
<div class="modal-overlay" id="reviewLeave<?= $l['id'] ?>">
    <div class="modal-box" style="max-width:420px;">
        <div class="modal-header">
            <span style="font-weight:700;"><i class="fas fa-gavel"></i> ছুটির আবেদন রিভিউ</span>
            <button onclick="closeModal('reviewLeave<?= $l['id'] ?>')" class="btn btn-outline btn-xs">✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="csrf" value="<?= getCsrfToken() ?>">
            <input type="hidden" name="review_leave" value="1">
            <input type="hidden" name="leave_id" value="<?= $l['id'] ?>">
            <div class="modal-body">
                <div style="background:var(--bg);border-radius:8px;padding:12px;margin-bottom:16px;font-size:13px;">
                    <strong><?= ['casual'=>'সাধারণ','sick'=>'অসুস্থতা','emergency'=>'জরুরি','other'=>'অন্যান্য'][$l['leave_type']] ?> ছুটি</strong><br>
                    <?= banglaDate($l['from_date']) ?> → <?= banglaDate($l['to_date']) ?> (<?= toBanglaNumber($l['days']) ?> দিন)<br>
                    <span style="color:var(--text-muted);"><?= e($l['reason'] ?? '') ?></span>
                </div>
                <div class="form-group">
                    <label>মন্তব্য (ঐচ্ছিক)</label>
                    <textarea name="review_note" class="form-control" rows="2" placeholder="কারণ বা নির্দেশনা..."></textarea>
                </div>
            </div>
            <div class="modal-footer" style="gap:8px;">
                <button type="button" onclick="closeModal('reviewLeave<?= $l['id'] ?>')" class="btn btn-outline">বাতিল</button>
                <button type="submit" name="action" value="rejected" class="btn btn-danger"><i class="fas fa-times"></i> প্রত্যাখ্যান</button>
                <button type="submit" name="action" value="approved" class="btn btn-success"><i class="fas fa-check"></i> অনুমোদন</button>
            </div>
        </form>
    </div>
</div>
<?php endif; endforeach; endif; ?>

<?php require_once '../../includes/footer.php'; ?>
