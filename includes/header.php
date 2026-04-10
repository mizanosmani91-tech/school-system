<!DOCTYPE html>
<html lang="bn" dir="ltr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle ?? 'ড্যাশবোর্ড') ?> | <?= e(getSetting('institute_name', APP_NAME)) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@300;400;500;600;700&family=Noto+Serif+Bengali:wght@400;600;700&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
:root {
    --primary: #1a5276;
    --primary-light: #2471a3;
    --primary-dark: #0e2f44;
    --accent: #e67e22;
    --accent-light: #f0a500;
    --success: #27ae60;
    --danger: #c0392b;
    --warning: #f39c12;
    --info: #2980b9;
    --bg: #f0f4f8;
    --card: #ffffff;
    --sidebar-bg: #0d2137;
    --sidebar-text: #a8c0d4;
    --sidebar-active: #1a5276;
    --text: #1a202c;
    --text-muted: #718096;
    --border: #e2e8f0;
    --shadow: 0 1px 3px rgba(0,0,0,.1), 0 1px 2px rgba(0,0,0,.06);
    --shadow-md: 0 4px 6px rgba(0,0,0,.07), 0 2px 4px rgba(0,0,0,.06);
    --shadow-lg: 0 10px 15px rgba(0,0,0,.1);
    --radius: 10px;
    --font: 'Hind Siliguri', sans-serif;
}
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: var(--font); background: var(--bg); color: var(--text); font-size: 15px; }

/* ========== SIDEBAR ========== */
.sidebar {
    width: 260px; height: 100vh; background: var(--sidebar-bg);
    position: fixed; left: 0; top: 0; z-index: 100;
    display: flex; flex-direction: column;
    transition: width .3s ease;
    overflow-y: auto;
    overflow-x: hidden;
}
.sidebar-logo {
    padding: 20px 16px; border-bottom: 1px solid rgba(255,255,255,.08);
    display: flex; align-items: center; gap: 12px;
}
.sidebar-logo-icon {
    width: 44px; height: 44px; background: var(--accent);
    border-radius: 10px; display: flex; align-items: center; justify-content: center;
    font-size: 22px; color: #fff; flex-shrink: 0; overflow: hidden;
}
.sidebar-logo-icon img {
    width: 100%; height: 100%; object-fit: contain; padding: 4px;
}
.sidebar-logo-text h2 { color: #fff; font-size: 13px; font-weight: 700; line-height: 1.3; }
.sidebar-logo-text span { color: var(--sidebar-text); font-size: 11px; }

.sidebar-nav { flex: 1; padding: 12px 0; }
.nav-section { padding: 8px 16px 4px; font-size: 10px; font-weight: 600;
    color: rgba(255,255,255,.3); text-transform: uppercase; letter-spacing: 1px; }
.nav-item { display: flex; align-items: center; gap: 10px;
    padding: 10px 18px; color: var(--sidebar-text); text-decoration: none;
    font-size: 14px; transition: all .2s; cursor: pointer; }
.nav-item:hover, .nav-item.active {
    background: var(--sidebar-active); color: #fff;
    border-right: 3px solid var(--accent);
}
.nav-item i { width: 20px; text-align: center; font-size: 15px; }
.nav-badge { margin-left: auto; background: var(--accent);
    color: #fff; border-radius: 20px; padding: 1px 7px; font-size: 10px; font-weight: 700; }

/* ===== COLLAPSIBLE NAV GROUPS ===== */
.nav-group { border-bottom: 1px solid rgba(255,255,255,.04); }
.nav-group-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 11px 18px; color: var(--sidebar-text);
    font-size: 13px; font-weight: 600; cursor: pointer;
    transition: all .2s; user-select: none;
}
.nav-group-header:hover { color: #fff; background: rgba(255,255,255,.04); }
.nav-group-header span { display: flex; align-items: center; gap: 10px; }
.nav-group-header span i { width: 20px; text-align: center; font-size: 14px; color: var(--accent-light); }
.nav-arrow { font-size: 11px; opacity: .5; transition: transform .25s; }
.nav-arrow.open { transform: rotate(180deg); }
.nav-group-items { display: none; background: rgba(0,0,0,.15); }
.nav-group-items.open { display: block; }
.nav-item.nav-sub {
    padding: 9px 18px 9px 44px;
    font-size: 13px;
    border-left: 2px solid transparent;
}
.nav-item.nav-sub:hover, .nav-item.nav-sub.active {
    border-left-color: var(--accent);
    border-right: none;
    background: rgba(255,255,255,.06);
    color: #fff;
}
.nav-item.nav-sub i { font-size: 13px; opacity: .7; }

/* ========== MAIN CONTENT ========== */
.main-wrapper { margin-left: 260px; min-height: 100vh; display: flex; flex-direction: column; }
.topbar {
    background: #fff; padding: 0 24px; height: 64px;
    display: flex; align-items: center; justify-content: space-between;
    border-bottom: 1px solid var(--border);
    position: sticky; top: 0; z-index: 50;
    box-shadow: var(--shadow);
}
.topbar-left { display: flex; align-items: center; gap: 16px; }
.page-title { font-size: 18px; font-weight: 700; color: var(--primary-dark); }
.topbar-right { display: flex; align-items: center; gap: 16px; }
.topbar-btn {
    width: 38px; height: 38px; border-radius: 8px; border: 1px solid var(--border);
    background: #fff; cursor: pointer; display: flex; align-items: center; justify-content: center;
    color: var(--text-muted); transition: all .2s; position: relative;
}
.topbar-btn:hover { background: var(--bg); color: var(--primary); }
.notif-dot { position: absolute; top: 6px; right: 6px; width: 8px; height: 8px;
    background: var(--danger); border-radius: 50%; border: 1px solid #fff; }
.user-avatar {
    width: 38px; height: 38px; border-radius: 9px; background: var(--primary);
    color: #fff; display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: 15px; cursor: pointer;
}
.user-dropdown { position: relative; }
.user-menu {
    position: absolute; right: 0; top: 48px; width: 200px;
    background: #fff; border-radius: var(--radius); box-shadow: var(--shadow-lg);
    border: 1px solid var(--border); display: none; z-index: 200;
}
.user-menu.show { display: block; }
.user-menu a { display: flex; align-items: center; gap: 10px; padding: 10px 16px;
    color: var(--text); text-decoration: none; font-size: 14px; transition: background .2s; }
.user-menu a:hover { background: var(--bg); }
.user-menu hr { border: none; border-top: 1px solid var(--border); margin: 4px 0; }

.content { padding: 24px; flex: 1; }

/* ========== CARDS ========== */
.card { background: var(--card); border-radius: var(--radius); box-shadow: var(--shadow); overflow: hidden; }
.card-header { padding: 16px 20px; border-bottom: 1px solid var(--border);
    display: flex; align-items: center; justify-content: space-between; }
.card-title { font-size: 16px; font-weight: 700; color: var(--primary-dark); }
.card-body { padding: 20px; }
.card-footer { padding: 12px 20px; background: var(--bg); border-top: 1px solid var(--border); }

/* ========== STAT CARDS ========== */
.stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px; }
.stat-card {
    background: var(--card); border-radius: var(--radius); padding: 20px;
    box-shadow: var(--shadow); display: flex; align-items: center; gap: 16px;
    border-left: 4px solid transparent; transition: transform .2s, box-shadow .2s;
}
.stat-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); }
.stat-card.blue { border-color: var(--primary-light); }
.stat-card.green { border-color: var(--success); }
.stat-card.orange { border-color: var(--accent); }
.stat-card.red { border-color: var(--danger); }
.stat-card.purple { border-color: #8e44ad; }
.stat-icon { width: 52px; height: 52px; border-radius: 12px; display: flex;
    align-items: center; justify-content: center; font-size: 22px; flex-shrink: 0; }
.stat-card.blue .stat-icon { background: #ebf5fb; color: var(--primary-light); }
.stat-card.green .stat-icon { background: #eafaf1; color: var(--success); }
.stat-card.orange .stat-icon { background: #fef9e7; color: var(--accent); }
.stat-card.red .stat-icon { background: #fdedec; color: var(--danger); }
.stat-card.purple .stat-icon { background: #f4ecf7; color: #8e44ad; }
.stat-value { font-size: 26px; font-weight: 700; color: var(--text); }
.stat-label { font-size: 13px; color: var(--text-muted); margin-top: 2px; }

/* ========== TABLE ========== */
.table-wrap { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; font-size: 14px; }
thead th { background: var(--primary); color: #fff; padding: 12px 14px;
    text-align: left; font-weight: 600; white-space: nowrap; }
tbody tr { border-bottom: 1px solid var(--border); transition: background .15s; }
tbody tr:hover { background: #f7fafc; }
tbody td { padding: 11px 14px; }
.badge {
    display: inline-flex; align-items: center; padding: 3px 10px;
    border-radius: 20px; font-size: 12px; font-weight: 600; white-space: nowrap;
}
.badge-success { background: #d4edda; color: #155724; }
.badge-danger { background: #f8d7da; color: #721c24; }
.badge-warning { background: #fff3cd; color: #856404; }
.badge-info { background: #d1ecf1; color: #0c5460; }
.badge-primary { background: #cce5ff; color: #004085; }
.badge-secondary { background: #e2e3e5; color: #383d41; }

/* ========== FORMS ========== */
.form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; }
.form-group { display: flex; flex-direction: column; gap: 6px; }
.form-group label { font-size: 13px; font-weight: 600; color: var(--text); }
.form-group label span { color: var(--danger); margin-left: 2px; }
.form-control {
    padding: 9px 12px; border: 1.5px solid var(--border); border-radius: 8px;
    font-family: var(--font); font-size: 14px; color: var(--text);
    background: #fff; transition: border-color .2s, box-shadow .2s; outline: none;
}
.form-control:focus { border-color: var(--primary-light); box-shadow: 0 0 0 3px rgba(36,113,163,.15); }
select.form-control { cursor: pointer; }
textarea.form-control { resize: vertical; min-height: 80px; }
.input-group { display: flex; }
.input-group .form-control { border-radius: 8px 0 0 8px; flex: 1; }
.input-group-append .btn { border-radius: 0 8px 8px 0; }

/* ========== BUTTONS ========== */
.btn {
    display: inline-flex; align-items: center; gap: 7px;
    padding: 9px 18px; border-radius: 8px; border: none; cursor: pointer;
    font-family: var(--font); font-size: 14px; font-weight: 600;
    text-decoration: none; transition: all .2s; white-space: nowrap;
}
.btn-primary { background: var(--primary); color: #fff; }
.btn-primary:hover { background: var(--primary-dark); }
.btn-success { background: var(--success); color: #fff; }
.btn-success:hover { background: #1e8449; }
.btn-danger { background: var(--danger); color: #fff; }
.btn-danger:hover { background: #a93226; }
.btn-warning { background: var(--warning); color: #fff; }
.btn-warning:hover { background: #d68910; }
.btn-info { background: var(--info); color: #fff; }
.btn-accent { background: var(--accent); color: #fff; }
.btn-outline { background: transparent; border: 1.5px solid var(--border); color: var(--text); }
.btn-outline:hover { background: var(--bg); }
.btn-sm { padding: 5px 12px; font-size: 12px; }
.btn-xs { padding: 3px 8px; font-size: 11px; border-radius: 6px; }

/* ========== ALERTS ========== */
.alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 16px;
    display: flex; align-items: center; gap: 10px; font-size: 14px; }
.alert-success { background: #d4edda; color: #155724; border-left: 4px solid var(--success); }
.alert-danger { background: #f8d7da; color: #721c24; border-left: 4px solid var(--danger); }
.alert-warning { background: #fff3cd; color: #856404; border-left: 4px solid var(--warning); }
.alert-info { background: #d1ecf1; color: #0c5460; border-left: 4px solid var(--info); }

/* ========== PAGINATION ========== */
.pagination { display: flex; gap: 4px; list-style: none; padding: 8px 0; }
.page-item.active .page-link { background: var(--primary); color: #fff; border-color: var(--primary); }
.page-link { padding: 6px 12px; border: 1px solid var(--border); border-radius: 6px;
    color: var(--primary); text-decoration: none; font-size: 13px; transition: all .2s; }
.page-link:hover { background: var(--primary); color: #fff; }

/* ========== AI CHAT ========== */
.ai-chat-box { display: flex; flex-direction: column; height: 450px; }
.ai-messages { flex: 1; overflow-y: auto; padding: 16px; display: flex; flex-direction: column; gap: 12px; }
.ai-msg { max-width: 80%; padding: 10px 14px; border-radius: 12px; font-size: 14px; line-height: 1.6; }
.ai-msg.user { background: var(--primary); color: #fff; align-self: flex-end; border-radius: 12px 12px 0 12px; }
.ai-msg.bot { background: var(--bg); color: var(--text); align-self: flex-start; border-radius: 12px 12px 12px 0; border: 1px solid var(--border); }
.ai-input-row { display: flex; gap: 8px; padding: 12px; border-top: 1px solid var(--border); background: #fff; }
.ai-input-row .form-control { flex: 1; }

/* ========== MODAL ========== */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.5);
    z-index: 1000; display: flex; align-items: center; justify-content: center;
    opacity: 0; pointer-events: none; transition: opacity .2s; }
.modal-overlay.show { opacity: 1; pointer-events: all; }
.modal-box { background: #fff; border-radius: var(--radius); width: 90%; max-width: 600px;
    max-height: 90vh; overflow-y: auto; box-shadow: var(--shadow-lg);
    transform: scale(.95); transition: transform .2s; }
.modal-overlay.show .modal-box { transform: scale(1); }
.modal-header { padding: 16px 20px; border-bottom: 1px solid var(--border);
    display: flex; align-items: center; justify-content: space-between; }
.modal-body { padding: 20px; }
.modal-footer { padding: 12px 20px; border-top: 1px solid var(--border);
    display: flex; justify-content: flex-end; gap: 8px; }

/* ========== RESPONSIVE ========== */
@media (max-width: 768px) {
    .sidebar { width: 0; overflow: hidden; }
    .sidebar.open { width: 260px; }
    .main-wrapper { margin-left: 0; }
    .stat-grid { grid-template-columns: 1fr 1fr; }
    .content { padding: 16px; }
}

/* ========== MISC ========== */
.avatar { width: 36px; height: 36px; border-radius: 8px; object-fit: cover; background: var(--primary);
    display: inline-flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; }
.section-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
.section-title { font-size: 20px; font-weight: 700; color: var(--primary-dark); }
.divider { border: none; border-top: 1px solid var(--border); margin: 16px 0; }
.text-muted { color: var(--text-muted); }
.text-success { color: var(--success); }
.text-danger { color: var(--danger); }
.fw-bold { font-weight: 700; }
.d-flex { display: flex; }
.align-center { align-items: center; }
.gap-8 { gap: 8px; }
.gap-16 { gap: 16px; }
.mb-16 { margin-bottom: 16px; }
.mb-24 { margin-bottom: 24px; }
.mt-16 { margin-top: 16px; }
.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
.grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; }
@media (max-width: 900px) { .grid-2, .grid-3 { grid-template-columns: 1fr; } }
.loading { display: flex; align-items: center; justify-content: center; padding: 40px; }
.spinner { width: 32px; height: 32px; border: 3px solid var(--border);
    border-top-color: var(--primary); border-radius: 50%; animation: spin .7s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

/* Print */
@media print {
    .sidebar, .topbar, .no-print { display: none !important; }
    .main-wrapper { margin: 0; }
    .card { box-shadow: none; border: 1px solid #ccc; }
}
</style>
</head>
<body>
<?php
startSession();
$currentUser = getCurrentUser();
$instituteName = getSetting('institute_name', 'স্কুল ম্যানেজমেন্ট সিস্টেম');
$instituteLogo = getSetting('logo', '');
$roleSlug = $_SESSION['role_slug'] ?? '';

if (isset($parentLayout) && $parentLayout) {
    return;
}
?>
<!-- SIDEBAR -->
<nav class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <div class="sidebar-logo-icon">
            <?php if ($instituteLogo): ?>
                <img src="<?= str_starts_with($instituteLogo,'http') ? e($instituteLogo) : UPLOAD_URL.e($instituteLogo) ?>"
                     alt="<?= e($instituteName) ?>">
            <?php else: ?>
                <i class="fas fa-mosque"></i>
            <?php endif; ?>
        </div>
        <div class="sidebar-logo-text">
            <h2><?= e($instituteName) ?></h2>
            <span>ম্যানেজমেন্ট সিস্টেম</span>
        </div>
    </div>
    <nav class="sidebar-nav">

        <?php if ($roleSlug === 'teacher'): ?>
        <a href="<?= BASE_URL ?>/modules/teacher/dashboard.php" class="nav-item <?= basename($_SERVER['PHP_SELF'])=='dashboard.php'?'active':'' ?>">
            <i class="fas fa-chart-line"></i> ড্যাশবোর্ড
        </a>
        <?php else: ?>
        <a href="<?= BASE_URL ?>/index.php" class="nav-item <?= (basename($_SERVER['PHP_SELF']) == 'index.php' && strpos($_SERVER['PHP_SELF'], 'modules') === false ? 'active' : '') ?>">
            <i class="fas fa-chart-line"></i> ড্যাশবোর্ড
        </a>
        <?php endif; ?>

        <?php if (in_array($roleSlug, ['super_admin','principal','teacher','accountant'])): ?>

        <div class="nav-group">
            <div class="nav-group-header" onclick="toggleGroup('students')">
                <span><i class="fas fa-user-graduate"></i> ছাত্র</span>
                <i class="fas fa-chevron-down nav-arrow" id="arrow-students"></i>
            </div>
            <div class="nav-group-items" id="group-students">
                <a href="<?= BASE_URL ?>/modules/student/list.php" class="nav-item nav-sub"><i class="fas fa-list"></i> ছাত্র তালিকা</a>
                <a href="<?= BASE_URL ?>/modules/student/admission.php" class="nav-item nav-sub"><i class="fas fa-user-plus"></i> নতুন ভর্তি</a>
                <?php if (in_array($roleSlug, ['super_admin','principal'])): ?>
                <a href="<?= BASE_URL ?>/modules/student/classes.php" class="nav-item nav-sub"><i class="fas fa-school"></i> শ্রেণী ও বিভাগ</a>
                <?php endif; ?>
                <a href="<?= BASE_URL ?>/modules/student/bulk_import.php" class="nav-item nav-sub"><i class="fas fa-file-excel"></i> বাল্ক ভর্তি</a>
                <a href="<?= BASE_URL ?>/modules/attendance/index.php" class="nav-item nav-sub"><i class="fas fa-clipboard-check"></i> ছাত্র উপস্থিতি</a>
            </div>
        </div>

        <?php if (in_array($roleSlug, ['super_admin','principal'])): ?>
        <div class="nav-group">
            <div class="nav-group-header" onclick="toggleGroup('teachers')">
                <span><i class="fas fa-chalkboard-teacher"></i> শিক্ষক ও স্টাফ</span>
                <i class="fas fa-chevron-down nav-arrow" id="arrow-teachers"></i>
            </div>
            <div class="nav-group-items" id="group-teachers">
                <a href="<?= BASE_URL ?>/modules/teacher/list.php" class="nav-item nav-sub"><i class="fas fa-users"></i> শিক্ষক তালিকা</a>
                <a href="<?= BASE_URL ?>/modules/attendance/live_monitor.php" class="nav-item nav-sub"><i class="fas fa-circle" style="color:#ff4757;font-size:8px;animation:pulse2 1.5s infinite;"></i> লাইভ ক্লাস মনিটর</a>
                <a href="<?= BASE_URL ?>/attendance_report.php" class="nav-item nav-sub"><i class="fas fa-clipboard-list"></i> উপস্থিতি রিপোর্ট</a>
            </div>
        </div>
        <?php endif; ?>

        <?php if (in_array($roleSlug, ['super_admin','principal'])): ?>
        <a href="<?= BASE_URL ?>/modules/idcard/id_card.php" class="nav-item"><i class="fas fa-id-card"></i> আইডি কার্ড জেনারেট</a>
        <?php endif; ?>

        <div class="nav-group">
            <div class="nav-group-header" onclick="toggleGroup('academic')">
                <span><i class="fas fa-graduation-cap"></i> একাডেমিক</span>
                <i class="fas fa-chevron-down nav-arrow" id="arrow-academic"></i>
            </div>
            <div class="nav-group-items" id="group-academic">
                <a href="<?= BASE_URL ?>/modules/exam/index.php" class="nav-item nav-sub"><i class="fas fa-file-alt"></i> পরীক্ষা ও ফলাফল</a>
                <a href="<?= BASE_URL ?>/modules/exam/result_entry.php" class="nav-item nav-sub"><i class="fas fa-pen"></i> মার্ক এন্ট্রি</a>
                <a href="<?= BASE_URL ?>/modules/exam/model_test.php" class="nav-item nav-sub"><i class="fas fa-question-circle"></i> মডেল টেস্ট / MCQ</a>
                <?php if (in_array($roleSlug, ['super_admin','principal'])): ?>
                <a href="<?= BASE_URL ?>/modules/exam/subjects.php" class="nav-item nav-sub"><i class="fas fa-book"></i> বিষয়সমূহ</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="nav-group">
            <div class="nav-group-header" onclick="toggleGroup('classmanage')">
                <span><i class="fas fa-book-open"></i> ক্লাস ম্যানেজমেন্ট</span>
                <i class="fas fa-chevron-down nav-arrow" id="arrow-classmanage"></i>
            </div>
            <div class="nav-group-items" id="group-classmanage">
                <a href="<?= BASE_URL ?>/modules/teacher/diary.php" class="nav-item nav-sub"><i class="fas fa-book-open"></i> ক্লাস ডাইরি</a>
                <a href="<?= BASE_URL ?>/modules/syllabus/index.php" class="nav-item nav-sub"><i class="fas fa-list-alt"></i> সিলেবাস</a>
                <a href="<?= BASE_URL ?>/modules/timetable/index.php" class="nav-item nav-sub"><i class="fas fa-calendar-alt"></i> ক্লাস রুটিন</a>
            </div>
        </div>

        <?php if (in_array($roleSlug, ['super_admin','principal','accountant'])): ?>
        <div class="nav-group">
            <div class="nav-group-header" onclick="toggleGroup('finance')">
                <span><i class="fas fa-money-bill-wave"></i> আর্থিক</span>
                <i class="fas fa-chevron-down nav-arrow" id="arrow-finance"></i>
            </div>
            <div class="nav-group-items" id="group-finance">
                <a href="<?= BASE_URL ?>/modules/fees/collection.php" class="nav-item nav-sub"><i class="fas fa-hand-holding-usd"></i> ফি সংগ্রহ</a>
                <a href="<?= BASE_URL ?>/modules/fees/due.php" class="nav-item nav-sub"><i class="fas fa-exclamation-circle"></i> বকেয়া ফি</a>
                <a href="<?= BASE_URL ?>/modules/fees/fee_types.php" class="nav-item nav-sub"><i class="fas fa-tags"></i> ফী ধরন ম্যানেজ</a>
                <a href="<?= BASE_URL ?>/modules/fees/report.php" class="nav-item nav-sub"><i class="fas fa-chart-bar"></i> আর্থিক রিপোর্ট</a>
            </div>
        </div>
        <?php endif; ?>

        <div class="nav-group">
            <div class="nav-group-header" onclick="toggleGroup('comms')">
                <span><i class="fas fa-bullhorn"></i> বিজ্ঞপ্তি ও যোগাযোগ</span>
                <i class="fas fa-chevron-down nav-arrow" id="arrow-comms"></i>
            </div>
            <div class="nav-group-items" id="group-comms">
                <a href="<?= BASE_URL ?>/modules/notice/index.php" class="nav-item nav-sub"><i class="fas fa-bullhorn"></i> নোটিশ বোর্ড</a>
                <a href="<?= BASE_URL ?>/modules/parent/messages.php" class="nav-item nav-sub">
                    <i class="fas fa-comments"></i> অভিভাবক বার্তা
                    <?php try { $db=getDB(); $unread=$db->query("SELECT COUNT(*) FROM parent_messages WHERE status='unread'")->fetchColumn(); if($unread>0) echo '<span class="nav-badge">'.$unread.'</span>'; } catch(Exception $e){} ?>
                </a>
                <?php if (in_array($roleSlug, ['super_admin','principal'])): ?>
                <a href="<?= BASE_URL ?>/modules/notice/holidays.php" class="nav-item nav-sub"><i class="fas fa-calendar-times"></i> ছুটির তালিকা</a>
                <?php endif; ?>
            </div>
        </div>

        <?php endif; ?>

        <div class="nav-group">
            <div class="nav-group-header" onclick="toggleGroup('system')">
                <span><i class="fas fa-cog"></i> সিস্টেম</span>
                <i class="fas fa-chevron-down nav-arrow" id="arrow-system"></i>
            </div>
            <div class="nav-group-items" id="group-system">
                <a href="<?= BASE_URL ?>/modules/ai/assistant.php" class="nav-item nav-sub"><i class="fas fa-robot"></i> AI সহকারী</a>
                <?php if (in_array($roleSlug, ['super_admin','principal'])): ?>
                <a href="<?= BASE_URL ?>/settings.php" class="nav-item nav-sub"><i class="fas fa-sliders-h"></i> সেটিংস</a>
                <?php endif; ?>
            </div>
        </div>

    </nav>

    <div style="padding:16px;border-top:1px solid rgba(255,255,255,.08);">
        <div style="background:rgba(255,255,255,.07);border-radius:8px;padding:10px 12px;display:flex;align-items:center;gap:10px;">
            <div style="width:32px;height:32px;background:var(--accent);border-radius:7px;display:flex;align-items:center;justify-content:center;font-weight:700;color:#fff;font-size:13px;">
                <?= strtoupper(mb_substr($currentUser['name'] ?? 'A', 0, 1)) ?>
            </div>
            <div style="flex:1;min-width:0;">
                <div style="color:#fff;font-size:13px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= e($currentUser['name_bn'] ?? $currentUser['name'] ?? 'User') ?></div>
                <div style="color:var(--sidebar-text);font-size:11px;"><?= e($currentUser['role_name'] ?? '') ?></div>
            </div>
        </div>
    </div>
</nav>

<script>
function toggleGroup(id) {
    const items = document.getElementById('group-' + id);
    const arrow = document.getElementById('arrow-' + id);
    if (!items) return;
    const isOpen = items.classList.contains('open');
    items.classList.toggle('open', !isOpen);
    if (arrow) arrow.classList.toggle('open', !isOpen);
    try { localStorage.setItem('nav_' + id, !isOpen ? '1' : '0'); } catch(e){}
}
document.addEventListener('DOMContentLoaded', function() {
    const groups = ['students','teachers','academic','classmanage','finance','comms','system'];
    const currentPath = window.location.pathname;
    groups.forEach(id => {
        const items = document.getElementById('group-' + id);
        const arrow = document.getElementById('arrow-' + id);
        if (!items) return;
        let hasActive = false;
        items.querySelectorAll('a').forEach(a => {
            try {
                const aPath = new URL(a.href).pathname;
                if (currentPath === aPath || currentPath.startsWith(aPath.replace('/index.php','')+'/')) {
                    a.classList.add('active'); hasActive = true;
                }
            } catch(e){}
        });
        let saved = '0';
        try { saved = localStorage.getItem('nav_' + id) || '0'; } catch(e){}
        if (hasActive || saved === '1') {
            items.classList.add('open');
            if (arrow) arrow.classList.add('open');
        }
    });
});
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
}
function toggleUserMenu() {
    document.getElementById('userMenu').classList.toggle('show');
}
document.addEventListener('click', function(e) {
    if (!e.target.closest('.user-dropdown')) {
        const m = document.getElementById('userMenu');
        if (m) m.classList.remove('show');
    }
});
</script>

<!-- MAIN WRAPPER -->
<div class="main-wrapper">
    <header class="topbar">
        <div class="topbar-left">
            <button class="topbar-btn" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
            <h1 class="page-title"><?= e($pageTitle ?? 'ড্যাশবোর্ড') ?></h1>
        </div>
        <div class="topbar-right">
            <div style="font-size:13px;color:var(--text-muted);"><?= banglaDate() ?></div>
            <a href="<?= BASE_URL ?>/modules/notice/index.php" class="topbar-btn">
                <i class="fas fa-bell"></i><span class="notif-dot"></span>
            </a>
            <div class="user-dropdown">
                <div class="user-avatar" onclick="toggleUserMenu()"><?= strtoupper(mb_substr($currentUser['name'] ?? 'A', 0, 1)) ?></div>
                <div class="user-menu" id="userMenu">
                    <div style="padding:12px 16px;border-bottom:1px solid var(--border);">
                        <div style="font-weight:700;font-size:14px;"><?= e($currentUser['name_bn'] ?? $currentUser['name'] ?? '') ?></div>
                        <div style="font-size:12px;color:var(--text-muted);"><?= e($currentUser['role_name'] ?? '') ?></div>
                    </div>
                    <a href="<?= BASE_URL ?>/profile.php"><i class="fas fa-user-circle"></i> প্রোফাইল</a>
                    <a href="<?= BASE_URL ?>/settings.php"><i class="fas fa-cog"></i> সেটিংস</a>
                    <hr>
                    <a href="<?= BASE_URL ?>/logout.php" style="color:var(--danger);"><i class="fas fa-sign-out-alt"></i> লগআউট</a>
                </div>
            </div>
        </div>
    </header>
    <div class="content">
<?php $flash = getFlash(); if ($flash): ?>
<div class="alert alert-<?= $flash['type'] ?>"><i class="fas fa-info-circle"></i> <?= e($flash['msg']) ?></div>
<?php endif; ?>
