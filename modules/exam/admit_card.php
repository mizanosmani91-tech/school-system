<?php
require_once '../../includes/functions.php';
requireLogin(['super_admin','principal','teacher']);
$pageTitle = 'প্রবেশপত্র জেনারেটর';
$db = getDB();

// ডেটা লোড
$divisions = $db->query("SELECT * FROM divisions WHERE is_active=1 ORDER BY sort_order, id")->fetchAll();
$exams     = $db->query("SELECT * FROM exams ORDER BY academic_year DESC, start_date DESC")->fetchAll();

$divisionId  = (int)($_GET['division_id'] ?? 0);
$classId     = (int)($_GET['class_id'] ?? 0);
$examId      = (int)($_GET['exam_id'] ?? 0);
$printMode   = isset($_GET['print']);

// শ্রেণী — বিভাগ অনুযায়ী
if ($divisionId) {
    $clsStmt = $db->prepare("SELECT c.*, d.division_name_bn FROM classes c LEFT JOIN divisions d ON c.division_id=d.id WHERE c.is_active=1 AND c.division_id=? ORDER BY c.class_numeric");
    $clsStmt->execute([$divisionId]);
    $classes = $clsStmt->fetchAll();
} else {
    $classes = $db->query("SELECT c.*, d.division_name_bn FROM classes c LEFT JOIN divisions d ON c.division_id=d.id WHERE c.is_active=1 ORDER BY d.sort_order, c.class_numeric")->fetchAll();
}

// বর্তমান পরীক্ষা
$currentExam = null;
foreach ($exams as $e) { if ($e['id'] == $examId) { $currentExam = $e; break; } }

// ছাত্র লোড
$students = [];
$currentClass = null;
if ($classId && $examId) {
    $ci = $db->prepare("SELECT c.*, d.division_name_bn FROM classes c LEFT JOIN divisions d ON c.division_id=d.id WHERE c.id=?");
    $ci->execute([$classId]);
    $currentClass = $ci->fetch();

    $stmt = $db->prepare("SELECT s.*, sec.section_name FROM students s
        LEFT JOIN sections sec ON s.section_id = sec.id
        WHERE s.class_id=? AND s.status='active'
        ORDER BY s.roll_number");
    $stmt->execute([$classId]);
    $students = $stmt->fetchAll();
}

// প্রতিষ্ঠান তথ্য
$instituteName    = getSetting('institute_name', 'An Nazah Tahfizul Quran Madrasah');
$instituteAddress = getSetting('address', 'Bilkis Cottage, Pandhoa Abason, Ashulia, Savar, Dhaka');
$instituteLogo    = getSetting('id_card_logo_b64', '');

require_once '../../includes/header.php';
?>

<?php if (!$printMode): ?>
<!-- ===== কন্ট্রোল প্যানেল ===== -->
<div class="section-header no-print">
    <h2 class="section-title"><i class="fas fa-id-card-alt"></i> প্রবেশপত্র জেনারেটর</h2>
    <?php if (!empty($students)): ?>
    <button onclick="window.print()" class="btn btn-primary">
        <i class="fas fa-print"></i> প্রিন্ট / PDF
    </button>
    <?php endif; ?>
</div>

<!-- Filter -->
<div class="card mb-16 no-print">
    <div class="card-body" style="padding:16px 20px;">
        <form method="GET" id="filterForm" style="display:flex;flex-wrap:wrap;gap:14px;align-items:flex-end;">
            <input type="hidden" name="division_id" id="hiddenDivId" value="<?= $divisionId ?>">

            <!-- পরীক্ষা -->
            <div class="form-group" style="margin:0;flex:2;min-width:200px;">
                <label style="font-size:12px;font-weight:600;">পরীক্ষা নির্বাচন করুন</label>
                <select name="exam_id" class="form-control" style="padding:8px;" onchange="this.form.submit()">
                    <option value="">-- পরীক্ষা বেছে নিন --</option>
                    <?php foreach($exams as $e): ?>
                    <option value="<?=$e['id']?>" <?=$examId==$e['id']?'selected':''?>>
                        <?=e($e['exam_name_bn']??$e['exam_name'])?>
                        (<?=$e['academic_year']?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- বিভাগ -->
            <div class="form-group" style="margin:0;flex:1;min-width:140px;">
                <label style="font-size:12px;font-weight:600;">বিভাগ</label>
                <select class="form-control" style="padding:8px;" onchange="onDivChange(this.value)">
                    <option value="">সব বিভাগ</option>
                    <?php foreach($divisions as $d): ?>
                    <option value="<?=$d['id']?>" <?=$divisionId==$d['id']?'selected':''?>>
                        <?=e($d['division_name_bn'])?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- শ্রেণী -->
            <div class="form-group" style="margin:0;flex:1;min-width:160px;">
                <label style="font-size:12px;font-weight:600;">শ্রেণী</label>
                <select name="class_id" class="form-control" style="padding:8px;" onchange="this.form.submit()">
                    <option value="">শ্রেণী নির্বাচন</option>
                    <?php foreach($classes as $c): ?>
                    <option value="<?=$c['id']?>" <?=$classId==$c['id']?'selected':''?>>
                        <?php if(!$divisionId): ?><?=e($c['division_name_bn']??'')?> → <?php endif; ?>
                        <?=e($c['class_name_bn'])?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if ($classId && $examId && !empty($students)): ?>
            <a href="?exam_id=<?=$examId?>&division_id=<?=$divisionId?>&class_id=<?=$classId?>&print"
               class="btn btn-success btn-sm" target="_blank">
                <i class="fas fa-print"></i> Print Preview
            </a>
            <?php endif; ?>
        </form>
    </div>
</div>

<?php if (!$examId): ?>
<div class="card"><div class="card-body" style="text-align:center;padding:48px;color:var(--text-muted);">
    <i class="fas fa-file-alt" style="font-size:48px;margin-bottom:16px;opacity:.3;display:block;"></i>
    <p style="font-size:16px;">পরীক্ষা নির্বাচন করুন</p>
</div></div>
<?php elseif (!$classId): ?>
<div class="card"><div class="card-body" style="text-align:center;padding:48px;color:var(--text-muted);">
    <i class="fas fa-chalkboard" style="font-size:48px;margin-bottom:16px;opacity:.3;display:block;"></i>
    <p style="font-size:16px;">শ্রেণী নির্বাচন করুন</p>
</div></div>
<?php elseif (empty($students)): ?>
<div class="alert alert-warning">এই শ্রেণীতে কোনো সক্রিয় ছাত্র নেই।</div>
<?php else: ?>
<div class="card mb-16 no-print">
    <div class="card-header">
        <span class="card-title">
            <span style="color:var(--primary);font-weight:700;"><?=e($currentClass['division_name_bn']??'')?></span>
            — <?=e($currentClass['class_name_bn']??'')?>
            | মোট <?=toBanglaNumber(count($students))?> জনের প্রবেশপত্র প্রস্তুত
        </span>
    </div>
</div>
<?php endif; ?>
<?php endif; // !printMode ?>

<?php if (!empty($students) && $currentExam): ?>
<!-- ===== প্রবেশপত্র ===== -->
<div id="admitContainer">
<?php foreach($students as $s): ?>

<div class="admit-card">
    <!-- Watermark -->
    <div class="admit-watermark">
        <?php if($instituteLogo): ?>
        <img src="<?=$instituteLogo?>" alt="" style="width:160px;height:160px;object-fit:contain;opacity:.07;">
        <?php else: ?>
        <i class="fas fa-graduation-cap" style="font-size:120px;color:rgba(26,138,60,.06);"></i>
        <?php endif; ?>
    </div>

    <!-- Header -->
    <div class="admit-header">
        <?php if($instituteLogo): ?>
        <img src="<?=$instituteLogo?>" class="admit-logo" alt="logo">
        <?php endif; ?>
        <div class="admit-inst-name"><?=e($instituteName)?></div>
        <div class="admit-inst-addr"><?=e($instituteAddress)?></div>
    </div>

    <!-- Badge -->
    <div class="admit-badge-wrap">
        <div class="admit-badge">ADMIT CARD</div>
    </div>

    <!-- Exam Name -->
    <div class="admit-exam-name">
        <?=e($currentExam['exam_name_bn'] ?? $currentExam['exam_name'])?>
        <?=$currentExam['academic_year']?>
    </div>

    <!-- Info Fields -->
    <div class="admit-fields">
        <div class="admit-field-row full">
            <span class="admit-field-label">Student Name:</span>
            <span class="admit-field-line"><?=e($s['name_bn'] ?? $s['name'])?></span>
        </div>
        <div class="admit-field-row">
            <span class="admit-field-label">Class:</span>
            <span class="admit-field-line"><?=e($currentClass['class_name_bn']??'')?></span>
        </div>
        <div class="admit-field-row">
            <span class="admit-field-label">Section:</span>
            <span class="admit-field-line"><?=e($s['section_name']??'')?></span>
        </div>
        <div class="admit-field-row">
            <span class="admit-field-label">Roll:</span>
            <span class="admit-field-line"><?=e($s['roll_number']??'')?></span>
        </div>
    </div>

    <!-- Notice -->
    <div class="admit-notice">
        <strong>E.B:</strong> Bring this Admit Card to the Exam. hall
    </div>

    <!-- Signatures -->
    <div class="admit-sigs">
        <div class="admit-sig">
            <div class="admit-sig-line"></div>
            <div class="admit-sig-label">Sign.of Exam-In-Charge</div>
        </div>
        <div class="admit-sig" style="text-align:right;">
            <div class="admit-sig-line" style="margin-left:auto;margin-right:0;"></div>
            <div class="admit-sig-label">Sign.of the Principal</div>
        </div>
    </div>
</div>

<?php endforeach; ?>
</div>
<?php endif; ?>

<style>
@import url('https://fonts.googleapis.com/css2?family=EB+Garamond:wght@400;600;700&family=Hind+Siliguri:wght@400;600;700&display=swap');

/* Container */
#admitContainer {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    justify-content: flex-start;
    margin-top: 16px;
}

/* প্রতিটি কার্ড — A6 landscape (148×105mm) */
.admit-card {
    width: 148mm;
    min-height: 103mm;
    background: #ffffff;
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    padding: 10mm 12mm 8mm;
    position: relative;
    overflow: hidden;
    box-sizing: border-box;
    font-family: 'EB Garamond', 'Libre Baskerville', Georgia, serif;
    box-shadow: 0 4px 20px rgba(0,0,0,.08);
}

/* Watermark */
.admit-watermark {
    position: absolute;
    top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    pointer-events: none;
    z-index: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* সব content এর z-index */
.admit-card > *:not(.admit-watermark) { position: relative; z-index: 1; }

/* Header */
.admit-header {
    text-align: center;
    margin-bottom: 4mm;
}
.admit-logo {
    width: 36px; height: 36px;
    object-fit: contain;
    margin-bottom: 4px;
    display: block;
    margin-left: auto; margin-right: auto;
}
.admit-inst-name {
    font-family: 'EB Garamond', Georgia, serif;
    font-size: 18pt;
    font-weight: 700;
    color: #1a5276;
    line-height: 1.2;
    margin-bottom: 2px;
}
.admit-inst-addr {
    font-size: 9pt;
    color: #4a5568;
    font-family: 'Hind Siliguri', sans-serif;
}

/* Badge */
.admit-badge-wrap {
    text-align: center;
    margin: 3mm 0;
}
.admit-badge {
    display: inline-block;
    background: #1a8a3c;
    color: #ffffff;
    font-family: 'EB Garamond', serif;
    font-size: 11pt;
    font-weight: 700;
    letter-spacing: 2px;
    padding: 5px 22px;
    border-radius: 30px;
}

/* Exam Name */
.admit-exam-name {
    text-align: center;
    font-family: 'EB Garamond', serif;
    font-size: 15pt;
    font-weight: 700;
    color: #1a3a6b;
    margin-bottom: 5mm;
    line-height: 1.3;
}

/* Info Fields */
.admit-fields {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 3mm 4mm;
    margin-bottom: 4mm;
}
.admit-field-row {
    display: flex;
    align-items: baseline;
    gap: 4px;
}
.admit-field-row.full {
    grid-column: 1 / -1;
}
.admit-field-label {
    font-family: 'EB Garamond', serif;
    font-size: 10pt;
    font-weight: 700;
    color: #1a202c;
    white-space: nowrap;
    flex-shrink: 0;
}
.admit-field-line {
    flex: 1;
    border-bottom: 1px dotted #888;
    min-width: 30mm;
    font-size: 10pt;
    color: #1a202c;
    padding-bottom: 1px;
    padding-left: 4px;
    font-family: 'Hind Siliguri', sans-serif;
}

/* Notice */
.admit-notice {
    text-align: center;
    font-size: 10pt;
    font-weight: 700;
    color: #1a202c;
    margin: 3mm 0 4mm;
    font-family: 'EB Garamond', serif;
}

/* Signatures */
.admit-sigs {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    margin-top: 2mm;
    padding-top: 2mm;
    border-top: 1px solid #e2e8f0;
}
.admit-sig { width: 45%; }
.admit-sig-line {
    width: 100%;
    border-top: 1.5px dotted #555;
    margin-bottom: 4px;
}
.admit-sig-label {
    font-size: 8.5pt;
    font-weight: 700;
    color: #1a202c;
    font-family: 'EB Garamond', serif;
}

/* ===== PRINT ===== */
@media print {
    .no-print, .sidebar, .topbar, header, nav { display: none !important; }
    .main-wrapper { margin-left: 0 !important; }
    .content { padding: 0 !important; }
    body, html { margin: 0; padding: 0; background: #fff; }

    #admitContainer {
        display: flex !important;
        flex-wrap: wrap !important;
        gap: 6mm !important;
        padding: 5mm !important;
        margin: 0 !important;
        justify-content: flex-start !important;
    }

    .admit-card {
        box-shadow: none !important;
        border: 1.5px solid #ccc !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
        break-inside: avoid;
        page-break-inside: avoid;
    }
}
@page { size: A4 portrait; margin: 8mm; }
</style>

<script>
function onDivChange(divId) {
    document.getElementById('hiddenDivId').value = divId;
    document.querySelector('select[name="class_id"]').value = '';
    document.getElementById('filterForm').submit();
}
</script>

<?php require_once '../../includes/footer.php'; ?>
