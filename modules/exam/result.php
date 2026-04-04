<?php
require_once '../../includes/functions.php';
requireLogin();
$pageTitle = 'পরীক্ষার ফলাফল';
$db = getDB();

$examId = (int)($_GET['exam_id'] ?? 0);
$classId = (int)($_GET['class_id'] ?? 0);
$studentId = (int)($_GET['student_id'] ?? 0);
$exams = $db->query("SELECT * FROM exams ORDER BY start_date DESC")->fetchAll();
$classes = $db->query("SELECT * FROM classes WHERE is_active=1 ORDER BY class_numeric")->fetchAll();

$results = []; $students = [];
if ($examId && ($classId || $studentId)) {
    $where = "em.exam_id=?"; $params = [$examId];
    if ($studentId) { $where .= " AND em.student_id=?"; $params[] = $studentId; }
    elseif ($classId) { $where .= " AND s.class_id=?"; $params[] = $classId; }

    $stmt = $db->prepare("SELECT em.*, s.name_bn, s.name, s.roll_number, s.student_id as sid,
        sub.subject_name_bn, sub.full_marks, sub.pass_marks
        FROM exam_marks em
        JOIN students s ON em.student_id=s.id
        JOIN subjects sub ON em.subject_id=sub.id
        WHERE $where ORDER BY s.roll_number, sub.subject_name_bn");
    $stmt->execute($params);
    $rawData = $stmt->fetchAll();

    // Group by student
    foreach ($rawData as $row) {
        $sid = $row['student_id'];
        if (!isset($results[$sid])) {
            $results[$sid] = ['info' => $row, 'subjects' => [], 'total' => 0, 'gpa' => 0, 'count' => 0];
        }
        $results[$sid]['subjects'][] = $row;
        if (!$row['is_absent']) {
            $results[$sid]['total'] += $row['total_marks'];
            $results[$sid]['gpa'] += $row['grade_point'];
            $results[$sid]['count']++;
        }
    }
    // Compute GPA avg and rank
    foreach ($results as &$r) {
        $r['avg_gpa'] = $r['count'] > 0 ? round($r['gpa'] / $r['count'], 2) : 0;
        $r['avg_marks'] = $r['count'] > 0 ? round($r['total'] / $r['count'], 1) : 0;
        $r['passed'] = !in_array('F', array_column($r['subjects'], 'grade'));
    }
    // Sort by total marks for rank
    uasort($results, fn($a,$b) => $b['total'] <=> $a['total']);
    $rank = 1;
    foreach ($results as $sid => &$r) { $r['rank'] = $rank++; }
}

require_once '../../includes/header.php';
?>
<div class="section-header">
    <h2 class="section-title"><i class="fas fa-trophy"></i> পরীক্ষার ফলাফল</h2>
    <button onclick="window.print()" class="btn btn-outline btn-sm no-print"><i class="fas fa-print"></i> প্রিন্ট</button>
</div>

<div class="card mb-16 no-print">
    <div class="card-body" style="padding:12px 20px;">
        <form method="GET" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">
            <div class="form-group" style="margin:0;flex:1;min-width:160px;">
                <label style="font-size:12px;">পরীক্ষা</label>
                <select name="exam_id" class="form-control" style="padding:7px;" onchange="this.form.submit()">
                    <option value="">পরীক্ষা নির্বাচন</option>
                    <?php foreach($exams as $e): ?>
                    <option value="<?=$e['id']?>" <?=$examId==$e['id']?'selected':''?>><?=e($e['exam_name_bn']??$e['exam_name'])?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0;flex:1;min-width:160px;">
                <label style="font-size:12px;">শ্রেণী</label>
                <select name="class_id" class="form-control" style="padding:7px;" onchange="this.form.submit()">
                    <option value="">শ্রেণী নির্বাচন</option>
                    <?php foreach($classes as $c): ?>
                    <option value="<?=$c['id']?>" <?=$classId==$c['id']?'selected':''?>><?=e($c['class_name_bn'])?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
</div>

<?php if (empty($results) && ($examId || $classId)): ?>
<div class="card"><div class="card-body" style="text-align:center;padding:40px;color:#718096;">
    <i class="fas fa-info-circle" style="font-size:36px;margin-bottom:12px;"></i>
    <p>কোনো ফলাফল পাওয়া যায়নি। নম্বর এন্ট্রি করুন।</p>
    <a href="index.php" class="btn btn-primary" style="margin-top:12px;"><i class="fas fa-pencil-alt"></i> নম্বর এন্ট্রি করুন</a>
</div></div>
<?php elseif (!empty($results)): ?>

<?php foreach ($results as $sid => $r): ?>
<div class="card mb-16">
    <div style="background:linear-gradient(90deg,var(--primary),var(--primary-light));color:#fff;padding:14px 20px;display:flex;align-items:center;justify-content:space-between;">
        <div>
            <span style="font-size:17px;font-weight:700;"><?=e($r['info']['name_bn']??$r['info']['name'])?></span>
            <span style="margin-left:12px;opacity:.8;font-size:13px;">রোল: <?=toBanglaNumber($r['info']['roll_number'])?> &bull; ID: <?=e($r['info']['sid'])?></span>
        </div>
        <div style="display:flex;gap:12px;align-items:center;">
            <div style="text-align:center;">
                <div style="font-size:20px;font-weight:700;"><?=$r['avg_gpa']?></div>
                <div style="font-size:10px;opacity:.8;">GPA</div>
            </div>
            <div style="text-align:center;">
                <div style="font-size:20px;font-weight:700;"><?=toBanglaNumber($r['rank'])?></div>
                <div style="font-size:10px;opacity:.8;">মেধাক্রম</div>
            </div>
            <span class="badge badge-<?=$r['passed']?'success':'danger'?>" style="font-size:13px;padding:6px 12px;">
                <?=$r['passed']?'উত্তীর্ণ':'অনুত্তীর্ণ'?>
            </span>
        </div>
    </div>
    <table>
        <thead><tr><th>বিষয়</th><th>লিখিত</th><th>MCQ</th><th>ব্যবহারিক</th><th>মোট</th><th>পূর্ণমান</th><th>গ্রেড</th><th>পয়েন্ট</th></tr></thead>
        <tbody>
            <?php foreach ($r['subjects'] as $sub): ?>
            <tr style="<?=$sub['grade']==='F'?'background:#fff5f5':''?>">
                <td style="font-weight:600;font-size:13px;"><?=e($sub['subject_name_bn'])?></td>
                <td><?=$sub['is_absent']?'-':e($sub['written_marks'])?></td>
                <td><?=$sub['is_absent']?'-':e($sub['mcq_marks'])?></td>
                <td><?=$sub['is_absent']?'-':e($sub['practical_marks'])?></td>
                <td style="font-weight:700;"><?=$sub['is_absent']?'<span class="badge badge-secondary">AB</span>':e($sub['total_marks'])?></td>
                <td style="color:var(--text-muted);"><?=e($sub['full_marks'])?></td>
                <td><span class="badge badge-<?=$sub['grade']==='F'?'danger':($sub['grade']==='A+'?'success':'info')?>"><?=e($sub['grade'])?></span></td>
                <td style="font-weight:600;"><?=e($sub['grade_point'])?></td>
            </tr>
            <?php endforeach; ?>
            <tr style="background:var(--bg);font-weight:700;">
                <td>মোট</td><td colspan="3"></td>
                <td><?=toBanglaNumber($r['total'])?></td>
                <td></td>
                <td colspan="2">GPA: <?=$r['avg_gpa']?></td>
            </tr>
        </tbody>
    </table>
</div>
<?php endforeach; ?>

<?php else: ?>
<div class="card"><div class="card-body" style="text-align:center;padding:48px;color:#718096;">
    <i class="fas fa-search" style="font-size:48px;margin-bottom:16px;"></i>
    <p>পরীক্ষা ও শ্রেণী নির্বাচন করুন</p>
</div></div>
<?php endif; ?>
<?php require_once '../../includes/footer.php'; ?>
