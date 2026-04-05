<?php
require_once '../../includes/functions.php';
requireLogin(['super_admin','principal']);
$pageTitle = 'শিক্ষক উপস্থিতি রিপোর্ট';
$db = getDB();

$month  = $_GET['month'] ?? date('Y-m');
$search = trim($_GET['search'] ?? '');

// শিক্ষকের তালিকা + এই মাসের উপস্থিতি সারসংক্ষেপ
$startDate = $month . '-01';
$endDate   = date('Y-m-t', strtotime($startDate));

$where  = "t.is_active=1";
$params = [];
if ($search) {
    $where .= " AND (t.name_bn LIKE ? OR t.name LIKE ? OR t.phone LIKE ?)";
    $s = "%$search%";
    $params = [$s, $s, $s];
}

$teachers = [];
try {
    $stmt = $db->prepare("
        SELECT
            t.id, t.name_bn, t.name, t.designation_bn, t.teacher_id_no, t.phone,
            COUNT(ta.id) as total_days,
            SUM(CASE WHEN ta.check_in IS NOT NULL THEN 1 ELSE 0 END) as present_days,
            SUM(CASE WHEN ta.check_in IS NOT NULL AND ta.check_out IS NOT NULL THEN
                TIMESTAMPDIFF(MINUTE, ta.check_in, ta.check_out) ELSE 0 END) as total_minutes,
            MIN(ta.check_in) as earliest_checkin,
            MAX(ta.check_out) as latest_checkout
        FROM teachers t
        LEFT JOIN teacher_attendance ta ON t.id = ta.teacher_id
            AND ta.date BETWEEN ? AND ?
        WHERE $where
        GROUP BY t.id
        ORDER BY t.name_bn
    ");
    $stmt->execute(array_merge([$startDate, $endDate], $params));
    $teachers = $stmt->fetchAll();
} catch(Exception $e) {}

// আজকের উপস্থিতি
$today = date('Y-m-d');
$todayData = [];
try {
    $todayStmt = $db->query("
        SELECT ta.*, t.name_bn, t.name, t.designation_bn, t.teacher_id_no
        FROM teacher_attendance ta
        JOIN teachers t ON ta.teacher_id = t.id
        WHERE ta.date = '$today'
        ORDER BY ta.check_in ASC
    ");
    $todayData = $todayStmt->fetchAll();
} catch(Exception $e) {}

// যারা আজ আসেনি
$absentToday = [];
try {
    $absentStmt = $db->query("
        SELECT t.id, t.name_bn, t.name, t.designation_bn
        FROM teachers t
        WHERE t.is_active=1
        AND t.id NOT IN (SELECT teacher_id FROM teacher_attendance WHERE date='$today')
        ORDER BY t.name_bn
    ");
    $absentToday = $absentStmt->fetchAll();
} catch(Exception $e) {}

require_once '../../includes/header.php';
?>

<div class="section-header">
    <h2 class="section-title"><i class="fas fa-chart-bar"></i> শিক্ষক উপস্থিতি রিপোর্ট</h2>
    <button onclick="window.print()" class="btn btn-outline btn-sm no-print"><i class="fas fa-print"></i> প্রিন্ট</button>
</div>

<!-- আজকের সারসংক্ষেপ -->
<div class="stat-grid mb-24">
    <div class="stat-card green">
        <div class="stat-icon"><i class="fas fa-user-check"></i></div>
        <div>
            <div class="stat-value"><?= toBanglaNumber(count($todayData)) ?></div>
            <div class="stat-label">আজ উপস্থিত</div>
        </div>
    </div>
    <div class="stat-card red">
        <div class="stat-icon"><i class="fas fa-user-times"></i></div>
        <div>
            <div class="stat-value"><?= toBanglaNumber(count($absentToday)) ?></div>
            <div class="stat-label">আজ অনুপস্থিত</div>
        </div>
    </div>
    <div class="stat-card blue">
        <div class="stat-icon"><i class="fas fa-users"></i></div>
        <div>
            <div class="stat-value"><?= toBanglaNumber(count($todayData) + count($absentToday)) ?></div>
            <div class="stat-label">মোট শিক্ষক</div>
        </div>
    </div>
    <div class="stat-card orange">
        <div class="stat-icon"><i class="fas fa-sign-out-alt"></i></div>
        <div>
            <div class="stat-value"><?= toBanglaNumber(count(array_filter($todayData, fn($r) => $r['check_out']))) ?></div>
            <div class="stat-label">আজ চেক আউট করেছে</div>
        </div>
    </div>
</div>

<div class="grid-2 mb-24">
    <!-- আজ উপস্থিত -->
    <div class="card">
        <div class="card-header">
            <span class="card-title"><i class="fas fa-check-circle" style="color:var(--success);"></i> আজ উপস্থিত (<?= toBanglaNumber(count($todayData)) ?>)</span>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>নাম</th><th>চেক ইন</th><th>চেক আউট</th><th>মোট</th></tr></thead>
                <tbody>
                    <?php if(empty($todayData)): ?>
                    <tr><td colspan="4" style="text-align:center;padding:20px;color:var(--text-muted);">কেউ চেক ইন করেননি</td></tr>
                    <?php else: foreach($todayData as $r):
                        $totalH = '';
                        if($r['check_in'] && $r['check_out']) {
                            $d = strtotime($r['check_out']) - strtotime($r['check_in']);
                            $totalH = floor($d/3600).'ঘ '.floor(($d%3600)/60).'মি';
                        }
                    ?>
                    <tr>
                        <td>
                            <div style="font-weight:600;font-size:13px;"><?= e($r['name_bn']??$r['name']) ?></div>
                            <div style="font-size:11px;color:var(--text-muted);"><?= e($r['designation_bn']??'') ?></div>
                        </td>
                        <td style="color:var(--success);font-weight:600;font-size:13px;"><?= $r['check_in'] ? date('h:i A', strtotime($r['check_in'])) : '-' ?></td>
                        <td style="color:var(--danger);font-size:13px;"><?= $r['check_out'] ? date('h:i A', strtotime($r['check_out'])) : '<span style="color:var(--text-muted);">নেই</span>' ?></td>
                        <td style="font-size:13px;font-weight:600;"><?= $totalH ?: '-' ?></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- আজ অনুপস্থিত -->
    <div class="card">
        <div class="card-header">
            <span class="card-title"><i class="fas fa-times-circle" style="color:var(--danger);"></i> আজ আসেননি (<?= toBanglaNumber(count($absentToday)) ?>)</span>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>নাম</th><th>পদবী</th></tr></thead>
                <tbody>
                    <?php if(empty($absentToday)): ?>
                    <tr><td colspan="2" style="text-align:center;padding:20px;color:var(--success);">সবাই উপস্থিত ✅</td></tr>
                    <?php else: foreach($absentToday as $r): ?>
                    <tr>
                        <td style="font-weight:600;font-size:13px;"><?= e($r['name_bn']??$r['name']) ?></td>
                        <td style="font-size:12px;color:var(--text-muted);"><?= e($r['designation_bn']??'-') ?></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- মাসিক রিপোর্ট ফিল্টার -->
<div class="card mb-16 no-print">
    <div class="card-body" style="padding:12px 20px;">
        <form method="GET" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">
            <div class="form-group" style="margin:0;flex:1;min-width:160px;">
                <label style="font-size:12px;">মাস</label>
                <input type="month" name="month" class="form-control" style="padding:7px;" value="<?= e($month) ?>" onchange="this.form.submit()">
            </div>
            <div class="form-group" style="margin:0;flex:2;min-width:200px;">
                <label style="font-size:12px;">শিক্ষক খুঁজুন</label>
                <input type="text" name="search" class="form-control" style="padding:7px;" value="<?= e($search) ?>" placeholder="নাম বা ফোন...">
            </div>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> খুঁজুন</button>
            <a href="teacher_report.php" class="btn btn-outline btn-sm">রিসেট</a>
        </form>
    </div>
</div>

<!-- মাসিক সারসংক্ষেপ -->
<div class="card">
    <div class="card-header">
        <span class="card-title"><i class="fas fa-calendar-alt"></i> <?= e($month) ?> মাসের উপস্থিতি সারসংক্ষেপ</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>শিক্ষকের নাম</th>
                    <th>উপস্থিত দিন</th>
                    <th>মোট সময়</th>
                    <th>গড় সময়/দিন</th>
                    <th>হার</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $workDays = (new DateTime($startDate))->diff(new DateTime($endDate))->days + 1;
                if(empty($teachers)):
                ?>
                <tr><td colspan="6" style="text-align:center;padding:24px;color:var(--text-muted);">কোনো তথ্য নেই</td></tr>
                <?php else: foreach($teachers as $i => $t):
                    $hrs  = floor($t['total_minutes'] / 60);
                    $mins = $t['total_minutes'] % 60;
                    $avgMins = $t['present_days'] > 0 ? round($t['total_minutes'] / $t['present_days']) : 0;
                    $avgH = floor($avgMins/60); $avgM = $avgMins%60;
                    $rate = $workDays > 0 ? round(($t['present_days'] / $workDays) * 100) : 0;
                    $color = $rate >= 80 ? 'var(--success)' : ($rate >= 60 ? 'var(--warning)' : 'var(--danger)');
                ?>
                <tr>
                    <td style="color:var(--text-muted);font-size:13px;"><?= toBanglaNumber($i+1) ?></td>
                    <td>
                        <a href="../teacher/profile.php?id=<?= $t['id'] ?>" style="font-weight:600;font-size:13px;color:var(--primary);text-decoration:none;">
                            <?= e($t['name_bn']??$t['name']) ?>
                        </a>
                        <div style="font-size:11px;color:var(--text-muted);"><?= e($t['designation_bn']??'') ?></div>
                    </td>
                    <td style="font-weight:700;color:var(--primary);"><?= toBanglaNumber($t['present_days']) ?> দিন</td>
                    <td style="font-size:13px;"><?= $t['total_minutes'] > 0 ? toBanglaNumber($hrs).'ঘ '.toBanglaNumber($mins).'মি' : '-' ?></td>
                    <td style="font-size:13px;"><?= $avgMins > 0 ? toBanglaNumber($avgH).'ঘ '.toBanglaNumber($avgM).'মি' : '-' ?></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <div style="flex:1;background:var(--border);border-radius:3px;height:6px;min-width:60px;">
                                <div style="background:<?= $color ?>;width:<?= min($rate,100) ?>%;height:100%;border-radius:3px;"></div>
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

<?php require_once '../../includes/footer.php'; ?>
