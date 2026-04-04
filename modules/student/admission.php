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
    $fatherPhone = trim($_POST['father_phone'] ?? '');
    $motherName = trim($_POST['mother_name'] ?? '');
    $guardianPhone = trim($_POST['guardian_phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $religion = $_POST['religion'] ?? 'islam';
    $bloodGroup = $_POST['blood_group'] ?? '';
    $prevSchool = trim($_POST['prev_school'] ?? '');
    $birthCert = trim($_POST['birth_cert'] ?? '');

    if (!$name || !$classId) {
        setFlash('danger', 'নাম ও শ্রেণী আবশ্যক।');
    } else {
        $studentId = generateStudentId($classId);
        $rollNo = $db->query("SELECT COUNT(*)+1 FROM students WHERE class_id=$classId AND academic_year='".date('Y')."'")->fetchColumn();

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
             class_id, section_id, academic_year, admission_date, father_name, father_phone, mother_name,
             guardian_phone, address_present, previous_school, birth_certificate_no, photo, status, created_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())");
        $stmt->execute([
            $studentId, $rollNo, $name, $nameBn, $dob ?: null, $gender, $religion, $bloodGroup,
            $classId, $sectionId, date('Y'), $admDate, $fatherName, $fatherPhone, $motherName,
            $guardianPhone, $address, $prevSchool, $birthCert, $photo, 'active'
        ]);
        $newId = $db->lastInsertId();

        // Create parent user account if phone provided
        if ($guardianPhone || $fatherPhone) {
            $phone = $guardianPhone ?: $fatherPhone;
            $existing = $db->prepare("SELECT id FROM users WHERE phone=?");
            $existing->execute([$phone]);
            if (!$existing->fetch()) {
                $hashedPw = password_hash($phone, PASSWORD_DEFAULT);
                $uStmt = $db->prepare("INSERT INTO users (name, name_bn, username, phone, password, role_id) VALUES (?,?,?,?,?,5)");
                $uStmt->execute([$fatherName ?: 'অভিভাবক', $fatherName, $phone, $phone, $hashedPw]);
            }
        }

        logActivity($_SESSION['user_id'], 'student_admit', 'students', "ছাত্র ভর্তি: $name ($studentId)");
        setFlash('success', "ছাত্র সফলভাবে ভর্তি হয়েছে! ID: $studentId");
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
                <input type="text" name="father_name" class="form-control">
            </div>
            <div class="form-group">
                <label>পিতার মোবাইল <span style="color:var(--info);">(লগইন নম্বর)</span></label>
                <input type="tel" name="father_phone" class="form-control" placeholder="01XXXXXXXXX">
            </div>
            <div class="form-group">
                <label>মাতার নাম</label>
                <input type="text" name="mother_name" class="form-control">
            </div>
            <div class="form-group">
                <label>অভিভাবকের মোবাইল</label>
                <input type="tel" name="guardian_phone" class="form-control" placeholder="01XXXXXXXXX">
            </div>
        </div>
        <div class="alert alert-info mt-16">
            <i class="fas fa-info-circle"></i>
            পিতা/অভিভাবকের ফোন নম্বর দিয়ে স্বয়ংক্রিয়ভাবে অভিভাবক পোর্টাল অ্যাকাউন্ট তৈরি হবে। পাসওয়ার্ড হবে ফোন নম্বর।
        </div>
    </div>
</div>

<div style="display:flex;gap:12px;">
    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> ভর্তি করুন</button>
    <a href="list.php" class="btn btn-outline"><i class="fas fa-times"></i> বাতিল</a>
</div>
</form>

<script>
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
