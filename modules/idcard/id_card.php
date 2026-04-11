<?php
require_once '../../includes/functions.php';
requireLogin(['super_admin','principal']);
$pageTitle = 'আইডি কার্ড জেনারেটর';
$db = getDB();

$divisionId  = (int)($_GET['division_id'] ?? 0);
$filterClass = (int)($_GET['class_id'] ?? 0);
$filterIds   = $_GET['ids'] ?? '';
$type        = $_GET['type'] ?? 'student';
$printMode   = isset($_GET['print']);

// সব বিভাগ
$divisions = $db->query("SELECT * FROM divisions WHERE is_active=1 ORDER BY sort_order, id")->fetchAll();

// শ্রেণী — বিভাগ অনুযায়ী
if ($divisionId) {
    $clsStmt = $db->prepare("SELECT c.*, d.division_name_bn FROM classes c LEFT JOIN divisions d ON c.division_id=d.id WHERE c.is_active=1 AND c.division_id=? ORDER BY c.class_numeric");
    $clsStmt->execute([$divisionId]);
    $classes = $clsStmt->fetchAll();
} else {
    $classes = $db->query("SELECT c.*, d.division_name_bn FROM classes c LEFT JOIN divisions d ON c.division_id=d.id WHERE c.is_active=1 ORDER BY d.sort_order, c.class_numeric")->fetchAll();
}

// ===== ডেটা লোড =====
$people = [];
if ($type === 'teacher') {
    if ($filterIds) {
        $ids = array_map('intval', explode(',', $filterIds));
        $ph  = implode(',', array_fill(0, count($ids), '?'));
        $st  = $db->prepare("SELECT *,teacher_id_no AS student_id,designation_bn,phone AS father_phone,blood_group,'' AS class_name_bn,'' AS section_name,'' AS roll_number FROM teachers WHERE id IN($ph) AND is_active=1 ORDER BY name_bn");
        $st->execute($ids); $people = $st->fetchAll();
    } else {
        $people = $db->query("SELECT *,teacher_id_no AS student_id,designation_bn,phone AS father_phone,blood_group,'' AS class_name_bn,'' AS section_name,'' AS roll_number FROM teachers WHERE is_active=1 ORDER BY name_bn")->fetchAll();
    }
} elseif ($type === 'staff') {
    if ($filterIds) {
        $ids = array_map('intval', explode(',', $filterIds));
        $ph  = implode(',', array_fill(0, count($ids), '?'));
        $st  = $db->prepare("SELECT *,staff_id AS student_id,designation_bn,phone AS father_phone,blood_group,'' AS class_name_bn,'' AS section_name,'' AS roll_number FROM staff WHERE id IN($ph) AND is_active=1 ORDER BY name_bn");
        $st->execute($ids); $people = $st->fetchAll();
    } else {
        $people = $db->query("SELECT *,staff_id AS student_id,designation_bn,phone AS father_phone,blood_group,'' AS class_name_bn,'' AS section_name,'' AS roll_number FROM staff WHERE is_active=1 ORDER BY name_bn")->fetchAll();
    }
} else {
    // ছাত্র
    if ($filterIds) {
        $ids = array_map('intval', explode(',', $filterIds));
        $ph  = implode(',', array_fill(0, count($ids), '?'));
        $st  = $db->prepare("SELECT s.*,c.class_name_bn,c.class_name,sec.section_name,d.division_name_bn FROM students s LEFT JOIN classes c ON s.class_id=c.id LEFT JOIN sections sec ON s.section_id=sec.id LEFT JOIN divisions d ON s.division_id=d.id WHERE s.id IN($ph) AND s.status='active' ORDER BY s.roll_number");
        $st->execute($ids); $people = $st->fetchAll();
    } elseif ($filterClass) {
        $st = $db->prepare("SELECT s.*,c.class_name_bn,c.class_name,sec.section_name,d.division_name_bn FROM students s LEFT JOIN classes c ON s.class_id=c.id LEFT JOIN sections sec ON s.section_id=sec.id LEFT JOIN divisions d ON s.division_id=d.id WHERE s.class_id=? AND s.status='active' ORDER BY s.roll_number");
        $st->execute([$filterClass]); $people = $st->fetchAll();
    } elseif ($divisionId) {
        // বিভাগ বাছা হলে সেই বিভাগের সব ছাত্র
        $st = $db->prepare("SELECT s.*,c.class_name_bn,c.class_name,sec.section_name,d.division_name_bn FROM students s LEFT JOIN classes c ON s.class_id=c.id LEFT JOIN sections sec ON s.section_id=sec.id LEFT JOIN divisions d ON s.division_id=d.id WHERE s.division_id=? AND s.status='active' ORDER BY c.class_numeric, s.roll_number");
        $st->execute([$divisionId]); $people = $st->fetchAll();
    }
}

// ===== প্রতিষ্ঠান তথ্য =====
$instituteName    = getSetting('institute_name','আন নাজাহ তাহফিজুল কুরআন মাদরাসা');
$instituteAddress = getSetting('address','পান্ধোয়া বাজার, আশুলিয়া, সাভার, ঢাকা');
$institutePhone   = getSetting('phone','01715-821661');
$instituteWeb     = getSetting('website','www.annazah.com');

// ===== ডিজাইন সেটিংস =====
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
];
if ($type==='teacher')   { $idc['c1']=$idc['t_c1'];  $idc['c2']=$idc['t_c2'];  }
elseif ($type==='staff') { $idc['c1']=$idc['sf_c1']; $idc['c2']=$idc['sf_c2']; }
else                     { $idc['c1']=$idc['s_c1'];  $idc['c2']=$idc['s_c2'];  }
$R = (int)$idc['radius'];

require_once '../../includes/header.php';
?>

<?php if (!$printMode): ?>
<div class="section-header no-print">
    <h2 class="section-title"><i class="fas fa-id-card"></i> আইডি কার্ড জেনারেটর</h2>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a href="id_card_settings.php" class="btn btn-outline btn-sm"><i class="fas fa-palette"></i> ডিজাইন সেটিংস</a>
        <?php if (!empty($people)): ?>
        <button onclick="window.print()" class="btn btn-primary"><i class="fas fa-print"></i> প্রিন্ট / PDF</button>
        <?php endif; ?>
    </div>
</div>

<!-- বিভাগ Quick-Tab (শুধু ছাত্রের জন্য) -->
<?php if ($type === 'student'): ?>
<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px;" class="no-print">
    <a href="id_card.php?type=student" class="btn btn-sm <?= !$divisionId ? 'btn-primary' : 'btn-outline' ?>">
        <i class="fas fa-layer-group"></i> সব বিভাগ
    </a>
    <?php foreach($divisions as $dv): ?>
    <a href="id_card.php?type=student&division_id=<?=$dv['id']?>" class="btn btn-sm <?= $divisionId==$dv['id'] ? 'btn-primary' : 'btn-outline' ?>">
        <?=e($dv['division_name_bn'])?>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="card mb-16 no-print">
    <div class="card-body" style="padding:16px 20px;">
        <form method="GET" id="filterForm">
            <div style="display:flex;flex-wrap:wrap;gap:14px;align-items:flex-end;">

                <!-- ধরন -->
                <div class="form-group" style="margin:0;flex:1;min-width:150px;">
                    <label style="font-size:12px;">ধরন</label>
                    <select name="type" class="form-control" style="padding:8px;" onchange="onTypeChange(this)">
                        <option value="student" <?= $type==='student'?'selected':'' ?>>ছাত্র</option>
                        <option value="teacher" <?= $type==='teacher'?'selected':'' ?>>শিক্ষক</option>
                        <option value="staff"   <?= $type==='staff'  ?'selected':'' ?>>স্টাফ</option>
                    </select>
                </div>

                <!-- বিভাগ (শুধু ছাত্রের জন্য) -->
                <div class="form-group" style="margin:0;flex:1;min-width:140px;" id="divisionDiv" <?= $type!=='student'?'style="display:none"':'' ?>>
                    <label style="font-size:12px;font-weight:600;">বিভাগ</label>
                    <select name="division_id" class="form-control" style="padding:8px;" onchange="onDivisionChange(this.value)">
                        <option value="">সব বিভাগ</option>
                        <?php foreach($divisions as $dv): ?>
                        <option value="<?=$dv['id']?>" <?=$divisionId==$dv['id']?'selected':''?>><?=e($dv['division_name_bn'])?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- শ্রেণী (শুধু ছাত্রের জন্য) -->
                <div class="form-group" style="margin:0;flex:1;min-width:150px;" id="classDiv" <?= $type!=='student'?'style="display:none"':'' ?>>
                    <label style="font-size:12px;">শ্রেণী</label>
                    <select name="class_id" class="form-control" style="padding:8px;" onchange="this.form.submit()">
                        <option value="">সব শ্রেণী</option>
                        <?php foreach($classes as $c): ?>
                        <option value="<?=$c['id']?>" <?=$filterClass==$c['id']?'selected':''?>>
                            <?php if(!$divisionId): ?><?=e($c['division_name_bn']??'')?> → <?php endif; ?>
                            <?=e($c['class_name_bn'])?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </form>

        <?php if (!empty($people) && (($type==='student' && ($filterClass||$divisionId)) || $type!=='student')): ?>
        <div style="margin-top:12px;display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
            <span style="font-size:13px;color:var(--text-muted);">নির্বাচন:</span>
            <button onclick="selAll()" class="btn btn-outline btn-sm">সবাই</button>
            <button onclick="selNone()" class="btn btn-outline btn-sm">কেউ না</button>
            <button onclick="genSelected()" class="btn btn-primary btn-sm"><i class="fas fa-id-card"></i> নির্বাচিতদের কার্ড</button>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($people) && (($type==='student' && ($filterClass||$divisionId)) || $type!=='student')): ?>
<div class="card mb-16 no-print">
    <div class="card-header">
        <span class="card-title">মোট <?=toBanglaNumber(count($people))?> জন <?=$type==='teacher'?'শিক্ষক':($type==='staff'?'স্টাফ':'ছাত্র')?></span>
    </div>
    <div class="card-body" style="padding:12px 20px;">
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:8px;">
        <?php foreach($people as $s): ?>
            <label style="display:flex;align-items:center;gap:8px;padding:8px;border:1px solid var(--border);border-radius:8px;cursor:pointer;">
                <input type="checkbox" class="pchk" value="<?=$s['id']?>" checked>
                <div>
                    <div style="font-size:13px;font-weight:600;"><?=e($s['name_bn']?:$s['name'])?></div>
                    <div style="font-size:11px;color:var(--text-muted);"><?=e($s['student_id']??'')?></div>
                </div>
            </label>
        <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>
<?php endif; // !printMode ?>

<?php if (empty($people) && !$printMode): ?>
<div class="card no-print"><div class="card-body" style="text-align:center;padding:48px;color:var(--text-muted);">
    <i class="fas fa-id-card" style="font-size:48px;margin-bottom:16px;opacity:.3;display:block;"></i>
    <p style="font-size:16px;"><?=$type==='student'?'বিভাগ বা শ্রেণী নির্বাচন করুন':'কোনো তথ্য পাওয়া যায়নি'?></p>
</div></div>
<?php endif; ?>

<?php if (!empty($people)): ?>
<div id="idc-container">
<?php foreach($people as $s):
    $name     = $s['name_bn'] ?: $s['name'];
    $parts    = explode(' ', trim($s['name'] ?? ''), 2);
    $fn       = $parts[0] ?? '';
    $ln       = $parts[1] ?? '';
    $rawPhoto = $s['photo'] ?? '';
    $photoUrl = '';
    if ($rawPhoto && strpos($rawPhoto,'http')===0) $photoUrl=$rawPhoto;
    elseif ($rawPhoto) $photoUrl=BASE_URL.'/assets/uploads/'.$rawPhoto;
    $cls   = $s['class_name_bn'] ?? '';
    $sec   = $s['section_name']  ?? '';
    $roll  = $s['roll_number']   ?? '';
    $blood = $s['blood_group']   ?? '';
    $sid   = $s['student_id']    ?? '';
    $ph    = $s['father_phone']  ?? $s['guardian_phone'] ?? '';
    $des   = $s['designation_bn']?? '';
    $lbl   = $type==='teacher'?'TEACHER ID CARD':($type==='staff'?'STAFF ID CARD':'STUDENT ID CARD');
?>
<div class="idc-pair">

  <!-- BACK -->
  <div class="idc idc-back" style="border-radius:<?=$R?>px;">
    <div class="idc-wm"><i class="fas fa-mosque"></i></div>
    <div class="idc-back-inner">
      <div class="idc-back-h">Terms and Condition</div>
      <p class="idc-back-p">This ID card must be brought and worn whenever the student attends the madrasah. If this card is lost, the student or guardian must inform the office immediately. If anyone finds this card, please return it to An Nazah Tahfizul Quran Madrasah. Misuse, lending, or altering this card in any way is strictly prohibited.</p>
      <div class="idc-back-bot" style="border-top-color:<?=$idc['c2']?>;">
        <div class="idc-qr-row">
          <div class="idc-qr" style="border-color:<?=$idc['c2']?>;">
            <svg viewBox="0 0 100 100" width="54" height="54" xmlns="http://www.w3.org/2000/svg">
              <rect x="5"  y="5"  width="35" height="35" rx="3" fill="none" stroke="<?=$idc['c2']?>" stroke-width="4"/>
              <rect x="12" y="12" width="21" height="21" rx="1" fill="<?=$idc['c2']?>"/>
              <rect x="60" y="5"  width="35" height="35" rx="3" fill="none" stroke="<?=$idc['c2']?>" stroke-width="4"/>
              <rect x="67" y="12" width="21" height="21" rx="1" fill="<?=$idc['c2']?>"/>
              <rect x="5"  y="60" width="35" height="35" rx="3" fill="none" stroke="<?=$idc['c2']?>" stroke-width="4"/>
              <rect x="12" y="67" width="21" height="21" rx="1" fill="<?=$idc['c2']?>"/>
              <rect x="55" y="55" width="8" height="8" fill="#333"/><rect x="67" y="55" width="8" height="8" fill="#333"/>
              <rect x="79" y="55" width="8" height="8" fill="#333"/><rect x="55" y="67" width="8" height="8" fill="#333"/>
              <rect x="79" y="67" width="8" height="8" fill="#333"/><rect x="55" y="79" width="8" height="8" fill="#333"/>
              <rect x="67" y="79" width="8" height="8" fill="#333"/><rect x="91" y="55" width="8" height="8" fill="#333"/>
              <rect x="91" y="79" width="8" height="8" fill="#333"/><rect x="55" y="91" width="8" height="8" fill="#333"/>
              <rect x="79" y="91" width="8" height="8" fill="#333"/>
            </svg>
          </div>
          <div class="idc-sig"><div class="idc-sig-ln"></div><div class="idc-sig-tx">Principal's Signature</div></div>
        </div>
        <div class="idc-addr">
          <div><?=e($instituteAddress)?></div>
          <div style="font-weight:700;margin-top:3px;">Mobile: <?=e($institutePhone)?></div>
          <div style="font-weight:700;"><?=e($instituteWeb)?></div>
        </div>
      </div>
    </div>
  </div>

  <!-- FRONT -->
  <div class="idc idc-front" style="border-radius:<?=$R?>px;">
    <div class="idc-strip">
      <?php if ($idc['use_svg']==='1' && $idc['strip_svg']): ?>
        <div class="idc-strip-svg"><?=$idc['strip_svg']?></div>
      <?php else: ?>
        <div class="idc-strip-a" style="background:<?=$idc['sc1']?>;"></div>
        <div class="idc-strip-b" style="background:<?=$idc['sc2']?>;"></div>
      <?php endif; ?>
      <div class="idc-strip-lbl" style="font-family:'<?=e($idc['label_font'])?>',sans-serif;font-size:<?=e($idc['label_size'])?>px;font-weight:<?=e($idc['label_w'])?>;font-style:<?=e($idc['label_style'])?>;color:<?=e($idc['label_color'])?>;letter-spacing:<?=e($idc['label_ls'])?>px;"><?=$lbl?></div>
    </div>
    <div class="idc-body">
      <div class="idc-hdr" style="border-bottom-color:<?=$idc['c1']?>;">
        <?php if ($idc['logo']): ?>
          <img src="<?=$idc['logo']?>" class="idc-logo" alt="logo">
        <?php else: ?>
          <div class="idc-logo-ph" style="background:linear-gradient(135deg,<?=$idc['c1']?>,<?=$idc['c2']?>);"><i class="fas fa-mosque"></i></div>
        <?php endif; ?>
        <div class="idc-inst">
          <div class="idc-ar" style="font-family:'<?=e($idc['ar_font'])?>',sans-serif;font-size:<?=e($idc['ar_size'])?>px;color:<?=e($idc['ar_color'])?>;">مدرسة النجاح لتحفيظ القرآن</div>
          <div class="idc-bn" style="font-family:'<?=e($idc['bn_font'])?>',sans-serif;font-size:<?=e($idc['bn_size'])?>px;color:<?=e($idc['bn_color'])?>;"><?=e($instituteName)?></div>
        </div>
      </div>
      <div class="idc-ph-wrap">
        <?php if ($photoUrl): ?>
          <img src="<?=e($photoUrl)?>" class="idc-ph" style="border-color:<?=$idc['photo_bc']?>;" alt="">
        <?php else: ?>
          <div class="idc-ph-x" style="border-color:<?=$idc['photo_bc']?>;color:<?=$idc['c1']?>;"><?=mb_substr($name,0,1)?></div>
        <?php endif; ?>
      </div>
      <div class="idc-name">
        <span style="font-family:'<?=e($idc['name_font'])?>',serif;font-size:<?=e($idc['name_size'])?>px;font-weight:<?=e($idc['name_w'])?>;color:<?=e($idc['name_color'])?>;"><?=e($fn?:$name)?></span><?php if($ln): ?><span style="font-family:'<?=e($idc['name_font'])?>',serif;font-size:<?=e($idc['name_size'])?>px;font-weight:400;color:#444;"> <?=e($ln)?></span><?php endif; ?>
      </div>
      <div class="idc-id" style="font-family:'<?=e($idc['id_font'])?>',sans-serif;font-size:<?=e($idc['id_size'])?>px;color:<?=e($idc['id_color'])?>;">ID: <?=e($sid)?></div>
      <div class="idc-tbl" style="border-top-color:<?=$idc['c1']?>;">
        <?php
        $rs="font-family:'{$idc['tb_font']}',sans-serif;font-size:{$idc['tb_size']}px;";
        $ls="color:{$idc['tb_lc']};font-weight:600;";
        $vs="color:{$idc['tb_vc']};";
        if ($type==='teacher'||$type==='staff'):?>
          <div class="idc-row" style="<?=$rs?>"><span class="idc-l" style="<?=$ls?>">পদবী</span><span class="idc-v" style="<?=$vs?>">:<?=e($des?:'-')?></span></div>
          <div class="idc-row" style="<?=$rs?>"><span class="idc-l" style="<?=$ls?>">ID</span><span class="idc-v" style="<?=$vs?>">:<?=e($sid)?></span></div>
          <div class="idc-row" style="<?=$rs?>"><span class="idc-l" style="<?=$ls?>">Phone</span><span class="idc-v" style="<?=$vs?>">:<?=e($ph)?></span></div>
          <div class="idc-row" style="<?=$rs?>"><span class="idc-l" style="<?=$ls?>">Blood</span><span class="idc-v" style="<?=$vs?>">:<?=e($blood?:'N/A')?></span></div>
        <?php else:?>
          <div class="idc-row" style="<?=$rs?>"><span class="idc-l" style="<?=$ls?>">Class</span><span class="idc-v" style="<?=$vs?>">:<?=e($cls)?></span></div>
          <?php if($sec):?><div class="idc-row" style="<?=$rs?>"><span class="idc-l" style="<?=$ls?>">Group</span><span class="idc-v" style="<?=$vs?>">:<?=e($sec)?></span></div><?php endif;?>
          <div class="idc-row" style="<?=$rs?>"><span class="idc-l" style="<?=$ls?>">Roll</span><span class="idc-v" style="<?=$vs?>">:<?=e($roll)?></span></div>
          <div class="idc-row" style="<?=$rs?>"><span class="idc-l" style="<?=$ls?>">Blood</span><span class="idc-v" style="<?=$vs?>">:<?=e($blood?:'N/A')?></span></div>
        <?php endif;?>
      </div>
    </div>
  </div>

</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;600;700&family=Libre+Baskerville:wght@400;700&display=swap');
#idc-container { display:flex; flex-direction:row; flex-wrap:wrap; gap:20px; margin-top:20px; align-items:flex-start; }
.idc-pair { display:flex; flex-direction:row; flex-wrap:nowrap; gap:8px; align-items:flex-start; }
.idc { display:block; width:204px; height:323px; overflow:hidden; box-shadow:0 4px 18px rgba(0,0,0,.2); position:relative; flex-shrink:0; box-sizing:border-box; font-family:'Hind Siliguri',sans-serif; font-size:13px; line-height:1.4; color:#222; }
.idc-front { background:#fff; display:flex; flex-direction:row; }
.idc-strip { width:30px; min-width:30px; max-width:30px; height:323px; position:relative; overflow:hidden; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.idc-strip-a { position:absolute; top:0; left:0; right:0; height:55%; clip-path:polygon(0 0,100% 0,100% 85%,0 100%); }
.idc-strip-b { position:absolute; bottom:0; left:0; right:0; height:55%; clip-path:polygon(0 15%,100% 0,100% 100%,0 100%); }
.idc-strip-svg { position:absolute; inset:0; overflow:hidden; }
.idc-strip-svg svg { width:100%; height:100%; }
.idc-strip-lbl { position:relative; z-index:2; writing-mode:vertical-rl; text-orientation:mixed; transform:rotate(180deg); white-space:nowrap; text-shadow:0 1px 3px rgba(0,0,0,.5); font-size:9px; font-weight:700; color:#fff; letter-spacing:2px; }
.idc-body { flex:1; min-width:0; display:flex; flex-direction:column; padding:8px 7px 6px 5px; overflow:hidden; }
.idc-hdr { display:flex; align-items:center; gap:5px; border-bottom:2px solid #1a8a3c; padding-bottom:5px; margin-bottom:6px; flex-shrink:0; }
.idc-logo { width:32px; height:32px; object-fit:contain; flex-shrink:0; display:block; }
.idc-logo-ph { width:32px; height:32px; border-radius:50%; display:flex; align-items:center; justify-content:center; color:#fff; font-size:14px; flex-shrink:0; }
.idc-inst { flex:1; text-align:center; min-width:0; }
.idc-ar { font-size:7.5px; font-weight:600; line-height:1.3; direction:rtl; text-align:center; }
.idc-bn { font-size:6.5px; font-weight:700; line-height:1.3; text-align:center; }
.idc-ph-wrap { text-align:center; margin:3px 0; flex-shrink:0; }
.idc-ph { width:80px; height:95px; object-fit:cover; border:3px solid #e67e22; border-radius:4px; display:inline-block; }
.idc-ph-x { width:80px; height:95px; background:#f0f8f0; border:3px solid #e67e22; border-radius:4px; display:inline-flex; align-items:center; justify-content:center; font-size:30px; font-weight:700; }
.idc-name { text-align:center; margin-top:4px; line-height:1.25; flex-shrink:0; }
.idc-id { text-align:center; font-size:8.5px; font-weight:700; color:#555; margin:2px 0 4px; letter-spacing:.5px; flex-shrink:0; }
.idc-tbl { border-top:1px dashed #1a8a3c; padding-top:4px; flex-shrink:0; }
.idc-row { display:flex !important; align-items:baseline; line-height:1.85; font-size:8px; padding:0 !important; margin:0 !important; border:0 !important; background:transparent !important; }
.idc-l { width:36px; min-width:36px; font-weight:600; font-size:inherit; flex-shrink:0; }
.idc-v { flex:1; min-width:0; font-size:inherit; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.idc-back { background:#fff; border:1px solid #ddd; display:flex; flex-direction:column; }
.idc-wm { position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); font-size:86px; color:rgba(26,138,60,.05); pointer-events:none; z-index:0; }
.idc-back-inner { padding:12px 10px 8px; display:flex; flex-direction:column; height:100%; position:relative; z-index:1; box-sizing:border-box; }
.idc-back-h { font-size:10px; font-weight:700; color:#1a5276; text-align:center; margin-bottom:7px; font-family:'Libre Baskerville',serif; }
.idc-back-p { font-size:6.5px; color:#444; line-height:1.7; text-align:justify; flex:1; }
.idc-back-bot { border-top:1px solid #e67e22; padding-top:7px; margin-top:8px; display:flex; flex-direction:column; gap:5px; }
.idc-qr-row { display:flex; align-items:center; justify-content:space-between; }
.idc-qr { background:#fff8f0; border:1px solid #e67e22; border-radius:4px; padding:3px; }
.idc-sig { text-align:center; }
.idc-sig-ln { width:60px; border-top:1px solid #333; margin:0 auto 2px; }
.idc-sig-tx { font-size:6px; color:#555; }
.idc-addr { font-size:6.5px; color:#444; text-align:center; line-height:1.65; }
@media print {
    .no-print,.sidebar,.topbar,header,nav { display:none !important; }
    .main-wrapper { margin-left:0 !important; }
    .content { padding:0 !important; }
    body,html { margin:0; padding:0; background:#fff; }
    #idc-container { display:flex !important; flex-wrap:wrap !important; gap:6mm !important; padding:5mm !important; margin:0 !important; }
    .idc-pair { display:flex !important; flex-direction:row !important; flex-wrap:nowrap !important; gap:4mm !important; break-inside:avoid; }
    .idc { box-shadow:none !important; border:1px solid #bbb !important; -webkit-print-color-adjust:exact !important; print-color-adjust:exact !important; }
}
@page { size:A4 portrait; margin:8mm; }
</style>

<script>
function onTypeChange(sel) {
    var cd = document.getElementById('classDiv');
    var dd = document.getElementById('divisionDiv');
    var isStudent = sel.value === 'student';
    if(cd) cd.style.display = isStudent ? '' : 'none';
    if(dd) dd.style.display = isStudent ? '' : 'none';
    var cs = document.querySelector('[name="class_id"]');
    var ds = document.querySelector('[name="division_id"]');
    if(cs) cs.value = '';
    if(ds) ds.value = '';
    sel.form.submit();
}
function onDivisionChange(divId) {
    var p = new URLSearchParams(window.location.search);
    if(divId) { p.set('division_id', divId); } else { p.delete('division_id'); }
    p.delete('class_id');
    p.delete('ids');
    window.location.href = '?' + p.toString();
}
function selAll()  { document.querySelectorAll('.pchk').forEach(function(c){c.checked=true;}); }
function selNone() { document.querySelectorAll('.pchk').forEach(function(c){c.checked=false;}); }
function genSelected() {
    var ids = [];
    document.querySelectorAll('.pchk:checked').forEach(function(c){ids.push(c.value);});
    if(!ids.length){alert('কমপক্ষে একজন নির্বাচন করুন।');return;}
    var p = new URLSearchParams(window.location.search);
    p.set('ids', ids.join(','));
    p.delete('class_id');
    window.location.href = '?' + p.toString();
}
</script>

<?php require_once '../../includes/footer.php'; ?>
