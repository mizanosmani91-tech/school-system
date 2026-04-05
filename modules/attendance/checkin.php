<?php
if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}

require_once '../../includes/functions.php';
requireLogin();
$pageTitle = 'চেক ইন / চেক আউট';
$db = getDB();

$roleSlug    = $_SESSION['role_slug'] ?? '';
$userId      = $_SESSION['user_id'];
$isAdmin     = in_array($roleSlug, ['super_admin', 'principal']);

// ===== IP CHECK =====
$ALLOWED_IPS = [
    '182.48.68.103',
    '127.0.0.1',
    '::1',
];
$userIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
if (str_contains($userIp, ',')) $userIp = trim(explode(',', $userIp)[0]);
$isAllowedIP = in_array($userIp, $ALLOWED_IPS);

// ===== USER INFO =====
$currentUser = getCurrentUser();

// Teacher বা Staff — teachers টেবিলে আছে কিনা দেখো
$staffRecord = null;
$staffStmt = $db->prepare("SELECT * FROM teachers WHERE user_id=?");
$staffStmt->execute([$userId]);
$staffRecord = $staffStmt->fetch();

// ===== TABLE তৈরি =====
$db->exec("CREATE TABLE IF NOT EXISTS teacher_attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    date DATE NOT NULL,
    check_in TIME NULL,
    check_out TIME NULL,
    ip_address VARCHAR(45),
    status ENUM('present','absent','half_day','leave') DEFAULT 'present',
    note TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_teacher_date (teacher_id, date),
    FOREIGN KEY (teacher_id) REFERENCES teachers(id)
)");

// ===== আজকের রেকর্ড =====
$today = date('Y-m-d');
$todayRecord = null;
if ($staffRecord) {
    $stmt = $db->prepare("SELECT * FROM teacher_attendance WHERE teacher_id=? AND date=?");
    $stmt->execute([$staffRecord['id'], $today]);
    $todayRecord = $stmt->fetch();
}

// ===== AJAX চেক ইন/আউট =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    if (!verifyCsrf($_POST['csrf'] ?? '')) {
        echo json_encode(['success' => false, 'msg' => 'CSRF error']);
        exit;
    }

    if (!$isAllowedIP) {
        echo json_encode(['success' => false, 'msg' => '⚠️ আপনি মাদ্রাসার নেটওয়ার্কে নেই। চেক ইন/আউট শুধুমাত্র মাদ্রাসার WiFi থেকে করা যাবে।']);
        exit;
    }

    if (!$staffRecord) {
        echo json_encode(['success' => false, 'msg' => 'স্টাফ প্রোফাইল পাওয়া যায়নি।']);
        exit;
    }

    $action = $_POST['action'];
    $now    = date('H:i:s');
    $note   = trim($_POST['note'] ?? '');

    if ($action === 'checkin') {
        if ($todayRecord && $todayRecord['check_in']) {
            echo json_encode(['success' => false, 'msg' => 'আজকে ইতিমধ্যে চেক ইন করা হয়েছে: ' . date('h:i A', strtotime($todayRecord['check_in']))]);
            exit;
        }
        $db->prepare("INSERT INTO teacher_attendance (teacher_id,date,check_in,ip_address,status,note)
            VALUES (?,?,?,?,'present',?)
            ON DUPLICATE KEY UPDATE check_in=VALUES(check_in), ip_address=VALUES(ip_address), note=VALUES(note)")
           ->execute([$staffRecord['id'], $today, $now, $userIp, $note]);
        logActivity($userId, 'check_in', 'attendance', "চেক ইন: $now IP: $userIp");
        echo json_encode(['success' => true, 'msg' => '✅ চেক ইন সফল! সময়: ' . date('h:i A', strtotime($now)), 'time' => $now, 'action' => 'checkin']);

    } elseif ($action === 'checkout') {
        // রেকর্ড রিফ্রেশ
        $stmt = $db->prepare("SELECT * FROM teacher_attendance WHERE teacher_id=? AND date=?");
        $stmt->execute([$staffRecord['id'], $today]);
        $todayRecord = $stmt->fetch();

        if (!$todayRecord || !$todayRecord['check_in']) {
            echo json_encode(['success' => false, 'msg' => 'আগে চেক ইন করুন।']);
            exit;
        }
        if ($todayRecord['check_out']) {
            echo json_encode(['success' => false, 'msg' => 'আজকে ইতিমধ্যে চেক আউট করা হয়েছে: ' . date('h:i A', strtotime($todayRecord['check_out']))]);
            exit;
        }
        $db->prepare("UPDATE teacher_attendance SET check_out=?, ip_address=?, note=CONCAT(IFNULL(note,''), IF(note IS NULL OR note='', '', ' | '), ?) WHERE teacher_id=? AND date=?")
           ->execute([$now, $userIp, $note, $staffRecord['id'], $today]);
        logActivity($userId, 'check_out', 'attendance', "চেক আউট: $now IP: $userIp");

        $diff  = strtotime($now) - strtotime($todayRecord['check_in']);
        $hours = floor($diff / 3600);
        $mins  = floor(($diff % 3600) / 60);
        echo json_encode(['success' => true, 'msg' => "✅ চেক আউট সফল! সময়: " . date('h:i A', strtotime($now)) . " (মোট: {$hours}ঘ {$mins}মি)", 'time' => $now, 'action' => 'checkout']);
    }
    exit;
}

// ===== মাসিক রিপোর্ট =====
$month      = $_GET['month'] ?? date('Y-m');
$reportData = [];
if ($staffRecord) {
    $stmt = $db->prepare("SELECT * FROM teacher_attendance WHERE teacher_id=? AND DATE_FORMAT(date,'%Y-%m')=? ORDER BY date DESC");
    $stmt->execute([$staffRecord['id'], $month]);
    $reportData = $stmt->fetchAll();
}

// ===== আজকের সব স্টাফ (Admin view) =====
$allTodayAttendance = [];
if ($isAdmin) {
    try {
        $allTodayAttendance = $db->query("
            SELECT ta.*, t.name_bn, t.name, t.designation_bn
            FROM teacher_attendance ta
            JOIN teachers t ON ta.teacher_id = t.id
            WHERE ta.date = '$today'
            ORDER BY ta.check_in ASC
        ")->fetchAll();
    } catch (Exception $e) {}
}

// ===== যারা আজও চেক ইন করেনি (Admin view) =====
$notCheckedIn = [];
if ($isAdmin) {
    try {
        $notCheckedIn = $db->query("
            SELECT t.id, t.name_bn, t.name, t.designation_bn
            FROM teachers t
            WHERE t.is_active = 1
            AND t.id NOT IN (
                SELECT teacher_id FROM teacher_attendance WHERE date = '$today'
            )
            ORDER BY t.name_bn
        ")->fetchAll();
    } catch (Exception $e) {}
}

// header নির্ধারণ
if ($isAdmin) {
    require_once '../../includes/header.php';
} else {
    require_once '../../includes/teacher_header.php';
}
?>

<div class="section-header">
    <h2 class="section-title"><i class="fas fa-fingerprint"></i> চেক ইন / চেক আউট</h2>
    <?php if ($isAdmin): ?>
    <a href="report.php" class="btn btn-outline btn-sm"><i class="fas fa-chart-bar"></i> রিপোর্ট</a>
    <?php endif; ?>
</div>

<!-- IP Status -->
<?php if ($isAllowedIP): ?>
<div class="alert alert-success mb-16">
    <i class="fas fa-wifi"></i> <strong>মাদ্রাসার নেটওয়ার্ক সংযুক্ত</strong> — আপনি চেক ইন/আউট করতে পারবেন।
    <span style="opacity:.6;font-size:12px;margin-left:8px;">IP: <?= e($userIp) ?></span>
</div>
<?php else: ?>
<div class="alert alert-warning mb-16">
    <i class="fas fa-exclamation-triangle"></i>
    <strong>মাদ্রাসার নেটওয়ার্কে নেই</strong> — চেক ইন/আউট শুধুমাত্র মাদ্রাসার WiFi থেকে করা যাবে।
    আপনার IP: <code><?= e($userIp) ?></code>
</div>
<?php endif; ?>

<?php if ($staffRecord): ?>
<!-- ===== চেক ইন/আউট কার্ড ===== -->
<div class="card mb-24">
    <div class="card-body" style="padding:40px 24px;text-align:center;">

        <!-- নাম ও পদবী -->
        <div style="margin-bottom:16px;">
            <div style="width:64px;height:64px;background:var(--primary);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:700;color:#fff;margin:0 auto 10px;">
                <?= mb_substr($staffRecord['name_bn'] ?? $staffRecord['name'], 0, 1) ?>
            </div>
            <div style="font-size:18px;font-weight:700;"><?= e($staffRecord['name_bn'] ?? $staffRecord['name']) ?></div>
            <div style="font-size:13px;color:var(--text-muted);"><?= e($staffRecord['designation_bn'] ?? '') ?> &bull; <?= e($staffRecord['teacher_id_no'] ?? '') ?></div>
        </div>

        <!-- বড় ঘড়ি -->
        <div style="font-size:56px;font-weight:700;color:var(--primary);font-variant-numeric:tabular-nums;line-height:1;margin-bottom:4px;" id="liveClock">
            <?= date('H:i:s') ?>
        </div>
        <div style="font-size:15px;color:var(--text-muted);margin-bottom:28px;"><?= banglaDate() ?></div>

        <!-- আজকের স্ট্যাটাস -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;max-width:420px;margin:0 auto 28px;">
            <div style="background:<?= $todayRecord && $todayRecord['check_in'] ? '#eafaf1' : '#f7fafc' ?>;border-radius:14px;padding:18px;border:2px solid <?= $todayRecord && $todayRecord['check_in'] ? 'var(--success)' : 'var(--border)' ?>;">
                <div style="font-size:11px;color:var(--text-muted);margin-bottom:6px;text-transform:uppercase;letter-spacing:.5px;">চেক ইন</div>
                <div style="font-size:26px;font-weight:700;color:<?= $todayRecord && $todayRecord['check_in'] ? 'var(--success)' : 'var(--text-muted)' ?>;">
                    <?= $todayRecord && $todayRecord['check_in'] ? date('h:i', strtotime($todayRecord['check_in'])) : '--:--' ?>
                </div>
                <?php if ($todayRecord && $todayRecord['check_in']): ?>
                <div style="font-size:11px;color:var(--success);margin-top:4px;"><i class="fas fa-check-circle"></i> সম্পন্ন</div>
                <?php endif; ?>
            </div>
            <div style="background:<?= $todayRecord && $todayRecord['check_out'] ? '#fdedec' : '#f7fafc' ?>;border-radius:14px;padding:18px;border:2px solid <?= $todayRecord && $todayRecord['check_out'] ? 'var(--danger)' : 'var(--border)' ?>;">
                <div style="font-size:11px;color:var(--text-muted);margin-bottom:6px;text-transform:uppercase;letter-spacing:.5px;">চেক আউট</div>
                <div style="font-size:26px;font-weight:700;color:<?= $todayRecord && $todayRecord['check_out'] ? 'var(--danger)' : 'var(--text-muted)' ?>;">
                    <?= $todayRecord && $todayRecord['check_out'] ? date('h:i', strtotime($todayRecord['check_out'])) : '--:--' ?>
                </div>
                <?php if ($todayRecord && $todayRecord['check_out']): ?>
                <?php
                    $diff  = strtotime($todayRecord['check_out']) - strtotime($todayRecord['check_in']);
                    $hrs   = floor($diff / 3600);
                    $mins  = floor(($diff % 3600) / 60);
                ?>
                <div style="font-size:11px;color:var(--danger);margin-top:4px;">মোট: <?= toBanglaNumber($hrs) ?>ঘ <?= toBanglaNumber($mins) ?>মি</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- নোট ফিল্ড -->
        <div style="max-width:420px;margin:0 auto 20px;">
            <textarea id="noteField" class="form-control" rows="2" placeholder="নোট লিখুন (ঐচ্ছিক)..."></textarea>
        </div>

        <!-- বাটন -->
        <?php
        $checkedIn  = $todayRecord && $todayRecord['check_in'];
        $checkedOut = $todayRecord && $todayRecord['check_out'];
        ?>
        <div style="display:flex;gap:14px;justify-content:center;flex-wrap:wrap;">
            <button onclick="doAction('checkin')" id="btnCheckin"
                class="btn btn-success"
                style="padding:16px 40px;font-size:17px;border-radius:12px;<?= $checkedIn ? 'opacity:.45;cursor:not-allowed;' : '' ?>"
                <?= $checkedIn || !$isAllowedIP ? 'disabled' : '' ?>>
                <i class="fas fa-sign-in-alt"></i> চেক ইন
            </button>
            <button onclick="doAction('checkout')" id="btnCheckout"
                class="btn btn-danger"
                style="padding:16px 40px;font-size:17px;border-radius:12px;<?= (!$checkedIn || $checkedOut) ? 'opacity:.45;cursor:not-allowed;' : '' ?>"
                <?= (!$checkedIn || $checkedOut) || !$isAllowedIP ? 'disabled' : '' ?>>
                <i class="fas fa-sign-out-alt"></i> চেক আউট
            </button>
        </div>

        <div id="actionResult" style="margin-top:20px;display:none;"></div>
    </div>
</div>

<!-- ===== মাসিক উপস্থিতি ===== -->
<div class="card mb-24">
    <div class="card-header">
        <span class="card-title"><i class="fas fa-calendar-alt"></i> মাসিক উপস্থিতি</span>
        <form method="GET" style="display:flex;gap:8px;">
            <input type="month" name="month" class="form-control" style="padding:5px 10px;width:auto;" value="<?= e($month) ?>" onchange="this.form.submit()">
        </form>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>তারিখ</th><th>চেক ইন</th><th>চেক আউট</th><th>মোট সময়</th><th>অবস্থা</th></tr>
            </thead>
            <tbody>
                <?php if (empty($reportData)): ?>
                <tr><td colspan="5" style="text-align:center;padding:24px;color:var(--text-muted);">এই মাসে কোনো তথ্য নেই</td></tr>
                <?php else: foreach ($reportData as $r):
                    $totalHours = '';
                    if ($r['check_in'] && $r['check_out']) {
                        $d = strtotime($r['check_out']) - strtotime($r['check_in']);
                        $totalHours = toBanglaNumber(floor($d/3600)).'ঘ '.toBanglaNumber(floor(($d%3600)/60)).'মি';
                    }
                ?>
                <tr>
                    <td><?= banglaDate($r['date']) ?></td>
                    <td style="color:var(--success);font-weight:600;"><?= $r['check_in'] ? date('h:i A', strtotime($r['check_in'])) : '-' ?></td>
                    <td style="color:var(--danger);font-weight:600;"><?= $r['check_out'] ? date('h:i A', strtotime($r['check_out'])) : '-' ?></td>
                    <td style="font-weight:600;"><?= $totalHours ?: '-' ?></td>
                    <td><span class="badge badge-<?= $r['status']==='present' ? 'success' : ($r['status']==='absent' ? 'danger' : 'warning') ?>">
                        <?= ['present'=>'উপস্থিত','absent'=>'অনুপস্থিত','half_day'=>'অর্ধদিন','leave'=>'ছুটি'][$r['status']] ?? $r['status'] ?>
                    </span></td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- ===== Admin: আজকের সব স্টাফ ===== -->
<?php if ($isAdmin): ?>
<div class="grid-2 mb-16">

    <!-- চেক ইন করেছে -->
    <div class="card">
        <div class="card-header">
            <span class="card-title"><i class="fas fa-users" style="color:var(--success);"></i> আজ উপস্থিত (<?= toBanglaNumber(count($allTodayAttendance)) ?>)</span>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>নাম</th><th>চেক ইন</th><th>চেক আউট</th><th>মোট</th></tr></thead>
                <tbody>
                    <?php if (empty($allTodayAttendance)): ?>
                    <tr><td colspan="4" style="text-align:center;padding:20px;color:var(--text-muted);">এখনো কেউ চেক ইন করেনি</td></tr>
                    <?php else: foreach ($allTodayAttendance as $r):
                        $totalH = '';
                        if ($r['check_in'] && $r['check_out']) {
                            $d = strtotime($r['check_out']) - strtotime($r['check_in']);
                            $totalH = floor($d/3600).'ঘ '.floor(($d%3600)/60).'মি';
                        }
                    ?>
                    <tr>
                        <td>
                            <div style="font-weight:600;font-size:13px;"><?= e($r['name_bn'] ?? $r['name']) ?></div>
                            <div style="font-size:11px;color:var(--text-muted);"><?= e($r['designation_bn'] ?? '') ?></div>
                        </td>
                        <td style="color:var(--success);font-weight:600;font-size:13px;"><?= $r['check_in'] ? date('h:i A', strtotime($r['check_in'])) : '-' ?></td>
                        <td style="color:var(--danger);font-size:13px;"><?= $r['check_out'] ? date('h:i A', strtotime($r['check_out'])) : '<span style="color:var(--text-muted);">এখনো নেই</span>' ?></td>
                        <td style="font-size:13px;"><?= $totalH ?: '-' ?></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- চেক ইন করেনি -->
    <div class="card">
        <div class="card-header">
            <span class="card-title"><i class="fas fa-user-times" style="color:var(--danger);"></i> এখনো আসেনি (<?= toBanglaNumber(count($notCheckedIn)) ?>)</span>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>নাম</th><th>পদবী</th></tr></thead>
                <tbody>
                    <?php if (empty($notCheckedIn)): ?>
                    <tr><td colspan="2" style="text-align:center;padding:20px;color:var(--success);">সবাই চেক ইন করেছে! ✅</td></tr>
                    <?php else: foreach ($notCheckedIn as $r): ?>
                    <tr>
                        <td style="font-weight:600;font-size:13px;"><?= e($r['name_bn'] ?? $r['name']) ?></td>
                        <td style="font-size:12px;color:var(--text-muted);"><?= e($r['designation_bn'] ?? '-') ?></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// লাইভ ঘড়ি
setInterval(() => {
    const now = new Date();
    const h = String(now.getHours()).padStart(2,'0');
    const m = String(now.getMinutes()).padStart(2,'0');
    const s = String(now.getSeconds()).padStart(2,'0');
    document.getElementById('liveClock').textContent = h+':'+m+':'+s;
}, 1000);

function doAction(action) {
    const note = document.getElementById('noteField')?.value || '';
    const resultDiv = document.getElementById('actionResult');
    const btn = document.getElementById(action === 'checkin' ? 'btnCheckin' : 'btnCheckout');

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> অপেক্ষা করুন...';
    resultDiv.style.display = 'none';

    fetch('', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({action, note, csrf: '<?= getCsrfToken() ?>'})
    })
    .then(r => r.json())
    .then(data => {
        resultDiv.style.display = 'block';
        resultDiv.innerHTML = `<div class="alert alert-${data.success ? 'success' : 'danger'}" style="display:inline-flex;gap:8px;">
            <i class="fas fa-${data.success ? 'check-circle' : 'exclamation-circle'}"></i> ${data.msg}
        </div>`;
        if (data.success) setTimeout(() => location.reload(), 2000);
        else { btn.disabled = false; btn.innerHTML = action === 'checkin' ? '<i class="fas fa-sign-in-alt"></i> চেক ইন' : '<i class="fas fa-sign-out-alt"></i> চেক আউট'; }
    })
    .catch(() => {
        resultDiv.style.display = 'block';
        resultDiv.innerHTML = '<div class="alert alert-danger">সংযোগ ব্যর্থ হয়েছে।</div>';
        btn.disabled = false;
    });
}
</script>

<?php require_once '../../includes/footer.php'; ?>
