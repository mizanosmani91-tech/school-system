<?php
/**
 * আইডি কার্ড ডিজাইন সেটিংস
 * ফাইল: modules/idcard/id_card_settings.php
 *
 * এই ফাইলটি id_card.php এর পাশে রাখুন।
 * settings টেবিলে id_card_design_* key দিয়ে সব সেভ হবে।
 */
require_once '../../includes/functions.php';
requireLogin(['super_admin','principal']);
$pageTitle = 'আইডি কার্ড ডিজাইন সেটিংস';
$db = getDB();

// ===== হেল্পার: getSetting যদি না থাকে =====
function idcs($key, $default = '') {
    global $db;
    try {
        $r = $db->prepare("SELECT value FROM settings WHERE `key`=? LIMIT 1");
        $r->execute([$key]);
        $v = $r->fetchColumn();
        return $v !== false ? $v : $default;
    } catch(Exception $e) { return $default; }
}
function saveIdcs($key, $value) {
    global $db;
    try {
        $c = $db->prepare("SELECT COUNT(*) FROM settings WHERE `key`=?");
        $c->execute([$key]);
        if ($c->fetchColumn()) {
            $db->prepare("UPDATE settings SET value=? WHERE `key`=?")->execute([$value, $key]);
        } else {
            $db->prepare("INSERT INTO settings(`key`,value) VALUES(?,?)")->execute([$key, $value]);
        }
        return true;
    } catch(Exception $e) { return false; }
}

$msg = '';
$msgType = 'success';

// ===== SVG/Image আপলোড হ্যান্ডেল =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // লোগো সরানো
    if (!empty($_POST['remove_logo']) && $_POST['remove_logo'] === '1') {
        saveIdcs('id_card_logo_b64', '');
    }

    // লোগো আপলোড (remove_logo না থাকলে)
    if (empty($_POST['remove_logo']) && !empty($_FILES['logo_svg']['tmp_name']) && $_FILES['logo_svg']['error'] === UPLOAD_ERR_OK) {
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

    // বাকি ফর্ম ফিল্ড সেভ
    $fields = [
        // স্ট্রিপ
        'id_card_strip_color1','id_card_strip_color2','id_card_strip_use_custom_svg',
        // লেবেল ফন্ট (STUDENT ID CARD)
        'id_card_label_font','id_card_label_size','id_card_label_weight','id_card_label_style','id_card_label_color','id_card_label_spacing',
        // নাম ফন্ট
        'id_card_name_font','id_card_name_size','id_card_name_weight','id_card_name_color',
        // আইডি ফন্ট
        'id_card_id_font','id_card_id_size','id_card_id_color',
        // টেবিল ফন্ট
        'id_card_table_font','id_card_table_size','id_card_table_label_color','id_card_table_val_color',
        // হেডার
        'id_card_arabic_font','id_card_arabic_size','id_card_arabic_color',
        'id_card_bn_font','id_card_bn_size','id_card_bn_color',
        // ছাত্র কার্ড রং
        'id_card_student_color1','id_card_student_color2',
        // শিক্ষক কার্ড রং
        'id_card_teacher_color1','id_card_teacher_color2',
        // স্টাফ কার্ড রং
        'id_card_staff_color1','id_card_staff_color2',
        // ব্যাকগ্রাউন্ড ও বর্ডার
        'id_card_border_radius','id_card_photo_border_color',
    ];
    foreach ($fields as $f) {
        if (isset($_POST[$f])) {
            saveIdcs($f, trim($_POST[$f]));
        }
    }
    // POST-Redirect-GET: double submit প্রতিরোধ করে
    header('Location: id_card_settings.php?saved=1');
    exit;
}

// ===== বর্তমান মান লোড =====
$savedMsg = !empty($_GET['saved']) ? 'সেটিংস সফলভাবে সেভ হয়েছে!' : '';
$cfg = [
    'logo_b64'              => idcs('id_card_logo_b64',''),
    'strip_svg'             => idcs('id_card_strip_svg',''),
    'strip_use_custom'      => idcs('id_card_strip_use_custom_svg','0'),
    'strip_color1'          => idcs('id_card_strip_color1','#1a8a3c'),
    'strip_color2'          => idcs('id_card_strip_color2','#e67e22'),
    // লেবেল
    'label_font'            => idcs('id_card_label_font','Hind Siliguri'),
    'label_size'            => idcs('id_card_label_size','9'),
    'label_weight'          => idcs('id_card_label_weight','700'),
    'label_style'           => idcs('id_card_label_style','normal'),
    'label_color'           => idcs('id_card_label_color','#ffffff'),
    'label_spacing'         => idcs('id_card_label_spacing','2'),
    // নাম
    'name_font'             => idcs('id_card_name_font','Libre Baskerville'),
    'name_size'             => idcs('id_card_name_size','14'),
    'name_weight'           => idcs('id_card_name_weight','700'),
    'name_color'            => idcs('id_card_name_color','#1a8a3c'),
    // আইডি
    'id_font'               => idcs('id_card_id_font','Hind Siliguri'),
    'id_size'               => idcs('id_card_id_size','8.5'),
    'id_color'              => idcs('id_card_id_color','#555555'),
    // টেবিল
    'table_font'            => idcs('id_card_table_font','Hind Siliguri'),
    'table_size'            => idcs('id_card_table_size','8'),
    'table_label_color'     => idcs('id_card_table_label_color','#1a5276'),
    'table_val_color'       => idcs('id_card_table_val_color','#333333'),
    // হেডার আরবি
    'arabic_font'           => idcs('id_card_arabic_font','Hind Siliguri'),
    'arabic_size'           => idcs('id_card_arabic_size','7.5'),
    'arabic_color'          => idcs('id_card_arabic_color','#1a5276'),
    // হেডার বাংলা
    'bn_font'               => idcs('id_card_bn_font','Hind Siliguri'),
    'bn_size'               => idcs('id_card_bn_size','6.5'),
    'bn_color'              => idcs('id_card_bn_color','#1a8a3c'),
    // কার্ড রং
    'student_color1'        => idcs('id_card_student_color1','#1a8a3c'),
    'student_color2'        => idcs('id_card_student_color2','#e67e22'),
    'teacher_color1'        => idcs('id_card_teacher_color1','#1a3a6b'),
    'teacher_color2'        => idcs('id_card_teacher_color2','#c9a227'),
    'staff_color1'          => idcs('id_card_staff_color1','#5b2c8c'),
    'staff_color2'          => idcs('id_card_staff_color2','#8e44ad'),
    // ছবি বর্ডার
    'photo_border_color'    => idcs('id_card_photo_border_color','#e67e22'),
    'border_radius'         => idcs('id_card_border_radius','10'),
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

<!-- Google Fonts লোড -->
<link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;600;700&family=Libre+Baskerville:ital,wght@0,400;0,700;1,400&family=Roboto:wght@400;700&family=Open+Sans:wght@400;700&family=Montserrat:wght@400;700&family=Poppins:wght@400;700&family=Playfair+Display:wght@400;700&family=Raleway:wght@400;700&family=Oswald:wght@400;700&family=Lato:wght@400;700&family=Noto+Serif+Bengali:wght@400;700&family=Tiro+Bangla&family=Baloo+Da+2:wght@400;700&display=swap" rel="stylesheet">

<style>
.settings-grid { display: grid; grid-template-columns: 1fr 420px; gap: 24px; align-items: start; }
@media(max-width:1100px){ .settings-grid { grid-template-columns: 1fr; } }
.settings-panel { display: flex; flex-direction: column; gap: 16px; }
.preview-panel { position: sticky; top: 80px; }
.tab-row { display: flex; gap: 4px; flex-wrap: wrap; margin-bottom: 16px; }
.tab-btn { padding: 7px 16px; border-radius: 7px; border: 1.5px solid var(--border);
    background: #fff; cursor: pointer; font-size: 13px; font-weight: 600; color: var(--text-muted);
    font-family: var(--font); transition: all .2s; }
.tab-btn.active { background: var(--primary); color: #fff; border-color: var(--primary); }
.tab-pane { display: none; }
.tab-pane.active { display: block; }
.field-row { display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 12px; }
.field-row .form-group { flex: 1; min-width: 120px; margin: 0; }
.color-preview { width: 28px; height: 28px; border-radius: 6px; border: 2px solid var(--border); display: inline-block; vertical-align: middle; margin-left: 6px; }
.upload-area {
    border: 2px dashed var(--border); border-radius: 10px; padding: 18px;
    text-align: center; cursor: pointer; transition: all .2s; background: var(--bg);
}
.upload-area:hover { border-color: var(--primary); background: #ebf5fb; }
.upload-area input[type=file] { display: none; }
.upload-preview { max-width: 80px; max-height: 60px; margin: 8px auto 0; display: block; }

/* ===== লাইভ প্রিভিউ কার্ড ===== */
.preview-wrap {
    display: flex; gap: 10px; justify-content: center;
    overflow-x: auto; padding: 12px 0;
}
/* CR80: 54×85.6mm → 204×323px */
.pv-card {
    width: 204px; height: 323px; border-radius: var(--pv-radius, 10px);
    overflow: hidden; box-shadow: 0 6px 24px rgba(0,0,0,.2);
    position: relative; font-family: 'Hind Siliguri', sans-serif;
    flex-shrink: 0;
}
/* FRONT */
.pv-front { background: #fff; display: flex; }
.pv-strip {
    width: 30px; position: relative; flex-shrink: 0;
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
.pv-body { flex: 1; display: flex; flex-direction: column; padding: 8px 8px 8px 6px; }
.pv-header { display: flex; align-items: center; gap: 5px; padding-bottom: 5px; margin-bottom: 6px; }
.pv-logo { width: 32px; height: 32px; object-fit: contain; flex-shrink: 0; }
.pv-logo-placeholder {
    width: 32px; height: 32px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-size: 13px; flex-shrink: 0;
    font-weight: 700;
}
.pv-inst { flex: 1; text-align: center; }
.pv-arabic { font-size: 7.5px; font-weight: 600; line-height: 1.3; direction: rtl; text-align: center; }
.pv-bn { font-size: 6.5px; font-weight: 700; line-height: 1.3; text-align: center; }
.pv-photo-wrap { text-align: center; margin: 4px 0; }
.pv-photo-box {
    width: 80px; height: 95px; border: 3px solid #e67e22;
    border-radius: 4px; display: inline-flex;
    align-items: center; justify-content: center;
    background: #f0f8f0; font-size: 28px; font-weight: 700; color: #1a8a3c;
}
.pv-name { text-align: center; margin-top: 6px; line-height: 1.2; }
.pv-name-first { font-size: 14px; font-weight: 700; font-family: 'Libre Baskerville', serif; }
.pv-name-last  { font-size: 14px; font-weight: 400; font-family: 'Libre Baskerville', serif; color: #333; }
.pv-id { text-align: center; font-size: 8.5px; font-weight: 700; color: #555; margin: 2px 0 5px; letter-spacing: 0.5px; }
.pv-table { padding-top: 5px; }
.pv-row { display: flex; font-size: 8px; line-height: 1.8; }
.pv-label { width: 38px; font-weight: 600; }
.pv-val { flex: 1; }
/* BACK */
.pv-back { background: #fff; border: 1px solid #ddd; position: relative; overflow: hidden;
    display: flex; flex-direction: column; }
.pv-back-wm { position: absolute; top:50%;left:50%;transform:translate(-50%,-50%);
    font-size:90px;color:rgba(26,138,60,.06);pointer-events:none; }
.pv-back-inner { padding: 12px 10px 8px; display: flex; flex-direction: column; height: 100%; position: relative; z-index:1; }
.pv-back-title { font-size:10px;font-weight:700;color:#1a5276;text-align:center;margin-bottom:7px; }
.pv-back-text { font-size:6.5px;color:#444;line-height:1.7;text-align:justify;flex:1; }
.pv-back-bottom { margin-top:8px;border-top:1px solid #e67e22;padding-top:7px;display:flex;flex-direction:column;gap:5px; }
.pv-qr-row { display:flex;align-items:center;justify-content:space-between; }
.pv-sig { text-align:center; }
.pv-sig-line { width:60px;border-top:1px solid #333;margin:0 auto 2px; }
.pv-sig-txt { font-size:6px;color:#555; }
.pv-addr { font-size:6.5px;color:#444;text-align:center;line-height:1.6; }
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

        <!-- ট্যাব বোতাম -->
        <div class="tab-row">
            <button type="button" class="tab-btn active" onclick="switchTab('logo')"><i class="fas fa-image"></i> লোগো</button>
            <button type="button" class="tab-btn" onclick="switchTab('strip')"><i class="fas fa-grip-lines-vertical"></i> সাইড স্ট্রিপ</button>
            <button type="button" class="tab-btn" onclick="switchTab('fonts')"><i class="fas fa-font"></i> ফন্ট</button>
            <button type="button" class="tab-btn" onclick="switchTab('colors')"><i class="fas fa-fill-drip"></i> কার্ড রং</button>
        </div>

        <!-- ===== ট্যাব: লোগো ===== -->
        <div class="tab-pane active" id="tab-logo">
            <div class="card">
                <div class="card-header"><span class="card-title"><i class="fas fa-image"></i> লোগো / হেডার ইমেজ</span></div>
                <div class="card-body">
                    <p style="font-size:13px;color:var(--text-muted);margin-bottom:12px;">SVG, PNG, JPG, WebP সাপোর্টেড। কার্ডের উপরে বাম দিকে ৩২×৩২ px বক্সে বসবে।</p>
                    <div class="upload-area" onclick="document.getElementById('logo_svg').click()">
                        <i class="fas fa-cloud-upload-alt" style="font-size:28px;color:var(--primary-light);margin-bottom:8px;display:block;"></i>
                        <div style="font-size:13px;font-weight:600;">লোগো আপলোড করুন</div>
                        <div style="font-size:11px;color:var(--text-muted);">SVG / PNG / JPG / WebP</div>
                        <input type="file" id="logo_svg" name="logo_svg" accept=".svg,.png,.jpg,.jpeg,.webp" onchange="previewLogo(this)">
                        <?php if($cfg['logo_b64']): ?>
                        <img src="<?= $cfg['logo_b64'] ?>" class="upload-preview" id="logoPreviewImg" alt="current logo">
                        <?php else: ?>
                        <img src="" class="upload-preview" id="logoPreviewImg" alt="" style="display:none;">
                        <?php endif; ?>
                    </div>
                    <?php if($cfg['logo_b64']): ?>
                    <div style="margin-top:8px;text-align:center;">
                        <button type="button" class="btn btn-outline btn-sm" onclick="removeLogo()"><i class="fas fa-trash"></i> লোগো সরান</button>
                    </div>
                    <input type="hidden" name="remove_logo" id="removeLogo" value="0">
                    <?php endif; ?>
                </div>
            </div>

            <!-- হেডার টেক্সট ফন্ট -->
            <div class="card">
                <div class="card-header"><span class="card-title"><i class="fas fa-mosque"></i> হেডার — আরবি লেখা</span></div>
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
                <div class="card-header"><span class="card-title"><i class="fas fa-grip-lines-vertical"></i> সাইড স্ট্রিপ ডিজাইন</span></div>
                <div class="card-body">
                    <div class="field-row" style="margin-bottom:16px;">
                        <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-size:14px;">
                            <input type="checkbox" name="id_card_strip_use_custom_svg" id="useCustomSvg" value="1"
                                <?= $cfg['strip_use_custom']==='1'?'checked':'' ?> onchange="toggleStripMode()">
                            <span>কাস্টম SVG শেপ ব্যবহার করব</span>
                        </label>
                    </div>

                    <!-- ডিফল্ট রং মোড -->
                    <div id="stripColorMode" <?= $cfg['strip_use_custom']==='1'?'style="display:none"':'' ?>>
                        <p style="font-size:13px;color:var(--text-muted);margin-bottom:12px;">ডিফল্ট diagonal শেপের দুটি রং পরিবর্তন করুন:</p>
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

                    <!-- কাস্টম SVG মোড -->
                    <div id="stripSvgMode" <?= $cfg['strip_use_custom']!=='1'?'style="display:none"':'' ?>>
                        <p style="font-size:13px;color:var(--text-muted);margin-bottom:8px;">
                            SVG ফাইল আপলোড করুন। ৩০px প্রস্থ × ৩২৩px উচ্চতার বক্সে বসবে।<br>
                            <strong>টিপস:</strong> viewBox="0 0 30 323" দিয়ে SVG বানান।
                        </p>
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

            <!-- লেবেল (STUDENT ID CARD) ফন্ট -->
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
                                <option value="400" <?= $cfg['label_weight']==='400'?'selected':'' ?>>Normal (400)</option>
                                <option value="600" <?= $cfg['label_weight']==='600'?'selected':'' ?>>Semi Bold (600)</option>
                                <option value="700" <?= $cfg['label_weight']==='700'?'selected':'' ?>>Bold (700)</option>
                                <option value="800" <?= $cfg['label_weight']==='800'?'selected':'' ?>>Extra Bold (800)</option>
                                <option value="900" <?= $cfg['label_weight']==='900'?'selected':'' ?>>Black (900)</option>
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
                            <label>লেটার স্পেসিং</label>
                            <input type="number" name="id_card_label_spacing" class="form-control" value="<?= e($cfg['label_spacing']) ?>" min="0" max="10" step="0.5" oninput="updatePreview()">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== ট্যাব: ফন্ট ===== -->
        <div class="tab-pane" id="tab-fonts">
            <!-- নাম ফন্ট -->
            <div class="card">
                <div class="card-header"><span class="card-title"><i class="fas fa-user"></i> নাম ফন্ট</span></div>
                <div class="card-body">
                    <div class="field-row">
                        <div class="form-group">
                            <label>ফন্ট ফ্যামিলি</label>
                            <select name="id_card_name_font" class="form-control" onchange="updatePreview()">
                                <?php foreach($googleFonts as $fv => $fl): ?>
                                <option value="<?= $fv ?>" <?= $cfg['name_font']===$fv?'selected':'' ?>><?= $fl ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>সাইজ (px)</label>
                            <input type="number" name="id_card_name_size" class="form-control" value="<?= e($cfg['name_size']) ?>" min="8" max="24" step="0.5" oninput="updatePreview()">
                        </div>
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
                    </div>
                </div>
            </div>

            <!-- আইডি ফন্ট -->
            <div class="card">
                <div class="card-header"><span class="card-title"><i class="fas fa-hashtag"></i> আইডি নম্বর ফন্ট</span></div>
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
                    </div>
                </div>
            </div>

            <!-- তথ্য টেবিল ফন্ট -->
            <div class="card">
                <div class="card-header"><span class="card-title"><i class="fas fa-table"></i> তথ্য সারি ফন্ট (Class/Roll/Blood)</span></div>
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
                    </div>
                </div>
            </div>

            <!-- ছবি ও বর্ডার -->
            <div class="card">
                <div class="card-header"><span class="card-title"><i class="fas fa-image"></i> ছবি ও কার্ড বর্ডার</span></div>
                <div class="card-body">
                    <div class="field-row">
                        <div class="form-group">
                            <label>ছবি বর্ডার রং</label>
                            <input type="color" name="id_card_photo_border_color" class="form-control" value="<?= e($cfg['photo_border_color']) ?>" oninput="updatePreview()">
                        </div>
                        <div class="form-group">
                            <label>কার্ড কোণা গোলত্ব (px)</label>
                            <input type="number" name="id_card_border_radius" class="form-control" value="<?= e($cfg['border_radius']) ?>" min="0" max="30" oninput="updatePreview()">
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
                            <label>প্রাইমারি রং (স্ট্রিপ উপর / বর্ডার)</label>
                            <input type="color" name="id_card_student_color1" class="form-control" value="<?= e($cfg['student_color1']) ?>" oninput="updatePreview()">
                        </div>
                        <div class="form-group">
                            <label>সেকেন্ডারি রং (স্ট্রিপ নিচ / অ্যাকসেন্ট)</label>
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
                        <div class="pv-back-inner">
                            <div class="pv-back-title">Terms and Condition</div>
                            <p class="pv-back-text">This ID card must be brought and worn whenever the student attends the madrasah. If this card is lost, the student or guardian must inform the office immediately.</p>
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
                                    <div class="pv-sig"><div class="pv-sig-line"></div><div class="pv-sig-txt">Principal's Signature</div></div>
                                </div>
                                <div class="pv-addr">
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
                        <div class="pv-body">
                            <div class="pv-header" id="pvHeader">
                                <?php if($cfg['logo_b64']): ?>
                                <img src="<?= $cfg['logo_b64'] ?>" class="pv-logo" id="pvLogoImg" alt="logo">
                                <div id="pvLogoPlaceholder" style="display:none;" class="pv-logo-placeholder">🕌</div>
                                <?php else: ?>
                                <div id="pvLogoImg" style="display:none;"></div>
                                <div id="pvLogoPlaceholder" class="pv-logo-placeholder">🕌</div>
                                <?php endif; ?>
                                <div class="pv-inst">
                                    <div class="pv-arabic" id="pvArabic">مدرسة النجاح لتحفيظ القرآن</div>
                                    <div class="pv-bn" id="pvBn"><?= e(getSetting('institute_name','আন নাজাহ তাহফিজুল কুরআন মাদরাসা')) ?></div>
                                </div>
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
                <p style="font-size:12px;color:var(--text-muted);margin-bottom:8px;">আপনার id_card.php ফাইলের PHP সেকশনের শুরুতে এই কোড যোগ করুন:</p>
                <pre style="background:#1e2d3d;color:#a8d8ea;border-radius:8px;padding:12px;font-size:11px;overflow-x:auto;line-height:1.6;">// আইডি কার্ড ডিজাইন সেটিংস লোড
$idc = [
  'logo'        =&gt; getSetting('id_card_logo_b64',''),
  'strip_svg'   =&gt; getSetting('id_card_strip_svg',''),
  'use_svg'     =&gt; getSetting('id_card_strip_use_custom_svg','0'),
  'sc1'         =&gt; getSetting('id_card_strip_color1','#1a8a3c'),
  'sc2'         =&gt; getSetting('id_card_strip_color2','#e67e22'),
  'label_font'  =&gt; getSetting('id_card_label_font','Hind Siliguri'),
  'label_size'  =&gt; getSetting('id_card_label_size','9'),
  'label_w'     =&gt; getSetting('id_card_label_weight','700'),
  'label_style' =&gt; getSetting('id_card_label_style','normal'),
  'label_color' =&gt; getSetting('id_card_label_color','#ffffff'),
  'label_ls'    =&gt; getSetting('id_card_label_spacing','2'),
  'name_font'   =&gt; getSetting('id_card_name_font','Libre Baskerville'),
  'name_size'   =&gt; getSetting('id_card_name_size','14'),
  'name_w'      =&gt; getSetting('id_card_name_weight','700'),
  'name_color'  =&gt; getSetting('id_card_name_color','#1a8a3c'),
  'id_font'     =&gt; getSetting('id_card_id_font','Hind Siliguri'),
  'id_size'     =&gt; getSetting('id_card_id_size','8.5'),
  'id_color'    =&gt; getSetting('id_card_id_color','#555555'),
  'tb_font'     =&gt; getSetting('id_card_table_font','Hind Siliguri'),
  'tb_size'     =&gt; getSetting('id_card_table_size','8'),
  'tb_lc'       =&gt; getSetting('id_card_table_label_color','#1a5276'),
  'tb_vc'       =&gt; getSetting('id_card_table_val_color','#333333'),
  'ar_font'     =&gt; getSetting('id_card_arabic_font','Hind Siliguri'),
  'ar_size'     =&gt; getSetting('id_card_arabic_size','7.5'),
  'ar_color'    =&gt; getSetting('id_card_arabic_color','#1a5276'),
  'bn_font'     =&gt; getSetting('id_card_bn_font','Hind Siliguri'),
  'bn_size'     =&gt; getSetting('id_card_bn_size','6.5'),
  'bn_color'    =&gt; getSetting('id_card_bn_color','#1a8a3c'),
  's_c1'        =&gt; getSetting('id_card_student_color1','#1a8a3c'),
  's_c2'        =&gt; getSetting('id_card_student_color2','#e67e22'),
  't_c1'        =&gt; getSetting('id_card_teacher_color1','#1a3a6b'),
  't_c2'        =&gt; getSetting('id_card_teacher_color2','#c9a227'),
  'sf_c1'       =&gt; getSetting('id_card_staff_color1','#5b2c8c'),
  'sf_c2'       =&gt; getSetting('id_card_staff_color2','#8e44ad'),
  'photo_bc'    =&gt; getSetting('id_card_photo_border_color','#e67e22'),
  'radius'      =&gt; getSetting('id_card_border_radius','10'),
];</pre>
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

// ===== স্ট্রিপ মোড টগল =====
function toggleStripMode() {
    var use = document.getElementById('useCustomSvg').checked;
    document.getElementById('stripColorMode').style.display = use ? 'none' : '';
    document.getElementById('stripSvgMode').style.display   = use ? '' : 'none';
    updatePreview();
}

// ===== লোগো প্রিভিউ =====
function previewLogo(input) {
    if (!input.files || !input.files[0]) return;
    var reader = new FileReader();
    reader.onload = function(e) {
        var img = document.getElementById('pvLogoImg');
        var ph  = document.getElementById('pvLogoPlaceholder');
        img.src = e.target.result;
        img.style.display = '';
        img.className = 'pv-logo';
        ph.style.display = 'none';
        // also show in upload area
        var uImg = document.getElementById('logoPreviewImg');
        if (uImg) { uImg.src = e.target.result; uImg.style.display = ''; }
    };
    reader.readAsDataURL(input.files[0]);
}
function removeLogo() {
    document.getElementById('removeLogo').value = '1';
    var img = document.getElementById('pvLogoImg');
    var ph  = document.getElementById('pvLogoPlaceholder');
    if(img){ img.style.display='none'; }
    if(ph) { ph.style.display=''; }
}

// ===== স্ট্রিপ SVG প্রিভিউ =====
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

// ===== প্রিভিউ টাইপ =====
var currentType = 'student';
function setPreviewType(type) {
    currentType = type;
    updatePreview();
}

// ===== লাইভ প্রিভিউ আপডেট =====
function getVal(name) {
    var el = document.querySelector('[name="' + name + '"]');
    return el ? el.value : '';
}
function updatePreview() {
    var useCustom = document.getElementById('useCustomSvg') && document.getElementById('useCustomSvg').checked;

    // কার্ড টাইপ অনুযায়ী রং
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

    // স্ট্রিপ রং
    var sc1 = getVal('id_card_strip_color1') || c1;
    var sc2 = getVal('id_card_strip_color2') || c2;

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

    // হেডার বর্ডার
    var pvHeader = document.getElementById('pvHeader');
    pvHeader.style.borderBottomColor = c1;

    // হেডার লোগো placeholder রং
    var ph = document.getElementById('pvLogoPlaceholder');
    if (ph) ph.style.background = 'linear-gradient(135deg,' + c1 + ',' + c2 + ')';

    // আরবি
    var ar = document.getElementById('pvArabic');
    ar.style.fontFamily = "'" + (getVal('id_card_arabic_font') || 'Hind Siliguri') + "'";
    ar.style.fontSize   = (getVal('id_card_arabic_size') || '7.5') + 'px';
    ar.style.color      = getVal('id_card_arabic_color') || '#1a5276';

    // বাংলা নাম
    var bn = document.getElementById('pvBn');
    bn.style.fontFamily = "'" + (getVal('id_card_bn_font') || 'Hind Siliguri') + "'";
    bn.style.fontSize   = (getVal('id_card_bn_size') || '6.5') + 'px';
    bn.style.color      = getVal('id_card_bn_color') || c1;

    // ছবি বর্ডার
    var pbox = document.getElementById('pvPhotoBox');
    var pbc  = getVal('id_card_photo_border_color') || c2;
    pbox.style.borderColor = pbc;
    pbox.style.color       = c1;

    // নাম
    var nf = document.getElementById('pvNameFirst');
    nf.style.fontFamily = "'" + (getVal('id_card_name_font') || 'Libre Baskerville') + "'";
    nf.style.fontSize   = (getVal('id_card_name_size') || '14') + 'px';
    nf.style.fontWeight = getVal('id_card_name_weight') || '700';
    nf.style.color      = getVal('id_card_name_color') || c1;

    // আইডি
    var idEl = document.getElementById('pvId');
    idEl.style.fontFamily = "'" + (getVal('id_card_id_font') || 'Hind Siliguri') + "'";
    idEl.style.fontSize   = (getVal('id_card_id_size') || '8.5') + 'px';
    idEl.style.color      = getVal('id_card_id_color') || '#555';

    // টেবিল
    var tFont  = getVal('id_card_table_font') || 'Hind Siliguri';
    var tSize  = (getVal('id_card_table_size') || '8') + 'px';
    var tLblC  = getVal('id_card_table_label_color') || '#1a5276';
    var tValC  = getVal('id_card_table_val_color') || '#333';
    ['pvLbl1','pvLbl2','pvLbl3'].forEach(function(id){
        var el = document.getElementById(id);
        if (!el) return;
        el.style.fontFamily = "'" + tFont + "'";
        el.style.fontSize   = tSize;
        el.style.color      = tLblC;
    });
    ['pvVal1','pvVal2','pvVal3'].forEach(function(id){
        var el = document.getElementById(id);
        if (!el) return;
        el.style.fontFamily = "'" + tFont + "'";
        el.style.fontSize   = tSize;
        el.style.color      = tValC;
    });

    // টেবিল বর্ডার
    document.getElementById('pvTable').style.borderTopColor = c1;

    // কার্ড radius
    var r = (getVal('id_card_border_radius') || '10') + 'px';
    document.getElementById('pvFront').style.borderRadius = r;
    document.getElementById('pvBack').style.borderRadius  = r;

    // শিক্ষক/স্টাফ প্রিভিউ তথ্য আপডেট
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

// পেজ লোডে একবার রান
document.addEventListener('DOMContentLoaded', function(){
    updatePreview();
    // সব color/range ইনপুটে listener
    document.querySelectorAll('input[type=color], input[type=number], select').forEach(function(el){
        el.addEventListener('change', updatePreview);
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>
