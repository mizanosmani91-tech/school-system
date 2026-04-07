<?php
require_once 'includes/functions.php';
requireLogin();
$pageTitle = 'ড্যাশবোর্ড';

$db = getDB();
$year = date('Y');

// Stats
$totalStudents = $db->query("SELECT COUNT(*) FROM students WHERE status='active' AND academic_year='$year'")->fetchColumn();
$totalTeachers = $db->query("SELECT COUNT(*) FROM teachers WHERE is_active=1")->fetchColumn();
$today = date('Y-m-d');

// আজকের উপস্থিতি
$presentToday = $db->query("SELECT COUNT(*) FROM attendance WHERE date='$today' AND status='present'")->fetchColumn();
$totalToday = $db->query("SELECT COUNT(*) FROM attendance WHERE date='$today'")->fetchColumn();
$attendanceRate = $totalToday > 0 ? round(($presentToday / $totalToday) * 100) : 0;

// এই মাসের ফি
$thisMonth = date('Y-m');
$monthlyFee = $db->query("SELECT COALESCE(SUM(paid_amount),0) FROM fee_collections WHERE month_year='$thisMonth'")->fetchColumn();

// বকেয়া (rough estimate)
$dueCount = $db->query("SELECT COUNT(DISTINCT student_id) FROM students s WHERE status='active' AND NOT EXISTS (SELECT 1 FROM fee_collections f WHERE f.student_id = s.id AND f.month_year='$thisMonth')")->fetchColumn();

// সাম্প্রতিক ভর্তি
$recentStudents = $db->query("SELECT s.*, c.class_name_bn FROM students s LEFT JOIN classes c ON s.class_id = c.id ORDER BY s.created_at DESC LIMIT 6")->fetchAll();

// নোটিশ
$notices = $db->query("SELECT * FROM notices WHERE is_published=1 ORDER BY created_at DESC LIMIT 5")->fetchAll();

// শিক্ষক আজকের চেক ইন স্ট্যাটাস
$teacherCheckedIn = 0;
$teacherNotCheckedIn = 0;
$teacherCheckedOut = 0;
try {
    $teacherCheckedIn = $db->query("SELECT COUNT(*) FROM teacher_attendance WHERE date='$today' AND check_in IS NOT NULL")->fetchColumn();
    $teacherCheckedOut = $db->query("SELECT COUNT(*) FROM teacher_attendance WHERE date='$today' AND check_out IS NOT NULL")->fetchColumn();
    $teacherNotCheckedIn = $db->query("SELECT COUNT(*) FROM teachers WHERE is_active=1")->fetchColumn() - $teacherCheckedIn;
} catch(Exception $e) {}

// আজকের শিক্ষক উপস্থিতি তালিকা
$teacherAttendanceToday = [];
try {
    $teacherAttendanceToday = $db->query("
        SELECT ta.*, t.name_bn, t.name, t.designation_bn, t.teacher_id_no
        FROM teacher_attendance ta
        JOIN teachers t ON ta.teacher_id = t.id
        WHERE ta.date = '$today'
        ORDER BY ta.check_in ASC
    ")->fetchAll();
} catch(Exception $e) {}

// উপস্থিতি ডেটা (শেষ ৭ দিন)
$attendanceChart = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $p = $db->prepare("SELECT COUNT(*) FROM attendance WHERE date=? AND status='present'");
    $p->execute([$d]);
    $a = $db->prepare("SELECT COUNT(*) FROM attendance WHERE date=?");
    $a->execute([$d]);
    $attendanceChart[] = ['date' => date('d/m', strtotime($d)), 'present' => $p->fetchColumn(), 'total' => $a->fetchColumn()];
}

require_once 'includes/header.php';
?>

<?php
// ===== CARD DATA (PHP থেকে JS এ পাঠানো হবে) =====
$allCards = [
    ['id'=>'students',   'color'=>'blue',   'icon'=>'fa-user-graduate',      'value'=> toBanglaNumber($totalStudents),        'label'=>'মোট ছাত্র (সক্রিয়)'],
    ['id'=>'teachers',   'color'=>'green',  'icon'=>'fa-chalkboard-teacher', 'value'=> toBanglaNumber($totalTeachers),        'label'=>'মোট শিক্ষক'],
    ['id'=>'attendance', 'color'=>'orange', 'icon'=>'fa-clipboard-check',    'value'=> toBanglaNumber($attendanceRate).'%',   'label'=>'আজকের উপস্থিতি'],
    ['id'=>'fee',        'color'=>'purple', 'icon'=>'fa-money-bill-wave',    'value'=> '৳'.number_format($monthlyFee),        'label'=>'এই মাসের ফি আদায়'],
    ['id'=>'due',        'color'=>'red',    'icon'=>'fa-exclamation-circle', 'value'=> toBanglaNumber($dueCount),             'label'=>'ফি বকেয়া ছাত্র'],
    ['id'=>'checkin',    'color'=>'green',  'icon'=>'fa-fingerprint',        'value'=> toBanglaNumber($teacherCheckedIn),     'label'=>'শিক্ষক আজ চেক ইন'],
    ['id'=>'notcheckin', 'color'=>'red',    'icon'=>'fa-user-times',         'value'=> toBanglaNumber($teacherNotCheckedIn),  'label'=>'এখনো আসেননি'],
    ['id'=>'checkout',   'color'=>'blue',   'icon'=>'fa-sign-out-alt',       'value'=> toBanglaNumber($teacherCheckedOut),    'label'=>'চেক আউট সম্পন্ন'],
];
?>

<style>
/* ===== CUSTOMIZE TOOLBAR ===== */
.cbar {
    display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px;
    background:#fff; border-radius:10px; padding:10px 16px;
    margin-bottom:12px; box-shadow:var(--shadow);
}
.cbar-title { font-size:13px; font-weight:600; color:var(--text-muted); display:flex; align-items:center; gap:6px; }
.cbar-actions { display:flex; gap:6px; flex-wrap:wrap; align-items:center; }
.cbar select {
    padding:5px 10px; border-radius:7px; border:1.5px solid var(--border);
    font-family:var(--font); font-size:13px; color:var(--text); background:#fff; cursor:pointer;
}
.cbar select:focus { outline:none; border-color:var(--primary-light); }

/* ===== STAT GRID ===== */
#statGrid {
    display:grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap:16px; margin-bottom:24px;
}

/* Size variants */
.stat-card.sz-small  { padding:12px 14px; }
.stat-card.sz-small .stat-icon  { width:38px; height:38px; font-size:16px; }
.stat-card.sz-small .stat-value { font-size:18px; }
.stat-card.sz-small .stat-label { font-size:11px; }

.stat-card.sz-large  { padding:28px 22px; }
.stat-card.sz-large .stat-icon  { width:64px; height:64px; font-size:28px; }
.stat-card.sz-large .stat-value { font-size:34px; }

/* Hidden */
.stat-card.card-hidden { display:none !important; }

/* Drag */
.stat-card.dragging  { opacity:.35; border:2px dashed var(--primary-light) !important; }
.stat-card.drag-over { border:2px dashed var(--accent) !important; transform:scale(1.03); }

/* Edit mode */
.stat-card.edit-mode { cursor:grab; user-select:none; }
.stat-card.edit-mode:active { cursor:grabbing; }
.card-ctrl {
    position:absolute; top:6px; right:6px;
    display:none; gap:4px;
}
.stat-card { position:relative; }
#statGrid.editing .card-ctrl { display:flex; }
.card-ctrl button {
    width:24px; height:24px; border-radius:5px; border:none; cursor:pointer;
    font-size:11px; display:flex; align-items:center; justify-content:center; line-height:1;
}
.card-ctrl .btn-hide { background:#fee; color:var(--danger); }
.card-ctrl .btn-sz   { background:#e8f4fd; color:var(--info); }

/* Customize panel modal */
.customize-panel {
    position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:2000;
    display:none; align-items:center; justify-content:center;
}
.customize-panel.open { display:flex; }
.cp-box {
    background:#fff; border-radius:14px; width:90%; max-width:520px;
    box-shadow:0 20px 40px rgba(0,0,0,.2); overflow:hidden;
}
.cp-head {
    padding:16px 20px; background:var(--primary); color:#fff;
    display:flex; align-items:center; justify-content:space-between;
}
.cp-head h3 { font-size:16px; font-weight:700; margin:0; }
.cp-head button { background:transparent; border:none; color:#fff; font-size:18px; cursor:pointer; }
.cp-body { padding:16px 20px; max-height:70vh; overflow-y:auto; }
.cp-card-row {
    display:flex; align-items:center; gap:10px;
    padding:10px 12px; border-radius:8px; margin-bottom:8px;
    border:1.5px solid var(--border); background:#fafafa;
    transition:background .2s;
}
.cp-card-row:hover { background:#f0f4f8; }
.cp-card-row .cp-icon {
    width:36px; height:36px; border-radius:8px;
    display:flex; align-items:center; justify-content:center; font-size:16px; flex-shrink:0;
}
.cp-card-row .cp-info { flex:1; }
.cp-card-row .cp-label { font-size:13px; font-weight:600; color:var(--text); }
.cp-card-row .cp-val   { font-size:12px; color:var(--text-muted); margin-top:1px; }
.cp-card-row .cp-controls { display:flex; gap:6px; align-items:center; }
.cp-toggle {
    width:40px; height:22px; border-radius:11px; border:none; cursor:pointer;
    background:var(--border); position:relative; transition:background .2s; flex-shrink:0;
}
.cp-toggle.on { background:var(--success); }
.cp-toggle::after {
    content:''; position:absolute; width:18px; height:18px; border-radius:50%;
    background:#fff; top:2px; left:2px; transition:left .2s;
    box-shadow:0 1px 3px rgba(0,0,0,.2);
}
.cp-toggle.on::after { left:20px; }
.cp-size-sel {
    padding:4px 8px; border-radius:6px; border:1.5px solid var(--border);
    font-family:var(--font); font-size:12px; cursor:pointer;
}
.cp-foot { padding:12px 20px; border-top:1px solid var(--border); display:flex; justify-content:flex-end; gap:8px; }

/* Color icon backgrounds */
.ci-blue   { background:#ebf5fb; color:var(--primary-light); }
.ci-green  { background:#eafaf1; color:var(--success); }
.ci-orange { background:#fef9e7; color:var(--accent); }
.ci-red    { background:#fdedec; color:var(--danger); }
.ci-purple { background:#f4ecf7; color:#8e44ad; }
</style>

<!-- Toolbar -->
<div class="cbar">
    <div class="cbar-title"><i class="fas fa-th-large"></i> ড্যাশবোর্ড কার্ডসমূহ</div>
    <div class="cbar-actions">
        <label class="cbar-title" style="gap:4px;">
            <i class="fas fa-columns"></i> কলাম:
        </label>
        <select id="colSelect" onchange="setColumns(this.value)">
            <option value="auto">অটো</option>
            <option value="2">২টি</option>
            <option value="3">৩টি</option>
            <option value="4">৪টি</option>
            <option value="5">৫টি</option>
        </select>
        <button class="btn btn-outline btn-sm" onclick="openCustomize()">
            <i class="fas fa-sliders-h"></i> কাস্টমাইজ
        </button>
        <button class="btn btn-sm" id="editToggleBtn" onclick="toggleEditMode()" style="background:var(--primary);color:#fff;">
            <i class="fas fa-arrows-alt"></i> সাজান
        </button>
        <button class="btn btn-outline btn-sm" onclick="resetCards()" title="রিসেট">
            <i class="fas fa-undo"></i>
        </button>
    </div>
</div>

<!-- Stat Grid -->
<div id="statGrid"></div>

<!-- Customize Panel -->
<div class="customize-panel" id="customizePanel">
    <div class="cp-box">
        <div class="cp-head">
            <h3><i class="fas fa-sliders-h"></i> কার্ড কাস্টমাইজ করুন</h3>
            <button onclick="closeCustomize()"><i class="fas fa-times"></i></button>
        </div>
        <div class="cp-body" id="cpBody"></div>
        <div class="cp-foot">
            <button class="btn btn-outline btn-sm" onclick="closeCustomize()">বাতিল</button>
            <button class="btn btn-sm" style="background:var(--primary);color:#fff;" onclick="applyCustomize()">
                <i class="fas fa-check"></i> সংরক্ষণ
            </button>
        </div>
    </div>
</div>

<script>
// ===== CARD DATA FROM PHP =====
const ALL_CARDS = <?= json_encode($allCards, JSON_UNESCAPED_UNICODE) ?>;

// ===== STATE =====
const STORAGE_KEY = 'dashboard_cards_v2';
let cardState = loadState();
let editMode = false;
let dragSrc = null;

// Default state
function defaultState() {
    return ALL_CARDS.map(c => ({ id:c.id, visible:true, size:'normal', order: ALL_CARDS.findIndex(x=>x.id===c.id) }));
}

function loadState() {
    try {
        const s = JSON.parse(localStorage.getItem(STORAGE_KEY));
        if (s && Array.isArray(s) && s.length === ALL_CARDS.length) return s;
    } catch(e) {}
    return defaultState();
}

function saveState() {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(cardState));
}

function getCard(id) { return ALL_CARDS.find(c => c.id === id); }
function getState(id) { return cardState.find(s => s.id === id); }

// ===== RENDER =====
function renderGrid() {
    const grid = document.getElementById('statGrid');
    grid.innerHTML = '';

    // sort by order
    const sorted = [...cardState].sort((a,b) => a.order - b.order);

    sorted.forEach(st => {
        const card = getCard(st.id);
        if (!card) return;

        const div = document.createElement('div');
        div.className = `stat-card ${card.color}${st.size !== 'normal' ? ' sz-'+st.size : ''}${!st.visible ? ' card-hidden' : ''}${editMode ? ' edit-mode' : ''}`;
        div.dataset.id = st.id;

        div.innerHTML = `
            <div class="stat-icon"><i class="fas ${card.icon}"></i></div>
            <div>
                <div class="stat-value">${card.value}</div>
                <div class="stat-label">${card.label}</div>
            </div>
            <div class="card-ctrl">
                <button class="btn-sz" onclick="cycleSize('${st.id}')" title="সাইজ"><i class="fas fa-text-height"></i></button>
                <button class="btn-hide" onclick="hideCard('${st.id}')" title="লুকান"><i class="fas fa-eye-slash"></i></button>
            </div>
        `;

        // Drag events
        div.setAttribute('draggable', editMode);
        div.addEventListener('dragstart', onDragStart);
        div.addEventListener('dragover',  onDragOver);
        div.addEventListener('dragleave', onDragLeave);
        div.addEventListener('drop',      onDrop);
        div.addEventListener('dragend',   onDragEnd);

        grid.appendChild(div);
    });
}

// ===== COLUMN CONTROL =====
function setColumns(val) {
    const grid = document.getElementById('statGrid');
    grid.className = '';
    if (val !== 'auto') grid.classList.add('grid-cols-' + val);
    try { localStorage.setItem('dashboard_cols', val); } catch(e) {}
}

function loadColumns() {
    try {
        const c = localStorage.getItem('dashboard_cols') || 'auto';
        document.getElementById('colSelect').value = c;
        setColumns(c);
    } catch(e) {}
}

// ===== EDIT MODE =====
function toggleEditMode() {
    editMode = !editMode;
    const grid = document.getElementById('statGrid');
    const btn  = document.getElementById('editToggleBtn');
    grid.classList.toggle('editing', editMode);
    if (editMode) {
        btn.innerHTML = '<i class="fas fa-check"></i> সম্পন্ন';
        btn.style.background = 'var(--success)';
    } else {
        btn.innerHTML = '<i class="fas fa-arrows-alt"></i> সাজান';
        btn.style.background = 'var(--primary)';
    }
    renderGrid();
}

function cycleSize(id) {
    const st = getState(id);
    const sizes = ['normal','small','large'];
    const idx = sizes.indexOf(st.size);
    st.size = sizes[(idx+1) % sizes.length];
    saveState(); renderGrid();
}

function hideCard(id) {
    getState(id).visible = false;
    saveState(); renderGrid();
}

// ===== DRAG & DROP =====
function onDragStart(e) {
    if (!editMode) return;
    dragSrc = this;
    this.classList.add('dragging');
    e.dataTransfer.effectAllowed = 'move';
}
function onDragOver(e) {
    e.preventDefault();
    if (!editMode || this === dragSrc) return;
    this.classList.add('drag-over');
}
function onDragLeave() { this.classList.remove('drag-over'); }
function onDrop(e) {
    e.preventDefault();
    if (!editMode || this === dragSrc) return;
    this.classList.remove('drag-over');
    const srcId  = dragSrc.dataset.id;
    const destId = this.dataset.id;
    const srcSt  = getState(srcId);
    const dstSt  = getState(destId);
    const tmp = srcSt.order;
    srcSt.order = dstSt.order;
    dstSt.order = tmp;
    saveState(); renderGrid();
}
function onDragEnd() {
    this.classList.remove('dragging');
    document.querySelectorAll('.stat-card').forEach(c => c.classList.remove('drag-over'));
}

// ===== CUSTOMIZE PANEL =====
function openCustomize() {
    const body = document.getElementById('cpBody');
    body.innerHTML = '';
    const sorted = [...cardState].sort((a,b) => a.order - b.order);
    sorted.forEach(st => {
        const card = getCard(st.id);
        if (!card) return;
        const row = document.createElement('div');
        row.className = 'cp-card-row';
        row.innerHTML = `
            <div class="cp-icon ci-${card.color}"><i class="fas ${card.icon}"></i></div>
            <div class="cp-info">
                <div class="cp-label">${card.label}</div>
                <div class="cp-val">${card.value}</div>
            </div>
            <div class="cp-controls">
                <select class="cp-size-sel" data-id="${st.id}">
                    <option value="small"  ${st.size==='small'  ? 'selected':''}>ছোট</option>
                    <option value="normal" ${st.size==='normal' ? 'selected':''}>স্বাভাবিক</option>
                    <option value="large"  ${st.size==='large'  ? 'selected':''}>বড়</option>
                </select>
                <button class="cp-toggle${st.visible ? ' on':''}" data-id="${st.id}" onclick="cpToggle(this, '${st.id}')"></button>
            </div>
        `;
        body.appendChild(row);
    });
    document.getElementById('customizePanel').classList.add('open');
}

function cpToggle(btn, id) {
    btn.classList.toggle('on');
}

function applyCustomize() {
    document.querySelectorAll('.cp-toggle').forEach(btn => {
        getState(btn.dataset.id).visible = btn.classList.contains('on');
    });
    document.querySelectorAll('.cp-size-sel').forEach(sel => {
        getState(sel.dataset.id).size = sel.value;
    });
    saveState(); renderGrid();
    closeCustomize();
}

function closeCustomize() {
    document.getElementById('customizePanel').classList.remove('open');
}

function resetCards() {
    if (!confirm('সব পরিবর্তন মুছে ডিফল্টে ফিরে যাবেন?')) return;
    cardState = defaultState();
    saveState();
    setColumns('auto');
    document.getElementById('colSelect').value = 'auto';
    try { localStorage.removeItem('dashboard_cols'); } catch(e){}
    renderGrid();
}

// Click outside panel to close
document.getElementById('customizePanel').addEventListener('click', function(e) {
    if (e.target === this) closeCustomize();
});

// ===== INIT =====
document.addEventListener('DOMContentLoaded', function() {
    loadColumns();
    renderGrid();
});
</script>

<div class="grid-2 mb-24">
    <!-- সাম্প্রতিক ভর্তি -->
    <div class="card">
        <div class="card-header">
            <span class="card-title"><i class="fas fa-user-plus"></i> সাম্প্রতিক ভর্তি</span>
            <a href="modules/student/list.php" class="btn btn-outline btn-sm">সব দেখুন</a>
        </div>
        <div class="card-body" style="padding:0;">
            <table>
                <thead>
                    <tr><th>নাম</th><th>শ্রেণী</th><th>ভর্তির তারিখ</th><th>অবস্থা</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($recentStudents)): ?>
                    <tr><td colspan="4" style="text-align:center;padding:20px;color:#718096;">কোনো তথ্য নেই</td></tr>
                    <?php else: foreach ($recentStudents as $s): ?>
                    <tr>
                        <td>
                            <div style="display:flex;align-items:center;gap:8px;">
                                <div class="avatar" style="font-size:12px;">
                                    <?= mb_substr($s['name_bn'] ?? $s['name'], 0, 1) ?>
                                </div>
                                <div>
                                    <div style="font-weight:600;font-size:13px;"><?= e($s['name_bn'] ?? $s['name']) ?></div>
                                    <div style="font-size:11px;color:#718096;"><?= e($s['student_id'] ?? '') ?></div>
                                </div>
                            </div>
                        </td>
                        <td><span class="badge badge-primary" style="font-size:11px;"><?= e($s['class_name_bn'] ?? '') ?></span></td>
                        <td style="font-size:13px;"><?= banglaDate($s['admission_date']) ?></td>
                        <td><span class="badge badge-success" style="font-size:11px;">সক্রিয়</span></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- নোটিশ বোর্ড -->
    <div class="card">
        <div class="card-header">
            <span class="card-title"><i class="fas fa-bullhorn"></i> নোটিশ বোর্ড</span>
            <a href="modules/notice/index.php" class="btn btn-outline btn-sm">নতুন নোটিশ</a>
        </div>
        <div class="card-body" style="padding:0;">
            <?php if (empty($notices)): ?>
            <div style="text-align:center;padding:30px;color:#718096;">কোনো নোটিশ নেই</div>
            <?php else: ?>
            <div style="display:flex;flex-direction:column;">
                <?php foreach ($notices as $n): ?>
                <div style="padding:14px 18px;border-bottom:1px solid var(--border);display:flex;gap:12px;align-items:flex-start;">
                    <div style="width:8px;height:8px;border-radius:50%;background:<?= $n['notice_type'] === 'urgent' ? 'var(--danger)' : 'var(--primary-light)' ?>;margin-top:6px;flex-shrink:0;"></div>
                    <div>
                        <div style="font-weight:600;font-size:14px;"><?= e($n['title_bn'] ?? $n['title']) ?></div>
                        <div style="font-size:12px;color:#718096;margin-top:3px;"><?= banglaDate($n['created_at']) ?></div>
                    </div>
                    <span class="badge badge-<?= $n['notice_type'] === 'urgent' ? 'danger' : 'info' ?>" style="font-size:10px;margin-left:auto;">
                        <?= e($n['notice_type']) ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- শিক্ষক উপস্থিতি টেবিল -->
<div class="card mb-24">
    <div class="card-header">
        <span class="card-title"><i class="fas fa-chalkboard-teacher"></i> আজকের শিক্ষক উপস্থিতি</span>
        <a href="modules/payroll/attendance_report.php" class="btn btn-outline btn-sm">বিস্তারিত</a>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>নাম</th><th>পদবী</th><th>চেক ইন</th><th>চেক আউট</th><th>মোট সময়</th><th>অবস্থা</th></tr>
            </thead>
            <tbody>
                <?php if(empty($teacherAttendanceToday)): ?>
                <tr><td colspan="6" style="text-align:center;padding:24px;color:var(--text-muted);">আজ এখনো কেউ চেক ইন করেননি</td></tr>
                <?php else: foreach($teacherAttendanceToday as $ta):
                    $totalH = '';
                    if($ta['check_in'] && $ta['check_out']) {
                        $d = strtotime($ta['check_out']) - strtotime($ta['check_in']);
                        $totalH = toBanglaNumber(floor($d/3600)).'ঘ '.toBanglaNumber(floor(($d%3600)/60)).'মি';
                    }
                ?>
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <div class="avatar" style="font-size:12px;"><?= mb_substr($ta['name_bn']??$ta['name'],0,1) ?></div>
                            <div>
                                <div style="font-weight:600;font-size:13px;"><?= e($ta['name_bn']??$ta['name']) ?></div>
                                <div style="font-size:11px;color:var(--text-muted);"><?= e($ta['teacher_id_no']??'')?></div>
                            </div>
                        </div>
                    </td>
                    <td style="font-size:12px;color:var(--text-muted);"><?= e($ta['designation_bn']??'')?></td>
                    <td style="color:var(--success);font-weight:600;font-size:13px;"><?= $ta['check_in'] ? date('h:i A',strtotime($ta['check_in'])) : '-' ?></td>
                    <td style="color:var(--danger);font-size:13px;"><?= $ta['check_out'] ? date('h:i A',strtotime($ta['check_out'])) : '<span style="color:var(--text-muted);">এখনো নেই</span>' ?></td>
                    <td style="font-size:13px;font-weight:600;"><?= $totalH ?: '-' ?></td>
                    <td>
                        <?php if($ta['check_out']): ?>
                        <span class="badge badge-success">সম্পন্ন</span>
                        <?php elseif($ta['check_in']): ?>
                        <span class="badge badge-warning">চেক ইন</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Quick Actions -->
<div class="card mb-24">
    <div class="card-header">
        <span class="card-title"><i class="fas fa-bolt"></i> দ্রুত অ্যাকশন</span>
    </div>
    <div class="card-body">
        <div style="display:flex;flex-wrap:wrap;gap:12px;">
            <a href="modules/student/admission.php" class="btn btn-primary"><i class="fas fa-user-plus"></i> নতুন ভর্তি</a>
            <a href="modules/attendance/index.php" class="btn btn-success"><i class="fas fa-clipboard-check"></i> উপস্থিতি নিন</a>
            <a href="modules/fees/collection.php" class="btn btn-warning"><i class="fas fa-money-bill"></i> ফি নিন</a>
            <a href="modules/exam/marks.php" class="btn btn-info"><i class="fas fa-pencil-alt"></i> নম্বর দিন</a>
            <a href="modules/notice/add.php" class="btn btn-accent"><i class="fas fa-bullhorn"></i> নোটিশ দিন</a>
            <a href="modules/ai/assistant.php" class="btn btn-outline"><i class="fas fa-robot"></i> AI সহকারী</a>
        </div>
    </div>
</div>

<!-- Attendance Chart (simple CSS) -->
<div class="card">
    <div class="card-header">
        <span class="card-title"><i class="fas fa-chart-bar"></i> শেষ ৭ দিনের উপস্থিতি</span>
    </div>
    <div class="card-body">
        <div style="display:flex;align-items:flex-end;gap:8px;height:120px;border-bottom:2px solid var(--border);padding-bottom:8px;">
            <?php foreach ($attendanceChart as $d):
                $h = $d['total'] > 0 ? ($d['present'] / $d['total']) * 100 : 0;
                $color = $h >= 80 ? 'var(--success)' : ($h >= 60 ? 'var(--warning)' : 'var(--danger)');
            ?>
            <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px;">
                <div style="font-size:10px;color:var(--text-muted);"><?= round($h) ?>%</div>
                <div style="width:100%;background:<?= $color ?>;border-radius:4px 4px 0 0;height:<?= max(4, $h) ?>px;transition:height .3s;"></div>
                <div style="font-size:10px;color:var(--text-muted);"><?= $d['date'] ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <div style="display:flex;gap:16px;margin-top:12px;font-size:12px;">
            <div style="display:flex;align-items:center;gap:4px;"><div style="width:12px;height:12px;background:var(--success);border-radius:2px;"></div> ৮০%+</div>
            <div style="display:flex;align-items:center;gap:4px;"><div style="width:12px;height:12px;background:var(--warning);border-radius:2px;"></div> ৬০-৮০%</div>
            <div style="display:flex;align-items:center;gap:4px;"><div style="width:12px;height:12px;background:var(--danger);border-radius:2px;"></div> ৬০%</div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
