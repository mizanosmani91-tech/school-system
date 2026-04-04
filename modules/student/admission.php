<?php
require_once '../../includes/functions.php';
requireLogin(['super_admin','principal','teacher']);
$pageTitle = 'নতুন ছাত্র ভর্তি';
$db = getDB();

// Classes
$classes = $db->query("SELECT * FROM classes WHERE is_active=1 ORDER BY class_numeric")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf'] ?? '')) {
        setFlash('danger', 'CSRF token অবৈধ।');
        header('Location: admission.php');
        exit;
    }

    $name = trim($_POST['name'] ?? '');
    $nameBn = trim($_POST['name_bn'] ?? '');
    $classId = (int)($_POST['class_id'] ?? 0);
    $sectionId = (int)($_POST['section_id'] ?? 0) ?: null;
    $dob = $_POST['dob'] ?? null;
    $gender = $_POST['gender'] ?? 'male';
    $admDate = $_POST['admission_date'] ?? date('Y-m-d');
    $fatherName = trim($_POST['father_name'] ?? '');
    $fatherNameEn = trim($_POST['father_name_en'] ?? '');
    $fatherPhone = trim($_POST['father_phone'] ?? '');
    $motherName = trim($_POST['mother_name'] ?? '');
    $motherNameEn = trim($_POST['mother_name_en'] ?? '');
    $motherPhone = trim($_POST['mother_phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $religion = $_POST['religion'] ?? 'islam';
    $bloodGroup = $_POST['blood_group'] ?? '';
    $prevSchool = trim($_POST['prev_school'] ?? '');
    $birthCert = trim($_POST['birth_cert'] ?? '');

    // ফি সংক্রান্ত তথ্য
    $monthlyFee   = (float)($_POST['monthly_fee'] ?? 0);
    $isHostel     = isset($_POST['is_hostel']) ? 1 : 0;
    $hostelFee    = $isHostel ? (float)($_POST['hostel_fee'] ?? 0) : 0;
    $isHostelFood = ($isHostel && isset($_POST['is_hostel_food'])) ? 1 : 0;
    $foodFee      = $isHostelFood ? (float)($_POST['food_fee'] ?? 0) : 0;

    // অভিভাবকের ফোন — পিতার টা থাকলে পিতার, না থাকলে মাতার
    $guardianPhone = $fatherPhone ?: $motherPhone;

    if (!$name || !$classId) {
        setFlash('danger', 'নাম ও শ্রেণী আবশ্যক।');
    } else {
        // Random Unique Student ID: ANT-YYYY-XXXX
        do {
            $rand = strtoupper(substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 4));
            $studentId = 'ANT-' . date('Y') . '-' . $rand;
            $exists = $db->prepare("SELECT id FROM students WHERE student_id=?");
            $exists->execute([$studentId]);
        } while ($exists->fetch());

        // Secret Code — 6 digit alphanumeric
        do {
            $secretCode = strtoupper(substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 6));
            $secExists = $db->prepare("SELECT id FROM students WHERE secret_code=?");
            $secExists->execute([$secretCode]);
        } while ($secExists->fetch());

        // Roll Number — last roll + 1 for this class
        $rollNo = $db->query("SELECT COALESCE(MAX(roll_number),0)+1 FROM students WHERE class_id=$classId AND academic_year='".date('Y')."'")->fetchColumn();

        // Photo upload
        $photo = null;
        if (!empty($_FILES['photo']['name'])) {
            $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
            $photo = 'students/' . $studentId . '.' . $ext;
            $dir = UPLOAD_PATH . 'students/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            move_uploaded_file($_FILES['photo']['tmp_name'], UPLOAD_PATH . $photo);
        }

        $stmt = $db->prepare("INSERT INTO students 
            (student_id, roll_number, name, name_bn, date_of_birth, gender, religion, blood_group,
             class_id, section_id, academic_year, admission_date, father_name, father_name_en, father_phone,
             mother_name, mother_name_en, mother_phone, guardian_phone, address_present, 
             previous_school, birth_certificate_no, photo, secret_code, status,
             monthly_fee, is_hostel, hostel_fee, is_hostel_food, food_fee, created_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())");
        $stmt->execute([
            $studentId, $rollNo, $name, $nameBn, $dob ?: null, $gender, $religion, $bloodGroup,
            $classId, $sectionId, date('Y'), $admDate, $fatherName, $fatherNameEn, $fatherPhone,
            $motherName, $motherNameEn, $motherPhone, $guardianPhone, $address,
            $prevSchool, $birthCert, $photo, $secretCode, 'active',
            $monthlyFee, $isHostel, $hostelFee, $isHostelFood, $foodFee
        ]);
        $newId = $db->lastInsertId();

        // Create parent user account
        if ($guardianPhone) {
            $existing = $db->prepare("SELECT id FROM users WHERE phone=?");
            $existing->execute([$guardianPhone]);
            if (!$existing->fetch()) {
                $hashedPw = password_hash($guardianPhone, PASSWORD_DEFAULT);
                $uStmt = $db->prepare("INSERT INTO users (name, name_bn, username, phone, password, role_id) VALUES (?,?,?,?,?,5)");
                $uStmt->execute([$fatherName ?: 'অভিভাবক', $fatherName, $guardianPhone, $guardianPhone, $hashedPw]);
            }
        }

        logActivity($_SESSION['user_id'], 'student_admit', 'students', "ছাত্র ভর্তি: $name ($studentId)");
        setFlash('success', "ছাত্র সফলভাবে ভর্তি হয়েছে! ID: $studentId | Secret Code: $secretCode");
        header('Location: view.php?id=' . $newId);
        exit;
    }
}

require_once '../../includes/header.php';
?>
<div class="section-header">
    <h2 class="section-title"><i class="fas fa-user-plus"></i> নতুন ছাত্র ভর্তি</h2>
    <a href="list.php" class="btn btn-outline"><i class="fas fa-list"></i> তালিকা</a>
</div>

<form method="POST" enctype="multipart/form-data">
<input type="hidden" name="csrf" value="<?= getCsrfToken() ?>">

<!-- ছাত্রের তথ্য -->
<div class="card mb-24">
    <div class="card-header" style="background:#ebf5fb;">
        <span class="card-title" style="color:var(--primary);"><i class="fas fa-user-graduate"></i> ছাত্রের ব্যক্তিগত তথ্য</span>
    </div>
    <div class="card-body">
        <div class="form-grid">
            <div class="form-group">
                <label>নাম (বাংলায়) <span>*</span></label>
                <input type="text" name="name_bn" class="form-control" placeholder="মুহাম্মদ আব্দুল্লাহ" required>
            </div>
            <div class="form-group">
                <label>নাম (ইংরেজিতে) <span>*</span></label>
                <input type="text" name="name" class="form-control" placeholder="Muhammad Abdullah" required>
            </div>
            <div class="form-group">
                <label>জন্ম তারিখ</label>
                <input type="date" name="dob" class="form-control" max="<?= date('Y-m-d') ?>">
            </div>
            <div class="form-group">
                <label>লিঙ্গ</label>
                <select name="gender" class="form-control">
                    <option value="male">ছেলে</option>
                    <option value="female">মেয়ে</option>
                </select>
            </div>
            <div class="form-group">
                <label>ধর্ম</label>
                <select name="religion" class="form-control">
                    <option value="islam">ইসলাম</option>
                    <option value="hinduism">হিন্দু</option>
                    <option value="christianity">খ্রিস্টান</option>
                    <option value="buddhism">বৌদ্ধ</option>
                </select>
            </div>
            <div class="form-group">
                <label>রক্তের গ্রুপ</label>
                <select name="blood_group" class="form-control">
                    <option value="">অজানা</option>
                    <option>A+</option><option>A-</option>
                    <option>B+</option><option>B-</option>
                    <option>AB+</option><option>AB-</option>
                    <option>O+</option><option>O-</option>
                </select>
            </div>
            <div class="form-group">
                <label>জন্ম নিবন্ধন নং</label>
                <input type="text" name="birth_cert" class="form-control">
            </div>
            <div class="form-group">
                <label>ভর্তির তারিখ</label>
                <input type="date" name="admission_date" class="form-control" value="<?= date('Y-m-d') ?>">
            </div>
        </div>
        <div class="form-grid" style="margin-top:16px;">
            <div class="form-group" style="grid-column: 1/-1;">
                <label>বর্তমান ঠিকানা</label>
                <textarea name="address" class="form-control" rows="2" placeholder="গ্রাম, ইউনিয়ন, উপজেলা, জেলা"></textarea>
            </div>
            <div class="form-group">
                <label>পূর্ববর্তী প্রতিষ্ঠান</label>
                <input type="text" name="prev_school" class="form-control" placeholder="আগের স্কুল/মাদ্রাসার নাম">
            </div>
            <div class="form-group">
                <label>ছবি</label>
                <input type="file" name="photo" class="form-control" accept="image/*">
            </div>
        </div>
    </div>
</div>

<!-- শ্রেণী তথ্য -->
<div class="card mb-24">
    <div class="card-header" style="background:#eafaf1;">
        <span class="card-title" style="color:var(--success);"><i class="fas fa-school"></i> শ্রেণী তথ্য</span>
    </div>
    <div class="card-body">
        <div class="form-grid">
            <div class="form-group">
                <label>শ্রেণী <span>*</span></label>
                <select name="class_id" class="form-control" required id="classSelect" onchange="loadSections(this.value)">
                    <option value="">শ্রেণী নির্বাচন করুন</option>
                    <?php foreach ($classes as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= e($c['class_name_bn'] ?? $c['class_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>শাখা/সেকশন</label>
                <select name="section_id" class="form-control" id="sectionSelect">
                    <option value="">শ্রেণী নির্বাচন করুন আগে</option>
                </select>
            </div>
        </div>
    </div>
</div>

<!-- অভিভাবক তথ্য -->
<div class="card mb-24">
    <div class="card-header" style="background:#fef9e7;">
        <span class="card-title" style="color:var(--accent);"><i class="fas fa-users"></i> অভিভাবকের তথ্য</span>
    </div>
    <div class="card-body">
        <div class="form-grid">
            <div class="form-group">
                <label>পিতার নাম (বাংলায়)</label>
                <input type="text" name="father_name" class="form-control" placeholder="আব্দুর রহমান">
            </div>
            <div class="form-group">
                <label>পিতার নাম (ইংরেজিতে)</label>
                <input type="text" name="father_name_en" class="form-control" placeholder="Abdur Rahman">
            </div>
            <div class="form-group">
                <label>পিতার মোবাইল</label>
                <input type="tel" name="father_phone" class="form-control" placeholder="01XXXXXXXXX">
            </div>
            <div class="form-group">
                <label>মাতার নাম (বাংলায়)</label>
                <input type="text" name="mother_name" class="form-control" placeholder="ফাতেমা বেগম">
            </div>
            <div class="form-group">
                <label>মাতার নাম (ইংরেজিতে)</label>
                <input type="text" name="mother_name_en" class="form-control" placeholder="Fatema Begum">
            </div>
            <div class="form-group">
                <label>মাতার মোবাইল</label>
                <input type="tel" name="mother_phone" class="form-control" placeholder="01XXXXXXXXX">
            </div>
        </div>
        <div class="alert alert-info mt-16" style="padding:10px 14px;background:#ebf5fb;border-radius:8px;font-size:13px;">
            <i class="fas fa-info-circle"></i>
            পিতার মোবাইল থাকলে সেটা অভিভাবকের নম্বর হিসেবে ব্যবহার হবে। না থাকলে মাতার নম্বর ব্যবহার হবে।
        </div>
    </div>
</div>

<!-- ফি তথ্য -->
<div class="card mb-24">
    <div class="card-header" style="background:#f4ecf7;">
        <span class="card-title" style="color:#7d3c98;"><i class="fas fa-money-bill-wave"></i> ফি তথ্য</span>
    </div>
    <div class="card-body">
        <div class="form-grid">
            <div class="form-group">
                <label>মাসিক বেতন (টাকা) <span>*</span></label>
                <input type="number" name="monthly_fee" class="form-control" placeholder="০" min="0" step="0.01" required>
            </div>
        </div>

        <!-- হোস্টেল -->
        <div style="margin-top:16px;">
            <label style="display:flex;align-items:center;gap:10px;font-weight:600;cursor:pointer;">
                <input type="checkbox" name="is_hostel" id="isHostelCheck" onchange="toggleHostel(this)" style="width:18px;height:18px;">
                হোস্টেলে থাকবে
            </label>
        </div>

        <div id="hostelFields" style="display:none;margin-top:16px;padding:16px;background:#faf4ff;border-radius:10px;border:1px dashed #c39bd3;">
            <div class="form-grid">
                <div class="form-group">
                    <label>হোস্টেল ফি (টাকা/মাস)</label>
                    <input type="number" name="hostel_fee" id="hostelFee" class="form-control" placeholder="০" min="0" step="0.01">
                </div>
            </div>

            <!-- খাবার -->
            <div style="margin-top:12px;">
                <label style="display:flex;align-items:center;gap:10px;font-weight:600;cursor:pointer;">
                    <input type="checkbox" name="is_hostel_food" id="isHostelFoodCheck" onchange="toggleFood(this)" style="width:18px;height:18px;">
                    হোস্টেলের খাবার খাবে
                </label>
            </div>

            <div id="foodFields" style="display:none;margin-top:12px;">
                <div class="form-grid">
                    <div class="form-group">
                        <label>খাবার ফি (টাকা/মাস)</label>
                        <input type="number" name="food_fee" id="foodFee" class="form-control" placeholder="০" min="0" step="0.01">
                    </div>
                </div>
            </div>
        </div>

        <!-- মোট দেখানো -->
        <div id="totalBox" style="display:none;margin-top:16px;padding:12px 16px;background:#eafaf1;border-radius:8px;border-left:4px solid var(--success);">
            <strong>মোট মাসিক খরচ:</strong>
            <span id="totalAmount" style="font-size:18px;font-weight:700;color:var(--success);margin-left:8px;">৳ ০</span>
        </div>
    </div>
</div>

<div style="display:flex;gap:12px;">
    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> ভর্তি করুন</button>
    <a href="list.php" class="btn btn-outline"><i class="fas fa-times"></i> বাতিল</a>
</div>
</form>

<script>
function toggleHostel(cb) {
    document.getElementById('hostelFields').style.display = cb.checked ? 'block' : 'none';
    if (!cb.checked) {
        document.getElementById('isHostelFoodCheck').checked = false;
        document.getElementById('foodFields').style.display = 'none';
        document.getElementById('hostelFee').value = '';
        document.getElementById('foodFee').value = '';
    }
    calcTotal();
}

function toggleFood(cb) {
    document.getElementById('foodFields').style.display = cb.checked ? 'block' : 'none';
    if (!cb.checked) document.getElementById('foodFee').value = '';
    calcTotal();
}

function calcTotal() {
    const monthly = parseFloat(document.querySelector('[name=monthly_fee]').value) || 0;
    const hostel  = parseFloat(document.getElementById('hostelFee').value) || 0;
    const food    = parseFloat(document.getElementById('foodFee').value) || 0;
    const total   = monthly + hostel + food;
    const box     = document.getElementById('totalBox');
    box.style.display = (monthly > 0) ? 'block' : 'none';
    document.getElementById('totalAmount').textContent = '৳ ' + total.toLocaleString('bn-BD');
}

// সব fee ইনপুটে লাইভ আপডেট
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[name=monthly_fee],[name=hostel_fee],[name=food_fee]').forEach(el => {
        el.addEventListener('input', calcTotal);
    });
});

function loadSections(classId) {
    if (!classId) return;
    fetch('<?= BASE_URL ?>/api/sections.php?class_id=' + classId)
        .then(r => r.json())
        .then(data => {
            const sel = document.getElementById('sectionSelect');
            sel.innerHTML = '<option value="">সেকশন নির্বাচন করুন</option>';
            data.forEach(s => {
                sel.innerHTML += `<option value="${s.id}">${s.section_name}</option>`;
            });
        });
}
</script>

<?php require_once '../../includes/footer.php'; ?>
