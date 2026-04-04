<?php
require_once '../../includes/functions.php';
requireLogin(['super_admin','principal']);
$pageTitle = 'ছাত্রের তথ্য সম্পাদনা';
$db = getDB();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: list.php'); exit; }
$stmt = $db->prepare("SELECT * FROM students WHERE id=?");
$stmt->execute([$id]); $student = $stmt->fetch();
if (!$student) { setFlash('danger','পাওয়া যায়নি।'); header('Location: list.php'); exit; }

$classes = $db->query("SELECT * FROM classes WHERE is_active=1 ORDER BY class_numeric")->fetchAll();
$sections = $db->prepare("SELECT * FROM sections WHERE class_id=?");
$sections->execute([$student['class_id']]); $currentSections = $sections->fetchAll();

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['update_student'])) {
    if (!verifyCsrf($_POST['csrf']??'')) die('CSRF');
    $fields = ['name_bn','name','date_of_birth','gender','religion','blood_group','class_id','section_id',
               'father_name','father_phone','mother_name','guardian_phone','address_present',
               'status','hifz_para_complete','notes'];
    $sets=[]; $vals=[];
    foreach ($fields as $f) {
        $sets[] = "$f=?";
        $vals[] = trim($_POST[$f]??'') ?: null;
    }
    $vals[] = $id;
    $db->prepare("UPDATE students SET ".implode(',',$sets)." WHERE id=?")->execute($vals);
    setFlash('success','তথ্য আপডেট হয়েছে।');
    header("Location: view.php?id=$id"); exit;
}
require_once '../../includes/header.php';
?>
<div class="section-header">
    <h2 class="section-title"><i class="fas fa-edit"></i> ছাত্রের তথ্য সম্পাদনা</h2>
    <a href="view.php?id=<?=$id?>" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> ফিরুন</a>
</div>
<form method="POST">
<input type="hidden" name="csrf" value="<?=getCsrfToken()?>">
<input type="hidden" name="update_student" value="1">
<div class="card mb-16">
    <div class="card-header"><span class="card-title">ব্যক্তিগত তথ্য</span></div>
    <div class="card-body">
        <div class="form-grid">
            <div class="form-group"><label>নাম (বাংলায়)</label>
                <input type="text" name="name_bn" class="form-control" value="<?=e($student['name_bn'])?>"></div>
            <div class="form-group"><label>নাম (ইংরেজি)</label>
                <input type="text" name="name" class="form-control" value="<?=e($student['name'])?>"></div>
            <div class="form-group"><label>জন্ম তারিখ</label>
                <input type="date" name="date_of_birth" class="form-control" value="<?=e($student['date_of_birth'])?>"></div>
            <div class="form-group"><label>লিঙ্গ</label>
                <select name="gender" class="form-control">
                    <option value="male" <?=$student['gender']==='male'?'selected':''?>>ছেলে</option>
                    <option value="female" <?=$student['gender']==='female'?'selected':''?>>মেয়ে</option>
                </select></div>
            <div class="form-group"><label>ধর্ম</label>
                <select name="religion" class="form-control">
                    <?php foreach(['islam'=>'ইসলাম','hinduism'=>'হিন্দু','christianity'=>'খ্রিস্টান','buddhism'=>'বৌদ্ধ'] as $v=>$l): ?>
                    <option value="<?=$v?>" <?=$student['religion']===$v?'selected':''?>><?=$l?></option>
                    <?php endforeach; ?>
                </select></div>
            <div class="form-group"><label>রক্তের গ্রুপ</label>
                <select name="blood_group" class="form-control">
                    <option value="">অজানা</option>
                    <?php foreach(['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bg): ?>
                    <option <?=$student['blood_group']===$bg?'selected':''?>><?=$bg?></option>
                    <?php endforeach; ?>
                </select></div>
            <div class="form-group"><label>শ্রেণী</label>
                <select name="class_id" class="form-control" onchange="loadSections(this.value)">
                    <?php foreach($classes as $c): ?>
                    <option value="<?=$c['id']?>" <?=$student['class_id']==$c['id']?'selected':''?>><?=e($c['class_name_bn'])?></option>
                    <?php endforeach; ?>
                </select></div>
            <div class="form-group"><label>শাখা</label>
                <select name="section_id" class="form-control" id="sectionSelect">
                    <?php foreach($currentSections as $sec): ?>
                    <option value="<?=$sec['id']?>" <?=$student['section_id']==$sec['id']?'selected':''?>><?=e($sec['section_name'])?></option>
                    <?php endforeach; ?>
                </select></div>
            <div class="form-group"><label>অবস্থা</label>
                <select name="status" class="form-control">
                    <?php foreach(['active'=>'সক্রিয়','inactive'=>'নিষ্ক্রিয়','passed'=>'উত্তীর্ণ','transferred'=>'বদলি'] as $v=>$l): ?>
                    <option value="<?=$v?>" <?=$student['status']===$v?'selected':''?>><?=$l?></option>
                    <?php endforeach; ?>
                </select></div>
            <div class="form-group"><label>হিফজ সম্পন্ন পারা</label>
                <input type="number" name="hifz_para_complete" class="form-control" min="0" max="30" value="<?=e($student['hifz_para_complete']??0)?>"></div>
        </div>
        <div class="form-group mt-16"><label>ঠিকানা</label>
            <textarea name="address_present" class="form-control" rows="2"><?=e($student['address_present']??'')?></textarea></div>
    </div>
</div>
<div class="card mb-16">
    <div class="card-header"><span class="card-title">অভিভাবকের তথ্য</span></div>
    <div class="card-body">
        <div class="form-grid">
            <div class="form-group"><label>পিতার নাম</label>
                <input type="text" name="father_name" class="form-control" value="<?=e($student['father_name']??'')?>"></div>
            <div class="form-group"><label>পিতার ফোন</label>
                <input type="tel" name="father_phone" class="form-control" value="<?=e($student['father_phone']??'')?>"></div>
            <div class="form-group"><label>মাতার নাম</label>
                <input type="text" name="mother_name" class="form-control" value="<?=e($student['mother_name']??'')?>"></div>
            <div class="form-group"><label>অভিভাবকের ফোন</label>
                <input type="tel" name="guardian_phone" class="form-control" value="<?=e($student['guardian_phone']??'')?>"></div>
        </div>
    </div>
</div>
<div class="card mb-16">
    <div class="card-header"><span class="card-title">অতিরিক্ত নোট</span></div>
    <div class="card-body">
        <textarea name="notes" class="form-control" rows="3"><?=e($student['notes']??'')?></textarea>
    </div>
</div>
<div style="display:flex;gap:10px;">
    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> আপডেট করুন</button>
    <a href="view.php?id=<?=$id?>" class="btn btn-outline">বাতিল</a>
</div>
</form>
<script>
function loadSections(classId) {
    fetch('<?=BASE_URL?>/api/sections.php?class_id='+classId)
    .then(r=>r.json()).then(data=>{
        const sel=document.getElementById('sectionSelect');
        sel.innerHTML='<option value="">সেকশন নির্বাচন করুন</option>';
        data.forEach(s=>{ sel.innerHTML+=`<option value="${s.id}">${s.section_name}</option>`; });
    });
}
</script>
<?php require_once '../../includes/footer.php'; ?>
