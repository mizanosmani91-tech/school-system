<?php
require_once '../../includes/functions.php';
startSession();

// Auth check
if ($_SESSION['role_slug'] !== 'student' || empty($_SESSION['student_id'])) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

$db = getDB();
$studentDbId = (int)$_SESSION['student_id'];

// Get student info
$stmt = $db->prepare("SELECT s.*, c.class_name_bn, c.class_name, c.id as cid, sec.section_name
    FROM students s
    LEFT JOIN classes c ON s.class_id = c.id
    LEFT JOIN sections sec ON s.section_id = sec.id
    WHERE s.id = ?");
$stmt->execute([$studentDbId]);
$student = $stmt->fetch();
if (!$student) { header('Location: ' . BASE_URL . '/login.php'); exit; }

$classId   = $student['cid'];
$tab       = $_GET['tab'] ?? 'diary';
$today     = date('Y-m-d');
$dayName   = ['Sunday'=>'রবিবার','Monday'=>'সোমবার','Tuesday'=>'মঙ্গলবার','Wednesday'=>'বুধবার','Thursday'=>'বৃহস্পতিবার','Friday'=>'শুক্রবার','Saturday'=>'শনিবার'][date('l')] ?? date('l');

// ─── POST handlers ───────────────────────────────────────────────
// Save note
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_note'])) {
    $title   = trim($_POST['note_title'] ?? '');
    $content = trim($_POST['note_content'] ?? '');
    $subId   = (int)($_POST['note_subject_id'] ?? 0) ?: null;
    if ($title && $content) {
        if (!empty($_POST['note_id'])) {
            $db->prepare("UPDATE student_notes SET title=?, content=?, subject_id=?, updated_at=NOW() WHERE id=? AND student_id=?")
               ->execute([$title, $content, $subId, (int)$_POST['note_id'], $studentDbId]);
        } else {
            $db->prepare("INSERT INTO student_notes (student_id, subject_id, title, content) VALUES (?,?,?,?)")
               ->execute([$studentDbId, $subId, $title, $content]);
        }
    }
    header("Location: portal.php?tab=notes"); exit;
}
// Delete note
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_note'])) {
    $db->prepare("DELETE FROM student_notes WHERE id=? AND student_id=?")->execute([(int)$_POST['note_id'], $studentDbId]);
    header("Location: portal.php?tab=notes"); exit;
}
// Send message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $msg      = trim($_POST['message'] ?? '');
    $teachId  = (int)($_POST['teacher_id'] ?? 0) ?: null;
    if ($msg) {
        $db->prepare("INSERT INTO student_messages (student_id, teacher_id, sender, message) VALUES (?,?,'student',?)")
           ->execute([$studentDbId, $teachId, $msg]);
    }
    header("Location: portal.php?tab=messages"); exit;
}
// Submit model test
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_test'])) {
    $subId     = (int)($_POST['subject_id'] ?? 0);
    $answers   = $_POST['answers'] ?? [];
    $qIds      = array_keys($answers);
    $correct   = $wrong = 0;
    if ($qIds) {
        $in    = implode(',', array_map('intval', $qIds));
        $qRows = $db->query("SELECT id, correct_answer FROM model_test_questions WHERE id IN ($in)")->fetchAll();
        foreach ($qRows as $q) {
            if (isset($answers[$q['id']])) {
                $answers[$q['id']] === $q['correct_answer'] ? $correct++ : $wrong++;
            }
        }
    }
    $total = $correct + $wrong;
    $score = $total > 0 ? round(($correct / $total) * 100, 1) : 0;
    $db->prepare("INSERT INTO model_test_results (student_id, subject_id, total_questions, correct, wrong, score) VALUES (?,?,?,?,?,?)")
       ->execute([$studentDbId, $subId, $total, $correct, $wrong, $score]);
    $_SESSION['test_result'] = compact('correct', 'wrong', 'total', 'score');
    header("Location: portal.php?tab=modeltest&done=1"); exit;
}

// ─── Data loading ────────────────────────────────────────────────
// Subjects for this class
$subjects = $db->prepare("SELECT s.* FROM subjects s JOIN class_subjects cs ON s.id=cs.subject_id WHERE cs.class_id=? ORDER BY s.subject_name_bn");
$subjects->execute([$classId]);
$subjectList = $subjects->fetchAll();

// Teachers
$teachers = $db->query("SELECT id, name, name_bn FROM teachers WHERE is_active=1 ORDER BY name_bn")->fetchAll();

// Diary
$diaryData = [];
if ($tab === 'diary') {
    $ds = $db->prepare("SELECT d.*, s.subject_name_bn FROM class_diary d LEFT JOIN subjects s ON d.subject_id=s.id WHERE d.class_id=? ORDER BY d.diary_date DESC, d.id DESC LIMIT 30");
    $ds->execute([$classId]);
    $diaryData = $ds->fetchAll();
}

// Syllabus
$syllabusData = [];
if ($tab === 'syllabus') {
    $ss = $db->prepare("SELECT sy.*, s.subject_name_bn FROM syllabus sy JOIN subjects s ON sy.subject_id=s.id WHERE sy.class_id=? ORDER BY s.subject_name_bn, sy.id");
    $ss->execute([$classId]);
    $syllabusData = $ss->fetchAll();
}

// Timetable
$timetableData = [];
if ($tab === 'routine') {
    $ts = $db->prepare("SELECT t.*, s.subject_name_bn, CONCAT(te.name_bn,' (',te.name,')') as teacher_name
        FROM timetable t
        LEFT JOIN subjects s ON t.subject_id=s.id
        LEFT JOIN teachers te ON t.teacher_id=te.id
        WHERE t.class_id=? ORDER BY FIELD(t.day_of_week,'Saturday','Sunday','Monday','Tuesday','Wednesday','Thursday'), t.start_time");
    $ts->execute([$classId]);
    $timetableData = $ts->fetchAll();
}

// Exam routine
$examData = [];
if ($tab === 'exam') {
    $es = $db->prepare("SELECT e.*, GROUP_CONCAT(DISTINCT s.subject_name_bn ORDER BY s.subject_name_bn SEPARATOR ', ') as subjects
        FROM exams e
        LEFT JOIN exam_marks em ON e.id=em.exam_id AND em.student_id=?
        LEFT JOIN subjects s ON em.subject_id=s.id
        WHERE (e.class_id=? OR e.class_id IS NULL) AND e.start_date >= CURDATE() - INTERVAL 30 DAY
        GROUP BY e.id ORDER BY e.start_date ASC LIMIT 10");
    $es->execute([$studentDbId, $classId]);
    $examData = $es->fetchAll();
}

// Model test
$testSubjectId = (int)($_GET['subject_id'] ?? 0);
$testQuestions = [];
$testResult    = null;
if ($tab === 'modeltest') {
    if (!empty($_SESSION['test_result']) && isset($_GET['done'])) {
        $testResult = $_SESSION['test_result'];
        unset($_SESSION['test_result']);
    }
    if ($testSubjectId) {
        $tq = $db->prepare("SELECT * FROM model_test_questions WHERE subject_id=? AND class_id=? ORDER BY RAND() LIMIT 10");
        $tq->execute([$testSubjectId, $classId]);
        $testQuestions = $tq->fetchAll();
    }
    // History
    $testHistory = $db->prepare("SELECT mr.*, s.subject_name_bn FROM model_test_results mr JOIN subjects s ON mr.subject_id=s.id WHERE mr.student_id=? ORDER BY mr.taken_at DESC LIMIT 10");
    $testHistory->execute([$studentDbId]);
    $testHistoryData = $testHistory->fetchAll();
}

// Notes
$notesData = [];
if ($tab === 'notes') {
    $ns = $db->prepare("SELECT n.*, s.subject_name_bn FROM student_notes n LEFT JOIN subjects s ON n.subject_id=s.id WHERE n.student_id=? ORDER BY n.updated_at DESC");
    $ns->execute([$studentDbId]);
    $notesData = $ns->fetchAll();
}

// Messages
$messagesData = [];
if ($tab === 'messages') {
    $ms = $db->prepare("SELECT m.*, t.name_bn as teacher_name_bn FROM student_messages m LEFT JOIN teachers t ON m.teacher_id=t.id WHERE m.student_id=? ORDER BY m.created_at ASC");
    $ms->execute([$studentDbId]);
    $messagesData = $ms->fetchAll();
    // Mark teacher replies as read
    $db->prepare("UPDATE student_messages SET is_read=1 WHERE student_id=? AND sender='teacher' AND is_read=0")->execute([$studentDbId]);
}
$unreadCount = $db->prepare("SELECT COUNT(*) FROM student_messages WHERE student_id=? AND sender='teacher' AND is_read=0");
$unreadCount->execute([$studentDbId]);
$unread = $unreadCount->fetchColumn();
?>
<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ছাত্র পোর্টাল | <?= e(getSetting('institute_name','মাদ্রাসা')) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
:root {
    --primary: #1a5276; --primary-dark: #0d2137; --accent: #e67e22;
    --success: #27ae60; --danger: #e74c3c; --warning: #f39c12;
    --bg: #f0f4f8; --card: #fff; --border: #e2e8f0;
    --text: #1a202c; --muted: #718096;
    --font: 'Hind Siliguri', sans-serif;
}
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:var(--font); background:var(--bg); color:var(--text); font-size:15px; }

/* Header */
.portal-header {
    background: linear-gradient(135deg, var(--primary-dark), var(--primary));
    color:#fff; padding:0 20px; height:60px;
    display:flex; align-items:center; justify-content:space-between;
    position:sticky; top:0; z-index:200;
    box-shadow: 0 2px 10px rgba(0,0,0,.2);
}
.portal-header h1 { font-size:17px; display:flex; align-items:center; gap:10px; }
.header-right { display:flex; align-items:center; gap:14px; font-size:13px; }
.logout-btn { background:rgba(255,255,255,.15); border:none; color:#fff; padding:6px 14px; border-radius:20px; cursor:pointer; font-family:var(--font); font-size:13px; text-decoration:none; display:flex; align-items:center; gap:6px; transition:.2s; }
.logout-btn:hover { background:rgba(255,255,255,.25); }

/* Student info bar */
.student-bar {
    background: linear-gradient(135deg, #1a5276 0%, #117a65 100%);
    color:#fff; padding:14px 20px;
    display:flex; align-items:center; gap:16px; flex-wrap:wrap;
}
.student-avatar {
    width:48px; height:48px; border-radius:12px;
    background:rgba(255,255,255,.2); display:flex; align-items:center;
    justify-content:center; font-size:20px; font-weight:700; flex-shrink:0;
}
.student-info h2 { font-size:17px; font-weight:700; }
.student-info p  { font-size:12px; opacity:.8; margin-top:2px; }
.student-meta { display:flex; gap:16px; flex-wrap:wrap; margin-left:auto; font-size:12px; opacity:.85; }
.student-meta span { display:flex; align-items:center; gap:5px; }

/* Nav tabs */
.nav-tabs {
    display:flex; gap:2px; overflow-x:auto; padding:0 12px;
    background:#fff; border-bottom:2px solid var(--border);
    position:sticky; top:60px; z-index:100;
    scrollbar-width:none;
}
.nav-tabs::-webkit-scrollbar { display:none; }
.nav-tab {
    padding:13px 16px; color:var(--muted); font-size:13px; font-weight:600;
    cursor:pointer; white-space:nowrap; border-bottom:3px solid transparent;
    text-decoration:none; display:flex; align-items:center; gap:6px;
    transition:all .2s; position:relative; top:2px;
}
.nav-tab:hover { color:var(--primary); }
.nav-tab.active { color:var(--primary); border-bottom-color:var(--primary); }
.nav-tab .badge-dot {
    background:var(--danger); color:#fff; font-size:10px;
    padding:1px 5px; border-radius:10px; font-weight:700;
}

/* Content */
.content { max-width:960px; margin:0 auto; padding:20px 16px; }

/* Cards */
.card { background:var(--card); border-radius:12px; overflow:hidden; box-shadow:0 1px 4px rgba(0,0,0,.08); margin-bottom:16px; }
.card-header { padding:14px 18px; border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; }
.card-title { font-size:15px; font-weight:700; color:var(--primary-dark); display:flex; align-items:center; gap:8px; }
.card-body { padding:18px; }

/* Tables */
table { width:100%; border-collapse:collapse; font-size:13px; }
thead th { background:var(--primary); color:#fff; padding:10px 12px; text-align:left; }
tbody tr { border-bottom:1px solid var(--border); }
tbody td { padding:10px 12px; }
tbody tr:hover { background:#f7fafc; }

/* Badges */
.badge { display:inline-flex; align-items:center; padding:3px 9px; border-radius:20px; font-size:11px; font-weight:700; }
.badge-success { background:#d4edda; color:#155724; }
.badge-warning { background:#fff3cd; color:#856404; }
.badge-danger  { background:#f8d7da; color:#721c24; }
.badge-info    { background:#d1ecf1; color:#0c5460; }
.badge-primary { background:#cce5ff; color:#004085; }

/* Forms */
.form-group { margin-bottom:14px; }
.form-group label { display:block; font-size:13px; font-weight:600; color:#2d3748; margin-bottom:6px; }
.form-control {
    width:100%; padding:10px 12px; border:1.5px solid var(--border);
    border-radius:8px; font-family:var(--font); font-size:14px;
    color:var(--text); outline:none; transition:.2s;
}
.form-control:focus { border-color:var(--primary); box-shadow:0 0 0 3px rgba(26,82,118,.1); }
textarea.form-control { resize:vertical; }
.btn { display:inline-flex; align-items:center; gap:6px; padding:9px 18px; border-radius:8px; border:none; cursor:pointer; font-family:var(--font); font-size:14px; font-weight:600; text-decoration:none; transition:all .2s; }
.btn-primary { background:var(--primary); color:#fff; }
.btn-primary:hover { background:var(--primary-dark); }
.btn-success { background:var(--success); color:#fff; }
.btn-danger  { background:var(--danger); color:#fff; }
.btn-outline { background:transparent; border:1.5px solid var(--border); color:var(--text); }
.btn-sm { padding:6px 12px; font-size:12px; }

/* Diary */
.diary-item { padding:14px 0; border-bottom:1px solid var(--border); }
.diary-item:last-child { border:none; }
.diary-date-badge { display:inline-flex; align-items:center; gap:6px; background:var(--primary); color:#fff; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:700; margin-bottom:8px; }
.diary-subject { font-size:12px; color:var(--accent); font-weight:700; margin-bottom:4px; }
.diary-content { font-size:14px; line-height:1.7; }
.diary-hw { background:#fffbeb; border-left:3px solid var(--warning); padding:8px 12px; border-radius:0 6px 6px 0; margin-top:8px; font-size:13px; }

/* Routine */
.day-block { margin-bottom:16px; }
.day-label { background:var(--primary); color:#fff; padding:8px 14px; border-radius:8px 8px 0 0; font-weight:700; font-size:13px; display:flex; align-items:center; gap:8px; }
.day-label.today-label { background:var(--accent); }
.period-row { display:flex; align-items:center; gap:12px; padding:10px 14px; background:#fff; border:1px solid var(--border); border-top:none; font-size:13px; }
.period-time { color:var(--muted); width:110px; flex-shrink:0; font-size:12px; }
.period-subject { font-weight:700; flex:1; }
.period-teacher { color:var(--muted); font-size:12px; }

/* Syllabus */
.syllabus-subject { margin-bottom:16px; }
.syllabus-subject-title { background:#ebf5fb; padding:10px 14px; border-radius:8px 8px 0 0; font-weight:700; color:var(--primary); font-size:14px; border:1px solid #bee3f8; }
.syllabus-item { padding:10px 14px; border:1px solid var(--border); border-top:none; display:flex; align-items:flex-start; gap:10px; }
.syllabus-item:last-child { border-radius:0 0 8px 8px; }

/* Model test */
.question-card { background:#f8fafc; border:1px solid var(--border); border-radius:10px; padding:16px; margin-bottom:14px; }
.question-text { font-size:15px; font-weight:600; margin-bottom:12px; }
.options { display:grid; grid-template-columns:1fr 1fr; gap:8px; }
.option-label { display:flex; align-items:center; gap:8px; padding:9px 12px; background:#fff; border:1.5px solid var(--border); border-radius:8px; cursor:pointer; font-size:13px; transition:.15s; }
.option-label:hover { border-color:var(--primary); background:#ebf5fb; }
.option-label input { accent-color:var(--primary); }

/* Result card */
.result-card { background:linear-gradient(135deg,var(--primary),var(--primary-dark)); color:#fff; border-radius:14px; padding:24px; text-align:center; margin-bottom:20px; }
.result-score { font-size:52px; font-weight:700; }
.result-label { font-size:14px; opacity:.8; margin-top:4px; }
.result-stats { display:flex; justify-content:center; gap:24px; margin-top:16px; }
.result-stat { background:rgba(255,255,255,.15); padding:10px 20px; border-radius:10px; }
.result-stat .val { font-size:22px; font-weight:700; }
.result-stat .lbl { font-size:11px; opacity:.8; }

/* Notes */
.note-card { border-left:4px solid var(--accent); padding:14px 16px; background:#fff; border-radius:0 10px 10px 0; margin-bottom:12px; box-shadow:0 1px 3px rgba(0,0,0,.06); }
.note-title { font-size:15px; font-weight:700; margin-bottom:6px; }
.note-content { font-size:13px; color:#4a5568; line-height:1.7; white-space:pre-wrap; }
.note-meta { font-size:11px; color:var(--muted); margin-top:8px; display:flex; align-items:center; justify-content:space-between; }

/* Messages */
.msg-bubble { margin-bottom:12px; display:flex; }
.msg-bubble.sent { justify-content:flex-end; }
.msg-bubble.received { justify-content:flex-start; }
.bubble { max-width:70%; padding:10px 14px; border-radius:14px; font-size:13px; line-height:1.6; }
.sent .bubble { background:var(--primary); color:#fff; border-radius:14px 14px 4px 14px; }
.received .bubble { background:#fff; border:1px solid var(--border); border-radius:14px 14px 14px 4px; }
.bubble-meta { font-size:10px; margin-top:4px; opacity:.7; }
.teacher-name { font-size:11px; font-weight:700; color:var(--primary); margin-bottom:4px; }
.msg-area { min-height:300px; max-height:400px; overflow-y:auto; padding:16px; background:#f7fafc; border-radius:10px; margin-bottom:14px; }

/* Exam cards */
.exam-card { background:#fff; border:1px solid var(--border); border-radius:10px; padding:16px; margin-bottom:12px; display:flex; align-items:flex-start; gap:14px; }
.exam-date-box { background:var(--primary); color:#fff; border-radius:10px; padding:10px 14px; text-align:center; min-width:60px; flex-shrink:0; }
.exam-date-day { font-size:22px; font-weight:700; line-height:1; }
.exam-date-month { font-size:11px; opacity:.8; margin-top:2px; }
.exam-info h3 { font-size:15px; font-weight:700; margin-bottom:4px; }
.exam-info p { font-size:12px; color:var(--muted); }

/* Empty state */
.empty-state { text-align:center; padding:40px 20px; color:var(--muted); }
.empty-state i { font-size:40px; margin-bottom:12px; opacity:.4; display:block; }

/* Note modal */
.modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:500; align-items:center; justify-content:center; }
.modal-overlay.open { display:flex; }
.modal { background:#fff; border-radius:14px; padding:24px; width:90%; max-width:520px; }
.modal h3 { font-size:17px; font-weight:700; margin-bottom:16px; color:var(--primary-dark); }

@media (max-width:600px) {
    .options { grid-template-columns:1fr; }
    .student-meta { display:none; }
    .result-score { font-size:40px; }
}
</style>
</head>
<body>

<!-- Header -->
<div class="portal-header">
    <h1><i class="fas fa-user-graduate"></i> ছাত্র পোর্টাল</h1>
    <div class="header-right">
        <span style="font-size:12px;opacity:.8;"><?= e($student['name_bn'] ?? $student['name']) ?></span>
        <a href="<?= BASE_URL ?>/logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> লগআউট</a>
    </div>
</div>

<!-- Student Bar -->
<div class="student-bar">
    <div class="student-avatar">
        <?php if ($student['photo']): ?>
        <img src="<?= UPLOAD_URL . e($student['photo']) ?>" style="width:100%;height:100%;object-fit:cover;border-radius:10px;">
        <?php else: ?>
        <?= mb_substr($student['name_bn'] ?? $student['name'], 0, 1) ?>
        <?php endif; ?>
    </div>
    <div class="student-info">
        <h2><?= e($student['name_bn'] ?? $student['name']) ?></h2>
        <p><?= e($student['name']) ?></p>
    </div>
    <div class="student-meta">
        <span><i class="fas fa-id-card"></i> <?= e($student['student_id']) ?></span>
        <span><i class="fas fa-school"></i> <?= e($student['class_name_bn']) ?> <?= e($student['section_name'] ?? '') ?></span>
        <span><i class="fas fa-hashtag"></i> রোল: <?= e($student['roll_number']) ?></span>
        <span><i class="fas fa-calendar-day"></i> আজ: <?= $dayName ?></span>
    </div>
</div>

<!-- Nav -->
<div class="nav-tabs">
    <a href="?tab=diary"     class="nav-tab <?= $tab==='diary'?'active':'' ?>"><i class="fas fa-book-open"></i> ক্লাস ডাইরি</a>
    <a href="?tab=routine"   class="nav-tab <?= $tab==='routine'?'active':'' ?>"><i class="fas fa-calendar-week"></i> রুটিন</a>
    <a href="?tab=syllabus"  class="nav-tab <?= $tab==='syllabus'?'active':'' ?>"><i class="fas fa-list-alt"></i> সিলেবাস</a>
    <a href="?tab=exam"      class="nav-tab <?= $tab==='exam'?'active':'' ?>"><i class="fas fa-file-alt"></i> পরীক্ষার সময়সূচি</a>
    <a href="?tab=modeltest" class="nav-tab <?= $tab==='modeltest'?'active':'' ?>"><i class="fas fa-tasks"></i> মডেল টেস্ট</a>
    <a href="?tab=notes"     class="nav-tab <?= $tab==='notes'?'active':'' ?>"><i class="fas fa-sticky-note"></i> আমার নোট</a>
    <a href="?tab=messages"  class="nav-tab <?= $tab==='messages'?'active':'' ?>">
        <i class="fas fa-comments"></i> বার্তা
        <?php if ($unread > 0): ?><span class="badge-dot"><?= $unread ?></span><?php endif; ?>
    </a>
</div>

<div class="content">

<?php /* ═══════════ DIARY ═══════════ */ if ($tab === 'diary'): ?>
<div class="card">
    <div class="card-header">
        <span class="card-title"><i class="fas fa-book-open"></i> ক্লাস ডাইরি</span>
        <span style="font-size:12px;color:var(--muted);">সর্বশেষ ৩০টি এন্ট্রি</span>
    </div>
    <div class="card-body">
        <?php if (empty($diaryData)): ?>
        <div class="empty-state"><i class="fas fa-book"></i> এখনো কোনো ডাইরি এন্ট্রি নেই</div>
        <?php else: foreach ($diaryData as $d): ?>
        <div class="diary-item">
            <div class="diary-date-badge"><i class="fas fa-calendar-day"></i> <?= banglaDate($d['diary_date']) ?></div>
            <?php if ($d['subject_name_bn']): ?><div class="diary-subject"><i class="fas fa-book"></i> <?= e($d['subject_name_bn']) ?></div><?php endif; ?>
            <div class="diary-content"><?= nl2br(e($d['content'])) ?></div>
            <?php if ($d['homework']): ?>
            <div class="diary-hw"><strong><i class="fas fa-pencil-alt"></i> বাড়ির কাজ:</strong> <?= nl2br(e($d['homework'])) ?></div>
            <?php endif; ?>
        </div>
        <?php endforeach; endif; ?>
    </div>
</div>

<?php /* ═══════════ ROUTINE ═══════════ */ elseif ($tab === 'routine'): ?>
<div class="card">
    <div class="card-header">
        <span class="card-title"><i class="fas fa-calendar-week"></i> ক্লাস রুটিন</span>
        <span class="badge badge-info"><?= $dayName ?></span>
    </div>
    <div class="card-body" style="padding:0;">
        <?php
        $days = ['Saturday'=>'শনিবার','Sunday'=>'রবিবার','Monday'=>'সোমবার','Tuesday'=>'মঙ্গলবার','Wednesday'=>'বুধবার','Thursday'=>'বৃহস্পতিবার'];
        $grouped = [];
        foreach ($timetableData as $r) { $grouped[$r['day_of_week']][] = $r; }
        $todayEn = date('l');
        if (empty($grouped)):
        ?>
        <div class="empty-state"><i class="fas fa-calendar"></i> রুটিন এখনো যোগ করা হয়নি</div>
        <?php else: foreach ($days as $en => $bn): if (empty($grouped[$en])) continue; ?>
        <div class="day-block" style="margin:12px 12px 0;">
            <div class="day-label <?= $en===$todayEn?'today-label':'' ?>">
                <i class="fas fa-calendar-day"></i> <?= $bn ?> <?= $en===$todayEn ? '← আজ' : '' ?>
            </div>
            <?php foreach ($grouped[$en] as $p): ?>
            <div class="period-row">
                <span class="period-time"><i class="fas fa-clock" style="opacity:.5;margin-right:3px;"></i><?= e($p['start_time']) ?> - <?= e($p['end_time']) ?></span>
                <span class="period-subject"><?= e($p['subject_name_bn'] ?? 'বিষয়') ?></span>
                <span class="period-teacher"><?= e($p['teacher_name'] ?? '') ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; endif; ?>
        <div style="height:12px;"></div>
    </div>
</div>

<?php /* ═══════════ SYLLABUS ═══════════ */ elseif ($tab === 'syllabus'): ?>
<div class="card">
    <div class="card-header">
        <span class="card-title"><i class="fas fa-list-alt"></i> সিলেবাস</span>
    </div>
    <div class="card-body">
        <?php
        if (empty($syllabusData)):
        ?><div class="empty-state"><i class="fas fa-clipboard-list"></i> সিলেবাস এখনো যোগ করা হয়নি</div><?php
        else:
            $sGrouped = [];
            foreach ($syllabusData as $s) { $sGrouped[$s['subject_name_bn']][] = $s; }
            foreach ($sGrouped as $subName => $items):
        ?>
        <div class="syllabus-subject">
            <div class="syllabus-subject-title"><i class="fas fa-book"></i> <?= e($subName) ?></div>
            <?php foreach ($items as $item): ?>
            <div class="syllabus-item">
                <span style="margin-top:2px;">
                    <?php if ($item['is_completed']): ?>
                    <i class="fas fa-check-circle" style="color:var(--success);"></i>
                    <?php else: ?>
                    <i class="far fa-circle" style="color:var(--muted);"></i>
                    <?php endif; ?>
                </span>
                <div>
                    <div style="font-weight:600;font-size:14px;"><?= e($item['title']) ?></div>
                    <?php if ($item['description']): ?>
                    <div style="font-size:12px;color:var(--muted);margin-top:3px;"><?= e($item['description']) ?></div>
                    <?php endif; ?>
                </div>
                <?php if ($item['is_completed']): ?><span class="badge badge-success" style="margin-left:auto;flex-shrink:0;">সম্পন্ন</span><?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; endif; ?>
    </div>
</div>

<?php /* ═══════════ EXAM ═══════════ */ elseif ($tab === 'exam'): ?>
<div class="card">
    <div class="card-header">
        <span class="card-title"><i class="fas fa-file-alt"></i> পরীক্ষার সময়সূচি</span>
    </div>
    <div class="card-body">
        <?php if (empty($examData)): ?>
        <div class="empty-state"><i class="fas fa-file-alt"></i> আসন্ন কোনো পরীক্ষা নেই</div>
        <?php else: foreach ($examData as $e):
            $daysLeft = (strtotime($e['start_date']) - time()) / 86400;
        ?>
        <div class="exam-card">
            <div class="exam-date-box">
                <div class="exam-date-day"><?= date('d', strtotime($e['start_date'])) ?></div>
                <div class="exam-date-month"><?= date('M', strtotime($e['start_date'])) ?></div>
            </div>
            <div class="exam-info">
                <h3><?= e($e['exam_name_bn'] ?? $e['exam_name']) ?></h3>
                <p><i class="fas fa-calendar-alt" style="margin-right:4px;"></i>
                    <?= banglaDate($e['start_date']) ?>
                    <?php if ($e['end_date'] && $e['end_date'] !== $e['start_date']): ?>
                     — <?= banglaDate($e['end_date']) ?>
                    <?php endif; ?>
                </p>
                <?php if ($daysLeft > 0): ?>
                <span class="badge badge-<?= $daysLeft <= 3 ? 'danger' : ($daysLeft <= 7 ? 'warning' : 'info') ?>" style="margin-top:6px;">
                    <?= round($daysLeft) ?> দিন বাকি
                </span>
                <?php else: ?>
                <span class="badge badge-success" style="margin-top:6px;">চলমান / সম্পন্ন</span>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; endif; ?>
    </div>
</div>

<?php /* ═══════════ MODEL TEST ═══════════ */ elseif ($tab === 'modeltest'): ?>

<?php if ($testResult): ?>
<div class="result-card">
    <div class="result-score"><?= $testResult['score'] ?>%</div>
    <div class="result-label">তোমার স্কোর</div>
    <div class="result-stats">
        <div class="result-stat">
            <div class="val"><?= $testResult['total'] ?></div>
            <div class="lbl">মোট প্রশ্ন</div>
        </div>
        <div class="result-stat">
            <div class="val" style="color:#2ecc71;"><?= $testResult['correct'] ?></div>
            <div class="lbl">সঠিক</div>
        </div>
        <div class="result-stat">
            <div class="val" style="color:#e74c3c;"><?= $testResult['wrong'] ?></div>
            <div class="lbl">ভুল</div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <span class="card-title"><i class="fas fa-tasks"></i> মডেল টেস্ট</span>
    </div>
    <div class="card-body">
        <!-- Subject select -->
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:20px;">
            <?php foreach ($subjectList as $sub): ?>
            <a href="?tab=modeltest&subject_id=<?= $sub['id'] ?>"
               class="btn btn-sm <?= $testSubjectId===$sub['id']?'btn-primary':'btn-outline' ?>">
                <?= e($sub['subject_name_bn']) ?>
            </a>
            <?php endforeach; ?>
        </div>

        <?php if ($testSubjectId && empty($testQuestions)): ?>
        <div class="empty-state"><i class="fas fa-question-circle"></i> এই বিষয়ে এখনো প্রশ্ন যোগ করা হয়নি</div>
        <?php elseif (!empty($testQuestions)): ?>
        <form method="POST">
            <input type="hidden" name="submit_test" value="1">
            <input type="hidden" name="subject_id" value="<?= $testSubjectId ?>">
            <?php foreach ($testQuestions as $i => $q): ?>
            <div class="question-card">
                <div class="question-text"><?= ($i+1) ?>. <?= e($q['question']) ?></div>
                <div class="options">
                    <?php foreach (['A'=>$q['option_a'],'B'=>$q['option_b'],'C'=>$q['option_c'],'D'=>$q['option_d']] as $k=>$v): ?>
                    <label class="option-label">
                        <input type="radio" name="answers[<?= $q['id'] ?>]" value="<?= $k ?>" required>
                        <span><strong><?= $k ?>.</strong> <?= e($v) ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
            <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:13px;">
                <i class="fas fa-paper-plane"></i> টেস্ট জমা দিন
            </button>
        </form>
        <?php endif; ?>
    </div>
</div>

<!-- Test history -->
<?php if (!empty($testHistoryData)): ?>
<div class="card">
    <div class="card-header"><span class="card-title"><i class="fas fa-history"></i> আগের টেস্ট ফলাফল</span></div>
    <div class="card-body" style="padding:0;">
        <table>
            <thead><tr><th>বিষয়</th><th>মোট</th><th>সঠিক</th><th>স্কোর</th><th>তারিখ</th></tr></thead>
            <tbody>
                <?php foreach ($testHistoryData as $h): ?>
                <tr>
                    <td><?= e($h['subject_name_bn']) ?></td>
                    <td><?= $h['total_questions'] ?></td>
                    <td style="color:var(--success);font-weight:700;"><?= $h['correct'] ?></td>
                    <td>
                        <span class="badge badge-<?= $h['score']>=80?'success':($h['score']>=50?'warning':'danger') ?>">
                            <?= $h['score'] ?>%
                        </span>
                    </td>
                    <td style="font-size:12px;color:var(--muted);"><?= banglaDate($h['taken_at']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php /* ═══════════ NOTES ═══════════ */ elseif ($tab === 'notes'): ?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
    <h3 style="font-size:16px;font-weight:700;color:var(--primary-dark);"><i class="fas fa-sticky-note"></i> আমার নোট</h3>
    <button onclick="openNoteModal()" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> নতুন নোট</button>
</div>

<?php if (empty($notesData)): ?>
<div class="card"><div class="card-body"><div class="empty-state"><i class="fas fa-sticky-note"></i> কোনো নোট নেই — নতুন নোট লিখুন!</div></div></div>
<?php else: foreach ($notesData as $note): ?>
<div class="note-card">
    <div class="note-title"><?= e($note['title']) ?></div>
    <?php if ($note['subject_name_bn']): ?>
    <span class="badge badge-primary" style="font-size:11px;margin-bottom:8px;"><?= e($note['subject_name_bn']) ?></span>
    <?php endif; ?>
    <div class="note-content"><?= e($note['content']) ?></div>
    <div class="note-meta">
        <span><?= banglaDate($note['updated_at']) ?></span>
        <div style="display:flex;gap:6px;">
            <button onclick="editNote(<?= htmlspecialchars(json_encode($note)) ?>)" class="btn btn-outline btn-sm"><i class="fas fa-edit"></i></button>
            <form method="POST" style="display:inline;" onsubmit="return confirm('নোট মুছবেন?')">
                <input type="hidden" name="delete_note" value="1">
                <input type="hidden" name="note_id" value="<?= $note['id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
            </form>
        </div>
    </div>
</div>
<?php endforeach; endif; ?>

<!-- Note Modal -->
<div class="modal-overlay" id="noteModal">
    <div class="modal">
        <h3 id="modalTitle"><i class="fas fa-sticky-note"></i> নতুন নোট</h3>
        <form method="POST">
            <input type="hidden" name="save_note" value="1">
            <input type="hidden" name="note_id" id="noteId">
            <div class="form-group">
                <label>শিরোনাম</label>
                <input type="text" name="note_title" id="noteTitle" class="form-control" placeholder="নোটের শিরোনাম" required>
            </div>
            <div class="form-group">
                <label>বিষয় (ঐচ্ছিক)</label>
                <select name="note_subject_id" id="noteSubject" class="form-control">
                    <option value="">বিষয় নির্বাচন করুন</option>
                    <?php foreach ($subjectList as $sub): ?>
                    <option value="<?= $sub['id'] ?>"><?= e($sub['subject_name_bn']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>নোট লিখুন</label>
                <textarea name="note_content" id="noteContent" class="form-control" rows="5" placeholder="এখানে নোট লিখুন..." required></textarea>
            </div>
            <div style="display:flex;gap:8px;justify-content:flex-end;">
                <button type="button" onclick="closeNoteModal()" class="btn btn-outline">বাতিল</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> সংরক্ষণ</button>
            </div>
        </form>
    </div>
</div>

<?php /* ═══════════ MESSAGES ═══════════ */ elseif ($tab === 'messages'): ?>
<div class="card">
    <div class="card-header">
        <span class="card-title"><i class="fas fa-comments"></i> শিক্ষকের সাথে বার্তা</span>
    </div>
    <div class="card-body">
        <div class="msg-area" id="msgArea">
            <?php if (empty($messagesData)): ?>
            <div class="empty-state"><i class="fas fa-comment-dots"></i> কোনো বার্তা নেই</div>
            <?php else: foreach ($messagesData as $m): ?>
            <div class="msg-bubble <?= $m['sender']==='student'?'sent':'received' ?>">
                <div>
                    <?php if ($m['sender']==='teacher' && $m['teacher_name_bn']): ?>
                    <div class="teacher-name"><?= e($m['teacher_name_bn']) ?></div>
                    <?php endif; ?>
                    <div class="bubble">
                        <?= nl2br(e($m['message'])) ?>
                        <div class="bubble-meta"><?= banglaDate($m['created_at']) ?></div>
                    </div>
                </div>
            </div>
            <?php endforeach; endif; ?>
        </div>
        <form method="POST">
            <input type="hidden" name="send_message" value="1">
            <div class="form-group">
                <label>শিক্ষক নির্বাচন করুন</label>
                <select name="teacher_id" class="form-control">
                    <option value="">সকল শিক্ষক</option>
                    <?php foreach ($teachers as $t): ?>
                    <option value="<?= $t['id'] ?>"><?= e($t['name_bn'] ?? $t['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display:flex;gap:8px;">
                <textarea name="message" class="form-control" rows="2" placeholder="বার্তা লিখুন..." required style="flex:1;"></textarea>
                <button type="submit" class="btn btn-primary" style="align-self:flex-end;padding:10px 16px;">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

</div><!-- /content -->

<script>
// Auto-scroll messages
const msgArea = document.getElementById('msgArea');
if (msgArea) msgArea.scrollTop = msgArea.scrollHeight;

// Note modal
function openNoteModal() {
    document.getElementById('noteId').value = '';
    document.getElementById('noteTitle').value = '';
    document.getElementById('noteContent').value = '';
    document.getElementById('noteSubject').value = '';
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-sticky-note"></i> নতুন নোট';
    document.getElementById('noteModal').classList.add('open');
}
function editNote(note) {
    document.getElementById('noteId').value = note.id;
    document.getElementById('noteTitle').value = note.title;
    document.getElementById('noteContent').value = note.content;
    document.getElementById('noteSubject').value = note.subject_id || '';
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit"></i> নোট সম্পাদনা';
    document.getElementById('noteModal').classList.add('open');
}
function closeNoteModal() {
    document.getElementById('noteModal').classList.remove('open');
}
document.getElementById('noteModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeNoteModal();
});
</script>
</body>
</html>
