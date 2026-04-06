<?php
require_once '../../includes/functions.php';
requireLogin(['super_admin','principal','accountant']);
$pageTitle = 'হোস্টেল ম্যানেজমেন্ট';
$db = getDB();

// ===== টেবিল তৈরি =====
$db->exec("CREATE TABLE IF NOT EXISTS hostels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    name_bn VARCHAR(100),
    type ENUM('boys','girls','general') DEFAULT 'general',
    total_capacity INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$db->exec("CREATE TABLE IF NOT EXISTS hostel_rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hostel_id INT NOT NULL,
    floor_no VARCHAR(20),
    room_no VARCHAR(20) NOT NULL,
    capacity INT DEFAULT 4,
    room_type ENUM('general','ac','vip') DEFAULT 'general',
    is_active TINYINT(1) DEFAULT 1,
    FOREIGN KEY (hostel_id) REFERENCES hostels(id)
)");

$db->exec("CREATE TABLE IF NOT EXISTS hostel_beds (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    bed_no VARCHAR(20) NOT NULL,
    is_occupied TINYINT(1) DEFAULT 0,
    FOREIGN KEY (room_id) REFERENCES hostel_rooms(id)
)");

$db->exec("CREATE TABLE IF NOT EXISTS hostel_students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    hostel_id INT NOT NULL,
    room_id INT NOT NULL,
    bed_id INT,
    join_date DATE NOT NULL,
    leave_date DATE NULL,
    hostel_fee DECIMAL(10,2) DEFAULT 0,
    food_fee DECIMAL(10,2) DEFAULT 0,
    has_food TINYINT(1) DEFAULT 1,
    status ENUM('active','left') DEFAULT 'active',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id),
    FOREIGN KEY (hostel_id) REFERENCES hostels(id),
    FOREIGN KEY (room_id) REFERENCES hostel_rooms(id)
)");

// ===== হোস্টেল যোগ =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_hostel'])) {
    if (!verifyCsrf($_POST['csrf'] ?? '')) die('CSRF');
    $db->prepare("INSERT INTO hostels (name, name_bn, type) VALUES (?,?,?)")
       ->execute([trim($_POST['name']), trim($_POST['name_bn']), $_POST['type']]);
    setFlash('success', 'হোস্টেল যোগ হয়েছে!');
    header('Location: index.php'); exit;
}

// ===== রুম যোগ =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_room'])) {
    if (!verifyCsrf($_POST['csrf'] ?? '')) die('CSRF');
    $hostelId = (int)$_POST['hostel_id'];
    $roomNo   = trim($_POST['room_no']);
    $floorNo  = trim($_POST['floor_no'] ?? '');
    $capacity = (int)$_POST['capacity'];
    $type     = $_POST['room_type'] ?? 'general';

    $db->prepare("INSERT INTO hostel_rooms (hostel_id, floor_no, room_no, capacity, room_type) VALUES (?,?,?,?,?)")
       ->execute([$hostelId, $floorNo, $roomNo, $capacity, $type]);
    $roomId = $db->lastInsertId();

    // বেড স্বয়ংক্রিয় তৈরি
    for ($i = 1; $i <= $capacity; $i++) {
        $db->prepare("INSERT INTO hostel_beds (room_id, bed_no) VALUES (?,?)")
           ->execute([$roomId, $i]);
    }
    setFlash('success', "রুম $roomNo যোগ হয়েছে ($capacity টি বেড তৈরি হয়েছে)!");
    header('Location: index.php?hostel_id=' . $hostelId); exit;
}

// ===== ছাত্র ভর্তি =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admit_student'])) {
    if (!verifyCsrf($_POST['csrf'] ?? '')) die('CSRF');
    $studentId  = (int)$_POST['student_id'];
    $hostelId   = (int)$_POST['hostel_id'];
    $roomId     = (int)$_POST['room_id'];
    $bedId      = (int)($_POST['bed_id'] ?? 0) ?: null;
    $joinDate   = $_POST['join_date'] ?? date('Y-m-d');
    $hostelFee  = (float)$_POST['hostel_fee'];
    $foodFee    = (float)($_POST['food_fee'] ?? 0);
    $hasFood    = isset($_POST['has_food']) ? 1 : 0;
    $notes      = trim($_POST['notes'] ?? '');

    // আগে active আছে কিনা চেক
    $existing = $db->prepare("SELECT id FROM hostel_students WHERE student_id=? AND status='active'");
    $existing->execute([$studentId]);
    if ($existing->fetch()) {
        setFlash('danger', 'এই ছাত্র ইতিমধ্যে হোস্টেলে আছে!');
    } else {
        $db->prepare("INSERT INTO hostel_students (student_id, hostel_id, room_id, bed_id, join_date, hostel_fee, food_fee, has_food, notes, status)
            VALUES (?,?,?,?,?,?,?,?,?,'active')")
           ->execute([$studentId, $hostelId, $roomId, $bedId, $joinDate, $hostelFee, $foodFee, $hasFood, $notes]);
        // বেড occupied করো
        if ($bedId) $db->prepare("UPDATE hostel_beds SET is_occupied=1 WHERE id=?")->execute([$bedId]);
        setFlash('success', 'ছাত্র হোস্টেলে ভর্তি হয়েছে!');
    }
    header('Location: index.php'); exit;
}

// ===== ছাত্র বের করা =====
if (isset($_GET['remove_student'])) {
    $hsId = (int)$_GET['remove_student'];
    $hs = $db->prepare("SELECT * FROM hostel_students WHERE id=?");
    $hs->execute([$hsId]);
    $hs = $hs->fetch();
    if ($hs) {
        $db->prepare("UPDATE hostel_students SET status='left', leave_date=? WHERE id=?")->execute([date('Y-m-d'), $hsId]);
        if ($hs['bed_id']) $db->prepare("UPDATE hostel_beds SET is_occupied=0 WHERE id=?")->execute([$hs['bed_id']]);
        setFlash('success', 'ছাত্র হোস্টেল থেকে বের করা হয়েছে।');
    }
    header('Location: index.php'); exit;
}

// ===== ডেটা লোড =====
$hostels = $db->query("SELECT h.*, COUNT(r.id) as room_count FROM hostels h LEFT JOIN hostel_rooms r ON h.id=r.hostel_id AND r.is_active=1 WHERE h.is_active=1 GROUP BY h.id")->fetchAll();

$selectedHostel = (int)($_GET['hostel_id'] ?? ($hostels[0]['id'] ?? 0));

$rooms = [];
$hostelStudents = [];
$totalBeds = 0;
$occupiedBeds = 0;

if ($selectedHostel) {
    $rooms = $db->prepare("SELECT r.*, COUNT(b.id) as total_beds, SUM(b.is_occupied) as occupied_beds
        FROM hostel_rooms r
        LEFT JOIN hostel_beds b ON r.id=b.room_id
        WHERE r.hostel_id=? AND r.is_active=1
        GROUP BY r.id ORDER BY r.floor_no, r.room_no");
    $rooms->execute([$selectedHostel]);
    $rooms = $rooms->fetchAll();

    $hostelStudents = $db->prepare("
        SELECT hs.*, s.name_bn, s.name, s.student_id as stu_id, s.roll_number,
               c.class_name_bn, r.room_no, r.floor_no, b.bed_no
        FROM hostel_students hs
        JOIN students s ON hs.student_id = s.id
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN hostel_rooms r ON hs.room_id = r.id
        LEFT JOIN hostel_beds b ON hs.bed_id = b.id
        WHERE hs.hostel_id=? AND hs.status='active'
        ORDER BY r.floor_no, r.room_no, b.bed_no
    ");
    $hostelStudents->execute([$selectedHostel]);
    $hostelStudents = $hostelStudents->fetchAll();

    foreach ($rooms as $r) {
        $totalBeds    += $r['total_beds'];
        $occupiedBeds += $r['occupied_beds'];
    }
}

// রুম ও বেড লিস্ট (ভর্তির জন্য)
$allRooms = $selectedHostel ? $db->prepare("SELECT * FROM hostel_rooms WHERE hostel_id=? AND is_active=1 ORDER BY floor_no, room_no") : null;
if ($allRooms) { $allRooms->execute([$selectedHostel]); $allRooms = $allRooms->fetchAll(); }

// ছাত্র খোঁজা (ভর্তির জন্য)
$searchStudents = [];
if (!empty($_GET['search_student'])) {
    $sq = '%' . $_GET['search_student'] . '%';
    $ss = $db->prepare("SELECT s.id, s.name_bn, s.name, s.student_id, c.class_name_bn FROM students s LEFT JOIN classes c ON s.class_id=c.id WHERE s.status='active' AND (s.name_bn LIKE ? OR s.name LIKE ? OR s.student_id LIKE ?) LIMIT 10");
    $ss->execute([$sq,$sq,$sq]);
    $searchStudents = $ss->fetchAll();
}

require_once '../../includes/header.php';
?>

<div class="section-header">
    <h2 class="section-title"><i class="fas fa-building"></i> হোস্টেল ম্যানেজমেন্ট</h2>
    <div style="display:flex;gap:8px;">
        <button onclick="openModal('addRoomModal')" class="btn btn-outline btn-sm"><i class="fas fa-door-open"></i> নতুন রুম</button>
        <button onclick="openModal('addHostelModal')" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> নতুন হোস্টেল</button>
    </div>
</div>

<!-- হোস্টেল ট্যাব -->
<?php if(!empty($hostels)): ?>
<div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;">
    <?php foreach($hostels as $h): ?>
    <a href="?hostel_id=<?= $h['id'] ?>"
       style="padding:8px 20px;border-radius:20px;font-size:13px;font-weight:600;text-decoration:none;
              background:<?= $selectedHostel==$h['id'] ? 'var(--primary)' : '#fff' ?>;
              color:<?= $selectedHostel==$h['id'] ? '#fff' : 'var(--text)' ?>;
              border:2px solid <?= $selectedHostel==$h['id'] ? 'var(--primary)' : 'var(--border)' ?>;">
        <i class="fas fa-building"></i> <?= e($h['name_bn']?:$h['name']) ?>
        <span style="opacity:.7;font-size:11px;">(<?= toBanglaNumber($h['room_count']) ?> রুম)</span>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- স্ট্যাট কার্ড -->
<?php if($selectedHostel): ?>
<div class="stat-grid mb-24">
    <div class="stat-card blue">
        <div class="stat-icon"><i class="fas fa-door-open"></i></div>
        <div><div class="stat-value"><?= toBanglaNumber(count($rooms)) ?></div><div class="stat-label">মোট রুম</div></div>
    </div>
    <div class="stat-card green">
        <div class="stat-icon"><i class="fas fa-bed"></i></div>
        <div><div class="stat-value"><?= toBanglaNumber($totalBeds) ?></div><div class="stat-label">মোট বেড</div></div>
    </div>
    <div class="stat-card orange">
        <div class="stat-icon"><i class="fas fa-user-check"></i></div>
        <div><div class="stat-value"><?= toBanglaNumber($occupiedBeds) ?></div><div class="stat-label">ব্যবহৃত বেড</div></div>
    </div>
    <div class="stat-card red">
        <div class="stat-icon"><i class="fas fa-user-plus"></i></div>
        <div><div class="stat-value"><?= toBanglaNumber($totalBeds - $occupiedBeds) ?></div><div class="stat-label">খালি বেড</div></div>
    </div>
</div>

<div class="grid-2 mb-24">
    <!-- রুমের তালিকা -->
    <div class="card">
        <div class="card-header">
            <span class="card-title"><i class="fas fa-door-open"></i> রুমের তালিকা</span>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>ফ্লোর</th><th>রুম</th><th>ধরন</th><th>বেড</th><th>খালি</th></tr></thead>
                <tbody>
                    <?php if(empty($rooms)): ?>
                    <tr><td colspan="5" style="text-align:center;padding:20px;color:var(--text-muted);">কোনো রুম নেই</td></tr>
                    <?php else: foreach($rooms as $r):
                        $free = $r['total_beds'] - $r['occupied_beds'];
                        $typeLabel = ['general'=>'সাধারণ','ac'=>'AC','vip'=>'VIP'][$r['room_type']] ?? $r['room_type'];
                    ?>
                    <tr>
                        <td style="font-size:13px;"><?= e($r['floor_no'] ?: '-') ?></td>
                        <td style="font-weight:700;"><?= e($r['room_no']) ?></td>
                        <td><span class="badge badge-info" style="font-size:11px;"><?= $typeLabel ?></span></td>
                        <td style="font-size:13px;"><?= toBanglaNumber($r['total_beds']) ?></td>
                        <td>
                            <span style="color:<?= $free>0?'var(--success)':'var(--danger)' ?>;font-weight:700;">
                                <?= toBanglaNumber($free) ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ছাত্র ভর্তি ফর্ম -->
    <div class="card">
        <div class="card-header" style="background:#eafaf1;">
            <span class="card-title" style="color:var(--success);"><i class="fas fa-user-plus"></i> হোস্টেলে ভর্তি করুন</span>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="csrf" value="<?= getCsrfToken() ?>">
                <input type="hidden" name="admit_student" value="1">
                <input type="hidden" name="hostel_id" value="<?= $selectedHostel ?>">

                <div class="form-group mb-16">
                    <label>ছাত্র খুঁজুন *</label>
                    <div style="display:flex;gap:8px;">
                        <input type="text" id="studentSearchInput" class="form-control" placeholder="নাম বা ID..." oninput="searchStudentLive(this.value)">
                    </div>
                    <div id="studentSearchResult" style="border:1px solid var(--border);border-radius:8px;display:none;max-height:180px;overflow-y:auto;margin-top:4px;"></div>
                    <input type="hidden" name="student_id" id="selectedStudentId" required>
                    <div id="selectedStudentInfo" style="margin-top:6px;font-size:13px;color:var(--success);"></div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>রুম *</label>
                        <select name="room_id" id="roomSelect" class="form-control" required onchange="loadBeds(this.value)">
                            <option value="">রুম নির্বাচন করুন</option>
                            <?php foreach($allRooms??[] as $r): ?>
                            <option value="<?= $r['id'] ?>"><?= e($r['floor_no']?$r['floor_no'].'তলা - ':'') ?>রুম <?= e($r['room_no']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>বেড</label>
                        <select name="bed_id" id="bedSelect" class="form-control">
                            <option value="">আগে রুম নির্বাচন করুন</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>ভর্তির তারিখ *</label>
                        <input type="date" name="join_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>হোস্টেল ফি (৳/মাস) *</label>
                        <input type="number" name="hostel_fee" class="form-control" value="1500" min="0" required>
                    </div>
                    <div class="form-group">
                        <label><input type="checkbox" name="has_food" value="1" checked onchange="toggleFoodFee(this)"> খাবার নেবে</label>
                        <input type="number" name="food_fee" id="foodFeeInput" class="form-control" value="1000" min="0">
                    </div>
                    <div class="form-group" style="grid-column:1/-1;">
                        <label>নোট</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="বিশেষ কোনো তথ্য..."></textarea>
                    </div>
                </div>
                <button type="submit" class="btn btn-success" style="width:100%;margin-top:8px;">
                    <i class="fas fa-user-plus"></i> হোস্টেলে ভর্তি করুন
                </button>
            </form>
        </div>
    </div>
</div>

<!-- হোস্টেলের ছাত্র তালিকা -->
<div class="card">
    <div class="card-header">
        <span class="card-title"><i class="fas fa-users"></i> হোস্টেলের ছাত্র তালিকা (<?= toBanglaNumber(count($hostelStudents)) ?> জন)</span>
        <button onclick="window.print()" class="btn btn-outline btn-sm no-print"><i class="fas fa-print"></i></button>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>#</th><th>ছাত্র</th><th>শ্রেণী</th><th>রুম/বেড</th><th>ভর্তির তারিখ</th><th>হোস্টেল ফি</th><th>খাবার ফি</th><th class="no-print">অ্যাকশন</th></tr>
            </thead>
            <tbody>
                <?php if(empty($hostelStudents)): ?>
                <tr><td colspan="8" style="text-align:center;padding:24px;color:var(--text-muted);">কোনো ছাত্র নেই</td></tr>
                <?php else: foreach($hostelStudents as $i=>$hs): ?>
                <tr>
                    <td style="font-size:13px;color:var(--text-muted);"><?= toBanglaNumber($i+1) ?></td>
                    <td>
                        <div style="font-weight:700;font-size:13px;"><?= e($hs['name_bn']?:$hs['name']) ?></div>
                        <div style="font-size:11px;color:var(--text-muted);"><?= e($hs['stu_id']) ?></div>
                    </td>
                    <td style="font-size:13px;"><?= e($hs['class_name_bn']??'-') ?></td>
                    <td>
                        <span style="font-weight:600;">রুম <?= e($hs['room_no']) ?></span>
                        <?php if($hs['floor_no']): ?><span style="font-size:11px;color:var(--text-muted);"> | <?= e($hs['floor_no']) ?>তলা</span><?php endif; ?>
                        <?php if($hs['bed_no']): ?><br><span style="font-size:11px;color:var(--text-muted);">বেড <?= e($hs['bed_no']) ?></span><?php endif; ?>
                    </td>
                    <td style="font-size:13px;"><?= banglaDate($hs['join_date']) ?></td>
                    <td style="font-weight:700;color:var(--primary);">৳<?= number_format($hs['hostel_fee']) ?></td>
                    <td style="font-size:13px;">
                        <?= $hs['has_food'] ? '৳'.number_format($hs['food_fee']) : '<span style="color:var(--text-muted);">নেই</span>' ?>
                    </td>
                    <td class="no-print">
                        <a href="?remove_student=<?= $hs['id'] ?>&hostel_id=<?= $selectedHostel ?>"
                           onclick="return confirm('হোস্টেল থেকে বের করবেন?')"
                           class="btn btn-danger btn-xs"><i class="fas fa-sign-out-alt"></i> বের করুন</a>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- নতুন হোস্টেল Modal -->
<div class="modal-overlay" id="addHostelModal">
    <div class="modal-box" style="max-width:440px;">
        <div class="modal-header">
            <span style="font-weight:700;"><i class="fas fa-building"></i> নতুন হোস্টেল যোগ করুন</span>
            <button onclick="closeModal('addHostelModal')" class="btn btn-outline btn-xs">✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="csrf" value="<?= getCsrfToken() ?>">
            <input type="hidden" name="add_hostel" value="1">
            <div class="modal-body">
                <div class="form-group mb-16">
                    <label>হোস্টেলের নাম (বাংলায়) *</label>
                    <input type="text" name="name_bn" class="form-control" placeholder="যেমন: হিফয হোস্টেল" required>
                </div>
                <div class="form-group mb-16">
                    <label>হোস্টেলের নাম (ইংরেজি) *</label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. Hifz Hostel" required>
                </div>
                <div class="form-group">
                    <label>ধরন</label>
                    <select name="type" class="form-control">
                        <option value="boys">ছেলে</option>
                        <option value="girls">মেয়ে</option>
                        <option value="general">সাধারণ</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="closeModal('addHostelModal')" class="btn btn-outline">বাতিল</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> যোগ করুন</button>
            </div>
        </form>
    </div>
</div>

<!-- নতুন রুম Modal -->
<div class="modal-overlay" id="addRoomModal">
    <div class="modal-box" style="max-width:480px;">
        <div class="modal-header">
            <span style="font-weight:700;"><i class="fas fa-door-open"></i> নতুন রুম যোগ করুন</span>
            <button onclick="closeModal('addRoomModal')" class="btn btn-outline btn-xs">✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="csrf" value="<?= getCsrfToken() ?>">
            <input type="hidden" name="add_room" value="1">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label>হোস্টেল *</label>
                        <select name="hostel_id" class="form-control" required>
                            <?php foreach($hostels as $h): ?>
                            <option value="<?= $h['id'] ?>" <?= $selectedHostel==$h['id']?'selected':'' ?>><?= e($h['name_bn']?:$h['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>ফ্লোর নম্বর</label>
                        <input type="text" name="floor_no" class="form-control" placeholder="যেমন: ১, ২, G">
                    </div>
                    <div class="form-group">
                        <label>রুম নম্বর *</label>
                        <input type="text" name="room_no" class="form-control" placeholder="যেমন: ১০১" required>
                    </div>
                    <div class="form-group">
                        <label>ধারণক্ষমতা (বেড সংখ্যা) *</label>
                        <input type="number" name="capacity" class="form-control" value="4" min="1" max="20" required>
                    </div>
                    <div class="form-group" style="grid-column:1/-1;">
                        <label>রুমের ধরন</label>
                        <select name="room_type" class="form-control">
                            <option value="general">সাধারণ</option>
                            <option value="ac">AC</option>
                            <option value="vip">VIP</option>
                        </select>
                    </div>
                </div>
                <div class="alert alert-info mt-16" style="font-size:12px;">
                    <i class="fas fa-info-circle"></i> ধারণক্ষমতা অনুযায়ী স্বয়ংক্রিয়ভাবে বেড তৈরি হবে।
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="closeModal('addRoomModal')" class="btn btn-outline">বাতিল</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> রুম যোগ করুন</button>
            </div>
        </form>
    </div>
</div>

<script>
// ছাত্র লাইভ সার্চ
let searchTimeout;
function searchStudentLive(q) {
    clearTimeout(searchTimeout);
    if (q.length < 2) { document.getElementById('studentSearchResult').style.display = 'none'; return; }
    searchTimeout = setTimeout(() => {
        fetch('<?= BASE_URL ?>/api/search_student.php?q=' + encodeURIComponent(q))
            .then(r => r.json())
            .then(data => {
                const div = document.getElementById('studentSearchResult');
                if (!data.length) { div.style.display = 'none'; return; }
                div.innerHTML = data.map(s => `
                    <div onclick="selectStudent(${s.id},'${(s.name_bn||s.name).replace(/'/g,"\\'")}')"
                         style="padding:10px 14px;cursor:pointer;border-bottom:1px solid var(--border);font-size:13px;"
                         onmouseover="this.style.background='#f0f4f8'" onmouseout="this.style.background=''">
                        <strong>${s.name_bn||s.name}</strong> — ${s.student_id} — ${s.class_name_bn||''}
                    </div>`).join('');
                div.style.display = 'block';
            });
    }, 300);
}

function selectStudent(id, name) {
    document.getElementById('selectedStudentId').value = id;
    document.getElementById('studentSearchInput').value = name;
    document.getElementById('studentSearchResult').style.display = 'none';
    document.getElementById('selectedStudentInfo').innerHTML = '<i class="fas fa-check-circle"></i> নির্বাচিত: <strong>' + name + '</strong>';
}

// বেড লোড
function loadBeds(roomId) {
    if (!roomId) return;
    fetch('<?= BASE_URL ?>/api/hostel_beds.php?room_id=' + roomId)
        .then(r => r.json())
        .then(data => {
            const sel = document.getElementById('bedSelect');
            sel.innerHTML = '<option value="">বেড নির্বাচন করুন (ঐচ্ছিক)</option>';
            data.forEach(b => {
                if (!b.is_occupied) {
                    sel.innerHTML += `<option value="${b.id}">বেড ${b.bed_no}</option>`;
                }
            });
        }).catch(() => {});
}

function toggleFoodFee(cb) {
    document.getElementById('foodFeeInput').disabled = !cb.checked;
}
</script>

<?php require_once '../../includes/footer.php'; ?>
