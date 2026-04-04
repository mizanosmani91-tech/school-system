<?php
require_once '../../includes/functions.php';
requireLogin(['super_admin','principal','teacher']);
$pageTitle = 'পরীক্ষা ও ফলাফল';
$db = getDB();

$exams = $db->query("SELECT * FROM exams WHERE academic_year='".date('Y')."' ORDER BY start_date")->fetchAll();
$classes = $db->query("SELECT * FROM classes WHERE is_active=1 ORDER BY class_numeric")->fetchAll();
$subjects = $db->query("SELECT * FROM subjects WHERE is_active=1")->fetchAll();

$examId = (int)($_GET['exam_id'] ?? 0);
$classId = (int)($_GET['class_id'] ?? 0);
$subjectId = (int)($_GET['subject_id'] ?? 0);

// Save marks
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_marks'])) {
    if (!verifyCsrf($_POST['csrf'] ?? '')) die('CSRF');
    $postExamId = (int)$_POST['exam_id'];
    $postSubjectId = (int)$_POST['subject_id'];
    $marks = $_POST['marks'] ?? [];
    $absents = $_POST['absent'] ?? [];

    $db->beginTransaction();
    try {
        foreach ($marks as $studentId => $m) {
            $written = (float)($m['written'] ?? 0);
            $mcq = (float)($m['mcq'] ?? 0);
            $practical = (float)($m['practical'] ?? 0);
            $total = $written + $mcq + $practical;
            $isAbsent = isset($absents[$studentId]) ? 1 : 0;

            // Get subject full marks
            $fullMarks = 100;
            foreach ($subjects as $s) { if ($s['id'] == $postSubjectId) { $fullMarks = $s['full_marks']; break; } }
            $gradeInfo = $isAbsent ? ['grade'=>'AB','point'=>0] : calculateGrade($total, $fullMarks);

            $stmt = $db->prepare("INSERT INTO exam_marks
                (exam_id, student_id, subject_id, written_marks, mcq_marks, practical_marks, total_marks, grade, grade_point, is_absent, entered_by)
                VALUES (?,?,?,?,?,?,?,?,?,?,?)
                ON DUPLICATE KEY UPDATE written_marks=VALUES(written_marks), mcq_marks=VALUES(mcq_marks),
                practical_marks=VALUES(practical_marks), total_marks=VALUES(total_marks),
                grade=VALUES(grade), grade_point=VALUES(grade_point), is_absent=VALUES(is_absent), entered_by=VALUES(entered_by)");
            $stmt->execute([$postExamId,$studentId,$postSubjectId,$written,$mcq,$practical,$total,
                $gradeInfo['grade'],$gradeInfo['point'],$isAbsent,$_SESSION['user_id']]);
        }
        $db->commit();
        setFlash('success', 'নম্বর সফলভাবে সংরক্ষিত হয়েছে।');
    } catch (Exception $e) {
        $db->rollBack();
        setFlash('danger', 'ত্রুটি: ' . $e->getMessage());
    }
    header("Location: index.php?exam_id=$postExamId&class_id=$classId&subject_id=$postSubjectId");
    exit;
}

// Load students
$students = [];
$existingMarks = [];
if ($examId && $classId && $subjectId) {
    $stmt = $db->prepare("SELECT * FROM students WHERE class_id=? AND status='active' ORDER BY roll_number");
    $stmt->execute([$classId]);
    $students = $stmt->fetchAll();

    $stmt2 = $db->prepare("SELECT * FROM exam_marks WHERE exam_id=? AND subject_id=?");
    $stmt2->execute([$examId, $subjectId]);
    foreach ($stmt2->fetchAll() as $m) $existingMarks[$m['student_id']] = $m;
}

require_once '../../includes/header.php';
?>
<div class="section-header">
    <h2 class="section-title"><i class="fas fa-file-alt"></i> পরীক্ষা ও ফলাফল</h2>
    <div style="display:flex;gap:8px;">
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

<?php if ($examId && $classId && $subjectId && !empty($students)):
    $currentSubject = null;
    foreach ($subjects as $s) { if ($s['id'] == $subjectId) { $currentSubject = $s; break; } }
?>

<form method="POST">
    <input type="hidden" name="csrf" value="<?= getCsrfToken() ?>">
    <input type="hidden" name="save_marks" value="1">
    <input type="hidden" name="exam_id" value="<?= $examId ?>">
    <input type="hidden" name="subject_id" value="<?= $subjectId ?>">

<div class="card">
    <div class="card-header">
        <span class="card-title">
            নম্বর এন্ট্রি &mdash; <?= e($currentSubject['subject_name_bn'] ?? '') ?>
            (পূর্ণমান: <?= toBanglaNumber($currentSubject['full_marks'] ?? 100) ?>, পাস: <?= toBanglaNumber($currentSubject['pass_marks'] ?? 33) ?>)
        </span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>ছাত্রের নাম</th>
                    <th>রোল</th>
                    <th>লিখিত</th>
                    <th>MCQ</th>
                    <th>ব্যবহারিক</th>
                    <th>মোট</th>
                    <th>গ্রেড</th>
                    <th>অনুপস্থিত</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($students as $i => $s):
                    $m = $existingMarks[$s['id']] ?? [];
                ?>
                <tr id="markRow<?= $s['id'] ?>">
                    <td style="font-size:12px;color:var(--text-muted);"><?= toBanglaNumber($i+1) ?></td>
                    <td>
                        <div style="font-weight:600;font-size:13px;"><?= e($s['name_bn'] ?? $s['name']) ?></div>
                    </td>
                    <td><?= toBanglaNumber($s['roll_number']) ?></td>
                    <td>
                        <input type="number" name="marks[<?= $s['id'] ?>][written]"
                            class="form-control mark-input" style="width:70px;padding:5px;"
                            value="<?= e($m['written_marks'] ?? '') ?>"
                            min="0" max="<?= $currentSubject['full_marks'] ?>" step="0.5"
                            onchange="calcRowTotal(<?= $s['id'] ?>)">
                    </td>
                    <td>
                        <input type="number" name="marks[<?= $s['id'] ?>][mcq]"
                            class="form-control mark-input" style="width:70px;padding:5px;"
                            value="<?= e($m['mcq_marks'] ?? '') ?>"
                            min="0" max="<?= $currentSubject['full_marks'] ?>" step="0.5"
                            onchange="calcRowTotal(<?= $s['id'] ?>)">
                    </td>
                    <td>
                        <input type="number" name="marks[<?= $s['id'] ?>][practical]"
                            class="form-control mark-input" style="width:70px;padding:5px;"
                            value="<?= e($m['practical_marks'] ?? '') ?>"
                            min="0" max="<?= $currentSubject['full_marks'] ?>" step="0.5"
                            onchange="calcRowTotal(<?= $s['id'] ?>)">
                    </td>
                    <td>
                        <span id="total<?= $s['id'] ?>" style="font-weight:700;font-size:14px;color:var(--primary);">
                            <?= e($m['total_marks'] ?? 0) ?>
                        </span>
                    </td>
                    <td>
                        <span id="grade<?= $s['id'] ?>" class="badge <?= isset($m['grade']) && $m['grade'] === 'F' ? 'badge-danger' : 'badge-success' ?>">
                            <?= e($m['grade'] ?? '-') ?>
                        </span>
                    </td>
                    <td>
                        <input type="checkbox" name="absent[<?= $s['id'] ?>]" value="1"
                            <?= ($m['is_absent'] ?? 0) ? 'checked' : '' ?>
                            onchange="toggleAbsent(<?= $s['id'] ?>, this)">
                    </td>
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
const fullMarks = <?= $currentSubject['full_marks'] ?? 100 ?>;
const passMarks = <?= $currentSubject['pass_marks'] ?? 33 ?>;

function calcRowTotal(id) {
    const row = document.getElementById('markRow' + id);
    const inputs = row.querySelectorAll('.mark-input');
    let total = 0;
    inputs.forEach(i => total += parseFloat(i.value) || 0);
    document.getElementById('total' + id).textContent = total;

    let grade, color;
    const p = (total / fullMarks) * 100;
    if (p >= 80) { grade='A+'; color='badge-success'; }
    else if (p >= 70) { grade='A'; color='badge-success'; }
    else if (p >= 60) { grade='A-'; color='badge-success'; }
    else if (p >= 50) { grade='B'; color='badge-info'; }
    else if (p >= 40) { grade='C'; color='badge-info'; }
    else if (p >= 33) { grade='D'; color='badge-warning'; }
    else { grade='F'; color='badge-danger'; }

    const gradeEl = document.getElementById('grade' + id);
    gradeEl.textContent = grade;
    gradeEl.className = 'badge ' + color;
}

function toggleAbsent(id, cb) {
    const row = document.getElementById('markRow' + id);
    row.querySelectorAll('.mark-input').forEach(i => { i.disabled = cb.checked; });
    if (cb.checked) {
        document.getElementById('total' + id).textContent = 'AB';
        document.getElementById('grade' + id).textContent = 'AB';
        document.getElementById('grade' + id).className = 'badge badge-secondary';
    } else {
        calcRowTotal(id);
    }
}
</script>

<?php elseif (!$examId || !$classId || !$subjectId): ?>
<div class="card"><div class="card-body" style="text-align:center;padding:40px;color:var(--text-muted);">
    <i class="fas fa-arrow-up" style="font-size:36px;margin-bottom:12px;"></i>
    <p>উপরে পরীক্ষা, শ্রেণী ও বিষয় নির্বাচন করুন</p>
</div></div>
<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>
