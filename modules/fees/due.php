<?php
require_once '../../includes/functions.php';
requireLogin(['super_admin','principal','accountant']);
$pageTitle = 'বকেয়া ফি তালিকা';
$db = getDB();

$monthYear  = $_GET['month'] ?? date('Y-m');
$divisionId = (int)($_GET['division_id'] ?? 0);
$classId    = (int)($_GET['class_id'] ?? 0);

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

$feeTypes = $db->query("SELECT * FROM fee_types WHERE is_active=1 AND fee_category='monthly'")->fetchAll();

// বকেয়া ছাত্র
$where  = "s.status='active' AND s.academic_year='" . date('Y') . "'";
$params = [];
if ($divisionId) { $where .= " AND s.division_id=?"; $params[] = $divisionId; }
if ($classId)    { $where .= " AND s.class_id=?";    $params[] = $classId; }

$query = "SELECT s.*, c.class_name_bn, d.division_name_bn,
    (SELECT COALESCE(SUM(paid_amount),0) FROM fee_collections WHERE student_id=s.id AND month_year=?) as paid_this_month
    FROM students s
    LEFT JOIN classes c ON s.class_id=c.id
    LEFT JOIN divisions d ON s.division_id=d.id
    WHERE $where
    HAVING paid_this_month = 0
    ORDER BY d.sort_order, c.class_numeric, s.roll_number";

$stmt = $db->prepare($query);
$stmt->execute(array_merge([$monthYear], $params));
$dueStudents = $stmt->fetchAll();

$totalMonthlyFee = 0;
foreach ($feeTypes as $ft) $totalMonthlyFee += $ft['amount'];

require_once '../../includes/header.php';
?>

<div class="section-header">
    <h2 class="section-title"><i class="fas fa-exclamation-circle"></i> বকেয়া ফি তালিকা</h2>
    <div style="display:flex;gap:8px;">
        <button onclick="window.print()" class="btn btn-outline btn-sm no-print"><i class="fas fa-print"></i> প্রিন্ট</button>
        <a href="report.php" class="btn btn-outline btn-sm"><i class="fas fa-chart-bar"></i> রিপোর্ট</a>
    </div>
</div>

<!-- বিভাগ Quick-Tab -->
<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px;" class="no-print">
    <a href="due.php?month=<?= $monthYear ?>"
       class="btn btn-sm <?= !$divisionId ? 'btn-primary' : 'btn-outline' ?>">
        <i class="fas fa-layer-group"></i> সব বিভাগ
    </a>
    <?php foreach ($divisions as $d): ?>
    <a href="due.php?division_id=<?= $d['id'] ?>&month=<?= $monthYear ?>"
       class="btn btn-sm <?= $divisionId == $d['id'] ? 'btn-primary' : 'btn-outline' ?>">
        <?= e($d['division_name_bn']) ?>
    </a>
    <?php endforeach; ?>
</div>

<!-- Filter -->
<div class="card mb-16 no-print">
    <div class="card-body" style="padding:12px 20px;">
        <form method="GET" id="filterForm" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
            <input type="hidden" name="division_id" id="hiddenDivisionId" value="<?= $divisionId ?>">

            <!-- মাস -->
            <div class="form-group" style="margin:0;">
                <label style="font-size:12px;font-weight:600;">মাস নির্বাচন</label>
                <input type="month" name="month" class="form-control" style="padding:7px;"
                    value="<?= e($monthYear) ?>" onchange="this.form.submit()">
            </div>

            <!-- বিভাগ -->
            <div class="form-group" style="margin:0;min-width:150px;">
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
            <div class="form-group" style="margin:0;min-width:150px;">
                <label style="font-size:12px;font-weight:600;">শ্রেণী</label>
                <select name="class_id" class="form-control" style="padding:7px;" onchange="this.form.submit()">
                    <option value="">সব শ্রেণী</option>
                    <?php foreach ($classes as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $classId == $c['id'] ? 'selected' : '' ?>>
                        <?php if (!$divisionId): ?><?= e($c['division_name_bn']) ?> → <?php endif; ?>
                        <?= e($c['class_name_bn']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
</div>

<?php if (!empty($dueStudents)): ?>
<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:20px;">
    <div class="stat-card red">
        <div class="stat-icon"><i class="fas fa-users"></i></div>
        <div><div class="stat-value"><?= toBanglaNumber(count($dueStudents)) ?></div><div class="stat-label">বকেয়াদার ছাত্র</div></div>
    </div>
    <div class="stat-card orange">
        <div class="stat-icon"><i class="fas fa-money-bill"></i></div>
        <div><div class="stat-value">৳<?= number_format(count($dueStudents) * $totalMonthlyFee) ?></div><div class="stat-label">আনুমানিক বকেয়া</div></div>
    </div>
    <div class="stat-card blue">
        <div class="stat-icon"><i class="fas fa-calendar"></i></div>
        <div><div class="stat-value"><?= e($monthYear) ?></div><div class="stat-label">নির্বাচিত মাস</div></div>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <span class="card-title">
            <?php if ($divisionId): ?>
            <?php foreach ($divisions as $d): if ($d['id'] == $divisionId): ?>
            <span style="font-size:12px;color:var(--primary);font-weight:700;margin-right:6px;"><?= e($d['division_name_bn']) ?></span>
            <?php endif; endforeach; ?>
            <?php endif; ?>
            <?= banglaDate(date('Y-m-d', strtotime($monthYear . '-01'))) ?> মাসের বকেয়া তালিকা
        </span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>ছাত্রের নাম</th>
                    <th>বিভাগ / শ্রেণী</th>
                    <th>পিতার নাম</th>
                    <th>ফোন</th>
                    <th class="no-print">অ্যাকশন</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($dueStudents)): ?>
                <tr>
                    <td colspan="6" style="text-align:center;padding:30px;color:var(--success);font-weight:600;">
                        <i class="fas fa-check-circle" style="font-size:24px;"></i><br>এই মাসে কোনো বকেয়া নেই!
                    </td>
                </tr>
                <?php else: foreach ($dueStudents as $i => $s): ?>
                <tr>
                    <td style="color:var(--text-muted);"><?= toBanglaNumber($i + 1) ?></td>
                    <td>
                        <div style="font-weight:600;"><?= e($s['name_bn'] ?? $s['name']) ?></div>
                        <div style="font-size:11px;color:var(--text-muted);"><?= e($s['student_id']) ?></div>
                    </td>
                    <td>
                        <span style="font-size:11px;color:var(--primary);font-weight:600;"><?= e($s['division_name_bn'] ?? '') ?></span>
                        <span style="font-size:12px;color:var(--text-muted);"> / <?= e($s['class_name_bn'] ?? '') ?></span>
                    </td>
                    <td style="font-size:13px;"><?= e($s['father_name'] ?? '-') ?></td>
                    <td style="font-size:13px;"><?= e($s['father_phone'] ?? $s['guardian_phone'] ?? '-') ?></td>
                    <td class="no-print">
                        <a href="collection.php?student_id=<?= $s['id'] ?>" class="btn btn-success btn-xs">
                            <i class="fas fa-money-bill"></i> ফি নিন
                        </a>
                        <?php if ($s['father_phone'] || ($s['guardian_phone'] ?? null)): ?>
                        <a href="sms.php?phone=<?= urlencode($s['father_phone'] ?? $s['guardian_phone']) ?>&student_id=<?= $s['id'] ?>&month=<?= $monthYear ?>"
                            class="btn btn-info btn-xs"><i class="fas fa-sms"></i> SMS</a>
                        <?php endif; ?>
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
