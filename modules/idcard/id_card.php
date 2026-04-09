<?php
require_once '../../includes/functions.php';
requireLogin(['super_admin','principal']);
$pageTitle = 'আইডি কার্ড জেনারেটর';
$db = getDB();

$classes  = $db->query("SELECT * FROM classes WHERE is_active=1 ORDER BY class_numeric")->fetchAll();
$filterClass = (int)($_GET['class_id'] ?? 0);
$filterIds   = $_GET['ids'] ?? ''; // comma separated ids
$design      = $_GET['design'] ?? 'modern';
$type        = $_GET['type'] ?? 'student'; // student, teacher, staff
$printMode   = isset($_GET['print']);

// ===== ডেটা লোড (ধরন অনুযায়ী) =====
$students = []; // সব ধরনের কার্ডই এই array তে থাকবে

if ($type === 'teacher') {
    // শিক্ষক লোড
    if ($filterIds) {
        $ids = array_map('intval', explode(',', $filterIds));
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $db->prepare("SELECT *, teacher_id_no AS student_id, name_bn, name,
            designation_bn, phone AS father_phone, blood_group,
            '' AS class_name_bn, '' AS section_name, '' AS roll_number, '' AS father_name_bn
            FROM teachers WHERE id IN ($placeholders) AND is_active=1 ORDER BY name_bn");
        $stmt->execute($ids);
        $students = $stmt->fetchAll();
    } else {
        $students = $db->query("SELECT *, teacher_id_no AS student_id, name_bn, name,
            designation_bn, phone AS father_phone, blood_group,
            '' AS class_name_bn, '' AS section_name, '' AS roll_number, '' AS father_name_bn
            FROM teachers WHERE is_active=1 ORDER BY name_bn")->fetchAll();
    }

} elseif ($type === 'staff') {
    // স্টাফ লোড
    if ($filterIds) {
        $ids = array_map('intval', explode(',', $filterIds));
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $db->prepare("SELECT *, staff_id AS student_id, name_bn, name,
            designation_bn, phone AS father_phone, blood_group,
            '' AS class_name_bn, '' AS section_name, '' AS roll_number, '' AS father_name_bn
            FROM staff WHERE id IN ($placeholders) AND is_active=1 ORDER BY name_bn");
        $stmt->execute($ids);
        $students = $stmt->fetchAll();
    } else {
        $students = $db->query("SELECT *, staff_id AS student_id, name_bn, name,
            designation_bn, phone AS father_phone, blood_group,
            '' AS class_name_bn, '' AS section_name, '' AS roll_number, '' AS father_name_bn
            FROM staff WHERE is_active=1 ORDER BY name_bn")->fetchAll();
    }

} else {
    // ছাত্র লোড
    if ($filterIds) {
        $ids = array_map('intval', explode(',', $filterIds));
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $db->prepare("SELECT s.*, c.class_name_bn, c.class_name, sec.section_name
            FROM students s
            LEFT JOIN classes c ON s.class_id = c.id
            LEFT JOIN sections sec ON s.section_id = sec.id
            WHERE s.id IN ($placeholders) AND s.status='active'
            ORDER BY s.roll_number");
        $stmt->execute($ids);
        $students = $stmt->fetchAll();
    } elseif ($filterClass) {
        $stmt = $db->prepare("SELECT s.*, c.class_name_bn, c.class_name, sec.section_name
            FROM students s
            LEFT JOIN classes c ON s.class_id = c.id
            LEFT JOIN sections sec ON s.section_id = sec.id
            WHERE s.class_id=? AND s.status='active'
            ORDER BY s.roll_number");
        $stmt->execute([$filterClass]);
        $students = $stmt->fetchAll();
    }
}

$instituteName    = getSetting('institute_name', 'আন নাজাহ তাহফিজুল কুরআন মাদরাসা');
$instituteAddress = getSetting('address', 'পান্ধোয়া বাজার, আশুলিয়া, সাভার, ঢাকা');
$institutePhone   = getSetting('phone', '01715-821661');
$instituteWeb     = getSetting('website', 'www.annazah.com');
$logoPath         = getSetting('logo', '');

// ===== ডিজাইন সেটিংস লোড =====
$idc = [
    'logo'        => getSetting('id_card_logo_b64',''),
    'strip_svg'   => getSetting('id_card_strip_svg',''),
    'use_svg'     => getSetting('id_card_strip_use_custom_svg','0'),
    'sc1'         => getSetting('id_card_strip_color1','#1a8a3c'),
    'sc2'         => getSetting('id_card_strip_color2','#e67e22'),
    'label_font'  => getSetting('id_card_label_font','Hind Siliguri'),
    'label_size'  => getSetting('id_card_label_size','9'),
    'label_w'     => getSetting('id_card_label_weight','700'),
    'label_style' => getSetting('id_card_label_style','normal'),
    'label_color' => getSetting('id_card_label_color','#ffffff'),
    'label_ls'    => getSetting('id_card_label_spacing','2'),
    'name_font'   => getSetting('id_card_name_font','Libre Baskerville'),
    'name_size'   => getSetting('id_card_name_size','14'),
    'name_w'      => getSetting('id_card_name_weight','700'),
    'name_color'  => getSetting('id_card_name_color','#1a8a3c'),
    'id_font'     => getSetting('id_card_id_font','Hind Siliguri'),
    'id_size'     => getSetting('id_card_id_size','8.5'),
    'id_color'    => getSetting('id_card_id_color','#555555'),
    'tb_font'     => getSetting('id_card_table_font','Hind Siliguri'),
    'tb_size'     => getSetting('id_card_table_size','8'),
    'tb_lc'       => getSetting('id_card_table_label_color','#1a5276'),
    'tb_vc'       => getSetting('id_card_table_val_color','#333333'),
    'ar_font'     => getSetting('id_card_arabic_font','Hind Siliguri'),
    'ar_size'     => getSetting('id_card_arabic_size','7.5'),
    'ar_color'    => getSetting('id_card_arabic_color','#1a5276'),
    'bn_font'     => getSetting('id_card_bn_font','Hind Siliguri'),
    'bn_size'     => getSetting('id_card_bn_size','6.5'),
    'bn_color'    => getSetting('id_card_bn_color','#1a8a3c'),
    's_c1'        => getSetting('id_card_student_color1','#1a8a3c'),
    's_c2'        => getSetting('id_card_student_color2','#e67e22'),
    't_c1'        => getSetting('id_card_teacher_color1','#1a3a6b'),
    't_c2'        => getSetting('id_card_teacher_color2','#c9a227'),
    'sf_c1'       => getSetting('id_card_staff_color1','#5b2c8c'),
    'sf_c2'       => getSetting('id_card_staff_color2','#8e44ad'),
    'photo_bc'    => getSetting('id_card_photo_border_color','#e67e22'),
    'radius'      => getSetting('id_card_border_radius','10'),
    // হেডার নাম — image বা text
    'header_mode' => getSetting('id_card_header_mode','text'),       // 'text' বা 'image'
    'inst_img'    => getSetting('id_card_inst_name_img_b64',''),     // base64 institute name image
    'inst_img_h'  => getSetting('id_card_inst_name_img_height','32'), // image height (px)
    'logo_size'   => getSetting('id_card_logo_size','32'),
];
if ($type === 'teacher')   { $idc['c1']=$idc['t_c1'];  $idc['c2']=$idc['t_c2'];  }
elseif ($type === 'staff') { $idc['c1']=$idc['sf_c1']; $idc['c2']=$idc['sf_c2']; }
else                       { $idc['c1']=$idc['s_c1'];  $idc['c2']=$idc['s_c2'];  }

require_once '../../includes/header.php';
?>

<?php if (!$printMode): ?>
<!-- ===== কন্ট্রোল প্যানেল ===== -->
<div class="section-header no-print">
    <h2 class="section-title"><i class="fas fa-id-card"></i> আইডি কার্ড জেনারেটর</h2>
    <div style="display:flex;gap:8px;">
        <a href="id_card_settings.php" class="btn btn-outline btn-sm"><i class="fas fa-palette"></i> ডিজাইন সেটিংস</a>
        <?php if (!empty($students)): ?>
        <button onclick="printCards()" class="btn btn-primary"><i class="fas fa-print"></i> প্রিন্ট / PDF ডাউনলোড</button>
        <?php endif; ?>
    </div>
</div>

<!-- ফিল্টার -->
<div class="card mb-16 no-print">
    <div class="card-body" style="padding:16px 20px;">
        <form method="GET" id="filterForm">
            <div style="display:flex;flex-wrap:wrap;gap:14px;align-items:flex-end;">
                <!-- ধরন -->
                <div class="form-group" style="margin:0;flex:1;min-width:160px;">
                    <label style="font-size:12px;">ধরন</label>
                    <select name="type" class="form-control" style="padding:8px;" onchange="onTypeChange(this)">
                        <option value="student" <?= $type==='student'?'selected':'' ?>>ছাত্র</option>
                        <option value="teacher" <?= $type==='teacher'?'selected':'' ?>>শিক্ষক</option>
                        <option value="staff"   <?= $type==='staff'  ?'selected':'' ?>>স্টাফ</option>
                    </select>
                </div>
                <!-- শ্রেণী (শুধু ছাত্রের জন্য) -->
                <div class="form-group" style="margin:0;flex:1;min-width:160px;" id="classDiv" <?= $type!=='student'?'style="display:none;"':'' ?>>
                    <label style="font-size:12px;">শ্রেণী</label>
                    <select name="class_id" class="form-control" style="padding:8px;" onchange="this.form.submit()">
                        <option value="">সব শ্রেণী</option>
                        <?php foreach($classes as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $filterClass==$c['id']?'selected':'' ?>><?= e($c['class_name_bn']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <!-- ডিজাইন -->
                <div class="form-group" style="margin:0;flex:1;min-width:160px;">
                    <label style="font-size:12px;">ডিজাইন</label>
                    <select name="design" class="form-control" style="padding:8px;" onchange="this.form.submit()">
                        <option value="modern"  <?= $design==='modern' ?'selected':'' ?>>🔵 মডার্ন (নীল-কমলা)</option>
                        <option value="green"   <?= $design==='green'  ?'selected':'' ?>>🟢 গ্রিন (সবুজ-সাদা)</option>
                        <option value="classic" <?= $design==='classic'?'selected':'' ?>>⚫ ক্লাসিক (গাঢ় নীল)</option>
                        <option value="maroon"  <?= $design==='maroon' ?'selected':'' ?>>🔴 মেরুন (ঐতিহ্যবাহী)</option>
                    </select>
                </div>
            </div>
        </form>

        <?php
        // শিক্ষক/স্টাফ: সরাসরি সবাই দেখা যাবে, নির্বাচন বাটন দেখাব
        // ছাত্র: শ্রেণী বেছে নিলে তবে দেখাবে
        $showCheckboxControls = ($type !== 'student') || ($filterClass && !empty($students));
        if ($showCheckboxControls && !empty($students)):
        ?>
        <div style="margin-top:12px;display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
            <span style="font-size:13px;color:var(--text-muted);">নির্বাচন করুন:</span>
            <button onclick="selectAll()" class="btn btn-outline btn-sm">সবাই</button>
            <button onclick="selectNone()" class="btn btn-outline btn-sm">কেউ না</button>
            <button onclick="generateSelected()" class="btn btn-primary btn-sm"><i class="fas fa-id-card"></i> নির্বাচিতদের কার্ড দেখুন</button>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- চেকবক্স তালিকা -->
<?php if (!empty($students) && (($type === 'student' && $filterClass) || $type !== 'student')): ?>
<div class="card mb-16 no-print">
    <div class="card-header">
        <span class="card-title">
            মোট <?= toBanglaNumber(count($students)) ?> জন
            <?= $type==='teacher' ? 'শিক্ষক' : ($type==='staff' ? 'স্টাফ' : 'ছাত্র') ?>
        </span>
    </div>
    <div class="card-body" style="padding:12px 20px;">
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:8px;">
            <?php foreach($students as $s): ?>
            <label style="display:flex;align-items:center;gap:8px;padding:8px;border:1px solid var(--border);border-radius:8px;cursor:pointer;">
                <input type="checkbox" class="student-check" value="<?= $s['id'] ?>" checked>
                <div>
                    <div style="font-size:13px;font-weight:600;"><?= e($s['name_bn'] ?: $s['name']) ?></div>
                    <div style="font-size:11px;color:var(--text-muted);"><?= e($s['student_id'] ?? '') ?></div>
                </div>
            </label>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>
<?php endif; // !$printMode ?>

<!-- ===== আইডি কার্ড ===== -->
<?php if (!empty($students)): ?>
<div id="cardContainer" style="<?= $printMode ? '' : 'margin-top:24px;' ?>display:flex;flex-wrap:wrap;gap:20px;justify-content:flex-start;align-items:flex-start;">
    <?php foreach($students as $s):
        $name    = $s['name_bn'] ?: $s['name'];
        $nameEn  = $s['name'] ?? '';
        $nameParts   = explode(' ', $nameEn, 2);
        $firstNameEn = $nameParts[0] ?? '';
        $lastNameEn  = $nameParts[1] ?? '';

        // PHP 7 compatible: str_starts_with নেই, strpos দিয়ে করব
        $rawPhoto = $s['photo'] ?? '';
        if ($rawPhoto && strpos($rawPhoto, 'http') === 0) {
            $photoUrl = $rawPhoto; // Cloudinary full URL
        } elseif ($rawPhoto) {
            $photoUrl = BASE_URL . '/assets/uploads/' . $rawPhoto;
        } else {
            $photoUrl = '';
        }

        $classNameBn = $s['class_name_bn'] ?? '';
        $section     = $s['section_name'] ?? '';
        $roll        = $s['roll_number'] ?? '';
        $blood       = $s['blood_group'] ?? '';
        $stuId       = $s['student_id'] ?? '';
        $father      = $s['father_name_bn'] ?? '';
        $phone       = $s['father_phone'] ?? $s['guardian_phone'] ?? '';
    ?>

    <div class="id-card-pair">

        <!-- ===== পেছনের দিক (Back) ===== -->
        <div class="id-card card-back">
            <div class="back-watermark"><i class="fas fa-mosque"></i></div>
            <div class="back-content">
                <h3 class="back-title">Terms and Condition</h3>
                <p class="back-text">This ID card must be brought and worn whenever the student attends the madrasah. If this card is lost, the student or guardian must inform the office immediately. If anyone finds this card, please return it to An Nazah Tahfizul Quran Madrasah. Misuse, lending, or altering this card in any way is strictly prohibited.</p>

                <div class="back-bottom">
                    <div class="back-qr">
                        <div class="qr-box">
                            <svg viewBox="0 0 100 100" width="60" height="60" xmlns="http://www.w3.org/2000/svg">
                                <rect x="5" y="5" width="35" height="35" rx="3" fill="none" stroke="#e67e22" stroke-width="4"/>
                                <rect x="12" y="12" width="21" height="21" rx="1" fill="#e67e22"/>
                                <rect x="60" y="5" width="35" height="35" rx="3" fill="none" stroke="#e67e22" stroke-width="4"/>
                                <rect x="67" y="12" width="21" height="21" rx="1" fill="#e67e22"/>
                                <rect x="5" y="60" width="35" height="35" rx="3" fill="none" stroke="#e67e22" stroke-width="4"/>
                                <rect x="12" y="67" width="21" height="21" rx="1" fill="#e67e22"/>
                                <rect x="55" y="55" width="8" height="8" fill="#333"/>
                                <rect x="67" y="55" width="8" height="8" fill="#333"/>
                                <rect x="79" y="55" width="8" height="8" fill="#333"/>
                                <rect x="91" y="55" width="8" height="8" fill="#333"/>
                                <rect x="55" y="67" width="8" height="8" fill="#333"/>
                                <rect x="79" y="67" width="8" height="8" fill="#333"/>
                                <rect x="55" y="79" width="8" height="8" fill="#333"/>
                                <rect x="67" y="79" width="8" height="8" fill="#333"/>
                                <rect x="91" y="79" width="8" height="8" fill="#333"/>
                                <rect x="55" y="91" width="8" height="8" fill="#333"/>
                                <rect x="79" y="91" width="8" height="8" fill="#333"/>
                            </svg>
                        </div>
                        <div class="back-sig">
                            <div class="sig-line"></div>
                            <div class="sig-text">Principal's Signature</div>
                        </div>
                    </div>
                    <div class="back-address">
                        <p><?= e($instituteAddress) ?></p>
                        <p style="margin-top:5px;font-weight:700;">Mobile: <?= e($institutePhone) ?></p>
                        <p style="font-weight:700;"><?= e($instituteWeb) ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== সামনের দিক (Front) ===== -->
        <div class="id-card card-front <?= e($type) ?>" style="border-radius:<?= (int)$idc['radius'] ?>px;">
            <!-- সাইড স্ট্রিপ -->
            <div class="front-strip">
                <?php if ($idc['use_svg'] === '1' && $idc['strip_svg']): ?>
                    <div style="position:absolute;inset:0;overflow:hidden;"><?= $idc['strip_svg'] ?></div>
                <?php else: ?>
                    <div class="strip-green" style="background:<?= e($idc['sc1']) ?>;"></div>
                    <div class="strip-orange" style="background:<?= e($idc['sc2']) ?>;"></div>
                <?php endif; ?>
                <div class="strip-label" style="
                    font-family:'<?= e($idc['label_font']) ?>',sans-serif;
                    font-size:<?= e($idc['label_size']) ?>px;
                    font-weight:<?= e($idc['label_w']) ?>;
                    font-style:<?= e($idc['label_style']) ?>;
                    color:<?= e($idc['label_color']) ?>;
                    letter-spacing:<?= e($idc['label_ls']) ?>px;">
                    <?php
                    if ($type === 'teacher') echo 'TEACHER ID CARD';
                    elseif ($type === 'staff') echo 'STAFF ID CARD';
                    else echo 'STUDENT ID CARD';
                    ?>
                </div>
            </div>

            <div class="front-body">
                <!-- লোগো উপরে মাঝে + নিচে প্রতিষ্ঠানের নাম -->
                <div class="front-header" style="border-bottom-color:<?= e($idc['c1']) ?>;flex-direction:column;align-items:center;gap:3px;">
                    <!-- লোগো -->
                    <?php
                    $logoSize = (int)($idc['logo_size'] ?? 32);
                    if ($idc['logo']): ?>
                    <img src="<?= $idc['logo'] ?>" style="width:<?= $logoSize ?>px;height:<?= $logoSize ?>px;object-fit:contain;" alt="logo">
                    <?php elseif($logoPath): ?>
                    <img src="<?= BASE_URL.'/'.$logoPath ?>" style="width:<?= $logoSize ?>px;height:<?= $logoSize ?>px;object-fit:contain;" alt="logo">
                    <?php else: ?>
                    <div style="width:<?= $logoSize ?>px;height:<?= $logoSize ?>px;background:linear-gradient(135deg,<?= e($idc['c1']) ?>,<?= e($idc['c2']) ?>);border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-size:<?= (int)($logoSize*0.45) ?>px;flex-shrink:0;">
                        <i class="fas fa-mosque"></i>
                    </div>
                    <?php endif; ?>

                    <!-- প্রতিষ্ঠানের নাম: image অথবা text -->
                    <?php if ($idc['header_mode'] === 'image' && $idc['inst_img']): ?>
                    <img src="<?= $idc['inst_img'] ?>" style="max-width:100%;height:<?= (int)$idc['inst_img_h'] ?>px;object-fit:contain;" alt="institute name">
                    <?php else: ?>
                    <div style="text-align:center;width:100%;">
                        <div style="font-family:'<?= e($idc['ar_font']) ?>',sans-serif;font-size:<?= e($idc['ar_size']) ?>px;color:<?= e($idc['ar_color']) ?>;font-weight:600;line-height:1.3;direction:rtl;">
                            مدرسة النجاح لتحفيظ القرآن
                        </div>
                        <div style="font-family:'<?= e($idc['bn_font']) ?>',sans-serif;font-size:<?= e($idc['bn_size']) ?>px;color:<?= e($idc['bn_color']) ?>;font-weight:700;line-height:1.3;">
                            <?= e($instituteName) ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- ছাত্রের ছবি -->
                <div class="front-photo-wrap">
                    <?php if($photoUrl): ?>
                    <img src="<?= e($photoUrl) ?>" class="front-photo" style="border-color:<?= e($idc['photo_bc']) ?>;" alt="photo">
                    <?php else: ?>
                    <div class="front-photo-avatar" style="border-color:<?= e($idc['photo_bc']) ?>;color:<?= e($idc['c1']) ?>;"><?= mb_substr($name, 0, 1) ?></div>
                    <?php endif; ?>
                </div>

                <!-- নাম -->
                <div class="front-name">
                    <span class="name-first" style="
                        font-family:'<?= e($idc['name_font']) ?>',serif;
                        font-size:<?= e($idc['name_size']) ?>px;
                        font-weight:<?= e($idc['name_w']) ?>;
                        color:<?= e($idc['name_color']) ?>;">
                        <?= e($firstNameEn ?: $name) ?>
                    </span>
                    <?php if($lastNameEn): ?>
                    <span class="name-last" style="
                        font-family:'<?= e($idc['name_font']) ?>',serif;
                        font-size:<?= e($idc['name_size']) ?>px;">
                        <?= e($lastNameEn) ?>
                    </span>
                    <?php endif; ?>
                </div>
                <div class="front-id" style="
                    font-family:'<?= e($idc['id_font']) ?>',sans-serif;
                    font-size:<?= e($idc['id_size']) ?>px;
                    color:<?= e($idc['id_color']) ?>;">
                    ID: <?= e($stuId) ?>
                </div>

                <div class="front-table" style="border-top-color:<?= e($idc['c1']) ?>;">
                    <?php
                    // টেবিল সারির ইনলাইন স্টাইল
                    $rowStyle  = 'font-family:\'' . e($idc['tb_font']) . '\',sans-serif;font-size:' . e($idc['tb_size']) . 'px;';
                    $lblStyle  = 'color:' . e($idc['tb_lc']) . ';';
                    $valStyle  = 'color:' . e($idc['tb_vc']) . ';';
                    ?>
                    <?php if ($type === 'teacher' || $type === 'staff'): ?>
                    <div class="front-row" style="<?= $rowStyle ?>"><span class="fr-label" style="<?= $lblStyle ?>">পদবী</span><span class="fr-val" style="<?= $valStyle ?>">:<?= e($s['designation_bn'] ?? '-') ?></span></div>
                    <div class="front-row" style="<?= $rowStyle ?>"><span class="fr-label" style="<?= $lblStyle ?>">ID</span><span class="fr-val" style="<?= $valStyle ?>">:<?= e($stuId) ?></span></div>
                    <div class="front-row" style="<?= $rowStyle ?>"><span class="fr-label" style="<?= $lblStyle ?>">Phone</span><span class="fr-val" style="<?= $valStyle ?>">:<?= e($phone) ?></span></div>
                    <div class="front-row" style="<?= $rowStyle ?>"><span class="fr-label" style="<?= $lblStyle ?>">Blood</span><span class="fr-val" style="<?= $valStyle ?>">:<?= e($blood ?: 'N/A') ?></span></div>
                    <?php else: ?>
                    <div class="front-row" style="<?= $rowStyle ?>"><span class="fr-label" style="<?= $lblStyle ?>">Class</span><span class="fr-val" style="<?= $valStyle ?>">:<?= e($classNameBn) ?></span></div>
                    <?php if($section): ?>
                    <div class="front-row" style="<?= $rowStyle ?>"><span class="fr-label" style="<?= $lblStyle ?>">Group</span><span class="fr-val" style="<?= $valStyle ?>">:<?= e($section) ?></span></div>
                    <?php endif; ?>
                    <div class="front-row" style="<?= $rowStyle ?>"><span class="fr-label" style="<?= $lblStyle ?>">Roll</span><span class="fr-val" style="<?= $valStyle ?>">:<?= e($roll) ?></span></div>
                    <div class="front-row" style="<?= $rowStyle ?>"><span class="fr-label" style="<?= $lblStyle ?>">Blood</span><span class="fr-val" style="<?= $valStyle ?>">:<?= e($blood ?: 'N/A') ?></span></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>
    <?php endforeach; ?>
</div>
<?php elseif (!$printMode): ?>
<div class="card"><div class="card-body" style="text-align:center;padding:48px;color:var(--text-muted);">
    <i class="fas fa-id-card" style="font-size:48px;margin-bottom:16px;opacity:.3;display:block;"></i>
    <p style="font-size:16px;">
        <?= $type === 'student' ? 'শ্রেণী নির্বাচন করুন অথবা ছাত্র বেছে নিন' : 'কোনো তথ্য পাওয়া যায়নি' ?>
    </p>
</div></div>
<?php endif; ?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;600;700&family=Libre+Baskerville:wght@400;700&display=swap');

/* ===== আইডি কার্ড কন্টেইনার আইসোলেশন ===== */
#cardContainer {
    /* header.php এর global styles override করতে */
}

/* global table reset যাতে কার্ডের ভেতরে affect না করে */
.id-card table, .id-card thead, .id-card tbody, .id-card tr, .id-card th, .id-card td {
    all: unset;
    display: revert;
}

.id-card-pair {
    display: flex;
    flex-direction: row;
    gap: 10px;
    align-items: flex-start;
}
.id-card {
    width: 204px;
    min-width: 204px;
    max-width: 204px;
    height: 323px;
    min-height: 323px;
    max-height: 323px;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 6px 24px rgba(0,0,0,.18);
    position: relative;
    font-family: 'Hind Siliguri', sans-serif;
    box-sizing: border-box;
    flex-shrink: 0;
}
/* FRONT */
.card-front {
    background: #fff;
    display: flex;
}
.front-strip {
    width: 30px;
    position: relative;
    flex-shrink: 0;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
}
.strip-green {
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 55%;
    background: #1a8a3c;
    clip-path: polygon(0 0, 100% 0, 100% 85%, 0 100%);
}
.strip-orange {
    position: absolute;
    bottom: 0; left: 0; right: 0;
    height: 55%;
    background: #e67e22;
    clip-path: polygon(0 15%, 100% 0, 100% 100%, 0 100%);
}
.strip-label {
    position: relative; z-index: 2;
    color: #fff; font-size: 9px; font-weight: 700;
    letter-spacing: 2px;
    writing-mode: vertical-rl; text-orientation: mixed;
    transform: rotate(180deg);
    white-space: nowrap;
    text-shadow: 0 1px 3px rgba(0,0,0,.5);
}
.front-body {
    flex: 1; display: flex; flex-direction: column;
    padding: 8px 8px 8px 6px;
}
.front-header {
    display: flex; align-items: center; justify-content: center;
    flex-direction: column; gap: 3px;
    border-bottom: 2px solid #1a8a3c;
    padding-bottom: 5px; margin-bottom: 6px;
}
.front-logo { width: 32px; height: 32px; object-fit: contain; flex-shrink: 0; }
.front-logo-placeholder {
    width: 32px; height: 32px;
    background: linear-gradient(135deg,#1a8a3c,#e67e22);
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-size: 15px; flex-shrink: 0;
}
.front-institute { flex: 1; text-align: center; }
.front-institute-arabic {
    font-size: 7.5px; color: #1a5276; font-weight: 600;
    line-height: 1.3; direction: rtl; text-align: center;
}
.front-institute-bn {
    font-size: 6.5px; color: #1a8a3c; font-weight: 700;
    line-height: 1.3; text-align: center;
}
.front-photo-wrap { text-align: center; margin: 4px 0; }
.front-photo {
    width: 80px; height: 95px; object-fit: cover;
    border: 3px solid #e67e22; border-radius: 4px; display: inline-block;
}
.front-photo-avatar {
    width: 80px; height: 95px;
    background: #f0f8f0;
    border: 3px solid #e67e22;
    border-radius: 4px;
    display: inline-flex;
    align-items: center; justify-content: center;
    font-size: 32px; font-weight: 700; color: #1a8a3c;
}
.front-name { text-align: center; margin-top: 6px; line-height: 1.2; }
.name-first { font-size: 14px; font-weight: 700; color: #1a8a3c; font-family: 'Libre Baskerville', serif; }
.name-last  { font-size: 14px; font-weight: 400; color: #333; font-family: 'Libre Baskerville', serif; }
.front-id { text-align: center; font-size: 8.5px; font-weight: 700; color: #555; margin: 2px 0 5px; letter-spacing: 0.5px; }
.front-table { border-top: 1px dashed #1a8a3c; padding-top: 5px; display: block !important; }
.front-row { display: flex !important; font-size: 8px; line-height: 1.8; color: #333; margin: 0; padding: 0; border: none; background: none; }
.fr-label { width: 38px; color: #1a5276; font-weight: 600; flex-shrink: 0; }
.fr-val { flex: 1; }

/* BACK */
.card-back {
    background: #fff; border: 1px solid #ddd;
    display: flex; flex-direction: column;
    position: relative; overflow: hidden;
    border-radius: 10px;
}
.back-watermark {
    position: absolute; top: 50%; left: 50%;
    transform: translate(-50%,-50%);
    font-size: 90px; color: rgba(26,138,60,.06); pointer-events: none;
}
.back-content {
    padding: 12px 10px 8px;
    display: flex; flex-direction: column; height: 100%;
    position: relative; z-index: 1;
}
.back-title { font-size: 10px; font-weight: 700; color: #1a5276; text-align: center; margin-bottom: 7px; font-family: 'Libre Baskerville', serif; }
.back-text { font-size: 6.5px; color: #444; line-height: 1.7; text-align: justify; flex: 1; }
.back-bottom { margin-top: 8px; border-top: 1px solid #e67e22; padding-top: 7px; display: flex; flex-direction: column; gap: 5px; }
.back-qr { display: flex; align-items: center; justify-content: space-between; }
.qr-box { background: #fff8f0; border: 1px solid #e67e22; border-radius: 4px; padding: 3px; }
.back-sig { text-align: center; }
.sig-line { width: 60px; border-top: 1px solid #333; margin: 0 auto 2px; }
.sig-text { font-size: 6px; color: #555; }
.back-address { font-size: 6.5px; color: #444; text-align: center; line-height: 1.6; }

/* TEACHER — নীল-সোনালি */
.card-front.teacher .strip-green { background: #1a3a6b; }
.card-front.teacher .strip-orange { background: #c9a227; }
.card-front.teacher .front-header { border-bottom-color: #1a3a6b; }
.card-front.teacher .front-institute-arabic { color: #1a3a6b; }
.card-front.teacher .front-institute-bn { color: #c9a227; }
.card-front.teacher .front-photo { border-color: #c9a227; }
.card-front.teacher .front-photo-avatar { background: #eef2f8; border-color: #c9a227; color: #1a3a6b; }
.card-front.teacher .front-logo-placeholder { background: linear-gradient(135deg,#1a3a6b,#c9a227); }
.card-front.teacher .name-first { color: #1a3a6b; }
.card-front.teacher .fr-label { color: #1a3a6b; }
.card-front.teacher .front-table { border-top-color: #c9a227; }

/* STAFF — বেগুনি-রুপালি */
.card-front.staff .strip-green { background: #5b2c8c; }
.card-front.staff .strip-orange { background: #8e44ad; }
.card-front.staff .front-header { border-bottom-color: #5b2c8c; }
.card-front.staff .front-institute-arabic { color: #5b2c8c; }
.card-front.staff .front-institute-bn { color: #8e44ad; }
.card-front.staff .front-photo { border-color: #8e44ad; }
.card-front.staff .front-photo-avatar { background: #f5eefb; border-color: #8e44ad; color: #5b2c8c; }
.card-front.staff .front-logo-placeholder { background: linear-gradient(135deg,#5b2c8c,#8e44ad); }
.card-front.staff .name-first { color: #5b2c8c; }
.card-front.staff .fr-label { color: #5b2c8c; }
.card-front.staff .front-table { border-top-color: #8e44ad; }

/* PRINT */
@media print {
    .no-print { display: none !important; }
    body { margin: 0; padding: 0; background: #fff; }
    #cardContainer { display: flex; flex-wrap: wrap; gap: 5mm; padding: 5mm; }
    .id-card-pair { margin: 0; }
    .id-card { box-shadow: none; border: 1px solid #ccc; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .sidebar, .topbar, header, nav { display: none !important; }
    .main-wrapper { margin-left: 0 !important; }
    .content { padding: 0 !important; }
}
@page { size: A4 portrait; margin: 8mm; }
</style>

<script>
function onTypeChange(sel) {
    var classDiv = document.getElementById('classDiv');
    if (classDiv) {
        classDiv.style.display = sel.value === 'student' ? '' : 'none';
    }
    // class_id রিসেট করে ফর্ম সাবমিট
    var classSelect = document.querySelector('select[name="class_id"]');
    if (classSelect) classSelect.value = '';
    sel.form.submit();
}
function selectAll() {
    document.querySelectorAll('.student-check').forEach(function(c){ c.checked = true; });
}
function selectNone() {
    document.querySelectorAll('.student-check').forEach(function(c){ c.checked = false; });
}
function generateSelected() {
    var ids = [];
    document.querySelectorAll('.student-check:checked').forEach(function(c){ ids.push(c.value); });
    if (!ids.length) { alert('কমপক্ষে একজন নির্বাচন করুন।'); return; }
    var params = new URLSearchParams(window.location.search);
    params.set('ids', ids.join(','));
    params.delete('class_id'); // class_id না থাকলে ids দিয়ে লোড হবে
    window.location.href = '?' + params.toString();
}
function printCards() {
    window.print();
}
</script>

<?php require_once '../../includes/footer.php'; ?>
