<?php
require_once '../../includes/functions.php';
requireLogin(['super_admin','principal']);
$pageTitle = 'শিক্ষক তালিকা';
$db = getDB();

// Unique Code কালার badge ফাংশন
function uniqueCodeBadge($code) {
    if (empty($code)) return '';
    $colors = [
        'AN-STUD'  => ['bg'=>'#e8f5e9','color'=>'#2e7d32','border'=>'#a5d6a7'], // সবুজ — ছাত্র
        'AN-TEAC'  => ['bg'=>'#e3f2fd','color'=>'#1565c0','border'=>'#90caf9'], // নীল — শিক্ষক
        'AN-PRINC' => ['bg'=>'#fce4ec','color'=>'#880e4f','border'=>'#f48fb1'], // গোলাপি — প্রিন্সিপাল
        'AN-STAFF' => ['bg'=>'#fff3e0','color'=>'#e65100','border'=>'#ffcc80'], // কমলা — স্টাফ
        'AN-ADMIN' => ['bg'=>'#f3e5f5','color'=>'#6a1b9a','border'=>'#ce93d8'], // বেগুনি — অ্যাডমিন
        'AN-SADM'  => ['bg'=>'#fbe9e7','color'=>'#bf360c','border'=>'#ffab91'], // লাল — সুপার অ্যাডমিন
    ];
    $style = ['bg'=>'#f5f5f5','color'=>'#555','border'=>'#ddd']; // default
    foreach ($colors as $prefix => $s) {
        if (str_starts_with($code, $prefix)) { $style = $s; break; }
    }
    return '<div style="display:inline-block;margin-top:3px;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:700;letter-spacing:.5px;background:'.$style['bg'].';color:'.$style['color'].';border:1px solid '.$style['border'].';">'.htmlspecialchars($code).'</div>';
}

// Add teacher
$newTeacherInfo = null;
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_teacher'])) {
    if (!verifyCsrf($_POST['csrf']??'')) die('CSRF');
    $name = trim($_POST['name']??''); $nameBn = trim($_POST['name_bn']??'');
    $phone = trim($_POST['phone']??''); $designation = trim($_POST['designation_bn']??'');
    $joining = $_POST['joining_date']??date('Y-m-d');
    $salary = (float)($_POST['salary']??0);
    $qualification = trim($_POST['qualification']??'');

    if ($name && $phone) {
        $rawPassword = substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789'), 0, 8);
        $hashedPw = password_hash($rawPassword, PASSWORD_DEFAULT);
        $uStmt = $db->prepare("INSERT INTO users (name,name_bn,username,phone,password,role_id) VALUES (?,?,?,?,?,3)");
        $uStmt->execute([$name,$nameBn,$phone,$phone,$hashedPw]);
        $userId = $db->lastInsertId();
        $teacherId = 'TCH-'.date('Y').'-'.str_pad($db->query("SELECT COUNT(*)+1 FROM teachers")->fetchColumn(),3,'0',STR_PAD_LEFT);

        // Unique Code — AN-TEAC-XXXXXX-XXXXXX
        $uniqueCode = generateUniqueCode($db, 'teacher');

        $tStmt = $db->prepare("INSERT INTO teachers (user_id,teacher_id_no,name,name_bn,phone,designation_bn,joining_date,salary,qualification,unique_code,is_active) VALUES (?,?,?,?,?,?,?,?,?,?,1)");
        $tStmt->execute([$userId,$teacherId,$name,$nameBn,$phone,$designation,$joining,$salary,$qualification,$uniqueCode]);

        $_SESSION['new_teacher_info'] = [
            'name'        => $nameBn ?: $name,
            'id'          => $teacherId,
            'unique_code' => $uniqueCode,
            'phone'       => $phone,
            'pass'        => $rawPassword,
        ];
    } else {
        setFlash('danger','নাম ও ফোন আবশ্যক।');
    }
    header('Location: list.php'); exit;
}

// Delete
if (isset($_GET['delete'])) {
    $db->prepare("UPDATE teachers SET is_active=0 WHERE id=?")->execute([(int)$_GET['delete']]);
    setFlash('success','শিক্ষক নিষ্ক্রিয় করা হয়েছে।');
    header('Location: list.php'); exit;
}

if (!empty($_SESSION['new_teacher_info'])) {
    $newTeacherInfo = $_SESSION['new_teacher_info'];
    unset($_SESSION['new_teacher_info']);
}

$search = trim($_GET['search']??'');
$where = "is_active=1"; $params=[];
if ($search) { $where.=" AND (name LIKE ? OR name_bn LIKE ? OR phone LIKE ?)"; $s="%$search%"; $params=[$s,$s,$s]; }
$stmt = $db->prepare("SELECT * FROM teachers WHERE $where ORDER BY name_bn");
$stmt->execute($params); $teachers = $stmt->fetchAll();

require_once '../../includes/header.php';
?>

<?php if ($newTeacherInfo): ?>
<div class="modal-overlay" id="teacherInfoModal" style="display:flex;">
    <div class="modal-box" style="max-width:480px;">
        <div class="modal-header" style="background:var(--success);color:#fff;border-radius:10px 10px 0 0;">
            <span style="font-weight:700;font-size:16px;"><i class="fas fa-check-circle"></i> শিক্ষক সফলভাবে যোগ হয়েছে!</span>
        </div>
        <div class="card-body" style="padding:24px;">
            <p style="color:var(--text-muted);font-size:13px;margin-bottom:16px;">নিচের তথ্যগুলো শিক্ষককে জানিয়ে দিন। এই উইন্ডো বন্ধ করলে আর দেখা যাবে না।</p>
            <div style="background:var(--bg);border:2px dashed var(--border);border-radius:10px;padding:16px;font-family:monospace;font-size:14px;line-height:2.2;">
                <div><span style="color:var(--text-muted);">নাম:</span> <strong><?= e($newTeacherInfo['name']) ?></strong></div>
                <div><span style="color:var(--text-muted);">শিক্ষক ID:</span> <strong style="color:var(--primary);"><?= e($newTeacherInfo['id']) ?></strong></div>
                <div><span style="color:var(--text-muted);">Unique Code:</span> <?= uniqueCodeBadge($newTeacherInfo['unique_code']) ?></div>
                <div><span style="color:var(--text-muted);">লগইন নম্বর:</span> <strong><?= e($newTeacherInfo['phone']) ?></strong></div>
                <div><span style="color:var(--text-muted);">পাসওয়ার্ড:</span> <strong style="color:var(--danger);font-size:16px;letter-spacing:1px;"><?= e($newTeacherInfo['pass']) ?></strong></div>
            </div>
            <div style="display:flex;gap:10px;margin-top:16px;">
                <button onclick="copyTeacherInfo()" class="btn btn-primary" style="flex:1;" id="copyBtn">
                    <i class="fas fa-copy"></i> তথ্য কপি করুন
                </button>
                <button onclick="document.getElementById('teacherInfoModal').style.display='none'" class="btn btn-outline" style="flex:1;">
                    <i class="fas fa-times"></i> বন্ধ করুন
                </button>
            </div>
            <div class="alert alert-warning mt-16" style="font-size:12px;">
                <i class="fas fa-exclamation-triangle"></i> পাসওয়ার্ডটি এখনই সংরক্ষণ করুন। পরে আর দেখা যাবে না।
            </div>
        </div>
    </div>
</div>
<script>
function copyTeacherInfo() {
    const text = `নাম: <?= e($newTeacherInfo['name']) ?>\nশিক্ষক ID: <?= e($newTeacherInfo['id']) ?>\nUnique Code: <?= e($newTeacherInfo['unique_code']) ?>\nলগইন নম্বর: <?= e($newTeacherInfo['phone']) ?>\nপাসওয়ার্ড: <?= e($newTeacherInfo['pass']) ?>`;
    navigator.clipboard.writeText(text).then(() => {
        const btn = document.getElementById('copyBtn');
        btn.innerHTML = '<i class="fas fa-check"></i> কপি হয়েছে!';
        btn.style.background = 'var(--success)';
        setTimeout(() => { btn.innerHTML = '<i class="fas fa-copy"></i> তথ্য কপি করুন'; btn.style.background = ''; }, 2500);
    });
}
</script>
<?php endif; ?>

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
                                <a href="profile.php?id=<?=$t['id']?>" style="font-weight:700;font-size:14px;color:var(--primary);text-decoration:none;">
                                    <?=e($t['name_bn']??$t['name'])?>
                                </a>
                                <div style="font-size:11px;color:var(--text-muted);">ID: <?=e($t['teacher_id_no'])?></div>
                                <?php if (!empty($t['unique_code'])): ?>
                                    <?= uniqueCodeBadge($t['unique_code']) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td style="font-size:13px;"><?=e($t['designation_bn']??'-')?></td>
                    <td style="font-size:13px;"><?=e($t['phone']??'-')?></td>
                    <td style="font-size:13px;"><?=banglaDate($t['joining_date']??'')?></td>
                    <td style="font-weight:700;color:var(--success);">৳<?=number_format($t['salary']??0)?></td>
                    <td class="no-print" style="display:flex;gap:6px;align-items:center;">
                        <a href="profile.php?id=<?=$t['id']?>" class="btn btn-primary btn-xs" title="প্রোফাইল"><i class="fas fa-eye"></i></a>
                        <a href="?delete=<?=$t['id']?>" onclick="return confirm('নিষ্ক্রিয় করবেন?')" class="btn btn-danger btn-xs" title="নিষ্ক্রিয়"><i class="fas fa-ban"></i></a>
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
            </div>
            <div class="modal-footer">
                <button type="button" onclick="closeModal('addTeacherModal')" class="btn btn-outline">বাতিল</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> যোগ করুন</button>
            </div>
        </form>
    </div>
</div>
<?php require_once '../../includes/footer.php'; ?>
