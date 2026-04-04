<?php
require_once 'includes/functions.php';
startSession();

if (isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        $db = getDB();
        $stmt = $db->prepare("SELECT u.*, r.role_name, r.role_slug FROM users u JOIN roles r ON u.role_id = r.id WHERE (u.username = ? OR u.phone = ?) AND u.is_active = 1");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['role_slug'] = $user['role_slug'];
            $_SESSION['role_id'] = $user['role_id'];

            // Update last login
            $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
            logActivity($user['id'], 'login', 'auth', 'লগইন সফল');

            // Redirect by role
            if ($user['role_slug'] === 'parent') {
                header('Location: ' . BASE_URL . '/modules/parent/portal.php');
            } elseif ($user['role_slug'] === 'student') {
                header('Location: ' . BASE_URL . '/modules/student/portal.php');
            } else {
                header('Location: ' . BASE_URL . '/index.php');
            }
            exit;
        } else {
            $error = 'ব্যবহারকারীর নাম বা পাসওয়ার্ড ভুল।';
        }
    } else {
        $error = 'সকল তথ্য পূরণ করুন।';
    }
}

$instituteName = getSetting('institute_name', APP_NAME);
?>
<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>লগইন | <?= e($instituteName) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body {
    font-family: 'Hind Siliguri', sans-serif;
    min-height: 100vh;
    background: linear-gradient(135deg, #0d2137 0%, #1a5276 50%, #0e6655 100%);
    display: flex; align-items: center; justify-content: center;
    padding: 20px;
}
.login-container {
    display: grid; grid-template-columns: 1fr 1fr;
    background: #fff; border-radius: 20px;
    box-shadow: 0 25px 50px rgba(0,0,0,.3);
    overflow: hidden; max-width: 900px; width: 100%;
}
.login-banner {
    background: linear-gradient(135deg, #1a5276, #0d2137);
    padding: 48px 40px;
    display: flex; flex-direction: column; justify-content: center; align-items: center;
    text-align: center; color: #fff;
    position: relative; overflow: hidden;
}
.login-banner::before {
    content: ''; position: absolute; inset: 0;
    background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.04'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
}
.banner-icon { font-size: 64px; margin-bottom: 20px; color: #f0a500; }
.banner-title { font-size: 24px; font-weight: 700; margin-bottom: 10px; }
.banner-sub { font-size: 14px; opacity: .75; margin-bottom: 30px; line-height: 1.6; }
.banner-features { list-style: none; text-align: left; }
.banner-features li { padding: 6px 0; font-size: 13px; display: flex; align-items: center; gap: 10px; opacity: .85; }
.banner-features i { color: #f0a500; }

.login-form-side { padding: 48px 40px; }
.login-logo { margin-bottom: 32px; }
.login-logo h1 { font-size: 22px; font-weight: 700; color: #0d2137; }
.login-logo p { font-size: 13px; color: #718096; margin-top: 4px; }
.form-title { font-size: 18px; font-weight: 700; color: #1a202c; margin-bottom: 24px; }
.form-group { margin-bottom: 18px; }
.form-group label { display: block; font-size: 13px; font-weight: 600; color: #2d3748; margin-bottom: 7px; }
.input-wrap { position: relative; }
.input-wrap i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #a0aec0; }
.input-wrap input {
    width: 100%; padding: 11px 12px 11px 38px;
    border: 1.5px solid #e2e8f0; border-radius: 9px;
    font-family: 'Hind Siliguri', sans-serif; font-size: 14px;
    color: #1a202c; outline: none; transition: all .2s;
}
.input-wrap input:focus { border-color: #2471a3; box-shadow: 0 0 0 3px rgba(36,113,163,.15); }
.btn-login {
    width: 100%; padding: 13px; background: #1a5276;
    color: #fff; border: none; border-radius: 9px; font-family: 'Hind Siliguri', sans-serif;
    font-size: 16px; font-weight: 700; cursor: pointer; transition: background .2s;
    margin-top: 8px;
}
.btn-login:hover { background: #0d2137; }
.error-box { background: #fff5f5; border: 1px solid #fed7d7; color: #c53030;
    padding: 10px 14px; border-radius: 8px; font-size: 13px; margin-bottom: 16px;
    display: flex; align-items: center; gap: 8px; }
.login-links { margin-top: 20px; text-align: center; font-size: 13px; color: #718096; }
.login-links a { color: #2471a3; text-decoration: none; font-weight: 600; }
.portal-tabs { display: flex; gap: 8px; margin-bottom: 24px; }
.portal-tab {
    flex: 1; padding: 9px; text-align: center; border: 1.5px solid #e2e8f0;
    border-radius: 8px; cursor: pointer; font-size: 13px; color: #718096;
    transition: all .2s;
}
.portal-tab.active { border-color: #1a5276; background: #ebf5fb; color: #1a5276; font-weight: 700; }
@media (max-width: 700px) {
    .login-container { grid-template-columns: 1fr; }
    .login-banner { display: none; }
    .login-form-side { padding: 32px 24px; }
}
</style>
</head>
<body>
<div class="login-container">
    <div class="login-banner">
        <i class="fas fa-mosque banner-icon"></i>
        <h2 class="banner-title"><?= e($instituteName) ?></h2>
        <p class="banner-sub">ডিজিটাল শিক্ষা ব্যবস্থাপনা সিস্টেম<br>সকলের জন্য সহজ ও আধুনিক</p>
        <ul class="banner-features">
            <li><i class="fas fa-check-circle"></i> ছাত্র ভর্তি ও ব্যবস্থাপনা</li>
            <li><i class="fas fa-check-circle"></i> উপস্থিতি ট্র্যাকিং</li>
            <li><i class="fas fa-check-circle"></i> পরীক্ষা ও ফলাফল</li>
            <li><i class="fas fa-check-circle"></i> ফি সংগ্রহ (bKash/Nagad)</li>
            <li><i class="fas fa-check-circle"></i> অভিভাবক পোর্টাল</li>
            <li><i class="fas fa-check-circle"></i> AI সহকারী</li>
        </ul>
    </div>
    <div class="login-form-side">
        <div class="login-logo">
            <h1>স্বাগতম</h1>
            <p>আপনার অ্যাকাউন্টে প্রবেশ করুন</p>
        </div>

        <div class="portal-tabs">
            <div class="portal-tab active" onclick="setPortal('admin', this)"><i class="fas fa-user-shield"></i> অ্যাডমিন/শিক্ষক</div>
            <div class="portal-tab" onclick="setPortal('parent', this)"><i class="fas fa-users"></i> অভিভাবক</div>
            <div class="portal-tab" onclick="setPortal('student', this)"><i class="fas fa-user-graduate"></i> ছাত্র</div>
        </div>

        <?php if ($error): ?>
        <div class="error-box"><i class="fas fa-exclamation-circle"></i> <?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <p class="form-title">লগইন করুন</p>
            <div class="form-group">
                <label>ব্যবহারকারীর নাম / ফোন নম্বর</label>
                <div class="input-wrap">
                    <i class="fas fa-user"></i>
                    <input type="text" name="username" placeholder="username বা 01XXXXXXXXX" value="<?= e($_POST['username'] ?? '') ?>" required>
                </div>
            </div>
            <div class="form-group">
                <label>পাসওয়ার্ড</label>
                <div class="input-wrap">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="password" placeholder="••••••••" required id="pwdField">
                </div>
            </div>
            <button type="submit" class="btn-login">
                <i class="fas fa-sign-in-alt"></i> প্রবেশ করুন
            </button>
        </form>

        <div class="login-links">
            <p style="margin-top:16px;background:#f7fafc;padding:12px;border-radius:8px;font-size:12px;line-height:1.8;">
                <strong>ডেমো লগইন:</strong><br>
                অ্যাডমিন: <code>admin</code> / <code>password</code><br>
                অভিভাবক পোর্টালের জন্য ফোন নম্বর দিয়ে লগইন করুন
            </p>
        </div>
    </div>
</div>
<script>
function setPortal(type, el) {
    document.querySelectorAll('.portal-tab').forEach(t => t.classList.remove('active'));
    el.classList.add('active');
    const userField = document.querySelector('input[name="username"]');
    const hints = {
        admin: 'username বা 01XXXXXXXXX',
        parent: 'অভিভাবকের ফোন নম্বর',
        student: 'ছাত্রের ID বা ফোন নম্বর'
    };
    userField.placeholder = hints[type];
}
</script>
</body>
</html>
