<?php
require_once 'includes/functions.php';
$db = getDB();

// ===== AUTH CHECK =====
// আপনার বর্তমান auth/session check এখানে রাখুন
// যেমন: if (!isLoggedIn()) { header('Location: login.php'); exit; }

// ===== AJAX: মাসিক রিপোর্ট JSON =====
if (isset($_GET['ajax']) && $_GET['ajax'] === 'monthly') {
    header('Content-Type: application/json');
    $month  = $_GET['month'] ?? date('Y-m');
    $start  = $month . '-01';
    $end    = date('Y-m-t', strtotime($start));

    $rows = $db->prepare("
        SELECT
            t.id AS teacher_id,
            t.teacher_id_no,
            t.designation_bn,
            u.name_bn,
            u.name,
            COUNT(ta.id)                                             AS total_days,
            SUM(ta.status = 'present')                               AS present_days,
            SUM(ta.status = 'absent')                                AS absent_days,
            SUM(ta.status = 'half_day')                              AS half_days,
            SUM(ta.status = 'leave')                                 AS leave_days,
            SEC_TO_TIME(
                SUM(
                    CASE WHEN ta.check_in IS NOT NULL AND ta.check_out IS NOT NULL
                    THEN TIME_TO_SEC(TIMEDIFF(ta.check_out, ta.check_in))
                    ELSE 0 END
                )
            )                                                        AS total_hours
        FROM teachers t
        JOIN users u ON t.user_id = u.id
        LEFT JOIN teacher_attendance ta ON ta.teacher_id = t.id
            AND ta.date BETWEEN ? AND ?
        WHERE t.is_active = 1
        GROUP BY t.id
        ORDER BY u.name_bn
    ");
    $rows->execute([$start, $end]);
    echo json_encode(['success' => true, 'data' => $rows->fetchAll(PDO::FETCH_ASSOC), 'month' => $month, 'start' => $start, 'end' => $end]);
    exit;
}

// ===== TODAY DATA =====
$today = date('Y-m-d');
$currentMonth = date('Y-m');

// আজকের চেক ইন/আউট
$todayStmt = $db->prepare("
    SELECT
        ta.*,
        u.name_bn, u.name,
        t.teacher_id_no, t.designation_bn,
        CASE WHEN ta.check_in IS NOT NULL AND ta.check_out IS NOT NULL
             THEN SEC_TO_TIME(TIME_TO_SEC(TIMEDIFF(ta.check_out, ta.check_in)))
             ELSE NULL END AS worked_hours
    FROM teacher_attendance ta
    JOIN teachers t ON ta.teacher_id = t.id
    JOIN users u ON t.user_id = u.id
    WHERE ta.date = ?
    ORDER BY ta.check_in ASC
");
$todayStmt->execute([$today]);
$todayRecords = $todayStmt->fetchAll();

// অনুপস্থিত শিক্ষক (আজ কোনো রেকর্ড নেই)
$absentStmt = $db->prepare("
    SELECT t.teacher_id_no, t.designation_bn, u.name_bn, u.name
    FROM teachers t
    JOIN users u ON t.user_id = u.id
    WHERE t.is_active = 1
      AND t.id NOT IN (
          SELECT teacher_id FROM teacher_attendance WHERE date = ?
      )
    ORDER BY u.name_bn
");
$absentStmt->execute([$today]);
$absentTeachers = $absentStmt->fetchAll();

// সারাংশ কার্ড
$totalTeachers  = $db->query("SELECT COUNT(*) FROM teachers WHERE is_active=1")->fetchColumn();
$presentToday   = count($todayRecords);
$absentToday    = count($absentTeachers);
$checkedOut     = array_filter($todayRecords, fn($r) => $r['check_out']);
$avgHours       = 0;
if (count($checkedOut)) {
    $totalSec = 0;
    foreach ($checkedOut as $r) {
        if ($r['check_in'] && $r['check_out'])
            $totalSec += strtotime($r['check_out']) - strtotime($r['check_in']);
    }
    $avgHours = round($totalSec / count($checkedOut) / 3600, 1);
}

$instituteName = '';
try { $instituteName = getSetting('institute_name', 'মাদ্রাসা ম্যানেজমেন্ট সিস্টেম'); } catch(Exception $e){}

// বাংলা মাস
$bnMonths = ['','জানুয়ারি','ফেব্রুয়ারি','মার্চ','এপ্রিল','মে','জুন','জুলাই','আগস্ট','সেপ্টেম্বর','অক্টোবর','নভেম্বর','ডিসেম্বর'];
function toBn($n){ return strtr((string)$n,['0'=>'০','1'=>'১','2'=>'২','3'=>'৩','4'=>'৪','5'=>'৫','6'=>'৬','7'=>'৭','8'=>'৮','9'=>'৯']); }
function fmtTime($t){ if(!$t) return '—'; $h=date('g',strtotime($t)); $m=date('i',strtotime($t)); $ampm=date('A',strtotime($t))==='AM'?'AM':'PM'; return toBn($h).':'.toBn($m).' '.$ampm; }
function fmtHours($t){ if(!$t) return '—'; preg_match('/(\d+):(\d+)/',$t,$m); return toBn((int)$m[1]).'ঘ '.toBn((int)$m[2]).'মি'; }
$todayBn = toBn(date('j')).' '.$bnMonths[(int)date('n')].' '.toBn(date('Y'));
?>
<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>উপস্থিতি রিপোর্ট | <?= htmlspecialchars($instituteName) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
/* ===== RESET & BASE ===== */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
    --primary:   #1a3c5e;
    --primary-light: #2471a3;
    --success:   #27ae60;
    --danger:    #e74c3c;
    --warning:   #f39c12;
    --info:      #2980b9;
    --bg:        #f0f4f8;
    --card:      #ffffff;
    --border:    #e2e8f0;
    --text:      #2d3748;
    --muted:     #718096;
    --radius:    12px;
    --shadow:    0 2px 12px rgba(0,0,0,.08);
}
body { font-family: 'Hind Siliguri', sans-serif; background: var(--bg); color: var(--text); font-size: 15px; }

/* ===== PAGE HEADER ===== */
.page-header {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
    color: #fff; padding: 24px 28px; display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: 12px;
}
.page-header h1 { font-size: 22px; font-weight: 700; display: flex; align-items: center; gap: 10px; }
.page-header .subtitle { font-size: 13px; opacity: .75; margin-top: 3px; }
.header-actions { display: flex; gap: 10px; }
.btn { display: inline-flex; align-items: center; gap: 7px; padding: 9px 18px; border-radius: 8px;
       border: none; cursor: pointer; font-family: inherit; font-size: 14px; font-weight: 600; transition: all .2s; text-decoration: none; }
.btn-white   { background: rgba(255,255,255,.15); color: #fff; border: 1px solid rgba(255,255,255,.3); }
.btn-white:hover { background: rgba(255,255,255,.25); }
.btn-success { background: var(--success); color: #fff; }
.btn-success:hover { background: #219a52; }
.btn-primary { background: var(--primary); color: #fff; }
.btn-primary:hover { background: #152f4a; }
.btn-outline { background: transparent; color: var(--primary); border: 1.5px solid var(--border); }
.btn-outline:hover { border-color: var(--primary); background: #f0f8ff; }

/* ===== LAYOUT ===== */
.main { padding: 24px 28px; max-width: 1400px; margin: 0 auto; }

/* ===== SUMMARY CARDS ===== */
.summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 16px; margin-bottom: 24px; }
.s-card {
    background: var(--card); border-radius: var(--radius); padding: 20px; box-shadow: var(--shadow);
    display: flex; align-items: center; gap: 16px; border-left: 4px solid transparent;
}
.s-card.total   { border-color: var(--primary-light); }
.s-card.present { border-color: var(--success); }
.s-card.absent  { border-color: var(--danger); }
.s-card.hours   { border-color: var(--warning); }
.s-card.pct     { border-color: var(--info); }
.s-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; }
.s-card.total   .s-icon { background: #e8f0fe; color: var(--primary-light); }
.s-card.present .s-icon { background: #d4edda; color: var(--success); }
.s-card.absent  .s-icon { background: #fde8e8; color: var(--danger); }
.s-card.hours   .s-icon { background: #fef3cd; color: var(--warning); }
.s-card.pct     .s-icon { background: #d1ecf1; color: var(--info); }
.s-val { font-size: 30px; font-weight: 700; line-height: 1; }
.s-label { font-size: 13px; color: var(--muted); margin-top: 4px; }

/* ===== TABS ===== */
.tabs { display: flex; gap: 0; background: var(--card); border-radius: var(--radius) var(--radius) 0 0;
        box-shadow: var(--shadow); overflow: hidden; border-bottom: 2px solid var(--border); }
.tab-btn { flex: 1; padding: 14px 10px; text-align: center; border: none; background: transparent;
           cursor: pointer; font-family: inherit; font-size: 14px; font-weight: 600; color: var(--muted);
           transition: all .2s; display: flex; align-items: center; justify-content: center; gap: 7px; }
.tab-btn.active { color: var(--primary); border-bottom: 3px solid var(--primary); background: #f7fbff; }
.tab-btn:hover:not(.active) { background: #f8fafc; }
.tab-pane { display: none; }
.tab-pane.active { display: block; }

/* ===== TABLE CARD ===== */
.table-card { background: var(--card); border-radius: 0 0 var(--radius) var(--radius); box-shadow: var(--shadow); overflow: hidden; }
.table-toolbar { padding: 16px 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; border-bottom: 1px solid var(--border); }
.table-toolbar h3 { font-size: 15px; font-weight: 700; display: flex; align-items: center; gap: 8px; }
.toolbar-right { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.search-input { padding: 8px 14px; border: 1.5px solid var(--border); border-radius: 8px; font-family: inherit; font-size: 14px; outline: none; width: 220px; }
.search-input:focus { border-color: var(--primary-light); }
.month-input { padding: 8px 12px; border: 1.5px solid var(--border); border-radius: 8px; font-family: inherit; font-size: 14px; outline: none; }

table { width: 100%; border-collapse: collapse; }
thead tr { background: #f7f9fc; }
th { padding: 13px 16px; text-align: right; font-size: 13px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: .3px; white-space: nowrap; }
th:first-child, td:first-child { text-align: right; }
td { padding: 13px 16px; border-top: 1px solid var(--border); font-size: 14px; vertical-align: middle; }
tr:hover td { background: #f9fbff; }

.badge { display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
.badge-success { background: #d4edda; color: #155724; }
.badge-danger  { background: #fde8e8; color: #721c24; }
.badge-warning { background: #fff3cd; color: #856404; }
.badge-info    { background: #d1ecf1; color: #0c5460; }
.badge-secondary { background: #e2e8f0; color: #495057; }

.teacher-cell { display: flex; align-items: center; gap: 10px; }
.avatar { width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center;
          justify-content: center; font-weight: 700; font-size: 15px; color: #fff; flex-shrink: 0; }

/* ===== MONTHLY TABLE LOADING ===== */
.loading-row td { text-align: center; padding: 40px; color: var(--muted); }
.spinner { display: inline-block; width: 22px; height: 22px; border: 3px solid var(--border);
           border-top-color: var(--primary); border-radius: 50%; animation: spin .7s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

/* ===== EMPTY STATE ===== */
.empty { text-align: center; padding: 48px 20px; color: var(--muted); }
.empty i { font-size: 40px; margin-bottom: 12px; opacity: .4; display: block; }

/* ===== PRINT ===== */
.print-only { display: none; }
@media print {
    .page-header, .tabs, .table-toolbar, .header-actions, .no-print { display: none !important; }
    .print-only { display: block; text-align: center; margin-bottom: 16px; }
    .tab-pane { display: block !important; margin-bottom: 30px; }
    body { background: #fff; }
    .table-card { box-shadow: none; }
}

/* ===== PROGRESS BAR ===== */
.pct-bar { display: flex; align-items: center; gap: 8px; }
.bar-bg { flex: 1; height: 6px; background: #e2e8f0; border-radius: 3px; overflow: hidden; }
.bar-fill { height: 100%; border-radius: 3px; transition: width .4s; }

/* ===== RESPONSIVE ===== */
@media (max-width: 768px) {
    .main { padding: 16px; }
    .page-header { padding: 16px; }
    th, td { padding: 10px 12px; font-size: 13px; }
    .search-input { width: 100%; }
}
</style>
</head>
<body>

<!-- ===== PAGE HEADER ===== -->
<div class="page-header">
    <div>
        <h1><i class="fas fa-clipboard-list"></i> শিক্ষক উপস্থিতি রিপোর্ট</h1>
        <div class="subtitle"><?= $instituteName ?> | আজ: <?= $todayBn ?></div>
    </div>
    <div class="header-actions">
        <a href="staff_attendance.php" class="btn btn-white" target="_blank">
            <i class="fas fa-fingerprint"></i> চেক ইন / আউট
        </a>
        <button onclick="window.print()" class="btn btn-white no-print">
            <i class="fas fa-print"></i> প্রিন্ট
        </button>
        <button onclick="exportCSV()" class="btn btn-white no-print">
            <i class="fas fa-file-csv"></i> CSV
        </button>
    </div>
</div>

<!-- ===== PRINT HEADER ===== -->
<div class="print-only">
    <h2><?= htmlspecialchars($instituteName) ?></h2>
    <p>শিক্ষক উপস্থিতি রিপোর্ট | <?= $todayBn ?></p>
</div>

<div class="main">

    <!-- ===== SUMMARY CARDS ===== -->
    <div class="summary-grid">
        <div class="s-card total">
            <div class="s-icon"><i class="fas fa-users"></i></div>
            <div>
                <div class="s-val"><?= toBn($totalTeachers) ?></div>
                <div class="s-label">মোট শিক্ষক/স্টাফ</div>
            </div>
        </div>
        <div class="s-card present">
            <div class="s-icon"><i class="fas fa-user-check"></i></div>
            <div>
                <div class="s-val"><?= toBn($presentToday) ?></div>
                <div class="s-label">আজ উপস্থিত</div>
            </div>
        </div>
        <div class="s-card absent">
            <div class="s-icon"><i class="fas fa-user-times"></i></div>
            <div>
                <div class="s-val"><?= toBn($absentToday) ?></div>
                <div class="s-label">আজ অনুপস্থিত</div>
            </div>
        </div>
        <div class="s-card hours">
            <div class="s-icon"><i class="fas fa-clock"></i></div>
            <div>
                <div class="s-val"><?= toBn($avgHours) ?>ঘ</div>
                <div class="s-label">গড় কর্মঘণ্টা (আজ)</div>
            </div>
        </div>
        <div class="s-card pct">
            <div class="s-icon"><i class="fas fa-chart-pie"></i></div>
            <div>
                <div class="s-val"><?= $totalTeachers > 0 ? toBn(round($presentToday/$totalTeachers*100)) : '০' ?>%</div>
                <div class="s-label">উপস্থিতির হার</div>
            </div>
        </div>
    </div>

    <!-- ===== TABS ===== -->
    <div class="tabs no-print">
        <button class="tab-btn active" onclick="switchTab('today', this)">
            <i class="fas fa-calendar-day"></i> আজকের রিপোর্ট
        </button>
        <button class="tab-btn" onclick="switchTab('absent', this)">
            <i class="fas fa-user-times"></i> অনুপস্থিত
            <span class="badge badge-danger" style="margin-left:4px;"><?= toBn($absentToday) ?></span>
        </button>
        <button class="tab-btn" onclick="switchTab('monthly', this)">
            <i class="fas fa-calendar-alt"></i> মাসিক সারসংক্ষেপ
        </button>
    </div>

    <!-- ===== TAB: আজকের রিপোর্ট ===== -->
    <div id="tab-today" class="tab-pane active table-card">
        <div class="table-toolbar">
            <h3><i class="fas fa-calendar-day" style="color:var(--primary)"></i> আজকের চেক ইন / আউট</h3>
            <div class="toolbar-right">
                <input type="text" class="search-input" id="searchToday" placeholder="নাম খুঁজুন..." oninput="filterTable('tableToday', this.value)">
            </div>
        </div>
        <div style="overflow-x:auto;">
        <table id="tableToday">
            <thead>
                <tr>
                    <th>#</th>
                    <th>নাম</th>
                    <th>পদবি</th>
                    <th>চেক ইন</th>
                    <th>চেক আউট</th>
                    <th>মোট সময়</th>
                    <th>অবস্থা</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($todayRecords)): ?>
                <tr><td colspan="7">
                    <div class="empty"><i class="fas fa-info-circle"></i> আজ এখনো কেউ চেক ইন করেননি।</div>
                </td></tr>
            <?php else: ?>
                <?php $colors = ['#1a5276','#27ae60','#e74c3c','#8e44ad','#d35400','#16a085','#c0392b','#2471a3']; ?>
                <?php foreach ($todayRecords as $i => $r): ?>
                <?php
                    $name = $r['name_bn'] ?: $r['name'];
                    $initial = mb_substr($name, 0, 1);
                    $color = $colors[$i % count($colors)];
                    $hasOut = !empty($r['check_out']);
                ?>
                <tr>
                    <td><?= toBn($i+1) ?></td>
                    <td>
                        <div class="teacher-cell">
                            <div class="avatar" style="background:<?= $color ?>"><?= htmlspecialchars($initial) ?></div>
                            <div>
                                <div style="font-weight:600;"><?= htmlspecialchars($name) ?></div>
                                <div style="font-size:12px;color:var(--muted);"><?= htmlspecialchars($r['teacher_id_no']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td><?= htmlspecialchars($r['designation_bn'] ?? '—') ?></td>
                    <td style="color:var(--success);font-weight:600;"><?= fmtTime($r['check_in']) ?></td>
                    <td style="color:var(--danger);font-weight:600;"><?= fmtTime($r['check_out']) ?></td>
                    <td><?= $r['worked_hours'] ? '<span style="font-weight:600;">'.fmtHours($r['worked_hours']).'</span>' : '<span style="color:var(--muted)">চলমান</span>' ?></td>
                    <td>
                        <?php if ($hasOut): ?>
                            <span class="badge badge-success"><i class="fas fa-check-circle"></i> সম্পন্ন</span>
                        <?php else: ?>
                            <span class="badge badge-info"><i class="fas fa-circle" style="font-size:8px;animation:pulse 1.5s infinite"></i> কর্মরত</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>

    <!-- ===== TAB: অনুপস্থিত ===== -->
    <div id="tab-absent" class="tab-pane table-card">
        <div class="table-toolbar">
            <h3><i class="fas fa-user-times" style="color:var(--danger)"></i> আজ অনুপস্থিত শিক্ষক/স্টাফ</h3>
        </div>
        <div style="overflow-x:auto;">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>নাম</th>
                    <th>আইডি</th>
                    <th>পদবি</th>
                    <th>অবস্থা</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($absentTeachers)): ?>
                <tr><td colspan="5">
                    <div class="empty"><i class="fas fa-check-circle" style="color:var(--success)"></i> আলহামদুলিল্লাহ! আজ সবাই উপস্থিত আছেন।</div>
                </td></tr>
            <?php else: ?>
                <?php foreach ($absentTeachers as $i => $r): ?>
                <?php $name = $r['name_bn'] ?: $r['name']; ?>
                <tr>
                    <td><?= toBn($i+1) ?></td>
                    <td>
                        <div class="teacher-cell">
                            <div class="avatar" style="background:#e74c3c"><?= htmlspecialchars(mb_substr($name,0,1)) ?></div>
                            <div style="font-weight:600;"><?= htmlspecialchars($name) ?></div>
                        </div>
                    </td>
                    <td style="color:var(--muted);font-size:13px;"><?= htmlspecialchars($r['teacher_id_no']) ?></td>
                    <td><?= htmlspecialchars($r['designation_bn'] ?? '—') ?></td>
                    <td><span class="badge badge-danger"><i class="fas fa-times-circle"></i> অনুপস্থিত</span></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>

    <!-- ===== TAB: মাসিক সারসংক্ষেপ ===== -->
    <div id="tab-monthly" class="tab-pane table-card">
        <div class="table-toolbar">
            <h3><i class="fas fa-calendar-alt" style="color:var(--primary)"></i> মাসিক উপস্থিতি সারসংক্ষেপ</h3>
            <div class="toolbar-right">
                <input type="month" id="monthPicker" class="month-input" value="<?= $currentMonth ?>" onchange="loadMonthly(this.value)">
                <button class="btn btn-outline" onclick="loadMonthly(document.getElementById('monthPicker').value)">
                    <i class="fas fa-sync"></i> লোড করুন
                </button>
            </div>
        </div>
        <div style="overflow-x:auto;">
        <table id="tableMonthly">
            <thead>
                <tr>
                    <th>#</th>
                    <th>নাম</th>
                    <th>পদবি</th>
                    <th>উপস্থিত দিন</th>
                    <th>অনুপস্থিত</th>
                    <th>ছুটি</th>
                    <th>মোট কর্মঘণ্টা</th>
                    <th>উপস্থিতির হার</th>
                </tr>
            </thead>
            <tbody id="monthlyBody">
                <tr class="loading-row"><td colspan="8">
                    <div class="spinner"></div> লোড হচ্ছে...
                </td></tr>
            </tbody>
        </table>
        </div>
    </div>

</div><!-- /main -->

<style>
@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.4} }
</style>

<script>
// ===== TAB SWITCH =====
function switchTab(id, btn) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('tab-' + id).classList.add('active');
    if (id === 'monthly') loadMonthly(document.getElementById('monthPicker').value);
}

// ===== TABLE SEARCH =====
function filterTable(tableId, query) {
    const q = query.trim().toLowerCase();
    document.querySelectorAll('#' + tableId + ' tbody tr').forEach(row => {
        row.style.display = (!q || row.textContent.toLowerCase().includes(q)) ? '' : 'none';
    });
}

// ===== MONTHLY LOAD =====
function loadMonthly(month) {
    const body = document.getElementById('monthlyBody');
    body.innerHTML = '<tr class="loading-row"><td colspan="8"><div class="spinner"></div> লোড হচ্ছে...</td></tr>';

    fetch('?ajax=monthly&month=' + month)
        .then(r => r.json())
        .then(res => {
            if (!res.success || !res.data.length) {
                body.innerHTML = '<tr><td colspan="8"><div class="empty"><i class="fas fa-database"></i> এই মাসে কোনো তথ্য নেই।</div></td></tr>';
                return;
            }
            const colors = ['#1a5276','#27ae60','#e74c3c','#8e44ad','#d35400','#16a085','#c0392b','#2471a3'];
            const totalDaysInMonth = new Date(month + '-01');
            const daysInMonth = new Date(totalDaysInMonth.getFullYear(), totalDaysInMonth.getMonth()+1, 0).getDate();

            body.innerHTML = res.data.map((r, i) => {
                const name = r.name_bn || r.name;
                const initial = [...name][0] || '?';
                const color = colors[i % colors.length];
                const pct = daysInMonth > 0 ? Math.round((r.present_days / daysInMonth) * 100) : 0;
                const barColor = pct >= 75 ? '#27ae60' : pct >= 50 ? '#f39c12' : '#e74c3c';
                const pctBadge = pct >= 75 ? 'badge-success' : pct >= 50 ? 'badge-warning' : 'badge-danger';

                return `<tr>
                    <td>${toBn(i+1)}</td>
                    <td>
                        <div class="teacher-cell">
                            <div class="avatar" style="background:${color}">${initial}</div>
                            <div>
                                <div style="font-weight:600;">${name}</div>
                                <div style="font-size:12px;color:var(--muted)">${r.teacher_id_no||''}</div>
                            </div>
                        </div>
                    </td>
                    <td>${r.designation_bn||'—'}</td>
                    <td><span class="badge badge-success"><i class="fas fa-check"></i> ${toBn(r.present_days||0)} দিন</span></td>
                    <td><span class="badge badge-danger">${toBn(r.absent_days||0)} দিন</span></td>
                    <td><span class="badge badge-secondary">${toBn((parseInt(r.half_days)||0)+(parseInt(r.leave_days)||0))} দিন</span></td>
                    <td style="font-weight:600;">${fmtHours(r.total_hours)}</td>
                    <td>
                        <div class="pct-bar">
                            <div class="bar-bg"><div class="bar-fill" style="width:${pct}%;background:${barColor}"></div></div>
                            <span class="badge ${pctBadge}" style="min-width:52px;justify-content:center;">${toBn(pct)}%</span>
                        </div>
                    </td>
                </tr>`;
            }).join('');
        })
        .catch(() => {
            body.innerHTML = '<tr><td colspan="8"><div class="empty"><i class="fas fa-exclamation-triangle"></i> ডেটা লোড করতে সমস্যা হয়েছে।</div></td></tr>';
        });
}

// ===== HELPERS =====
function toBn(n) {
    return String(n||0).replace(/[0-9]/g, d => '০১২৩৪৫৬৭৮৯'[d]);
}
function fmtHours(t) {
    if (!t || t === '00:00:00') return '—';
    const m = t.match(/(\d+):(\d+)/);
    if (!m) return t;
    return toBn(parseInt(m[1])) + 'ঘ ' + toBn(parseInt(m[2])) + 'মি';
}

// ===== CSV EXPORT =====
function exportCSV() {
    const activeTab = document.querySelector('.tab-pane.active');
    const table = activeTab.querySelector('table');
    if (!table) return;
    let csv = [];
    table.querySelectorAll('tr').forEach(row => {
        const cols = [...row.querySelectorAll('th,td')].map(c => '"' + c.innerText.trim().replace(/"/g,'""') + '"');
        csv.push(cols.join(','));
    });
    const blob = new Blob(['\uFEFF' + csv.join('\n')], {type: 'text/csv;charset=utf-8'});
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a'); a.href = url;
    a.download = 'attendance_' + new Date().toISOString().slice(0,10) + '.csv';
    a.click(); URL.revokeObjectURL(url);
}

// ===== AUTO LOAD MONTHLY =====
loadMonthly(document.getElementById('monthPicker').value);
</script>
</body>
</html>
