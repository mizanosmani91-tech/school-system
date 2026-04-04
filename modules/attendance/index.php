<?php
require_once '../../includes/functions.php';
requireLogin(['super_admin','principal','teacher']);
$pageTitle = 'উপস্থিতি গ্রহণ';
$db = getDB();

$classId = (int)($_GET['class_id'] ?? 0);
$date = $_GET['date'] ?? date('Y-m-d');
$classes = $db->query("SELECT * FROM classes WHERE is_active=1 ORDER BY class_numeric")->fetchAll();

// Save attendance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_attendance'])) {
    if (!verifyCsrf($_POST['csrf'] ?? '')) die('CSRF Error');

    $postDate = $_POST['date'];
    $postClassId = (int)$_POST['class_id'];
    $statuses = $_POST['status'] ?? [];
    $notes = $_POST['note'] ?? [];

    $db->beginTransaction();
    try {
        foreach ($statuses as $studentId => $status) {
            $stmt = $db->prepare("INSERT INTO attendance (student_id, class_id, date, status, note, marked_by)
                VALUES (?,?,?,?,?,?)
                ON DUPLICATE KEY UPDATE status=VALUES(status), note=VALUES(note), marked_by=VALUES(marked_by)");
            $stmt->execute([$studentId, $postClassId, $postDate, $status, $notes[$studentId] ?? '', $_SESSION['user_id']]);
        }
        $db->commit();
        setFlash('success', banglaDate($postDate) . ' তারিখের উপস্থিতি সংরক্ষিত হয়েছে।');
    } catch (Exception $e) {
        $db->rollBack();
        setFlash('danger', 'ত্রুটি: ' . $e->getMessage());
    }
    header("Location: index.php?class_id=$postClassId&date=$postDate");
    exit;
}

// Load students & existing attendance
$students = [];
$existingAttendance = [];
if ($classId) {
    $stmt = $db->prepare("SELECT s.*, sec.section_name FROM students s
        LEFT JOIN sections sec ON s.section_id = sec.id
        WHERE s.class_id=? AND s.status='active' ORDER BY s.roll_number");
    $stmt->execute([$classId]);
    $students = $stmt->fetchAll();

    $aStmt = $db->prepare("SELECT student_id, status, note FROM attendance WHERE class_id=? AND date=?");
    $aStmt->execute([$classId, $date]);
    foreach ($aStmt->fetchAll() as $a) {
        $existingAttendance[$a['student_id']] = $a;
    }
}

require_once '../../includes/header.php';
?>

<div class="section-header">
    <h2 class="section-title"><i class="fas fa-clipboard-check"></i> উপস্থিতি গ্রহণ</h2>
    <a href="report.php" class="btn btn-outline btn-sm"><i class="fas fa-chart-bar"></i> রিপোর্ট</a>
</div>

<!-- Filter -->
<div class="card mb-16">
    <div class="card-body" style="padding:14px 20px;">
        <form method="GET" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">
            <div class="form-group" style="flex:1;min-width:160px;margin:0;">
                <label style="font-size:12px;">শ্রেণী</label>
                <select name="class_id" class="form-control" style="padding:7px;" required onchange="this.form.submit()">
                    <option value="">শ্রেণী নির্বাচন করুন</option>
                    <?php foreach ($classes as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $classId == $c['id'] ? 'selected':'' ?>>
                        <?= e($c['class_name_bn']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="flex:1;min-width:160px;margin:0;">
                <label style="font-size:12px;">তারিখ</label>
                <input type="date" name="date" class="form-control" style="padding:7px;" value="<?= $date ?>" max="<?= date('Y-m-d') ?>" onchange="this.form.submit()">
            </div>
        </form>
    </div>
</div>

<?php if ($classId && !empty($students)): ?>
<form method="POST">
    <input type="hidden" name="csrf" value="<?= getCsrfToken() ?>">
    <input type="hidden" name="date" value="<?= $date ?>">
    <input type="hidden" name="class_id" value="<?= $classId ?>">
    <input type="hidden" name="save_attendance" value="1">

<div class="card">
    <div class="card-header">
        <span class="card-title">
            <?= banglaDate($date) ?> তারিখের উপস্থিতি &mdash; মোট <?= toBanglaNumber(count($students)) ?> জন
        </span>
        <div style="display:flex;gap:8px;">
            <button type="button" class="btn btn-success btn-sm" onclick="markAll('present')">সবাই উপস্থিত</button>
            <button type="button" class="btn btn-danger btn-sm" onclick="markAll('absent')">সবাই অনুপস্থিত</button>
        </div>
    </div>

    <!-- Summary bar -->
    <div style="padding:12px 20px;background:#f7fafc;border-bottom:1px solid var(--border);display:flex;gap:16px;" id="summaryBar">
        <span style="color:var(--success);font-size:13px;font-weight:600;"><i class="fas fa-check-circle"></i> উপস্থিত: <span id="cntPresent">০</span></span>
        <span style="color:var(--danger);font-size:13px;font-weight:600;"><i class="fas fa-times-circle"></i> অনুপস্থিত: <span id="cntAbsent">০</span></span>
        <span style="color:var(--warning);font-size:13px;font-weight:600;"><i class="fas fa-clock"></i> দেরি: <span id="cntLate">০</span></span>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th style="width:50px;">#</th>
                    <th>ছাত্রের নাম</th>
                    <th>রোল</th>
                    <th style="min-width:280px;">উপস্থিতি</th>
                    <th>মন্তব্য</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($students as $i => $s):
                    $att = $existingAttendance[$s['id']] ?? ['status' => 'present', 'note' => ''];
                ?>
                <tr id="row<?= $s['id'] ?>">
                    <td style="color:var(--text-muted);font-size:13px;"><?= toBanglaNumber($i+1) ?></td>
                    <td>
                        <div style="font-weight:600;font-size:14px;"><?= e($s['name_bn'] ?? $s['name']) ?></div>
                        <div style="font-size:11px;color:var(--text-muted);"><?= e($s['student_id']) ?></div>
                    </td>
                    <td><?= toBanglaNumber($s['roll_number']) ?></td>
                    <td>
                        <div style="display:flex;gap:6px;" class="att-btns">
                            <?php
                            $statOptions = ['present'=>['✓ উপস্থিত','success'], 'absent'=>['✗ অনুপস্থিত','danger'], 'late'=>['⏰ দেরি','warning'], 'excused'=>['ছুটি','info']];
                            foreach ($statOptions as $v => [$label, $color]):
                                $sel = $att['status'] === $v ? '' : 'outline-';
                            ?>
                            <label style="cursor:pointer;">
                                <input type="radio" name="status[<?= $s['id'] ?>]" value="<?= $v ?>"
                                    <?= $att['status'] === $v ? 'checked' : '' ?>
                                    onchange="updateRow(<?= $s['id'] ?>, '<?= $v ?>')"
                                    style="display:none;">
                                <span class="btn btn-<?= $color ?> btn-xs att-opt <?= $att['status'] === $v ? 'sel' : '' ?>"
                                    style="<?= $att['status'] !== $v ? 'background:transparent;color:var(--text-muted);border:1px solid var(--border);' : '' ?>">
                                    <?= $label ?>
                                </span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </td>
                    <td>
                        <input type="text" name="note[<?= $s['id'] ?>]" class="form-control"
                            style="padding:5px 8px;font-size:12px;width:120px;"
                            value="<?= e($att['note']) ?>" placeholder="কারণ...">
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer">
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> উপস্থিতি সংরক্ষণ করুন</button>
    </div>
</div>
</form>

<?php elseif ($classId && empty($students)): ?>
<div class="alert alert-warning"><i class="fas fa-info-circle"></i> এই শ্রেণীতে কোনো সক্রিয় ছাত্র নেই।</div>
<?php elseif (!$classId): ?>
<div class="card"><div class="card-body" style="text-align:center;padding:48px;color:var(--text-muted);">
    <i class="fas fa-hand-point-up" style="font-size:48px;margin-bottom:16px;"></i>
    <p style="font-size:16px;">উপরে শ্রেণী নির্বাচন করুন</p>
</div></div>
<?php endif; ?>

<script>
function markAll(status) {
    document.querySelectorAll(`input[type=radio][value=${status}]`).forEach(r => {
        r.checked = true;
        updateRow(r.name.match(/\[(\d+)\]/)[1], status);
    });
}

function updateRow(id, status) {
    const row = document.getElementById('row' + id);
    const colors = { present: '#eafaf1', absent: '#fdedec', late: '#fef9e7', excused: '#d1ecf1' };
    row.style.background = colors[status] || '';
    // Update button styles
    row.querySelectorAll('.att-opt').forEach(b => {
        b.style.background = 'transparent';
        b.style.color = 'var(--text-muted)';
        b.style.border = '1px solid var(--border)';
    });
    const radioInput = row.querySelector(`input[value="${status}"]`);
    if (radioInput) {
        const btn = radioInput.nextElementSibling;
        const bColors = { present: 'var(--success)', absent: 'var(--danger)', late: 'var(--warning)', excused: 'var(--info)' };
        btn.style.background = bColors[status];
        btn.style.color = '#fff';
        btn.style.border = 'none';
    }
    updateCounts();
}

function updateCounts() {
    const counts = { present: 0, absent: 0, late: 0, excused: 0 };
    document.querySelectorAll('input[type=radio]:checked').forEach(r => { counts[r.value] = (counts[r.value] || 0) + 1; });
    document.getElementById('cntPresent').textContent = counts.present || '০';
    document.getElementById('cntAbsent').textContent = counts.absent || '০';
    document.getElementById('cntLate').textContent = counts.late || '০';
}

// Init
document.querySelectorAll('input[type=radio]:checked').forEach(r => {
    updateRow(r.name.match(/\[(\d+)\]/)[1], r.value);
});
updateCounts();
</script>

<?php require_once '../../includes/footer.php'; ?>
