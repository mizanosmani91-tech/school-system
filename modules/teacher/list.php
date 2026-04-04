<?php
require_once '../../includes/functions.php';
requireLogin(['super_admin','principal']);
$pageTitle = 'শিক্ষক তালিকা';
$db = getDB();

// Add teacher
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_teacher'])) {
    if (!verifyCsrf($_POST['csrf']??'')) die('CSRF');
    $name = trim($_POST['name']??''); $nameBn = trim($_POST['name_bn']??'');
    $phone = trim($_POST['phone']??''); $designation = trim($_POST['designation_bn']??'');
    $joining = $_POST['joining_date']??date('Y-m-d');
    $salary = (float)($_POST['salary']??0);
    $qualification = trim($_POST['qualification']??'');

    if ($name && $phone) {
        // Random 8 character পাসওয়ার্ড তৈরি
        $rawPassword = substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789'), 0, 8);
        $hashedPw = password_hash($rawPassword, PASSWORD_DEFAULT);
        $uStmt = $db->prepare("INSERT INTO users (name,name_bn,username,phone,password,role_id) VALUES (?,?,?,?,?,3)");
        $uStmt->execute([$name,$nameBn,$phone,$phone,$hashedPw]);
        $userId = $db->lastInsertId();
        $teacherId = 'TCH-'.date('Y').'-'.str_pad($db->query("SELECT COUNT(*)+1 FROM teachers")->fetchColumn(),3,'0',STR_PAD_LEFT);
        $tStmt = $db->prepare("INSERT INTO teachers (user_id,teacher_id_no,name,name_bn,phone,designation_bn,joining_date,salary,qualification,is_active) VALUES (?,?,?,?,?,?,?,?,?,1)");
        $tStmt->execute([$userId,$teacherId,$name,$nameBn,$phone,$designation,$joining,$salary,$qualification]);
        setFlash('success',"শিক্ষক যোগ হয়েছে! ID: $teacherId | লগইন: $phone | পাসওয়ার্ড: $rawPassword (সংরক্ষণ করুন!)");
    } else { setFlash('danger','নাম ও ফোন আবশ্যক।'); }
    header('Location: list.php'); exit;
}

// Delete
if (isset($_GET['delete'])) {
    $db->prepare("UPDATE teachers SET is_active=0 WHERE id=?")->execute([(int)$_GET['delete']]);
    setFlash('success','শিক্ষক নিষ্ক্রিয় করা হয়েছে।');
    header('Location: list.php'); exit;
}

$search = trim($_GET['search']??'');
$where = "is_active=1"; $params=[];
if ($search) { $where.=" AND (name LIKE ? OR name_bn LIKE ? OR phone LIKE ?)"; $s="%$search%"; $params=[$s,$s,$s]; }
$stmt = $db->prepare("SELECT * FROM teachers WHERE $where ORDER BY name_bn");
$stmt->execute($params); $teachers = $stmt->fetchAll();

require_once '../../includes/header.php';
?>
<div class="section-header">
    <h2 class="section-title"><i class="fas fa-chalkboard-teacher"></i> শিক্ষক তালিকা</h2>
    <button onclick="openModal('addTeacherModal')" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> নতুন শিক্ষক</button>
</div>

<div class="card mb-16">
    <div class="card-body" style="padding:12px 20px;">
        <form method="GET" style="display:flex;gap:10px;">
            <input type="text" name="search" class="form-control" style="max-width:300px;" placeholder="নাম বা ফোন নম্বর..." value="<?=e($search)?>">
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i></button>
            <a href="list.php" class="btn btn-outline btn-sm">রিসেট</a>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <span class="card-title">মোট <?=toBanglaNumber(count($teachers))?> জন শিক্ষক</span>
        <button onclick="window.print()" class="btn btn-outline btn-sm no-print"><i class="fas fa-print"></i></button>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>#</th><th>শিক্ষকের তথ্য</th><th>পদবী</th><th>ফোন</th><th>যোগদানের তারিখ</th><th>বেতন</th><th class="no-print">অ্যাকশন</th></tr></thead>
            <tbody>
                <?php if(empty($teachers)): ?>
                <tr><td colspan="7" style="text-align:center;padding:30px;color:#718096;">কোনো শিক্ষক নেই</td></tr>
                <?php else: foreach($teachers as $i=>$t): ?>
                <tr>
                    <td style="color:var(--text-muted);font-size:13px;"><?=toBanglaNumber($i+1)?></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div class="avatar"><?=mb_substr($t['name_bn']??$t['name'],0,1)?></div>
                            <div>
                                <div style="font-weight:700;font-size:14px;"><?=e($t['name_bn']??$t['name'])?></div>
                                <div style="font-size:11px;color:var(--text-muted);">ID: <?=e($t['teacher_id_no'])?></div>
                            </div>
                        </div>
                    </td>
                    <td style="font-size:13px;"><?=e($t['designation_bn']??'-')?></td>
                    <td style="font-size:13px;"><?=e($t['phone']??'-')?></td>
                    <td style="font-size:13px;"><?=banglaDate($t['joining_date']??'')?></td>
                    <td style="font-weight:700;color:var(--success);">৳<?=number_format($t['salary']??0)?></td>
                    <td class="no-print">
                        <a href="?delete=<?=$t['id']?>" onclick="return confirm('নিষ্ক্রিয় করবেন?')" class="btn btn-danger btn-xs"><i class="fas fa-ban"></i></a>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Teacher Modal -->
<div class="modal-overlay" id="addTeacherModal">
    <div class="modal-box">
        <div class="modal-header">
            <span style="font-weight:700;"><i class="fas fa-user-plus"></i> নতুন শিক্ষক যোগ করুন</span>
            <button onclick="closeModal('addTeacherModal')" class="btn btn-outline btn-xs">✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="csrf" value="<?=getCsrfToken()?>">
            <input type="hidden" name="add_teacher" value="1">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group"><label>নাম (বাংলায়) *</label>
                        <input type="text" name="name_bn" class="form-control" required></div>
                    <div class="form-group"><label>নাম (ইংরেজি) *</label>
                        <input type="text" name="name" class="form-control" required></div>
                    <div class="form-group"><label>ফোন নম্বর * (লগইন নম্বর)</label>
                        <input type="tel" name="phone" class="form-control" placeholder="01XXXXXXXXX" required></div>
                    <div class="form-group"><label>পদবী</label>
                        <input type="text" name="designation_bn" class="form-control" placeholder="সহকারী শিক্ষক"></div>
                    <div class="form-group"><label>যোগদানের তারিখ</label>
                        <input type="date" name="joining_date" class="form-control" value="<?=date('Y-m-d')?>"></div>
                    <div class="form-group"><label>মাসিক বেতন (৳)</label>
                        <input type="number" name="salary" class="form-control" min="0"></div>
                    <div class="form-group" style="grid-column:1/-1;"><label>শিক্ষাগত যোগ্যতা</label>
                        <input type="text" name="qualification" class="form-control" placeholder="কামিল, বি.এড."></div>
                </div>
                <div class="alert alert-info mt-16"><i class="fas fa-info-circle"></i> ফোন নম্বর দিয়ে লগইন করতে পারবেন। প্রাথমিক পাসওয়ার্ড হবে ফোন নম্বর।</div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="closeModal('addTeacherModal')" class="btn btn-outline">বাতিল</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> যোগ করুন</button>
            </div>
        </form>
    </div>
</div>
<?php require_once '../../includes/footer.php'; ?>
