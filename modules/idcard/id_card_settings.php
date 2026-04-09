<?php
/**
 * আইডি কার্ড ডিজাইন সেটিংস — উন্নত সংস্করণ
 * ফাইল: modules/idcard/id_card_settings.php
 */
require_once '../../includes/functions.php';
requireLogin(['super_admin','principal']);
$pageTitle = 'আইডি কার্ড ডিজাইন সেটিংস';
$db = getDB();

function idcs($key, $default = '') {
    global $db;
    try {
        $r = $db->prepare("SELECT setting_value FROM settings WHERE setting_key=? LIMIT 1");
        $r->execute([$key]);
        $v = $r->fetchColumn();
        return $v !== false ? $v : $default;
    } catch(Exception $e) { return $default; }
}
function saveIdcs($key, $value) {
    global $db;
    try {
        $c = $db->prepare("SELECT COUNT(*) FROM settings WHERE setting_key=?");
        $c->execute([$key]);
        if ($c->fetchColumn()) {
            $db->prepare("UPDATE settings SET setting_value=? WHERE setting_key=?")->execute([$value, $key]);
        } else {
            $db->prepare("INSERT INTO settings(setting_key, setting_value) VALUES(?,?)")->execute([$key, $value]);
        }
        return true;
    } catch(Exception $e) { return false; }
}

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // লোগো সরানো
    if (!empty($_POST['remove_logo']) && $_POST['remove_logo'] === '1') {
        saveIdcs('id_card_logo_b64', '');
    }

    // লোগো আপলোড — BUG FIX: remove_logo check সঠিক করা হয়েছে
    if (empty($_POST['remove_logo']) || $_POST['remove_logo'] !== '1') {
        if (!empty($_FILES['logo_svg']['tmp_name']) && $_FILES['logo_svg']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['logo_svg']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['svg','png','jpg','jpeg','webp'])) {
                $content = file_get_contents($_FILES['logo_svg']['tmp_name']);
                if ($content !== false) {
                    $mime = mime_content_type($_FILES['logo_svg']['tmp_name']);
                    $b64  = 'data:' . $mime . ';base64,' . base64_encode($content);
                    saveIdcs('id_card_logo_b64', $b64);
                }
            }
        }
    }

    // স্ট্রিপ SVG আপলোড
    if (!empty($_FILES['strip_svg']['tmp_name']) && $_FILES['strip_svg']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['strip_svg']['name'], PATHINFO_EXTENSION));
        if ($ext === 'svg') {
            $svgContent = file_get_contents($_FILES['strip_svg']['tmp_name']);
            if ($svgContent !== false) {
                saveIdcs('id_card_strip_svg', $svgContent);
            }
        }
    }

    // প্রতিষ্ঠানের নাম ইমেজ আপলোড
    if (!empty($_POST['remove_inst_img']) && $_POST['remove_inst_img'] === '1') {
        saveIdcs('id_card_inst_name_img_b64', '');
    } elseif (!empty($_FILES['inst_name_img']['tmp_name']) && $_FILES['inst_name_img']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['inst_name_img']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['png','jpg','jpeg','webp','svg'])) {
            $content = file_get_contents($_FILES['inst_name_img']['tmp_name']);
            if ($content !== false) {
                $mime = mime_content_type($_FILES['inst_name_img']['tmp_name']);
                $b64  = 'data:' . $mime . ';base64,' . base64_encode($content);
                saveIdcs('id_card_inst_name_img_b64', $b64);
            }
        }
    }

    $fields = [
        // স্ট্রিপ
        'id_card_strip_color1','id_card_strip_color2','id_card_strip_use_custom_svg',
        // লেবেল ফন্ট
        'id_card_label_font','id_card_label_size','id_card_label_weight','id_card_label_style','id_card_label_color','id_card_label_spacing',
        // নাম ফন্ট
        'id_card_name_font','id_card_name_size','id_card_name_weight','id_card_name_color',
        'id_card_name_align','id_card_name_mt',
        // আইডি
        'id_card_id_font','id_card_id_size','id_card_id_color','id_card_id_align',
        // টেবিল
        'id_card_table_font','id_card_table_size','id_card_table_label_color','id_card_table_val_color',
        'id_card_table_row_height','id_card_table_label_width',
        // হেডার
        'id_card_arabic_font','id_card_arabic_size','id_card_arabic_color',
        'id_card_bn_font','id_card_bn_size','id_card_bn_color',
        // হেডার mode + inst name image height
        'id_card_header_mode','id_card_inst_name_img_height',
        // কার্ড রং
        'id_card_student_color1','id_card_student_color2',
        'id_card_teacher_color1','id_card_teacher_color2',
        'id_card_staff_color1','id_card_staff_color2',
        // ছবি ও বর্ডার
        'id_card_border_radius','id_card_photo_border_color',
        'id_card_photo_width','id_card_photo_height',
        // FRONT padding
        'id_card_front_pt','id_card_front_pb','id_card_front_pl','id_card_front_pr',
        // BACK customize
        'id_card_back_title','id_card_back_title_font','id_card_back_title_size','id_card_back_title_color','id_card_back_title_align',
        'id_card_back_terms','id_card_back_text_font','id_card_back_text_size','id_card_back_text_color','id_card_back_text_align',
        'id_card_back_sig_label','id_card_back_sig_font','id_card_back_sig_size','id_card_back_sig_color',
        'id_card_back_addr_font','id_card_back_addr_size','id_card_back_addr_color','id_card_back_addr_align',
        // BACK padding
        'id_card_back_pt','id_card_back_pb','id_card_back_pl','id_card_back_pr',
        // strip width
        'id_card_strip_width',
        // logo size
        'id_card_logo_size',
    ];
    foreach ($fields as $f) {
        if (isset($_POST[$f])) {
            saveIdcs($f, trim($_POST[$f]));
        }
    }
    header('Location: id_card_settings.php?saved=1');
    exit;
}

$savedMsg = !empty($_GET['saved']) ? 'সেটিংস সফলভাবে সেভ হয়েছে!' : '';

$cfg = [
    'logo_b64'              => idcs('id_card_logo_b64',''),
    'strip_svg'             => idcs('id_card_strip_svg',''),
    'strip_use_custom'      => idcs('id_card_strip_use_custom_svg','0'),
    'strip_color1'          => idcs('id_card_strip_color1','#1a8a3c'),
    'strip_color2'          => idcs('id_card_strip_color2','#e67e22'),
    'strip_width'           => idcs('id_card_strip_width','30'),
    'label_font'            => idcs('id_card_label_font','Hind Siliguri'),
    'label_size'            => idcs('id_card_label_size','9'),
    'label_weight'          => idcs('id_card_label_weight','700'),
    'label_style'           => idcs('id_card_label_style','normal'),
    'label_color'           => idcs('id_card_label_color','#ffffff'),
    'label_spacing'         => idcs('id_card_label_spacing','2'),
    'name_font'             => idcs('id_card_name_font','Libre Baskerville'),
    'name_size'             => idcs('id_card_name_size','14'),
    'name_weight'           => idcs('id_card_name_weight','700'),
    'name_color'            => idcs('id_card_name_color','#1a8a3c'),
    'name_align'            => idcs('id_card_name_align','center'),
    'name_mt'               => idcs('id_card_name_mt','6'),
    'id_font'               => idcs('id_card_id_font','Hind Siliguri'),
    'id_size'               => idcs('id_card_id_size','8.5'),
    'id_color'              => idcs('id_card_id_color','#555555'),
    'id_align'              => idcs('id_card_id_align','center'),
    'table_font'            => idcs('id_card_table_font','Hind Siliguri'),
    'table_size'            => idcs('id_card_table_size','8'),
    'table_label_color'     => idcs('id_card_table_label_color','#1a5276'),
    'table_val_color'       => idcs('id_card_table_val_color','#333333'),
    'table_row_height'      => idcs('id_card_table_row_height','1.8'),
    'table_label_width'     => idcs('id_card_table_label_width','38'),
    'arabic_font'           => idcs('id_card_arabic_font','Hind Siliguri'),
    'arabic_size'           => idcs('id_card_arabic_size','7.5'),
    'arabic_color'          => idcs('id_card_arabic_color','#1a5276'),
    'bn_font'               => idcs('id_card_bn_font','Hind Siliguri'),
    'bn_size'               => idcs('id_card_bn_size','6.5'),
    'bn_color'              => idcs('id_card_bn_color','#1a8a3c'),
    'student_color1'        => idcs('id_card_student_color1','#1a8a3c'),
    'student_color2'        => idcs('id_card_student_color2','#e67e22'),
    'teacher_color1'        => idcs('id_card_teacher_color1','#1a3a6b'),
    'teacher_color2'        => idcs('id_card_teacher_color2','#c9a227'),
    'staff_color1'          => idcs('id_card_staff_color1','#5b2c8c'),
    'staff_color2'          => idcs('id_card_staff_color2','#8e44ad'),
    'photo_border_color'    => idcs('id_card_photo_border_color','#e67e22'),
    'photo_width'           => idcs('id_card_photo_width','80'),
    'photo_height'          => idcs('id_card_photo_height','95'),
    'border_radius'         => idcs('id_card_border_radius','10'),
    // FRONT padding
    'front_pt'              => idcs('id_card_front_pt','8'),
    'front_pb'              => idcs('id_card_front_pb','8'),
    'front_pl'              => idcs('id_card_front_pl','6'),
    'front_pr'              => idcs('id_card_front_pr','8'),
    // BACK
    'back_title'            => idcs('id_card_back_title','Terms and Condition'),
    'back_title_font'       => idcs('id_card_back_title_font','Libre Baskerville'),
    'back_title_size'       => idcs('id_card_back_title_size','10'),
    'back_title_color'      => idcs('id_card_back_title_color','#1a5276'),
    'back_title_align'      => idcs('id_card_back_title_align','center'),
    'back_terms'            => idcs('id_card_back_terms','This ID card must be brought and worn whenever the student attends the madrasah. If this card is lost, the student or guardian must inform the office immediately. If anyone finds this card, please return it to An Nazah Tahfizul Quran Madrasah. Misuse, lending, or altering this card in any way is strictly prohibited.'),
    'back_text_font'        => idcs('id_card_back_text_font','Hind Siliguri'),
    'back_text_size'        => idcs('id_card_back_text_size','6.5'),
    'back_text_color'       => idcs('id_card_back_text_color','#444444'),
    'back_text_align'       => idcs('id_card_back_text_align','justify'),
    'back_sig_label'        => idcs('id_card_back_sig_label','Principal\'s Signature'),
    'back_sig_font'         => idcs('id_card_back_sig_font','Hind Siliguri'),
    'back_sig_size'         => idcs('id_card_back_sig_size','6'),
    'back_sig_color'        => idcs('id_card_back_sig_color','#555555'),
    'back_addr_font'        => idcs('id_card_back_addr_font','Hind Siliguri'),
    'back_addr_size'        => idcs('id_card_back_addr_size','6.5'),
    'back_addr_color'       => idcs('id_card_back_addr_color','#444444'),
    'back_addr_align'       => idcs('id_card_back_addr_align','center'),
    // BACK padding
    'back_pt'               => idcs('id_card_back_pt','12'),
    'back_pb'               => idcs('id_card_back_pb','8'),
    'back_pl'               => idcs('id_card_back_pl','10'),
    'back_pr'               => idcs('id_card_back_pr','10'),
    // logo size
    'logo_size'             => idcs('id_card_logo_size','32'),
    // হেডার mode
    'header_mode'           => idcs('id_card_header_mode','text'),
    'inst_name_img_b64'     => idcs('id_card_inst_name_img_b64',''),
    'inst_name_img_height'  => idcs('id_card_inst_name_img_height','32'),
];

$googleFonts = [
    'Hind Siliguri'       => 'Hind Siliguri (বাংলা)',
    'Libre Baskerville'   => 'Libre Baskerville (সেরিফ)',
    'Roboto'              => 'Roboto',
    'Open Sans'           => 'Open Sans',
    'Montserrat'          => 'Montserrat',
    'Poppins'             => 'Poppins',
    'Playfair Display'    => 'Playfair Display',
    'Raleway'             => 'Raleway',
    'Oswald'              => 'Oswald',
    'Lato'                => 'Lato',
    'Noto Serif Bengali'  => 'Noto Serif Bengali (বাংলা সেরিফ)',
    'Tiro Bangla'         => 'Tiro Bangla',
    'Baloo Da 2'          => 'Baloo Da 2 (বাংলা)',
];

require_once '../../includes/header.php';
?>

<link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;600;700&family=Libre+Baskerville:ital,wght@0,400;0,700;1,400&family=Roboto:wght@400;700&family=Open+Sans:wght@400;700&family=Montserrat:wght@400;700&family=Poppins:wght@400;700&family=Playfair+Display:wght@400;700&family=Raleway:wght@400;700&family=Oswald:wght@400;700&family=Lato:wght@400;700&family=Noto+Serif+Bengali:wght@400;700&family=Tiro+Bangla&family=Baloo+Da+2:wght@400;700&display=swap" rel="stylesheet">

<style>
.settings-grid { display: grid; grid-template-columns: 1fr 430px; gap: 24px; align-items: start; }
@media(max-width:1100px){ .settings-grid { grid-template-columns: 1fr; } }
.settings-panel { display: flex; flex-direction: column; gap: 16px; }
.preview-panel { position: sticky; top: 80px; }
.tab-row { display: flex; gap: 4px; flex-wrap: wrap; margin-bottom: 16px; }
.tab-btn { padding: 7px 14px; border-radius: 7px; border: 1.5px solid var(--border);
    background: #fff; cursor: pointer; font-size: 12px; font-weight: 600; color: var(--text-muted);
    font-family: var(--font); transition: all .2s; }
.tab-btn.active { background: var(--primary); color: #fff; border-color: var(--primary); }
.tab-pane { display: none; }
.tab-pane.active { display: block; }
.field-row { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 12px; }
.field-row .form-group { flex: 1; min-width: 100px; margin: 0; }
.upload-area {
    border: 2px dashed var(--border); border-radius: 10px; padding: 18px;
    text-align: center; cursor: pointer; transition: all .2s; background: var(--bg);
}
.upload-area:hover { border-color: var(--primary); background: #ebf5fb; }
.upload-area input[type=file] { display: none; }
.upload-preview { max-width: 80px; max-height: 60px; margin: 8px auto 0; display: block; }
.section-label { font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; margin: 12px 0 6px; padding-bottom: 4px; border-bottom: 1px solid var(--border); }
.px-row { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
.px-row .form-group { flex: 1; min-width: 60px; margin: 0; }
.px-row label { font-size: 11px; }

/* ===== লাইভ প্রিভিউ ===== */
.preview-wrap {
    display: flex; gap: 10px; justify-content: center;
    overflow-x: auto; padding: 12px 0;
}
.pv-card {
    width: 204px; height: 323px;
    overflow: hidden; box-shadow: 0 6px 24px rgba(0,0,0,.2);
    position: relative; font-family: 'Hind Siliguri', sans-serif;
    flex-shrink: 0;
}
.pv-front { background: #fff; display: flex; }
.pv-strip {
    position: relative; flex-shrink: 0;
    overflow: hidden; display: flex; align-items: center; justify-content: center;
}
.pv-strip-svg-wrap { position: absolute; inset: 0; overflow: hidden; }
.pv-strip-svg-wrap svg { width: 100%; height: 100%; }
.pv-strip-top {
    position: absolute; top: 0; left: 0; right: 0; height: 55%;
    clip-path: polygon(0 0,100% 0,100% 85%,0 100%);
}
.pv-strip-bot {
    position: absolute; bottom: 0; left: 0; right: 0; height: 55%;
    clip-path: polygon(0 15%,100% 0,100% 100%,0 100%);
}
.pv-strip-label {
    position: relative; z-index: 2; color: #fff;
    font-weight: 700; letter-spacing: 2px;
    writing-mode: vertical-rl; text-orientation: mixed;
    transform: rotate(180deg); white-space: nowrap;
    text-shadow: 0 1px 3px rgba(0,0,0,.5);
}
.pv-body { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
.pv-header { display: flex; align-items: center; justify-content: center; flex-direction: column; gap: 3px; padding-bottom: 5px; margin-bottom: 6px; border-bottom: 2px solid #1a8a3c; }
.pv-logo { object-fit: contain; flex-shrink: 0; }
.pv-logo-placeholder {
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    color: #fff; flex-shrink: 0; font-weight: 700;
}
.pv-inst { flex: 1; text-align: center; overflow: hidden; }
.pv-arabic { font-size: 7.5px; font-weight: 600; line-height: 1.3; direction: rtl; text-align: center; }
.pv-bn { font-size: 6.5px; font-weight: 700; line-height: 1.3; text-align: center; }
.pv-photo-wrap { text-align: center; margin: 4px 0; }
.pv-photo-box {
    border: 3px solid #e67e22; border-radius: 4px; display: inline-flex;
    align-items: center; justify-content: center;
    background: #f0f8f0; font-size: 28px; font-weight: 700; color: #1a8a3c;
}
.pv-name { line-height: 1.2; }
.pv-name-first { font-size: 14px; font-weight: 700; font-family: 'Libre Baskerville', serif; }
.pv-name-last  { font-size: 14px; font-weight: 400; font-family: 'Libre Baskerville', serif; color: #333; }
.pv-id { font-size: 8.5px; font-weight: 700; color: #555; margin: 2px 0 5px; letter-spacing: 0.5px; }
.pv-table { padding-top: 5px; }
.pv-row { display: flex; font-size: 8px; }
.pv-label { font-weight: 600; }
.pv-val { flex: 1; }
.pv-back { background: #fff; border: 1px solid #ddd; position: relative; overflow: hidden;
    display: flex; flex-direction: column; }
.pv-back-wm { position: absolute; top:50%;left:50%;transform:translate(-50%,-50%);
    font-size:90px;color:rgba(26,138,60,.06);pointer-events:none; }
.pv-back-inner { display: flex; flex-direction: column; height: 100%; position: relative; z-index:1; }
.pv-back-title { font-weight:700; margin-bottom:7px; }
.pv-back-text { line-height:1.7; flex:1; }
.pv-back-bottom { border-top:1px solid #e67e22; padding-top:7px; display:flex; flex-direction:column; gap:5px; margin-top: 8px; }
.pv-qr-row { display:flex;align-items:center;justify-content:space-between; }
.pv-sig { text-align:center; }
.pv-sig-line { width:60px;border-top:1px solid #333;margin:0 auto 2px; }
.pv-addr { line-height:1.6; }
</style>

<?php if ($savedMsg): ?>
<div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= e($savedMsg) ?></div>
<?php endif; ?>

<div class="section-header">
    <h2 class="section-title"><i class="fas fa-palette"></i> আইডি কার্ড ডিজাইন সেটিংস</h2>
    <a href="id_card.php" class="btn btn-outline"><i class="fas fa-eye"></i> কার্ড দেখুন</a>
</div>

<form method="POST" enctype="multipart/form-data" id="settingsForm">
<div class="settings-grid">

    <!-- ===== বাম: সেটিংস প্যানেল ===== -->
    <div class="settings-panel">

        <div class="tab-row">
            <button type="button" class="tab-btn active" onclick="switchTab('logo')"><i class="fas fa-image"></i> লোগো</button>
            <button type="button" class="tab-btn" onclick="switchTab('strip')"><i class="fas fa-grip-lines-vertical"></i> সাইড স্ট্রিপ</button>
            <button type="button" class="tab-btn" onclick="switchTab('front')"><i class="fas fa-id-card"></i> সামনের দিক</button>
            <button type="button" class="tab-btn" onclick="switchTab('back')"><i class="fas fa-id-card-alt"></i> পেছনের দিক</button>
            <button type="button" class="tab-btn" onclick="switchTab('colors')"><i class="fas fa-fill-drip"></i> কার্ড রং</button>
        </div>

        <!-- ===== ট্যাব: লোগো ===== -->
        <div class="tab-pane active" id="tab-logo">
            <div class="card">
                <div class="card-header"><span class="card-title"><i class="fas fa-image"></i> লোগো / হেডার ইমেজ</span></div>
                <div class="card-body">
                    <p style="font-size:13px;color:var(--text-muted);margin-bottom:12px;">SVG, PNG, JPG, WebP সাপোর্টেড।</p>
                    <div class="upload-area" onclick="document.getElementById('logo_svg').click()">
                        <i class="fas fa-cloud-upload-alt" style="font-size:28px;color:var(--primary-light);margin-bottom:8px;display:block;"></i>
                        <div style="font-size:13px;font-weight:600;">লোগো আপলোড করুন</div>
                        <div style="font-size:11px;color:var(--text-muted);">SVG / PNG / JPG / WebP</div>
                        <input type="file" id="logo_svg" name="logo_svg" accept=".svg,.png,.jpg,.jpeg,.webp" onchange="previewLogo(this)">
                        <?php if($cfg['logo_b64']): ?>
                        <img src="<?= $cfg['logo_b64'] ?>" class="upload-preview" id="logoUploadPreview" alt="current logo">
                        <?php else: ?>
                        <img src="" class="upload-preview" id="logoUploadPreview" alt="" style="display:none;">
                        <?php endif; ?>
                    </div>
                    <?php if($cfg['logo_b64']): ?>
                    <div style="margin-top:8px;text-align:center;">
                        <input type="hidden" name="remove_logo" id="removeLogo" value="0">
                        <button type="button" class="btn btn-outline btn-sm" onclick="removeLogo()"><i class="fas fa-trash"></i> লোগো সরান</button>
                    </div>
                    <?php else: ?>
                    <input type="hidden" name="remove_logo" id="removeLogo" value="0">
                    <?php endif; ?>

                    <div class="section-label" style="margin-top:16px;">লোগো সাইজ</div>
                    <div class="field-row">
                        <div class="form-group">
                            <label>লোগো বক্স সাইজ (px)</label>
                            <input type="number" name="id_card_logo_size" class="form-control" value="<?= e($cfg['logo_size']) ?>" min="16" max="60" oninput="updatePreview()">
                        </div>
                    </div>
                </div>
            </div>

            <!-- প্রতিষ্ঠানের নাম: image বা text -->
            <div class="card">
                <div class="card-header"><span class="card-title"><i class="fas fa-building"></i> প্রতিষ্ঠানের নাম (হেডার)</span></div>
                <div class="card-body">
                    <div class="section-label">নাম দেখানোর পদ্ধতি</div>
                    <div class="field-row" style="margin-bottom:14px;">
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:14px;font-weight:600;">
                            <input type="radio" name="id_card_header_mode" value="text"
                                <?= $cfg['header_mode'] !== 'image' ? 'checked' : '' ?>
                                onchange="toggleHeaderMode()">
                            <span>📝 টেক্সট (Arabic + Bangla)</span>
                        </label>
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:14px;font-weight:600;">
                            <input type="radio" name="id_card_header_mode" value="image"
                                <?= $cfg['header_mode'] === 'image' ? 'checked' : '' ?>
                                onchange="toggleHeaderMode()">
                            <span>🖼️ ইমেজ (PNG/SVG আপলোড)</span>
                        </label>
                    </div>

                    <!-- image mode -->
                    <div id="instImgMode" <?= $cfg['header_mode'] !== 'image' ? 'style="display:none;"' : '' ?>>
                        <p style="font-size:12px;color:var(--text-muted);margin-bottom:10px;">
                            মাদরাসার নামের ইমেজ আপলোড করুন। PNG transparent বা SVG ব্যবহার করলে সবচেয়ে ভালো দেখাবে।
                        </p>
                        <div class="upload-area" onclick="document.getElementById('inst_name_img').click()">
                            <i class="fas fa-file-image" style="font-size:28px;color:var(--primary-light);margin-bottom:8px;display:block;"></i>
                            <div style="font-size:13px;font-weight:600;">নামের ইমেজ আপলোড</div>
                            <div style="font-size:11px;color:var(--text-muted);">PNG / SVG / JPG (transparent background recommended)</div>
                            <input type="file" id="inst_name_img" name="inst_name_img" accept=".svg,.png,.jpg,.jpeg,.webp" onchange="previewInstImg(this)">
                            <?php if($cfg['inst_name_img_b64']): ?>
                            <img src="<?= $cfg['inst_name_img_b64'] ?>" id="instImgPreview" style="max-width:100%;max-height:50px;margin:8px auto 0;display:block;" alt="inst name">
                            <?php else: ?>
                            <img src="" id="instImgPreview" style="max-width:100%;max-height:50px;margin:8px auto 0;display:none;" alt="">
                            <?php endif; ?>
                        </div>
                        <?php if($cfg['inst_name_img_b64']): ?>
                        <div style="margin-top:8px;text-align:center;">
                            <input type="hidden" name="remove_inst_img" id="removeInstImg" value="0">
                            <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('removeInstImg').value='1';this.textContent='সরানো হবে ✓';"><i class="fas fa-trash"></i> ইমেজ সরান</button>
                        </div>
                        <?php else: ?>
                        <input type="hidden" name="remove_inst_img" id="removeInstImg" value="0">
                        <?php endif; ?>

                        <div class="section-label" style="margin-top:12px;">ইমেজ উচ্চতা</div>
                        <div class="field-row">
                            <div class="form-group">
                                <label>Height (px) — card এ কতটুকু জায়গা নেবে</label>
                                <input type="number" name="id_card_inst_name_img_height" class="form-control"
                                    value="<?= e($cfg['inst_name_img_height']) ?>" min="15" max="55" oninput="updatePreview()">
                            </div>
                        </div>
                    </div>

                    <!-- text mode hint -->
                    <div id="instTextMode" <?= $cfg['header_mode'] === 'image' ? 'style="display:none;"' : '' ?>>
                        <p style="font-size:12px;color:var(--text-muted);">
                            নিচের "হেডার — আরবি লেখা" ও "হেডার — বাংলা নাম" সেকশন থেকে ফন্ট ও সাইজ পরিবর্তন করুন।
                        </p>
                    </div>
                </div>
            </div>
                <div class="card-body">
                    <div class="field-row">
                        <div class="form-group">
                            <label>ফন্ট</label>
                            <select name="id_card_arabic_font" class="form-control" onchange="updatePreview()">
                                <?php foreach($googleFonts as $fv => $fl): ?>
                                <option value="<?= $fv ?>" <?= $cfg['arabic_font']===$fv?'selected':'' ?>><?= $fl ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>সাইজ (px)</label>
                            <input type="number" name="id_card_arabic_size" class="form-control" value="<?= e($cfg['arabic_size']) ?>" min="5" max="20" step="0.5" oninput="updatePreview()">
                        </div>
                        <div class="form-group">
                            <label>রং</label>
                            <input type="color" name="id_card_arabic_color" class="form-control" value="<?= e($cfg['arabic_color']) ?>" oninput="updatePreview()">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><span class="card-title"><i class="fas fa-align-center"></i> হেডার — বাংলা নাম</span></div>
                <div class="card-body">
                    <div class="field-row">
                        <div class="form-group">
                            <label>ফন্ট</label>
                            <select name="id_card_bn_font" class="form-control" onchange="updatePreview()">
                                <?php foreach($googleFonts as $fv => $fl): ?>
                                <option value="<?= $fv ?>" <?= $cfg['bn_font']===$fv?'selected':'' ?>><?= $fl ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>সাইজ (px)</label>
                            <input type="number" name="id_card_bn_size" class="form-control" value="<?= e($cfg['bn_size']) ?>" min="5" max="20" step="0.5" oninput="updatePreview()">
                        </div>
                        <div class="form-group">
                            <label>রং</label>
                            <input type="color" name="id_card_bn_color" class="form-control" value="<?= e($cfg['bn_color']) ?>" oninput="updatePreview()">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== ট্যাব: সাইড স্ট্রিপ ===== -->
        <div class="tab-pane" id="tab-strip">
            <div class="card">
                <div class="card-header"><span class="card-title"><i class="fas fa-grip-lines-vertical"></i> সাইড স্ট্রিপ</span></div>
                <div class="card-body">
                    <div class="field-row">
                        <div class="form-group">
                            <label>স্ট্রিপ প্রস্থ (px)</label>
                            <input type="number" name="id_card_strip_width" class="form-control" value="<?= e($cfg['strip_width']) ?>" min="15" max="60" oninput="updatePreview()">
                        </div>
                    </div>
                    <div class="field-row" style="margin-bottom:16px;">
                        <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-size:14px;">
                            <input type="checkbox" name="id_card_strip_use_custom_svg" id="useCustomSvg" value="1"
                                <?= $cfg['strip_use_custom']==='1'?'checked':'' ?> onchange="toggleStripMode()">
                            <span>কাস্টম SVG শেপ ব্যবহার করব</span>
                        </label>
                    </div>
                    <div id="stripColorMode" <?= $cfg['strip_use_custom']==='1'?'style="display:none"':'' ?>>
                        <div class="field-row">
                            <div class="form-group">
                                <label>উপরের রং (Color 1)</label>
                                <input type="color" name="id_card_strip_color1" class="form-control" value="<?= e($cfg['strip_color1']) ?>" oninput="updatePreview()">
                            </div>
                            <div class="form-group">
                                <label>নিচের রং (Color 2)</label>
                                <input type="color" name="id_card_strip_color2" class="form-control" value="<?= e($cfg['strip_color2']) ?>" oninput="updatePreview()">
                            </div>
                        </div>
                    </div>
                    <div id="stripSvgMode" <?= $cfg['strip_use_custom']!=='1'?'style="display:none"':'' ?>>
                        <div class="upload-area" onclick="document.getElementById('strip_svg').click()">
                            <i class="fas fa-bezier-curve" style="font-size:28px;color:var(--primary-light);margin-bottom:8px;display:block;"></i>
                            <div style="font-size:13px;font-weight:600;">স্ট্রিপ SVG আপলোড</div>
                            <div style="font-size:11px;color:var(--text-muted);">শুধুমাত্র .svg ফাইল</div>
                            <input type="file" id="strip_svg" name="strip_svg" accept=".svg" onchange="previewStripSvg(this)">
                        </div>
                        <?php if($cfg['strip_svg']): ?>
                        <div style="margin-top:8px;background:#f7f9fc;border:1px solid var(--border);border-radius:6px;padding:8px;font-size:11px;color:var(--text-muted);">
                            <i class="fas fa-check-circle" style="color:var(--success);"></i> কাস্টম SVG লোড আছে
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><span class="card-title"><i class="fas fa-text-height"></i> স্ট্রিপ লেবেল ফন্ট ("STUDENT ID CARD")</span></div>
                <div class="card-body">
                    <div class="field-row">
                        <div class="form-group">
                            <label>ফন্ট</label>
                            <select name="id_card_label_font" class="form-control" onchange="updatePreview()">
                                <?php foreach($googleFonts as $fv => $fl): ?>
                                <option value="<?= $fv ?>" <?= $cfg['label_font']===$fv?'selected':'' ?>><?= $fl ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>সাইজ (px)</label>
                            <input type="number" name="id_card_label_size" class="form-control" value="<?= e($cfg['label_size']) ?>" min="5" max="18" step="0.5" oninput="updatePreview()">
                        </div>
                    </div>
                    <div class="field-row">
                        <div class="form-group">
                            <label>ওজন</label>
                            <select name="id_card_label_weight" class="form-control" onchange="updatePreview()">
                                <option value="400" <?= $cfg['label_weight']==='400'?'selected':'' ?>>Normal</option>
                                <option value="600" <?= $cfg['label_weight']==='600'?'selected':'' ?>>Semi Bold</option>
                                <option value="700" <?= $cfg['label_weight']==='700'?'selected':'' ?>>Bold</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>স্টাইল</label>
                            <select name="id_card_label_style" class="form-control" onchange="updatePreview()">
                                <option value="normal" <?= $cfg['label_style']==='normal'?'selected':'' ?>>Normal</option>
                                <option value="italic" <?= $cfg['label_style']==='italic'?'selected':'' ?>>Italic</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>রং</label>
                            <input type="color" name="id_card_label_color" class="form-control" value="<?= e($cfg['label_color']) ?>" oninput="updatePreview()">
                        </div>
                        <div class="form-group">
                            <label>Letter Spacing (px)</label>
                            <input type="number" name="id_card_label_spacing" class="form-control" value="<?= e($cfg['label_spacing']) ?>" min="0" max="10" step="0.5" oninput="updatePreview()">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== ট্যাব: সামনের দিক (FRONT) ===== -->
        <div class="tab-pane" id="tab-front">

            <!-- FRONT padding -->
            <div class="card">
                <div class="card-header"><span class="card-title"><i class="fas fa-expand-arrows-alt"></i> সামনের কার্ড — প্যাডিং (px)</span></div>
                <div class="card-body">
                    <p style="font-size:12px;color:var(--text-muted);margin-bottom:10px;">কার্ডের ভেতরের ফাঁকা জায়গা px হিসেবে নিয়ন্ত্রণ করুন।</p>
                    <div class="px-row">
                        <div class="form-group">
                            <label>উপরে (Top)</label>
                            <input type="number" name="id_card_front_pt" class="form-control" value="<?= e($cfg['front_pt']) ?>" min="0" max="40" oninput="updatePreview()">
                        </div>
                        <div class="form-group">
                            <label>নিচে (Bottom)</label>
                            <input type="number" name="id_card_front_pb" class="form-control" value="<?= e($cfg['front_pb']) ?>" min="0" max="40" oninput="updatePreview()">
                        </div>
                        <div class="form-group">
                            <label>বাম (Left)</label>
                            <input type="number" name="id_card_front_pl" class="form-control" value="<?= e($cfg['front_pl']) ?>" min="0" max="40" oninput="updatePreview()">
                        </div>
                        <div class="form-group">
                            <label>ডান (Right)</label>
                            <input type="number" name="id_card_front_pr" class="form-control" value="<?= e($cfg['front_pr']) ?>" min="0" max="40" oninput="updatePreview()">
                        </div>
                    </div>
                </div>
            </div>

            <!-- ছবি -->
            <div class="card">
                <div class="card-header"><span class="card-title"><i class="fas fa-portrait"></i> ছাত্রের ছবি</span></div>
                <div class="card-body">
                    <div class="field-row">
                        <div class="form-group">
                            <label>প্রস্থ (px)</label>
                            <input type="number" name="id_card_photo_width" class="form-control" value="<?= e($cfg['photo_width']) ?>" min="40" max="150" oninput="updatePreview()">
                        </div>
                        <div class="form-group">
                            <label>উচ্চতা (px)</label>
                            <input type="number" name="id_card_photo_height" class="form-control" value="<?= e($cfg['photo_height']) ?>" min="40" max="180" oninput="updatePreview()">
                        </div>
                        <div class="form-group">
                            <label>বর্ডার রং</label>
                            <input type="color" name="id_card_photo_border_color" class="form-control" value="<?= e($cfg['photo_border_color']) ?>" oninput="updatePreview()">
                        </div>
                    </div>
                    <div class="field-row">
                        <div class="form-group">
                            <label>কার্ড কোণা গোলত্ব (px)</label>
                            <input type="number" name="id_card_border_radius" class="form-control" value="<?= e($cfg['border_radius']) ?>" min="0" max="30" oninput="updatePreview()">
                        </div>
                    </div>
                </div>
            </div>

            <!-- নাম -->
            <div class="card">
                <div class="card-header"><span class="card-title"><i class="fas fa-font"></i> ছাত্রের নাম</span></div>
                <div class="card-body">
                    <div class="field-row">
                        <div class="form-group">
                            <label>ফন্ট</label>
                            <select name="id_card_name_font" class="form-control" onchange="updatePreview()">
                                <?php foreach($googleFonts as $fv => $fl): ?>
                                <option value="<?= $fv ?>" <?= $cfg['name_font']===$fv?'selected':'' ?>><?= $fl ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>সাইজ (px)</label>
                            <input type="number" name="id_card_name_size" class="form-control" value="<?= e($cfg['name_size']) ?>" min="6" max="24" step="0.5" oninput="updatePreview()">
                        </div>
                    </div>
                    <div class="field-row">
                        <div class="form-group">
                            <label>ওজন</label>
                            <select name="id_card_name_weight" class="form-control" onchange="updatePreview()">
                                <option value="400" <?= $cfg['name_weight']==='400'?'selected':'' ?>>Normal</option>
                                <option value="600" <?= $cfg['name_weight']==='600'?'selected':'' ?>>Semi Bold</option>
                                <option value="700" <?= $cfg['name_weight']==='700'?'selected':'' ?>>Bold</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>রং</label>
                            <input type="color" name="id_card_name_color" class="form-control" value="<?= e($cfg['name_color']) ?>" oninput="updatePreview()">
                        </div>
                        <div class="form-group">
                            <label>Alignment</label>
                            <select name="id_card_name_align" class="form-control" onchange="updatePreview()">
                                <option value="left"   <?= $cfg['name_align']==='left'  ?'selected':'' ?>>বাম</option>
                                <option value="center" <?= $cfg['name_align']==='center'?'selected':'' ?>>মাঝ</option>
                                <option value="right"  <?= $cfg['name_align']==='right' ?'selected':'' ?>>ডান</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>উপরে ফাঁক (px)</label>
                            <input type="number" name="id_card_name_mt" class="form-control" value="<?= e($cfg['name_mt']) ?>" min="0" max="30" oninput="updatePreview()">
                        </div>
                    </div>
                </div>
            </div>

            <!-- ID নম্বর -->
            <div class="card">
                <div class="card-header"><span class="card-title"><i class="fas fa-hashtag"></i> ID নম্বর</span></div>
                <div class="card-body">
                    <div class="field-row">
                        <div class="form-group">
                            <label>ফন্ট</label>
                            <select name="id_card_id_font" class="form-control" onchange="updatePreview()">
                                <?php foreach($googleFonts as $fv => $fl): ?>
                                <option value="<?= $fv ?>" <?= $cfg['id_font']===$fv?'selected':'' ?>><?= $fl ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>সাইজ (px)</label>
                            <input type="number" name="id_card_id_size" class="form-control" value="<?= e($cfg['id_size']) ?>" min="5" max="16" step="0.5" oninput="updatePreview()">
                        </div>
                        <div class="form-group">
                            <label>রং</label>
                            <input type="color" name="id_card_id_color" class="form-control" value="<?= e($cfg['id_color']) ?>" oninput="updatePreview()">
                        </div>
                        <div class="form-group">
                            <label>Alignment</label>
                            <select name="id_card_id_align" class="form-control" onchange="updatePreview()">
                                <option value="left"   <?= $cfg['id_align']==='left'  ?'selected':'' ?>>বাম</option>
                                <option value="center" <?= $cfg['id_align']==='center'?'selected':'' ?>>মাঝ</option>
                                <option value="right"  <?= $cfg['id_align']==='right' ?'selected':'' ?>>ডান</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- টেবিল (Class/Roll/Blood) -->
            <div class="card">
                <div class="card-header"><span class="card-title"><i class="fas fa-table"></i> তথ্য টেবিল (Class, Roll, Blood...)</span></div>
                <div class="card-body">
                    <div class="field-row">
                        <div class="form-group">
                            <label>ফন্ট</label>
                            <select name="id_card_table_font" class="form-control" onchange="updatePreview()">
                                <?php foreach($googleFonts as $fv => $fl): ?>
                                <option value="<?= $fv ?>" <?= $cfg['table_font']===$fv?'selected':'' ?>><?= $fl ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>সাইজ (px)</label>
                            <input type="number" name="id_card_table_size" class="form-control" value="<?= e($cfg['table_size']) ?>" min="5" max="14" step="0.5" oninput="updatePreview()">
                        </div>
                    </div>
                    <div class="field-row">
                        <div class="form-group">
                            <label>লেবেল রং</label>
                            <input type="color" name="id_card_table_label_color" class="form-control" value="<?= e($cfg['table_label_color']) ?>" oninput="updatePreview()">
                        </div>
                        <div class="form-group">
                            <label>মান রং</label>
                            <input type="color" name="id_card_table_val_color" class="form-control" value="<?= e($cfg['table_val_color']) ?>" oninput="updatePreview()">
                        </div>
                        <div class="form-group">
                            <label>সারির উচ্চতা (line-height)</label>
                            <input type="number" name="id_card_table_row_height" class="form-control" value="<?= e($cfg['table_row_height']) ?>" min="1" max="3" step="0.1" oninput="updatePreview()">
                        </div>
                        <div class="form-group">
                            <label>লেবেল প্রস্থ (px)</label>
                            <input type="number" name="id_card_table_label_width" class="form-control" value="<?= e($cfg['table_label_width']) ?>" min="20" max="80" oninput="updatePreview()">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== ট্যাব: পেছনের দিক (BACK) ===== -->
        <div class="tab-pane" id="tab-back">

            <!-- BACK padding -->
            <div class="card">
                <div class="card-header"><span class="card-title"><i class="fas fa-expand-arrows-alt"></i> পেছনের কার্ড — প্যাডিং (px)</span></div>
                <div class="card-body">
                    <div class="px-row">
                        <div class="form-group">
                            <label>উপরে (Top)</label>
                            <input type="number" name="id_card_back_pt" class="form-control" value="<?= e($cfg['back_pt']) ?>" min="0" max="40" oninput="updatePreview()">
                        </div>
                        <div class="form-group">
                            <label>নিচে (Bottom)</label>
                            <input type="number" name="id_card_back_pb" class="form-control" value="<?= e($cfg['back_pb']) ?>" min="0" max="40" oninput="updatePreview()">
                        </div>
                        <div class="form-group">
                            <label>বাম (Left)</label>
                            <input type="number" name="id_card_back_pl" class="form-control" value="<?= e($cfg['back_pl']) ?>" min="0" max="40" oninput="updatePreview()">
                        </div>
                        <div class="form-group">
                            <label>ডান (Right)</label>
                            <input type="number" name="id_card_back_pr" class="form-control" value="<?= e($cfg['back_pr']) ?>" min="0" max="40" oninput="updatePreview()">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Terms শিরোনাম -->
            <div class="card">
                <div class="card-header"><span class="card-title"><i class="fas fa-heading"></i> Terms শিরোনাম</span></div>
                <div class="card-body">
                    <div class="form-group" style="margin-bottom:10px;">
                        <label>শিরোনাম টেক্সট</label>
                        <input type="text" name="id_card_back_title" class="form-control" value="<?= e($cfg['back_title']) ?>" oninput="updatePreview()">
                    </div>
                    <div class="field-row">
                        <div class="form-group">
                            <label>ফন্ট</label>
                            <select name="id_card_back_title_font" class="form-control" onchange="updatePreview()">
                                <?php foreach($googleFonts as $fv => $fl): ?>
                                <option value="<?= $fv ?>" <?= $cfg['back_title_font']===$fv?'selected':'' ?>><?= $fl ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>সাইজ (px)</label>
                            <input type="number" name="id_card_back_title_size" class="form-control" value="<?= e($cfg['back_title_size']) ?>" min="6" max="20" step="0.5" oninput="updatePreview()">
                        </div>
                        <div class="form-group">
                            <label>রং</label>
                            <input type="color" name="id_card_back_title_color" class="form-control" value="<?= e($cfg['back_title_color']) ?>" oninput="updatePreview()">
                        </div>
                        <div class="form-group">
                            <label>Alignment</label>
                            <select name="id_card_back_title_align" class="form-control" onchange="updatePreview()">
                                <option value="left"   <?= $cfg['back_title_align']==='left'  ?'selected':'' ?>>বাম</option>
                                <option value="center" <?= $cfg['back_title_align']==='center'?'selected':'' ?>>মাঝ</option>
                                <option value="right"  <?= $cfg['back_title_align']==='right' ?'selected':'' ?>>ডান</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Terms টেক্সট -->
            <div class="card">
                <div class="card-header"><span class="card-title"><i class="fas fa-file-alt"></i> Terms টেক্সট</span></div>
                <div class="card-body">
                    <div class="form-group" style="margin-bottom:10px;">
                        <label>টেক্সট (সম্পাদনা করুন)</label>
                        <textarea name="id_card_back_terms" class="form-control" rows="5" style="font-size:12px;" oninput="updatePreview()"><?= e($cfg['back_terms']) ?></textarea>
                    </div>
                    <div class="field-row">
                        <div class="form-group">
                            <label>ফন্ট</label>
                            <select name="id_card_back_text_font" class="form-control" onchange="updatePreview()">
                                <?php foreach($googleFonts as $fv => $fl): ?>
                                <option value="<?= $fv ?>" <?= $cfg['back_text_font']===$fv?'selected':'' ?>><?= $fl ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>সাইজ (px)</label>
                            <input type="number" name="id_card_back_text_size" class="form-control" value="<?= e($cfg['back_text_size']) ?>" min="4" max="14" step="0.5" oninput="updatePreview()">
                        </div>
                        <div class="form-group">
                            <label>রং</label>
                            <input type="color" name="id_card_back_text_color" class="form-control" value="<?= e($cfg['back_text_color']) ?>" oninput="updatePreview()">
                        </div>
                        <div class="form-group">
                            <label>Alignment</label>
                            <select name="id_card_back_text_align" class="form-control" onchange="updatePreview()">
                                <option value="left"    <?= $cfg['back_text_align']==='left'   ?'selected':'' ?>>বাম</option>
                                <option value="center"  <?= $cfg['back_text_align']==='center' ?'selected':'' ?>>মাঝ</option>
                                <option value="right"   <?= $cfg['back_text_align']==='right'  ?'selected':'' ?>>ডান</option>
                                <option value="justify" <?= $cfg['back_text_align']==='justify'?'selected':'' ?>>Justify</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- স্বাক্ষর -->
            <div class="card">
                <div class="card-header"><span class="card-title"><i class="fas fa-signature"></i> Principal এর স্বাক্ষর লেবেল</span></div>
                <div class="card-body">
                    <div class="form-group" style="margin-bottom:10px;">
                        <label>স্বাক্ষর লেবেল টেক্সট</label>
                        <input type="text" name="id_card_back_sig_label" class="form-control" value="<?= e($cfg['back_sig_label']) ?>" oninput="updatePreview()">
                    </div>
                    <div class="field-row">
                        <div class="form-group">
                            <label>ফন্ট</label>
                            <select name="id_card_back_sig_font" class="form-control" onchange="updatePreview()">
                                <?php foreach($googleFonts as $fv => $fl): ?>
                                <option value="<?= $fv ?>" <?= $cfg['back_sig_font']===$fv?'selected':'' ?>><?= $fl ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>সাইজ (px)</label>
                            <input type="number" name="id_card_back_sig_size" class="form-control" value="<?= e($cfg['back_sig_size']) ?>" min="4" max="12" step="0.5" oninput="updatePreview()">
                        </div>
                        <div class="form-group">
                            <label>রং</label>
                            <input type="color" name="id_card_back_sig_color" class="form-control" value="<?= e($cfg['back_sig_color']) ?>" oninput="updatePreview()">
                        </div>
                    </div>
                </div>
            </div>

            <!-- ঠিকানা/ওয়েবসাইট -->
            <div class="card">
                <div class="card-header"><span class="card-title"><i class="fas fa-map-marker-alt"></i> ঠিকানা ও যোগাযোগ</span></div>
                <div class="card-body">
                    <p style="font-size:12px;color:var(--text-muted);margin-bottom:10px;">ঠিকানা, ফোন ও ওয়েবসাইট — মূল সেটিংস থেকে আসে। এখানে শুধু ডিজাইন।</p>
                    <div class="field-row">
                        <div class="form-group">
                            <label>ফন্ট</label>
                            <select name="id_card_back_addr_font" class="form-control" onchange="updatePreview()">
                                <?php foreach($googleFonts as $fv => $fl): ?>
                                <option value="<?= $fv ?>" <?= $cfg['back_addr_font']===$fv?'selected':'' ?>><?= $fl ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>সাইজ (px)</label>
                            <input type="number" name="id_card_back_addr_size" class="form-control" value="<?= e($cfg['back_addr_size']) ?>" min="4" max="12" step="0.5" oninput="updatePreview()">
                        </div>
                        <div class="form-group">
                            <label>রং</label>
                            <input type="color" name="id_card_back_addr_color" class="form-control" value="<?= e($cfg['back_addr_color']) ?>" oninput="updatePreview()">
                        </div>
                        <div class="form-group">
                            <label>Alignment</label>
                            <select name="id_card_back_addr_align" class="form-control" onchange="updatePreview()">
                                <option value="left"   <?= $cfg['back_addr_align']==='left'  ?'selected':'' ?>>বাম</option>
                                <option value="center" <?= $cfg['back_addr_align']==='center'?'selected':'' ?>>মাঝ</option>
                                <option value="right"  <?= $cfg['back_addr_align']==='right' ?'selected':'' ?>>ডান</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== ট্যাব: কার্ড রং ===== -->
        <div class="tab-pane" id="tab-colors">
            <div class="card">
                <div class="card-header"><span class="card-title"><i class="fas fa-user-graduate"></i> ছাত্র কার্ড রং</span></div>
                <div class="card-body">
                    <div class="field-row">
                        <div class="form-group">
                            <label>প্রাইমারি রং</label>
                            <input type="color" name="id_card_student_color1" class="form-control" value="<?= e($cfg['student_color1']) ?>" oninput="updatePreview()">
                        </div>
                        <div class="form-group">
                            <label>সেকেন্ডারি রং</label>
                            <input type="color" name="id_card_student_color2" class="form-control" value="<?= e($cfg['student_color2']) ?>" oninput="updatePreview()">
                        </div>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-header"><span class="card-title"><i class="fas fa-chalkboard-teacher"></i> শিক্ষক কার্ড রং</span></div>
                <div class="card-body">
                    <div class="field-row">
                        <div class="form-group">
                            <label>প্রাইমারি রং</label>
                            <input type="color" name="id_card_teacher_color1" class="form-control" value="<?= e($cfg['teacher_color1']) ?>">
                        </div>
                        <div class="form-group">
                            <label>সেকেন্ডারি রং</label>
                            <input type="color" name="id_card_teacher_color2" class="form-control" value="<?= e($cfg['teacher_color2']) ?>">
                        </div>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-header"><span class="card-title"><i class="fas fa-user-tie"></i> স্টাফ কার্ড রং</span></div>
                <div class="card-body">
                    <div class="field-row">
                        <div class="form-group">
                            <label>প্রাইমারি রং</label>
                            <input type="color" name="id_card_staff_color1" class="form-control" value="<?= e($cfg['staff_color1']) ?>">
                        </div>
                        <div class="form-group">
                            <label>সেকেন্ডারি রং</label>
                            <input type="color" name="id_card_staff_color2" class="form-control" value="<?= e($cfg['staff_color2']) ?>">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- সেভ বাটন -->
        <div style="display:flex;gap:12px;">
            <button type="submit" class="btn btn-primary" style="flex:1;">
                <i class="fas fa-save"></i> সেটিংস সেভ করুন
            </button>
            <a href="id_card.php" class="btn btn-outline">
                <i class="fas fa-times"></i> বাতিল
            </a>
        </div>

    </div><!-- /settings-panel -->

    <!-- ===== ডান: লাইভ প্রিভিউ ===== -->
    <div class="preview-panel">
        <div class="card">
            <div class="card-header">
                <span class="card-title"><i class="fas fa-eye"></i> লাইভ প্রিভিউ</span>
                <div style="display:flex;gap:6px;">
                    <button type="button" class="btn btn-outline btn-sm" onclick="setPreviewType('student')">ছাত্র</button>
                    <button type="button" class="btn btn-outline btn-sm" onclick="setPreviewType('teacher')">শিক্ষক</button>
                    <button type="button" class="btn btn-outline btn-sm" onclick="setPreviewType('staff')">স্টাফ</button>
                </div>
            </div>
            <div class="card-body" style="background:#e8edf2;padding:16px;">
                <div class="preview-wrap">
                    <!-- BACK -->
                    <div class="pv-card pv-back" id="pvBack">
                        <div class="pv-back-wm"><i class="fas fa-mosque"></i></div>
                        <div class="pv-back-inner" id="pvBackInner">
                            <div class="pv-back-title" id="pvBackTitle">Terms and Condition</div>
                            <p class="pv-back-text" id="pvBackText">This ID card must be brought and worn whenever the student attends the madrasah. If this card is lost, the student or guardian must inform the office immediately.</p>
                            <div class="pv-back-bottom">
                                <div class="pv-qr-row">
                                    <svg viewBox="0 0 100 100" width="50" height="50" xmlns="http://www.w3.org/2000/svg">
                                        <rect x="5" y="5" width="35" height="35" rx="3" fill="none" stroke="#e67e22" stroke-width="4"/>
                                        <rect x="12" y="12" width="21" height="21" rx="1" fill="#e67e22"/>
                                        <rect x="60" y="5" width="35" height="35" rx="3" fill="none" stroke="#e67e22" stroke-width="4"/>
                                        <rect x="67" y="12" width="21" height="21" rx="1" fill="#e67e22"/>
                                        <rect x="5" y="60" width="35" height="35" rx="3" fill="none" stroke="#e67e22" stroke-width="4"/>
                                        <rect x="12" y="67" width="21" height="21" rx="1" fill="#e67e22"/>
                                    </svg>
                                    <div class="pv-sig"><div class="pv-sig-line"></div><div class="pv-sig-txt" id="pvSigLabel">Principal's Signature</div></div>
                                </div>
                                <div class="pv-addr" id="pvAddr">
                                    <p><?= e(getSetting('address','পান্ধোয়া বাজার, আশুলিয়া, সাভার, ঢাকা')) ?></p>
                                    <p style="font-weight:700;">Mobile: <?= e(getSetting('phone','01715-821661')) ?></p>
                                    <p style="font-weight:700;"><?= e(getSetting('website','www.annazah.com')) ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- FRONT -->
                    <div class="pv-card pv-front" id="pvFront">
                        <div class="pv-strip" id="pvStrip">
                            <div class="pv-strip-top" id="pvStripTop"></div>
                            <div class="pv-strip-bot" id="pvStripBot"></div>
                            <div class="pv-strip-svg-wrap" id="pvStripSvgWrap" style="display:none;"></div>
                            <div class="pv-strip-label" id="pvStripLabel">STUDENT ID CARD</div>
                        </div>
                        <div class="pv-body" id="pvBody">
                            <div class="pv-header" id="pvHeader">
                                <?php if($cfg['logo_b64']): ?>
                                <img src="<?= $cfg['logo_b64'] ?>" class="pv-logo" id="pvLogoImg" alt="logo">
                                <div id="pvLogoPlaceholder" style="display:none;" class="pv-logo-placeholder">🕌</div>
                                <?php else: ?>
                                <div id="pvLogoImg" style="display:none;"></div>
                                <div id="pvLogoPlaceholder" class="pv-logo-placeholder">🕌</div>
                                <?php endif; ?>
                                <!-- institute name: image বা text -->
                                <?php if ($cfg['header_mode'] === 'image' && $cfg['inst_name_img_b64']): ?>
                                <img src="<?= $cfg['inst_name_img_b64'] ?>" id="pvInstImg"
                                    style="max-width:100%;height:<?= (int)$cfg['inst_name_img_height'] ?>px;object-fit:contain;" alt="inst">
                                <div id="pvInstText" style="display:none;width:100%;text-align:center;">
                                    <div class="pv-arabic" id="pvArabic">مدرسة النجاح لتحفيظ القرآن</div>
                                    <div class="pv-bn" id="pvBn"><?= e(getSetting('institute_name','আন নাজাহ তাহফিজুল কুরআন মাদরাসা')) ?></div>
                                </div>
                                <?php else: ?>
                                <img src="" id="pvInstImg" style="display:none;max-width:100%;object-fit:contain;" alt="inst">
                                <div id="pvInstText" style="width:100%;text-align:center;">
                                    <div class="pv-arabic" id="pvArabic">مدرسة النجاح لتحفيظ القرآن</div>
                                    <div class="pv-bn" id="pvBn"><?= e(getSetting('institute_name','আন নাজাহ তাহফিজুল কুরআন মাদরাসা')) ?></div>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="pv-photo-wrap">
                                <div class="pv-photo-box" id="pvPhotoBox">ক</div>
                            </div>
                            <div class="pv-name" id="pvName">
                                <span class="pv-name-first" id="pvNameFirst">Rakika Rahman</span>
                                <span class="pv-name-last" id="pvNameLast"> Toha</span>
                            </div>
                            <div class="pv-id" id="pvId">ID: ANT-2026-NP4X</div>
                            <div class="pv-table" id="pvTable">
                                <div class="pv-row"><span class="pv-label" id="pvLbl1">Class</span><span class="pv-val" id="pvVal1">:দ্বিতীয় শ্রেণী</span></div>
                                <div class="pv-row"><span class="pv-label" id="pvLbl2">Roll</span><span class="pv-val" id="pvVal2">:১</span></div>
                                <div class="pv-row"><span class="pv-label" id="pvLbl3">Blood</span><span class="pv-val" id="pvVal3">:O+</span></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div style="text-align:center;margin-top:8px;font-size:11px;color:var(--text-muted);">← পেছন &nbsp;|&nbsp; সামনে →</div>
            </div>
        </div>

        <!-- PHP কোড স্নিপেট -->
        <div class="card" style="margin-top:16px;">
            <div class="card-header"><span class="card-title"><i class="fas fa-code"></i> id_card.php এ যোগ করুন</span></div>
            <div class="card-body">
                <p style="font-size:12px;color:var(--text-muted);margin-bottom:8px;">আপনার id_card.php ফাইলের PHP সেকশনে $idc array-তে এই নতুন keys যোগ করুন:</p>
                <pre style="background:#1e2d3d;color:#a8d8ea;border-radius:8px;padding:12px;font-size:10px;overflow-x:auto;line-height:1.6;">'name_align'  =&gt; getSetting('id_card_name_align','center'),
'name_mt'     =&gt; getSetting('id_card_name_mt','6'),
'id_align'    =&gt; getSetting('id_card_id_align','center'),
'tb_rh'       =&gt; getSetting('id_card_table_row_height','1.8'),
'tb_lw'       =&gt; getSetting('id_card_table_label_width','38'),
'photo_w'     =&gt; getSetting('id_card_photo_width','80'),
'photo_h'     =&gt; getSetting('id_card_photo_height','95'),
'f_pt'        =&gt; getSetting('id_card_front_pt','8'),
'f_pb'        =&gt; getSetting('id_card_front_pb','8'),
'f_pl'        =&gt; getSetting('id_card_front_pl','6'),
'f_pr'        =&gt; getSetting('id_card_front_pr','8'),
'b_pt'        =&gt; getSetting('id_card_back_pt','12'),
'b_pb'        =&gt; getSetting('id_card_back_pb','8'),
'b_pl'        =&gt; getSetting('id_card_back_pl','10'),
'b_pr'        =&gt; getSetting('id_card_back_pr','10'),
'back_title'  =&gt; getSetting('id_card_back_title','Terms and Condition'),
'bt_font'     =&gt; getSetting('id_card_back_title_font','Libre Baskerville'),
'bt_size'     =&gt; getSetting('id_card_back_title_size','10'),
'bt_color'    =&gt; getSetting('id_card_back_title_color','#1a5276'),
'bt_align'    =&gt; getSetting('id_card_back_title_align','center'),
'back_terms'  =&gt; getSetting('id_card_back_terms','This ID card...'),
'bx_font'     =&gt; getSetting('id_card_back_text_font','Hind Siliguri'),
'bx_size'     =&gt; getSetting('id_card_back_text_size','6.5'),
'bx_color'    =&gt; getSetting('id_card_back_text_color','#444444'),
'bx_align'    =&gt; getSetting('id_card_back_text_align','justify'),
'sig_label'   =&gt; getSetting('id_card_back_sig_label',"Principal's Signature"),
'sig_font'    =&gt; getSetting('id_card_back_sig_font','Hind Siliguri'),
'sig_size'    =&gt; getSetting('id_card_back_sig_size','6'),
'sig_color'   =&gt; getSetting('id_card_back_sig_color','#555555'),
'addr_font'   =&gt; getSetting('id_card_back_addr_font','Hind Siliguri'),
'addr_size'   =&gt; getSetting('id_card_back_addr_size','6.5'),
'addr_color'  =&gt; getSetting('id_card_back_addr_color','#444444'),
'addr_align'  =&gt; getSetting('id_card_back_addr_align','center'),
'strip_w'     =&gt; getSetting('id_card_strip_width','30'),
'logo_sz'     =&gt; getSetting('id_card_logo_size','32'),</pre>
            </div>
        </div>
    </div><!-- /preview-panel -->

</div><!-- /settings-grid -->
</form>

<script>
// ===== ট্যাব =====
function switchTab(name) {
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    event.currentTarget.classList.add('active');
}

function toggleStripMode() {
    var use = document.getElementById('useCustomSvg').checked;
    document.getElementById('stripColorMode').style.display = use ? 'none' : '';
    document.getElementById('stripSvgMode').style.display   = use ? '' : 'none';

function toggleHeaderMode() {
    var mode = document.querySelector('input[name="id_card_header_mode"]:checked');
    if (!mode) return;
    var isImage = mode.value === 'image';
    document.getElementById('instImgMode').style.display  = isImage ? '' : 'none';
    document.getElementById('instTextMode').style.display = isImage ? 'none' : '';
    updatePreview();
}

function previewInstImg(input) {
    if (!input.files || !input.files[0]) return;
    var reader = new FileReader();
    reader.onload = function(e) {
        var img = document.getElementById('instImgPreview');
        img.src = e.target.result;
        img.style.display = 'block';
        // preview panel এও দেখাও
        var pvInstImg = document.getElementById('pvInstImg');
        if (pvInstImg) {
            pvInstImg.src = e.target.result;
            pvInstImg.style.display = 'block';
        }
        var pvInstText = document.getElementById('pvInstText');
        if (pvInstText) pvInstText.style.display = 'none';
    };
    reader.readAsDataURL(input.files[0]);
}
    updatePreview();
}

// ===== লোগো প্রিভিউ — BUG FIX: সঠিক ID ব্যবহার =====
function previewLogo(input) {
    if (!input.files || !input.files[0]) return;
    var reader = new FileReader();
    reader.onload = function(e) {
        // preview in live card
        var img = document.getElementById('pvLogoImg');
        var ph  = document.getElementById('pvLogoPlaceholder');
        img.src = e.target.result;
        img.style.display = '';
        img.className = 'pv-logo';
        ph.style.display = 'none';
        // preview in upload area
        var uImg = document.getElementById('logoUploadPreview');
        if (uImg) { uImg.src = e.target.result; uImg.style.display = ''; }
        updatePreview();
    };
    reader.readAsDataURL(input.files[0]);
}
function removeLogo() {
    document.getElementById('removeLogo').value = '1';
    var img = document.getElementById('pvLogoImg');
    var ph  = document.getElementById('pvLogoPlaceholder');
    if(img){ img.style.display='none'; img.src=''; }
    if(ph) { ph.style.display=''; }
    var uImg = document.getElementById('logoUploadPreview');
    if(uImg){ uImg.style.display='none'; uImg.src=''; }
}

function previewStripSvg(input) {
    if (!input.files || !input.files[0]) return;
    var reader = new FileReader();
    reader.onload = function(e) {
        var wrap = document.getElementById('pvStripSvgWrap');
        wrap.innerHTML = e.target.result;
        wrap.style.display = '';
        document.getElementById('pvStripTop').style.display = 'none';
        document.getElementById('pvStripBot').style.display = 'none';
    };
    reader.readAsText(input.files[0]);
}

var currentType = 'student';
function setPreviewType(type) {
    currentType = type;
    updatePreview();
}

function getVal(name) {
    var el = document.querySelector('[name="' + name + '"]');
    return el ? el.value : '';
}
function getNum(name, def) {
    var v = parseFloat(getVal(name));
    return isNaN(v) ? def : v;
}

function updatePreview() {
    var useCustom = document.getElementById('useCustomSvg') && document.getElementById('useCustomSvg').checked;

    var c1, c2;
    if (currentType === 'teacher') {
        c1 = getVal('id_card_teacher_color1') || '#1a3a6b';
        c2 = getVal('id_card_teacher_color2') || '#c9a227';
    } else if (currentType === 'staff') {
        c1 = getVal('id_card_staff_color1') || '#5b2c8c';
        c2 = getVal('id_card_staff_color2') || '#8e44ad';
    } else {
        c1 = getVal('id_card_student_color1') || '#1a8a3c';
        c2 = getVal('id_card_student_color2') || '#e67e22';
    }

    var sc1 = getVal('id_card_strip_color1') || c1;
    var sc2 = getVal('id_card_strip_color2') || c2;

    // স্ট্রিপ প্রস্থ
    var sw = getNum('id_card_strip_width', 30) + 'px';
    document.getElementById('pvStrip').style.width = sw;

    if (!useCustom) {
        document.getElementById('pvStripTop').style.background = sc1;
        document.getElementById('pvStripBot').style.background = sc2;
        document.getElementById('pvStripTop').style.display = '';
        document.getElementById('pvStripBot').style.display = '';
        document.getElementById('pvStripSvgWrap').style.display = 'none';
    }

    // লেবেল
    var lbl = document.getElementById('pvStripLabel');
    lbl.style.fontFamily    = "'" + (getVal('id_card_label_font') || 'Hind Siliguri') + "'";
    lbl.style.fontSize      = (getVal('id_card_label_size') || '9') + 'px';
    lbl.style.fontWeight    = getVal('id_card_label_weight') || '700';
    lbl.style.fontStyle     = getVal('id_card_label_style') || 'normal';
    lbl.style.color         = getVal('id_card_label_color') || '#fff';
    lbl.style.letterSpacing = (getVal('id_card_label_spacing') || '2') + 'px';
    var labels = { student: 'STUDENT ID CARD', teacher: 'TEACHER ID CARD', staff: 'STAFF ID CARD' };
    lbl.textContent = labels[currentType] || 'STUDENT ID CARD';

    // FRONT padding
    var fp = {
        t: getNum('id_card_front_pt', 8),
        b: getNum('id_card_front_pb', 8),
        l: getNum('id_card_front_pl', 6),
        r: getNum('id_card_front_pr', 8)
    };
    document.getElementById('pvBody').style.padding = fp.t+'px '+fp.r+'px '+fp.b+'px '+fp.l+'px';

    // হেডার
    var pvHeader = document.getElementById('pvHeader');
    pvHeader.style.borderBottomColor = c1;

    // লোগো সাইজ
    var lsz = getNum('id_card_logo_size', 32) + 'px';
    var pvLI = document.getElementById('pvLogoImg');
    var pvLP = document.getElementById('pvLogoPlaceholder');
    if(pvLI && pvLI.src && pvLI.style.display !== 'none') {
        pvLI.style.width = lsz; pvLI.style.height = lsz;
    }
    if(pvLP) {
        pvLP.style.width = lsz; pvLP.style.height = lsz;
        pvLP.style.background = 'linear-gradient(135deg,' + c1 + ',' + c2 + ')';
        pvLP.style.fontSize = (getNum('id_card_logo_size',32)*0.4)+'px';
    }

    // আরবি ও বাংলা নাম
    var ar = document.getElementById('pvArabic');
    if (ar) {
        ar.style.fontFamily = "'" + (getVal('id_card_arabic_font') || 'Hind Siliguri') + "'";
        ar.style.fontSize   = (getVal('id_card_arabic_size') || '7.5') + 'px';
        ar.style.color      = getVal('id_card_arabic_color') || '#1a5276';
    }
    var bn = document.getElementById('pvBn');
    if (bn) {
        bn.style.fontFamily = "'" + (getVal('id_card_bn_font') || 'Hind Siliguri') + "'";
        bn.style.fontSize   = (getVal('id_card_bn_size') || '6.5') + 'px';
        bn.style.color      = getVal('id_card_bn_color') || c1;
    }

    // header mode: image বা text
    var modeEl = document.querySelector('input[name="id_card_header_mode"]:checked');
    var headerMode = modeEl ? modeEl.value : 'text';
    var pvInstImg  = document.getElementById('pvInstImg');
    var pvInstText = document.getElementById('pvInstText');
    if (headerMode === 'image' && pvInstImg && pvInstImg.src && pvInstImg.src !== window.location.href) {
        if (pvInstImg) { pvInstImg.style.display = ''; pvInstImg.style.height = getNum('id_card_inst_name_img_height',32)+'px'; }
        if (pvInstText) pvInstText.style.display = 'none';
    } else {
        if (pvInstImg) pvInstImg.style.display = 'none';
        if (pvInstText) pvInstText.style.display = '';
    }

    // ছবি
    var pbox = document.getElementById('pvPhotoBox');
    var pbc  = getVal('id_card_photo_border_color') || c2;
    pbox.style.borderColor = pbc;
    pbox.style.color       = c1;
    pbox.style.width       = getNum('id_card_photo_width', 80) + 'px';
    pbox.style.height      = getNum('id_card_photo_height', 95) + 'px';

    // নাম
    var nmt = getNum('id_card_name_mt', 6);
    var pvName = document.getElementById('pvName');
    pvName.style.textAlign  = getVal('id_card_name_align') || 'center';
    pvName.style.marginTop  = nmt + 'px';
    var nf = document.getElementById('pvNameFirst');
    nf.style.fontFamily = "'" + (getVal('id_card_name_font') || 'Libre Baskerville') + "'";
    nf.style.fontSize   = (getVal('id_card_name_size') || '14') + 'px';
    nf.style.fontWeight = getVal('id_card_name_weight') || '700';
    nf.style.color      = getVal('id_card_name_color') || c1;
    var nl = document.getElementById('pvNameLast');
    nl.style.fontFamily = nf.style.fontFamily;
    nl.style.fontSize   = nf.style.fontSize;

    // আইডি
    var idEl = document.getElementById('pvId');
    idEl.style.fontFamily  = "'" + (getVal('id_card_id_font') || 'Hind Siliguri') + "'";
    idEl.style.fontSize    = (getVal('id_card_id_size') || '8.5') + 'px';
    idEl.style.color       = getVal('id_card_id_color') || '#555';
    idEl.style.textAlign   = getVal('id_card_id_align') || 'center';

    // টেবিল
    var tFont  = getVal('id_card_table_font') || 'Hind Siliguri';
    var tSize  = (getVal('id_card_table_size') || '8') + 'px';
    var tLblC  = getVal('id_card_table_label_color') || '#1a5276';
    var tValC  = getVal('id_card_table_val_color') || '#333';
    var tRH    = getVal('id_card_table_row_height') || '1.8';
    var tLW    = getNum('id_card_table_label_width', 38) + 'px';
    ['pvLbl1','pvLbl2','pvLbl3'].forEach(function(id){
        var el = document.getElementById(id);
        if (!el) return;
        el.style.fontFamily = "'" + tFont + "'";
        el.style.fontSize   = tSize;
        el.style.color      = tLblC;
        el.style.width      = tLW;
    });
    ['pvVal1','pvVal2','pvVal3'].forEach(function(id){
        var el = document.getElementById(id);
        if (!el) return;
        el.style.fontFamily = "'" + tFont + "'";
        el.style.fontSize   = tSize;
        el.style.color      = tValC;
    });
    document.querySelectorAll('.pv-row').forEach(function(r){ r.style.lineHeight = tRH; });
    document.getElementById('pvTable').style.borderTopColor = c1;

    // কার্ড radius
    var r = (getVal('id_card_border_radius') || '10') + 'px';
    document.getElementById('pvFront').style.borderRadius = r;
    document.getElementById('pvBack').style.borderRadius  = r;

    // BACK padding
    var bp = {
        t: getNum('id_card_back_pt', 12),
        b: getNum('id_card_back_pb', 8),
        l: getNum('id_card_back_pl', 10),
        r: getNum('id_card_back_pr', 10)
    };
    document.getElementById('pvBackInner').style.padding = bp.t+'px '+bp.r+'px '+bp.b+'px '+bp.l+'px';

    // Back Title
    var btEl = document.getElementById('pvBackTitle');
    btEl.textContent   = getVal('id_card_back_title') || 'Terms and Condition';
    btEl.style.fontFamily  = "'" + (getVal('id_card_back_title_font') || 'Libre Baskerville') + "'";
    btEl.style.fontSize    = (getVal('id_card_back_title_size') || '10') + 'px';
    btEl.style.color       = getVal('id_card_back_title_color') || '#1a5276';
    btEl.style.textAlign   = getVal('id_card_back_title_align') || 'center';
    btEl.style.fontWeight  = '700';

    // Back Terms text
    var bxEl = document.getElementById('pvBackText');
    var rawText = getVal('id_card_back_terms');
    if (rawText) bxEl.textContent = rawText;
    bxEl.style.fontFamily  = "'" + (getVal('id_card_back_text_font') || 'Hind Siliguri') + "'";
    bxEl.style.fontSize    = (getVal('id_card_back_text_size') || '6.5') + 'px';
    bxEl.style.color       = getVal('id_card_back_text_color') || '#444';
    bxEl.style.textAlign   = getVal('id_card_back_text_align') || 'justify';

    // Signature
    var sigEl = document.getElementById('pvSigLabel');
    sigEl.textContent      = getVal('id_card_back_sig_label') || "Principal's Signature";
    sigEl.style.fontFamily = "'" + (getVal('id_card_back_sig_font') || 'Hind Siliguri') + "'";
    sigEl.style.fontSize   = (getVal('id_card_back_sig_size') || '6') + 'px';
    sigEl.style.color      = getVal('id_card_back_sig_color') || '#555';

    // Address
    var adEl = document.getElementById('pvAddr');
    adEl.style.fontFamily  = "'" + (getVal('id_card_back_addr_font') || 'Hind Siliguri') + "'";
    adEl.style.fontSize    = (getVal('id_card_back_addr_size') || '6.5') + 'px';
    adEl.style.color       = getVal('id_card_back_addr_color') || '#444';
    adEl.style.textAlign   = getVal('id_card_back_addr_align') || 'center';

    // card type preview data
    if (currentType === 'teacher') {
        document.getElementById('pvLbl1').textContent = 'পদবী';
        document.getElementById('pvVal1').textContent = ':হেড শিক্ষক';
        document.getElementById('pvLbl2').textContent = 'Phone';
        document.getElementById('pvVal2').textContent = ':01700-000000';
        document.getElementById('pvLbl3').textContent = 'Blood';
        document.getElementById('pvVal3').textContent = ':A+';
        document.getElementById('pvId').textContent = 'ID: TCH-2026-001';
    } else if (currentType === 'staff') {
        document.getElementById('pvLbl1').textContent = 'পদবী';
        document.getElementById('pvVal1').textContent = ':অফিস সহকারী';
        document.getElementById('pvLbl2').textContent = 'Phone';
        document.getElementById('pvVal2').textContent = ':01800-000000';
        document.getElementById('pvLbl3').textContent = 'Blood';
        document.getElementById('pvVal3').textContent = ':B+';
        document.getElementById('pvId').textContent = 'ID: STF-2026-001';
    } else {
        document.getElementById('pvLbl1').textContent = 'Class';
        document.getElementById('pvVal1').textContent = ':দ্বিতীয় শ্রেণী';
        document.getElementById('pvLbl2').textContent = 'Roll';
        document.getElementById('pvVal2').textContent = ':১';
        document.getElementById('pvLbl3').textContent = 'Blood';
        document.getElementById('pvVal3').textContent = ':O+';
        document.getElementById('pvId').textContent = 'ID: ANT-2026-NP4X';
    }
}

document.addEventListener('DOMContentLoaded', function(){
    updatePreview();
    document.querySelectorAll('input[type=color], input[type=number], select, input[type=text], textarea').forEach(function(el){
        el.addEventListener('input', updatePreview);
        el.addEventListener('change', updatePreview);
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>
