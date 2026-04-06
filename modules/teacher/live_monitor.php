<?php
require_once '../../includes/functions.php';
requireLogin();
$pageTitle = 'লাইভ ক্লাস মনিটর';
$db = getDB();

// Ensure live_classes table exists
$db->exec("CREATE TABLE IF NOT EXISTS live_classes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    class_id INT NOT NULL,
    subject_id INT,
    started_at DATETIME NOT NULL,
    ended_at DATETIME,
    date DATE NOT NULL,
    status ENUM('ongoing','ended') DEFAULT 'ongoing',
    FOREIGN KEY (teacher_id) REFERENCES teachers(id),
    FOREIGN KEY (class_id) REFERENCES classes(id)
)");

$userId = $_SESSION['user_id'];
$teacher = null;
$t = $db->prepare("SELECT * FROM teachers WHERE user_id=?");
$t->execute([$userId]); $teacher = $t->fetch();

// ===== AJAX ACTIONS =====
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    $action = $_POST['action'];

    // শিক্ষক ক্লাস শুরু করবেন
    if ($action === 'start_class' && $teacher) {
        $classId   = (int)$_POST['class_id'];
        $subjectId = (int)($_POST['subject_id']??0) ?: null;

        // আগের চলমান ক্লাস শেষ করুন
        $db->prepare("UPDATE live_classes SET status='ended', ended_at=NOW() WHERE teacher_id=? AND status='ongoing'")
           ->execute([$teacher['id']]);

        $db->prepare("INSERT INTO live_classes (teacher_id,class_id,subject_id,started_at,date,status) VALUES (?,?,?,NOW(),CURDATE(),'ongoing')")
           ->execute([$teacher['id'],$classId,$subjectId]);

        echo json_encode(['success'=>true,'msg'=>'ক্লাস শুরু হয়েছে!']);
        exit;
    }

    // শিক্ষক ক্লাস শেষ করবেন
    if ($action === 'end_class' && $teacher) {
        $db->prepare("UPDATE live_classes SET status='ended', ended_at=NOW() WHERE teacher_id=? AND status='ongoing'")
           ->execute([$teacher['id']]);
        echo json_encode(['success'=>true,'msg'=>'ক্লাস শেষ হয়েছে।']);
        exit;
    }

    // Live data refresh (Principal এর জন্য)
    if ($action === 'get_live_data') {
        $now = date('H:i:s');
        $today = date('Y-m-d');
        $dayOfWeek = date('w'); // 0=Sun

        // ১. শিক্ষক নিজে start করেছেন (নিশ্চিত)
        $confirmed = $db->query("SELECT lc.*, t.name_bn as teacher_name, t.photo as teacher_photo,
            c.class_name_bn, s.subject_name_bn,
            TIMESTAMPDIFF(MINUTE, lc.started_at, NOW()) as minutes_ago
            FROM live_classes lc
            JOIN teachers t ON lc.teacher_id=t.id
            JOIN classes c ON lc.class_id=c.id
            LEFT JOIN subjects s ON lc.subject_id=s.id
            WHERE lc.status='ongoing' AND lc.date='$today'
            ORDER BY lc.started_at")->fetchAll();

        // ২. রুটিন অনুযায়ী এখন যে ক্লাস হওয়ার কথা
        $scheduled = $db->query("SELECT tt.*, t.name_bn as teacher_name,
            c.class_name_bn, s.subject_name_bn,
            tt.start_time, tt.end_time
            FROM timetable tt
            JOIN teachers t ON tt.teacher_id=t.id
            JOIN classes c ON tt.class_id=c.id
            LEFT JOIN subjects s ON tt.subject_id=s.id
            WHERE tt.day_of_week=$dayOfWeek
            AND tt.start_time <= '$now' AND tt.end_time >= '$now'
            AND tt.academic_year='".date('Y')."'
            ORDER BY tt.start_time")->fetchAll();

        // Confirmed teacher IDs
        $confirmedTeacherIds = array_column($confirmed, 'teacher_id');

        // Scheduled যারা confirmed না তাদের "অপেক্ষিত" হিসেবে দেখাব
        $expected = array_filter($scheduled, fn($r) => !in_array($r['teacher_id'], $confirmedTeacherIds));

        // আজকের ক্লাস ইতিহাস
        $history = $db->query("SELECT lc.*, t.name_bn as teacher_name,
            c.class_name_bn, s.subject_name_bn,
            TIMESTAMPDIFF(MINUTE, lc.started_at, lc.ended_at) as duration_minutes
            FROM live_classes lc
            JOIN teachers t ON lc.teacher_id=t.id
            JOIN classes c ON lc.class_id=c.id
            LEFT JOIN subjects s ON lc.subject_id=s.id
            WHERE lc.date='$today' AND lc.status='ended'
            ORDER BY lc.ended_at DESC LIMIT 10")->fetchAll();

        echo json_encode([
            'success' => true,
            'confirmed' => array_values($confirmed),
            'expected'  => array_values($expected),
            'history'   => array_values($history),
            'time'      => date('h:i:s A'),
        ]);
        exit;
    }

    echo json_encode(['success'=>false,'msg'=>'অজানা অ্যাকশন']);
    exit;
}

// Current ongoing class for this teacher
$myOngoing = null;
if ($teacher) {
    $o = $db->prepare("SELECT lc.*, c.class_name_bn, s.subject_name_bn FROM live_classes lc
        JOIN classes c ON lc.class_id=c.id LEFT JOIN subjects s ON lc.subject_id=s.id
        WHERE lc.teacher_id=? AND lc.status='ongoing' AND lc.date=CURDATE()");
    $o->execute([$teacher['id']]); $myOngoing = $o->fetch();
}

$classes  = $db->query("SELECT * FROM classes WHERE is_active=1 ORDER BY class_numeric")->fetchAll();
$subjects = $db->query("SELECT * FROM subjects WHERE is_active=1 ORDER BY subject_name_bn")->fetchAll();

require_once '../../includes/header.php';
?>

<div class="section-header">
    <h2 class="section-title">
        <span style="display:inline-flex;align-items:center;gap:8px;">
            <span style="width:10px;height:10px;background:var(--success);border-radius:50%;animation:pulse 1.5s infinite;"></span>
            লাইভ ক্লাস মনিটর
        </span>
    </h2>
    <div style="font-size:13px;color:var(--text-muted);" id="liveTime">লোড হচ্ছে...</div>
</div>

<style>
@keyframes pulse { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.5;transform:scale(1.3)} }
.class-card {
    background:#fff; border-radius:12px; padding:18px;
    box-shadow:0 2px 8px rgba(0,0,0,.08); border-left:4px solid var(--success);
    transition:transform .2s;
}
.class-card:hover { transform:translateY(-2px); }
.class-card.expected { border-left-color: var(--warning); }
.class-card.ended { border-left-color: var(--border); opacity:.7; }
.teacher-avatar {
    width:48px; height:48px; border-radius:12px; background:var(--primary);
    display:flex; align-items:center; justify-content:center;
    color:#fff; font-size:18px; font-weight:700; flex-shrink:0;
}
.live-badge {
    background:#ff4757; color:#fff; font-size:10px; font-weight:700;
    padding:2px 8px; border-radius:20px; animation:pulse 1.5s infinite;
    letter-spacing:1px;
}
.empty-state { text-align:center; padding:40px 20px; color:var(--text-muted); }
</style>

<!-- Teacher Panel: ক্লাস শুরু/শেষ করুন -->
<?php if ($teacher): ?>
<div class="card mb-24">
    <div class="card-header" style="background:<?=$myOngoing?'#27ae60':'var(--primary)'?>;color:#fff;">
        <span style="font-weight:700;font-size:16px;">
            <i class="fas fa-chalkboard-teacher"></i>
            <?=$myOngoing?'✅ ক্লাস চলছে':'আপনার ক্লাস'?>
        </span>
        <?php if($myOngoing): ?>
        <span class="live-badge">LIVE</span>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if ($myOngoing): ?>
        <!-- Ongoing class info -->
        <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
            <div style="flex:1;">
                <div style="font-size:20px;font-weight:700;color:var(--success);"><?=e($myOngoing['class_name_bn'])?></div>
                <div style="font-size:14px;color:var(--text-muted);margin-top:4px;">
                    বিষয়: <?=e($myOngoing['subject_name_bn']??'উল্লেখ নেই')?> &bull;
                    শুরু: <strong><?=date('h:i A',strtotime($myOngoing['started_at']))?></strong>
                </div>
                <div style="font-size:13px;color:var(--success);margin-top:6px;" id="ongoingTimer">
                    ⏱ চলছে...
                </div>
            </div>
            <button onclick="endClass()" class="btn btn-danger" style="padding:12px 24px;font-size:15px;">
                <i class="fas fa-stop-circle"></i> ক্লাস শেষ করুন
            </button>
        </div>
        <?php else: ?>
        <!-- Start class form -->
        <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">
            <div class="form-group" style="flex:1;min-width:160px;margin:0;">
                <label style="font-size:12px;">শ্রেণী নির্বাচন করুন *</label>
                <select id="startClassId" class="form-control" style="padding:8px;">
                    <option value="">শ্রেণী নির্বাচন করুন</option>
                    <?php foreach($classes as $c): ?>
                    <option value="<?=$c['id']?>"><?=e($c['class_name_bn'])?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="flex:1;min-width:160px;margin:0;">
                <label style="font-size:12px;">বিষয় (ঐচ্ছিক)</label>
                <select id="startSubjectId" class="form-control" style="padding:8px;">
                    <option value="">বিষয় নির্বাচন করুন</option>
                    <?php foreach($subjects as $s): ?>
                    <option value="<?=$s['id']?>"><?=e($s['subject_name_bn'])?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button onclick="startClass()" class="btn btn-success" style="padding:12px 24px;font-size:15px;">
                <i class="fas fa-play-circle"></i> ক্লাস শুরু করুন
            </button>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Principal Dashboard: Live View -->
<?php if(in_array($_SESSION['role_slug'],['super_admin','principal','teacher'])): ?>

<div style="display:grid;gap:8px;margin-bottom:16px;">
    <div style="display:flex;align-items:center;gap:8px;">
        <span style="width:10px;height:10px;background:var(--success);border-radius:50%;"></span>
        <span style="font-weight:700;font-size:14px;color:var(--success);">নিশ্চিত উপস্থিত</span>
        <span style="font-size:12px;color:var(--text-muted);">(শিক্ষক নিজে শুরু করেছেন)</span>
    </div>
</div>

<div id="confirmedGrid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:16px;margin-bottom:24px;">
    <div class="empty-state" style="grid-column:1/-1;"><div class="spinner"></div></div>
</div>

<div style="display:flex;align-items:center;gap:8px;margin-bottom:16px;">
    <span style="width:10px;height:10px;background:var(--warning);border-radius:50%;"></span>
    <span style="font-weight:700;font-size:14px;color:var(--warning);">রুটিন অনুযায়ী থাকার কথা</span>
    <span style="font-size:12px;color:var(--text-muted);">(নিশ্চিত নয়)</span>
</div>

<div id="expectedGrid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:16px;margin-bottom:24px;">
    <div class="empty-state" style="grid-column:1/-1;padding:20px;"><i class="fas fa-clock" style="font-size:24px;margin-bottom:8px;"></i><br>লোড হচ্ছে...</div>
</div>

<div class="card">
    <div class="card-header"><span class="card-title"><i class="fas fa-history"></i> আজকের ক্লাস ইতিহাস</span></div>
    <div id="historyList" style="padding:0;">
        <div class="empty-state"><div class="spinner"></div></div>
    </div>
</div>

<?php endif; ?>

<script>
const csrf = '<?=getCsrfToken()?>';
const isTeacher = <?=$teacher?'true':'false'?>;
const ongoingStarted = <?=$myOngoing?json_encode($myOngoing['started_at']):'null'?>;

// Live clock
setInterval(() => {
    const now = new Date();
    document.getElementById('liveTime').textContent =
        now.toLocaleTimeString('bn-BD', {hour:'2-digit',minute:'2-digit',second:'2-digit'});
}, 1000);

// Ongoing timer
if (ongoingStarted) {
    setInterval(() => {
        const start = new Date(ongoingStarted.replace(' ','T'));
        const diff = Math.floor((new Date() - start) / 1000);
        const h = Math.floor(diff/3600);
        const m = Math.floor((diff%3600)/60);
        const s = diff % 60;
        const el = document.getElementById('ongoingTimer');
        if (el) el.textContent = `⏱ ${h?h+'ঘণ্টা ':''} ${m}মিনিট ${s}সেকেন্ড চলছে`;
    }, 1000);
}

function startClass() {
    const classId = document.getElementById('startClassId').value;
    if (!classId) { alert('শ্রেণী নির্বাচন করুন'); return; }
    const subjectId = document.getElementById('startSubjectId').value;

    fetch('', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body: new URLSearchParams({action:'start_class', class_id:classId, subject_id:subjectId, csrf})
    }).then(r=>r.json()).then(d => {
        if (d.success) location.reload();
        else alert(d.msg);
    });
}

function endClass() {
    if (!confirm('ক্লাস শেষ করবেন?')) return;
    fetch('', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body: new URLSearchParams({action:'end_class', csrf})
    }).then(r=>r.json()).then(d => {
        if (d.success) location.reload();
        else alert(d.msg);
    });
}

// Load live data
function loadLiveData() {
    fetch('', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body: new URLSearchParams({action:'get_live_data', csrf})
    }).then(r=>r.json()).then(d => {
        if (!d.success) return;

        document.getElementById('liveTime').textContent = d.time;

        // Confirmed
        const cGrid = document.getElementById('confirmedGrid');
        if (!cGrid) return;
        if (d.confirmed.length === 0) {
            cGrid.innerHTML = '<div class="empty-state" style="grid-column:1/-1;padding:20px;"><i class="fas fa-info-circle" style="font-size:24px;margin-bottom:8px;color:#ccc;"></i><br>এখন কোনো শিক্ষক ক্লাসে নেই</div>';
        } else {
            cGrid.innerHTML = d.confirmed.map(c => `
                <div class="class-card">
                    <div style="display:flex;gap:12px;align-items:flex-start;">
                        <div class="teacher-avatar">${(c.teacher_name||'?').charAt(0)}</div>
                        <div style="flex:1;">
                            <div style="display:flex;justify-content:space-between;align-items:flex-start;">
                                <div style="font-weight:700;font-size:15px;">${c.teacher_name||''}</div>
                                <span class="live-badge">LIVE</span>
                            </div>
                            <div style="margin-top:6px;">
                                <span class="badge badge-success" style="font-size:12px;">${c.class_name_bn||''}</span>
                                ${c.subject_name_bn?`<span class="badge badge-info" style="font-size:11px;margin-left:4px;">${c.subject_name_bn}</span>`:''}
                            </div>
                            <div style="font-size:12px;color:#718096;margin-top:6px;">
                                ⏱ ${c.minutes_ago} মিনিট ধরে চলছে
                            </div>
                        </div>
                    </div>
                </div>`).join('');
        }

        // Expected (from timetable)
        const eGrid = document.getElementById('expectedGrid');
        if (d.expected.length === 0) {
            eGrid.innerHTML = '<div class="empty-state" style="grid-column:1/-1;padding:20px;color:#ccc;"><i class="fas fa-calendar-times" style="font-size:24px;margin-bottom:8px;"></i><br>এই মুহূর্তে রুটিনে কোনো ক্লাস নেই</div>';
        } else {
            eGrid.innerHTML = d.expected.map(e => `
                <div class="class-card expected">
                    <div style="display:flex;gap:12px;align-items:flex-start;">
                        <div class="teacher-avatar" style="background:var(--warning);">${(e.teacher_name||'?').charAt(0)}</div>
                        <div style="flex:1;">
                            <div style="font-weight:700;font-size:15px;">${e.teacher_name||''}</div>
                            <div style="margin-top:6px;">
                                <span class="badge badge-warning" style="font-size:12px;">${e.class_name_bn||''}</span>
                                ${e.subject_name_bn?`<span class="badge badge-secondary" style="font-size:11px;margin-left:4px;">${e.subject_name_bn}</span>`:''}
                            </div>
                            <div style="font-size:12px;color:#718096;margin-top:6px;">
                                🕐 ${e.start_time} — ${e.end_time}
                            </div>
                        </div>
                    </div>
                </div>`).join('');
        }

        // History
        const hist = document.getElementById('historyList');
        if (d.history.length === 0) {
            hist.innerHTML = '<div class="empty-state" style="padding:20px;">আজ এখনো কোনো ক্লাস শেষ হয়নি</div>';
        } else {
            hist.innerHTML = `<table style="width:100%;border-collapse:collapse;font-size:13px;">
                <thead><tr>
                    <th style="padding:10px 14px;background:var(--primary);color:#fff;text-align:left;">শিক্ষক</th>
                    <th style="padding:10px 14px;background:var(--primary);color:#fff;">শ্রেণী</th>
                    <th style="padding:10px 14px;background:var(--primary);color:#fff;">বিষয়</th>
                    <th style="padding:10px 14px;background:var(--primary);color:#fff;">সময়</th>
                    <th style="padding:10px 14px;background:var(--primary);color:#fff;">স্থায়িত্ব</th>
                </tr></thead>
                <tbody>${d.history.map((h,i) => `
                    <tr style="border-bottom:1px solid #e2e8f0;background:${i%2?'#f7fafc':'#fff'}">
                        <td style="padding:10px 14px;font-weight:600;">${h.teacher_name||''}</td>
                        <td style="padding:10px 14px;">${h.class_name_bn||''}</td>
                        <td style="padding:10px 14px;color:#718096;">${h.subject_name_bn||'-'}</td>
                        <td style="padding:10px 14px;white-space:nowrap;">${h.started_at.slice(11,16)} — ${(h.ended_at||'').slice(11,16)}</td>
                        <td style="padding:10px 14px;">${h.duration_minutes||0} মিনিট</td>
                    </tr>`).join('')}
                </tbody></table>`;
        }
    });
}

// Load immediately then every 15 seconds
loadLiveData();
setInterval(loadLiveData, 15000);
</script>

<?php require_once '../../includes/footer.php'; ?>
