<?php
require_once '../../includes/functions.php';
requireLogin(['super_admin','principal','teacher']);
$pageTitle = 'ক্লাস ডাইরি';
$db = getDB();
$currentUser = getCurrentUser();
$userId = $_SESSION['user_id'];

// Teacher info
$teacher = $db->prepare("SELECT * FROM teachers WHERE user_id=?");
$teacher->execute([$userId]);
$teacher = $teacher->fetch();

$divisionId = (int)(\$_GET['division_id'] ?? 0);
\$divisions  = \$db->query("SELECT * FROM divisions WHERE is_active=1 ORDER BY sort_order, id")->fetchAll();
if (\$divisionId) {
    \$clsStmt = \$db->prepare("SELECT c.*, d.division_name_bn FROM classes c LEFT JOIN divisions d ON c.division_id=d.id WHERE c.is_active=1 AND c.division_id=? ORDER BY c.class_numeric");
    \$clsStmt->execute([\$divisionId]);
    \$classes = \$clsStmt->fetchAll();
} else {
    \$classes = \$db->query("SELECT c.*, d.division_name_bn FROM classes c LEFT JOIN divisions d ON c.division_id=d.id WHERE c.is_active=1 ORDER BY d.sort_order, c.class_numeric")->fetchAll();
}
\$subjects = $db->query("SELECT * FROM subjects WHERE is_active=1 ORDER BY subject_name_bn")->fetchAll();

// Ensure diary table exists
$db->exec("CREATE TABLE IF NOT EXISTS class_diary (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT,
    class_id INT NOT NULL,
    subject_id INT,
    diary_date DATE NOT NULL,
    topic VARCHAR(255),
    topic_bn VARCHAR(255),
    description TEXT,
    homework TEXT,
    next_topic VARCHAR(255),
    lesson_status ENUM('completed','partial','not_started') DEFAULT 'completed',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES classes(id),
    FOREIGN KEY (subject_id) REFERENCES subjects(id)
)");

// Save diary
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save_diary'])) {
    if (!verifyCsrf($_POST['csrf']??'')) die('CSRF');
    $classId   = (int)$_POST['class_id'];
    $subjectId = (int)($_POST['subject_id']??0) ?: null;
    $date      = $_POST['diary_date'] ?? date('Y-m-d');
    $topic     = trim($_POST['topic']??'');
    $topicBn   = trim($_POST['topic_bn']??'');
    $desc      = trim($_POST['description']??'');
    $homework  = trim($_POST['homework']??'');
    $nextTopic = trim($_POST['next_topic']??'');
    $status    = $_POST['lesson_status']??'completed';
    $teacherId = $teacher['id'] ?? null; // admin হলে null থাকবে

    $stmt = $db->prepare("INSERT INTO class_diary
        (teacher_id,class_id,subject_id,diary_date,topic,topic_bn,description,homework,next_topic,lesson_status,created_by)
        VALUES (?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute([$teacherId,$classId,$subjectId,$date,$topic,$topicBn,$desc,$homework,$nextTopic,$status,$userId]);
    setFlash('success','ডাইরি সফলভাবে সংরক্ষিত হয়েছে।');
    header('Location: diary.php'); exit;
}

// Delete
if (isset($_GET['delete']) && in_array($_SESSION['role_slug'],['super_admin','principal'])) {
    $db->prepare("DELETE FROM class_diary WHERE id=?")->execute([(int)$_GET['delete']]);
    setFlash('success','ডাইরি মুছে ফেলা হয়েছে।');
    header('Location: diary.php'); exit;
}

// Save student evaluations
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save_evaluation'])) {
    if (!verifyCsrf($_POST['csrf']??'')) die('CSRF');
    $diaryId   = (int)$_POST['diary_id'];
    $classId   = (int)$_POST['eval_class_id'];
    $subjectId = (int)($_POST['eval_subject_id']??0) ?: null;
    $evalDate  = $_POST['eval_date'] ?? date('Y-m-d');
    $teacherId = $teacher['id'] ?? null;
    $evals     = $_POST['evals'] ?? [];
    $inattentive = $_POST['inattentive'] ?? [];
    $notes     = $_POST['notes'] ?? [];

    foreach ($evals as $studentId => $data) {
        $studentId     = (int)$studentId;
        $lessonStatus  = $data['lesson'] ?? 'learned';
        $hwStatus      = $data['homework'] ?? 'na';
        $isInattentive = isset($inattentive[$studentId]) ? 1 : 0;
        $note          = trim($notes[$studentId] ?? '');

        $db->prepare("INSERT INTO student_evaluations
            (diary_id, student_id, teacher_id, class_id, subject_id, eval_date, lesson_status, homework_status, is_inattentive, note)
            VALUES (?,?,?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE lesson_status=VALUES(lesson_status), homework_status=VALUES(homework_status),
            is_inattentive=VALUES(is_inattentive), note=VALUES(note)")
           ->execute([$diaryId, $studentId, $teacherId, $classId, $subjectId, $evalDate, $lessonStatus, $hwStatus, $isInattentive, $note]);

        // Parent notification if inattentive
        if ($isInattentive) {
            $stInfo = $db->prepare("SELECT name_bn, name FROM students WHERE id=?");
            $stInfo->execute([$studentId]);
            $st = $stInfo->fetch();
            $subName = $subjectId ? $db->query("SELECT subject_name_bn FROM subjects WHERE id=$subjectId")->fetchColumn() : 'অজানা বিষয়';
            $tName   = $teacherId ? $db->query("SELECT name_bn FROM teachers WHERE id=$teacherId")->fetchColumn() : 'শিক্ষক';
            $msg = ($st['name_bn']??$st['name']) . " ($evalDate) তারিখে \"$subName\" বিষয়ে অমনোযোগী ছিল। দয়া করে $tName শিক্ষকের সাথে যোগাযোগ করুন।";

            $alreadyNotified = $db->prepare("SELECT id FROM parent_notifications WHERE student_id=? AND subject_id=? AND DATE(created_at)=?");
            $alreadyNotified->execute([$studentId, $subjectId, $evalDate]);
            if (!$alreadyNotified->fetch()) {
                $db->prepare("INSERT INTO parent_notifications (student_id, teacher_id, subject_id, type, message) VALUES (?,?,?,'inattentive',?)")
                   ->execute([$studentId, $teacherId, $subjectId, $msg]);
            }
        }
    }
    setFlash('success','মূল্যায়ন সফলভাবে সংরক্ষিত হয়েছে।');
    header('Location: diary.php'); exit;
}

// Filter
$filterClass   = (int)($_GET['class_id']??0);
$filterDate    = $_GET['date']??'';
$filterTeacher = (int)($_GET['teacher_id']??0);
$page = max(1,(int)($_GET['page']??1));
$perPage = 15;
$offset = ($page-1)*$perPage;

$where = ['1=1']; $params = [];
if ($divisionId)    { $where[] = 'c.division_id=?'; $params[] = $divisionId; }
if ($filterClass)   { $where[] = 'cd.class_id=?';   $params[] = $filterClass; }
if ($filterDate)    { $where[] = 'cd.diary_date=?';        $params[] = $filterDate; }
if ($filterTeacher) { $where[] = 'cd.teacher_id=?';  $params[] = $filterTeacher; }
// Teachers see only their own
if ($_SESSION['role_slug']==='teacher' && $teacher) {
    $where[] = 'cd.teacher_id=?'; $params[] = $teacher['id'];
}
$whereStr = implode(' AND ', $where);

$total = $db->prepare("SELECT COUNT(*) FROM class_diary cd LEFT JOIN classes c ON cd.class_id=c.id WHERE $whereStr");
$total->execute($params); $total = $total->fetchColumn();

$stmt = $db->prepare("SELECT cd.*, c.class_name_bn, s.subject_name_bn, t.name_bn as teacher_name, d.division_name_bn
    FROM class_diary cd
    LEFT JOIN classes c ON cd.class_id=c.id
    LEFT JOIN divisions d ON c.division_id=d.id
    LEFT JOIN subjects s ON cd.subject_id=s.id
    LEFT JOIN teachers t ON cd.teacher_id=t.id
    WHERE $whereStr ORDER BY cd.diary_date DESC, cd.created_at DESC
    LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$diaries = $stmt->fetchAll();

$teachers = $db->query("SELECT t.id, t.name_bn FROM teachers t WHERE t.is_active=1 ORDER BY t.name_bn")->fetchAll();

require_once ($_SESSION['role_slug']==='teacher') ? '../../includes/teacher_header.php' : '../../includes/header.php';
?>

<div class="section-header">
    <h2 class="section-title"><i class="fas fa-book-open"></i> ক্লাস ডাইরি</h2>
    <button onclick="openModal('addDiaryModal')" class="btn btn-primary btn-sm">
        <i class="fas fa-plus"></i> নতুন এন্ট্রি
    </button>
</div>

<!-- Filter -->
<div class="card mb-16 no-print">
    <div class="card-body" style="padding:12px 20px;">
        <form method="GET" id="diaryFilter" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">
            <input type="hidden" name="division_id" id="hiddenDivId" value="<?=$divisionId?>">
            <div class="form-group" style="margin:0;flex:1;min-width:130px;">
                <label style="font-size:12px;font-weight:600;">বিভাগ</label>
                <select class="form-control" style="padding:7px;" onchange="onDivChange(this.value)">
                    <option value="">সব বিভাগ</option>
                    <?php foreach($divisions as $dv): ?>
                    <option value="<?=$dv['id']?>" <?=$divisionId==$dv['id']?'selected':''?>><?=e($dv['division_name_bn'])?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0;flex:1;min-width:140px;">
                <label style="font-size:12px;">শ্রেণী</label>
                <select name="class_id" class="form-control" style="padding:7px;" onchange="this.form.submit()">
                    <option value="">সব শ্রেণী</option>
                    <?php foreach($classes as $c): ?>
                    <option value="<?=$c['id']?>" <?=$filterClass==$c['id']?'selected':''?>>
                        <?php if(!$divisionId): ?><?=e($c['division_name_bn']??'')?> → <?php endif; ?>
                        <?=e($c['class_name_bn'])?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0;flex:1;min-width:140px;">
                <label style="font-size:12px;">তারিখ</label>
                <input type="date" name="date" class="form-control" style="padding:7px;" value="<?=e($filterDate)?>" onchange="this.form.submit()">
            </div>
            <?php if(in_array($_SESSION['role_slug'],['super_admin','principal'])): ?>
            <div class="form-group" style="margin:0;flex:1;min-width:140px;">
                <label style="font-size:12px;">শিক্ষক</label>
                <select name="teacher_id" class="form-control" style="padding:7px;" onchange="this.form.submit()">
                    <option value="">সব শিক্ষক</option>
                    <?php foreach($teachers as $t): ?>
                    <option value="<?=$t['id']?>" <?=$filterTeacher==$t['id']?'selected':''?>><?=e($t['name_bn'])?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <a href="diary.php" class="btn btn-outline btn-sm">রিসেট</a>
        </form>
    </div>
</div>

<!-- Diary List -->
<div class="card">
    <div class="card-header">
        <span class="card-title">মোট <?=toBanglaNumber($total)?> এন্ট্রি</span>
        <button onclick="window.print()" class="btn btn-outline btn-sm no-print"><i class="fas fa-print"></i></button>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>তারিখ</th><th>শ্রেণী</th><th>বিষয়</th><th>আলোচিত বিষয়</th><th>হোমওয়ার্ক</th><th>শিক্ষক</th><th>অবস্থা</th><th class="no-print">অ্যাকশন</th></tr>
            </thead>
            <tbody>
                <?php if(empty($diaries)): ?>
                <tr><td colspan="8" style="text-align:center;padding:30px;color:var(--text-muted);">কোনো ডাইরি এন্ট্রি নেই</td></tr>
                <?php else: foreach($diaries as $d): ?>
                <tr>
                    <td style="font-size:13px;white-space:nowrap;"><?=banglaDate($d['date'])?></td>
                    <td><span class="badge badge-primary" style="font-size:11px;"><?=e($d['class_name_bn']??'')?></span></td>
                    <td style="font-size:13px;"><?=e($d['subject_name_bn']??'-')?></td>
                    <td>
                        <div style="font-weight:600;font-size:13px;"><?=e($d['topic_bn']??$d['topic']??'')?></div>
                        <?php if($d['description']): ?>
                        <div style="font-size:11px;color:var(--text-muted);margin-top:2px;"><?=e(mb_substr($d['description'],0,60))?>...</div>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:12px;max-width:150px;"><?=e(mb_substr($d['homework']??'',0,50))?><?=strlen($d['homework']??'')>50?'...':''?></td>
                    <td style="font-size:13px;"><?=e($d['teacher_name']??'-')?></td>
                    <td>
                        <span class="badge badge-<?=['completed'=>'success','partial'=>'warning','not_started'=>'danger'][$d['lesson_status']]??'secondary'?>">
                            <?=['completed'=>'সম্পন্ন','partial'=>'আংশিক','not_started'=>'শুরু হয়নি'][$d['lesson_status']]??''?>
                        </span>
                    </td>
                    <td class="no-print">
                        <button onclick="viewDiary(<?=json_encode($d)?>)" class="btn btn-info btn-xs"><i class="fas fa-eye"></i></button>
                        <button onclick="openEvalModal(<?=$d['id']?>,<?=$d['class_id']?>,<?=$d['subject_id']??0?>,'<?=e($d['diary_date'])?>','<?=e($d['subject_name_bn']??'')?>','<?=e($d['class_name_bn']??'')?>')" class="btn btn-warning btn-xs" title="ছাত্র মূল্যায়ন"><i class="fas fa-clipboard-check"></i></button>
                        <?php if(in_array($_SESSION['role_slug'],['super_admin','principal'])): ?>
                        <a href="?delete=<?=$d['id']?>" onclick="return confirm('মুছবেন?')" class="btn btn-danger btn-xs"><i class="fas fa-trash"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php if($total > $perPage): ?>
    <div class="card-footer no-print"><?=paginate($total,$perPage,$page,'diary.php?class_id='.$filterClass.'&date='.urlencode($filterDate))?></div>
    <?php endif; ?>
</div>

<!-- Add Diary Modal -->
<div class="modal-overlay" id="addDiaryModal">
    <div class="modal-box" style="max-width:680px;">
        <div class="modal-header">
            <span style="font-weight:700;"><i class="fas fa-book-open"></i> নতুন ডাইরি এন্ট্রি</span>
            <button onclick="closeModal('addDiaryModal')" class="btn btn-outline btn-xs">✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="csrf" value="<?=getCsrfToken()?>">
            <input type="hidden" name="save_diary" value="1">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label>তারিখ <span style="color:red;">*</span></label>
                        <input type="date" name="diary_date" class="form-control" value="<?=date('Y-m-d')?>" required>
                    </div>
                    <div class="form-group">
                        <label>শ্রেণী <span style="color:red;">*</span></label>
                        <select name="class_id" class="form-control" required>
                            <option value="">শ্রেণী নির্বাচন করুন</option>
                            <?php foreach($classes as $c): ?>
                            <option value="<?=$c['id']?>"><?=e($c['class_name_bn'])?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>বিষয়</label>
                        <select name="subject_id" class="form-control">
                            <option value="">বিষয় নির্বাচন করুন</option>
                            <?php foreach($subjects as $s): ?>
                            <option value="<?=$s['id']?>"><?=e($s['subject_name_bn'])?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>পাঠের অবস্থা</label>
                        <select name="lesson_status" class="form-control">
                            <option value="completed">সম্পন্ন হয়েছে</option>
                            <option value="partial">আংশিক সম্পন্ন</option>
                            <option value="not_started">শুরু হয়নি</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>আলোচিত বিষয় (বাংলায়) <span style="color:red;">*</span></label>
                        <input type="text" name="topic_bn" class="form-control" placeholder="আজকের পাঠের শিরোনাম" required>
                    </div>
                    <div class="form-group">
                        <label>Topic (English)</label>
                        <input type="text" name="topic" class="form-control" placeholder="Today's lesson topic">
                    </div>
                    <div class="form-group" style="grid-column:1/-1;">
                        <label>বিস্তারিত বিবরণ</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="আজকের ক্লাসে কী কী পড়ানো হয়েছে..."></textarea>
                    </div>
                    <div class="form-group" style="grid-column:1/-1;">
                        <label>হোমওয়ার্ক / বাড়ির কাজ</label>
                        <textarea name="homework" class="form-control" rows="2" placeholder="ছাত্রদের জন্য বাড়ির কাজ..."></textarea>
                    </div>
                    <div class="form-group" style="grid-column:1/-1;">
                        <label>পরবর্তী পাঠ</label>
                        <input type="text" name="next_topic" class="form-control" placeholder="পরের ক্লাসে কী পড়ানো হবে...">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="closeModal('addDiaryModal')" class="btn btn-outline">বাতিল</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> সংরক্ষণ করুন</button>
            </div>
        </form>
    </div>
</div>

<!-- View Diary Modal -->
<div class="modal-overlay" id="viewDiaryModal">
    <div class="modal-box" style="max-width:600px;">
        <div class="modal-header">
            <span style="font-weight:700;" id="viewTitle">ডাইরি বিস্তারিত</span>
            <button onclick="closeModal('viewDiaryModal')" class="btn btn-outline btn-xs">✕</button>
        </div>
        <div class="modal-body" id="viewContent"></div>
    </div>
</div>

<!-- Evaluation Modal -->
<div class="modal-overlay" id="evalModal">
    <div class="modal-box" style="max-width:800px;max-height:90vh;overflow-y:auto;">
        <div class="modal-header">
            <span style="font-weight:700;"><i class="fas fa-clipboard-check"></i> ছাত্র মূল্যায়ন — <span id="evalTitle"></span></span>
            <button onclick="closeModal('evalModal')" class="btn btn-outline btn-xs">✕</button>
        </div>
        <form method="POST" id="evalForm">
            <input type="hidden" name="csrf" value="<?=getCsrfToken()?>">
            <input type="hidden" name="save_evaluation" value="1">
            <input type="hidden" name="diary_id" id="evalDiaryId">
            <input type="hidden" name="eval_class_id" id="evalClassId">
            <input type="hidden" name="eval_subject_id" id="evalSubjectId">
            <input type="hidden" name="eval_date" id="evalDate">
            <div class="modal-body">
                <div id="evalStudentList">
                    <div style="text-align:center;padding:20px;color:#718096;"><i class="fas fa-spinner fa-spin"></i> ছাত্র তালিকা লোড হচ্ছে...</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="closeModal('evalModal')" class="btn btn-outline">বাতিল</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> মূল্যায়ন সংরক্ষণ</button>
            </div>
        </form>
    </div>
</div>

<style>
.eval-table { width:100%; border-collapse:collapse; font-size:13px; }
.eval-table th { background:#1a5276; color:#fff; padding:9px 10px; text-align:center; font-size:12px; }
.eval-table td { padding:8px 10px; border-bottom:1px solid #e2e8f0; vertical-align:middle; }
.eval-table tr:hover { background:#f7fafc; }
.eval-radio { display:flex; gap:6px; justify-content:center; flex-wrap:wrap; }
.eval-radio label {
    display:flex; align-items:center; gap:4px; padding:4px 8px;
    border:1.5px solid #e2e8f0; border-radius:20px; cursor:pointer;
    font-size:11px; font-weight:600; transition:.15s; white-space:nowrap;
}
.eval-radio label:hover { border-color:#1a5276; }
.eval-radio input[type=radio] { display:none; }
.eval-radio input[type=radio]:checked + span { font-weight:700; }
.label-learned   { border-color:#27ae60!important; color:#27ae60; }
.label-partial   { border-color:#f39c12!important; color:#f39c12; }
.label-not       { border-color:#e74c3c!important; color:#e74c3c; }
.label-hw-done   { border-color:#2980b9!important; color:#2980b9; }
.label-hw-not    { border-color:#e74c3c!important; color:#e74c3c; }
.label-hw-na     { border-color:#95a5a6!important; color:#95a5a6; }
.inattentive-cb  { width:18px; height:18px; cursor:pointer; accent-color:#e74c3c; }
</style>

<script>
function viewDiary(d) {
    document.getElementById('viewTitle').textContent = d.topic_bn || d.topic || 'ডাইরি বিস্তারিত';
    document.getElementById('viewContent').innerHTML = `
        <div style="display:grid;gap:12px;">
            <div style="display:flex;gap:12px;flex-wrap:wrap;">
                <span class="badge badge-primary">${d.class_name_bn||''}</span>
                <span class="badge badge-info">${d.subject_name_bn||''}</span>
                <span class="badge badge-secondary">${d.diary_date||''}</span>
            </div>
            ${d.description ? `<div><strong>বিবরণ:</strong><p style="margin-top:6px;font-size:14px;line-height:1.6;">${d.description}</p></div>` : ''}
            ${d.homework ? `<div style="background:#fff3cd;border-radius:8px;padding:12px;"><strong>🏠 বাড়ির কাজ:</strong><p style="margin-top:6px;font-size:14px;">${d.homework}</p></div>` : ''}
            ${d.next_topic ? `<div style="background:#d1ecf1;border-radius:8px;padding:12px;"><strong>➡️ পরবর্তী পাঠ:</strong> ${d.next_topic}</div>` : ''}
            <div style="font-size:12px;color:#718096;">শিক্ষক: ${d.teacher_name||'অজানা'}</div>
        </div>`;
    openModal('viewDiaryModal');
}

function onDivChange(divId) {
    document.getElementById('hiddenDivId').value = divId;
    document.querySelector('select[name="class_id"]').value = '';
    document.getElementById('diaryFilter').submit();
}
function openEvalModal(diaryId, classId, subjectId, date, subjectName, className) {
    document.getElementById('evalDiaryId').value    = diaryId;
    document.getElementById('evalClassId').value    = classId;
    document.getElementById('evalSubjectId').value  = subjectId;
    document.getElementById('evalDate').value       = date;
    document.getElementById('evalTitle').textContent = className + (subjectName ? ' — ' + subjectName : '') + ' (' + date + ')';

    // Load students via AJAX
    const listDiv = document.getElementById('evalStudentList');
    listDiv.innerHTML = '<div style="text-align:center;padding:20px;"><i class="fas fa-spinner fa-spin"></i> লোড হচ্ছে...</div>';
    openModal('evalModal');

    fetch(`<?= BASE_URL ?>/api/students_by_class.php?class_id=${classId}&diary_id=${diaryId}`)
        .then(r => r.json())
        .then(students => {
            if (!students.length) {
                listDiv.innerHTML = '<div style="text-align:center;padding:20px;color:#718096;">এই শ্রেণীতে কোনো ছাত্র নেই</div>';
                return;
            }
            let html = `<table class="eval-table">
                <thead><tr>
                    <th style="text-align:left;width:30px;">#</th>
                    <th style="text-align:left;">ছাত্রের নাম</th>
                    <th>পড়া শিখেছে?</th>
                    <th>বাড়ির কাজ?</th>
                    <th>অমনোযোগী?</th>
                    <th>মন্তব্য</th>
                </tr></thead><tbody>`;

            students.forEach((s, i) => {
                const ls = s.lesson_status || 'learned';
                const hs = s.homework_status || 'na';
                const ia = s.is_inattentive == 1;
                const nt = s.note || '';
                html += `<tr>
                    <td style="color:#718096;">${i+1}</td>
                    <td>
                        <div style="font-weight:600;">${s.name_bn||s.name}</div>
                        <div style="font-size:11px;color:#718096;">রোল: ${s.roll_number||''}</div>
                    </td>
                    <td>
                        <div class="eval-radio">
                            <label class="${ls==='learned'?'label-learned':''}">
                                <input type="radio" name="evals[${s.id}][lesson]" value="learned" ${ls==='learned'?'checked':''}>
                                <span>✅ শিখেছে</span>
                            </label>
                            <label class="${ls==='partial'?'label-partial':''}">
                                <input type="radio" name="evals[${s.id}][lesson]" value="partial" ${ls==='partial'?'checked':''}>
                                <span>🟡 আংশিক</span>
                            </label>
                            <label class="${ls==='not_learned'?'label-not':''}">
                                <input type="radio" name="evals[${s.id}][lesson]" value="not_learned" ${ls==='not_learned'?'checked':''}>
                                <span>❌ শিখেনি</span>
                            </label>
                        </div>
                    </td>
                    <td>
                        <div class="eval-radio">
                            <label class="${hs==='done'?'label-hw-done':''}">
                                <input type="radio" name="evals[${s.id}][homework]" value="done" ${hs==='done'?'checked':''}>
                                <span>✅ করেছে</span>
                            </label>
                            <label class="${hs==='not_done'?'label-hw-not':''}">
                                <input type="radio" name="evals[${s.id}][homework]" value="not_done" ${hs==='not_done'?'checked':''}>
                                <span>❌ করেনি</span>
                            </label>
                            <label class="${hs==='na'?'label-hw-na':''}">
                                <input type="radio" name="evals[${s.id}][homework]" value="na" ${hs==='na'?'checked':''}>
                                <span>প্রযোজ্য নয়</span>
                            </label>
                        </div>
                    </td>
                    <td style="text-align:center;">
                        <input type="checkbox" class="inattentive-cb" name="inattentive[${s.id}]" value="1" ${ia?'checked':''} title="অমনোযোগী হলে টিক দিন — অভিভাবককে notify করবে">
                    </td>
                    <td>
                        <input type="text" name="notes[${s.id}]" class="form-control" style="padding:5px 8px;font-size:12px;" placeholder="মন্তব্য..." value="${nt}">
                    </td>
                </tr>`;
            });
            html += '</tbody></table>';
            listDiv.innerHTML = html;

            // Radio label highlight on change
            listDiv.querySelectorAll('.eval-radio input[type=radio]').forEach(radio => {
                radio.addEventListener('change', function() {
                    this.closest('.eval-radio').querySelectorAll('label').forEach(l => {
                        l.className = l.className.replace(/label-\S+/g,'').trim();
                    });
                    const map = {learned:'label-learned',partial:'label-partial',not_learned:'label-not',done:'label-hw-done',not_done:'label-hw-not',na:'label-hw-na'};
                    if (map[this.value]) this.parentElement.classList.add(map[this.value]);
                });
            });
        })
        .catch(() => {
            listDiv.innerHTML = '<div style="text-align:center;padding:20px;color:#e74c3c;">ছাত্র তালিকা লোড করতে সমস্যা হয়েছে।</div>';
        });
}
</script>

<?php require_once '../../includes/footer.php'; ?>
