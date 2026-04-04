<?php
require_once '../../includes/functions.php';
requireLogin(['super_admin','principal','teacher']);
$pageTitle = 'উপস্থিতি রিপোর্ট';
$db = getDB();

$classId = (int)($_GET['class_id'] ?? 0);
$month = $_GET['month'] ?? date('Y-m');
$classes = $db->query("SELECT * FROM classes WHERE is_active=1 ORDER BY class_numeric")->fetchAll();

$data = [];
if ($classId) {
    $startDate = $month.'-01'; $endDate = date('Y-m-t', strtotime($startDate));
    $stmt = $db->prepare("SELECT s.id, s.name_bn, s.name, s.roll_number,
        COUNT(CASE WHEN a.status='present' THEN 1 END) as present_days,
        COUNT(CASE WHEN a.status='absent' THEN 1 END) as absent_days,
        COUNT(CASE WHEN a.status='late' THEN 1 END) as late_days,
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
    <button onclick="window.print()" class="btn btn-outline btn-sm no-print"><i class="fas fa-print"></i> প্রিন্ট</button>
</div>
<div class="card mb-16 no-print">
    <div class="card-body" style="padding:12px 20px;">
        <form method="GET" style="display:flex;gap:12px;align-items:flex-end;">
            <div class="form-group" style="margin:0;flex:1;">
                <label style="font-size:12px;">শ্রেণী</label>
                <select name="class_id" class="form-control" style="padding:7px;" onchange="this.form.submit()">
                    <option value="">শ্রেণী নির্বাচন</option>
                    <?php foreach($classes as $c): ?>
                    <option value="<?=$c['id']?>" <?=$classId==$c['id']?'selected':''?>><?=e($c['class_name_bn'])?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0;flex:1;">
                <label style="font-size:12px;">মাস</label>
                <input type="month" name="month" class="form-control" style="padding:7px;" value="<?=e($month)?>" onchange="this.form.submit()">
            </div>
        </form>
    </div>
</div>
<div class="card">
    <div class="card-header">
        <span class="card-title"><?=e($month)?> মাসের উপস্থিতি সারসংক্ষেপ</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>#</th><th>ছাত্রের নাম</th><th>রোল</th><th>উপস্থিত</th><th>অনুপস্থিত</th><th>দেরি</th><th>মোট দিন</th><th>হার</th></tr></thead>
            <tbody>
                <?php if(empty($data)): ?>
                <tr><td colspan="8" style="text-align:center;padding:30px;color:#718096;">শ্রেণী নির্বাচন করুন</td></tr>
                <?php else: foreach($data as $i=>$s):
                    $rate = $s['total_days']>0 ? round(($s['present_days']/$s['total_days'])*100) : 0;
                    $color = $rate>=80?'var(--success)':($rate>=60?'var(--warning)':'var(--danger)');
                ?>
                <tr>
                    <td style="font-size:13px;color:var(--text-muted);"><?=toBanglaNumber($i+1)?></td>
                    <td style="font-weight:600;"><?=e($s['name_bn']??$s['name'])?></td>
                    <td><?=toBanglaNumber($s['roll_number'])?></td>
                    <td style="color:var(--success);font-weight:700;"><?=toBanglaNumber($s['present_days']??0)?></td>
                    <td style="color:var(--danger);font-weight:700;"><?=toBanglaNumber($s['absent_days']??0)?></td>
                    <td style="color:var(--warning);"><?=toBanglaNumber($s['late_days']??0)?></td>
                    <td><?=toBanglaNumber($s['total_days']??0)?></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <div style="flex:1;background:var(--border);border-radius:3px;height:6px;min-width:60px;">
                                <div style="background:<?=$color?>;width:<?=$rate?>%;height:100%;border-radius:3px;"></div>
                            </div>
                            <span style="color:<?=$color?>;font-weight:700;font-size:13px;"><?=toBanglaNumber($rate)?>%</span>
                        </div>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once '../../includes/footer.php'; ?>
