<?php
require_once '../../includes/functions.php';
requireLogin(['super_admin','principal','teacher']);
$pageTitle = 'উপস্থিতি রিপোর্ট';
$db = getDB();

$divisionId = (int)($_GET['division_id'] ?? 0);
$classId    = (int)($_GET['class_id'] ?? 0);
$month      = $_GET['month'] ?? date('Y-m');

// সব বিভাগ
$divisions = $db->query("SELECT * FROM divisions WHERE is_active=1 ORDER BY sort_order, id")->fetchAll();

// শ্রেণী — বিভাগ অনুযায়ী
if ($divisionId) {
    $clsStmt = $db->prepare("SELECT c.*, d.division_name_bn FROM classes c LEFT JOIN divisions d ON c.division_id=d.id WHERE c.is_active=1 AND c.division_id=? ORDER BY c.class_numeric");
    $clsStmt->execute([$divisionId]);
    $classes = $clsStmt->fetchAll();
} else {
    $classes = $db->query("SELECT c.*, d.division_name_bn FROM classes c LEFT JOIN divisions d ON c.division_id=d.id WHERE c.is_active=1 ORDER BY d.sort_order, c.class_numeric")->fetchAll();
}

$data = [];
$currentClass = null;
if ($classId) {
    $clsInfo = $db->prepare("SELECT c.*, d.division_name_bn FROM classes c LEFT JOIN divisions d ON c.division_id=d.id WHERE c.id=?");
    $clsInfo->execute([$classId]);
    $currentClass = $clsInfo->fetch();

    $startDate = $month.'-01';
    $endDate   = date('Y-m-t', strtotime($startDate));

    $stmt = $db->prepare("SELECT s.id, s.name_bn, s.name, s.roll_number,
        COUNT(CASE WHEN a.status='present' THEN 1 END) as present_days,
        COUNT(CASE WHEN a.status='absent'  THEN 1 END) as absent_days,
        COUNT(CASE WHEN a.status='late'    THEN 1 END) as late_days,
        COUNT(a.id) as total_days
        FROM students s
        LEFT JOIN attendance a ON s.id=a.student_id AND a.date BETWEEN ? AND ?
        WHERE s.class_id=? AND s.status='active'
        GROUP BY s.id ORDER BY s.roll_number");
    $stmt->execute([$startDate, $endDate, $classId]);
    $data = $stmt->fetchAll();
}

require_once '../../includes/header.php';
?>

<div class="section-header">
    <h2 class="section-title"><i class="fas fa-chart-bar"></i> উপস্থিতি রিপোর্ট</h2>
    <button onclick="window.print()" class="btn btn-outline btn-sm no-print">
        <i class="fas fa-print"></i> প্রিন্ট
    </button>
</div>

<!-- বিভাগ Quick-Tab -->
<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px;" class="no-print">
    <a href="report.php?month=<?= $month ?>"
       class="btn btn-sm <?= !$divisionId ? 'btn-primary' : 'btn-outline' ?>">
        <i class="fas fa-layer-group"></i> সব বিভাগ
    </a>
    <?php foreach ($divisions as $d): ?>
    <a href="report.php?division_id=<?= $d['id'] ?>&month=<?= $month ?>"
       class="btn btn-sm <?= $divisionId == $d['id'] ? 'btn-primary' : 'btn-outline' ?>">
        <?= e($d['division_name_bn']) ?>
    </a>
    <?php endforeach; ?>
</div>

<!-- Filter -->
<div class="card mb-16 no-print">
    <div class="card-body" style="padding:12px 20px;">
        <form method="GET" id="filterForm" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">

            <input type="hidden" name="division_id" id="hiddenDivisionId" value="<?= $divisionId ?>">

            <!-- বিভাগ -->
            <div class="form-group" style="margin:0;flex:1;min-width:150px;">
                <label style="font-size:12px;font-weight:600;">বিভাগ</label>
                <select class="form-control" style="padding:7px;" onchange="onDivisionChange(this.value)">
                    <option value="">সব বিভাগ</option>
                    <?php foreach ($divisions as $d): ?>
                    <option value="<?= $d['id'] ?>" <?= $divisionId == $d['id'] ? 'selected' : '' ?>>
                        <?= e($d['division_name_bn']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- শ্রেণী -->
            <div class="form-group" style="margin:0;flex:1;min-width:150px;">
                <label style="font-size:12px;font-weight:600;">শ্রেণী</label>
                <select name="class_id" class="form-control" style="padding:7px;" onchange="this.form.submit()">
                    <option value="">শ্রেণী নির্বাচন</option>
                    <?php foreach ($classes as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $classId == $c['id'] ? 'selected' : '' ?>>
                        <?php if (!$divisionId): ?><?= e($c['division_name_bn']) ?> → <?php endif; ?>
                        <?= e($c['class_name_bn']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- মাস -->
            <div class="form-group" style="margin:0;flex:1;min-width:150px;">
                <label style="font-size:12px;font-weight:600;">মাস</label>
                <input type="month" name="month" class="form-control" style="padding:7px;"
                    value="<?= e($month) ?>" onchange="this.form.submit()">
            </div>
        </form>
    </div>
</div>

<!-- রিপোর্ট টেবিল -->
<div class="card">
    <div class="card-header">
        <span class="card-title">
            <?php if ($currentClass): ?>
            <span style="font-size:12px;color:var(--primary);font-weight:700;margin-right:6px;">
                <?= e($currentClass['division_name_bn']) ?>
            </span>
            <?= e($currentClass['class_name_bn']) ?> —
            <?php endif; ?>
            <?= e($month) ?> মাসের উপস্থিতি সারসংক্ষেপ
        </span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>ছাত্রের নাম</th>
                    <th>রোল</th>
                    <th>উপস্থিত</th>
                    <th>অনুপস্থিত</th>
                    <th>দেরি</th>
                    <th>মোট দিন</th>
                    <th>হার</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($data)): ?>
                <tr>
                    <td colspan="8" style="text-align:center;padding:30px;color:#718096;">
                        বিভাগ ও শ্রেণী নির্বাচন করুন
                    </td>
                </tr>
                <?php else: foreach ($data as $i => $s):
                    $rate  = $s['total_days'] > 0 ? round(($s['present_days'] / $s['total_days']) * 100) : 0;
                    $color = $rate >= 80 ? 'var(--success)' : ($rate >= 60 ? 'var(--warning)' : 'var(--danger)');
                ?>
                <tr>
                    <td style="font-size:13px;color:var(--text-muted);"><?= toBanglaNumber($i + 1) ?></td>
                    <td style="font-weight:600;"><?= e($s['name_bn'] ?? $s['name']) ?></td>
                    <td><?= toBanglaNumber($s['roll_number']) ?></td>
                    <td style="color:var(--success);font-weight:700;"><?= toBanglaNumber($s['present_days'] ?? 0) ?></td>
                    <td style="color:var(--danger);font-weight:700;"><?= toBanglaNumber($s['absent_days'] ?? 0) ?></td>
                    <td style="color:var(--warning);"><?= toBanglaNumber($s['late_days'] ?? 0) ?></td>
                    <td><?= toBanglaNumber($s['total_days'] ?? 0) ?></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <div style="flex:1;background:var(--border);border-radius:3px;height:6px;min-width:60px;">
                                <div style="background:<?= $color ?>;width:<?= $rate ?>%;height:100%;border-radius:3px;"></div>
                            </div>
                            <span style="color:<?= $color ?>;font-weight:700;font-size:13px;"><?= toBanglaNumber($rate) ?>%</span>
                        </div>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function onDivisionChange(divId) {
    document.getElementById('hiddenDivisionId').value = divId;
    const classSel = document.querySelector('select[name="class_id"]');
    if (classSel) classSel.value = '';
    document.getElementById('filterForm').submit();
}
</script>

<?php require_once '../../includes/footer.php'; ?>
