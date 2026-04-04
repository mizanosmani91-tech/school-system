<?php
require_once '../../includes/functions.php';
requireLogin(['super_admin','principal','teacher']);
$pageTitle = 'বাল্ক ছাত্র ভর্তি (Excel/CSV)';
$db = getDB();
$classes = $db->query("SELECT * FROM classes WHERE is_active=1 ORDER BY class_numeric")->fetchAll();

$results = [];

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['import'])) {
    if (!verifyCsrf($_POST['csrf']??'')) die('CSRF');

    $classId = (int)($_POST['default_class_id']??0);
    $academicYear = date('Y');
    $file = $_FILES['excel_file'] ?? null;

    if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        setFlash('danger','ফাইল আপলোড ব্যর্থ হয়েছে।');
        header('Location: bulk_import.php'); exit;
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['csv','xlsx','xls'])) {
        setFlash('danger','শুধুমাত্র CSV, XLS বা XLSX ফাইল গ্রহণযোগ্য।');
        header('Location: bulk_import.php'); exit;
    }

    // Read CSV
    $rows = [];
    if ($ext === 'csv') {
        $handle = fopen($file['tmp_name'], 'r');
        $header = fgetcsv($handle); // skip header row
        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = $row;
        }
        fclose($handle);
    } else {
        // For xlsx/xls - basic parsing (convert to CSV first via simple method)
        // We'll read as CSV after detection
        setFlash('danger','Excel ফাইলের জন্য নিচের template ডাউনলোড করে CSV হিসেবে সেভ করুন।');
        header('Location: bulk_import.php'); exit;
    }

    $success = 0; $failed = 0; $skipped = 0;

    foreach ($rows as $i => $row) {
        if (empty(array_filter($row))) continue; // skip empty rows

        // CSV columns: name_bn, name, father_name, father_phone, mother_name, class_id, dob, gender, address
        $nameBn      = trim($row[0] ?? '');
        $name        = trim($row[1] ?? '') ?: $nameBn;
        $fatherName  = trim($row[2] ?? '');
        $fatherPhone = trim($row[3] ?? '');
        $motherName  = trim($row[4] ?? '');
        $rowClassId  = !empty($row[5]) ? (int)$row[5] : $classId;
        $dob         = trim($row[6] ?? '') ?: null;
        $gender      = strtolower(trim($row[7] ?? 'male'));
        $address     = trim($row[8] ?? '');

        if (!$nameBn && !$name) { $failed++; $results[] = ['row'=>$i+2,'status'=>'failed','msg'=>'নাম নেই']; continue; }
        if (!$rowClassId) { $failed++; $results[] = ['row'=>$i+2,'status'=>'failed','msg'=>'শ্রেণী নেই']; continue; }

        // Check duplicate by name+father_phone
        if ($fatherPhone) {
            $dup = $db->prepare("SELECT id FROM students WHERE name_bn=? AND father_phone=?");
            $dup->execute([$nameBn, $fatherPhone]);
            if ($dup->fetch()) { $skipped++; $results[] = ['row'=>$i+2,'status'=>'skipped','msg'=>"$nameBn — আগে থেকে আছে"]; continue; }
        }

        try {
            $studentId = generateStudentId($rowClassId);
            $rollNo = $db->query("SELECT COUNT(*)+1 FROM students WHERE class_id=$rowClassId AND academic_year='$academicYear'")->fetchColumn();

            $stmt = $db->prepare("INSERT INTO students
                (student_id,roll_number,name,name_bn,father_name,father_phone,mother_name,
                 class_id,academic_year,date_of_birth,gender,address_present,admission_date,status)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,CURDATE(),'active')");
            $stmt->execute([$studentId,$rollNo,$name,$nameBn,$fatherName,$fatherPhone,
                $motherName,$rowClassId,$academicYear,$dob,$gender,$address]);

            // Create parent account
            if ($fatherPhone) {
                $ex = $db->prepare("SELECT id FROM users WHERE phone=?");
                $ex->execute([$fatherPhone]);
                if (!$ex->fetch()) {
                    $db->prepare("INSERT INTO users (name,name_bn,username,phone,password,role_id) VALUES (?,?,?,?,?,5)")
                       ->execute([$fatherName ?: 'অভিভাবক',$fatherName,$fatherPhone,$fatherPhone,password_hash($fatherPhone,PASSWORD_DEFAULT)]);
                }
            }

            $success++;
            $results[] = ['row'=>$i+2,'status'=>'success','msg'=>"$nameBn ($studentId) — সফল"];
        } catch (Exception $e) {
            $failed++;
            $results[] = ['row'=>$i+2,'status'=>'failed','msg'=>"$nameBn — ত্রুটি: ".$e->getMessage()];
        }
    }

    setFlash('success', "আমদানি সম্পন্ন! সফল: $success, বাদ: $skipped, ব্যর্থ: $failed");
}

// Download template
if (isset($_GET['download_template'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="student_import_template.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM for Excel
    fputcsv($out, ['নাম (বাংলায়)*','নাম (ইংরেজি)','পিতার নাম','পিতার ফোন','মাতার নাম','শ্রেণী ID*','জন্ম তারিখ (YYYY-MM-DD)','লিঙ্গ (male/female)','ঠিকানা']);
    // Sample rows
    fputcsv($out, ['মুহাম্মদ আব্দুল্লাহ','Muhammad Abdullah','আব্দুর রহমান','01700000001','ফাতেমা বেগম','1','2015-05-10','male','সাভার, ঢাকা']);
    fputcsv($out, ['আয়েশা সিদ্দিকা','Ayesha Siddika','করিম মিয়া','01700000002','রাহেলা বেগম','2','2014-08-15','female','আশুলিয়া, ঢাকা']);
    fclose($out);
    exit;
}

require_once '../../includes/header.php';
?>

<div class="section-header">
    <h2 class="section-title"><i class="fas fa-file-upload"></i> বাল্ক ছাত্র ভর্তি</h2>
    <div style="display:flex;gap:8px;">
        <a href="?download_template=1" class="btn btn-success btn-sm"><i class="fas fa-download"></i> Template ডাউনলোড</a>
        <a href="admission.php" class="btn btn-outline btn-sm"><i class="fas fa-user-plus"></i> একক ভর্তি</a>
        <a href="list.php" class="btn btn-outline btn-sm"><i class="fas fa-list"></i> তালিকা</a>
    </div>
</div>

<!-- Instructions -->
<div class="card mb-16" style="border-left:4px solid var(--info);">
    <div class="card-body">
        <h3 style="font-size:15px;font-weight:700;color:var(--primary);margin-bottom:12px;"><i class="fas fa-info-circle"></i> কীভাবে ব্যবহার করবেন</h3>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;font-size:13px;">
            <div style="display:flex;gap:10px;">
                <div style="width:28px;height:28px;background:var(--primary);color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;flex-shrink:0;">১</div>
                <div><strong>Template ডাউনলোড</strong> করুন উপরের বাটন থেকে</div>
            </div>
            <div style="display:flex;gap:10px;">
                <div style="width:28px;height:28px;background:var(--primary);color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;flex-shrink:0;">২</div>
                <div>Excel/Google Sheets এ ছাত্রদের তথ্য পূরণ করুন</div>
            </div>
            <div style="display:flex;gap:10px;">
                <div style="width:28px;height:28px;background:var(--primary);color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;flex-shrink:0;">৩</div>
                <div><strong>CSV হিসেবে সেভ</strong> করুন (File → Save As → CSV)</div>
            </div>
            <div style="display:flex;gap:10px;">
                <div style="width:28px;height:28px;background:var(--primary);color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;flex-shrink:0;">৪</div>
                <div>নিচে CSV ফাইল আপলোড করে <strong>Import</strong> করুন</div>
            </div>
        </div>

        <div style="margin-top:12px;padding:10px 14px;background:#fff3cd;border-radius:8px;font-size:12px;">
            <strong>⚠️ শ্রেণী ID সমূহ:</strong>
            <?php foreach($classes as $c): ?>
            <span style="margin-left:8px;background:#fff;padding:2px 8px;border-radius:4px;border:1px solid #dee2e6;">
                <?=e($c['class_name_bn'])?> = <strong><?=$c['id']?></strong>
            </span>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Upload Form -->
<div class="card mb-24">
    <div class="card-header"><span class="card-title"><i class="fas fa-upload"></i> CSV ফাইল আপলোড করুন</span></div>
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data" id="importForm">
            <input type="hidden" name="csrf" value="<?=getCsrfToken()?>">
            <input type="hidden" name="import" value="1">

            <div style="border:2px dashed var(--border);border-radius:12px;padding:40px;text-align:center;margin-bottom:20px;transition:border-color .2s;cursor:pointer;" id="dropZone">
                <i class="fas fa-file-csv" style="font-size:48px;color:var(--primary-light);margin-bottom:12px;"></i>
                <p style="font-size:15px;font-weight:600;color:var(--primary);">CSV ফাইল এখানে drag & drop করুন</p>
                <p style="font-size:13px;color:var(--text-muted);margin:6px 0;">অথবা</p>
                <label style="cursor:pointer;">
                    <span class="btn btn-primary btn-sm"><i class="fas fa-folder-open"></i> ফাইল বেছে নিন</span>
                    <input type="file" name="excel_file" accept=".csv,.xlsx,.xls" style="display:none;" id="fileInput" onchange="showFileName(this)">
                </label>
                <p style="font-size:12px;color:var(--text-muted);margin-top:8px;" id="fileNameDisplay">CSV, XLS, XLSX (সর্বোচ্চ 5MB)</p>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label>ডিফল্ট শ্রেণী <span style="color:var(--text-muted);font-size:11px;">(CSV এ শ্রেণী না থাকলে এটা ব্যবহার হবে)</span></label>
                    <select name="default_class_id" class="form-control">
                        <option value="">শ্রেণী নির্বাচন করুন</option>
                        <?php foreach($classes as $c): ?>
                        <option value="<?=$c['id']?>"><?=e($c['class_name_bn'])?> (ID: <?=$c['id']?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="display:flex;align-items:flex-end;">
                    <button type="submit" class="btn btn-primary" id="importBtn" style="width:100%;">
                        <i class="fas fa-file-import"></i> আমদানি শুরু করুন
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Results -->
<?php if (!empty($results)): ?>
<div class="card">
    <div class="card-header">
        <span class="card-title"><i class="fas fa-list-check"></i> আমদানির ফলাফল</span>
        <div style="display:flex;gap:8px;font-size:13px;">
            <span style="color:var(--success);font-weight:700;"><i class="fas fa-check"></i> সফল: <?=count(array_filter($results,fn($r)=>$r['status']==='success'))?></span>
            <span style="color:var(--warning);font-weight:700;"><i class="fas fa-skip-forward"></i> বাদ: <?=count(array_filter($results,fn($r)=>$r['status']==='skipped'))?></span>
            <span style="color:var(--danger);font-weight:700;"><i class="fas fa-times"></i> ব্যর্থ: <?=count(array_filter($results,fn($r)=>$r['status']==='failed'))?></span>
        </div>
    </div>
    <div class="table-wrap" style="max-height:400px;overflow-y:auto;">
        <table>
            <thead><tr><th>সারি</th><th>বার্তা</th><th>অবস্থা</th></tr></thead>
            <tbody>
                <?php foreach($results as $r): ?>
                <tr>
                    <td style="font-size:13px;"><?=toBanglaNumber($r['row'])?></td>
                    <td style="font-size:13px;"><?=e($r['msg'])?></td>
                    <td>
                        <span class="badge badge-<?=$r['status']==='success'?'success':($r['status']==='skipped'?'warning':'danger')?>">
                            <?=['success'=>'সফল','skipped'=>'বাদ','failed'=>'ব্যর্থ'][$r['status']]?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer">
        <a href="list.php" class="btn btn-primary btn-sm"><i class="fas fa-list"></i> ছাত্র তালিকা দেখুন</a>
    </div>
</div>
<?php endif; ?>

<script>
function showFileName(input) {
    if (input.files[0]) {
        document.getElementById('fileNameDisplay').textContent = '✅ ' + input.files[0].name + ' (' + (input.files[0].size/1024).toFixed(1) + ' KB)';
        document.getElementById('dropZone').style.borderColor = 'var(--success)';
    }
}

// Drag & drop
const dropZone = document.getElementById('dropZone');
dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.style.borderColor='var(--primary)'; });
dropZone.addEventListener('dragleave', () => { dropZone.style.borderColor='var(--border)'; });
dropZone.addEventListener('drop', e => {
    e.preventDefault();
    const file = e.dataTransfer.files[0];
    if (file) {
        document.getElementById('fileInput').files = e.dataTransfer.files;
        showFileName(document.getElementById('fileInput'));
    }
});

// Loading state
document.getElementById('importForm').addEventListener('submit', function() {
    document.getElementById('importBtn').innerHTML = '<div class="spinner" style="width:16px;height:16px;border-width:2px;"></div> আমদানি হচ্ছে...';
    document.getElementById('importBtn').disabled = true;
});
</script>

<?php require_once '../../includes/footer.php'; ?>
