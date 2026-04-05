<?php
require_once '../../includes/functions.php';
requireLogin(['super_admin','principal','teacher']);
$pageTitle = 'পরীক্ষা ও ফলাফল';
$db = getDB();

// নতুন পরীক্ষা যোগ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_exam'])) {
    if (!verifyCsrf($_POST['csrf'] ?? '')) die('CSRF');
    $examNameBn = trim($_POST['exam_name_bn'] ?? '');
    $examName   = trim($_POST['exam_name'] ?? '');
    $examType   = $_POST['exam_type'] ?? 'test';
    $year       = (int)($_POST['academic_year'] ?? date('Y'));
    $startDate  = $_POST['start_date'] ?: null;
    $endDate    = $_POST['end_date'] ?: null;
    if ($examNameBn) {
        $db->prepare("INSERT INTO exams (exam_name,exam_name_bn,exam_type,academic_year,start_date,end_date) VALUES (?,?,?,?,?,?)")
           ->execute([$examName ?: $examNameBn, $examNameBn, $examType, $year, $startDate, $endDate]);
        setFlash('success','পরীক্ষা যোগ হয়েছে!');
    }
    header('Location: index.php'); exit;
}

// পরীক্ষা মুছুন
if (isset($_GET['delete_exam']) && in_array($_SESSION['role_slug'],['super_admin','principal'])) {
    $db->prepare("DELETE FROM exams WHERE id=?")->execute([(int)$_GET['delete_exam']]);
    setFlash('success','পরীক্ষা মুছে ফেলা হয়েছে।');
    header('Location: index.php'); exit;
}

$exams    = $db->query("SELECT * FROM exams ORDER BY academic_year DESC, start_date")->fetchAll();
$classes  = $db->query("SELECT * FROM classes WHERE is_active=1 ORDER BY class_numeric")->fetchAll();
$subjects = $db->query("SELECT * FROM subjects WHERE is_active=1")->fetchAll();

$examId    = (int)($_GET['exam_id'] ?? 0);
$classId   = (int)($_GET['class_id'] ?? 0);
$subjectId = (int)($_GET['subject_id'] ?? 0);

// বর্তমান পরীক্ষার তথ্য
$currentExam = null;
foreach ($exams as $e) { if ($e['id'] == $examId) { $currentExam = $e; break; } }
$isModelTest   = $currentExam && in_array($currentExam['exam_type'], ['test','monthly']);
$isMainExam    = $currentExam && in_array($currentExam['exam_type'], ['half_yearly','annual']);

// মার্ক এন্ট্রি কনফিগ (POST থেকে অথবা session থেকে)
$markConfig = $_SESSION['mark_config'][$examId][$subjectId] ?? [
    'has_written' => 1, 'written_full' => $isModelTest ? 20 : 80,
    'has_oral'    => 0, 'oral_full'    => 0,
    'has_mcq'     => 0, 'mcq_full'     => 0,
];

// কনফিগ সেভ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_config'])) {
    if (!verifyCsrf($_POST['csrf'] ?? '')) die('CSRF');
    $markConfig = [
        'has_written'  => isset($_POST['has_written']) ? 1 : 0,
        'written_full' => (int)($_POST['written_full'] ?? 0),
        'has_oral'     => isset($_POST['has_oral']) ? 1 : 0,
        'oral_full'    => (int)($_POST['oral_full'] ?? 0),
        'has_mcq'      => isset($_POST['has_mcq']) ? 1 : 0,
        'mcq_full'     => (int)($_POST['mcq_full'] ?? 0),
    ];
    $_SESSION['mark_config'][$examId][$subjectId] = $markConfig;
    header("Location: index.php?exam_id=$examId&class_id=$classId&subject_id=$subjectId"); exit;
}

// মার্ক সেভ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_marks'])) {
    if (!verifyCsrf($_POST['csrf'] ?? '')) die('CSRF');
    $postExamId    = (int)$_POST['exam_id'];
    $postSubjectId = (int)$_POST['subject_id'];
    $marks         = $_POST['marks'] ?? [];
    $absents       = $_POST['absent'] ?? [];
    $cfg           = $_SESSION['mark_config'][$postExamId][$postSubjectId] ?? $markConfig;

    $fullMarks = ($cfg['written_full'] ?? 0) + ($cfg['oral_full'] ?? 0) + ($cfg['mcq_full'] ?? 0);
    if ($isModelTest) $fullMarks = 20;

    $db->beginTransaction();
    try {
        foreach ($marks as $studentId => $m) {
            $written  = (float)($m['written'] ?? 0);
            $oral     = (float)($m['oral'] ?? 0);
            $mcq      = (float)($m['mcq'] ?? 0);
            $isAbsent = isset($absents[$studentId]) ? 1 : 0;

            if ($isModelTest) {
                // মডেল টেস্ট — আলাদা টেবিলে
                $total = $written + $oral + $mcq;
                $db->prepare("INSERT INTO model_test_marks
                    (exam_id,student_id,subject_id,written_marks,oral_marks,total_marks,entered_by)
                    VALUES (?,?,?,?,?,?,?)
                    ON DUPLICATE KEY UPDATE written_marks=VALUES(written_marks),
                    oral_marks=VALUES(oral_marks),total_marks=VALUES(total_marks),entered_by=VALUES(entered_by)")
                   ->execute([$postExamId,$studentId,$postSubjectId,$written,$oral,$total,$_SESSION['user_id']]);
            } else {
                // মেইন পরীক্ষা — মডেল টেস্টের গড় অটো যোগ
                $modelAvg = 0;
                if ($isMainExam) {
                    // এই পরীক্ষার জন্য কোন মডেল টেস্টগুলো?
                    $examYear  = $currentExam['academic_year'] ?? date('Y');
                    $modelType = $currentExam['exam_type'] === 'half_yearly' ? ['test','monthly'] : ['test','monthly'];
                    // অর্ধ-বার্ষিকের জন্য ১ম ও ২য়, বার্ষিকের জন্য ৩য় ও ৪র্থ
                    // exam_id কম মানে আগের — অর্ধ-বার্ষিকের আগে ২টা, বার্ষিকের আগে ২টা
                    $modelExams = $db->prepare("SELECT id FROM exams WHERE exam_type IN ('test','monthly') AND academic_year=? AND id < ? ORDER BY id DESC LIMIT 2");
                    $modelExams->execute([$examYear, $postExamId]);
                    $modelExamIds = array_column($modelExams->fetchAll(), 'id');

                    if (!empty($modelExamIds)) {
                        $placeholders = implode(',', array_fill(0, count($modelExamIds), '?'));
                        $modelMarks = $db->prepare("SELECT AVG(total_marks) as avg FROM model_test_marks WHERE student_id=? AND subject_id=? AND exam_id IN ($placeholders)");
                        $modelMarks->execute(array_merge([$studentId, $postSubjectId], $modelExamIds));
                        $modelAvg = round((float)($modelMarks->fetchColumn() ?? 0), 2);
                    }
                }

                $total     = $written + $oral + $mcq;
                $grandTotal= $total + $modelAvg;
                $gradeInfo = $isAbsent ? ['grade'=>'AB','point'=>0] : calculateGrade($grandTotal, 100);

                $db->prepare("INSERT INTO exam_marks
                    (exam_id,student_id,subject_id,written_marks,mcq_marks,practical_marks,total_marks,grade,grade_point,is_absent,entered_by)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?)
                    ON DUPLICATE KEY UPDATE written_marks=VALUES(written_marks),mcq_marks=VALUES(mcq_marks),
                    practical_marks=VALUES(practical_marks),total_marks=VALUES(total_marks),
                    grade=VALUES(grade),grade_point=VALUES(grade_point),is_absent=VALUES(is_absent),entered_by=VALUES(entered_by)")
                   ->execute([$postExamId,$studentId,$postSubjectId,$written,$mcq,$oral,$grandTotal,
                       $gradeInfo['grade'],$gradeInfo['point'],$isAbsent,$_SESSION['user_id']]);
            }
        }

        // মডেল টেস্টে র‍্যাংক আপডেট
        if ($isModelTest) {
            $ranked = $db->prepare("SELECT id FROM model_test_marks WHERE exam_id=? AND subject_id=? ORDER BY total_marks DESC");
            $ranked->execute([$postExamId, $postSubjectId]);
            $rank = 1;
            foreach ($ranked->fetchAll() as $r) {
                $db->prepare("UPDATE model_test_marks SET rank_position=? WHERE id=?")->execute([$rank++, $r['id']]);
            }
        }

        $db->commit();
        setFlash('success','নম্বর সফলভাবে সংরক্ষিত হয়েছে।');
    } catch (Exception $ex) {
        $db->rollBack();
        setFlash('danger','ত্রুটি: '.$ex->getMessage());
    }
    header("Location: index.php?exam_id=$postExamId&class_id=$classId&subject_id=$postSubjectId"); exit;
}

// ছাত্র ও বিদ্যমান নম্বর লোড
$students      = [];
$existingMarks = [];
$modelTestAvgs = [];
if ($examId && $classId && $subjectId) {
    $stmt = $db->prepare("SELECT * FROM students WHERE class_id=? AND status='active' ORDER BY roll_number");
    $stmt->execute([$classId]);
    $students = $stmt->fetchAll();

    if ($isModelTest) {
        $stmt2 = $db->prepare("SELECT * FROM model_test_marks WHERE exam_id=? AND subject_id=?");
        $stmt2->execute([$examId, $subjectId]);
        foreach ($stmt2->fetchAll() as $m) $existingMarks[$m['student_id']] = $m;
    } else {
        $stmt2 = $db->prepare("SELECT * FROM exam_marks WHERE exam_id=? AND subject_id=?");
        $stmt2->execute([$examId, $subjectId]);
        foreach ($stmt2->fetchAll() as $m) $existingMarks[$m['student_id']] = $m;

        // মডেল টেস্টের গড়
        if ($isMainExam && $currentExam) {
            $examYear  = $currentExam['academic_year'] ?? date('Y');
            $modelExams = $db->prepare("SELECT id FROM exams WHERE exam_type IN ('test','monthly') AND academic_year=? AND id < ? ORDER BY id DESC LIMIT 2");
            $modelExams->execute([$examYear, $examId]);
            $modelExamIds = array_column($modelExams->fetchAll(), 'id');
            if (!empty($modelExamIds)) {
                $placeholders = implode(',', array_fill(0, count($modelExamIds), '?'));
                foreach ($students as $s) {
                    $q = $db->prepare("SELECT AVG(total_marks) as avg FROM model_test_marks WHERE student_id=? AND subject_id=? AND exam_id IN ($placeholders)");
                    $q->execute(array_merge([$s['id'], $subjectId], $modelExamIds));
                    $modelTestAvgs[$s['id']] = round((float)($q->fetchColumn() ?? 0), 2);
                }
            }
        }
    }
}

$currentSubject = null;
foreach ($subjects as $s) { if ($s['id'] == $subjectId) { $currentSubject = $s; break; } }

require_once '../../includes/header.php';
?>
<div class="section-header">
    <h2 class="section-title"><i class="fas fa-file-alt"></i> পরীক্ষা ও ফলাফল</h2>
    <div style="display:flex;gap:8px;">
        <button onclick="openModal('addExamModal')" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> নতুন পরীক্ষা</button>
        <a href="result.php" class="btn btn-success btn-sm"><i class="fas fa-trophy"></i> ফলাফল দেখুন</a>
        <a href="subjects.php" class="btn btn-outline btn-sm"><i class="fas fa-book"></i> বিষয়সমূহ</a>
    </div>
</div>

<!-- Filter -->
<div class="card mb-16">
    <div class="card-body" style="padding:14px 20px;">
        <form method="GET" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">
            <div class="form-group" style="flex:1;min-width:160px;margin:0;">
                <label style="font-size:12px;">পরীক্ষা</label>
                <select name="exam_id" class="form-control" style="padding:7px;" onchange="this.form.submit()">
                    <option value="">পরীক্ষা নির্বাচন করুন</option>
                    <?php foreach ($exams as $e): ?>
                    <option value="<?= $e['id'] ?>" <?= $examId == $e['id'] ? 'selected':'' ?>>
                        <?= e($e['exam_name_bn'] ?? $e['exam_name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="flex:1;min-width:160px;margin:0;">
                <label style="font-size:12px;">শ্রেণী</label>
                <select name="class_id" class="form-control" style="padding:7px;" onchange="this.form.submit()">
                    <option value="">শ্রেণী নির্বাচন করুন</option>
                    <?php foreach ($classes as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $classId == $c['id'] ? 'selected':'' ?>><?= e($c['class_name_bn']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="flex:1;min-width:160px;margin:0;">
                <label style="font-size:12px;">বিষয়</label>
                <select name="subject_id" class="form-control" style="padding:7px;" onchange="this.form.submit()">
                    <option value="">বিষয় নির্বাচন করুন</option>
                    <?php foreach ($subjects as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= $subjectId == $s['id'] ? 'selected':'' ?>>
                        <?= e($s['subject_name_bn'] ?? $s['subject_name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
</div>

<?php if ($examId && $classId && $subjectId && !empty($students)): ?>

<!-- নম্বরের অংশ কনফিগারেশন -->
<div class="card mb-16" style="border-left:4px solid var(--primary);">
    <div class="card-header" style="background:#ebf5fb;">
        <span class="card-title"><i class="fas fa-sliders-h"></i>
            <?= $isModelTest ? 'মডেল টেস্ট' : 'মেইন পরীক্ষা' ?> —
            <?= e($currentSubject['subject_name_bn'] ?? '') ?> — নম্বর বিভাজন সেট করুন
        </span>
    </div>
    <div class="card-body">
        <form method="POST" style="display:flex;flex-wrap:wrap;gap:16px;align-items:flex-end;">
            <input type="hidden" name="csrf" value="<?= getCsrfToken() ?>">
            <input type="hidden" name="save_config" value="1">
            <label style="display:flex;align-items:center;gap:8px;font-weight:600;">
                <input type="checkbox" name="has_written" <?= $markConfig['has_written'] ? 'checked' : '' ?>>
                লিখিত
                <input type="number" name="written_full" class="form-control" style="width:65px;padding:5px;"
                    value="<?= $markConfig['written_full'] ?>" min="0" max="<?= $isModelTest ? 20 : 80 ?>"> নম্বর
            </label>
            <label style="display:flex;align-items:center;gap:8px;font-weight:600;">
                <input type="checkbox" name="has_oral" <?= $markConfig['has_oral'] ? 'checked' : '' ?>>
                মৌখিক
                <input type="number" name="oral_full" class="form-control" style="width:65px;padding:5px;"
                    value="<?= $markConfig['oral_full'] ?>" min="0" max="<?= $isModelTest ? 20 : 80 ?>"> নম্বর
            </label>
            <?php if (!$isModelTest): ?>
            <label style="display:flex;align-items:center;gap:8px;font-weight:600;">
                <input type="checkbox" name="has_mcq" <?= $markConfig['has_mcq'] ? 'checked' : '' ?>>
                MCQ
                <input type="number" name="mcq_full" class="form-control" style="width:65px;padding:5px;"
                    value="<?= $markConfig['mcq_full'] ?>" min="0" max="80"> নম্বর
            </label>
            <?php endif; ?>
            <?php if ($isMainExam): ?>
            <div style="background:#eafaf1;padding:8px 14px;border-radius:8px;font-size:13px;font-weight:600;color:var(--success);">
                <i class="fas fa-plus-circle"></i> মডেল টেস্ট গড় = ২০ (অটো)
            </div>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-check"></i> সেট করুন</button>
        </form>
    </div>
</div>

<!-- নম্বর এন্ট্রি টেবিল -->
<form method="POST">
    <input type="hidden" name="csrf" value="<?= getCsrfToken() ?>">
    <input type="hidden" name="save_marks" value="1">
    <input type="hidden" name="exam_id" value="<?= $examId ?>">
    <input type="hidden" name="subject_id" value="<?= $subjectId ?>">
<div class="card">
    <div class="card-header">
        <span class="card-title">
            নম্বর এন্ট্রি — <?= e($currentSubject['subject_name_bn'] ?? '') ?>
            <?php if ($isModelTest): ?>
                <span class="badge badge-warning" style="margin-left:8px;">মডেল টেস্ট — পূর্ণমান: ২০</span>
            <?php else: ?>
                <span class="badge badge-primary" style="margin-left:8px;">পূর্ণমান: ১০০</span>
            <?php endif; ?>
        </span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th><th>ছাত্রের নাম</th><th>রোল</th>
                    <?php if ($markConfig['has_written']): ?><th>লিখিত /<?= $markConfig['written_full'] ?></th><?php endif; ?>
                    <?php if ($markConfig['has_oral']): ?><th>মৌখিক /<?= $markConfig['oral_full'] ?></th><?php endif; ?>
                    <?php if (!$isModelTest && $markConfig['has_mcq']): ?><th>MCQ /<?= $markConfig['mcq_full'] ?></th><?php endif; ?>
                    <?php if ($isMainExam): ?><th>মডেল গড়</th><?php endif; ?>
                    <th>মোট</th>
                    <?php if ($isModelTest): ?><th>র‍্যাংক</th><?php else: ?><th>গ্রেড</th><th>অনুপস্থিত</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($students as $i => $s):
                    $m = $existingMarks[$s['id']] ?? [];
                    $modelAvg = $modelTestAvgs[$s['id']] ?? 0;
                ?>
                <tr id="row<?= $s['id'] ?>">
                    <td style="font-size:12px;color:var(--text-muted);"><?= toBanglaNumber($i+1) ?></td>
                    <td style="font-weight:600;font-size:13px;"><?= e($s['name_bn'] ?? $s['name']) ?></td>
                    <td><?= toBanglaNumber($s['roll_number']) ?></td>
                    <?php if ($markConfig['has_written']): ?>
                    <td><input type="number" name="marks[<?= $s['id'] ?>][written]" class="form-control mark-input"
                        style="width:70px;padding:5px;" value="<?= e($m['written_marks'] ?? '') ?>"
                        min="0" max="<?= $markConfig['written_full'] ?>" step="0.5"
                        onchange="calcTotal(<?= $s['id'] ?>,<?= $modelAvg ?>)"></td>
                    <?php endif; ?>
                    <?php if ($markConfig['has_oral']): ?>
                    <td><input type="number" name="marks[<?= $s['id'] ?>][oral]" class="form-control mark-input"
                        style="width:70px;padding:5px;" value="<?= e($m['oral_marks'] ?? $m['practical_marks'] ?? '') ?>"
                        min="0" max="<?= $markConfig['oral_full'] ?>" step="0.5"
                        onchange="calcTotal(<?= $s['id'] ?>,<?= $modelAvg ?>)"></td>
                    <?php endif; ?>
                    <?php if (!$isModelTest && $markConfig['has_mcq']): ?>
                    <td><input type="number" name="marks[<?= $s['id'] ?>][mcq]" class="form-control mark-input"
                        style="width:70px;padding:5px;" value="<?= e($m['mcq_marks'] ?? '') ?>"
                        min="0" max="<?= $markConfig['mcq_full'] ?>" step="0.5"
                        onchange="calcTotal(<?= $s['id'] ?>,<?= $modelAvg ?>)"></td>
                    <?php endif; ?>
                    <?php if ($isMainExam): ?>
                    <td style="font-weight:600;color:var(--success);"><?= $modelAvg ?></td>
                    <?php endif; ?>
                    <td><span id="total<?= $s['id'] ?>" style="font-weight:700;font-size:14px;color:var(--primary);">
                        <?= $isModelTest ? ($m['total_marks'] ?? 0) : ($m['total_marks'] ?? $modelAvg) ?>
                    </span></td>
                    <?php if ($isModelTest): ?>
                    <td><span style="font-weight:700;color:var(--accent);">
                        <?= !empty($m['rank_position']) ? toBanglaNumber($m['rank_position']).'ম' : '-' ?>
                    </span></td>
                    <?php else: ?>
                    <td><span id="grade<?= $s['id'] ?>" class="badge badge-<?= isset($m['grade'])&&$m['grade']==='F'?'danger':'success' ?>">
                        <?= e($m['grade'] ?? '-') ?>
                    </span></td>
                    <td><input type="checkbox" name="absent[<?= $s['id'] ?>]" value="1"
                        <?= ($m['is_absent']??0)?'checked':'' ?> onchange="toggleAbsent(<?= $s['id'] ?>,this)"></td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer">
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> নম্বর সংরক্ষণ করুন</button>
    </div>
</div>
</form>

<script>
const isModelTest = <?= $isModelTest ? 'true' : 'false' ?>;
function calcTotal(id, modelAvg) {
    const row = document.getElementById('row' + id);
    let total = 0;
    row.querySelectorAll('.mark-input').forEach(i => total += parseFloat(i.value)||0);
    if (!isModelTest) total += modelAvg;
    document.getElementById('total'+id).textContent = total % 1 === 0 ? total : total.toFixed(1);
    if (!isModelTest) {
        const p = total;
        let grade, color;
        if (p>=80){grade='A+';color='badge-success';}
        else if(p>=70){grade='A';color='badge-success';}
        else if(p>=60){grade='A-';color='badge-success';}
        else if(p>=50){grade='B';color='badge-info';}
        else if(p>=40){grade='C';color='badge-info';}
        else if(p>=33){grade='D';color='badge-warning';}
        else{grade='F';color='badge-danger';}
        const g=document.getElementById('grade'+id);
        if(g){g.textContent=grade;g.className='badge '+color;}
    }
}
function toggleAbsent(id,cb){
    const row=document.getElementById('row'+id);
    row.querySelectorAll('.mark-input').forEach(i=>i.disabled=cb.checked);
    if(cb.checked){
        document.getElementById('total'+id).textContent='AB';
        const g=document.getElementById('grade'+id);
        if(g){g.textContent='AB';g.className='badge badge-secondary';}
    }
}
</script>

<?php elseif ($examId && $classId && $subjectId && empty($students)): ?>
<div class="card"><div class="card-body" style="text-align:center;padding:30px;color:var(--text-muted);">এই শ্রেণীতে কোনো সক্রিয় ছাত্র নেই।</div></div>
<?php elseif (!$examId || !$classId || !$subjectId): ?>
<div class="card"><div class="card-body" style="text-align:center;padding:40px;color:var(--text-muted);">
    <i class="fas fa-arrow-up" style="font-size:36px;margin-bottom:12px;display:block;"></i>
    উপরে পরীক্ষা, শ্রেণী ও বিষয় নির্বাচন করুন
</div></div>
<?php endif; ?>


<!-- পরীক্ষার তালিকা -->
<?php if(!empty($exams)): ?>
<div class="card mb-16">
    <div class="card-header">
        <span class="card-title"><i class="fas fa-list"></i> <?= toBanglaNumber(date('Y')) ?> সালের পরীক্ষাসমূহ</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>পরীক্ষার নাম</th><th>ধরন</th><th>শুরু</th><th>শেষ</th><th class="no-print">অ্যাকশন</th></tr></thead>
            <tbody>
                <?php foreach($exams as $e):
                    $typeLabel = ['monthly'=>'মাসিক','half_yearly'=>'অর্ধবার্ষিক','annual'=>'বার্ষিক','test'=>'টেস্ট','special'=>'বিশেষ'][$e['exam_type']] ?? $e['exam_type'];
                ?>
                <tr>
                    <td style="font-weight:600;"><?= e($e['exam_name_bn'] ?: $e['exam_name']) ?></td>
                    <td><span class="badge badge-info" style="font-size:11px;"><?= $typeLabel ?></span></td>
                    <td style="font-size:13px;"><?= $e['start_date'] ? banglaDate($e['start_date']) : '-' ?></td>
                    <td style="font-size:13px;"><?= $e['end_date'] ? banglaDate($e['end_date']) : '-' ?></td>
                    <td class="no-print" style="display:flex;gap:6px;">
                        <a href="?exam_id=<?= $e['id'] ?>" class="btn btn-primary btn-xs"><i class="fas fa-pen"></i> নম্বর দিন</a>
                        <?php if(in_array($_SESSION['role_slug'],['super_admin','principal'])): ?>
                        <a href="?delete_exam=<?= $e['id'] ?>" onclick="return confirm('মুছবেন?')" class="btn btn-danger btn-xs"><i class="fas fa-trash"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- নতুন পরীক্ষা Modal -->
<div class="modal-overlay" id="addExamModal">
    <div class="modal-box" style="max-width:500px;">
        <div class="modal-header">
            <span style="font-weight:700;"><i class="fas fa-plus"></i> নতুন পরীক্ষা যোগ করুন</span>
            <button onclick="closeModal('addExamModal')" class="btn btn-outline btn-xs">✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="csrf" value="<?= getCsrfToken() ?>">
            <input type="hidden" name="add_exam" value="1">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label>পরীক্ষার নাম (বাংলায়) *</label>
                        <input type="text" name="exam_name_bn" class="form-control" placeholder="যেমন: প্রথম সাময়িক পরীক্ষা" required>
                    </div>
                    <div class="form-group">
                        <label>পরীক্ষার নাম (ইংরেজি) *</label>
                        <input type="text" name="exam_name" class="form-control" placeholder="e.g. First Term Exam" required>
                    </div>
                    <div class="form-group">
                        <label>পরীক্ষার ধরন</label>
                        <select name="exam_type" class="form-control">
                            <option value="test">টেস্ট</option>
                            <option value="monthly">মাসিক</option>
                            <option value="half_yearly">অর্ধবার্ষিক</option>
                            <option value="annual">বার্ষিক</option>
                            <option value="special">বিশেষ</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>শিক্ষাবর্ষ</label>
                        <select name="academic_year" class="form-control">
                            <?php foreach([date('Y'), date('Y')-1, date('Y')+1] as $y): ?>
                            <option value="<?= $y ?>" <?= $y == date('Y') ? 'selected' : '' ?>><?= $y ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>শুরুর তারিখ</label>
                        <input type="date" name="start_date" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>শেষের তারিখ</label>
                        <input type="date" name="end_date" class="form-control">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="closeModal('addExamModal')" class="btn btn-outline">বাতিল</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> যোগ করুন</button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
