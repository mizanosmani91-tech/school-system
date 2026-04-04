<?php
require_once '../../includes/functions.php';
requireLogin();
$pageTitle = 'সিলেবাস ব্যবস্থাপনা';
$db = getDB();

$classes  = $db->query("SELECT * FROM classes WHERE is_active=1 ORDER BY class_numeric")->fetchAll();
$subjects = $db->query("SELECT * FROM subjects WHERE is_active=1 ORDER BY subject_name_bn")->fetchAll();

// Ensure syllabus table exists
$db->exec("CREATE TABLE IF NOT EXISTS syllabus (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    subject_id INT NOT NULL,
    academic_year VARCHAR(10) DEFAULT '2025',
    chapter_no INT,
    chapter_name VARCHAR(255),
    chapter_name_bn VARCHAR(255),
    topics TEXT,
    month VARCHAR(20),
    is_completed TINYINT(1) DEFAULT 0,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES classes(id),
    FOREIGN KEY (subject_id) REFERENCES subjects(id)
)");

// Save syllabus
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save_syllabus'])) {
    if (!verifyCsrf($_POST['csrf']??'')) die('CSRF');
    $classId    = (int)$_POST['class_id'];
    $subjectId  = (int)$_POST['subject_id'];
    $chapterNo  = (int)($_POST['chapter_no']??0) ?: null;
    $chapterBn  = trim($_POST['chapter_name_bn']??'');
    $chapter    = trim($_POST['chapter_name']??'') ?: $chapterBn;
    $topics     = trim($_POST['topics']??'');
    $month      = trim($_POST['month']??'');
    $year       = date('Y');

    $db->prepare("INSERT INTO syllabus (class_id,subject_id,academic_year,chapter_no,chapter_name,chapter_name_bn,topics,month,created_by)
        VALUES (?,?,?,?,?,?,?,?,?)")
       ->execute([$classId,$subjectId,$year,$chapterNo,$chapter,$chapterBn,$topics,$month,$_SESSION['user_id']]);
    setFlash('success','সিলেবাস সংরক্ষিত হয়েছে।');
    header('Location: index.php?class_id='.$classId.'&subject_id='.$subjectId); exit;
}

// Mark complete
if (isset($_GET['toggle']) && in_array($_SESSION['role_slug'],['super_admin','principal','teacher'])) {
    $row = $db->prepare("SELECT is_completed FROM syllabus WHERE id=?");
    $row->execute([(int)$_GET['toggle']]); $row = $row->fetch();
    if ($row) {
        $db->prepare("UPDATE syllabus SET is_completed=? WHERE id=?")->execute([!$row['is_completed'],(int)$_GET['toggle']]);
    }
    header('Location: '.$_SERVER['HTTP_REFERER']); exit;
}

// Delete
if (isset($_GET['delete']) && in_array($_SESSION['role_slug'],['super_admin','principal'])) {
    $db->prepare("DELETE FROM syllabus WHERE id=?")->execute([(int)$_GET['delete']]);
    setFlash('success','মুছে ফেলা হয়েছে।');
    header('Location: index.php'); exit;
}

$filterClass   = (int)($_GET['class_id']??0);
$filterSubject = (int)($_GET['subject_id']??0);
$syllabus = [];
$progress = 0;

if ($filterClass && $filterSubject) {
    $stmt = $db->prepare("SELECT * FROM syllabus WHERE class_id=? AND subject_id=? AND academic_year=? ORDER BY chapter_no, id");
    $stmt->execute([$filterClass,$filterSubject,date('Y')]);
    $syllabus = $stmt->fetchAll();
    if (!empty($syllabus)) {
        $completed = count(array_filter($syllabus, fn($r)=>$r['is_completed']));
        $progress = round(($completed/count($syllabus))*100);
    }
}

$months = ['জানুয়ারি','ফেব্রুয়ারি','মার্চ','এপ্রিল','মে','জুন','জুলাই','আগস্ট','সেপ্টেম্বর','অক্টোবর','নভেম্বর','ডিসেম্বর'];

require_once '../../includes/header.php';
?>

<div class="section-header">
    <h2 class="section-title"><i class="fas fa-list-alt"></i> সিলেবাস ব্যবস্থাপনা</h2>
    <?php if(in_array($_SESSION['role_slug'],['super_admin','principal','teacher'])): ?>
    <button onclick="openModal('addSyllabusModal')" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> অধ্যায় যোগ করুন</button>
    <?php endif; ?>
</div>

<!-- Filter -->
<div class="card mb-16">
    <div class="card-body" style="padding:12px 20px;">
        <form method="GET" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">
            <div class="form-group" style="margin:0;flex:1;min-width:160px;">
                <label style="font-size:12px;">শ্রেণী</label>
                <select name="class_id" class="form-control" style="padding:7px;" onchange="this.form.submit()">
                    <option value="">শ্রেণী নির্বাচন</option>
                    <?php foreach($classes as $c): ?>
                    <option value="<?=$c['id']?>" <?=$filterClass==$c['id']?'selected':''?>><?=e($c['class_name_bn'])?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin:0;flex:1;min-width:160px;">
                <label style="font-size:12px;">বিষয়</label>
                <select name="subject_id" class="form-control" style="padding:7px;" onchange="this.form.submit()">
                    <option value="">বিষয় নির্বাচন</option>
                    <?php foreach($subjects as $s): ?>
                    <option value="<?=$s['id']?>" <?=$filterSubject==$s['id']?'selected':''?>><?=e($s['subject_name_bn'])?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button onclick="window.print()" type="button" class="btn btn-outline btn-sm no-print"><i class="fas fa-print"></i></button>
        </form>
    </div>
</div>

<?php if ($filterClass && $filterSubject && !empty($syllabus)): ?>
<!-- Progress Bar -->
<div class="card mb-16">
    <div class="card-body" style="padding:16px 20px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
            <span style="font-weight:700;font-size:14px;">সিলেবাস অগ্রগতি</span>
            <span style="font-weight:700;color:var(--primary);"><?=toBanglaNumber($progress)?>% সম্পন্ন</span>
        </div>
        <div style="background:var(--border);border-radius:8px;height:12px;">
            <div style="background:<?=$progress>=80?'var(--success)':($progress>=50?'var(--warning)':'var(--primary)')?>; width:<?=$progress?>%;height:100%;border-radius:8px;transition:width .5s;"></div>
        </div>
        <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--text-muted);margin-top:6px;">
            <span>সম্পন্ন: <?=count(array_filter($syllabus,fn($r)=>$r['is_completed']))?></span>
            <span>বাকি: <?=count(array_filter($syllabus,fn($r)=>!$r['is_completed']))?></span>
            <span>মোট: <?=count($syllabus)?></span>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Syllabus Table -->
<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>অধ্যায়</th><th>বিষয়বস্তু</th><th>Topics</th><th>মাস</th><th>অবস্থা</th>
                <?php if(in_array($_SESSION['role_slug'],['super_admin','principal','teacher'])): ?>
                <th class="no-print">অ্যাকশন</th>
                <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($syllabus)): ?>
                <tr><td colspan="6" style="text-align:center;padding:30px;color:var(--text-muted);">
                    <?=$filterClass&&$filterSubject?'কোনো সিলেবাস নেই':'শ্রেণী ও বিষয় নির্বাচন করুন'?>
                </td></tr>
                <?php else: foreach($syllabus as $s): ?>
                <tr style="<?=$s['is_completed']?'opacity:.6;background:#f9f9f9':''?>">
                    <td>
                        <?php if($s['chapter_no']): ?>
                        <span class="badge badge-primary" style="font-size:12px;">অধ্যায় <?=toBanglaNumber($s['chapter_no'])?></span>
                        <?php endif; ?>
                        <div style="font-weight:700;font-size:14px;margin-top:4px;"><?=e($s['chapter_name_bn']??$s['chapter_name']??'')?></div>
                    </td>
                    <td style="font-size:13px;max-width:200px;"><?=e($s['chapter_name_bn']??'')?></td>
                    <td style="font-size:12px;color:var(--text-muted);max-width:200px;"><?=nl2br(e($s['topics']??''))?></td>
                    <td style="font-size:13px;"><?=e($s['month']??'-')?></td>
                    <td>
                        <?php if(in_array($_SESSION['role_slug'],['super_admin','principal','teacher'])): ?>
                        <a href="?toggle=<?=$s['id']?>&class_id=<?=$filterClass?>&subject_id=<?=$filterSubject?>"
                            class="badge badge-<?=$s['is_completed']?'success':'secondary'?>" style="cursor:pointer;text-decoration:none;font-size:12px;">
                            <?=$s['is_completed']?'✅ সম্পন্ন':'⏳ বাকি'?>
                        </a>
                        <?php else: ?>
                        <span class="badge badge-<?=$s['is_completed']?'success':'secondary'?>"><?=$s['is_completed']?'সম্পন্ন':'বাকি'?></span>
                        <?php endif; ?>
                    </td>
                    <?php if(in_array($_SESSION['role_slug'],['super_admin','principal','teacher'])): ?>
                    <td class="no-print">
                        <a href="?delete=<?=$s['id']?>&class_id=<?=$filterClass?>&subject_id=<?=$filterSubject?>" onclick="return confirm('মুছবেন?')" class="btn btn-danger btn-xs"><i class="fas fa-trash"></i></a>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Modal -->
<?php if(in_array($_SESSION['role_slug'],['super_admin','principal','teacher'])): ?>
<div class="modal-overlay" id="addSyllabusModal">
    <div class="modal-box" style="max-width:560px;">
        <div class="modal-header">
            <span style="font-weight:700;"><i class="fas fa-plus"></i> অধ্যায় যোগ করুন</span>
            <button onclick="closeModal('addSyllabusModal')" class="btn btn-outline btn-xs">✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="csrf" value="<?=getCsrfToken()?>">
            <input type="hidden" name="save_syllabus" value="1">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label>শ্রেণী *</label>
                        <select name="class_id" class="form-control" required>
                            <option value="">নির্বাচন করুন</option>
                            <?php foreach($classes as $c): ?>
                            <option value="<?=$c['id']?>" <?=$filterClass==$c['id']?'selected':''?>><?=e($c['class_name_bn'])?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>বিষয় *</label>
                        <select name="subject_id" class="form-control" required>
                            <option value="">নির্বাচন করুন</option>
                            <?php foreach($subjects as $s): ?>
                            <option value="<?=$s['id']?>" <?=$filterSubject==$s['id']?'selected':''?>><?=e($s['subject_name_bn'])?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>অধ্যায় নম্বর</label>
                        <input type="number" name="chapter_no" class="form-control" min="1" placeholder="১, ২, ৩...">
                    </div>
                    <div class="form-group">
                        <label>মাস</label>
                        <select name="month" class="form-control">
                            <option value="">নির্বাচন করুন</option>
                            <?php foreach($months as $m): ?>
                            <option><?=$m?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="grid-column:1/-1;">
                        <label>অধ্যায়ের নাম (বাংলায়) *</label>
                        <input type="text" name="chapter_name_bn" class="form-control" required placeholder="অধ্যায়ের নাম বাংলায়">
                    </div>
                    <div class="form-group" style="grid-column:1/-1;">
                        <label>Chapter Name (English)</label>
                        <input type="text" name="chapter_name" class="form-control" placeholder="Chapter name in English">
                    </div>
                    <div class="form-group" style="grid-column:1/-1;">
                        <label>বিষয়বস্তু / Topics</label>
                        <textarea name="topics" class="form-control" rows="3" placeholder="এই অধ্যায়ে কী কী পড়ানো হবে (প্রতিটি আলাদা লাইনে লিখুন)"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="closeModal('addSyllabusModal')" class="btn btn-outline">বাতিল</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> সংরক্ষণ করুন</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>
