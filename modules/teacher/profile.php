<?php
require_once '../../includes/functions.php';
requireLogin(['teacher']);
$pageTitle = 'আমার প্রোফাইল';
$db = getDB();
$userId = $_SESSION['user_id'];

// শিক্ষকের তথ্য
$stmt = $db->prepare("SELECT t.*, u.username, u.last_login FROM teachers t JOIN users u ON t.user_id=u.id WHERE t.user_id=?");
$stmt->execute([$userId]);
$teacher = $stmt->fetch();

if (!$teacher) {
    setFlash('danger', 'প্রোফাইল পাওয়া যায়নি।');
    header('Location: dashboard.php'); exit;
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

// ছুটির আবেদন সাবমিট
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_leave'])) {
    if (!verifyCsrf($_POST['csrf'] ?? '')) die('CSRF');
    $leaveType = $_POST['leave_type'] ?? 'casual';
    $fromDate  = $_POST['from_date'] ?? '';
    $toDate    = $_POST['to_date'] ?? '';
    $reason    = trim($_POST['reason'] ?? '');

    if ($fromDate && $toDate) {
        $days = (int)((strtotime($toDate) - strtotime($fromDate)) / 86400) + 1;
        $db->prepare("INSERT INTO teacher_leaves (teacher_id, leave_type, from_date, to_date, days, reason) VALUES (?,?,?,?,?,?)")
           ->execute([$teacher['id'], $leaveType, $fromDate, $toDate, $days, $reason]);
        logActivity($userId, 'leave_apply', 'teacher', "ছুটির আবেদন: $fromDate থেকে $toDate");
        setFlash('success', 'ছুটির আবেদন সফলভাবে জমা হয়েছে!');
    } else {
        setFlash('danger', 'তারিখ পূরণ করুন।');
    }
    header('Location: profile.php'); exit;
}

// পাসওয়ার্ড পরিবর্তন
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    if (!verifyCsrf($_POST['csrf'] ?? '')) die('CSRF');
    $currentPw  = $_POST['current_password'] ?? '';
    $newPw      = $_POST['new_password'] ?? '';
    $confirmPw  = $_POST['confirm_password'] ?? '';

    $userStmt = $db->prepare("SELECT password FROM users WHERE id=?");
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch();

    if (!password_verify($currentPw, $user['password'])) {
        setFlash('danger', 'বর্তমান পাসওয়ার্ড ভুল।');
    } elseif (strlen($newPw) < 6) {
        setFlash('danger', 'নতুন পাসওয়ার্ড কমপক্ষে ৬ অক্ষর হতে হবে।');
    } elseif ($newPw !== $confirmPw) {
        setFlash('danger', 'পাসওয়ার্ড মিলছে না।');
    } else {
        $db->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($newPw, PASSWORD_DEFAULT), $userId]);
        logActivity($userId, 'password_change', 'users', 'পাসওয়ার্ড পরিবর্তন');
        setFlash('success', 'পাসওয়ার্ড সফলভাবে পরিবর্তন হয়েছে!');
    }
    header('Location: profile.php'); exit;
}

// এই মাসের উপস্থিতি
$month = date('Y-m');
$attStmt = $db->prepare("SELECT COUNT(*) FROM teacher_attendance WHERE teacher_id=? AND DATE_FORMAT(date,'%Y-%m')=? AND status='present'");
$attStmt->execute([$teacher['id'], $month]);
$presentDays = $attStmt->fetchColumn();

// ছুটির আবেদন তালিকা
$leaves = $db->prepare("SELECT * FROM teacher_leaves WHERE teacher_id=? ORDER BY created_at DESC LIMIT 10");
$leaves->execute([$teacher['id']]);
$leaves = $leaves->fetchAll();

require_once '../../includes/teacher_header.php';
?>

<div class="section-header">
    <h2 class="section-title"><i class="fas fa-user-circle"></i> আমার প্রোফাইল</h2>
    <a href="dashboard.php" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> ড্যাশবোর্ড</a>
</div>

<div class="grid-2 mb-24">
    <!-- বাম: ব্যক্তিগত তথ্য -->
    <div>
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
                    <tr>
                        <td style="padding:10px 0;color:var(--text-muted);">এই মাসে উপস্থিতি</td>
                        <td style="padding:10px 0;font-weight:600;color:var(--primary);"><?= toBanglaNumber($presentDays) ?> দিন</td>
                    </tr>
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
    </div>

    <!-- ডান: পাসওয়ার্ড + ছুটি -->
    <div>
        <!-- পাসওয়ার্ড পরিবর্তন -->
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

        <!-- ছুটির আবেদন -->
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
    </div>
</div>

<!-- ছুটির আবেদন তালিকা -->
<div class="card">
    <div class="card-header"><span class="card-title"><i class="fas fa-list"></i> আবেদনের তালিকা</span></div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>ছুটির ধরন</th><th>শুরু</th><th>শেষ</th><th>দিন</th><th>কারণ</th><th>অবস্থা</th><th>মন্তব্য</th></tr>
            </thead>
            <tbody>
                <?php if(empty($leaves)): ?>
                <tr><td colspan="7" style="text-align:center;padding:20px;color:var(--text-muted);">কোনো আবেদন নেই</td></tr>
                <?php else: foreach($leaves as $l): ?>
                <?php
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
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
