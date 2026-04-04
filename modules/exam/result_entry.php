<?php
require_once '../../includes/functions.php';
requireLogin(['super_admin','principal','teacher']);
$pageTitle = 'ফলাফল এন্ট্রি';
$db = getDB();

$classes  = $db->query("SELECT * FROM classes WHERE is_active=1 ORDER BY class_numeric")->fetchAll();
$yearList = [date('Y'), date('Y')-1, date('Y')+1];

$filterClass = (int)($_GET['class_id'] ?? 0);
$filterYear  = $_GET['year'] ?? date('Y');
$filterExam  = (int)($_GET['exam_id'] ?? 0);

// Exams for selected class
$exams = [];
if ($filterClass) {
    $exStmt = $db->prepare("SELECT * FROM exams WHERE (class_id=? OR class_id IS NULL) AND academic_year=? ORDER BY FIELD(exam_type,'test','half_yearly','annual'), sequence_no");
    $exStmt->execute([$filterClass, $filterYear]);
    $exams = $exStmt->fetchAll();
}

// Subjects for class
$subjects = [];
if ($filterClass) {
    $subStmt = $db->prepare("SELECT s.* FROM subjects s JOIN class_subjects cs ON s.id=cs.subject_id WHERE cs.class_id=? AND s.is_active=1 ORDER BY s.subject_name_bn");
    $subStmt->execute([$filterClass]);
    $subjects = $subStmt->fetchAll();
}

// Students for class
$students = [];
if ($filterClass) {
    $stStmt = $db->prepare("SELECT * FROM students WHERE class_id=? AND status='active' AND academic_year=? ORDER BY roll_number");
    $stStmt->execute([$filterClass, $filterYear]);
    $students = $stStmt->fetchAll();
}

// Active exam info
$activeExam = null;
if ($filterExam) {
    $aeStmt = $db->prepare("SELECT * FROM exams WHERE id=?");
    $aeStmt->execute([$filterExam]);
    $activeExam = $aeStmt->fetch();
}

// Save marks
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_marks'])) {
    if (!verifyCsrf($_POST['csrf'] ?? '')) die('CSRF');
    $examId   = (int)$_POST['exam_id'];
    $classId  = (int)$_POST['class_id'];
    $year     = $_POST['year'];
    $marksArr = $_POST['marks'] ?? [];

    $examInfo = $db->prepare("SELECT * FROM exams WHERE id=?");
    $examInfo->execute([$examId]);
    $examInfo = $examInfo->fetch();
    $isModel  = $examInfo['exam_type'] === 'test';
    $fullMark = $isModel ? 20 : 80;

    foreach ($marksArr as $studentId => $subMarks) {
        $studentId = (int)$studentId;
        foreach ($subMarks as $subjectId => $marks) {
            $subjectId  = (int)$subjectId;
            $isAbsent   = isset($_POST['absent'][$studentId][$subjectId]) ? 1 : 0;
            $totalMarks = $isAbsent ? 0 : min((float)$marks, $fullMark);

            // Grade only for half_yearly and annual
            $grade = null; $gradePoint = null;
            if (!$isModel && !$isAbsent) {
                // Will be calculated in final result, not here
            }

            $existing = $db->prepare("SELECT id FROM exam_marks WHERE exam_id=? AND student_id=? AND subject_id=?");
            $existing->execute([$examId, $studentId, $subjectId]);

            if ($existing->fetch()) {
                $db->prepare("UPDATE exam_marks SET total_marks=?, is_absent=?, entered_by=? WHERE exam_id=? AND student_id=? AND subject_id=?")
                   ->execute([$totalMarks, $isAbsent, $_SESSION['user_id'], $examId, $studentId, $subjectId]);
            } else {
                $db->prepare("INSERT INTO exam_marks (exam_id, student_id, subject_id, total_marks, is_absent, entered_by) VALUES (?,?,?,?,?,?)")
                   ->execute([$examId, $studentId, $subjectId, $totalMarks, $isAbsent, $_SESSION['user_id']]);
            }
        }
    }
    setFlash('success', 'নম্বর সংরক্ষিত হয়েছে।');
    header("Location: result_entry.php?class_id=$classId&year=$year&exam_id=$examId");
    exit;
}

// Calculate & save final results
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['calculate_final'])) {
    if (!verifyCsrf($_POST['csrf'] ?? '')) die('CSRF');
    $classId = (int)$_POST['class_id'];
    $year    = $_POST['year'];

    // Get all exams for this class/year
    $allExams = $db->prepare("SELECT * FROM exams WHERE (class_id=? OR class_id IS NULL) AND academic_year=? AND is_published=1");
    $allExams->execute([$classId, $year]);
    $allExams = $allExams->fetchAll();

    // Categorize exams
    $examMap = ['model1'=>null,'model2'=>null,'half_yearly'=>null,'model3'=>null,'model4'=>null,'annual'=>null];
    foreach ($allExams as $ex) {
        if ($ex['exam_type'] === 'test') {
            $seq = (int)$ex['sequence_no'];
            if ($seq === 1) $examMap['model1'] = $ex['id'];
            elseif ($seq === 2) $examMap['model2'] = $ex['id'];
            elseif ($seq === 3) $examMap['model3'] = $ex['id'];
            elseif ($seq === 4) $examMap['model4'] = $ex['id'];
        } elseif ($ex['exam_type'] === 'half_yearly') {
            $examMap['half_yearly'] = $ex['id'];
        } elseif ($ex['exam_type'] === 'annual') {
            $examMap['annual'] = $ex['id'];
        }
    }

    $stList = $db->prepare("SELECT id FROM students WHERE class_id=? AND status='active' AND academic_year=?");
    $stList->execute([$classId, $year]);
    $stList = $stList->fetchAll(PDO::FETCH_COLUMN);

    $subList = $db->prepare("SELECT subject_id FROM class_subjects WHERE class_id=?");
    $subList->execute([$classId]);
    $subList = $subList->fetchAll(PDO::FETCH_COLUMN);

    // Grade function
    $getGrade = function($marks) {
        if ($marks >= 80) return ['A+', 5.00];
        if ($marks >= 70) return ['A',  4.00];
        if ($marks >= 60) return ['A-', 3.50];
        if ($marks >= 50) return ['B',  3.00];
        if ($marks >= 40) return ['C',  2.00];
        if ($marks >= 33) return ['D',  1.00];
        return ['F', 0.00];
    };

    $getMark = function($examId, $studentId, $subjectId) use ($db) {
        if (!$examId) return null;
        $s = $db->prepare("SELECT total_marks, is_absent FROM exam_marks WHERE exam_id=? AND student_id=? AND subject_id=?");
        $s->execute([$examId, $studentId, $subjectId]);
        $r = $s->fetch();
        return $r ? ($r['is_absent'] ? null : (float)$r['total_marks']) : null;
    };

    foreach ($stList as $studentId) {
        foreach ($subList as $subjectId) {
            $m1 = $getMark($examMap['model1'],     $studentId, $subjectId) ?? 0;
            $m2 = $getMark($examMap['model2'],     $studentId, $subjectId) ?? 0;
            $hy = $getMark($examMap['half_yearly'], $studentId, $subjectId) ?? 0;
            $m3 = $getMark($examMap['model3'],     $studentId, $subjectId) ?? 0;
            $m4 = $getMark($examMap['model4'],     $studentId, $subjectId) ?? 0;
            $an = $getMark($examMap['annual'],     $studentId, $subjectId) ?? 0;

            // Half yearly total = written(80) + model avg(20)
            $hyModelAvg   = ($m1 + $m2) / 2;
            $hyTotal      = $hy + $hyModelAvg;
            [$hyGrade, $hyGP] = $getGrade($hyTotal);

            // Annual total = written(80) + model avg(20)
            $anModelAvg   = ($m3 + $m4) / 2;
            $anTotal      = $an + $anModelAvg;
            [$anGrade, $anGP] = $getGrade($anTotal);

            // Final = (half_yearly_total + annual_total) / 2
            $finalMarks   = ($hyTotal + $anTotal) / 2;
            [$finalGrade, $finalGP] = $getGrade($finalMarks);
            $isPassed     = $finalGrade !== 'F' ? 1 : 0;

            $db->prepare("INSERT INTO final_results
                (student_id, class_id, academic_year, subject_id,
                 model1_marks, model2_marks, half_yearly_marks, half_yearly_total, half_yearly_grade, half_yearly_grade_point,
                 model3_marks, model4_marks, annual_marks, annual_total, annual_grade, annual_grade_point,
                 final_marks, final_grade, final_grade_point, is_passed)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                ON DUPLICATE KEY UPDATE
                model1_marks=VALUES(model1_marks), model2_marks=VALUES(model2_marks),
                half_yearly_marks=VALUES(half_yearly_marks), half_yearly_total=VALUES(half_yearly_total),
                half_yearly_grade=VALUES(half_yearly_grade), half_yearly_grade_point=VALUES(half_yearly_grade_point),
                model3_marks=VALUES(model3_marks), model4_marks=VALUES(model4_marks),
                annual_marks=VALUES(annual_marks), annual_total=VALUES(annual_total),
                annual_grade=VALUES(annual_grade), annual_grade_point=VALUES(annual_grade_point),
                final_marks=VALUES(final_marks), final_grade=VALUES(final_grade),
                final_grade_point=VALUES(final_grade_point), is_passed=VALUES(is_passed)")
               ->execute([
                    $studentId, $classId, $year, $subjectId,
                    $m1, $m2, $hy, round($hyTotal, 2), $hyGrade, $hyGP,
                    $m3, $m4, $an, round($anTotal, 2), $anGrade, $anGP,
                    round($finalMarks, 2), $finalGrade, $finalGP, $isPassed
               ]);
        }
    }

    // Calculate merit position based on total final_marks
    $meritStmt = $db->prepare("SELECT student_id, SUM(final_marks) as total FROM final_results WHERE class_id=? AND academic_year=? GROUP BY student_id ORDER BY total DESC");
    $meritStmt->execute([$classId, $year]);
    $meritList = $meritStmt->fetchAll();
    $pos = 1;
    foreach ($meritList as $m) {
        $db->prepare("UPDATE final_results SET merit_position=? WHERE student_id=? AND class_id=? AND academic_year=?")
           ->execute([$pos++, $m['student_id'], $classId, $year]);
    }

    setFlash('success', 'চূড়ান্ত ফলাফল গণনা সম্পন্ন হয়েছে! মেধাক্রম নির্ধারিত হয়েছে।');
    header("Location: result_entry.php?class_id=$classId&year=$year");
    exit;
}

// Load existing marks for display
$existingMarks = [];
if ($filterExam && $filterClass) {
    $emStmt = $db->prepare("SELECT * FROM exam_marks WHERE exam_id=? AND student_id IN (SELECT id FROM students WHERE class_id=? AND status='active')");
    $emStmt->execute([$filterExam, $filterClass]);
    foreach ($emStmt->fetchAll() as $em) {
        $existingMarks[$em['student_id']][$em['subject_id']] = $em;
    }
}

require_once '../../includes/header.php';
?>

<div class="section-header">
    <h2 class="section-title"><i class="fas fa-clipboard-list"></i> ফলাফল এন্ট্রি</h2>
    <a href="result.php" class="btn btn-outline btn-sm"><i class="fas fa-chart-bar"></i> ফলাফল দেখুন</a>
</div>

<!-- Filter -->
<div class="card mb-16">
    <div class="card-body" style="padding:14px 20px;">
        <form method="GET" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">
            <div class="form-group" style="margin:0;flex:1;min-width:140px;">
                <label style="font-size:12px;">শ্রেণী</label>
                <select name="class_id" class="form-control" style="padding:7px;" onchange="this.form.submit()">
                    <option value="">শ্রেণী নির্বাচন</option>
                    <?php foreach ($classes as $c): ?>
                    <option value="<?=$c['id']?>" <?=$filterClass==$c['id']?'selected':''?>><?=e($c['class_name_bn'])?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0;flex:1;min-width:120px;">
                <label style="font-size:12px;">শিক্ষাবর্ষ</label>
                <select name="year" class="form-control" style="padding:7px;" onchange="this.form.submit()">
                    <?php foreach ($yearList as $y): ?>
                    <option value="<?=$y?>" <?=$filterYear==$y?'selected':''?>><?=$y?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($filterClass && !empty($exams)): ?>
            <div class="form-group" style="margin:0;flex:2;min-width:180px;">
                <label style="font-size:12px;">পরীক্ষা নির্বাচন</label>
                <select name="exam_id" class="form-control" style="padding:7px;" onchange="this.form.submit()">
                    <option value="">পরীক্ষা নির্বাচন করুন</option>
                    <?php foreach ($exams as $ex): ?>
                    <option value="<?=$ex['id']?>" <?=$filterExam==$ex['id']?'selected':''?>>
                        <?=e($ex['exam_name_bn'])?> (<?=['test'=>'মডেল টেস্ট','half_yearly'=>'অর্ধ বার্ষিক','annual'=>'বার্ষিক'][$ex['exam_type']]??$ex['exam_type']?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<?php if ($filterClass && empty($exams)): ?>
<div class="card mb-16">
    <div class="card-body" style="text-align:center;padding:30px;color:#718096;">
        <i class="fas fa-exclamation-circle" style="font-size:32px;margin-bottom:10px;display:block;"></i>
        এই শ্রেণীর জন্য কোনো পরীক্ষা তৈরি করা হয়নি।<br>
        <a href="<?=BASE_URL?>/modules/exam/index.php" class="btn btn-primary btn-sm" style="margin-top:12px;">পরীক্ষা তৈরি করুন</a>
    </div>
</div>
<?php endif; ?>

<?php if ($filterClass && $filterExam && $activeExam && !empty($students) && !empty($subjects)): ?>
<?php
$isModel   = $activeExam['exam_type'] === 'test';
$fullMark  = $isModel ? 20 : 80;
$examLabel = ['test'=>'মডেল টেস্ট','half_yearly'=>'অর্ধ বার্ষিক','annual'=>'বার্ষিক'][$activeExam['exam_type']] ?? '';
?>
<div class="card mb-16">
    <div class="card-header">
        <span class="card-title">
            <i class="fas fa-edit"></i>
            <?=e($activeExam['exam_name_bn'])?> — <?=$examLabel?> (পূর্ণমান: <?=$fullMark?>)
        </span>
        <?php if ($isModel): ?>
        <span class="badge badge-info">গ্রেড নেই — শুধু নম্বর</span>
        <?php else: ?>
        <span class="badge badge-success">গ্রেডসহ</span>
        <?php endif; ?>
    </div>
    <form method="POST">
        <input type="hidden" name="csrf" value="<?=getCsrfToken()?>">
        <input type="hidden" name="save_marks" value="1">
        <input type="hidden" name="exam_id" value="<?=$filterExam?>">
        <input type="hidden" name="class_id" value="<?=$filterClass?>">
        <input type="hidden" name="year" value="<?=$filterYear?>">
        <div style="overflow-x:auto;">
            <table style="min-width:700px;">
                <thead>
                    <tr>
                        <th style="width:40px;">#</th>
                        <th>ছাত্রের নাম</th>
                        <?php foreach ($subjects as $sub): ?>
                        <th style="text-align:center;min-width:90px;">
                            <?=e($sub['subject_name_bn'])?><br>
                            <span style="font-size:10px;font-weight:400;opacity:.8;">/<?=$fullMark?></span>
                        </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $i => $st): ?>
                    <tr>
                        <td style="color:#718096;"><?=toBanglaNumber($st['roll_number']??($i+1))?></td>
                        <td>
                            <div style="font-weight:600;font-size:13px;"><?=e($st['name_bn']??$st['name'])?></div>
                            <div style="font-size:11px;color:#718096;"><?=e($st['student_id'])?></div>
                        </td>
                        <?php foreach ($subjects as $sub): ?>
                        <?php $em = $existingMarks[$st['id']][$sub['id']] ?? null; ?>
                        <td style="text-align:center;padding:6px;">
                            <input type="number"
                                name="marks[<?=$st['id']?>][<?=$sub['id']?>]"
                                class="form-control mark-input"
                                style="text-align:center;padding:5px;width:70px;margin:0 auto;"
                                min="0" max="<?=$fullMark?>" step="0.5"
                                value="<?=$em && !$em['is_absent'] ? $em['total_marks'] : ''?>"
                                <?=$em && $em['is_absent'] ? 'disabled' : ''?>>
                            <label style="font-size:10px;color:#e74c3c;display:flex;align-items:center;justify-content:center;gap:3px;margin-top:3px;cursor:pointer;">
                                <input type="checkbox" name="absent[<?=$st['id']?>][<?=$sub['id']?>]" value="1"
                                    <?=$em && $em['is_absent'] ? 'checked' : ''?>
                                    onchange="toggleAbsent(this)">
                                অনুপস্থিত
                            </label>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div style="padding:16px;display:flex;gap:10px;justify-content:flex-end;border-top:1px solid var(--border);">
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> নম্বর সংরক্ষণ</button>
        </div>
    </form>
</div>
<?php endif; ?>

<?php if ($filterClass && $filterYear): ?>
<!-- Calculate Final Result -->
<div class="card" style="border:2px solid #27ae60;">
    <div class="card-body" style="padding:16px 20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
        <div>
            <div style="font-weight:700;font-size:15px;color:#0d2137;"><i class="fas fa-calculator"></i> চূড়ান্ত ফলাফল গণনা</div>
            <div style="font-size:12px;color:#718096;margin-top:4px;">
                সব পরীক্ষার নম্বর এন্ট্রির পর এখানে click করুন — মডেল গড়, অর্ধ বার্ষিক, বার্ষিক ও ফাইনাল result + মেধাক্রম auto-calculate হবে।
            </div>
        </div>
        <form method="POST">
            <input type="hidden" name="csrf" value="<?=getCsrfToken()?>">
            <input type="hidden" name="calculate_final" value="1">
            <input type="hidden" name="class_id" value="<?=$filterClass?>">
            <input type="hidden" name="year" value="<?=$filterYear?>">
            <button type="submit" class="btn btn-success" onclick="return confirm('চূড়ান্ত ফলাফল গণনা করবেন?')">
                <i class="fas fa-calculator"></i> ফলাফল গণনা করুন
            </button>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
function toggleAbsent(cb) {
    const input = cb.closest('td').querySelector('.mark-input');
    if (cb.checked) { input.value = ''; input.disabled = true; }
    else { input.disabled = false; input.focus(); }
}
</script>

<?php require_once '../../includes/footer.php'; ?>
