<?php
require_once 'includes/functions.php';
$db = getDB();

// ===== IP CHECK ===== (আপনার প্রতিষ্ঠানের ওয়াইফাই IP দিন)
$ALLOWED_IPS = [
    '182.48.68.103',
    '127.0.0.1',
    '::1',
];
$userIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
if (str_contains($userIp, ',')) $userIp = trim(explode(',', $userIp)[0]);
$isAllowedIP = in_array($userIp, $ALLOWED_IPS);

// ===== AJAX CHECK IN / CHECK OUT LOGIC =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $phone  = preg_replace('/\D/', '', trim($_POST['phone'] ?? ''));
    $action = $_POST['action'] ?? '';
    $today  = date('Y-m-d');
    $now    = date('H:i:s');

    if (!$isAllowedIP) {
        echo json_encode(['success' => false, 'type' => 'ip', 'msg' => 'মাদ্রাসার WiFi তে কানেক্ট করুন।']);
        exit;
    }

    if (strlen($phone) < 10) {
        echo json_encode(['success' => false, 'msg' => 'সঠিক ফোন নম্বর দিন।']);
        exit;
    }

    // Teacher খোঁজো
    $stmt = $db->prepare("SELECT t.*, u.name, u.name_bn FROM teachers t JOIN users u ON t.user_id = u.id WHERE t.phone = ? AND t.is_active = 1");
    $stmt->execute([$phone]);
    $teacher = $stmt->fetch();

    if (!$teacher) {
        echo json_encode(['success' => false, 'msg' => 'এই ফোন নম্বরে কোনো শিক্ষক/স্টাফ পাওয়া যায়নি।']);
        exit;
    }

    // teacher_attendance টেবিল চেক বা তৈরি
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

    // আজকের রেকর্ড
    $rec = $db->prepare("SELECT * FROM teacher_attendance WHERE teacher_id=? AND date=?");
    $rec->execute([$teacher['id'], $today]);
    $todayRecord = $rec->fetch();

    $displayName = $teacher['name_bn'] ?: $teacher['name'];

    if ($action === 'checkin') {
        if ($todayRecord && $todayRecord['check_in']) {
            echo json_encode([
                'success' => false,
                'msg'     => 'আপনি আজ ' . date('h:i A', strtotime($todayRecord['check_in'])) . ' এ চেক ইন করেছেন।',
                'name'    => $displayName,
            ]);
            exit;
        }
        $db->prepare("INSERT INTO teacher_attendance (teacher_id, date, check_in, ip_address, status)
            VALUES (?,?,?,?,'present')
            ON DUPLICATE KEY UPDATE check_in=VALUES(check_in), ip_address=VALUES(ip_address)")
           ->execute([$teacher['id'], $today, $now, $userIp]);

        logActivity($teacher['user_id'], 'check_in', 'attendance', "চেক ইন: $now IP: $userIp");

        echo json_encode([
            'success'  => true,
            'action'   => 'checkin',
            'name'     => $displayName,
            'id'       => $teacher['teacher_id_no'],
            'designation' => $teacher['designation_bn'] ?? '',
            'time'     => date('h:i A', strtotime($now)),
            'msg'      => '✅ চেক ইন সফল!',
        ]);

    } elseif ($action === 'checkout') {
        if (!$todayRecord || !$todayRecord['check_in']) {
            echo json_encode(['success' => false, 'msg' => 'আগে চেক ইন করুন।', 'name' => $displayName]);
            exit;
        }
        if ($todayRecord['check_out']) {
            echo json_encode([
                'success' => false,
                'msg'     => 'আপনি আজ ' . date('h:i A', strtotime($todayRecord['check_out'])) . ' এ চেক আউট করেছেন।',
                'name'    => $displayName,
            ]);
            exit;
        }
        $db->prepare("UPDATE teacher_attendance SET check_out=?, ip_address=? WHERE teacher_id=? AND date=?")
           ->execute([$now, $userIp, $teacher['id'], $today]);

        logActivity($teacher['user_id'], 'check_out', 'attendance', "চেক আউট: $now IP: $userIp");

        $diff  = strtotime($now) - strtotime($todayRecord['check_in']);
        $hrs   = floor($diff / 3600);
        $mins  = floor(($diff % 3600) / 60);

        echo json_encode([
            'success'     => true,
            'action'      => 'checkout',
            'name'        => $displayName,
            'id'          => $teacher['teacher_id_no'],
            'designation' => $teacher['designation_bn'] ?? '',
            'checkin'     => date('h:i A', strtotime($todayRecord['check_in'])),
            'time'        => date('h:i A', strtotime($now)),
            'total'       => $hrs . 'ঘ ' . $mins . 'মি',
            'msg'         => '✅ চেক আউট সফল!',
        ]);
    }
    exit;
}

$instituteName = '';
try { $instituteName = getSetting('institute_name', 'মাদ্রাসা ম্যানেজমেন্ট সিস্টেম'); } catch(Exception $e){}
?>
<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>চেক ইন / চেক আউট | <?= htmlspecialchars($instituteName) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;600;700&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body {
    font-family: 'Hind Siliguri', sans-serif;
    background: linear-gradient(135deg, #0d2137 0%, #1a5276 100%);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px;
}
.container { width: 100%; max-width: 440px; }
.logo-area { text-align: center; margin-bottom: 24px; color: #fff; }
.logo-area h1 { font-size: 18px; font-weight: 700; opacity: .9; }
.logo-area p  { font-size: 13px; opacity: .6; margin-top: 4px; }
.card { background: #fff; border-radius: 20px; overflow: hidden; box-shadow: 0 20px 60px rgba(0,0,0,.3); }
.clock-area { background: linear-gradient(135deg, #1a5276, #2471a3); padding: 28px 24px 24px; text-align: center; color: #fff; }
.clock { font-size: 52px; font-weight: 700; letter-spacing: 2px; font-variant-numeric: tabular-nums; line-height: 1; }
.clock-date { font-size: 14px; opacity: .75; margin-top: 6px; }
.ip-bar { padding: 10px 20px; font-size: 13px; text-align: center; font-weight: 600; }
.ip-bar.ok { background: #d4edda; color: #155724; }
.ip-bar.warning { background: #fff3cd; color: #856404; }
.form-area { padding: 24px; }
.phone-input-wrap { display: flex; gap: 10px; margin-bottom: 16px; }
.phone-input { flex: 1; padding: 14px 16px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 18px; font-family: inherit; outline: none; text-align: center; font-weight: 600; letter-spacing: 2px;}
.phone-input:focus { border-color: #1a5276; }
.btn-check { padding: 14px 16px; border: none; border-radius: 12px; cursor: pointer; font-size: 14px; font-family: inherit; font-weight: 600; transition: all .2s; }
.btn-in  { background: #27ae60; color: #fff; flex: 1; font-size: 16px; }
.btn-out { background: #e74c3c; color: #fff; flex: 1; font-size: 16px; }
.btn-in:hover  { background: #219a52; }
.btn-out:hover { background: #c0392b; }
.btn-in:disabled, .btn-out:disabled { opacity: .5; cursor: not-allowed; }
.result-card { display: none; border-radius: 14px; padding: 20px; text-align: center; margin-top: 16px; animation: fadeIn .3s ease; }
.result-card.success-in  { background: #eafaf1; border: 2px solid #27ae60; }
.result-card.success-out { background: #fdedec; border: 2px solid #e74c3c; }
.result-card.error       { background: #fff3cd; border: 2px solid #f39c12; }
.result-avatar { width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 26px; font-weight: 700; color: #fff; margin: 0 auto 12px; }
.result-name  { font-size: 20px; font-weight: 700; margin-bottom: 2px; }
.result-id    { font-size: 12px; color: #718096; margin-bottom: 12px; }
.result-time  { font-size: 36px; font-weight: 700; margin-bottom: 4px; }
.result-msg   { font-size: 14px; }
.detail-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid rgba(0,0,0,.06); font-size: 14px; }
.detail-row:last-child { border: none; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
.numpad { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-top: 16px; }
.num-btn { padding: 14px; border: 1px solid #e2e8f0; border-radius: 10px; background: #f7fafc; font-size: 20px; font-weight: 600; cursor: pointer; transition: all .15s; font-family: inherit; }
.num-btn:active { background: #e2e8f0; transform: scale(.96); }
.num-btn.del { color: #e74c3c; font-size: 18px; }
.num-btn.clear { color: #718096; font-size: 14px; }
.reset-btn { width: 100%; margin-top: 12px; padding: 10px; border: none; border-radius: 10px; background: #f7fafc; color: #718096; font-size: 14px; font-family: inherit; cursor: pointer; }
</style>
</head>
<body>

<div class="container">
    <div class="logo-area">
        <h1><i class="fas fa-mosque"></i> <?= htmlspecialchars($instituteName) ?></h1>
        <p>শিক্ষক ও স্টাফ উপস্থিতি (Smart Kiosk)</p>
    </div>

    <div class="card">
        <!-- ঘড়ি -->
        <div class="clock-area">
            <div class="clock" id="liveClock">00:00:00</div>
            <div class="clock-date" id="liveDate"></div>
        </div>

        <!-- IP স্ট্যাটাস -->
        <?php if ($isAllowedIP): ?>
        <div class="ip-bar ok"><i class="fas fa-wifi"></i> মাদ্রাসার নেটওয়ার্ক সংযুক্ত</div>
        <?php else: ?>
        <div class="ip-bar warning"><i class="fas fa-exclamation-triangle"></i> মাদ্রাসার WiFi তে কানেক্ট করুন (IP: <?= htmlspecialchars($userIp) ?>)</div>
        <?php endif; ?>

        <!-- ফর্ম -->
        <div class="form-area" id="formArea">
            <div style="font-size:14px;color:#718096;margin-bottom:12px;text-align:center;">আপনার ফোন নম্বর দিন</div>

            <div class="phone-input-wrap">
                <input type="tel" id="phoneInput" class="phone-input" placeholder="01XXXXXXXXX" maxlength="11" readonly>
            </div>

            <div style="display:flex;gap:10px;margin-bottom:4px;">
                <button class="btn-check btn-in"  id="btnIn"  onclick="submit('checkin')"  <?= !$isAllowedIP ? 'disabled' : '' ?>>
                    <i class="fas fa-sign-in-alt"></i> চেক ইন
                </button>
                <button class="btn-check btn-out" id="btnOut" onclick="submit('checkout')" <?= !$isAllowedIP ? 'disabled' : '' ?>>
                    <i class="fas fa-sign-out-alt"></i> চেক আউট
                </button>
            </div>

            <!-- নম্বর প্যাড -->
            <div class="numpad">
                <?php foreach(['১','২','৩','৪','৫','৬','৭','৮','৯','','০','⌫'] as $k): ?>
                <?php if($k === ''): ?>
                <button class="num-btn clear" onclick="clearInput()">মুছুন</button>
                <?php elseif($k === '⌫'): ?>
                <button class="num-btn del" onclick="delChar()">⌫</button>
                <?php else: ?>
                <button class="num-btn" onclick="addNum('<?= $k ?>')"><?= $k ?></button>
                <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- রেজাল্ট -->
        <div class="form-area" id="resultArea" style="display:none;">
            <div class="result-card" id="resultCard">
                <div class="result-avatar" id="resultAvatar"></div>
                <div class="result-name"  id="resultName"></div>
                <div class="result-id"    id="resultId"></div>
                <div class="result-time"  id="resultTime"></div>
                <div class="result-msg"   id="resultMsg"></div>
                <div id="resultDetails"   style="margin-top:12px;"></div>
            </div>
            <button class="reset-btn" onclick="resetForm()"><i class="fas fa-redo"></i> নতুন এন্ট্রি</button>
        </div>
    </div>
</div>

<script>
// বাংলা সংখ্যা → ইংরেজি
const bnToEn = s => s.replace(/[০-৯]/g, d => '০১২৩৪৫৬৭৮৯'.indexOf(d));
const enToBn = n => String(n).replace(/[0-9]/g, d => '০১২৩৪৫৬৭৮৯'[d]);

// ঘড়ি
const bnMonths = ['জানুয়ারি','ফেব্রুয়ারি','মার্চ','এপ্রিল','মে','জুন','জুলাই','আগস্ট','সেপ্টেম্বর','অক্টোবর','নভেম্বর','ডিসেম্বর'];
function updateClock() {
    const now = new Date();
    const h = String(now.getHours()).padStart(2,'0');
    const m = String(now.getMinutes()).padStart(2,'0');
    const s = String(now.getSeconds()).padStart(2,'0');
    document.getElementById('liveClock').textContent = h+':'+m+':'+s;
    document.getElementById('liveDate').textContent = enToBn(now.getDate()) + ' ' + bnMonths[now.getMonth()] + ' ' + enToBn(now.getFullYear());
}
setInterval(updateClock, 1000); updateClock();

// নম্বর প্যাড
function addNum(bn) {
    const inp = document.getElementById('phoneInput');
    if (inp.value.length >= 11) return;
    inp.value += bnToEn(bn);
}
function delChar() {
    const inp = document.getElementById('phoneInput');
    inp.value = inp.value.slice(0, -1);
}
function clearInput() { document.getElementById('phoneInput').value = ''; }

// সাবমিট
function submit(action) {
    const phone = document.getElementById('phoneInput').value.trim();
    if (phone.length < 10) { alert('সঠিক ফোন নম্বর দিন'); return; }
    document.getElementById('btnIn').disabled  = true;
    document.getElementById('btnOut').disabled = true;

    fetch('', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({action, phone})
    })
    .then(r => r.json())
    .then(data => showResult(data, action))
    .catch(() => { alert('সংযোগ ব্যর্থ হয়েছে।'); resetForm(); });
}

function showResult(data, action) {
    document.getElementById('formArea').style.display   = 'none';
    document.getElementById('resultArea').style.display = 'block';

    const card    = document.getElementById('resultCard');
    const avatar  = document.getElementById('resultAvatar');
    const name    = data.name || '';
    const initial = name ? name[0] : '?';

    document.getElementById('resultName').textContent = name;
    document.getElementById('resultId').textContent   = (data.id || '') + (data.designation ? ' | ' + data.designation : '');
    document.getElementById('resultDetails').innerHTML = '';

    if (data.success && action === 'checkin') {
        card.className = 'result-card success-in'; avatar.style.background = '#27ae60'; avatar.textContent = initial;
        document.getElementById('resultTime').textContent = data.time || '';
        document.getElementById('resultTime').style.color = '#27ae60';
        document.getElementById('resultMsg').innerHTML    = '<strong style="color:#27ae60;">✅ চেক ইন সফল!</strong>';
    } else if (data.success && action === 'checkout') {
        card.className = 'result-card success-out'; avatar.style.background = '#e74c3c'; avatar.textContent = initial;
        document.getElementById('resultTime').textContent = data.time || '';
        document.getElementById('resultTime').style.color = '#e74c3c';
        document.getElementById('resultMsg').innerHTML    = '<strong style="color:#e74c3c;">✅ চেক আউট সফল!</strong>';
        if (data.checkin || data.total) {
            document.getElementById('resultDetails').innerHTML = `
                <div class="detail-row"><span>চেক ইন</span><strong>${data.checkin||'-'}</strong></div>
                <div class="detail-row"><span>চেক আউট</span><strong>${data.time||'-'}</strong></div>
                <div class="detail-row"><span>মোট সময়</span><strong>${data.total||'-'}</strong></div>`;
        }
    } else {
        card.className = 'result-card error'; avatar.style.background = '#f39c12'; avatar.textContent = name ? initial : '!';
        document.getElementById('resultTime').textContent = '';
        document.getElementById('resultMsg').textContent  = data.msg || 'কিছু একটা সমস্যা হয়েছে।';
    }
    setTimeout(resetForm, 5000); // ৫ সেকেন্ড পর নিজে নিজে রিসেট
}

function resetForm() {
    document.getElementById('phoneInput').value         = '';
    document.getElementById('formArea').style.display   = 'block';
    document.getElementById('resultArea').style.display = 'none';
    document.getElementById('btnIn').disabled  = <?= $isAllowedIP ? 'false' : 'true' ?>;
    document.getElementById('btnOut').disabled = <?= $isAllowedIP ? 'false' : 'true' ?>;
}
</script>
</body>
</html>