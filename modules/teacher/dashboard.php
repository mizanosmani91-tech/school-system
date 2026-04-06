<?php
require_once '../../includes/functions.php';
requireLogin(['teacher']);
$pageTitle = 'শিক্ষক ড্যাশবোর্ড';
$db = getDB();
$userId = $_SESSION['user_id'];

// শিক্ষকের তথ্য
$teacher = $db->prepare("SELECT * FROM teachers WHERE user_id=?");
$teacher->execute([$userId]);
$teacher = $teacher->fetch();

if (!$teacher) {
    setFlash('danger', 'শিক্ষক প্রোফাইল পাওয়া যায়নি।');
    header('Location: ' . BASE_URL . '/logout.php');
    exit;
}

$today = date('Y-m-d');

// আজকের চেক ইন স্ট্যাটাস
$checkIn = $db->prepare("SELECT * FROM teacher_attendance WHERE teacher_id=? AND date=?");
$checkIn->execute([$teacher['id'], $today]);
$todayAttendance = $checkIn->fetch();

// এই মাসে কতদিন উপস্থিত
$monthAttendance = $db->prepare("SELECT COUNT(*) FROM teacher_attendance WHERE teacher_id=? AND DATE_FORMAT(date,'%Y-%m')=? AND status='present'");
$monthAttendance->execute([$teacher['id'], date('Y-m')]);
$presentDays = $monthAttendance->fetchColumn();

// নোটিশ
try {
    $notices = $db->query("SELECT * FROM notices ORDER BY created_at DESC LIMIT 5")->fetchAll();
} catch(Exception $e) { $notices = []; }

// live_classes টেবিল তৈরি
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

// AJAX — ক্লাস শুরু/শেষ
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];
    if ($action === 'start_class') {
        $classId   = (int)$_POST['class_id'];
        $subjectId = (int)($_POST['subject_id']??0) ?: null;
        $db->prepare("UPDATE live_classes SET status='ended',ended_at=NOW() WHERE teacher_id=? AND status='ongoing'")
           ->execute([$teacher['id']]);
        $db->prepare("INSERT INTO live_classes (teacher_id,class_id,subject_id,started_at,date,status) VALUES (?,?,?,NOW(),CURDATE(),'ongoing')")
           ->execute([$teacher['id'],$classId,$subjectId]);
        echo json_encode(['success'=>true,'msg'=>'ক্লাস শুরু হয়েছে!']);
    } elseif ($action === 'end_class') {
        $db->prepare("UPDATE live_classes SET status='ended',ended_at=NOW() WHERE teacher_id=? AND status='ongoing'")
           ->execute([$teacher['id']]);
        echo json_encode(['success'=>true,'msg'=>'ক্লাস শেষ হয়েছে।']);
    }
    exit;
}

// চলমান ক্লাস
$myOngoing = null;
try {
    $o = $db->prepare("SELECT lc.*,c.class_name_bn,s.subject_name_bn FROM live_classes lc
        JOIN classes c ON lc.class_id=c.id LEFT JOIN subjects s ON lc.subject_id=s.id
        WHERE lc.teacher_id=? AND lc.status='ongoing' AND lc.date=CURDATE()");
    $o->execute([$teacher['id']]); $myOngoing = $o->fetch();
} catch(Exception $e) {}

$classes  = $db->query("SELECT * FROM classes WHERE is_active=1 ORDER BY class_numeric")->fetchAll();
$subjects = $db->query("SELECT * FROM subjects WHERE is_active=1 ORDER BY subject_name_bn")->fetchAll();

require_once '../../includes/teacher_header.php';
?>

<div class="section-header">
    <h2 class="section-title"><i class="fas fa-tachometer-alt"></i> আমার ড্যাশবোর্ড</h2>
    <span style="font-size:13px;color:var(--text-muted);"><?= banglaDate() ?></span>
</div>

<!-- স্বাগত কার্ড -->
<div class="card mb-24" style="background:linear-gradient(135deg,var(--primary),var(--primary-light));color:#fff;border:none;">
    <div class="card-body" style="display:flex;align-items:center;gap:20px;padding:24px;">
        <div style="width:60px;height:60px;background:rgba(255,255,255,.2);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:26px;font-weight:700;flex-shrink:0;">
            <?= mb_substr($teacher['name_bn'] ?? $teacher['name'], 0, 1) ?>
        </div>
        <div>
            <div style="font-size:20px;font-weight:700;">আস-সালামু আলাইকুম, <?= e($teacher['name_bn'] ?? $teacher['name']) ?>!</div>
            <div style="opacity:.85;font-size:14px;margin-top:4px;"><?= e($teacher['designation_bn'] ?? 'শিক্ষক') ?> | ID: <?= e($teacher['teacher_id_no']) ?></div>
        </div>
    </div>
</div>

<!-- ক্লাস শুরু/শেষ কার্ড -->
<div class="card mb-24" style="border-left:4px solid <?= $myOngoing ? 'var(--success)' : 'var(--primary)' ?>;">
    <div class="card-header" style="background:<?= $myOngoing ? '#eafaf1' : '#ebf5fb' ?>;">
        <span class="card-title" style="color:<?= $myOngoing ? 'var(--success)' : 'var(--primary)' ?>;">
            <i class="fas fa-chalkboard-teacher"></i>
            <?= $myOngoing ? '✅ ক্লাস চলছে' : 'ক্লাস শুরু করুন' ?>
        </span>
        <?php if($myOngoing): ?>
        <span style="background:#ff4757;color:#fff;font-size:10px;font-weight:700;padding:2px 10px;border-radius:20px;animation:pulse 1.5s infinite;letter-spacing:1px;">LIVE</span>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if ($myOngoing): ?>
        <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
            <div style="flex:1;">
                <div style="font-size:20px;font-weight:700;color:var(--success);"><?= e($myOngoing['class_name_bn']) ?></div>
                <div style="font-size:14px;color:var(--text-muted);margin-top:4px;">
                    বিষয়: <?= e($myOngoing['subject_name_bn'] ?? 'উল্লেখ নেই') ?> &bull;
                    শুরু: <strong><?= date('h:i A', strtotime($myOngoing['started_at'])) ?></strong>
                </div>
                <div style="font-size:13px;color:var(--success);margin-top:6px;" id="ongoingTimer">⏱ চলছে...</div>
            </div>
            <button onclick="endClass()" class="btn btn-danger" style="padding:12px 24px;font-size:15px;">
                <i class="fas fa-stop-circle"></i> ক্লাস শেষ করুন
            </button>
        </div>
        <?php else: ?>
        <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">
            <div class="form-group" style="flex:1;min-width:160px;margin:0;">
                <label style="font-size:12px;">শ্রেণী নির্বাচন করুন *</label>
                <select id="startClassId" class="form-control" style="padding:8px;">
                    <option value="">শ্রেণী নির্বাচন করুন</option>
                    <?php foreach($classes as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= e($c['class_name_bn']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="flex:1;min-width:160px;margin:0;">
                <label style="font-size:12px;">বিষয় (ঐচ্ছিক)</label>
                <select id="startSubjectId" class="form-control" style="padding:8px;">
                    <option value="">বিষয় নির্বাচন করুন</option>
                    <?php foreach($subjects as $s): ?>
                    <option value="<?= $s['id'] ?>"><?= e($s['subject_name_bn']) ?></option>
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

<style>@keyframes pulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.5;transform:scale(1.3)}}</style>
<script>
const csrf = '<?= getCsrfToken() ?>';
const ongoingStarted = <?= $myOngoing ? json_encode($myOngoing['started_at']) : 'null' ?>;

if (ongoingStarted) {
    setInterval(() => {
        const diff = Math.floor((new Date() - new Date(ongoingStarted.replace(' ','T'))) / 1000);
        const h = Math.floor(diff/3600), m = Math.floor((diff%3600)/60), s = diff%60;
        const el = document.getElementById('ongoingTimer');
        if (el) el.textContent = `⏱ ${h?h+'ঘণ্টা ':''} ${m}মিনিট ${s}সেকেন্ড`;
    }, 1000);
}

function startClass() {
    const classId = document.getElementById('startClassId').value;
    if (!classId) { alert('শ্রেণী নির্বাচন করুন'); return; }
    const subjectId = document.getElementById('startSubjectId').value;
    fetch('', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body: new URLSearchParams({action:'start_class',class_id:classId,subject_id:subjectId,csrf})
    }).then(r=>r.json()).then(d => { if(d.success) location.reload(); else alert(d.msg); });
}

function endClass() {
    if (!confirm('ক্লাস শেষ করবেন?')) return;
    fetch('', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body: new URLSearchParams({action:'end_class',csrf})
    }).then(r=>r.json()).then(d => { if(d.success) location.reload(); else alert(d.msg); });
}
</script>

<!-- স্ট্যাট কার্ড -->
<div class="stat-grid mb-24">
    <div class="stat-card <?= $todayAttendance && $todayAttendance['check_in'] ? 'green' : 'red' ?>">
        <div class="stat-icon"><i class="fas fa-fingerprint"></i></div>
        <div>
            <div class="stat-value"><?= $todayAttendance && $todayAttendance['check_in'] ? 'উপস্থিত' : 'অনুপস্থিত' ?></div>
            <div class="stat-label">আজকের অবস্থা</div>
        </div>
    </div>
    <div class="stat-card blue">
        <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
        <div>
            <div class="stat-value"><?= toBanglaNumber($presentDays) ?></div>
            <div class="stat-label">এই মাসে উপস্থিতি</div>
        </div>
    </div>
    <?php if($todayAttendance && $todayAttendance['check_in']): ?>
    <div class="stat-card green">
        <div class="stat-icon"><i class="fas fa-sign-in-alt"></i></div>
        <div>
            <div class="stat-value"><?= toBanglaNumber(date('h:i', strtotime($todayAttendance['check_in']))) ?></div>
            <div class="stat-label">আজ চেক ইন</div>
        </div>
    </div>
    <?php endif; ?>
    <?php if($todayAttendance && $todayAttendance['check_out']): ?>
    <div class="stat-card orange">
        <div class="stat-icon"><i class="fas fa-sign-out-alt"></i></div>
        <div>
            <div class="stat-value"><?= toBanglaNumber(date('h:i', strtotime($todayAttendance['check_out']))) ?></div>
            <div class="stat-label">আজ চেক আউট</div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- দ্রুত অ্যাকশন -->
<div class="card mb-24">
    <div class="card-header"><span class="card-title"><i class="fas fa-bolt"></i> দ্রুত অ্যাকশন</span></div>
    <div class="card-body">
        <div style="display:flex;flex-wrap:wrap;gap:12px;">
            <a href="<?= BASE_URL ?>/modules/attendance/checkin.php" class="btn btn-success" style="padding:12px 20px;">
                <i class="fas fa-fingerprint"></i> চেক ইন / চেক আউট
            </a>
            <a href="<?= BASE_URL ?>/modules/attendance/index.php" class="btn btn-primary" style="padding:12px 20px;">
                <i class="fas fa-clipboard-check"></i> ছাত্র উপস্থিতি
            </a>
            <a href="<?= BASE_URL ?>/modules/exam/marks.php" class="btn btn-warning" style="padding:12px 20px;">
                <i class="fas fa-pen"></i> মার্ক এন্ট্রি
            </a>
            <a href="<?= BASE_URL ?>/modules/teacher/diary.php" class="btn btn-outline" style="padding:12px 20px;">
                <i class="fas fa-book-open"></i> ডায়েরি
            </a>
            <a href="<?= BASE_URL ?>/modules/exam/model_test.php" class="btn btn-outline" style="padding:12px 20px;">
                <i class="fas fa-file-alt"></i> মডেল টেস্ট
            </a>
            <a href="<?= BASE_URL ?>/modules/teacher/profile.php" class="btn btn-outline" style="padding:12px 20px;">
                <i class="fas fa-user-circle"></i> আমার প্রোফাইল
            </a>
        </div>
    </div>
</div>

<!-- নোটিশ বোর্ড -->
<?php if(!empty($notices)): ?>
<div class="card">
    <div class="card-header"><span class="card-title"><i class="fas fa-bullhorn"></i> নোটিশ বোর্ড</span></div>
    <div class="card-body" style="padding:12px 20px;">
        <?php foreach($notices as $n): ?>
        <div style="padding:12px 0;border-bottom:1px solid var(--border);display:flex;align-items:flex-start;gap:12px;">
            <div style="width:8px;height:8px;background:var(--primary);border-radius:50%;margin-top:6px;flex-shrink:0;"></div>
            <div>
                <div style="font-weight:600;font-size:14px;"><?= e($n['title'] ?? '') ?></div>
                <div style="font-size:12px;color:var(--text-muted);margin-top:2px;"><?= banglaDate($n['created_at'] ?? '') ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>
