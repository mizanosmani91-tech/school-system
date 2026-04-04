<?php
require_once '../../includes/functions.php';
requireLogin();
$pageTitle = 'মডেল টেস্ট / MCQ';
$db = getDB();

$classes  = $db->query("SELECT * FROM classes WHERE is_active=1 ORDER BY class_numeric")->fetchAll();
$subjects = $db->query("SELECT * FROM subjects WHERE is_active=1 ORDER BY subject_name_bn")->fetchAll();

// Ensure tables exist
$db->exec("CREATE TABLE IF NOT EXISTS model_tests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    title_bn VARCHAR(255),
    class_id INT,
    subject_id INT,
    duration_minutes INT DEFAULT 30,
    total_marks INT DEFAULT 0,
    instructions TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES classes(id),
    FOREIGN KEY (subject_id) REFERENCES subjects(id)
)");

$db->exec("CREATE TABLE IF NOT EXISTS mcq_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    test_id INT NOT NULL,
    question TEXT NOT NULL,
    option_a VARCHAR(255),
    option_b VARCHAR(255),
    option_c VARCHAR(255),
    option_d VARCHAR(255),
    correct_answer ENUM('a','b','c','d') NOT NULL,
    marks INT DEFAULT 1,
    explanation TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (test_id) REFERENCES model_tests(id) ON DELETE CASCADE
)");

// Save test
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save_test'])) {
    if (!verifyCsrf($_POST['csrf']??'')) die('CSRF');
    $title    = trim($_POST['title_bn']??'');
    $classId  = (int)($_POST['class_id']??0) ?: null;
    $subjectId= (int)($_POST['subject_id']??0) ?: null;
    $duration = (int)($_POST['duration']??30);
    $instructions = trim($_POST['instructions']??'');

    $db->prepare("INSERT INTO model_tests (title,title_bn,class_id,subject_id,duration_minutes,instructions,created_by)
        VALUES (?,?,?,?,?,?,?)")
       ->execute([$title,$title,$classId,$subjectId,$duration,$instructions,$_SESSION['user_id']]);
    $testId = $db->lastInsertId();
    setFlash('success','মডেল টেস্ট তৈরি হয়েছে। এখন প্রশ্ন যোগ করুন।');
    header('Location: model_test.php?test_id='.$testId.'&add_question=1'); exit;
}

// Save question
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save_question'])) {
    if (!verifyCsrf($_POST['csrf']??'')) die('CSRF');
    $testId  = (int)$_POST['test_id'];
    $question= trim($_POST['question']??'');
    $optA    = trim($_POST['option_a']??'');
    $optB    = trim($_POST['option_b']??'');
    $optC    = trim($_POST['option_c']??'');
    $optD    = trim($_POST['option_d']??'');
    $correct = $_POST['correct_answer']??'a';
    $marks   = (int)($_POST['marks']??1);
    $explanation = trim($_POST['explanation']??'');

    $db->prepare("INSERT INTO mcq_questions (test_id,question,option_a,option_b,option_c,option_d,correct_answer,marks,explanation)
        VALUES (?,?,?,?,?,?,?,?,?)")
       ->execute([$testId,$question,$optA,$optB,$optC,$optD,$correct,$marks,$explanation]);

    // Update total marks
    $db->prepare("UPDATE model_tests SET total_marks=(SELECT SUM(marks) FROM mcq_questions WHERE test_id=?) WHERE id=?")
       ->execute([$testId,$testId]);

    setFlash('success','প্রশ্ন যোগ হয়েছে।');
    header('Location: model_test.php?test_id='.$testId.'&add_question=1'); exit;
}

// Delete test
if (isset($_GET['delete_test'])) {
    $db->prepare("DELETE FROM model_tests WHERE id=?")->execute([(int)$_GET['delete_test']]);
    setFlash('success','মডেল টেস্ট মুছে ফেলা হয়েছে।');
    header('Location: model_test.php'); exit;
}

// Delete question
if (isset($_GET['delete_q'])) {
    $q = $db->prepare("SELECT test_id FROM mcq_questions WHERE id=?");
    $q->execute([(int)$_GET['delete_q']]); $q = $q->fetch();
    $db->prepare("DELETE FROM mcq_questions WHERE id=?")->execute([(int)$_GET['delete_q']]);
    if ($q) {
        $db->prepare("UPDATE model_tests SET total_marks=(SELECT COALESCE(SUM(marks),0) FROM mcq_questions WHERE test_id=?) WHERE id=?")
           ->execute([$q['test_id'],$q['test_id']]);
        header('Location: model_test.php?test_id='.$q['test_id'].'&add_question=1'); exit;
    }
    header('Location: model_test.php'); exit;
}

$testId = (int)($_GET['test_id']??0);
$addQuestion = isset($_GET['add_question']) && $testId;

// Load selected test
$selectedTest = null;
$questions = [];
if ($testId) {
    $stmt = $db->prepare("SELECT mt.*, c.class_name_bn, s.subject_name_bn FROM model_tests mt
        LEFT JOIN classes c ON mt.class_id=c.id LEFT JOIN subjects s ON mt.subject_id=s.id WHERE mt.id=?");
    $stmt->execute([$testId]); $selectedTest = $stmt->fetch();

    $qStmt = $db->prepare("SELECT * FROM mcq_questions WHERE test_id=? ORDER BY id");
    $qStmt->execute([$testId]); $questions = $qStmt->fetchAll();
}

// All tests list
$tests = $db->query("SELECT mt.*, c.class_name_bn, s.subject_name_bn,
    (SELECT COUNT(*) FROM mcq_questions WHERE test_id=mt.id) as question_count
    FROM model_tests mt LEFT JOIN classes c ON mt.class_id=c.id LEFT JOIN subjects s ON mt.subject_id=s.id
    ORDER BY mt.created_at DESC LIMIT 50")->fetchAll();

$optLabels = ['a'=>'ক','b'=>'খ','c'=>'গ','d'=>'ঘ'];

require_once '../../includes/header.php';
?>

<div class="section-header">
    <h2 class="section-title"><i class="fas fa-question-circle"></i> মডেল টেস্ট / MCQ</h2>
    <?php if(in_array($_SESSION['role_slug'],['super_admin','principal','teacher'])): ?>
    <button onclick="openModal('addTestModal')" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> নতুন টেস্ট</button>
    <?php endif; ?>
</div>

<div class="grid-2">
<!-- Tests List -->
<div class="card">
    <div class="card-header"><span class="card-title"><i class="fas fa-list"></i> টেস্ট তালিকা</span></div>
    <div class="card-body" style="padding:0;">
        <?php if(empty($tests)): ?>
        <div style="text-align:center;padding:30px;color:var(--text-muted);">কোনো মডেল টেস্ট নেই</div>
        <?php else: foreach($tests as $t): ?>
        <div style="padding:14px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:12px;<?=$testId==$t['id']?'background:#ebf5fb;':''?>">
            <div style="flex:1;">
                <div style="font-weight:700;font-size:14px;"><?=e($t['title_bn']??$t['title'])?></div>
                <div style="font-size:12px;color:var(--text-muted);margin-top:3px;">
                    <?=e($t['class_name_bn']??'সব শ্রেণী')?> &bull;
                    <?=e($t['subject_name_bn']??'সব বিষয়')?> &bull;
                    <?=toBanglaNumber($t['question_count'])?> প্রশ্ন &bull;
                    <?=toBanglaNumber($t['total_marks'])?> নম্বর
                </div>
            </div>
            <div style="display:flex;gap:4px;">
                <a href="?test_id=<?=$t['id']?>" class="btn btn-info btn-xs"><i class="fas fa-eye"></i></a>
                <?php if(in_array($_SESSION['role_slug'],['super_admin','principal','teacher'])): ?>
                <a href="?test_id=<?=$t['id']?>&add_question=1" class="btn btn-primary btn-xs"><i class="fas fa-plus"></i></a>
                <a href="?delete_test=<?=$t['id']?>" onclick="return confirm('মুছবেন?')" class="btn btn-danger btn-xs"><i class="fas fa-trash"></i></a>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; endif; ?>
    </div>
</div>

<!-- Questions / Add Question -->
<div>
<?php if ($selectedTest): ?>
<div class="card mb-16">
    <div class="card-header" style="background:var(--primary);color:#fff;">
        <span style="font-weight:700;"><?=e($selectedTest['title_bn'])?></span>
        <div style="display:flex;gap:8px;">
            <span class="badge" style="background:rgba(255,255,255,.2);color:#fff;"><?=toBanglaNumber(count($questions))?> প্রশ্ন</span>
            <span class="badge" style="background:rgba(255,255,255,.2);color:#fff;"><?=toBanglaNumber($selectedTest['total_marks'])?> নম্বর</span>
            <button onclick="window.print()" class="btn btn-sm no-print" style="background:rgba(255,255,255,.2);color:#fff;padding:4px 10px;"><i class="fas fa-print"></i></button>
        </div>
    </div>
    <div class="card-body" style="padding:0;">
        <?php foreach($questions as $i=>$q): ?>
        <div style="padding:16px;border-bottom:1px solid var(--border);">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;">
                <div style="flex:1;">
                    <div style="font-weight:700;font-size:14px;margin-bottom:10px;">
                        <?=toBanglaNumber($i+1)?>. <?=e($q['question'])?>
                        <span style="color:var(--text-muted);font-size:12px;font-weight:400;">(<?=toBanglaNumber($q['marks'])?> নম্বর)</span>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;">
                        <?php foreach(['a','b','c','d'] as $opt): ?>
                        <div style="padding:6px 10px;border-radius:6px;font-size:13px;
                            background:<?=$q['correct_answer']===$opt?'#d4edda':'#f7fafc'?>;
                            border:1px solid <?=$q['correct_answer']===$opt?'var(--success)':'var(--border)'?>;
                            color:<?=$q['correct_answer']===$opt?'#155724':'var(--text)'?>;">
                            <strong><?=$optLabels[$opt]?>) </strong><?=e($q['option_'.$opt]??'')?>
                            <?=$q['correct_answer']===$opt?' ✓':''?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if($q['explanation']): ?>
                    <div style="margin-top:8px;padding:8px 10px;background:#fff3cd;border-radius:6px;font-size:12px;">
                        <strong>ব্যাখ্যা:</strong> <?=e($q['explanation'])?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php if(in_array($_SESSION['role_slug'],['super_admin','principal','teacher'])): ?>
                <a href="?delete_q=<?=$q['id']?>" onclick="return confirm('মুছবেন?')" class="btn btn-danger btn-xs no-print" style="margin-left:8px;"><i class="fas fa-trash"></i></a>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if(empty($questions)): ?>
        <div style="text-align:center;padding:30px;color:var(--text-muted);">এখনো কোনো প্রশ্ন যোগ করা হয়নি</div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Question Form -->
<?php if($addQuestion && in_array($_SESSION['role_slug'],['super_admin','principal','teacher'])): ?>
<div class="card">
    <div class="card-header"><span class="card-title"><i class="fas fa-plus"></i> প্রশ্ন যোগ করুন</span></div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="csrf" value="<?=getCsrfToken()?>">
            <input type="hidden" name="save_question" value="1">
            <input type="hidden" name="test_id" value="<?=$testId?>">
            <div class="form-group mb-16">
                <label>প্রশ্ন *</label>
                <textarea name="question" class="form-control" rows="2" required placeholder="প্রশ্নটি লিখুন..."></textarea>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label>ক) বিকল্প *</label>
                    <input type="text" name="option_a" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>খ) বিকল্প *</label>
                    <input type="text" name="option_b" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>গ) বিকল্প</label>
                    <input type="text" name="option_c" class="form-control">
                </div>
                <div class="form-group">
                    <label>ঘ) বিকল্প</label>
                    <input type="text" name="option_d" class="form-control">
                </div>
                <div class="form-group">
                    <label>সঠিক উত্তর *</label>
                    <select name="correct_answer" class="form-control" required>
                        <option value="a">ক)</option>
                        <option value="b">খ)</option>
                        <option value="c">গ)</option>
                        <option value="d">ঘ)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>নম্বর</label>
                    <input type="number" name="marks" class="form-control" value="1" min="1">
                </div>
                <div class="form-group" style="grid-column:1/-1;">
                    <label>ব্যাখ্যা (ঐচ্ছিক)</label>
                    <textarea name="explanation" class="form-control" rows="2" placeholder="সঠিক উত্তরের ব্যাখ্যা..."></textarea>
                </div>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> প্রশ্ন যোগ করুন</button>
        </form>
    </div>
</div>
<?php endif; ?>
<?php else: ?>
<div class="card"><div class="card-body" style="text-align:center;padding:48px;color:var(--text-muted);">
    <i class="fas fa-arrow-left" style="font-size:36px;margin-bottom:12px;"></i>
    <p>বাম দিক থেকে একটি টেস্ট নির্বাচন করুন</p>
</div></div>
<?php endif; ?>
</div>
</div>

<!-- Add Test Modal -->
<?php if(in_array($_SESSION['role_slug'],['super_admin','principal','teacher'])): ?>
<div class="modal-overlay" id="addTestModal">
    <div class="modal-box" style="max-width:500px;">
        <div class="modal-header">
            <span style="font-weight:700;">নতুন মডেল টেস্ট</span>
            <button onclick="closeModal('addTestModal')" class="btn btn-outline btn-xs">✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="csrf" value="<?=getCsrfToken()?>">
            <input type="hidden" name="save_test" value="1">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group" style="grid-column:1/-1;">
                        <label>টেস্টের নাম *</label>
                        <input type="text" name="title_bn" class="form-control" required placeholder="যেমন: গণিত মডেল টেস্ট - ১">
                    </div>
                    <div class="form-group">
                        <label>শ্রেণী</label>
                        <select name="class_id" class="form-control">
                            <option value="">সব শ্রেণী</option>
                            <?php foreach($classes as $c): ?>
                            <option value="<?=$c['id']?>"><?=e($c['class_name_bn'])?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>বিষয়</label>
                        <select name="subject_id" class="form-control">
                            <option value="">সব বিষয়</option>
                            <?php foreach($subjects as $s): ?>
                            <option value="<?=$s['id']?>"><?=e($s['subject_name_bn'])?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>সময়সীমা (মিনিট)</label>
                        <input type="number" name="duration" class="form-control" value="30" min="5">
                    </div>
                    <div class="form-group" style="grid-column:1/-1;">
                        <label>নির্দেশনা</label>
                        <textarea name="instructions" class="form-control" rows="2" placeholder="পরীক্ষার্থীদের জন্য নির্দেশনা..."></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="closeModal('addTestModal')" class="btn btn-outline">বাতিল</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-arrow-right"></i> পরবর্তী → প্রশ্ন যোগ করুন</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>
