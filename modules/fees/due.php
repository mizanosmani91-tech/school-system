<?php
require_once '../../includes/functions.php';
requireLogin(['super_admin','principal','accountant']);
$pageTitle = 'বকেয়া ফি তালিকা';
$db = getDB();

$monthYear = $_GET['month'] ?? date('Y-m');
$classId = (int)($_GET['class_id'] ?? 0);
$classes = $db->query("SELECT * FROM classes WHERE is_active=1 ORDER BY class_numeric")->fetchAll();
$feeTypes = $db->query("SELECT * FROM fee_types WHERE is_active=1 AND fee_category='monthly'")->fetchAll();

// Students who haven't paid for selected month
$where = "s.status='active' AND s.academic_year='".date('Y')."'";
$params = [];
if ($classId) { $where .= " AND s.class_id=?"; $params[] = $classId; }

$query = "SELECT s.*, c.class_name_bn,
    (SELECT COALESCE(SUM(paid_amount),0) FROM fee_collections WHERE student_id=s.id AND month_year=?) as paid_this_month
    FROM students s
    LEFT JOIN classes c ON s.class_id=c.id
    WHERE $where
    HAVING paid_this_month = 0
    ORDER BY c.class_numeric, s.roll_number";

$stmt = $db->prepare($query);
$stmt->execute(array_merge([$monthYear], $params));
$dueStudents = $stmt->fetchAll();

// Total due amount (sum of monthly fees)
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

<div class="card mb-16 no-print">
    <div class="card-body" style="padding:12px 20px;">
        <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
            <div class="form-group" style="margin:0;">
                <label style="font-size:12px;">মাস নির্বাচন</label>
                <input type="month" name="month" class="form-control" style="padding:7px;" value="<?=e($monthYear)?>" onchange="this.form.submit()">
            </div>
            <div class="form-group" style="margin:0;">
                <label style="font-size:12px;">শ্রেণী</label>
                <select name="class_id" class="form-control" style="padding:7px;" onchange="this.form.submit()">
                    <option value="">সব শ্রেণী</option>
                    <?php foreach($classes as $c): ?>
                    <option value="<?=$c['id']?>" <?=$classId==$c['id']?'selected':''?>><?=e($c['class_name_bn'])?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
</div>

<?php if (!empty($dueStudents)): ?>
<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:20px;">
    <div class="stat-card red"><div class="stat-icon"><i class="fas fa-users"></i></div>
        <div><div class="stat-value"><?=toBanglaNumber(count($dueStudents))?></div><div class="stat-label">বকেয়াদার ছাত্র</div></div></div>
    <div class="stat-card orange"><div class="stat-icon"><i class="fas fa-money-bill"></i></div>
        <div><div class="stat-value">৳<?=number_format(count($dueStudents)*$totalMonthlyFee)?></div><div class="stat-label">আনুমানিক বকেয়া</div></div></div>
    <div class="stat-card blue"><div class="stat-icon"><i class="fas fa-calendar"></i></div>
        <div><div class="stat-value"><?=e($monthYear)?></div><div class="stat-label">নির্বাচিত মাস</div></div></div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <span class="card-title"><?=banglaDate(date('Y-m-d',strtotime($monthYear.'-01')))?> মাসের বকেয়া তালিকা</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>#</th><th>ছাত্রের নাম</th><th>শ্রেণী</th><th>পিতার নাম</th><th>ফোন</th><th class="no-print">অ্যাকশন</th></tr></thead>
            <tbody>
                <?php if(empty($dueStudents)): ?>
                <tr><td colspan="6" style="text-align:center;padding:30px;color:var(--success);font-weight:600;">
                    <i class="fas fa-check-circle" style="font-size:24px;"></i><br>এই মাসে কোনো বকেয়া নেই!
                </td></tr>
                <?php else: foreach($dueStudents as $i=>$s): ?>
                <tr>
                    <td style="color:var(--text-muted);"><?=toBanglaNumber($i+1)?></td>
                    <td>
                        <div style="font-weight:600;"><?=e($s['name_bn']??$s['name'])?></div>
                        <div style="font-size:11px;color:var(--text-muted);"><?=e($s['student_id'])?></div>
                    </td>
                    <td><?=e($s['class_name_bn']??'')?></td>
                    <td style="font-size:13px;"><?=e($s['father_name']??'-')?></td>
                    <td style="font-size:13px;"><?=e($s['father_phone']??$s['guardian_phone']??'-')?></td>
                    <td class="no-print">
                        <a href="collection.php?student_id=<?=$s['id']?>" class="btn btn-success btn-xs"><i class="fas fa-money-bill"></i> ফি নিন</a>
                        <?php if($s['father_phone']||$s['guardian_phone']): ?>
                        <a href="sms.php?phone=<?=urlencode($s['father_phone']??$s['guardian_phone'])?>&student_id=<?=$s['id']?>&month=<?=$monthYear?>"
                            class="btn btn-info btn-xs"><i class="fas fa-sms"></i> SMS</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once '../../includes/footer.php'; ?>
