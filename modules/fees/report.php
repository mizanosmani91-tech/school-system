<?php
require_once '../../includes/functions.php';
requireLogin(['super_admin','principal','accountant']);
$pageTitle = 'আর্থিক রিপোর্ট';
$db = getDB();

$year = (int)($_GET['year'] ?? date('Y'));
$month = $_GET['month'] ?? '';

// Monthly summary
$monthlySummary = $db->prepare("SELECT
    DATE_FORMAT(payment_date,'%Y-%m') as month_year,
    COUNT(*) as transactions,
    SUM(paid_amount) as total_collected,
    SUM(discount) as total_discount,
    SUM(fine) as total_fine
    FROM fee_collections WHERE YEAR(payment_date)=?
    GROUP BY month_year ORDER BY month_year");
$monthlySummary->execute([$year]); $monthly = $monthlySummary->fetchAll();

// Fee type breakdown
$byType = $db->prepare("SELECT ft.fee_name_bn, COUNT(*) as cnt, SUM(fc.paid_amount) as total
    FROM fee_collections fc JOIN fee_types ft ON fc.fee_type_id=ft.id
    WHERE YEAR(fc.payment_date)=? GROUP BY ft.id ORDER BY total DESC");
$byType->execute([$year]); $byTypeData = $byType->fetchAll();

// Annual total
$annualTotal = $db->prepare("SELECT COALESCE(SUM(paid_amount),0) FROM fee_collections WHERE YEAR(payment_date)=?");
$annualTotal->execute([$year]); $annualAmt = $annualTotal->fetchColumn();

// By payment method
$byMethod = $db->prepare("SELECT payment_method, SUM(paid_amount) as total, COUNT(*) as cnt
    FROM fee_collections WHERE YEAR(payment_date)=? GROUP BY payment_method ORDER BY total DESC");
$byMethod->execute([$year]); $methodData = $byMethod->fetchAll();

require_once '../../includes/header.php';
?>
<div class="section-header">
    <h2 class="section-title"><i class="fas fa-chart-bar"></i> আর্থিক রিপোর্ট</h2>
    <div style="display:flex;gap:8px;">
        <button onclick="window.print()" class="btn btn-outline btn-sm no-print"><i class="fas fa-print"></i> প্রিন্ট</button>
    </div>
</div>

<div class="card mb-16 no-print">
    <div class="card-body" style="padding:12px 20px;">
        <form method="GET" style="display:flex;gap:12px;align-items:flex-end;">
            <div class="form-group" style="margin:0;">
                <label style="font-size:12px;">বছর</label>
                <select name="year" class="form-control" style="padding:7px;" onchange="this.form.submit()">
                    <?php for($y=date('Y');$y>=2020;$y--): ?>
                    <option value="<?=$y?>" <?=$year==$y?'selected':''?>><?=toBanglaNumber($y)?></option>
                    <?php endfor; ?>
                </select>
            </div>
        </form>
    </div>
</div>

<!-- Annual Summary -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin-bottom:24px;">
    <div class="stat-card green"><div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
        <div><div class="stat-value">৳<?=number_format($annualAmt)?></div><div class="stat-label"><?=toBanglaNumber($year)?> সালে মোট আদায়</div></div></div>
    <?php $maxMonth = !empty($monthly) ? max(array_column($monthly,'total_collected')) : 0;
          $maxMonthData = !empty($monthly) ? $monthly[array_search($maxMonth,array_column($monthly,'total_collected'))] : null; ?>
    <?php if ($maxMonthData): ?>
    <div class="stat-card blue"><div class="stat-icon"><i class="fas fa-arrow-up"></i></div>
        <div><div class="stat-value">৳<?=number_format($maxMonthData['total_collected'])?></div><div class="stat-label">সর্বোচ্চ মাস (<?=e($maxMonthData['month_year'])?>)</div></div></div>
    <?php endif; ?>
    <div class="stat-card orange"><div class="stat-icon"><i class="fas fa-receipt"></i></div>
        <div><div class="stat-value"><?=toBanglaNumber(array_sum(array_column($monthly,'transactions')))?></div><div class="stat-label">মোট লেনদেন</div></div></div>
</div>

<div class="grid-2">
    <!-- Monthly Chart -->
    <div class="card">
        <div class="card-header"><span class="card-title"><i class="fas fa-chart-bar"></i> মাসিক সংগ্রহ</span></div>
        <div class="card-body">
            <?php
            $months_bn = ['','জানু','ফেব্রু','মার্চ','এপ্রিল','মে','জুন','জুলাই','আগস্ট','সেপ্টে','অক্টো','নভে','ডিসে'];
            $monthlyMap = [];
            foreach ($monthly as $m) $monthlyMap[$m['month_year']] = $m['total_collected'];
            $maxVal = max(array_merge([1], array_values($monthlyMap)));
            ?>
            <div style="display:flex;align-items:flex-end;gap:6px;height:140px;border-bottom:2px solid var(--border);padding-bottom:8px;">
                <?php for($m=1;$m<=12;$m++):
                    $key = $year.'-'.str_pad($m,2,'0',STR_PAD_LEFT);
                    $val = $monthlyMap[$key] ?? 0;
                    $h = $maxVal > 0 ? ($val/$maxVal)*120 : 0;
                ?>
                <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:3px;">
                    <?php if($val>0): ?><div style="font-size:9px;color:var(--text-muted);">৳<?=number_format($val/1000,0)?>k</div><?php endif; ?>
                    <div style="width:100%;background:<?=$val>0?'var(--primary-light)':'var(--border)'?>;border-radius:3px 3px 0 0;height:<?=max(3,$h)?>px;" title="৳<?=number_format($val)?>"></div>
                    <div style="font-size:10px;color:var(--text-muted);"><?=$months_bn[$m]?></div>
                </div>
                <?php endfor; ?>
            </div>
        </div>
    </div>

    <!-- By Fee Type -->
    <div class="card">
        <div class="card-header"><span class="card-title"><i class="fas fa-tags"></i> ফির ধরন অনুযায়ী</span></div>
        <div class="card-body" style="padding:0;">
            <table>
                <thead><tr><th>ফির ধরন</th><th>সংখ্যা</th><th>মোট</th></tr></thead>
                <tbody>
                    <?php foreach($byTypeData as $bt): ?>
                    <tr>
                        <td style="font-size:13px;"><?=e($bt['fee_name_bn'])?></td>
                        <td style="font-size:13px;"><?=toBanglaNumber($bt['cnt'])?></td>
                        <td style="font-weight:700;color:var(--success);">৳<?=number_format($bt['total'])?></td>
                    </tr>
                    <?php endforeach; ?>
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
            $total = max(1, $annualAmt);
            foreach ($methodData as $md):
                $pct = round(($md['total']/$total)*100);
                $color = $methodColors[$md['payment_method']] ?? 'var(--primary)';
            ?>
            <div style="margin-bottom:12px;">
                <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px;">
                    <span style="font-weight:600;"><?=e(strtoupper($md['payment_method']))?></span>
                    <span>৳<?=number_format($md['total'])?> (<?=$pct?>%)</span>
                </div>
                <div style="background:var(--border);border-radius:4px;height:8px;">
                    <div style="background:<?=$color?>;border-radius:4px;height:8px;width:<?=$pct?>%;transition:width .5s;"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Monthly Table -->
    <div class="card">
        <div class="card-header"><span class="card-title"><i class="fas fa-table"></i> মাসিক বিস্তারিত</span></div>
        <div class="card-body" style="padding:0;">
            <table>
                <thead><tr><th>মাস</th><th>লেনদেন</th><th>মোট আদায়</th></tr></thead>
                <tbody>
                    <?php foreach($monthly as $m): ?>
                    <tr>
                        <td style="font-size:13px;"><?=e($m['month_year'])?></td>
                        <td><?=toBanglaNumber($m['transactions'])?></td>
                        <td style="font-weight:700;color:var(--success);">৳<?=number_format($m['total_collected'])?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr style="background:var(--bg);font-weight:700;">
                        <td>মোট</td>
                        <td><?=toBanglaNumber(array_sum(array_column($monthly,'transactions')))?></td>
                        <td style="color:var(--success);">৳<?=number_format($annualAmt)?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require_once '../../includes/footer.php'; ?>
