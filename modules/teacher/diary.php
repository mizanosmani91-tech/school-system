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

$classes = $db->query("SELECT * FROM classes WHERE is_active=1 ORDER BY class_numeric")->fetchAll();
$subjects = $db->query("SELECT * FROM subjects WHERE is_active=1 ORDER BY subject_name_bn")->fetchAll();

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

// Filter
$filterClass   = (int)($_GET['class_id']??0);
$filterDate    = $_GET['date']??'';
$filterTeacher = (int)($_GET['teacher_id']??0);
$page = max(1,(int)($_GET['page']??1));
$perPage = 15;
$offset = ($page-1)*$perPage;

$where = ['1=1']; $params = [];
if ($filterClass)   { $where[] = 'cd.class_id=?';   $params[] = $filterClass; }
if ($filterDate)    { $where[] = 'cd.diary_date=?';        $params[] = $filterDate; }
if ($filterTeacher) { $where[] = 'cd.teacher_id=?';  $params[] = $filterTeacher; }
// Teachers see only their own
if ($_SESSION['role_slug']==='teacher' && $teacher) {
    $where[] = 'cd.teacher_id=?'; $params[] = $teacher['id'];
}
$whereStr = implode(' AND ', $where);

$total = $db->prepare("SELECT COUNT(*) FROM class_diary cd WHERE $whereStr");
$total->execute($params); $total = $total->fetchColumn();

$stmt = $db->prepare("SELECT cd.*, c.class_name_bn, s.subject_name_bn, t.name_bn as teacher_name
    FROM class_diary cd
    LEFT JOIN classes c ON cd.class_id=c.id
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
        <form method="GET" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">
            <div class="form-group" style="margin:0;flex:1;min-width:140px;">
                <label style="font-size:12px;">শ্রেণী</label>
                <select name="class_id" class="form-control" style="padding:7px;" onchange="this.form.submit()">
                    <option value="">সব শ্রেণী</option>
                    <?php foreach($classes as $c): ?>
                    <option value="<?=$c['id']?>" <?=$filterClass==$c['id']?'selected':''?>><?=e($c['class_name_bn'])?></option>
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
</script>

<?php require_once '../../includes/footer.php'; ?>
