<?php
require_once '../../includes/functions.php';
requireLogin(['super_admin','principal','teacher']);
$pageTitle = 'নতুন ছাত্র ভর্তি';
$db = getDB();

// Classes
$divisions = $db->query("SELECT * FROM divisions WHERE is_active=1 ORDER BY sort_order, id")->fetchAll();
$classes = $db->query("SELECT c.*, d.division_name_bn FROM classes c LEFT JOIN divisions d ON c.division_id=d.id WHERE c.is_active=1 ORDER BY c.division_id, c.class_numeric")->fetchAll();
// Group classes by division
$classesByDiv = [];
foreach ($classes as $c) { $classesByDiv[$c['division_id']][] = $c; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf'] ?? '')) {
        setFlash('danger', 'CSRF token অবৈধ।');
        header('Location: admission.php');
        exit;
    }

    $name = trim($_POST['name'] ?? '');
    $nameBn = trim($_POST['name_bn'] ?? '');
    $divisionId = (int)($_POST['division_id'] ?? 0);
    $divisionId = (int)($_POST['division_id'] ?? 0);
    $classId = (int)($_POST['class_id'] ?? 0);
    $sectionId = (int)($_POST['section_id'] ?? 0) ?: null;
    $dob = $_POST['dob'] ?? null;
    $gender = $_POST['gender'] ?? 'male';
    $admDate = $_POST['admission_date'] ?? date('Y-m-d');
    $fatherName = trim($_POST['father_name'] ?? '');
    $fatherNameEn = trim($_POST['father_name_en'] ?? '');
    $fatherPhone = trim($_POST['father_phone'] ?? '');
    $motherName = trim($_POST['mother_name'] ?? '');
    $motherNameEn = trim($_POST['mother_name_en'] ?? '');
    $motherPhone = trim($_POST['mother_phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $religion = $_POST['religion'] ?? 'islam';
    $bloodGroup = $_POST['blood_group'] ?? '';
    $prevSchool = trim($_POST['prev_school'] ?? '');
    $birthCert = trim($_POST['birth_cert'] ?? '');

    $monthlyFee   = 0;
    $isHostel     = isset($_POST['is_hostel']) ? 1 : 0;
    $hostelFee    = $isHostel ? (float)($_POST['hostel_fee'] ?? 0) : 0;
    $isHostelFood = ($isHostel && isset($_POST['is_hostel_food'])) ? 1 : 0;
    $foodFee      = $isHostelFood ? (float)($_POST['food_fee'] ?? 0) : 0;

    $guardianPhone = $fatherPhone ?: $motherPhone;

    if (!$name || !$classId) {
        setFlash('danger', 'নাম ও শ্রেণী আবশ্যক।');
    } else {
        // Unique Student ID
        do {
            $rand = strtoupper(substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 4));
            $studentId = 'ANT-' . date('Y') . '-' . $rand;
            $exists = $db->prepare("SELECT id FROM students WHERE student_id=?");
            $exists->execute([$studentId]);
        } while ($exists->fetch());

        // Secret Code
        do {
            $secretCode = strtoupper(substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 6));
            $secExists = $db->prepare("SELECT id FROM students WHERE secret_code=?");
            $secExists->execute([$secretCode]);
        } while ($secExists->fetch());

        // Roll Number
        $rollNo = $db->query("SELECT COALESCE(MAX(roll_number),0)+1 FROM students WHERE class_id=$classId AND academic_year='".date('Y')."'")->fetchColumn();

        // ===== Photo Upload =====
        $photo = null;
        $photoCroppedB64 = trim($_POST['photo_cropped'] ?? '');

        if ($photoCroppedB64 && strpos($photoCroppedB64, 'data:image/') === 0) {
            // Browser থেকে cropped base64 image
            $b64Data  = preg_replace('/^data:image\/\w+;base64,/', '', $photoCroppedB64);
            $imgBytes = base64_decode($b64Data);

            if ($imgBytes !== false && strlen($imgBytes) > 100) {
                $tmpPath = tempnam(sys_get_temp_dir(), 'photo_') . '.jpg';
                if (file_put_contents($tmpPath, $imgBytes) !== false) {
                    require_once '../../includes/cloudinary_upload.php';
                    $cloudUrl = uploadToCloudinary($tmpPath, 'students/' . $studentId);
                    @unlink($tmpPath);
                    if ($cloudUrl) {
                        $photo = $cloudUrl;
                    } else {
                        setFlash('danger', 'ছবি upload ব্যর্থ হয়েছে। Cloudinary credentials চেক করুন অথবা আবার চেষ্টা করুন।');
                        header('Location: admission.php');
                        exit;
                    }
                } else {
                    error_log('[Admission] Failed to write temp file: ' . $tmpPath);
                    setFlash('danger', 'ছবি প্রক্রিয়া করতে ব্যর্থ হয়েছে। আবার চেষ্টা করুন।');
                    header('Location: admission.php');
                    exit;
                }
            }

        } elseif (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            // সরাসরি file upload (crop ছাড়া)
            $allowedTypes = ['image/jpeg','image/jpg','image/png','image/gif','image/webp'];
            $mimeType = mime_content_type($_FILES['photo']['tmp_name']);
            if (!in_array($mimeType, $allowedTypes)) {
                setFlash('danger', 'শুধু JPG, PNG, GIF বা WebP ছবি upload করুন।');
                header('Location: admission.php');
                exit;
            }
            if ($_FILES['photo']['size'] > 5 * 1024 * 1024) {
                setFlash('danger', 'ছবির সাইজ ৫MB এর বেশি হবে না।');
                header('Location: admission.php');
                exit;
            }
            require_once '../../includes/cloudinary_upload.php';
            $cloudUrl = uploadToCloudinary($_FILES['photo']['tmp_name'], 'students/' . $studentId);
            if ($cloudUrl) {
                $photo = $cloudUrl;
            } else {
                setFlash('danger', 'ছবি upload ব্যর্থ হয়েছে। Cloudinary credentials চেক করুন অথবা আবার চেষ্টা করুন।');
                header('Location: admission.php');
                exit;
            }

        } elseif (!empty($_FILES['photo']['error']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
            // PHP upload error code
            $uploadErrors = [
                UPLOAD_ERR_INI_SIZE   => 'ছবি php.ini upload_max_filesize সীমার বেশি।',
                UPLOAD_ERR_FORM_SIZE  => 'ছবি form MAX_FILE_SIZE সীমার বেশি।',
                UPLOAD_ERR_PARTIAL    => 'ছবি আংশিকভাবে upload হয়েছে।',
                UPLOAD_ERR_NO_TMP_DIR => 'Temporary folder পাওয়া যাচ্ছে না।',
                UPLOAD_ERR_CANT_WRITE => 'Disk এ লেখা সম্ভব হচ্ছে না।',
                UPLOAD_ERR_EXTENSION  => 'PHP extension ছবি upload বন্ধ করেছে।',
            ];
            $errCode = $_FILES['photo']['error'];
            $errMsg  = $uploadErrors[$errCode] ?? "অজানা upload error (code: $errCode)";
            setFlash('danger', $errMsg);
            header('Location: admission.php');
            exit;
        }

        // ===== ভর্তির বছর =====
        $admissionYear = $admDate ? (int)date('Y', strtotime($admDate)) : (int)date('Y');

        $stmt = $db->prepare("INSERT INTO students
            (student_id, roll_number, name, name_bn, date_of_birth, gender, religion, blood_group,
             division_id, class_id, admission_class_id, section_id, academic_year, admission_year, admission_date,
             father_name, father_name_en, father_phone,
             mother_name, mother_name_en, mother_phone, guardian_phone, address_present,
             previous_school, birth_certificate_no, photo, secret_code, status,
             monthly_fee, is_hostel, hostel_fee, is_hostel_food, food_fee, created_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())");
        $stmt->execute([
            $studentId, $rollNo, $name, $nameBn, $dob ?: null, $gender, $religion, $bloodGroup,
            $divisionId ?: null, $classId, $classId, $sectionId, date('Y'), $admissionYear, $admDate,
            $fatherName, $fatherNameEn, $fatherPhone,
            $motherName, $motherNameEn, $motherPhone, $guardianPhone, $address,
            $prevSchool, $birthCert, $photo, $secretCode, 'active',
            $monthlyFee, $isHostel, $hostelFee, $isHostelFood, $foodFee
        ]);
        $newId = $db->lastInsertId();

        // Parent user account তৈরি
        if ($guardianPhone) {
            $existing = $db->prepare("SELECT id FROM users WHERE phone=?");
            $existing->execute([$guardianPhone]);
            if (!$existing->fetch()) {
                $hashedPw = password_hash($guardianPhone, PASSWORD_DEFAULT);
                $uStmt = $db->prepare("INSERT INTO users (name, name_bn, username, phone, password, role_id) VALUES (?,?,?,?,?,5)");
                $uStmt->execute([$fatherName ?: 'অভিভাবক', $fatherName, $guardianPhone, $guardianPhone, $hashedPw]);
            }
        }

        logActivity($_SESSION['user_id'], 'student_admit', 'students', "ছাত্র ভর্তি: $name ($studentId)");
        setFlash('success', "ছাত্র সফলভাবে ভর্তি হয়েছে! ID: $studentId | Secret Code: $secretCode");
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
                <label>ছবি (পাসপোর্ট সাইজ)</label>

                <input type="hidden" name="photo_cropped" id="photoCroppedData">

                <div id="photoUploadArea" style="border:2px dashed var(--border);border-radius:10px;padding:16px;text-align:center;cursor:pointer;background:var(--bg);transition:all .2s;"
                     onclick="document.getElementById('photoFileInput').click()"
                     ondragover="event.preventDefault();this.style.borderColor='var(--primary)'"
                     ondragleave="this.style.borderColor='var(--border)'"
                     ondrop="handlePhotoDrop(event)">
                    <i class="fas fa-camera" style="font-size:28px;color:var(--primary-light);margin-bottom:8px;display:block;"></i>
                    <div style="font-size:13px;font-weight:600;">ছবি আপলোড করুন</div>
                    <div style="font-size:11px;color:var(--text-muted);">JPG / PNG / WebP • সর্বোচ্চ ৫MB</div>
                </div>
                <input type="file" id="photoFileInput" accept="image/*" style="display:none" onchange="openPhotoEditor(this)">

                <div id="photoPreviewWrap" style="display:none;margin-top:10px;text-align:center;">
                    <img id="photoPreviewImg" style="width:80px;height:103px;object-fit:cover;border:2px solid var(--primary);border-radius:6px;">
                    <div style="margin-top:6px;">
                        <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('photoFileInput').click()">
                            <i class="fas fa-redo"></i> পরিবর্তন করুন
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Photo Editor Modal -->
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
.crop-handle {
    position: absolute; width: 12px; height: 12px;
    background: #fff; border: 2px solid var(--primary);
    border-radius: 3px; pointer-events: all; z-index: 10;
}
</style>

<script>
var _editorImg=null,_editorScale=1,_canvasW=0,_canvasH=0;
var _crop={x:0,y:0,w:0,h:0},_drag=null,ASPECT=3/4;
function handlePhotoDrop(e){e.preventDefault();e.currentTarget.style.borderColor='var(--border)';var f=e.dataTransfer.files[0];if(f&&f.type.startsWith('image/'))openPhotoEditorFromFile(f);}
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

<!-- শ্রেণী তথ্য -->
<div class="card mb-24">
    <div class="card-header" style="background:#eafaf1;">
        <span class="card-title" style="color:var(--success);"><i class="fas fa-school"></i> শ্রেণী তথ্য</span>
    </div>
    <div class="card-body">
        <div class="form-grid">
            <div class="form-group">
                <label>বিভাগ <span>*</span></label>
                <select name="division_id" class="form-control" required id="divisionSelect" onchange="filterByDivision(this.value)">
                    <option value="">-- বিভাগ নির্বাচন করুন --</option>
                    <?php foreach ($divisions as $d): ?>
                    <option value="<?= $d['id'] ?>"><?= e($d['division_name_bn']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>শ্রেণী <span>*</span></label>
                <select name="class_id" class="form-control" required id="classSelect" onchange="loadSections(this.value)">
                    <option value="">আগে বিভাগ নির্বাচন করুন</option>
                    <?php foreach ($classes as $c): ?>
                    <option value="<?= $c['id'] ?>" data-div="<?= $c['division_id'] ?>" style="display:none;">
                        <?= e($c['class_name_bn'] ?? $c['class_name']) ?>
                    </option>
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
                <input type="text" name="father_name" class="form-control" placeholder="আব্দুর রহমান">
            </div>
            <div class="form-group">
                <label>পিতার নাম (ইংরেজিতে)</label>
                <input type="text" name="father_name_en" class="form-control" placeholder="Abdur Rahman">
            </div>
            <div class="form-group">
                <label>পিতার মোবাইল</label>
                <input type="tel" name="father_phone" class="form-control" placeholder="01XXXXXXXXX">
            </div>
            <div class="form-group">
                <label>মাতার নাম (বাংলায়)</label>
                <input type="text" name="mother_name" class="form-control" placeholder="ফাতেমা বেগম">
            </div>
            <div class="form-group">
                <label>মাতার নাম (ইংরেজিতে)</label>
                <input type="text" name="mother_name_en" class="form-control" placeholder="Fatema Begum">
            </div>
            <div class="form-group">
                <label>মাতার মোবাইল</label>
                <input type="tel" name="mother_phone" class="form-control" placeholder="01XXXXXXXXX">
            </div>
        </div>
        <div class="alert alert-info mt-16" style="padding:10px 14px;background:#ebf5fb;border-radius:8px;font-size:13px;">
            <i class="fas fa-info-circle"></i>
            পিতার মোবাইল থাকলে সেটা অভিভাবকের নম্বর হিসেবে ব্যবহার হবে। না থাকলে মাতার নম্বর ব্যবহার হবে।
        </div>
    </div>
</div>

<!-- হোস্টেল তথ্য -->
<div class="card mb-24">
    <div class="card-header" style="background:#f4ecf7;">
        <span class="card-title" style="color:#7d3c98;"><i class="fas fa-building"></i> হোস্টেল তথ্য</span>
    </div>
    <div class="card-body">
        <div style="padding:10px 14px;background:#fff8f0;border-radius:8px;font-size:13px;margin-bottom:16px;border-left:3px solid #e67e22;">
            <i class="fas fa-info-circle" style="color:#e67e22;"></i>
            টিউশন, লাইব্রেরি বা অন্য ফী ভর্তির পরে ছাত্রের প্রোফাইল থেকে আলাদাভাবে নির্ধারণ করা যাবে।
        </div>
        <div>
            <label style="display:flex;align-items:center;gap:10px;font-weight:600;cursor:pointer;">
                <input type="checkbox" name="is_hostel" id="isHostelCheck" onchange="toggleHostel(this)" style="width:18px;height:18px;">
                হোস্টেলে থাকবে
            </label>
        </div>
        <div id="hostelFields" style="display:none;margin-top:16px;padding:16px;background:#faf4ff;border-radius:10px;border:1px dashed #c39bd3;">
            <div class="form-grid">
                <div class="form-group">
                    <label>হোস্টেল ফি (টাকা/মাস)</label>
                    <input type="number" name="hostel_fee" id="hostelFee" class="form-control" placeholder="০" min="0" step="0.01">
                </div>
            </div>
            <div style="margin-top:12px;">
                <label style="display:flex;align-items:center;gap:10px;font-weight:600;cursor:pointer;">
                    <input type="checkbox" name="is_hostel_food" id="isHostelFoodCheck" onchange="toggleFood(this)" style="width:18px;height:18px;">
                    হোস্টেলের খাবার খাবে
                </label>
            </div>
            <div id="foodFields" style="display:none;margin-top:12px;">
                <div class="form-grid">
                    <div class="form-group">
                        <label>খাবার ফি (টাকা/মাস)</label>
                        <input type="number" name="food_fee" id="foodFee" class="form-control" placeholder="০" min="0" step="0.01">
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div style="display:flex;gap:12px;">
    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> ভর্তি করুন</button>
    <a href="list.php" class="btn btn-outline"><i class="fas fa-times"></i> বাতিল</a>
</div>
</form>

<script>
function toggleHostel(cb) {
    document.getElementById('hostelFields').style.display = cb.checked ? 'block' : 'none';
    if (!cb.checked) {
        document.getElementById('isHostelFoodCheck').checked = false;
        document.getElementById('foodFields').style.display = 'none';
        document.getElementById('hostelFee').value = '';
        document.getElementById('foodFee').value = '';
    }
}
function toggleFood(cb) {
    document.getElementById('foodFields').style.display = cb.checked ? 'block' : 'none';
    if (!cb.checked) document.getElementById('foodFee').value = '';
}
function filterByDivision(divId) {
    const classSel = document.getElementById('classSelect');
    const secSel = document.getElementById('sectionSelect');
    classSel.value = '';
    secSel.innerHTML = '<option value="">শ্রেণী নির্বাচন করুন আগে</option>';
    Array.from(classSel.options).forEach(opt => {
        if (!opt.value) { opt.style.display = ''; return; }
        opt.style.display = (opt.dataset.div === divId) ? '' : 'none';
    });
    classSel.options[0].text = divId ? 'শ্রেণী নির্বাচন করুন' : 'আগে বিভাগ নির্বাচন করুন';
}
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
