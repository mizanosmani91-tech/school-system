<?php
require_once '../../includes/functions.php';
requireLogin(['super_admin','principal','accountant']);
$pageTitle = 'আর্থিক রিপোর্ট';
$db = getDB();

$year       = (int)($_GET['year'] ?? date('Y'));
$month      = (int)($_GET['month'] ?? 0);
$divisionId = (int)($_GET['division_id'] ?? 0);

// সব বিভাগ
$divisions = $db->query("SELECT * FROM divisions WHERE is_active=1 ORDER BY sort_order, id")->fetchAll();

// Filter conditions
$divJoin   = "LEFT JOIN students s2 ON fc.student_id=s2.id";
$divWhere  = $divisionId ? "AND s2.division_id=$divisionId" : "";
$monthWhere = $month ? "AND MONTH(fc.payment_date)=$month" : "";

// Monthly summary (মাস filter থাকলে শুধু সেই মাসের দিন-ভিত্তিক, না থাকলে মাসিক)
$monthlySummary = $db->prepare("SELECT
    DATE_FORMAT(fc.payment_date,'%Y-%m') as month_year,
    COUNT(*) as transactions,
    SUM(fc.paid_amount) as total_collected,
    SUM(fc.discount) as total_discount,
    SUM(fc.fine) as total_fine
    FROM fee_collections fc
    $divJoin
    WHERE YEAR(fc.payment_date)=? $divWhere $monthWhere
    GROUP BY DATE_FORMAT(fc.payment_date,'%Y-%m')
    ORDER BY DATE_FORMAT(fc.payment_date,'%Y-%m')");
$monthlySummary->execute([$year]);
$monthly = $monthlySummary->fetchAll();

// Fee type breakdown
$byType = $db->prepare("SELECT ft.fee_name_bn, ft.id as ft_id, COUNT(*) as cnt, SUM(fc.paid_amount) as total
    FROM fee_collections fc
    JOIN fee_types ft ON fc.fee_type_id=ft.id
    $divJoin
    WHERE YEAR(fc.payment_date)=? $divWhere $monthWhere
    GROUP BY ft.id, ft.fee_name_bn ORDER BY SUM(fc.paid_amount) DESC");
$byType->execute([$year]);
$byTypeData = $byType->fetchAll();

// Total
$annualTotal = $db->prepare("SELECT COALESCE(SUM(fc.paid_amount),0)
    FROM fee_collections fc $divJoin
    WHERE YEAR(fc.payment_date)=? $divWhere $monthWhere");
$annualTotal->execute([$year]);
$annualAmt = $annualTotal->fetchColumn();

// By payment method
$byMethod = $db->prepare("SELECT fc.payment_method, SUM(fc.paid_amount) as total, COUNT(*) as cnt
    FROM fee_collections fc $divJoin
    WHERE YEAR(fc.payment_date)=? $divWhere $monthWhere
    GROUP BY fc.payment_method ORDER BY SUM(fc.paid_amount) DESC");
$byMethod->execute([$year]);
$methodData = $byMethod->fetchAll();

// বিভাগ অনুযায়ী breakdown
$byDivision = $db->prepare("SELECT d.division_name_bn, COUNT(*) as cnt, SUM(fc.paid_amount) as total
    FROM fee_collections fc
    LEFT JOIN students s2 ON fc.student_id=s2.id
    LEFT JOIN divisions d ON s2.division_id=d.id
    WHERE YEAR(fc.payment_date)=? $monthWhere
    GROUP BY d.id, d.division_name_bn ORDER BY SUM(fc.paid_amount) DESC");
$byDivision->execute([$year]);
$divisionData = $byDivision->fetchAll();

$months_bn_list = ['','জানুয়ারি','ফেব্রুয়ারি','মার্চ','এপ্রিল','মে','জুন','জুলাই','আগস্ট','সেপ্টেম্বর','অক্টোবর','নভেম্বর','ডিসেম্বর'];

require_once '../../includes/header.php';
?>

<div class="section-header">
    <h2 class="section-title"><i class="fas fa-chart-bar"></i> আর্থিক রিপোর্ট</h2>
    <button onclick="window.print()" class="btn btn-outline btn-sm no-print">
        <i class="fas fa-print"></i> প্রিন্ট
    </button>
</div>

<!-- বিভাগ Quick-Tab -->
<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px;" class="no-print">
    <a href="report.php?year=<?= $year ?>&month=<?= $month ?>"
       class="btn btn-sm <?= !$divisionId ? 'btn-primary' : 'btn-outline' ?>">
        <i class="fas fa-layer-group"></i> সব বিভাগ
    </a>
    <?php foreach ($divisions as $d): ?>
    <a href="report.php?division_id=<?= $d['id'] ?>&year=<?= $year ?>&month=<?= $month ?>"
       class="btn btn-sm <?= $divisionId == $d['id'] ? 'btn-primary' : 'btn-outline' ?>">
        <?= e($d['division_name_bn']) ?>
    </a>
    <?php endforeach; ?>
</div>

<!-- Filter -->
<div class="card mb-16 no-print">
    <div class="card-body" style="padding:12px 20px;">
        <form method="GET" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
            <input type="hidden" name="division_id" value="<?= $divisionId ?>">

            <!-- বছর -->
            <div class="form-group" style="margin:0;">
                <label style="font-size:12px;font-weight:600;">বছর</label>
                <select name="year" class="form-control" style="padding:7px;" onchange="this.form.submit()">
                    <?php for ($y = date('Y'); $y >= 2020; $y--): ?>
                    <option value="<?= $y ?>" <?= $year == $y ? 'selected' : '' ?>><?= toBanglaNumber($y) ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <!-- মাস -->
            <div class="form-group" style="margin:0;">
                <label style="font-size:12px;font-weight:600;">মাস</label>
                <select name="month" class="form-control" style="padding:7px;" onchange="this.form.submit()">
                    <option value="">সব মাস</option>
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?= $m ?>" <?= $month == $m ? 'selected' : '' ?>>
                        <?= $months_bn_list[$m] ?>
                    </option>
                    <?php endfor; ?>
                </select>
            </div>

            <?php if ($month || $divisionId): ?>
            <a href="report.php?year=<?= $year ?>" class="btn btn-outline btn-sm" style="margin-bottom:1px;">
                <i class="fas fa-redo"></i> রিসেট
            </a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Summary Cards -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin-bottom:24px;">
    <div class="stat-card green">
        <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
        <div>
            <div class="stat-value">৳<?= number_format($annualAmt) ?></div>
            <div class="stat-label">
                <?php if ($month): ?>
                    <?= $months_bn_list[$month] ?>, <?= toBanglaNumber($year) ?> — মোট আদায়
                <?php else: ?>
                    <?= toBanglaNumber($year) ?> সালে মোট আদায়
                <?php endif; ?>
                <?php if ($divisionId): foreach ($divisions as $d): if ($d['id'] == $divisionId): ?>
                <span style="font-size:11px;display:block;color:var(--primary);">(<?= e($d['division_name_bn']) ?>)</span>
                <?php endif; endforeach; endif; ?>
            </div>
        </div>
    </div>
    <?php
    $maxMonth     = !empty($monthly) ? max(array_column($monthly, 'total_collected')) : 0;
    $maxMonthData = !empty($monthly) ? $monthly[array_search($maxMonth, array_column($monthly, 'total_collected'))] : null;
    ?>
    <?php if ($maxMonthData && !$month): ?>
    <div class="stat-card blue">
        <div class="stat-icon"><i class="fas fa-arrow-up"></i></div>
        <div>
            <div class="stat-value">৳<?= number_format($maxMonthData['total_collected']) ?></div>
            <div class="stat-label">সর্বোচ্চ মাস (<?= e($maxMonthData['month_year']) ?>)</div>
        </div>
    </div>
    <?php endif; ?>
    <div class="stat-card orange">
        <div class="stat-icon"><i class="fas fa-receipt"></i></div>
        <div>
            <div class="stat-value"><?= toBanglaNumber(array_sum(array_column($monthly, 'transactions'))) ?></div>
            <div class="stat-label">মোট লেনদেন</div>
        </div>
    </div>
</div>

<div class="grid-2">
    <!-- Monthly Chart (মাস filter না থাকলে দেখাবে) -->
    <?php if (!$month): ?>
    <div class="card">
        <div class="card-header"><span class="card-title"><i class="fas fa-chart-bar"></i> মাসিক সংগ্রহ</span></div>
        <div class="card-body">
            <?php
            $months_bn = ['','জানু','ফেব্রু','মার্চ','এপ্রিল','মে','জুন','জুলাই','আগস্ট','সেপ্টে','অক্টো','নভে','ডিসে'];
            $monthlyMap = [];
            foreach ($monthly as $row) $monthlyMap[$row['month_year']] = $row['total_collected'];
            $maxVal = max(array_merge([1], array_values($monthlyMap)));
            ?>
            <div style="display:flex;align-items:flex-end;gap:6px;height:140px;border-bottom:2px solid var(--border);padding-bottom:8px;">
                <?php for ($m = 1; $m <= 12; $m++):
                    $key = $year . '-' . str_pad($m, 2, '0', STR_PAD_LEFT);
                    $val = $monthlyMap[$key] ?? 0;
                    $h   = $maxVal > 0 ? ($val / $maxVal) * 120 : 0;
                    $isCurrentMonth = ($m == date('n') && $year == date('Y'));
                ?>
                <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:3px;">
                    <?php if ($val > 0): ?>
                    <div style="font-size:9px;color:var(--text-muted);">৳<?= number_format($val/1000,0) ?>k</div>
                    <?php endif; ?>
                    <a href="report.php?year=<?= $year ?>&month=<?= $m ?>&division_id=<?= $divisionId ?>"
                       style="width:100%;display:block;background:<?= $val > 0 ? ($isCurrentMonth ? 'var(--primary)' : 'var(--primary-light)') : 'var(--border)' ?>;border-radius:3px 3px 0 0;height:<?= max(3,$h) ?>px;" title="<?= $months_bn_list[$m] ?>: ৳<?= number_format($val) ?>"></a>
                    <div style="font-size:10px;color:var(--text-muted);"><?= $months_bn[$m] ?></div>
                </div>
                <?php endfor; ?>
            </div>
            <div style="font-size:11px;color:var(--text-muted);margin-top:8px;text-align:center;">
                <i class="fas fa-mouse-pointer"></i> বার এ ক্লিক করলে সেই মাসের বিস্তারিত দেখবে
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- বিভাগ অনুযায়ী -->
    <div class="card">
        <div class="card-header"><span class="card-title"><i class="fas fa-layer-group"></i> বিভাগ অনুযায়ী আদায়</span></div>
        <div class="card-body">
            <?php
            $divTotal = max(1, $annualAmt);
            foreach ($divisionData as $dd):
                $pct   = round(($dd['total'] / $divTotal) * 100);
                $label = $dd['division_name_bn'] ?? 'অজানা';
            ?>
            <div style="margin-bottom:14px;">
                <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px;">
                    <span style="font-weight:600;"><?= e($label) ?></span>
                    <span>৳<?= number_format($dd['total']) ?> (<?= $pct ?>%)</span>
                </div>
                <div style="background:var(--border);border-radius:4px;height:8px;">
                    <div style="background:var(--primary);border-radius:4px;height:8px;width:<?= $pct ?>%;"></div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($divisionData)): ?>
            <div style="text-align:center;color:var(--text-muted);padding:20px;">কোনো তথ্য নেই</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- By Fee Type -->
    <div class="card">
        <div class="card-header"><span class="card-title"><i class="fas fa-tags"></i> ফির ধরন অনুযায়ী</span></div>
        <div class="card-body" style="padding:0;">
            <table>
                <thead><tr><th>ফির ধরন</th><th>সংখ্যা</th><th>মোট</th></tr></thead>
                <tbody>
                    <?php foreach ($byTypeData as $bt): ?>
                    <tr>
                        <td style="font-size:13px;"><?= e($bt['fee_name_bn']) ?></td>
                        <td style="font-size:13px;"><?= toBanglaNumber($bt['cnt']) ?></td>
                        <td style="font-weight:700;color:var(--success);">৳<?= number_format($bt['total']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($byTypeData)): ?>
                    <tr><td colspan="3" style="text-align:center;color:var(--text-muted);padding:20px;">কোনো তথ্য নেই</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Payment Methods -->
    <div class="card">
        <div class="card-header"><span class="card-title"><i class="fas fa-mobile-alt"></i> পরিশোধ পদ্ধতি</span></div>
        <div class="card-body">
            <?php
            $methodColors = ['cash'=>'var(--success)','bkash'=>'#e2136e','nagad'=>'#f8501c','rocket'=>'#8b5cf6','bank'=>'var(--info)'];
            $totalAmt = max(1, $annualAmt);
            foreach ($methodData as $md):
                $pct   = round(($md['total'] / $totalAmt) * 100);
                $color = $methodColors[$md['payment_method']] ?? 'var(--primary)';
            ?>
            <div style="margin-bottom:12px;">
                <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px;">
                    <span style="font-weight:600;"><?= e(strtoupper($md['payment_method'])) ?></span>
                    <span>৳<?= number_format($md['total']) ?> (<?= $pct ?>%)</span>
                </div>
                <div style="background:var(--border);border-radius:4px;height:8px;">
                    <div style="background:<?= $color ?>;border-radius:4px;height:8px;width:<?= $pct ?>%;transition:width .5s;"></div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($methodData)): ?>
            <div style="text-align:center;color:var(--text-muted);padding:20px;">কোনো তথ্য নেই</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Monthly / Detail Table -->
    <div class="card" style="grid-column:1/-1;">
        <div class="card-header">
            <span class="card-title">
                <i class="fas fa-table"></i>
                <?= $month ? $months_bn_list[$month] . ' ' . toBanglaNumber($year) . ' — বিস্তারিত' : 'মাসিক বিস্তারিত' ?>
            </span>
        </div>
        <div class="card-body" style="padding:0;">
            <table>
                <thead>
                    <tr>
                        <th>মাস</th>
                        <th>লেনদেন</th>
                        <th>ছাড়</th>
                        <th>জরিমানা</th>
                        <th>মোট আদায়</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($monthly as $row): ?>
                    <tr>
                        <td style="font-size:13px;">
                            <?php
                            $parts = explode('-', $row['month_year']);
                            $mNum  = (int)($parts[1] ?? 0);
                            echo e($months_bn_list[$mNum] ?? $row['month_year']) . ' ' . toBanglaNumber($parts[0] ?? $year);
                            ?>
                        </td>
                        <td><?= toBanglaNumber($row['transactions']) ?></td>
                        <td style="color:var(--success);">৳<?= number_format($row['total_discount'] ?? 0) ?></td>
                        <td style="color:var(--danger);">৳<?= number_format($row['total_fine'] ?? 0) ?></td>
                        <td style="font-weight:700;color:var(--success);">৳<?= number_format($row['total_collected']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($monthly)): ?>
                    <tr><td colspan="5" style="text-align:center;color:var(--text-muted);padding:20px;">কোনো তথ্য নেই</td></tr>
                    <?php else: ?>
                    <tr style="background:var(--bg);font-weight:700;">
                        <td>মোট</td>
                        <td><?= toBanglaNumber(array_sum(array_column($monthly, 'transactions'))) ?></td>
                        <td style="color:var(--success);">৳<?= number_format(array_sum(array_column($monthly, 'total_discount'))) ?></td>
                        <td style="color:var(--danger);">৳<?= number_format(array_sum(array_column($monthly, 'total_fine'))) ?></td>
                        <td style="color:var(--success);">৳<?= number_format($annualAmt) ?></td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
