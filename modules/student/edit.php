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
    // Photo upload — Cloudinary
    $photoCroppedB64 = trim($_POST['photo_cropped'] ?? '');
    if ($photoCroppedB64 && strpos($photoCroppedB64, 'data:image/') === 0) {
        $b64Data  = preg_replace('/^data:image\/\w+;base64,/', '', $photoCroppedB64);
        $imgBytes = base64_decode($b64Data);
        if ($imgBytes !== false && strlen($imgBytes) > 0) {
            $tmpPath = tempnam(sys_get_temp_dir(), 'photo_');
            file_put_contents($tmpPath, $imgBytes);
            require_once '../../includes/cloudinary_upload.php';
            $cloudUrl = uploadToCloudinary($tmpPath, 'students/' . $student['student_id']);
            unlink($tmpPath);
            if ($cloudUrl) {
                $sets[] = 'photo=?'; $vals[] = $cloudUrl;
            } else {
                setFlash('danger', 'ছবি upload ব্যর্থ হয়েছে। আবার চেষ্টা করুন।');
                header("Location: edit.php?id=$id"); exit;
            }
        }
    } elseif (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === 0) {
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
            $sets[] = 'photo=?'; $vals[] = $cloudUrl;
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

            <input type="hidden" name="photo_cropped" id="photoCroppedData">

            <?php
            $curPhoto = $student['photo'] ?? '';
            $curPhotoUrl = '';
            if ($curPhoto) {
                $curPhotoUrl = (strpos($curPhoto,'http') === 0) ? $curPhoto : BASE_URL.'/assets/uploads/'.e($curPhoto);
            }
            ?>
            <?php if ($curPhotoUrl): ?>
            <div id="photoPreviewWrap" style="margin-bottom:10px;text-align:left;">
                <img id="photoPreviewImg" src="<?= $curPhotoUrl ?>" style="width:80px;height:103px;object-fit:cover;border:2px solid var(--primary);border-radius:6px;">
                <div style="margin-top:6px;">
                    <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('photoFileInput').click()">
                        <i class="fas fa-camera"></i> ছবি পরিবর্তন করুন
                    </button>
                </div>
            </div>
            <div id="photoUploadArea" style="display:none;border:2px dashed var(--border);border-radius:10px;padding:16px;text-align:center;cursor:pointer;background:var(--bg);"
                 onclick="document.getElementById('photoFileInput').click()">
            <?php else: ?>
            <div id="photoPreviewWrap" style="display:none;margin-bottom:10px;">
                <img id="photoPreviewImg" style="width:80px;height:103px;object-fit:cover;border:2px solid var(--primary);border-radius:6px;">
                <div style="margin-top:6px;">
                    <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('photoFileInput').click()">
                        <i class="fas fa-redo"></i> পরিবর্তন করুন
                    </button>
                </div>
            </div>
            <div id="photoUploadArea" style="border:2px dashed var(--border);border-radius:10px;padding:16px;text-align:center;cursor:pointer;background:var(--bg);transition:all .2s;"
                 onclick="document.getElementById('photoFileInput').click()"
                 ondragover="event.preventDefault();this.style.borderColor='var(--primary)'"
                 ondragleave="this.style.borderColor='var(--border)'"
                 ondrop="handlePhotoDrop(event)">
                <i class="fas fa-camera" style="font-size:28px;color:var(--primary-light);margin-bottom:8px;display:block;"></i>
                <div style="font-size:13px;font-weight:600;">ছবি আপলোড করুন</div>
                <div style="font-size:11px;color:var(--text-muted);">JPG / PNG / WebP • সর্বোচ্চ ৫MB</div>
            <?php endif; ?>
            </div>
            <input type="file" id="photoFileInput" accept="image/*" style="display:none" onchange="openPhotoEditor(this)">
        </div>

<!-- ===== Photo Editor Modal ===== -->
<div id="photoEditorModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.7);align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:16px;width:min(96vw,780px);max-height:92vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.4);">
        <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;">
            <h3 style="margin:0;font-size:16px;"><i class="fas fa-crop-alt" style="color:var(--primary);"></i> ছবি এডিট করুন</h3>
            <button type="button" onclick="closePhotoEditor()" style="background:none;border:none;font-size:20px;cursor:pointer;color:var(--text-muted);">✕</button>
        </div>
        <div style="padding:16px;display:flex;flex-wrap:wrap;gap:16px;">
            <div style="flex:1;min-width:260px;">
                <div style="font-size:12px;color:var(--text-muted);margin-bottom:8px;font-weight:600;"><i class="fas fa-crop-alt"></i> ড্র্যাগ করে মুখ সেট করুন (৩:৪ অনুপাত)</div>
                <div style="position:relative;display:inline-block;max-width:100%;">
                    <canvas id="editorCanvas" style="max-width:100%;border:2px solid var(--border);border-radius:8px;display:block;cursor:crosshair;touch-action:none;"></canvas>
                    <div id="cropOverlay" style="position:absolute;border:2px solid #fff;box-shadow:0 0 0 9999px rgba(0,0,0,.5);cursor:move;pointer-events:all;"></div>
                    <div class="crop-handle" id="hTL" style="top:-5px;left:-5px;cursor:nw-resize;"></div>
                    <div class="crop-handle" id="hTR" style="top:-5px;right:-5px;cursor:ne-resize;"></div>
                    <div class="crop-handle" id="hBL" style="bottom:-5px;left:-5px;cursor:sw-resize;"></div>
                    <div class="crop-handle" id="hBR" style="bottom:-5px;right:-5px;cursor:se-resize;"></div>
                </div>
            </div>
            <div style="width:180px;flex-shrink:0;">
                <div style="font-size:12px;color:var(--text-muted);margin-bottom:8px;font-weight:600;"><i class="fas fa-sliders-h"></i> অ্যাডজাস্টমেন্ট</div>
                <div style="margin-bottom:12px;">
                    <label style="font-size:12px;font-weight:600;display:flex;justify-content:space-between;">উজ্জ্বলতা <span id="brightnessVal">100%</span></label>
                    <input type="range" id="brightnessRange" min="50" max="200" value="100" oninput="updateEditorPreview()" style="width:100%;">
                </div>
                <div style="margin-bottom:12px;">
                    <label style="font-size:12px;font-weight:600;display:flex;justify-content:space-between;">কনট্রাস্ট <span id="contrastVal">100%</span></label>
                    <input type="range" id="contrastRange" min="50" max="200" value="100" oninput="updateEditorPreview()" style="width:100%;">
                </div>
                <div style="margin-bottom:12px;">
                    <label style="font-size:12px;font-weight:600;display:flex;justify-content:space-between;">স্যাচুরেশন <span id="saturationVal">100%</span></label>
                    <input type="range" id="saturationRange" min="0" max="200" value="100" oninput="updateEditorPreview()" style="width:100%;">
                </div>
                <div style="margin-bottom:16px;">
                    <label style="font-size:12px;font-weight:600;display:flex;justify-content:space-between;">শার্পনেস <span id="sharpnessVal">0</span></label>
                    <input type="range" id="sharpnessRange" min="0" max="5" value="0" step="0.5" oninput="updateEditorPreview()" style="width:100%;">
                </div>
                <button type="button" onclick="resetEditorSliders()" class="btn btn-outline btn-sm" style="width:100%;margin-bottom:12px;">
                    <i class="fas fa-undo"></i> রিসেট
                </button>
                <div style="font-size:12px;color:var(--text-muted);margin-bottom:6px;font-weight:600;"><i class="fas fa-eye"></i> প্রিভিউ</div>
                <canvas id="previewCanvas" width="80" height="103" style="border:2px solid var(--primary);border-radius:6px;display:block;margin:0 auto;"></canvas>
                <div style="font-size:10px;color:var(--text-muted);text-align:center;margin-top:4px;">পাসপোর্ট সাইজ</div>
            </div>
        </div>
        <div style="padding:12px 20px;border-top:1px solid var(--border);display:flex;gap:10px;justify-content:space-between;align-items:center;flex-wrap:wrap;">
            <button type="button" onclick="skipPhotoCrop()" class="btn btn-outline" style="color:var(--text-muted);font-size:12px;">
                <i class="fas fa-forward"></i> এড়িয়ে যান (crop ছাড়া)
            </button>
            <div style="display:flex;gap:10px;">
                <button type="button" onclick="closePhotoEditor()" class="btn btn-outline">বাতিল</button>
                <button type="button" onclick="applyPhotoCrop()" class="btn btn-primary"><i class="fas fa-check"></i> ছবি সেট করুন</button>
            </div>
        </div>
    </div>
</div>
<style>
.crop-handle { position:absolute;width:12px;height:12px;background:#fff;border:2px solid var(--primary);border-radius:3px;pointer-events:all;z-index:10; }
</style>
<script>
var _editorImg=null,_editorScale=1,_canvasW=0,_canvasH=0;
var _crop={x:0,y:0,w:0,h:0},_drag=null,ASPECT=3/4;
function handlePhotoDrop(e){e.preventDefault();var f=e.dataTransfer.files[0];if(f&&f.type.startsWith('image/'))openPhotoEditorFromFile(f);}
function openPhotoEditor(input){if(!input.files||!input.files[0])return;openPhotoEditorFromFile(input.files[0]);}
function openPhotoEditorFromFile(file){
    if(file.size>5*1024*1024){alert('ছবির সাইজ ৫MB এর বেশি হবে না।');return;}
    var r=new FileReader();r.onload=function(e){var img=new Image();img.onload=function(){_editorImg=img;resetEditorSliders();setupEditorCanvas();document.getElementById('photoEditorModal').style.display='flex';};img.src=e.target.result;};r.readAsDataURL(file);
}
function setupEditorCanvas(){
    var canvas=document.getElementById('editorCanvas');
    var maxW=Math.min(window.innerWidth*0.55,440),maxH=window.innerHeight*0.55;
    _editorScale=Math.min(maxW/_editorImg.width,maxH/_editorImg.height,1);
    _canvasW=Math.round(_editorImg.width*_editorScale);_canvasH=Math.round(_editorImg.height*_editorScale);
    canvas.width=_canvasW;canvas.height=_canvasH;drawEditorCanvas();
    var ch=Math.min(_canvasH*0.85,_canvasW/ASPECT*0.85),cw=ch*ASPECT;
    _crop={x:Math.round((_canvasW-cw)/2),y:Math.round((_canvasH-ch)/2),w:Math.round(cw),h:Math.round(ch)};
    updateCropOverlay();updateEditorPreview();initCropDrag();
}
function drawEditorCanvas(){
    var canvas=document.getElementById('editorCanvas'),ctx=canvas.getContext('2d');
    ctx.filter='brightness('+document.getElementById('brightnessRange').value+'%) contrast('+document.getElementById('contrastRange').value+'%) saturate('+document.getElementById('saturationRange').value+'%)';
    ctx.clearRect(0,0,_canvasW,_canvasH);ctx.drawImage(_editorImg,0,0,_canvasW,_canvasH);ctx.filter='none';
}
function updateEditorPreview(){
    document.getElementById('brightnessVal').textContent=document.getElementById('brightnessRange').value+'%';
    document.getElementById('contrastVal').textContent=document.getElementById('contrastRange').value+'%';
    document.getElementById('saturationVal').textContent=document.getElementById('saturationRange').value+'%';
    document.getElementById('sharpnessVal').textContent=document.getElementById('sharpnessRange').value;
    drawEditorCanvas();renderPreviewCanvas();
}
function renderPreviewCanvas(){
    var prev=document.getElementById('previewCanvas'),pCtx=prev.getContext('2d');
    var sx=_crop.x/_editorScale,sy=_crop.y/_editorScale,sw=_crop.w/_editorScale,sh=_crop.h/_editorScale;
    pCtx.filter='brightness('+document.getElementById('brightnessRange').value+'%) contrast('+document.getElementById('contrastRange').value+'%) saturate('+document.getElementById('saturationRange').value+'%)';
    pCtx.clearRect(0,0,80,103);pCtx.drawImage(_editorImg,sx,sy,sw,sh,0,0,80,103);pCtx.filter='none';
    var sharp=parseFloat(document.getElementById('sharpnessRange').value);if(sharp>0)applySharpen(pCtx,80,103,sharp);
}
function applySharpen(ctx,w,h,amount){
    var d=ctx.getImageData(0,0,w,h),px=d.data,k=amount*0.3;
    var kernel=[-k,-k,-k,-k,1+8*k,-k,-k,-k,-k],copy=new Uint8ClampedArray(px);
    for(var y=1;y<h-1;y++)for(var x=1;x<w-1;x++)for(var ch=0;ch<3;ch++){
        var sum=0;for(var ky=-1;ky<=1;ky++)for(var kx=-1;kx<=1;kx++)sum+=copy[((y+ky)*w+(x+kx))*4+ch]*kernel[(ky+1)*3+(kx+1)];
        px[(y*w+x)*4+ch]=Math.min(255,Math.max(0,sum));
    }
    ctx.putImageData(d,0,0);
}
function resetEditorSliders(){
    ['brightness','contrast','saturation'].forEach(function(n){document.getElementById(n+'Range').value=100;document.getElementById(n+'Val').textContent='100%';});
    document.getElementById('sharpnessRange').value=0;document.getElementById('sharpnessVal').textContent='0';
    if(_editorImg){drawEditorCanvas();renderPreviewCanvas();}
}
function updateCropOverlay(){
    var ov=document.getElementById('cropOverlay');
    ov.style.left=_crop.x+'px';ov.style.top=_crop.y+'px';ov.style.width=_crop.w+'px';ov.style.height=_crop.h+'px';
    var h={hTL:[_crop.x-5,_crop.y-5],hTR:[_crop.x+_crop.w-7,_crop.y-5],hBL:[_crop.x-5,_crop.y+_crop.h-7],hBR:[_crop.x+_crop.w-7,_crop.y+_crop.h-7]};
    Object.keys(h).forEach(function(id){document.getElementById(id).style.left=h[id][0]+'px';document.getElementById(id).style.top=h[id][1]+'px';});
}
function initCropDrag(){
    var canvas=document.getElementById('editorCanvas');
    function getPos(e){var r=canvas.getBoundingClientRect(),t=e.touches?e.touches[0]:e;return{x:t.clientX-r.left,y:t.clientY-r.top};}
    function clamp(v,lo,hi){return Math.max(lo,Math.min(hi,v));}
    function startDrag(type,e){e.preventDefault();var p=getPos(e);_drag={type:type,startX:p.x,startY:p.y,startCrop:Object.assign({},_crop)};}
    document.getElementById('cropOverlay').addEventListener('mousedown',function(e){startDrag('move',e);});
    document.getElementById('cropOverlay').addEventListener('touchstart',function(e){startDrag('move',e);},{passive:false});
    ['hTL','hTR','hBL','hBR'].forEach(function(id){
        var el=document.getElementById(id);
        el.addEventListener('mousedown',function(e){e.stopPropagation();startDrag(id.replace('h','').toLowerCase(),e);});
        el.addEventListener('touchstart',function(e){e.stopPropagation();startDrag(id.replace('h','').toLowerCase(),e);},{passive:false});
    });
    function onMove(e){
        if(!_drag)return;e.preventDefault();
        var p=getPos(e),dx=p.x-_drag.startX,dy=p.y-_drag.startY,sc=_drag.startCrop,min=40,nw,nh;
        if(_drag.type==='move'){_crop.x=clamp(sc.x+dx,0,_canvasW-sc.w);_crop.y=clamp(sc.y+dy,0,_canvasH-sc.h);}
        else if(_drag.type==='br'){nw=clamp(sc.w+dx,min,_canvasW-sc.x);nh=nw/ASPECT;if(sc.y+nh>_canvasH){nh=_canvasH-sc.y;nw=nh*ASPECT;}_crop.w=Math.round(nw);_crop.h=Math.round(nh);}
        else if(_drag.type==='tl'){nw=clamp(sc.w-dx,min,sc.x+sc.w);nh=nw/ASPECT;_crop.x=Math.round(sc.x+sc.w-nw);_crop.y=Math.round(sc.y+sc.h-nh);_crop.w=Math.round(nw);_crop.h=Math.round(nh);}
        else if(_drag.type==='tr'){nw=clamp(sc.w+dx,min,_canvasW-sc.x);nh=nw/ASPECT;_crop.y=Math.round(sc.y+sc.h-nh);_crop.w=Math.round(nw);_crop.h=Math.round(nh);}
        else if(_drag.type==='bl'){nw=clamp(sc.w-dx,min,sc.x+sc.w);nh=nw/ASPECT;_crop.x=Math.round(sc.x+sc.w-nw);_crop.w=Math.round(nw);_crop.h=Math.round(nh);}
        updateCropOverlay();renderPreviewCanvas();
    }
    document.addEventListener('mousemove',onMove);document.addEventListener('touchmove',onMove,{passive:false});
    document.addEventListener('mouseup',function(){_drag=null;});document.addEventListener('touchend',function(){_drag=null;});
}
function applyPhotoCrop(){
    var out=document.createElement('canvas');out.width=200;out.height=257;
    var ctx=out.getContext('2d');
    ctx.filter='brightness('+document.getElementById('brightnessRange').value+'%) contrast('+document.getElementById('contrastRange').value+'%) saturate('+document.getElementById('saturationRange').value+'%)';
    ctx.drawImage(_editorImg,_crop.x/_editorScale,_crop.y/_editorScale,_crop.w/_editorScale,_crop.h/_editorScale,0,0,200,257);
    ctx.filter='none';
    var sharp=parseFloat(document.getElementById('sharpnessRange').value);if(sharp>0)applySharpen(ctx,200,257,sharp);
    setPhotoResult(out.toDataURL('image/jpeg',0.88));closePhotoEditor();
}
function skipPhotoCrop(){
    var MAX=800,iw=_editorImg.naturalWidth,ih=_editorImg.naturalHeight;
    var scale=(iw>MAX||ih>MAX)?Math.min(MAX/iw,MAX/ih):1;
    var out=document.createElement('canvas');out.width=Math.round(iw*scale);out.height=Math.round(ih*scale);
    var ctx=out.getContext('2d');ctx.imageSmoothingEnabled=true;ctx.imageSmoothingQuality='high';
    ctx.drawImage(_editorImg,0,0,out.width,out.height);
    setPhotoResult(out.toDataURL('image/jpeg',0.82));closePhotoEditor();
}
function setPhotoResult(dataUrl){
    document.getElementById('photoCroppedData').value=dataUrl;
    document.getElementById('photoPreviewImg').src=dataUrl;
    document.getElementById('photoPreviewWrap').style.display='';
    document.getElementById('photoUploadArea').style.display='none';
}
function closePhotoEditor(){document.getElementById('photoEditorModal').style.display='none';}
</script>
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
