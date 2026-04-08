<?php
require_once '../../includes/functions.php';
requireLogin(['super_admin','principal']);
$pageTitle = 'আইডি কার্ড জেনারেটর';
$db = getDB();

$classes  = $db->query("SELECT * FROM classes WHERE is_active=1 ORDER BY class_numeric")->fetchAll();
$filterClass = (int)($_GET['class_id'] ?? 0);
$filterIds   = $_GET['ids'] ?? '';
$design      = $_GET['design'] ?? 'modern';
$type        = $_GET['type'] ?? 'student';
$printMode   = isset($_GET['print']);

$students = [];

if ($type === 'teacher') {
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

// ===== ডিজাইন সেটিংস =====
$idc = [
    // লোগো
    'logo'        => getSetting('id_card_logo_b64',''),
    'logo_sz'     => getSetting('id_card_logo_size','32'),
    // স্ট্রিপ
    'strip_svg'   => getSetting('id_card_strip_svg',''),
    'use_svg'     => getSetting('id_card_strip_use_custom_svg','0'),
    'sc1'         => getSetting('id_card_strip_color1','#1a8a3c'),
    'sc2'         => getSetting('id_card_strip_color2','#e67e22'),
    'strip_w'     => getSetting('id_card_strip_width','30'),
    // লেবেল
    'label_font'  => getSetting('id_card_label_font','Hind Siliguri'),
    'label_size'  => getSetting('id_card_label_size','9'),
    'label_w'     => getSetting('id_card_label_weight','700'),
    'label_style' => getSetting('id_card_label_style','normal'),
    'label_color' => getSetting('id_card_label_color','#ffffff'),
    'label_ls'    => getSetting('id_card_label_spacing','2'),
    // নাম
    'name_font'   => getSetting('id_card_name_font','Libre Baskerville'),
    'name_size'   => getSetting('id_card_name_size','14'),
    'name_w'      => getSetting('id_card_name_weight','700'),
    'name_color'  => getSetting('id_card_name_color','#1a8a3c'),
    'name_align'  => getSetting('id_card_name_align','center'),
    'name_mt'     => getSetting('id_card_name_mt','6'),
    // আইডি
    'id_font'     => getSetting('id_card_id_font','Hind Siliguri'),
    'id_size'     => getSetting('id_card_id_size','8.5'),
    'id_color'    => getSetting('id_card_id_color','#555555'),
    'id_align'    => getSetting('id_card_id_align','center'),
    // টেবিল
    'tb_font'     => getSetting('id_card_table_font','Hind Siliguri'),
    'tb_size'     => getSetting('id_card_table_size','8'),
    'tb_lc'       => getSetting('id_card_table_label_color','#1a5276'),
    'tb_vc'       => getSetting('id_card_table_val_color','#333333'),
    'tb_rh'       => getSetting('id_card_table_row_height','1.8'),
    'tb_lw'       => getSetting('id_card_table_label_width','38'),
    // হেডার
    'ar_font'     => getSetting('id_card_arabic_font','Hind Siliguri'),
    'ar_size'     => getSetting('id_card_arabic_size','7.5'),
    'ar_color'    => getSetting('id_card_arabic_color','#1a5276'),
    'bn_font'     => getSetting('id_card_bn_font','Hind Siliguri'),
    'bn_size'     => getSetting('id_card_bn_size','6.5'),
    'bn_color'    => getSetting('id_card_bn_color','#1a8a3c'),
    // কার্ড রং
    's_c1'        => getSetting('id_card_student_color1','#1a8a3c'),
    's_c2'        => getSetting('id_card_student_color2','#e67e22'),
    't_c1'        => getSetting('id_card_teacher_color1','#1a3a6b'),
    't_c2'        => getSetting('id_card_teacher_color2','#c9a227'),
    'sf_c1'       => getSetting('id_card_staff_color1','#5b2c8c'),
    'sf_c2'       => getSetting('id_card_staff_color2','#8e44ad'),
    // ছবি ও বর্ডার
    'photo_bc'    => getSetting('id_card_photo_border_color','#e67e22'),
    'photo_w'     => getSetting('id_card_photo_width','80'),
    'photo_h'     => getSetting('id_card_photo_height','95'),
    'radius'      => getSetting('id_card_border_radius','10'),
    // FRONT padding
    'f_pt'        => getSetting('id_card_front_pt','8'),
    'f_pb'        => getSetting('id_card_front_pb','8'),
    'f_pl'        => getSetting('id_card_front_pl','6'),
    'f_pr'        => getSetting('id_card_front_pr','8'),
    // BACK content
    'back_title'  => getSetting('id_card_back_title','Terms and Condition'),
    'bt_font'     => getSetting('id_card_back_title_font','Libre Baskerville'),
    'bt_size'     => getSetting('id_card_back_title_size','10'),
    'bt_color'    => getSetting('id_card_back_title_color','#1a5276'),
    'bt_align'    => getSetting('id_card_back_title_align','center'),
    'back_terms'  => getSetting('id_card_back_terms','This ID card must be brought and worn whenever the student attends the madrasah. If this card is lost, the student or guardian must inform the office immediately. If anyone finds this card, please return it to An Nazah Tahfizul Quran Madrasah. Misuse, lending, or altering this card in any way is strictly prohibited.'),
    'bx_font'     => getSetting('id_card_back_text_font','Hind Siliguri'),
    'bx_size'     => getSetting('id_card_back_text_size','6.5'),
    'bx_color'    => getSetting('id_card_back_text_color','#444444'),
    'bx_align'    => getSetting('id_card_back_text_align','justify'),
    'sig_label'   => getSetting('id_card_back_sig_label',"Principal's Signature"),
    'sig_font'    => getSetting('id_card_back_sig_font','Hind Siliguri'),
    'sig_size'    => getSetting('id_card_back_sig_size','6'),
    'sig_color'   => getSetting('id_card_back_sig_color','#555555'),
    'addr_font'   => getSetting('id_card_back_addr_font','Hind Siliguri'),
    'addr_size'   => getSetting('id_card_back_addr_size','6.5'),
    'addr_color'  => getSetting('id_card_back_addr_color','#444444'),
    'addr_align'  => getSetting('id_card_back_addr_align','center'),
    // BACK padding
    'b_pt'        => getSetting('id_card_back_pt','12'),
    'b_pb'        => getSetting('id_card_back_pb','8'),
    'b_pl'        => getSetting('id_card_back_pl','10'),
    'b_pr'        => getSetting('id_card_back_pr','10'),
];

if ($type === 'teacher')   { $idc['c1']=$idc['t_c1'];  $idc['c2']=$idc['t_c2'];  }
elseif ($type === 'staff') { $idc['c1']=$idc['sf_c1']; $idc['c2']=$idc['sf_c2']; }
else                       { $idc['c1']=$idc['s_c1'];  $idc['c2']=$idc['s_c2'];  }

require_once '../../includes/header.php';
?>

<?php if (!$printMode): ?>
<div class="section-header no-print">
    <h2 class="section-title"><i class="fas fa-id-card"></i> আইডি কার্ড জেনারেটর</h2>
    <div style="display:flex;gap:8px;">
        <a href="id_card_settings.php" class="btn btn-outline btn-sm"><i class="fas fa-palette"></i> ডিজাইন সেটিংস</a>
        <?php if (!empty($students)): ?>
        <button onclick="printCards()" class="btn btn-primary"><i class="fas fa-print"></i> প্রিন্ট / PDF ডাউনলোড</button>
        <?php endif; ?>
    </div>
</div>

<div class="card mb-16 no-print">
    <div class="card-body" style="padding:16px 20px;">
        <form method="GET" id="filterForm">
            <div style="display:flex;flex-wrap:wrap;gap:14px;align-items:flex-end;">
                <div class="form-group" style="margin:0;flex:1;min-width:160px;">
                    <label style="font-size:12px;">ধরন</label>
                    <select name="type" class="form-control" style="padding:8px;" onchange="onTypeChange(this)">
                        <option value="student" <?= $type==='student'?'selected':'' ?>>ছাত্র</option>
                        <option value="teacher" <?= $type==='teacher'?'selected':'' ?>>শিক্ষক</option>
                        <option value="staff"   <?= $type==='staff'  ?'selected':'' ?>>স্টাফ</option>
                    </select>
                </div>
                <div class="form-group" style="margin:0;flex:1;min-width:160px;" id="classDiv" <?= $type!=='student'?'style="display:none;"':'' ?>>
                    <label style="font-size:12px;">শ্রেণী</label>
                    <select name="class_id" class="form-control" style="padding:8px;" onchange="this.form.submit()">
                        <option value="">সব শ্রেণী</option>
                        <?php foreach($classes as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $filterClass==$c['id']?'selected':'' ?>><?= e($c['class_name_bn']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
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

<?php if (!empty($students) && (($type === 'student' && $filterClass) || $type !== 'student')): ?>
<div class="card mb-16 no-print">
    <div class="card-header">
        <span class="card-title">মোট <?= toBanglaNumber(count($students)) ?> জন <?= $type==='teacher' ? 'শিক্ষক' : ($type==='staff' ? 'স্টাফ' : 'ছাত্র') ?></span>
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
<?php endif; ?>

<!-- ===== আইডি কার্ড ===== -->
<?php if (!empty($students)): ?>
<div id="cardContainer" style="<?= $printMode ? '' : 'margin-top:24px;' ?>">
    <?php foreach($students as $s):
        $name    = $s['name_bn'] ?: $s['name'];
        $nameEn  = $s['name'] ?? '';
        $nameParts   = explode(' ', $nameEn, 2);
        $firstNameEn = $nameParts[0] ?? '';
        $lastNameEn  = $nameParts[1] ?? '';

        $rawPhoto = $s['photo'] ?? '';
        if ($rawPhoto && strpos($rawPhoto, 'http') === 0) {
            $photoUrl = $rawPhoto;
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

        // ইনলাইন স্টাইল helpers
        $frontBodyPad = "padding:{$idc['f_pt']}px {$idc['f_pr']}px {$idc['f_pb']}px {$idc['f_pl']}px;";
        $backBodyPad  = "padding:{$idc['b_pt']}px {$idc['b_pr']}px {$idc['b_pb']}px {$idc['b_pl']}px;";
        $rowStyle     = "font-family:'{$idc['tb_font']}',sans-serif;font-size:{$idc['tb_size']}px;line-height:{$idc['tb_rh']};";
        $lblStyle     = "color:{$idc['tb_lc']};width:{$idc['tb_lw']}px;";
        $valStyle     = "color:{$idc['tb_vc']};";
    ?>

    <div class="id-card-pair">

        <!-- ===== পেছনের দিক (Back) ===== -->
        <div class="id-card card-back" style="border-radius:<?= (int)$idc['radius'] ?>px;">
            <div class="back-watermark"><i class="fas fa-mosque"></i></div>
            <div class="back-content" style="<?= $backBodyPad ?>">

                <!-- শিরোনাম -->
                <h3 class="back-title" style="
                    font-family:'<?= e($idc['bt_font']) ?>',serif;
                    font-size:<?= e($idc['bt_size']) ?>px;
                    color:<?= e($idc['bt_color']) ?>;
                    text-align:<?= e($idc['bt_align']) ?>;">
                    <?= e($idc['back_title']) ?>
                </h3>

                <!-- Terms টেক্সট -->
                <p class="back-text" style="
                    font-family:'<?= e($idc['bx_font']) ?>',sans-serif;
                    font-size:<?= e($idc['bx_size']) ?>px;
                    color:<?= e($idc['bx_color']) ?>;
                    text-align:<?= e($idc['bx_align']) ?>;">
                    <?= e($idc['back_terms']) ?>
                </p>

                <div class="back-bottom">
                    <div class="back-qr">
                        <div class="qr-box">
                            <svg viewBox="0 0 100 100" width="60" height="60" xmlns="http://www.w3.org/2000/svg">
                                <rect x="5" y="5" width="35" height="35" rx="3" fill="none" stroke="<?= e($idc['c2']) ?>" stroke-width="4"/>
                                <rect x="12" y="12" width="21" height="21" rx="1" fill="<?= e($idc['c2']) ?>"/>
                                <rect x="60" y="5" width="35" height="35" rx="3" fill="none" stroke="<?= e($idc['c2']) ?>" stroke-width="4"/>
                                <rect x="67" y="12" width="21" height="21" rx="1" fill="<?= e($idc['c2']) ?>"/>
                                <rect x="5" y="60" width="35" height="35" rx="3" fill="none" stroke="<?= e($idc['c2']) ?>" stroke-width="4"/>
                                <rect x="12" y="67" width="21" height="21" rx="1" fill="<?= e($idc['c2']) ?>"/>
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
                        <!-- স্বাক্ষর -->
                        <div class="back-sig">
                            <div class="sig-line"></div>
                            <div class="sig-text" style="
                                font-family:'<?= e($idc['sig_font']) ?>',sans-serif;
                                font-size:<?= e($idc['sig_size']) ?>px;
                                color:<?= e($idc['sig_color']) ?>;">
                                <?= e($idc['sig_label']) ?>
                            </div>
                        </div>
                    </div>
                    <!-- ঠিকানা -->
                    <div class="back-address" style="
                        font-family:'<?= e($idc['addr_font']) ?>',sans-serif;
                        font-size:<?= e($idc['addr_size']) ?>px;
                        color:<?= e($idc['addr_color']) ?>;
                        text-align:<?= e($idc['addr_align']) ?>;">
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
            <div class="front-strip" style="width:<?= (int)$idc['strip_w'] ?>px;">
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

            <div class="front-body" style="<?= $frontBodyPad ?>">
                <!-- লোগো ও প্রতিষ্ঠানের নাম -->
                <div class="front-header" style="border-bottom-color:<?= e($idc['c1']) ?>;">
                    <?php
                    $logoSz = (int)($idc['logo_sz'] ?? 32);
                    $logoStyle = "width:{$logoSz}px;height:{$logoSz}px;";
                    if($idc['logo']): ?>
                    <img src="<?= $idc['logo'] ?>" class="front-logo" style="<?= $logoStyle ?>object-fit:contain;flex-shrink:0;" alt="logo">
                    <?php elseif($logoPath): ?>
                    <img src="<?= str_starts_with($logoPath,'http') ? e($logoPath) : BASE_URL.'/assets/uploads/'.e($logoPath) ?>" class="front-logo" style="<?= $logoStyle ?>object-fit:contain;flex-shrink:0;" alt="logo">
                    <?php else: ?>
                    <div class="front-logo-placeholder" style="<?= $logoStyle ?>background:linear-gradient(135deg,<?= e($idc['c1']) ?>,<?= e($idc['c2']) ?>);border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fas fa-mosque" style="color:#fff;font-size:<?= round($logoSz*0.4) ?>px;"></i></div>
                    <?php endif; ?>
                    <div class="front-institute">
                        <div class="front-institute-arabic" style="
                            font-family:'<?= e($idc['ar_font']) ?>',sans-serif;
                            font-size:<?= e($idc['ar_size']) ?>px;
                            color:<?= e($idc['ar_color']) ?>;">
                            مدرسة النجاح لتحفيظ القرآن
                        </div>
                        <div class="front-institute-bn" style="
                            font-family:'<?= e($idc['bn_font']) ?>',sans-serif;
                            font-size:<?= e($idc['bn_size']) ?>px;
                            color:<?= e($idc['bn_color']) ?>;">
                            <?= e($instituteName) ?>
                        </div>
                    </div>
                </div>

                <!-- ছাত্রের ছবি -->
                <div class="front-photo-wrap">
                    <?php
                    $photoW = (int)($idc['photo_w'] ?? 80);
                    $photoH = (int)($idc['photo_h'] ?? 95);
                    $photoStyle = "width:{$photoW}px;height:{$photoH}px;";
                    if($photoUrl): ?>
                    <img src="<?= e($photoUrl) ?>" class="front-photo" style="<?= $photoStyle ?>object-fit:cover;border:3px solid <?= e($idc['photo_bc']) ?>;border-radius:4px;display:inline-block;" alt="photo">
                    <?php else: ?>
                    <div class="front-photo-avatar" style="<?= $photoStyle ?>border:3px solid <?= e($idc['photo_bc']) ?>;border-radius:4px;display:inline-flex;align-items:center;justify-content:center;background:#f0f8f0;font-size:<?= round($photoH*0.33) ?>px;font-weight:700;color:<?= e($idc['c1']) ?>;"><?= mb_substr($name, 0, 1) ?></div>
                    <?php endif; ?>
                </div>

                <!-- নাম -->
                <div class="front-name" style="text-align:<?= e($idc['name_align']) ?>;margin-top:<?= (int)$idc['name_mt'] ?>px;line-height:1.2;">
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

                <!-- ID নম্বর -->
                <div class="front-id" style="
                    font-family:'<?= e($idc['id_font']) ?>',sans-serif;
                    font-size:<?= e($idc['id_size']) ?>px;
                    color:<?= e($idc['id_color']) ?>;
                    text-align:<?= e($idc['id_align']) ?>;">
                    ID: <?= e($stuId) ?>
                </div>

                <!-- তথ্য টেবিল -->
                <div class="front-table" style="border-top-color:<?= e($idc['c1']) ?>;">
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

.id-card-pair {
    display: inline-flex;
    gap: 12px;
    margin: 10px;
    vertical-align: top;
}
.id-card {
    width: 204px;
    height: 323px;
    overflow: hidden;
    box-shadow: 0 6px 24px rgba(0,0,0,.18);
    position: relative;
    font-family: 'Hind Siliguri', sans-serif;
}
/* FRONT */
.card-front {
    background: #fff;
    display: flex;
}
.front-strip {
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
    overflow: hidden;
}
.front-header {
    display: flex; align-items: center; gap: 5px;
    border-bottom: 2px solid #1a8a3c;
    padding-bottom: 5px; margin-bottom: 6px;
}
.front-institute { flex: 1; text-align: center; overflow: hidden; }
.front-institute-arabic {
    font-size: 7.5px; color: #1a5276; font-weight: 600;
    line-height: 1.3; direction: rtl; text-align: center;
}
.front-institute-bn {
    font-size: 6.5px; color: #1a8a3c; font-weight: 700;
    line-height: 1.3; text-align: center;
}
.front-photo-wrap { text-align: center; margin: 4px 0; }
.front-id { font-weight: 700; margin: 2px 0 5px; letter-spacing: 0.5px; }
.front-table { border-top: 1px dashed #1a8a3c; padding-top: 5px; }
.front-row { display: flex; }
.fr-label { font-weight: 600; flex-shrink: 0; }
.fr-val { flex: 1; }

/* BACK */
.card-back {
    background: #fff; border: 1px solid #ddd;
    display: flex; flex-direction: column;
    position: relative; overflow: hidden;
}
.back-watermark {
    position: absolute; top: 50%; left: 50%;
    transform: translate(-50%,-50%);
    font-size: 90px; color: rgba(26,138,60,.06); pointer-events: none;
}
.back-content {
    display: flex; flex-direction: column; height: 100%;
    position: relative; z-index: 1;
}
.back-title { font-weight: 700; margin-bottom: 7px; }
.back-text { line-height: 1.7; text-align: justify; flex: 1; }
.back-bottom { margin-top: 8px; border-top: 1px solid #e67e22; padding-top: 7px; display: flex; flex-direction: column; gap: 5px; }
.back-qr { display: flex; align-items: center; justify-content: space-between; }
.qr-box { background: #fff8f0; border: 1px solid #e67e22; border-radius: 4px; padding: 3px; }
.back-sig { text-align: center; }
.sig-line { width: 60px; border-top: 1px solid #333; margin: 0 auto 2px; }
.back-address { line-height: 1.6; }

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
    if (classDiv) classDiv.style.display = sel.value === 'student' ? '' : 'none';
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
    params.delete('class_id');
    window.location.href = '?' + params.toString();
}
function printCards() {
    window.print();
}
</script>

<?php require_once '../../includes/footer.php'; ?>
