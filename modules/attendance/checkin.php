<?php
// PHP 7.x compatibility polyfill
if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}

require_once '../../includes/functions.php';
requireLogin(['teacher','accountant']);
$pageTitle = 'চেক ইন / চেক আউট';
$db = getDB();

// মাদ্রাসার অনুমোদিত IP ঠিকানা
$ALLOWED_IPS = [
    '182.48.68.103',  // মাদ্রাসার মূল IP
    '127.0.0.1',      // localhost (development)
    '::1',            // localhost IPv6
];

// ব্যবহারকারীর IP
$userIp = $_SERVER['HTTP_X_FORWARDED_FOR']
    ?? $_SERVER['HTTP_X_REAL_IP']
    ?? $_SERVER['REMOTE_ADDR']
    ?? '';

// একাধিক IP থাকলে প্রথমটা নিন
if (str_contains($userIp, ',')) {
    $userIp = trim(explode(',', $userIp)[0]);
}

$isAllowedIP = in_array($userIp, $ALLOWED_IPS);
$currentUser = getCurrentUser();
$userId = $_SESSION['user_id'];

// Teacher info
$teacher = null;
$teacherStmt = $db->prepare("SELECT * FROM teachers WHERE user_id=?");
$teacherStmt->execute([$userId]);
$teacher = $teacherStmt->fetch();

// আজকের চেক ইন তথ্য
$today = date('Y-m-d');
$todayRecord = null;
if ($teacher) {
    $stmt = $db->prepare("SELECT * FROM teacher_attendance WHERE teacher_id=? AND date=?");
    $stmt->execute([$teacher['id'], $today]);
    $todayRecord = $stmt->fetch();
}

// চেক ইন / চেক আউট হ্যান্ডেল
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    if (!$isAllowedIP) {
        echo json_encode(['success'=>false,'msg'=>'আপনি মাদ্রাসার নেটওয়ার্কে নেই। চেক ইন/আউট শুধুমাত্র মাদ্রাসার WiFi থেকে করা যাবে।']);
        exit;
    }

    if (!$teacher) {
        echo json_encode(['success'=>false,'msg'=>'শিক্ষক প্রোফাইল পাওয়া যায়নি।']);
        exit;
    }

    $action = $_POST['action'];
    $now = date('H:i:s');
    $note = trim($_POST['note']??'');

    // Check if table exists, if not create it
    $db->exec("CREATE TABLE IF NOT EXISTS teacher_attendance (
        id INT AUTO_INCREMENT PRIMARY KEY,
        teacher_id INT NOT NULL,
        date DATE NOT NULL,
        check_in TIME,
        check_out TIME,
        ip_address VARCHAR(45),
        status ENUM('present','absent','half_day','leave') DEFAULT 'present',
        note TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_teacher_date (teacher_id, date),
        FOREIGN KEY (teacher_id) REFERENCES teachers(id)
    )");

    if ($action === 'checkin') {
        if ($todayRecord && $todayRecord['check_in']) {
            echo json_encode(['success'=>false,'msg'=>'আজকে ইতিমধ্যে চেক ইন করা হয়েছে: '.$todayRecord['check_in']]);
            exit;
        }
        $stmt = $db->prepare("INSERT INTO teacher_attendance (teacher_id,date,check_in,ip_address,status,note)
            VALUES (?,?,?,?,'present',?)
            ON DUPLICATE KEY UPDATE check_in=VALUES(check_in), ip_address=VALUES(ip_address)");
        $stmt->execute([$teacher['id'],$today,$now,$userIp,$note]);
        logActivity($userId,'check_in','attendance',"চেক ইন: $now IP: $userIp");
        echo json_encode(['success'=>true,'msg'=>'✅ চেক ইন সফল! সময়: '.$now,'time'=>$now,'action'=>'checkin']);

    } elseif ($action === 'checkout') {
        if (!$todayRecord || !$todayRecord['check_in']) {
            echo json_encode(['success'=>false,'msg'=>'আগে চেক ইন করুন।']);
            exit;
        }
        if ($todayRecord['check_out']) {
            echo json_encode(['success'=>false,'msg'=>'আজকে ইতিমধ্যে চেক আউট করা হয়েছে: '.$todayRecord['check_out']]);
            exit;
        }
        $db->prepare("UPDATE teacher_attendance SET check_out=?, ip_address=?, note=CONCAT(IFNULL(note,''),' | আউট নোট: ',?) WHERE teacher_id=? AND date=?")
           ->execute([$now,$userIp,$note,$teacher['id'],$today]);
        logActivity($userId,'check_out','attendance',"চেক আউট: $now IP: $userIp");

        // Calculate hours
        $inTime = strtotime($todayRecord['check_in']);
        $outTime = strtotime($now);
        $hours = round(($outTime - $inTime) / 3600, 1);
        echo json_encode(['success'=>true,'msg'=>"✅ চেক আউট সফল! সময়: $now (মোট: $hours ঘণ্টা)",'time'=>$now,'action'=>'checkout','hours'=>$hours]);
    }
    exit;
}

// Monthly report
$month = $_GET['month'] ?? date('Y-m');
$reportData = [];
if ($teacher) {
    $stmt = $db->prepare("SELECT * FROM teacher_attendance WHERE teacher_id=? AND DATE_FORMAT(date,'%Y-%m')=? ORDER BY date DESC");
    $stmt->execute([$teacher['id'], $month]);
    $reportData = $stmt->fetchAll();
}

// All teachers today (admin view)
$allTodayAttendance = [];
if (in_array($_SESSION['role_slug'], ['super_admin','principal'])) {
    $allTodayAttendance = $db->query("SELECT ta.*, t.name_bn, t.designation_bn FROM teacher_attendance ta
        JOIN teachers t ON ta.teacher_id=t.id WHERE ta.date='$today' ORDER BY ta.check_in")->fetchAll();
}

require_once '../../includes/header.php';
?>

<div class="section-header">
    <h2 class="section-title"><i class="fas fa-fingerprint"></i> চেক ইন / চেক আউট</h2>
    <a href="report.php" class="btn btn-outline btn-sm"><i class="fas fa-chart-bar"></i> রিপোর্ট</a>
</div>

<!-- IP Status Alert -->
<?php if ($isAllowedIP): ?>
<div class="alert alert-success mb-16">
    <i class="fas fa-wifi"></i>
    <strong>মাদ্রাসার নেটওয়ার্ক সংযুক্ত</strong> — আপনি চেক ইন/আউট করতে পারবেন।
    <span style="margin-left:8px;opacity:.7;font-size:12px;">IP: <?=e($userIp)?></span>
</div>
<?php else: ?>
<div class="alert alert-warning mb-16">
    <i class="fas fa-exclamation-triangle"></i>
    <strong>মাদ্রাসার নেটওয়ার্কে নেই</strong> — চেক ইন/আউট শুধুমাত্র মাদ্রাসার WiFi থেকে করা যাবে।
    আপনার বর্তমান IP: <code><?=e($userIp)?></code>
    <br><small style="opacity:.8;">অন্যান্য কাজ (উপস্থিতি দেওয়া, ফলাফল ইত্যাদি) যেকোনো নেটওয়ার্ক থেকে করতে পারবেন।</small>
</div>
<?php endif; ?>

<!-- Check In/Out Card -->
<?php if ($teacher): ?>
<div class="card mb-24">
    <div class="card-body" style="padding:32px;text-align:center;">

        <!-- Current Time -->
        <div style="font-size:48px;font-weight:700;color:var(--primary);font-variant-numeric:tabular-nums;" id="liveClock">
            <?=date('H:i:s')?>
        </div>
        <div style="font-size:16px;color:var(--text-muted);margin-bottom:24px;"><?=banglaDate()?></div>

        <!-- Today's Status -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;max-width:400px;margin:0 auto 24px;">
            <div style="background:<?=$todayRecord&&$todayRecord['check_in']?'#eafaf1':'#f7fafc'?>;border-radius:12px;padding:16px;border:2px solid <?=$todayRecord&&$todayRecord['check_in']?'var(--success)':'var(--border)'?>;">
                <div style="font-size:12px;color:var(--text-muted);margin-bottom:4px;">চেক ইন</div>
                <div style="font-size:22px;font-weight:700;color:<?=$todayRecord&&$todayRecord['check_in']?'var(--success)':'var(--text-muted)'?>;">
                    <?=$todayRecord&&$todayRecord['check_in'] ? toBanglaNumber(date('h:i A',strtotime($todayRecord['check_in']))) : '--:--'?>
                </div>
            </div>
            <div style="background:<?=$todayRecord&&$todayRecord['check_out']?'#fdedec':'#f7fafc'?>;border-radius:12px;padding:16px;border:2px solid <?=$todayRecord&&$todayRecord['check_out']?'var(--danger)':'var(--border)'?>;">
                <div style="font-size:12px;color:var(--text-muted);margin-bottom:4px;">চেক আউট</div>
                <div style="font-size:22px;font-weight:700;color:<?=$todayRecord&&$todayRecord['check_out']?'var(--danger)':'var(--text-muted)'?>;">
                    <?=$todayRecord&&$todayRecord['check_out'] ? toBanglaNumber(date('h:i A',strtotime($todayRecord['check_out']))) : '--:--'?>
                </div>
            </div>
        </div>

        <!-- Note field -->
        <div style="max-width:400px;margin:0 auto 16px;">
            <textarea id="noteField" class="form-control" rows="2" placeholder="নোট (ঐচ্ছিক)..."></textarea>
        </div>

        <!-- Action Buttons -->
        <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;">
            <?php
            $checkedIn = $todayRecord && $todayRecord['check_in'];
            $checkedOut = $todayRecord && $todayRecord['check_out'];
            ?>
            <button onclick="doAction('checkin')"
                class="btn btn-success"
                style="padding:14px 32px;font-size:16px;<?=$checkedIn?'opacity:.5;cursor:not-allowed;':'';?>"
                <?=$checkedIn?'disabled':''?>
                <?=!$isAllowedIP?'disabled title="মাদ্রাসার নেটওয়ার্কে থাকুন"':''?>>
                <i class="fas fa-sign-in-alt"></i> চেক ইন
            </button>
            <button onclick="doAction('checkout')"
                class="btn btn-danger"
                style="padding:14px 32px;font-size:16px;<?=(!$checkedIn||$checkedOut)?'opacity:.5;cursor:not-allowed;':'';?>"
                <?=(!$checkedIn||$checkedOut)?'disabled':''?>
                <?=!$isAllowedIP?'disabled title="মাদ্রাসার নেটওয়ার্কে থাকুন"':''?>>
                <i class="fas fa-sign-out-alt"></i> চেক আউট
            </button>
        </div>

        <!-- Result message -->
        <div id="actionResult" style="margin-top:16px;display:none;"></div>
    </div>
</div>

<!-- Monthly Report -->
<div class="card mb-24">
    <div class="card-header">
        <span class="card-title"><i class="fas fa-calendar"></i> মাসিক উপস্থিতি</span>
        <form method="GET" style="display:flex;gap:8px;">
            <input type="month" name="month" class="form-control" style="padding:5px 10px;width:auto;" value="<?=e($month)?>" onchange="this.form.submit()">
        </form>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>তারিখ</th><th>চেক ইন</th><th>চেক আউট</th><th>মোট সময়</th><th>অবস্থা</th></tr></thead>
            <tbody>
                <?php if(empty($reportData)): ?>
                <tr><td colspan="5" style="text-align:center;padding:20px;color:var(--text-muted);">এই মাসে কোনো তথ্য নেই</td></tr>
                <?php else: foreach($reportData as $r): ?>
                <?php
                $totalHours = '';
                if ($r['check_in'] && $r['check_out']) {
                    $diff = strtotime($r['check_out']) - strtotime($r['check_in']);
                    $totalHours = toBanglaNumber(floor($diff/3600)) . 'ঘ ' . toBanglaNumber(floor(($diff%3600)/60)) . 'মি';
                }
                ?>
                <tr>
                    <td><?=banglaDate($r['date'])?></td>
                    <td style="color:var(--success);font-weight:600;"><?=$r['check_in']?toBanglaNumber(date('h:i A',strtotime($r['check_in']))):'-'?></td>
                    <td style="color:var(--danger);font-weight:600;"><?=$r['check_out']?toBanglaNumber(date('h:i A',strtotime($r['check_out']))):'-'?></td>
                    <td style="font-weight:600;"><?=$totalHours?:'-'?></td>
                    <td><span class="badge badge-<?=$r['status']==='present'?'success':($r['status']==='absent'?'danger':'warning')?>">
                        <?=['present'=>'উপস্থিত','absent'=>'অনুপস্থিত','half_day'=>'অর্ধদিন','leave'=>'ছুটি'][$r['status']]??$r['status']?>
                    </span></td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Admin: All Teachers Today -->
<?php if (!empty($allTodayAttendance)): ?>
<div class="card">
    <div class="card-header"><span class="card-title"><i class="fas fa-users"></i> আজকের শিক্ষক উপস্থিতি</span></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>শিক্ষক</th><th>পদবী</th><th>চেক ইন</th><th>চেক আউট</th><th>মোট</th></tr></thead>
            <tbody>
                <?php foreach($allTodayAttendance as $r): ?>
                <?php
                $totalHours = '';
                if ($r['check_in'] && $r['check_out']) {
                    $diff = strtotime($r['check_out']) - strtotime($r['check_in']);
                    $totalHours = floor($diff/3600).'ঘ '.floor(($diff%3600)/60).'মি';
                }
                ?>
                <tr>
                    <td style="font-weight:600;"><?=e($r['name_bn'])?></td>
                    <td style="font-size:12px;color:var(--text-muted);"><?=e($r['designation_bn']??'')?></td>
                    <td style="color:var(--success);font-weight:600;"><?=e($r['check_in'])?></td>
                    <td style="color:var(--danger);"><?=e($r['check_out']??'-')?></td>
                    <td><?=$totalHours?:'-'?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<script>
// Live clock
setInterval(() => {
    const now = new Date();
    const h = String(now.getHours()).padStart(2,'0');
    const m = String(now.getMinutes()).padStart(2,'0');
    const s = String(now.getSeconds()).padStart(2,'0');
    document.getElementById('liveClock').textContent = h+':'+m+':'+s;
}, 1000);

function doAction(action) {
    const note = document.getElementById('noteField').value;
    const resultDiv = document.getElementById('actionResult');
    resultDiv.style.display = 'none';

    fetch('', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({action: action, note: note, csrf: '<?=getCsrfToken()?>'})
    })
    .then(r => r.json())
    .then(data => {
        resultDiv.style.display = 'block';
        resultDiv.innerHTML = `<div class="alert alert-${data.success?'success':'danger'}" style="display:inline-flex;">
            <i class="fas fa-${data.success?'check-circle':'exclamation-circle'}"></i> ${data.msg}
        </div>`;
        if (data.success) setTimeout(() => location.reload(), 2000);
    })
    .catch(() => {
        resultDiv.style.display = 'block';
        resultDiv.innerHTML = '<div class="alert alert-danger">সংযোগ ব্যর্থ হয়েছে।</div>';
    });
}
</script>

<?php require_once '../../includes/footer.php'; ?>
