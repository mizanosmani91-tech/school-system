<?php
require_once 'includes/functions.php';
requireLogin(['super_admin','principal']);
$pageTitle = 'সিস্টেম সেটিংস';
$db = getDB();

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save_settings'])) {
    if (!verifyCsrf($_POST['csrf']??'')) die('CSRF');
    $keys = ['institute_name','institute_name_en','institute_type','address','phone','email',
             'academic_year','eiin','board','ai_api_key','sms_api_key'];
    foreach ($keys as $k) {
        $val = trim($_POST[$k]??'');
        $db->prepare("INSERT INTO settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?")->execute([$k,$val,$val]);
    }
    // Logo remove
    if (!empty($_POST['remove_logo'])) {
        $db->prepare("DELETE FROM settings WHERE setting_key='logo'")->execute();
    }
    // Logo upload
    if (!empty($_FILES['logo']['name'])) {
        $ext = pathinfo($_FILES['logo']['name'],PATHINFO_EXTENSION);
        $logoPath = 'logo.'.$ext;
        $dir = UPLOAD_PATH;
        if (!is_dir($dir)) mkdir($dir,0755,true);
        move_uploaded_file($_FILES['logo']['tmp_name'], $dir.$logoPath);
        $db->prepare("INSERT INTO settings (setting_key,setting_value) VALUES ('logo',?) ON DUPLICATE KEY UPDATE setting_value=?")->execute([$logoPath,$logoPath]);
    }
    setFlash('success','সেটিংস সংরক্ষিত হয়েছে।');
    header('Location: settings.php'); exit;
}

// Change password
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['change_password'])) {
    if (!verifyCsrf($_POST['csrf']??'')) die('CSRF');
    $oldPw = $_POST['old_password']??''; $newPw = $_POST['new_password']??''; $confirm = $_POST['confirm_password']??'';
    $user = getCurrentUser();
    if (!password_verify($oldPw, $user['password'])) { setFlash('danger','পুরনো পাসওয়ার্ড ভুল।'); }
    elseif ($newPw !== $confirm) { setFlash('danger','নতুন পাসওয়ার্ড মিলছে না।'); }
    elseif (strlen($newPw) < 6) { setFlash('danger','পাসওয়ার্ড কমপক্ষে ৬ অক্ষর হতে হবে।'); }
    else {
        $db->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($newPw,PASSWORD_DEFAULT),$_SESSION['user_id']]);
        setFlash('success','পাসওয়ার্ড পরিবর্তন হয়েছে।');
    }
    header('Location: settings.php'); exit;
}

$settings = [];
$rows = $db->query("SELECT setting_key, setting_value FROM settings")->fetchAll();
foreach ($rows as $r) $settings[$r['setting_key']] = $r['setting_value'];

require_once 'includes/header.php';
?>
<div class="section-header">
    <h2 class="section-title"><i class="fas fa-cog"></i> সিস্টেম সেটিংস</h2>
</div>

<div class="grid-2">
<div>
<div class="card mb-16">
    <div class="card-header"><span class="card-title"><i class="fas fa-school"></i> প্রতিষ্ঠানের তথ্য</span></div>
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?=getCsrfToken()?>">
            <input type="hidden" name="save_settings" value="1">
            <div class="form-grid">
                <div class="form-group"><label>প্রতিষ্ঠানের নাম (বাংলায়)</label>
                    <input type="text" name="institute_name" class="form-control" value="<?=e($settings['institute_name']??'')?>"></div>
                <div class="form-group"><label>প্রতিষ্ঠানের নাম (ইংরেজি)</label>
                    <input type="text" name="institute_name_en" class="form-control" value="<?=e($settings['institute_name_en']??'')?>"></div>
                <div class="form-group"><label>প্রতিষ্ঠানের ধরন</label>
                    <select name="institute_type" class="form-control">
                        <option value="school" <?=($settings['institute_type']??'')==='school'?'selected':''?>>স্কুল</option>
                        <option value="madrasa" <?=($settings['institute_type']??'')==='madrasa'?'selected':''?>>মাদ্রাসা</option>
                        <option value="college" <?=($settings['institute_type']??'')==='college'?'selected':''?>>কলেজ</option>
                    </select></div>
                <div class="form-group"><label>শিক্ষাবোর্ড</label>
                    <input type="text" name="board" class="form-control" value="<?=e($settings['board']??'')?>"></div>
                <div class="form-group"><label>EIIN নম্বর</label>
                    <input type="text" name="eiin" class="form-control" value="<?=e($settings['eiin']??'')?>"></div>
                <div class="form-group"><label>শিক্ষাবর্ষ</label>
                    <input type="text" name="academic_year" class="form-control" value="<?=e($settings['academic_year']??date('Y'))?>"></div>
                <div class="form-group"><label>ফোন নম্বর</label>
                    <input type="text" name="phone" class="form-control" value="<?=e($settings['phone']??'')?>"></div>
                <div class="form-group"><label>ইমেইল</label>
                    <input type="email" name="email" class="form-control" value="<?=e($settings['email']??'')?>"></div>
                <div class="form-group" style="grid-column:1/-1;"><label>ঠিকানা</label>
                    <textarea name="address" class="form-control" rows="2"><?=e($settings['address']??'')?></textarea></div>
                <div class="form-group" style="grid-column:1/-1;">
                    <label>প্রতিষ্ঠানের লোগো</label>
                    <?php $currentLogo = $settings['logo'] ?? ''; ?>
                    <?php if ($currentLogo): ?>
                    <div style="display:flex;align-items:center;gap:16px;margin-bottom:10px;padding:12px;background:#f8f9fa;border-radius:8px;border:1.5px solid var(--border);">
                        <img src="<?= str_starts_with($currentLogo,'http') ? e($currentLogo) : UPLOAD_URL.e($currentLogo) ?>"
                             alt="current logo"
                             style="width:60px;height:60px;object-fit:contain;border-radius:8px;border:1px solid var(--border);background:#fff;padding:4px;">
                        <div>
                            <div style="font-size:13px;font-weight:600;">বর্তমান লোগো</div>
                            <div style="font-size:11px;color:var(--text-muted);margin-top:2px;"><?= e($currentLogo) ?></div>
                            <label style="display:inline-flex;align-items:center;gap:6px;margin-top:6px;font-size:12px;color:var(--danger);cursor:pointer;">
                                <input type="checkbox" name="remove_logo" value="1"> লোগো মুছে ফেলুন
                            </label>
                        </div>
                    </div>
                    <?php endif; ?>
                    <input type="file" name="logo" class="form-control" accept="image/png,image/jpeg,image/gif,image/webp,image/svg+xml"
                           onchange="previewLogo(this)">
                    <div id="logoPreviewWrap" style="display:none;margin-top:8px;">
                        <div style="font-size:12px;color:var(--text-muted);margin-bottom:4px;">নতুন লোগো প্রিভিউ:</div>
                        <img id="logoPreviewImg" src="" alt="preview"
                             style="width:60px;height:60px;object-fit:contain;border-radius:8px;border:1px solid var(--border);background:#fff;padding:4px;">
                    </div>
                    <small style="color:var(--text-muted);font-size:12px;">PNG, JPG, SVG গ্রহণযোগ্য।</small>
                </div>
            </div>
            <button type="submit" class="btn btn-primary mt-16"><i class="fas fa-save"></i> সংরক্ষণ করুন</button>
        </form>
    </div>
</div>

<div class="card mb-16">
    <div class="card-header"><span class="card-title"><i class="fas fa-robot"></i> AI ও SMS সেটিংস</span></div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="csrf" value="<?=getCsrfToken()?>">
            <input type="hidden" name="save_settings" value="1">
            <div class="form-group mb-16">
                <label>Google Gemini AI API Key</label>
                <input type="password" name="ai_api_key" class="form-control" value="<?=e($settings['ai_api_key']??'')?>" placeholder="AIza...">
                <small style="color:var(--text-muted);font-size:12px;">
                    AI সহকারী ব্যবহারের জন্য
                    <a href="https://aistudio.google.com/app/apikey" target="_blank">aistudio.google.com</a>
                    থেকে <strong>বিনামূল্যে</strong> API Key নিন। (প্রতিদিন ১৫০০ request বিনামূল্যে)
                </small>
            </div>
            <div class="form-group mb-16">
                <label>SMS API Key</label>
                <input type="text" name="sms_api_key" class="form-control" value="<?=e($settings['sms_api_key']??'')?>" placeholder="SSL Wireless / BulkSMSBD API Key">
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> সংরক্ষণ করুন</button>
        </form>
    </div>
</div>
</div>

<div>
<div class="card mb-16">
    <div class="card-header"><span class="card-title"><i class="fas fa-lock"></i> পাসওয়ার্ড পরিবর্তন</span></div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="csrf" value="<?=getCsrfToken()?>">
            <input type="hidden" name="change_password" value="1">
            <div class="form-group mb-16"><label>বর্তমান পাসওয়ার্ড</label>
                <input type="password" name="old_password" class="form-control" required></div>
            <div class="form-group mb-16"><label>নতুন পাসওয়ার্ড</label>
                <input type="password" name="new_password" class="form-control" required minlength="6"></div>
            <div class="form-group mb-16"><label>পাসওয়ার্ড নিশ্চিত করুন</label>
                <input type="password" name="confirm_password" class="form-control" required></div>
            <button type="submit" class="btn btn-warning"><i class="fas fa-key"></i> পাসওয়ার্ড পরিবর্তন করুন</button>
        </form>
    </div>
</div>

<div class="card mb-16">
    <div class="card-header"><span class="card-title"><i class="fas fa-users-cog"></i> ব্যবহারকারী তালিকা</span></div>
    <div class="card-body" style="padding:0;">
        <table>
            <thead><tr><th>নাম</th><th>ফোন/Username</th><th>ভূমিকা</th><th>সর্বশেষ লগইন</th></tr></thead>
            <tbody>
                <?php $users = $db->query("SELECT u.*, r.role_name FROM users u JOIN roles r ON u.role_id=r.id WHERE u.is_active=1 ORDER BY u.id")->fetchAll();
                foreach ($users as $u): ?>
                <tr>
                    <td style="font-size:13px;font-weight:600;"><?=e($u['name_bn']??$u['name'])?></td>
                    <td style="font-size:12px;color:var(--text-muted);"><?=e($u['phone']??$u['username'])?></td>
                    <td><span class="badge badge-info" style="font-size:10px;"><?=e($u['role_name'])?></span></td>
                    <td style="font-size:11px;color:var(--text-muted);"><?=$u['last_login']?banglaDate($u['last_login']):'কখনো না'?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header"><span class="card-title"><i class="fas fa-history"></i> সাম্প্রতিক কার্যক্রম</span></div>
    <div class="card-body" style="padding:0;">
        <table>
            <thead><tr><th>কার্যক্রম</th><th>মডিউল</th><th>সময়</th></tr></thead>
            <tbody>
                <?php $logs = $db->query("SELECT al.*, u.name_bn FROM activity_logs al LEFT JOIN users u ON al.user_id=u.id ORDER BY al.created_at DESC LIMIT 10")->fetchAll();
                foreach ($logs as $l): ?>
                <tr>
                    <td style="font-size:12px;"><?=e($l['name_bn']??'')?> — <?=e($l['action'])?></td>
                    <td style="font-size:11px;"><span class="badge badge-secondary"><?=e($l['module'])?></span></td>
                    <td style="font-size:11px;color:var(--text-muted);"><?=banglaDate($l['created_at'])?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</div>
</div>
<script>
function previewLogo(input) {
    const wrap = document.getElementById('logoPreviewWrap');
    const img  = document.getElementById('logoPreviewImg');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => { img.src = e.target.result; wrap.style.display = 'block'; };
        reader.readAsDataURL(input.files[0]);
    } else {
        wrap.style.display = 'none';
    }
}
</script>
<?php require_once 'includes/footer.php'; ?>
