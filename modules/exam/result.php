<?php
require_once '../../includes/functions.php';
requireLogin();
$pageTitle = 'পরীক্ষার ফলাফল';
$db = getDB();

$classes  = $db->query("SELECT * FROM classes WHERE is_active=1 ORDER BY class_numeric")->fetchAll();
$filterClass  = (int)($_GET['class_id'] ?? 0);
$filterYear   = $_GET['year'] ?? date('Y');
$filterType   = $_GET['type'] ?? 'final'; // final, half_yearly, annual, model
$studentId    = (int)($_GET['student_id'] ?? 0);

// Subjects
$subjects = [];
if ($filterClass) {
    $subStmt = $db->prepare("SELECT s.* FROM subjects s JOIN class_subjects cs ON s.id=cs.subject_id WHERE cs.class_id=? AND s.is_active=1 ORDER BY s.subject_name_bn");
    $subStmt->execute([$filterClass]);
    $subjects = $subStmt->fetchAll();
}

// Students
$students = [];
if ($filterClass) {
    $stStmt = $db->prepare("SELECT * FROM students WHERE class_id=? AND status='active' AND academic_year=? ORDER BY roll_number");
    $stStmt->execute([$filterClass, $filterYear]);
    $students = $stStmt->fetchAll();
}

// Load results
$results = [];
if ($filterClass && $filterType !== 'model') {
    $rStmt = $db->prepare("SELECT fr.*, s.name_bn, s.name, s.roll_number, s.student_id as sid,
        sub.subject_name_bn
        FROM final_results fr
        JOIN students s ON fr.student_id = s.id
        JOIN subjects sub ON fr.subject_id = sub.id
        WHERE fr.class_id=? AND fr.academic_year=?
        ORDER BY fr.merit_position, s.roll_number, sub.subject_name_bn");
    $rStmt->execute([$filterClass, $filterYear]);
    foreach ($rStmt->fetchAll() as $r) {
        $results[$r['student_id']][$r['subject_id']] = $r;
    }
}

// Model test results
$modelResults = [];
if ($filterClass && $filterType === 'model') {
    $exStmt = $db->prepare("SELECT * FROM exams WHERE (class_id=? OR class_id IS NULL) AND academic_year=? AND exam_type='test' ORDER BY sequence_no");
    $exStmt->execute([$filterClass, $filterYear]);
    $modelExams = $exStmt->fetchAll();

    foreach ($modelExams as $mx) {
        $mStmt = $db->prepare("SELECT em.*, s.name_bn, s.name, s.roll_number, sub.subject_name_bn
            FROM exam_marks em
            JOIN students s ON em.student_id = s.id
            JOIN subjects sub ON em.subject_id = sub.id
            WHERE em.exam_id=? AND s.class_id=?
            ORDER BY s.roll_number, sub.subject_name_bn");
        $mStmt->execute([$mx['id'], $filterClass]);
        $modelResults[$mx['id']] = ['exam' => $mx, 'marks' => $mStmt->fetchAll()];
    }
}

// Single student report card
$reportStudent = null;
$reportResults = [];
if ($studentId) {
    $rsStmt = $db->prepare("SELECT * FROM students WHERE id=?");
    $rsStmt->execute([$studentId]);
    $reportStudent = $rsStmt->fetch();

    $rrStmt = $db->prepare("SELECT fr.*, sub.subject_name_bn FROM final_results fr
        JOIN subjects sub ON fr.subject_id = sub.id
        WHERE fr.student_id=? AND fr.academic_year=? ORDER BY sub.subject_name_bn");
    $rrStmt->execute([$studentId, $filterYear]);
    $reportResults = $rrStmt->fetchAll();
}

require_once '../../includes/header.php';

// Helper
function gradeBadge($grade) {
    $map = ['A+'=>'success','A'=>'success','A-'=>'info','B'=>'info','C'=>'warning','D'=>'warning','F'=>'danger'];
    $cls = $map[$grade] ?? 'secondary';
    return "<span class='badge badge-$cls'>$grade</span>";
}
?>

<style>
.report-card { max-width:780px; margin:0 auto; background:#fff; border:2px solid #1a5276; border-radius:12px; overflow:hidden; }
.report-header { background:linear-gradient(135deg,#1a5276,#0d2137); color:#fff; padding:20px 24px; text-align:center; }
.report-header h2 { font-size:20px; font-weight:700; }
.report-header p { font-size:13px; opacity:.8; margin-top:4px; }
.report-student-info { display:grid; grid-template-columns:1fr 1fr; gap:0; border-bottom:2px solid #1a5276; }
.info-cell { padding:10px 16px; border-right:1px solid #e2e8f0; font-size:13px; }
.info-cell:nth-child(even) { border-right:none; }
.info-label { color:#718096; font-size:11px; }
.info-value { font-weight:700; color:#0d2137; margin-top:2px; }
.result-section { padding:16px; border-bottom:1px solid #e2e8f0; }
.result-section h3 { font-size:14px; font-weight:700; color:#1a5276; margin-bottom:12px; display:flex; align-items:center; gap:8px; }
.marks-table { width:100%; border-collapse:collapse; font-size:12px; }
.marks-table th { background:#1a5276; color:#fff; padding:7px 10px; text-align:center; }
.marks-table td { padding:7px 10px; border-bottom:1px solid #e2e8f0; text-align:center; }
.marks-table tr:hover { background:#f7fafc; }
.final-box { background:linear-gradient(135deg,#1a5276,#0d2137); color:#fff; padding:16px 24px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; }
.final-stat { text-align:center; }
.final-stat .val { font-size:24px; font-weight:700; }
.final-stat .lbl { font-size:11px; opacity:.7; margin-top:2px; }
.pass-badge { padding:8px 20px; border-radius:20px; font-weight:700; font-size:15px; }
.pass-badge.pass { background:#27ae60; }
.pass-badge.fail { background:#e74c3c; }
@media print {
    .no-print { display:none!important; }
    body { background:#fff; }
    .report-card { border:1px solid #000; box-shadow:none; }
}
</style>

<div class="section-header no-print">
    <h2 class="section-title"><i class="fas fa-chart-bar"></i> পরীক্ষার ফলাফল</h2>
    <div style="display:flex;gap:8px;">
        <?php if (in_array($_SESSION['role_slug'],['super_admin','principal','teacher'])): ?>
        <a href="result_entry.php" class="btn btn-primary btn-sm"><i class="fas fa-edit"></i> নম্বর এন্ট্রি</a>
        <?php endif; ?>
        <?php if ($studentId): ?>
        <button onclick="window.print()" class="btn btn-outline btn-sm"><i class="fas fa-print"></i> প্রিন্ট</button>
        <?php endif; ?>
    </div>
</div>

<!-- Filter -->
<div class="card mb-16 no-print">
    <div class="card-body" style="padding:12px 20px;">
        <form method="GET" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">
            <div class="form-group" style="margin:0;flex:1;min-width:130px;">
                <label style="font-size:12px;">শ্রেণী</label>
                <select name="class_id" class="form-control" style="padding:7px;" onchange="this.form.submit()">
                    <option value="">শ্রেণী নির্বাচন</option>
                    <?php foreach ($classes as $c): ?>
                    <option value="<?=$c['id']?>" <?=$filterClass==$c['id']?'selected':''?>><?=e($c['class_name_bn'])?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0;flex:1;min-width:110px;">
                <label style="font-size:12px;">বর্ষ</label>
                <select name="year" class="form-control" style="padding:7px;" onchange="this.form.submit()">
                    <?php foreach ([date('Y'), date('Y')-1] as $y): ?>
                    <option value="<?=$y?>" <?=$filterYear==$y?'selected':''?>><?=$y?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0;flex:1;min-width:150px;">
                <label style="font-size:12px;">ফলাফলের ধরন</label>
                <select name="type" class="form-control" style="padding:7px;" onchange="this.form.submit()">
                    <option value="final"      <?=$filterType==='final'?'selected':''?>>চূড়ান্ত ফলাফল</option>
                    <option value="half_yearly" <?=$filterType==='half_yearly'?'selected':''?>>অর্ধ বার্ষিক</option>
                    <option value="annual"     <?=$filterType==='annual'?'selected':''?>>বার্ষিক</option>
                    <option value="model"      <?=$filterType==='model'?'selected':''?>>মডেল টেস্ট</option>
                </select>
            </div>
            <?php if ($filterClass && !empty($students)): ?>
            <div class="form-group" style="margin:0;flex:2;min-width:160px;">
                <label style="font-size:12px;">ছাত্র (Report Card)</label>
                <select name="student_id" class="form-control" style="padding:7px;" onchange="this.form.submit()">
                    <option value="">সকল ছাত্র</option>
                    <?php foreach ($students as $st): ?>
                    <option value="<?=$st['id']?>" <?=$studentId==$st['id']?'selected':''?>><?=e($st['name_bn']??$st['name'])?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <input type="hidden" name="class_id" value="<?=$filterClass?>">
        </form>
    </div>
</div>

<?php /* ═══ SINGLE STUDENT REPORT CARD ═══ */
if ($studentId && $reportStudent && !empty($reportResults)): ?>

<div class="report-card">
    <div class="report-header">
        <h2><?= e(getSetting('institute_name', 'মাদ্রাসা')) ?></h2>
        <p>বার্ষিক রিপোর্ট কার্ড — <?= $filterYear ?></p>
    </div>

    <div class="report-student-info">
        <div class="info-cell"><div class="info-label">ছাত্রের নাম</div><div class="info-value"><?=e($reportStudent['name_bn']??$reportStudent['name'])?></div></div>
        <div class="info-cell"><div class="info-label">Student ID</div><div class="info-value"><?=e($reportStudent['student_id'])?></div></div>
        <div class="info-cell"><div class="info-label">রোল নম্বর</div><div class="info-value"><?=e($reportStudent['roll_number'])?></div></div>
        <div class="info-cell"><div class="info-label">মেধাক্রম</div><div class="info-value"><?=e($reportResults[0]['merit_position'] ?? '—')?></div></div>
    </div>

    <!-- Half Yearly -->
    <div class="result-section">
        <h3><i class="fas fa-calendar-half"></i> অর্ধ বার্ষিক ফলাফল</h3>
        <table class="marks-table">
            <thead><tr><th>বিষয়</th><th>১ম মডেল (২০)</th><th>২য় মডেল (২০)</th><th>মডেল গড় (২০)</th><th>পরীক্ষা (৮০)</th><th>মোট (১০০)</th><th>গ্রেড</th></tr></thead>
            <tbody>
            <?php foreach ($reportResults as $r): ?>
            <tr>
                <td style="text-align:left;font-weight:600;"><?=e($r['subject_name_bn'])?></td>
                <td><?=$r['model1_marks']?></td>
                <td><?=$r['model2_marks']?></td>
                <td><?=round(($r['model1_marks']+$r['model2_marks'])/2,1)?></td>
                <td><?=$r['half_yearly_marks']?></td>
                <td style="font-weight:700;"><?=$r['half_yearly_total']?></td>
                <td><?=gradeBadge($r['half_yearly_grade'])?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Annual -->
    <div class="result-section">
        <h3><i class="fas fa-calendar"></i> বার্ষিক ফলাফল</h3>
        <table class="marks-table">
            <thead><tr><th>বিষয়</th><th>৩য় মডেল (২০)</th><th>৪র্থ মডেল (২০)</th><th>মডেল গড় (২০)</th><th>পরীক্ষা (৮০)</th><th>মোট (১০০)</th><th>গ্রেড</th></tr></thead>
            <tbody>
            <?php foreach ($reportResults as $r): ?>
            <tr>
                <td style="text-align:left;font-weight:600;"><?=e($r['subject_name_bn'])?></td>
                <td><?=$r['model3_marks']?></td>
                <td><?=$r['model4_marks']?></td>
                <td><?=round(($r['model3_marks']+$r['model4_marks'])/2,1)?></td>
                <td><?=$r['annual_marks']?></td>
                <td style="font-weight:700;"><?=$r['annual_total']?></td>
                <td><?=gradeBadge($r['annual_grade'])?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Final -->
    <div class="result-section">
        <h3><i class="fas fa-star"></i> চূড়ান্ত ফলাফল</h3>
        <table class="marks-table">
            <thead><tr><th>বিষয়</th><th>অর্ধ বার্ষিক (১০০)</th><th>বার্ষিক (১০০)</th><th>চূড়ান্ত (১০০)</th><th>গ্রেড</th><th>GPA</th><th>পাস/ফেল</th></tr></thead>
            <tbody>
            <?php
            $totalGP = 0; $subCount = 0; $allPassed = true;
            foreach ($reportResults as $r):
                $totalGP += $r['final_grade_point'];
                $subCount++;
                if ($r['final_grade'] === 'F') $allPassed = false;
            ?>
            <tr>
                <td style="text-align:left;font-weight:600;"><?=e($r['subject_name_bn'])?></td>
                <td><?=$r['half_yearly_total']?></td>
                <td><?=$r['annual_total']?></td>
                <td style="font-weight:700;"><?=$r['final_marks']?></td>
                <td><?=gradeBadge($r['final_grade'])?></td>
                <td><?=$r['final_grade_point']?></td>
                <td><?=$r['is_passed'] ? '<span style="color:#27ae60;font-weight:700;">✓ পাস</span>' : '<span style="color:#e74c3c;font-weight:700;">✗ ফেল</span>'?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Summary -->
    <div class="final-box">
        <div class="final-stat">
            <div class="val"><?=$reportResults[0]['merit_position'] ?? '—'?></div>
            <div class="lbl">মেধাক্রম</div>
        </div>
        <div class="final-stat">
            <div class="val"><?=$subCount > 0 ? round($totalGP/$subCount, 2) : '0.00'?></div>
            <div class="lbl">GPA</div>
        </div>
        <div class="final-stat">
            <div class="val"><?=round(array_sum(array_column($reportResults,'final_marks'))/$subCount, 2)?></div>
            <div class="lbl">গড় নম্বর</div>
        </div>
        <span class="pass-badge <?=$allPassed?'pass':'fail'?>">
            <?=$allPassed ? '✓ উত্তীর্ণ' : '✗ অনুত্তীর্ণ'?>
        </span>
    </div>
</div>

<?php /* ═══ MODEL TEST LIST ═══ */
elseif ($filterType === 'model' && !empty($modelResults)): ?>

<?php foreach ($modelResults as $examId => $mData):
    $mx    = $mData['exam'];
    $marks = $mData['marks'];

    // Group by student
    $byStudent = [];
    foreach ($marks as $m) {
        $byStudent[$m['student_id']]['info'] = ['name_bn'=>$m['name_bn'],'name'=>$m['name'],'roll'=>$m['roll_number']];
        $byStudent[$m['student_id']]['marks'][$m['subject_id']] = $m['total_marks'];
        $byStudent[$m['student_id']]['total'] = ($byStudent[$m['student_id']]['total'] ?? 0) + $m['total_marks'];
    }
    // Sort by total desc
    uasort($byStudent, fn($a,$b) => $b['total'] <=> $a['total']);
    $pos = 1;
?>
<div class="card mb-16">
    <div class="card-header">
        <span class="card-title"><i class="fas fa-tasks"></i> <?=e($mx['exam_name_bn'])?> — মডেল টেস্ট ফলাফল</span>
        <span class="badge badge-info">পূর্ণমান: ২০ প্রতি বিষয়</span>
    </div>
    <?php if (empty($byStudent)): ?>
    <div class="card-body" style="text-align:center;color:#718096;padding:30px;">নম্বর এন্ট্রি করা হয়নি</div>
    <?php else: ?>
    <div style="overflow-x:auto;">
        <table>
            <thead>
                <tr>
                    <th>অবস্থান</th>
                    <th>রোল</th>
                    <th>নাম</th>
                    <?php foreach ($subjects as $sub): ?>
                    <th style="text-align:center;"><?=e($sub['subject_name_bn'])?><br><span style="font-size:10px;font-weight:400;">/২০</span></th>
                    <?php endforeach; ?>
                    <th style="text-align:center;">মোট</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($byStudent as $sid => $sd): ?>
            <tr>
                <td style="text-align:center;font-weight:700;color:#e67e22;"><?=$pos++?></td>
                <td style="color:#718096;"><?=e($sd['info']['roll'])?></td>
                <td style="font-weight:600;"><?=e($sd['info']['name_bn']??$sd['info']['name'])?></td>
                <?php foreach ($subjects as $sub): ?>
                <td style="text-align:center;"><?=$sd['marks'][$sub['id']] ?? '—'?></td>
                <?php endforeach; ?>
                <td style="text-align:center;font-weight:700;color:#1a5276;"><?=$sd['total']?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
<?php endforeach; ?>

<?php /* ═══ CLASS RESULT TABLE ═══ */
elseif ($filterClass && !empty($results)): ?>

<div class="card">
    <div class="card-header">
        <span class="card-title">
            <i class="fas fa-list-ol"></i>
            <?=$filterType==='half_yearly'?'অর্ধ বার্ষিক':($filterType==='annual'?'বার্ষিক':'চূড়ান্ত')?> ফলাফল তালিকা
        </span>
        <button onclick="window.print()" class="btn btn-outline btn-sm no-print"><i class="fas fa-print"></i></button>
    </div>
    <div style="overflow-x:auto;">
        <table>
            <thead>
                <tr>
                    <th>মেধা</th>
                    <th>রোল</th>
                    <th>নাম</th>
                    <?php foreach ($subjects as $sub): ?>
                    <th style="text-align:center;font-size:11px;"><?=e($sub['subject_name_bn'])?></th>
                    <?php endforeach; ?>
                    <th style="text-align:center;">মোট</th>
                    <th style="text-align:center;">GPA</th>
                    <th style="text-align:center;">ফলাফল</th>
                    <th class="no-print">Report Card</th>
                </tr>
            </thead>
            <tbody>
            <?php
            // Build student rows
            $studentRows = [];
            foreach ($results as $sid => $subResults) {
                $first = reset($subResults);
                $totalFinal = 0; $totalGP = 0; $count = 0; $allPass = true;
                foreach ($subResults as $sr) {
                    $field = $filterType==='half_yearly' ? 'half_yearly_total' : ($filterType==='annual' ? 'annual_total' : 'final_marks');
                    $gpField = $filterType==='half_yearly' ? 'half_yearly_grade_point' : ($filterType==='annual' ? 'annual_grade_point' : 'final_grade_point');
                    $gradeField = $filterType==='half_yearly' ? 'half_yearly_grade' : ($filterType==='annual' ? 'annual_grade' : 'final_grade');
                    $totalFinal += $sr[$field];
                    $totalGP    += $sr[$gpField];
                    $count++;
                    if ($sr[$gradeField] === 'F') $allPass = false;
                }
                $studentRows[$sid] = [
                    'merit' => $first['merit_position'],
                    'roll'  => $first['roll_number'],
                    'name'  => $first['name_bn'] ?? $first['name'],
                    'id'    => $sid,
                    'total' => $totalFinal,
                    'gpa'   => $count > 0 ? round($totalGP/$count,2) : 0,
                    'pass'  => $allPass,
                    'subs'  => $subResults,
                ];
            }
            usort($studentRows, fn($a,$b) => $a['merit'] <=> $b['merit']);
            foreach ($studentRows as $row):
            ?>
            <tr>
                <td style="text-align:center;font-weight:700;color:#e67e22;"><?=$row['merit']?></td>
                <td style="color:#718096;"><?=e($row['roll'])?></td>
                <td style="font-weight:600;">
                    <a href="?class_id=<?=$filterClass?>&year=<?=$filterYear?>&student_id=<?=$row['id']?>" style="color:#1a5276;text-decoration:none;"><?=e($row['name'])?></a>
                </td>
                <?php
                $field = $filterType==='half_yearly' ? 'half_yearly_total' : ($filterType==='annual' ? 'annual_total' : 'final_marks');
                $gradeField = $filterType==='half_yearly' ? 'half_yearly_grade' : ($filterType==='annual' ? 'annual_grade' : 'final_grade');
                foreach ($subjects as $sub):
                    $sr = $row['subs'][$sub['id']] ?? null;
                ?>
                <td style="text-align:center;">
                    <?php if ($sr): ?>
                    <div style="font-weight:700;font-size:13px;"><?=$sr[$field]?></div>
                    <?=gradeBadge($sr[$gradeField])?>
                    <?php else: ?>—<?php endif; ?>
                </td>
                <?php endforeach; ?>
                <td style="text-align:center;font-weight:700;color:#1a5276;"><?=round($row['total'],1)?></td>
                <td style="text-align:center;font-weight:700;"><?=$row['gpa']?></td>
                <td style="text-align:center;">
                    <?php if ($row['pass']): ?>
                    <span style="color:#27ae60;font-weight:700;">✓ উত্তীর্ণ</span>
                    <?php else: ?>
                    <span style="color:#e74c3c;font-weight:700;">✗ অনুত্তীর্ণ</span>
                    <?php endif; ?>
                </td>
                <td class="no-print">
                    <a href="?class_id=<?=$filterClass?>&year=<?=$filterYear?>&student_id=<?=$row['id']?>" class="btn btn-info btn-xs"><i class="fas fa-id-card"></i> কার্ড</a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif ($filterClass): ?>
<div class="card"><div class="card-body" style="text-align:center;padding:40px;color:#718096;">
    <i class="fas fa-calculator" style="font-size:36px;display:block;margin-bottom:12px;opacity:.4;"></i>
    ফলাফল এখনো গণনা করা হয়নি।<br>
    <a href="result_entry.php?class_id=<?=$filterClass?>&year=<?=$filterYear?>" class="btn btn-primary btn-sm" style="margin-top:12px;">নম্বর এন্ট্রি করুন</a>
</div></div>
<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>
