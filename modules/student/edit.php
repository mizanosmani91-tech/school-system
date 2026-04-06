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
               'status','hifz_para_complete','notes',
               'monthly_fee','is_hostel','hostel_fee','is_hostel_food','food_fee'];
    $sets=[]; $vals=[];
    foreach ($fields as $f) {
        $sets[] = "$f=?";
        $vals[] = trim($_POST[$f]??'') ?: null;
    }

    // হোস্টেল চেকবক্স — না থাকলে 0
    $isHostel     = isset($_POST['is_hostel']) ? 1 : 0;
    $isHostelFood = ($isHostel && isset($_POST['is_hostel_food'])) ? 1 : 0;
    $hostelFee    = $isHostel ? (float)($_POST['hostel_fee'] ?? 0) : 0;
    $foodFee      = $isHostelFood ? (float)($_POST['food_fee'] ?? 0) : 0;

    $sets=[]; $vals=[];
    $simpleFields = ['name_bn','name','date_of_birth','gender','religion','blood_group','class_id','section_id',
                     'roll_number',
                     'father_name','father_phone','mother_name','guardian_phone','address_present',
                     'status','hifz_para_complete','notes'];
    foreach ($simpleFields as $f) {
        $sets[] = "$f=?";
        $vals[] = trim($_POST[$f]??'') ?: null;
    }
    // ফি ফিল্ড আলাদাভাবে
    $sets[] = 'monthly_fee=?';    $vals[] = (float)($_POST['monthly_fee'] ?? 0);
    $sets[] = 'is_hostel=?';      $vals[] = $isHostel;
    $sets[] = 'hostel_fee=?';     $vals[] = $hostelFee;
    $sets[] = 'is_hostel_food=?'; $vals[] = $isHostelFood;
    $sets[] = 'food_fee=?';       $vals[] = $foodFee;
    // Photo upload — Cloudinary (deploy তে photo টিকে থাকে)
    if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === 0) {
        $allowedTypes = ['image/jpeg','image/jpg','image/png','image/gif','image/webp'];
        $mimeType = mime_content_type($_FILES['photo']['tmp_name']);
        if (!in_array($mimeType, $allowedTypes)) {
            setFlash('danger', 'শুধু JPG, PNG, GIF বা WebP ছবি upload করুন।');
            header("Location: edit.php?id=$id"); exit;
        }
        if ($_FILES['photo']['size'] > 5 * 1024 * 1024) {
            setFlash('danger', 'ছবির সাইজ ৫MB এর বেশি হবে না।');
            header("Location: edit.php?id=$id"); exit;
        }
        require_once '../../includes/cloudinary_upload.php';
        $cloudUrl = uploadToCloudinary($_FILES['photo']['tmp_name'], 'students/' . $student['student_id']);
        if ($cloudUrl) {
            $sets[] = 'photo=?'; $vals[] = $cloudUrl; // Cloudinary URL database এ save হবে
        } else {
            setFlash('danger', 'ছবি upload ব্যর্থ হয়েছে। আবার চেষ্টা করুন।');
            header("Location: edit.php?id=$id"); exit;
        }
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
<form method="POST" enctype="multipart/form-data">
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
            <div class="form-group"><label>রোল নম্বর</label>
                <input type="number" name="roll_number" class="form-control" min="1" value="<?=e($student['roll_number']??'')?>"></div>
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
        <div class="form-group mt-16"><label>ছবি (পাসপোর্ট সাইজ)</label>
            <?php if (!empty($student['photo'])): ?>
            <div style="margin-bottom:8px;">
                <img src="<?=BASE_URL?>/assets/uploads/<?=e($student['photo'])?>" 
                     style="width:80px;height:103px;object-fit:cover;border-radius:6px;border:1px solid #e2e8f0;">
                <div style="font-size:11px;color:#718096;margin-top:4px;">বর্তমান ছবি</div>
            </div>
            <?php endif; ?>
            <input type="file" name="photo" class="form-control" accept="image/*">
            <div style="font-size:11px;color:#718096;margin-top:4px;">নতুন ছবি দিলে পুরানোটা বদলে যাবে। সর্বোচ্চ ৫MB।</div>
        </div>
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
<div class="card mb-16">
    <div class="card-header" style="background:#f4ecf7;">
        <span class="card-title" style="color:#7d3c98;"><i class="fas fa-building"></i> হোস্টেল তথ্য</span>
    </div>
    <div class="card-body">
        <!-- হোস্টেল -->
        <div>
            <label style="display:flex;align-items:center;gap:10px;font-weight:600;cursor:pointer;">
                <input type="checkbox" name="is_hostel" id="isHostelCheck" onchange="toggleHostel(this)"
                    style="width:18px;height:18px;" <?=!empty($student['is_hostel'])&&$student['is_hostel']?'checked':''?>>
                হোস্টেলে থাকে
            </label>
        </div>

        <div id="hostelFields" style="display:<?=!empty($student['is_hostel'])&&$student['is_hostel']?'block':'none'?>;margin-top:16px;padding:16px;background:#faf4ff;border-radius:10px;border:1px dashed #c39bd3;">
            <div class="form-grid">
                <div class="form-group">
                    <label>হোস্টেল ফি (টাকা/মাস)</label>
                    <input type="number" name="hostel_fee" id="hostelFee" class="form-control" min="0" step="0.01"
                        value="<?=e($student['hostel_fee'] ?? 0)?>">
                </div>
            </div>
            <div style="margin-top:12px;">
                <label style="display:flex;align-items:center;gap:10px;font-weight:600;cursor:pointer;">
                    <input type="checkbox" name="is_hostel_food" id="isHostelFoodCheck" onchange="toggleFood(this)"
                        style="width:18px;height:18px;" <?=!empty($student['is_hostel_food'])&&$student['is_hostel_food']?'checked':''?>>
                    হোস্টেলের খাবার খায়
                </label>
            </div>
            <div id="foodFields" style="display:<?=!empty($student['is_hostel_food'])&&$student['is_hostel_food']?'block':'none'?>;margin-top:12px;">
                <div class="form-grid">
                    <div class="form-group">
                        <label>খাবার ফি (টাকা/মাস)</label>
                        <input type="number" name="food_fee" id="foodFee" class="form-control" min="0" step="0.01"
                            value="<?=e($student['food_fee'] ?? 0)?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- ফী নির্ধারণ লিংক -->
        <div style="margin-top:16px;padding:12px 16px;background:#fff8f0;border-radius:8px;border-left:4px solid #e67e22;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
            <div>
                <div style="font-weight:600;font-size:14px;color:#e67e22;"><i class="fas fa-tags"></i> ব্যক্তিগত ফী নির্ধারণ</div>
                <div style="font-size:12px;color:#718096;margin-top:2px;">টিউশন, লাইব্রেরি বা অন্য ফী আলাদাভাবে নির্ধারণ করতে প্রোফাইলে যান।</div>
            </div>
            <a href="view.php?id=<?=$id?>" class="btn btn-sm" style="background:#e67e22;color:#fff;">
                <i class="fas fa-tags"></i> ফী নির্ধারণ করুন
            </a>
        </div>
    </div>
</div>

<div style="display:flex;gap:10px;">
    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> আপডেট করুন</button>
    <a href="view.php?id=<?=$id?>" class="btn btn-outline">বাতিল</a>
</div>
</form>
<script>
function toggleHostel(cb) {
    document.getElementById('hostelFields').style.display = cb.checked ? 'block' : 'none';
    if (!cb.checked) {
        document.getElementById('isHostelFoodCheck').checked = false;
        document.getElementById('foodFields').style.display = 'none';
        document.getElementById('hostelFee').value = 0;
        document.getElementById('foodFee').value = 0;
    }
}

function toggleFood(cb) {
    document.getElementById('foodFields').style.display = cb.checked ? 'block' : 'none';
    if (!cb.checked) document.getElementById('foodFee').value = 0;
}

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
