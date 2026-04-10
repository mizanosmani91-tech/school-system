<?php
/**
 * আইডি কার্ড ডিজাইন সেটিংস
 * ফাইল: modules/idcard/id_card_settings.php
 */
require_once '../../includes/functions.php';
requireLogin(['super_admin','principal']);
$pageTitle = 'আইডি কার্ড ডিজাইন সেটিংস';
$db = getDB();

// ===== DB হেল্পার =====
function idcs_get($key, $default='') {
    global $db;
    try {
        $s = $db->prepare("SELECT value FROM settings WHERE `key`=? LIMIT 1");
        $s->execute([$key]);
        $v = $s->fetchColumn();
        return ($v !== false && $v !== '') ? $v : $default;
    } catch(Exception $e) { return $default; }
}
function idcs_save($key, $value) {
    global $db;
    try {
        $c = $db->prepare("SELECT COUNT(*) FROM settings WHERE `key`=?");
        $c->execute([$key]);
        if ((int)$c->fetchColumn() > 0) {
            $db->prepare("UPDATE settings SET value=? WHERE `key`=?")->execute([$value, $key]);
        } else {
            $db->prepare("INSERT INTO settings(`key`,value) VALUES(?,?)")->execute([$key, $value]);
        }
    } catch(Exception $e) {}
}

$msg = ''; $msgType = 'success';

// ===== POST হ্যান্ডেল =====
if ($_SERVER['REQUEST_METHOD']==='POST') {

    // লোগো আপলোড
    if (!empty($_FILES['logo_file']['tmp_name'])) {
        $ext = strtolower(pathinfo($_FILES['logo_file']['name'], PATHINFO_EXTENSION));
        if (in_array($ext,['svg','png','jpg','jpeg','webp'])) {
            $mime = mime_content_type($_FILES['logo_file']['tmp_name']);
            $b64  = 'data:'.$mime.';base64,'.base64_encode(file_get_contents($_FILES['logo_file']['tmp_name']));
            idcs_save('id_card_logo_b64', $b64);
        }
    }
    if (!empty($_POST['remove_logo']) && $_POST['remove_logo']==='1') {
        idcs_save('id_card_logo_b64','');
    }

    // স্ট্রিপ SVG আপলোড
    if (!empty($_FILES['strip_file']['tmp_name'])) {
        $ext = strtolower(pathinfo($_FILES['strip_file']['name'], PATHINFO_EXTENSION));
        if ($ext==='svg') {
            idcs_save('id_card_strip_svg', file_get_contents($_FILES['strip_file']['tmp_name']));
        }
    }
    if (!empty($_POST['remove_strip']) && $_POST['remove_strip']==='1') {
        idcs_save('id_card_strip_svg','');
        idcs_save('id_card_strip_use_custom_svg','0');
    }

    // বাকি ফিল্ড
    $fields = [
        'id_card_strip_use_custom_svg','id_card_strip_color1','id_card_strip_color2',
        'id_card_label_font','id_card_label_size','id_card_label_weight','id_card_label_style','id_card_label_color','id_card_label_spacing',
        'id_card_name_font','id_card_name_size','id_card_name_weight','id_card_name_color',
        'id_card_id_font','id_card_id_size','id_card_id_color',
        'id_card_table_font','id_card_table_size','id_card_table_label_color','id_card_table_val_color',
        'id_card_arabic_font','id_card_arabic_size','id_card_arabic_color',
        'id_card_bn_font','id_card_bn_size','id_card_bn_color',
        'id_card_student_color1','id_card_student_color2',
        'id_card_teacher_color1','id_card_teacher_color2',
        'id_card_staff_color1','id_card_staff_color2',
        'id_card_photo_border_color','id_card_border_radius',
    ];
    // checkbox: use_custom_svg আলাদা handle
    idcs_save('id_card_strip_use_custom_svg', isset($_POST['id_card_strip_use_custom_svg']) ? '1' : '0');
    foreach ($fields as $f) {
        if ($f==='id_card_strip_use_custom_svg') continue;
        if (isset($_POST[$f])) idcs_save($f, trim($_POST[$f]));
    }
    $msg = '✅ সেটিংস সফলভাবে সেভ হয়েছে!';
}

// ===== বর্তমান মান =====
$cfg = [
    'logo'          => idcs_get('id_card_logo_b64',''),
    'strip_svg'     => idcs_get('id_card_strip_svg',''),
    'use_svg'       => idcs_get('id_card_strip_use_custom_svg','0'),
    'sc1'           => idcs_get('id_card_strip_color1','#1a8a3c'),
    'sc2'           => idcs_get('id_card_strip_color2','#e67e22'),
    'label_font'    => idcs_get('id_card_label_font','Hind Siliguri'),
    'label_size'    => idcs_get('id_card_label_size','9'),
    'label_w'       => idcs_get('id_card_label_weight','700'),
    'label_style'   => idcs_get('id_card_label_style','normal'),
    'label_color'   => idcs_get('id_card_label_color','#ffffff'),
    'label_ls'      => idcs_get('id_card_label_spacing','2'),
    'name_font'     => idcs_get('id_card_name_font','Libre Baskerville'),
    'name_size'     => idcs_get('id_card_name_size','14'),
    'name_w'        => idcs_get('id_card_name_weight','700'),
    'name_color'    => idcs_get('id_card_name_color','#1a8a3c'),
    'id_font'       => idcs_get('id_card_id_font','Hind Siliguri'),
    'id_size'       => idcs_get('id_card_id_size','8.5'),
    'id_color'      => idcs_get('id_card_id_color','#555555'),
    'tb_font'       => idcs_get('id_card_table_font','Hind Siliguri'),
    'tb_size'       => idcs_get('id_card_table_size','8'),
    'tb_lc'         => idcs_get('id_card_table_label_color','#1a5276'),
    'tb_vc'         => idcs_get('id_card_table_val_color','#333333'),
    'ar_font'       => idcs_get('id_card_arabic_font','Hind Siliguri'),
    'ar_size'       => idcs_get('id_card_arabic_size','7.5'),
    'ar_color'      => idcs_get('id_card_arabic_color','#1a5276'),
    'bn_font'       => idcs_get('id_card_bn_font','Hind Siliguri'),
    'bn_size'       => idcs_get('id_card_bn_size','6.5'),
    'bn_color'      => idcs_get('id_card_bn_color','#1a8a3c'),
    'st_c1'         => idcs_get('id_card_student_color1','#1a8a3c'),
    'st_c2'         => idcs_get('id_card_student_color2','#e67e22'),
    'tc_c1'         => idcs_get('id_card_teacher_color1','#1a3a6b'),
    'tc_c2'         => idcs_get('id_card_teacher_color2','#c9a227'),
    'sf_c1'         => idcs_get('id_card_staff_color1','#5b2c8c'),
    'sf_c2'         => idcs_get('id_card_staff_color2','#8e44ad'),
    'photo_bc'      => idcs_get('id_card_photo_border_color','#e67e22'),
    'radius'        => idcs_get('id_card_border_radius','10'),
];

$gFonts = [
    'Hind Siliguri'      => 'Hind Siliguri (বাংলা)',
    'Noto Serif Bengali' => 'Noto Serif Bengali',
    'Baloo Da 2'         => 'Baloo Da 2',
    'Tiro Bangla'        => 'Tiro Bangla',
    'Libre Baskerville'  => 'Libre Baskerville (Serif)',
    'Roboto'             => 'Roboto',
    'Open Sans'          => 'Open Sans',
    'Montserrat'         => 'Montserrat',
    'Poppins'            => 'Poppins',
    'Playfair Display'   => 'Playfair Display',
    'Raleway'            => 'Raleway',
    'Oswald'             => 'Oswald',
    'Lato'               => 'Lato',
];

$instName = getSetting('institute_name','আন নাজাহ তাহফিজুল কুরআন মাদরাসা');
$instAddr = getSetting('address','পান্ধোয়া বাজার, আশুলিয়া, সাভার, ঢাকা');
$instPhone= getSetting('phone','01715-821661');
$instWeb  = getSetting('website','www.annazah.com');

require_once '../../includes/header.php';
?>

<link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;600;700&family=Noto+Serif+Bengali:wght@400;700&family=Baloo+Da+2:wght@400;700&family=Libre+Baskerville:ital,wght@0,400;0,700;1,400&family=Roboto:wght@400;700&family=Open+Sans:wght@400;700&family=Montserrat:wght@400;700&family=Poppins:wght@400;700&family=Playfair+Display:wght@400;700&family=Raleway:wght@400;700&family=Oswald:wght@400;700&family=Lato:wght@400;700&display=swap" rel="stylesheet">

<style>
/* ===== PAGE LAYOUT ===== */
.ics-wrap { display:grid; grid-template-columns:1fr 450px; gap:20px; align-items:start; }
@media(max-width:1200px){ .ics-wrap { grid-template-columns:1fr; } }

/* ===== TABS ===== */
.ics-tabs { display:flex; gap:4px; flex-wrap:wrap; margin-bottom:14px; }
.ics-tab { padding:7px 14px; border-radius:7px; border:1.5px solid var(--border);
    background:#fff; cursor:pointer; font-size:13px; font-weight:600;
    color:var(--text-muted); font-family:var(--font); transition:all .2s; }
.ics-tab.on { background:var(--primary); color:#fff; border-color:var(--primary); }
.ics-pane { display:none; } .ics-pane.on { display:block; }

/* ===== FORM ===== */
.frow { display:flex; flex-wrap:wrap; gap:12px; margin-bottom:12px; }
.frow .form-group { flex:1; min-width:110px; margin:0; }
.upload-box {
    border:2px dashed var(--border); border-radius:10px; padding:16px;
    text-align:center; cursor:pointer; background:var(--bg); transition:all .2s;
}
.upload-box:hover { border-color:var(--primary); background:#ebf5fb; }
.upload-box input[type=file] { display:none; }
.upload-thumb { max-width:70px; max-height:55px; margin:8px auto 0; display:block; border-radius:4px; }

/* ===== PREVIEW PANEL ===== */
.pv-panel { position:sticky; top:80px; }
.pv-bg { background:#d0d8e4; border-radius:10px; padding:16px; }

/* পেয়ার: Back + Front পাশাপাশি */
.pv-pair {
    display:flex !important;
    flex-direction:row !important;
    flex-wrap:nowrap !important;
    gap:8px !important;
    justify-content:center;
    align-items:flex-start;
}

/* CR80 কার্ড */
.pvc {
    width:180px; height:285px;
    border-radius:9px;
    overflow:hidden;
    box-shadow:0 4px 18px rgba(0,0,0,.22);
    position:relative;
    flex-shrink:0;
    font-family:'Hind Siliguri',sans-serif;
    font-size:12px;
    color:#222;
    box-sizing:border-box;
}

/* FRONT */
.pvc-front { background:#fff; display:flex; flex-direction:row; }
.pvc-strip {
    width:26px; min-width:26px;
    position:relative; overflow:hidden;
    display:flex; align-items:center; justify-content:center; flex-shrink:0;
}
.pvc-st-a {
    position:absolute; top:0; left:0; right:0; height:55%;
    clip-path:polygon(0 0,100% 0,100% 85%,0 100%);
}
.pvc-st-b {
    position:absolute; bottom:0; left:0; right:0; height:55%;
    clip-path:polygon(0 15%,100% 0,100% 100%,0 100%);
}
.pvc-st-svg { position:absolute; inset:0; overflow:hidden; }
.pvc-st-svg svg { width:100%; height:100%; }
.pvc-st-lbl {
    position:relative; z-index:2;
    writing-mode:vertical-rl; text-orientation:mixed;
    transform:rotate(180deg); white-space:nowrap;
    text-shadow:0 1px 3px rgba(0,0,0,.5);
    font-size:8px; font-weight:700; color:#fff; letter-spacing:2px;
}
.pvc-body { flex:1; min-width:0; display:flex; flex-direction:column; padding:7px 7px 5px 5px; overflow:hidden; }
.pvc-hdr { display:flex; align-items:center; gap:4px; border-bottom:2px solid #1a8a3c; padding-bottom:4px; margin-bottom:5px; flex-shrink:0; }
.pvc-logo { width:28px; height:28px; object-fit:contain; flex-shrink:0; display:block; }
.pvc-logo-ph { width:28px; height:28px; border-radius:50%; display:flex; align-items:center; justify-content:center; color:#fff; font-size:12px; flex-shrink:0; }
.pvc-inst { flex:1; text-align:center; min-width:0; }
.pvc-ar { font-size:6.5px; font-weight:600; line-height:1.3; direction:rtl; text-align:center; }
.pvc-bn { font-size:5.5px; font-weight:700; line-height:1.3; text-align:center; }
.pvc-ph-wrap { text-align:center; margin:3px 0; flex-shrink:0; }
.pvc-ph-box { width:70px; height:83px; border:2.5px solid #e67e22; border-radius:3px; display:inline-flex; align-items:center; justify-content:center; font-size:26px; font-weight:700; background:#f0f8f0; }
.pvc-name { text-align:center; margin-top:3px; line-height:1.2; flex-shrink:0; font-size:12px; font-weight:700; }
.pvc-id { text-align:center; font-size:7.5px; font-weight:700; color:#555; margin:2px 0 3px; letter-spacing:.4px; flex-shrink:0; }
.pvc-tbl { border-top:1px dashed #1a8a3c; padding-top:3px; flex-shrink:0; }
.pvc-row { display:flex !important; font-size:7px; line-height:1.8; }
.pvc-l { width:32px; min-width:32px; font-weight:600; flex-shrink:0; }
.pvc-v { flex:1; min-width:0; overflow:hidden; white-space:nowrap; text-overflow:ellipsis; }

/* BACK */
.pvc-back { background:#fff; border:1px solid #ddd; display:flex; flex-direction:column; }
.pvc-back-wm { position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); font-size:70px; color:rgba(26,138,60,.05); pointer-events:none; }
.pvc-back-inner { padding:10px 9px 7px; display:flex; flex-direction:column; height:100%; position:relative; z-index:1; box-sizing:border-box; }
.pvc-back-h { font-size:8.5px; font-weight:700; color:#1a5276; text-align:center; margin-bottom:6px; font-family:'Libre Baskerville',serif; }
.pvc-back-p { font-size:5.5px; color:#444; line-height:1.65; text-align:justify; flex:1; }
.pvc-back-bot { border-top:1px solid #e67e22; padding-top:6px; margin-top:7px; display:flex; flex-direction:column; gap:4px; }
.pvc-qr-row { display:flex; align-items:center; justify-content:space-between; }
.pvc-qr { background:#fff8f0; border:1px solid #e67e22; border-radius:3px; padding:2px; }
.pvc-sig { text-align:center; }
.pvc-sig-ln { width:50px; border-top:1px solid #333; margin:0 auto 2px; }
.pvc-sig-tx { font-size:5.5px; color:#555; }
.pvc-addr { font-size:5.5px; color:#444; text-align:center; line-height:1.55; }
</style>

<div class="section-header">
    <h2 class="section-title"><i class="fas fa-palette"></i> আইডি কার্ড ডিজাইন সেটিংস</h2>
    <a href="id_card.php" class="btn btn-outline btn-sm"><i class="fas fa-eye"></i> কার্ড দেখুন</a>
</div>

<?php if($msg): ?>
<div class="alert alert-<?= $msgType ?>" style="margin-bottom:16px;"><i class="fas fa-check-circle"></i> <?= $msg ?></div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data">
<div class="ics-wrap">

<!-- ===== বাম: সেটিংস ===== -->
<div>
    <div class="ics-tabs">
        <button type="button" class="ics-tab on" onclick="icsTab('logo',this)"><i class="fas fa-image"></i> লোগো</button>
        <button type="button" class="ics-tab" onclick="icsTab('strip',this)"><i class="fas fa-grip-lines-vertical"></i> স্ট্রিপ</button>
        <button type="button" class="ics-tab" onclick="icsTab('fonts',this)"><i class="fas fa-font"></i> ফন্ট</button>
        <button type="button" class="ics-tab" onclick="icsTab('colors',this)"><i class="fas fa-fill-drip"></i> রং</button>
    </div>

    <!-- ===== লোগো ===== -->
    <div class="ics-pane on" id="pane-logo">
        <div class="card mb-16">
            <div class="card-header"><span class="card-title"><i class="fas fa-image"></i> লোগো ইমেজ</span></div>
            <div class="card-body">
                <p style="font-size:12px;color:var(--text-muted);margin-bottom:10px;">SVG/PNG/JPG/WebP — কার্ডের উপরে বামে ৩২×৩২px বক্সে বসবে।</p>
                <div class="upload-box" onclick="document.getElementById('logo_file').click()">
                    <i class="fas fa-cloud-upload-alt" style="font-size:26px;color:var(--primary-light);margin-bottom:6px;display:block;"></i>
                    <div style="font-size:13px;font-weight:600;">লোগো আপলোড</div>
                    <div style="font-size:11px;color:var(--text-muted);">SVG / PNG / JPG / WebP</div>
                    <input type="file" id="logo_file" name="logo_file" accept=".svg,.png,.jpg,.jpeg,.webp" onchange="prevLogo(this)">
                    <img id="logoThumb" src="<?= $cfg['logo'] ?>" class="upload-thumb" style="<?= $cfg['logo']?'':'display:none;' ?>" alt="">
                </div>
                <?php if($cfg['logo']): ?>
                <div style="margin-top:8px;text-align:right;">
                    <label style="font-size:12px;cursor:pointer;color:var(--danger);">
                        <input type="checkbox" name="remove_logo" value="1"> লোগো সরিয়ে দিন
                    </label>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card mb-16">
            <div class="card-header"><span class="card-title">হেডার — আরবি লেখা</span></div>
            <div class="card-body">
                <div class="frow">
                    <div class="form-group"><label>ফন্ট</label>
                        <select name="id_card_arabic_font" class="form-control" onchange="upPrev()">
                            <?php foreach($gFonts as $v=>$l): ?><option value="<?=$v?>" <?=$cfg['ar_font']===$v?'selected':''?>><?=$l?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label>সাইজ (px)</label>
                        <input type="number" name="id_card_arabic_size" class="form-control" value="<?=e($cfg['ar_size'])?>" min="5" max="18" step=".5" oninput="upPrev()">
                    </div>
                    <div class="form-group"><label>রং</label>
                        <input type="color" name="id_card_arabic_color" class="form-control" value="<?=e($cfg['ar_color'])?>" oninput="upPrev()">
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-16">
            <div class="card-header"><span class="card-title">হেডার — বাংলা নাম</span></div>
            <div class="card-body">
                <div class="frow">
                    <div class="form-group"><label>ফন্ট</label>
                        <select name="id_card_bn_font" class="form-control" onchange="upPrev()">
                            <?php foreach($gFonts as $v=>$l): ?><option value="<?=$v?>" <?=$cfg['bn_font']===$v?'selected':''?>><?=$l?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label>সাইজ (px)</label>
                        <input type="number" name="id_card_bn_size" class="form-control" value="<?=e($cfg['bn_size'])?>" min="5" max="16" step=".5" oninput="upPrev()">
                    </div>
                    <div class="form-group"><label>রং</label>
                        <input type="color" name="id_card_bn_color" class="form-control" value="<?=e($cfg['bn_color'])?>" oninput="upPrev()">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== স্ট্রিপ ===== -->
    <div class="ics-pane" id="pane-strip">
        <div class="card mb-16">
            <div class="card-header"><span class="card-title"><i class="fas fa-grip-lines-vertical"></i> সাইড স্ট্রিপ ডিজাইন</span></div>
            <div class="card-body">
                <label style="display:flex;align-items:center;gap:8px;margin-bottom:14px;cursor:pointer;">
                    <input type="checkbox" name="id_card_strip_use_custom_svg" id="useCustomSvg" value="1" <?=$cfg['use_svg']==='1'?'checked':''?> onchange="toggleSvgMode()">
                    <span style="font-size:14px;font-weight:600;">কাস্টম SVG শেপ ব্যবহার করব</span>
                </label>

                <div id="stripColorMode" <?=$cfg['use_svg']==='1'?'style="display:none"':''?>>
                    <p style="font-size:12px;color:var(--text-muted);margin-bottom:10px;">Diagonal শেপের দুটি রং:</p>
                    <div class="frow">
                        <div class="form-group"><label>উপরের রং</label>
                            <input type="color" name="id_card_strip_color1" class="form-control" value="<?=e($cfg['sc1'])?>" oninput="upPrev()">
                        </div>
                        <div class="form-group"><label>নিচের রং</label>
                            <input type="color" name="id_card_strip_color2" class="form-control" value="<?=e($cfg['sc2'])?>" oninput="upPrev()">
                        </div>
                    </div>
                </div>

                <div id="stripSvgMode" <?=$cfg['use_svg']!=='1'?'style="display:none"':''?>>
                    <p style="font-size:12px;color:var(--text-muted);margin-bottom:8px;">SVG ফাইল আপলোড করুন <strong>(viewBox="0 0 30 323")</strong></p>
                    <div class="upload-box" onclick="document.getElementById('strip_file').click()">
                        <i class="fas fa-bezier-curve" style="font-size:24px;color:var(--primary-light);margin-bottom:6px;display:block;"></i>
                        <div style="font-size:13px;font-weight:600;">স্ট্রিপ SVG আপলোড</div>
                        <div style="font-size:11px;color:var(--text-muted);">শুধু .svg ফাইল</div>
                        <input type="file" id="strip_file" name="strip_file" accept=".svg" onchange="prevStripSvg(this)">
                    </div>
                    <?php if($cfg['strip_svg']): ?>
                    <div style="margin-top:8px;font-size:12px;color:var(--success);"><i class="fas fa-check-circle"></i> কাস্টম SVG লোড আছে</div>
                    <label style="font-size:12px;cursor:pointer;color:var(--danger);margin-top:4px;display:block;">
                        <input type="checkbox" name="remove_strip" value="1"> SVG সরিয়ে default এ ফিরে যান
                    </label>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="card mb-16">
            <div class="card-header"><span class="card-title">স্ট্রিপ লেবেল ("STUDENT ID CARD")</span></div>
            <div class="card-body">
                <div class="frow">
                    <div class="form-group"><label>ফন্ট</label>
                        <select name="id_card_label_font" class="form-control" onchange="upPrev()">
                            <?php foreach($gFonts as $v=>$l): ?><option value="<?=$v?>" <?=$cfg['label_font']===$v?'selected':''?>><?=$l?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label>সাইজ (px)</label>
                        <input type="number" name="id_card_label_size" class="form-control" value="<?=e($cfg['label_size'])?>" min="5" max="16" step=".5" oninput="upPrev()">
                    </div>
                </div>
                <div class="frow">
                    <div class="form-group"><label>ওজন</label>
                        <select name="id_card_label_weight" class="form-control" onchange="upPrev()">
                            <option value="400" <?=$cfg['label_w']==='400'?'selected':''?>>Normal</option>
                            <option value="600" <?=$cfg['label_w']==='600'?'selected':''?>>Semi Bold</option>
                            <option value="700" <?=$cfg['label_w']==='700'?'selected':''?>>Bold</option>
                            <option value="900" <?=$cfg['label_w']==='900'?'selected':''?>>Black</option>
                        </select>
                    </div>
                    <div class="form-group"><label>স্টাইল</label>
                        <select name="id_card_label_style" class="form-control" onchange="upPrev()">
                            <option value="normal" <?=$cfg['label_style']==='normal'?'selected':''?>>Normal</option>
                            <option value="italic" <?=$cfg['label_style']==='italic'?'selected':''?>>Italic</option>
                        </select>
                    </div>
                    <div class="form-group"><label>রং</label>
                        <input type="color" name="id_card_label_color" class="form-control" value="<?=e($cfg['label_color'])?>" oninput="upPrev()">
                    </div>
                    <div class="form-group"><label>স্পেসিং</label>
                        <input type="number" name="id_card_label_spacing" class="form-control" value="<?=e($cfg['label_ls'])?>" min="0" max="10" step=".5" oninput="upPrev()">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== ফন্ট ===== -->
    <div class="ics-pane" id="pane-fonts">
        <div class="card mb-16">
            <div class="card-header"><span class="card-title">নাম ফন্ট</span></div>
            <div class="card-body">
                <div class="frow">
                    <div class="form-group"><label>ফন্ট</label>
                        <select name="id_card_name_font" class="form-control" onchange="upPrev()">
                            <?php foreach($gFonts as $v=>$l): ?><option value="<?=$v?>" <?=$cfg['name_font']===$v?'selected':''?>><?=$l?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label>সাইজ</label>
                        <input type="number" name="id_card_name_size" class="form-control" value="<?=e($cfg['name_size'])?>" min="8" max="22" step=".5" oninput="upPrev()">
                    </div>
                    <div class="form-group"><label>ওজন</label>
                        <select name="id_card_name_weight" class="form-control" onchange="upPrev()">
                            <option value="400" <?=$cfg['name_w']==='400'?'selected':''?>>Normal</option>
                            <option value="600" <?=$cfg['name_w']==='600'?'selected':''?>>Semi Bold</option>
                            <option value="700" <?=$cfg['name_w']==='700'?'selected':''?>>Bold</option>
                        </select>
                    </div>
                    <div class="form-group"><label>রং</label>
                        <input type="color" name="id_card_name_color" class="form-control" value="<?=e($cfg['name_color'])?>" oninput="upPrev()">
                    </div>
                </div>
            </div>
        </div>
        <div class="card mb-16">
            <div class="card-header"><span class="card-title">আইডি নম্বর ফন্ট</span></div>
            <div class="card-body">
                <div class="frow">
                    <div class="form-group"><label>ফন্ট</label>
                        <select name="id_card_id_font" class="form-control" onchange="upPrev()">
                            <?php foreach($gFonts as $v=>$l): ?><option value="<?=$v?>" <?=$cfg['id_font']===$v?'selected':''?>><?=$l?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label>সাইজ</label>
                        <input type="number" name="id_card_id_size" class="form-control" value="<?=e($cfg['id_size'])?>" min="5" max="14" step=".5" oninput="upPrev()">
                    </div>
                    <div class="form-group"><label>রং</label>
                        <input type="color" name="id_card_id_color" class="form-control" value="<?=e($cfg['id_color'])?>" oninput="upPrev()">
                    </div>
                </div>
            </div>
        </div>
        <div class="card mb-16">
            <div class="card-header"><span class="card-title">তথ্য সারি ফন্ট (Class/Roll/Blood)</span></div>
            <div class="card-body">
                <div class="frow">
                    <div class="form-group"><label>ফন্ট</label>
                        <select name="id_card_table_font" class="form-control" onchange="upPrev()">
                            <?php foreach($gFonts as $v=>$l): ?><option value="<?=$v?>" <?=$cfg['tb_font']===$v?'selected':''?>><?=$l?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label>সাইজ</label>
                        <input type="number" name="id_card_table_size" class="form-control" value="<?=e($cfg['tb_size'])?>" min="5" max="12" step=".5" oninput="upPrev()">
                    </div>
                    <div class="form-group"><label>লেবেল রং</label>
                        <input type="color" name="id_card_table_label_color" class="form-control" value="<?=e($cfg['tb_lc'])?>" oninput="upPrev()">
                    </div>
                    <div class="form-group"><label>মান রং</label>
                        <input type="color" name="id_card_table_val_color" class="form-control" value="<?=e($cfg['tb_vc'])?>" oninput="upPrev()">
                    </div>
                </div>
            </div>
        </div>
        <div class="card mb-16">
            <div class="card-header"><span class="card-title">ছবি ও কার্ড</span></div>
            <div class="card-body">
                <div class="frow">
                    <div class="form-group"><label>ছবির বর্ডার রং</label>
                        <input type="color" name="id_card_photo_border_color" class="form-control" value="<?=e($cfg['photo_bc'])?>" oninput="upPrev()">
                    </div>
                    <div class="form-group"><label>কোণা গোলত্ব (px)</label>
                        <input type="number" name="id_card_border_radius" class="form-control" value="<?=e($cfg['radius'])?>" min="0" max="25" oninput="upPrev()">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== রং ===== -->
    <div class="ics-pane" id="pane-colors">
        <div class="card mb-16">
            <div class="card-header"><span class="card-title"><i class="fas fa-user-graduate"></i> ছাত্র কার্ড</span></div>
            <div class="card-body">
                <div class="frow">
                    <div class="form-group"><label>প্রাইমারি রং</label>
                        <input type="color" name="id_card_student_color1" class="form-control" value="<?=e($cfg['st_c1'])?>" oninput="upPrev()">
                    </div>
                    <div class="form-group"><label>সেকেন্ডারি রং</label>
                        <input type="color" name="id_card_student_color2" class="form-control" value="<?=e($cfg['st_c2'])?>" oninput="upPrev()">
                    </div>
                </div>
            </div>
        </div>
        <div class="card mb-16">
            <div class="card-header"><span class="card-title"><i class="fas fa-chalkboard-teacher"></i> শিক্ষক কার্ড</span></div>
            <div class="card-body">
                <div class="frow">
                    <div class="form-group"><label>প্রাইমারি রং</label>
                        <input type="color" name="id_card_teacher_color1" class="form-control" value="<?=e($cfg['tc_c1'])?>" oninput="upPrev()">
                    </div>
                    <div class="form-group"><label>সেকেন্ডারি রং</label>
                        <input type="color" name="id_card_teacher_color2" class="form-control" value="<?=e($cfg['tc_c2'])?>" oninput="upPrev()">
                    </div>
                </div>
            </div>
        </div>
        <div class="card mb-16">
            <div class="card-header"><span class="card-title"><i class="fas fa-user-tie"></i> স্টাফ কার্ড</span></div>
            <div class="card-body">
                <div class="frow">
                    <div class="form-group"><label>প্রাইমারি রং</label>
                        <input type="color" name="id_card_staff_color1" class="form-control" value="<?=e($cfg['sf_c1'])?>" oninput="upPrev()">
                    </div>
                    <div class="form-group"><label>সেকেন্ডারি রং</label>
                        <input type="color" name="id_card_staff_color2" class="form-control" value="<?=e($cfg['sf_c2'])?>" oninput="upPrev()">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- সেভ বাটন -->
    <div style="display:flex;gap:10px;margin-top:4px;">
        <button type="submit" class="btn btn-primary" style="flex:1;"><i class="fas fa-save"></i> সেটিংস সেভ করুন</button>
        <a href="id_card.php" class="btn btn-outline"><i class="fas fa-times"></i> বাতিল</a>
    </div>
</div><!-- /বাম -->

<!-- ===== ডান: লাইভ প্রিভিউ ===== -->
<div class="pv-panel">
    <div class="card">
        <div class="card-header" style="flex-wrap:wrap;gap:8px;">
            <span class="card-title"><i class="fas fa-eye"></i> লাইভ প্রিভিউ</span>
            <div style="display:flex;gap:5px;">
                <button type="button" class="btn btn-outline btn-sm" onclick="pvType('student')">ছাত্র</button>
                <button type="button" class="btn btn-outline btn-sm" onclick="pvType('teacher')">শিক্ষক</button>
                <button type="button" class="btn btn-outline btn-sm" onclick="pvType('staff')">স্টাফ</button>
            </div>
        </div>
        <div class="card-body" style="padding:12px;background:#c8d0dc;">
            <!-- Back + Front পাশাপাশি -->
            <div class="pv-pair">

                <!-- BACK -->
                <div class="pvc pvc-back" id="pvBack">
                    <div class="pvc-back-wm"><i class="fas fa-mosque"></i></div>
                    <div class="pvc-back-inner">
                        <div class="pvc-back-h">Terms and Condition</div>
                        <p class="pvc-back-p">This ID card must be brought and worn whenever the student attends the madrasah. If this card is lost, the student or guardian must inform the office immediately. If anyone finds this card, please return it to the madrasah. Misuse or altering this card is strictly prohibited.</p>
                        <div class="pvc-back-bot" id="pvBackBot">
                            <div class="pvc-qr-row">
                                <div class="pvc-qr" id="pvQrBox">
                                    <svg viewBox="0 0 100 100" width="46" height="46" xmlns="http://www.w3.org/2000/svg">
                                        <rect x="5" y="5" width="35" height="35" rx="3" fill="none" stroke="#e67e22" stroke-width="4" id="pvQr1a"/>
                                        <rect x="12" y="12" width="21" height="21" rx="1" fill="#e67e22" id="pvQr1b"/>
                                        <rect x="60" y="5" width="35" height="35" rx="3" fill="none" stroke="#e67e22" stroke-width="4" id="pvQr2a"/>
                                        <rect x="67" y="12" width="21" height="21" rx="1" fill="#e67e22" id="pvQr2b"/>
                                        <rect x="5" y="60" width="35" height="35" rx="3" fill="none" stroke="#e67e22" stroke-width="4" id="pvQr3a"/>
                                        <rect x="12" y="67" width="21" height="21" rx="1" fill="#e67e22" id="pvQr3b"/>
                                        <rect x="55" y="55" width="8" height="8" fill="#333"/>
                                        <rect x="67" y="55" width="8" height="8" fill="#333"/>
                                        <rect x="79" y="55" width="8" height="8" fill="#333"/>
                                        <rect x="55" y="67" width="8" height="8" fill="#333"/>
                                        <rect x="79" y="67" width="8" height="8" fill="#333"/>
                                        <rect x="55" y="79" width="8" height="8" fill="#333"/>
                                        <rect x="67" y="79" width="8" height="8" fill="#333"/>
                                    </svg>
                                </div>
                                <div class="pvc-sig"><div class="pvc-sig-ln"></div><div class="pvc-sig-tx">Principal's Signature</div></div>
                            </div>
                            <div class="pvc-addr">
                                <div><?= e($instAddr) ?></div>
                                <div style="font-weight:700;">Mobile: <?= e($instPhone) ?></div>
                                <div style="font-weight:700;"><?= e($instWeb) ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- FRONT -->
                <div class="pvc pvc-front" id="pvFront">
                    <div class="pvc-strip" id="pvStrip">
                        <div class="pvc-st-a" id="pvStA"></div>
                        <div class="pvc-st-b" id="pvStB"></div>
                        <div class="pvc-st-svg" id="pvStSvg" style="display:none;"></div>
                        <div class="pvc-st-lbl" id="pvLbl">STUDENT ID CARD</div>
                    </div>
                    <div class="pvc-body">
                        <div class="pvc-hdr" id="pvHdr">
                            <?php if($cfg['logo']): ?>
                            <img src="<?=$cfg['logo']?>" class="pvc-logo" id="pvLogoImg" alt="">
                            <div class="pvc-logo-ph" id="pvLogoPh" style="display:none;">🕌</div>
                            <?php else: ?>
                            <div style="display:none;" id="pvLogoImg"></div>
                            <div class="pvc-logo-ph" id="pvLogoPh">🕌</div>
                            <?php endif; ?>
                            <div class="pvc-inst">
                                <div class="pvc-ar" id="pvAr">مدرسة النجاح لتحفيظ القرآن</div>
                                <div class="pvc-bn" id="pvBn"><?=e($instName)?></div>
                            </div>
                        </div>
                        <div class="pvc-ph-wrap">
                            <div class="pvc-ph-box" id="pvPhBox">ক</div>
                        </div>
                        <div class="pvc-name" id="pvName">
                            <span id="pvNF">Rakika Rahman</span><span id="pvNL" style="font-weight:400;color:#444;"> Toha</span>
                        </div>
                        <div class="pvc-id" id="pvId">ID: ANT-2026-NP4X</div>
                        <div class="pvc-tbl" id="pvTbl">
                            <div class="pvc-row"><span class="pvc-l" id="pvL1">Class</span><span class="pvc-v" id="pvV1">:দ্বিতীয় শ্রেণী</span></div>
                            <div class="pvc-row"><span class="pvc-l" id="pvL2">Roll</span><span class="pvc-v" id="pvV2">:১</span></div>
                            <div class="pvc-row"><span class="pvc-l" id="pvL3">Blood</span><span class="pvc-v" id="pvV3">:O+</span></div>
                        </div>
                    </div>
                </div>

            </div><!-- /pv-pair -->
            <div style="text-align:center;margin-top:8px;font-size:11px;color:#667;font-family:sans-serif;">← পেছন &nbsp;|&nbsp; সামনে →</div>
        </div>
    </div>
</div><!-- /ডান -->

</div><!-- /ics-wrap -->
</form>

<script>
// ===== ট্যাব =====
function icsTab(name, btn) {
    document.querySelectorAll('.ics-pane').forEach(function(p){ p.classList.remove('on'); });
    document.querySelectorAll('.ics-tab').forEach(function(b){ b.classList.remove('on'); });
    document.getElementById('pane-'+name).classList.add('on');
    btn.classList.add('on');
}

// ===== স্ট্রিপ মোড =====
function toggleSvgMode() {
    var on = document.getElementById('useCustomSvg').checked;
    document.getElementById('stripColorMode').style.display = on ? 'none' : '';
    document.getElementById('stripSvgMode').style.display   = on ? '' : 'none';
    upPrev();
}

// ===== লোগো প্রিভিউ =====
function prevLogo(inp) {
    if (!inp.files || !inp.files[0]) return;
    var r = new FileReader();
    r.onload = function(e) {
        var img = document.getElementById('pvLogoImg');
        var ph  = document.getElementById('pvLogoPh');
        if (img.tagName==='IMG') { img.src=e.target.result; img.style.display=''; } else { img.style.display='none'; }
        if (ph) ph.style.display='none';
        // thumb
        var th = document.getElementById('logoThumb');
        if (th) { th.src=e.target.result; th.style.display=''; }
    };
    r.readAsDataURL(inp.files[0]);
}

// ===== Strip SVG প্রিভিউ =====
function prevStripSvg(inp) {
    if (!inp.files || !inp.files[0]) return;
    var r = new FileReader();
    r.onload = function(e) {
        var wrap = document.getElementById('pvStSvg');
        wrap.innerHTML = e.target.result;
        wrap.style.display = '';
        document.getElementById('pvStA').style.display='none';
        document.getElementById('pvStB').style.display='none';
    };
    r.readAsText(inp.files[0]);
}

// ===== Preview Type =====
var _pvType = 'student';
function pvType(t) { _pvType=t; upPrev(); }

// ===== Live Update =====
function gv(name) {
    var el = document.querySelector('[name="'+name+'"]');
    return el ? el.value : '';
}
function upPrev() {
    var useCustom = document.getElementById('useCustomSvg') && document.getElementById('useCustomSvg').checked;

    // রং নির্ধারণ
    var c1, c2;
    if (_pvType==='teacher') {
        c1 = gv('id_card_teacher_color1') || '#1a3a6b';
        c2 = gv('id_card_teacher_color2') || '#c9a227';
    } else if (_pvType==='staff') {
        c1 = gv('id_card_staff_color1') || '#5b2c8c';
        c2 = gv('id_card_staff_color2') || '#8e44ad';
    } else {
        c1 = gv('id_card_student_color1') || '#1a8a3c';
        c2 = gv('id_card_student_color2') || '#e67e22';
    }
    var sc1 = gv('id_card_strip_color1') || c1;
    var sc2 = gv('id_card_strip_color2') || c2;

    // কার্ড radius
    var R = (gv('id_card_border_radius')||'10')+'px';
    document.getElementById('pvFront').style.borderRadius = R;
    document.getElementById('pvBack').style.borderRadius  = R;

    // স্ট্রিপ
    if (!useCustom) {
        var a=document.getElementById('pvStA'), b=document.getElementById('pvStB');
        if(a){a.style.background=sc1; a.style.display='';}
        if(b){b.style.background=sc2; b.style.display='';}
        document.getElementById('pvStSvg').style.display='none';
    }

    // লেবেল
    var lbl=document.getElementById('pvLbl');
    lbl.style.fontFamily   = "'"+( gv('id_card_label_font')||'Hind Siliguri')+"',sans-serif";
    lbl.style.fontSize     = (gv('id_card_label_size')||'9')+'px';
    lbl.style.fontWeight   = gv('id_card_label_weight')||'700';
    lbl.style.fontStyle    = gv('id_card_label_style')||'normal';
    lbl.style.color        = gv('id_card_label_color')||'#fff';
    lbl.style.letterSpacing= (gv('id_card_label_spacing')||'2')+'px';
    var labels={student:'STUDENT ID CARD',teacher:'TEACHER ID CARD',staff:'STAFF ID CARD'};
    lbl.textContent = labels[_pvType]||'STUDENT ID CARD';

    // হেডার
    var hdr=document.getElementById('pvHdr');
    hdr.style.borderBottomColor = c1;
    var ph=document.getElementById('pvLogoPh');
    if(ph) ph.style.background='linear-gradient(135deg,'+c1+','+c2+')';

    // আরবি
    var ar=document.getElementById('pvAr');
    ar.style.fontFamily=  "'"+( gv('id_card_arabic_font')||'Hind Siliguri')+"'";
    ar.style.fontSize  = (gv('id_card_arabic_size')||'7.5')+'px';
    ar.style.color     = gv('id_card_arabic_color')||c1;

    // বাংলা নাম
    var bn=document.getElementById('pvBn');
    bn.style.fontFamily=  "'"+( gv('id_card_bn_font')||'Hind Siliguri')+"'";
    bn.style.fontSize  = (gv('id_card_bn_size')||'6.5')+'px';
    bn.style.color     = gv('id_card_bn_color')||c1;

    // ছবি
    var pb=document.getElementById('pvPhBox');
    pb.style.borderColor= gv('id_card_photo_border_color')||c2;
    pb.style.color      = c1;

    // নাম
    var nf=document.getElementById('pvNF');
    nf.style.fontFamily= "'"+( gv('id_card_name_font')||'Libre Baskerville')+"',serif";
    nf.style.fontSize  = (gv('id_card_name_size')||'14')+'px';
    nf.style.fontWeight= gv('id_card_name_weight')||'700';
    nf.style.color     = gv('id_card_name_color')||c1;

    // ID
    var id=document.getElementById('pvId');
    id.style.fontFamily= "'"+( gv('id_card_id_font')||'Hind Siliguri')+"'";
    id.style.fontSize  = (gv('id_card_id_size')||'8.5')+'px';
    id.style.color     = gv('id_card_id_color')||'#555';

    // টেবিল
    var tf=gv('id_card_table_font')||'Hind Siliguri';
    var ts=(gv('id_card_table_size')||'8')+'px';
    var tlc=gv('id_card_table_label_color')||'#1a5276';
    var tvc=gv('id_card_table_val_color')||'#333';
    document.getElementById('pvTbl').style.borderTopColor=c1;
    ['pvL1','pvL2','pvL3'].forEach(function(id){
        var e=document.getElementById(id); if(!e)return;
        e.style.fontFamily="'"+tf+"'"; e.style.fontSize=ts; e.style.color=tlc;
    });
    ['pvV1','pvV2','pvV3'].forEach(function(id){
        var e=document.getElementById(id); if(!e)return;
        e.style.fontFamily="'"+tf+"'"; e.style.fontSize=ts; e.style.color=tvc;
    });

    // Back bottom border ও QR রং
    var bb=document.getElementById('pvBackBot');
    if(bb) bb.style.borderTopColor=c2;
    var qb=document.getElementById('pvQrBox');
    if(qb) { qb.style.borderColor=c2; qb.style.background='#fff'; }
    ['pvQr1a','pvQr2a','pvQr3a'].forEach(function(id){
        var e=document.getElementById(id); if(e){ e.setAttribute('stroke',c2); }
    });
    ['pvQr1b','pvQr2b','pvQr3b'].forEach(function(id){
        var e=document.getElementById(id); if(e){ e.setAttribute('fill',c2); }
    });

    // type অনুযায়ী তথ্য লেবেল
    if(_pvType==='teacher'){
        document.getElementById('pvL1').textContent='পদবী'; document.getElementById('pvV1').textContent=':হেড শিক্ষক';
        document.getElementById('pvL2').textContent='Phone'; document.getElementById('pvV2').textContent=':01700-000000';
        document.getElementById('pvId').textContent='ID: TCH-2026-001';
    } else if(_pvType==='staff'){
        document.getElementById('pvL1').textContent='পদবী'; document.getElementById('pvV1').textContent=':অফিস সহকারী';
        document.getElementById('pvL2').textContent='Phone'; document.getElementById('pvV2').textContent=':01800-000000';
        document.getElementById('pvId').textContent='ID: STF-2026-001';
    } else {
        document.getElementById('pvL1').textContent='Class'; document.getElementById('pvV1').textContent=':দ্বিতীয় শ্রেণী';
        document.getElementById('pvL2').textContent='Roll';  document.getElementById('pvV2').textContent=':১';
        document.getElementById('pvId').textContent='ID: ANT-2026-NP4X';
    }
}

document.addEventListener('DOMContentLoaded', function(){
    upPrev();
    document.querySelectorAll('input[type=color],input[type=number],select').forEach(function(el){
        el.addEventListener('change', upPrev);
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>
