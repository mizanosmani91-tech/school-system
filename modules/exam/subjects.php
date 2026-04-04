<?php
require_once '../../includes/functions.php';
requireLogin(['super_admin','principal']);
$pageTitle = 'বিষয়সমূহ';
$db = getDB();

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_subject'])) {
    if (!verifyCsrf($_POST['csrf']??'')) die('CSRF');
    $nameBn = trim($_POST['subject_name_bn']??''); $name = trim($_POST['subject_name']??'');
    $code = trim($_POST['subject_code']??''); $type = $_POST['subject_type']??'general';
    $full = (int)($_POST['full_marks']??100); $pass = (int)($_POST['pass_marks']??33);
    if ($nameBn) {
        $db->prepare("INSERT INTO subjects (subject_name,subject_name_bn,subject_code,subject_type,full_marks,pass_marks) VALUES (?,?,?,?,?,?)")
            ->execute([$name ?: $nameBn, $nameBn, $code, $type, $full, $pass]);
        setFlash('success','বিষয় যোগ হয়েছে।');
    }
    header('Location: subjects.php'); exit;
}
if (isset($_GET['delete'])) {
    $db->prepare("UPDATE subjects SET is_active=0 WHERE id=?")->execute([(int)$_GET['delete']]);
    setFlash('success','বিষয় নিষ্ক্রিয় করা হয়েছে।');
    header('Location: subjects.php'); exit;
}
$subjects = $db->query("SELECT * FROM subjects WHERE is_active=1 ORDER BY subject_type, subject_name_bn")->fetchAll();
require_once '../../includes/header.php';
?>
<div class="section-header">
    <h2 class="section-title"><i class="fas fa-book"></i> বিষয়সমূহ</h2>
    <button onclick="openModal('addSubjectModal')" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> নতুন বিষয়</button>
</div>
<div class="card">
    <div class="table-wrap">
        <table>
            <thead><tr><th>#</th><th>বিষয়ের নাম</th><th>কোড</th><th>ধরন</th><th>পূর্ণমান</th><th>পাস মার্ক</th><th>অ্যাকশন</th></tr></thead>
            <tbody>
                <?php foreach($subjects as $i=>$s): ?>
                <tr>
                    <td><?=toBanglaNumber($i+1)?></td>
                    <td>
                        <div style="font-weight:600;"><?=e($s['subject_name_bn']??$s['subject_name'])?></div>
                        <div style="font-size:11px;color:var(--text-muted);"><?=e($s['subject_name'])?></div>
                    </td>
                    <td><?=e($s['subject_code']??'')?></td>
                    <td><span class="badge badge-<?=['islamic'=>'success','arabic'=>'info','quran'=>'warning','general'=>'primary'][$s['subject_type']]??'secondary'?>">
                        <?=['islamic'=>'ইসলামী','arabic'=>'আরবি','quran'=>'কুরআন','general'=>'সাধারণ','science'=>'বিজ্ঞান'][$s['subject_type']]??e($s['subject_type'])?>
                    </span></td>
                    <td><?=toBanglaNumber($s['full_marks'])?></td>
                    <td><?=toBanglaNumber($s['pass_marks'])?></td>
                    <td><a href="?delete=<?=$s['id']?>" onclick="return confirm('নিষ্ক্রিয় করবেন?')" class="btn btn-danger btn-xs"><i class="fas fa-ban"></i></a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal-overlay" id="addSubjectModal">
    <div class="modal-box">
        <div class="modal-header">
            <span style="font-weight:700;">নতুন বিষয় যোগ করুন</span>
            <button onclick="closeModal('addSubjectModal')" class="btn btn-outline btn-xs">✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="csrf" value="<?=getCsrfToken()?>">
            <input type="hidden" name="add_subject" value="1">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group"><label>বিষয়ের নাম (বাংলায়) *</label>
                        <input type="text" name="subject_name_bn" class="form-control" required></div>
                    <div class="form-group"><label>বিষয়ের নাম (ইংরেজি)</label>
                        <input type="text" name="subject_name" class="form-control"></div>
                    <div class="form-group"><label>বিষয় কোড</label>
                        <input type="text" name="subject_code" class="form-control" placeholder="MAT101"></div>
                    <div class="form-group"><label>ধরন</label>
                        <select name="subject_type" class="form-control">
                            <option value="general">সাধারণ</option>
                            <option value="islamic">ইসলামী</option>
                            <option value="arabic">আরবি</option>
                            <option value="quran">কুরআন/হিফজ</option>
                            <option value="science">বিজ্ঞান</option>
                        </select></div>
                    <div class="form-group"><label>পূর্ণমান</label>
                        <input type="number" name="full_marks" class="form-control" value="100" min="1"></div>
                    <div class="form-group"><label>পাস মার্ক</label>
                        <input type="number" name="pass_marks" class="form-control" value="33" min="1"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="closeModal('addSubjectModal')" class="btn btn-outline">বাতিল</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> যোগ করুন</button>
            </div>
        </form>
    </div>
</div>
<?php require_once '../../includes/footer.php'; ?>
