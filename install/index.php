<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ইনস্টলেশন | স্কুল ম্যানেজমেন্ট সিস্টেম</title>
<link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;600;700&display=swap" rel="stylesheet">
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:'Hind Siliguri',sans-serif; background:#f0f4f8; display:flex; align-items:center; justify-content:center; min-height:100vh; padding:20px; }
.box { background:#fff; border-radius:16px; padding:40px; max-width:560px; width:100%; box-shadow:0 10px 30px rgba(0,0,0,.1); }
h1 { color:#1a5276; font-size:22px; margin-bottom:6px; }
p { color:#718096; font-size:13px; margin-bottom:24px; }
.form-group { margin-bottom:16px; }
label { display:block; font-size:13px; font-weight:600; margin-bottom:6px; }
input { width:100%; padding:10px 12px; border:1.5px solid #e2e8f0; border-radius:8px; font-family:inherit; font-size:14px; }
input:focus { outline:none; border-color:#1a5276; }
.btn { width:100%; padding:13px; background:#1a5276; color:#fff; border:none; border-radius:8px; font-family:inherit; font-size:16px; font-weight:700; cursor:pointer; margin-top:8px; }
.btn:hover { background:#0d2137; }
.alert { padding:12px 16px; border-radius:8px; margin-bottom:20px; font-size:14px; }
.alert-success { background:#d4edda; color:#155724; }
.alert-danger { background:#f8d7da; color:#721c24; }
.step { display:none; }
.step.active { display:block; }
.step-indicators { display:flex; gap:8px; margin-bottom:28px; }
.step-dot { flex:1; height:6px; border-radius:3px; background:#e2e8f0; transition:background .3s; }
.step-dot.done { background:#1a5276; }
.checklist { list-style:none; margin:16px 0; }
.checklist li { padding:6px 0; font-size:13px; display:flex; align-items:center; gap:8px; }
.checklist li.ok { color:#155724; }
.checklist li.fail { color:#721c24; }
</style>
</head>
<body>
<?php
$step = (int)($_GET['step'] ?? 1);
$error = ''; $success = '';

// Requirements check
$checks = [
    'PHP 7.4+' => version_compare(PHP_VERSION, '7.4.0', '>='),
    'PDO MySQL' => extension_loaded('pdo_mysql'),
    'cURL' => extension_loaded('curl'),
    'mbstring' => extension_loaded('mbstring'),
    'GD Library' => extension_loaded('gd'),
];
$allOk = !in_array(false, $checks);

if ($step === 2 && $_SERVER['REQUEST_METHOD']==='POST') {
    $host = $_POST['db_host']??'localhost';
    $user = $_POST['db_user']??'root';
    $pass = $_POST['db_pass']??'';
    $name = $_POST['db_name']??'school_db';

    try {
        $pdo = new PDO("mysql:host=$host;charset=utf8mb4",$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `$name`");

        // Run SQL
        $sql = file_get_contents(__DIR__.'/database.sql');
        // Remove comments and split
        $sql = preg_replace('/--.*$/m','',$sql);
        $statements = array_filter(array_map('trim',explode(';',$sql)));
        foreach ($statements as $stmt) { if ($stmt) $pdo->exec($stmt); }

        // Update config
        $configContent = file_get_contents(__DIR__.'/../includes/config.php');
        $configContent = preg_replace("/define\('DB_HOST',.*?\);/", "define('DB_HOST', '$host');", $configContent);
        $configContent = preg_replace("/define\('DB_USER',.*?\);/", "define('DB_USER', '$user');", $configContent);
        $configContent = preg_replace("/define\('DB_PASS',.*?\);/", "define('DB_PASS', '$pass');", $configContent);
        $configContent = preg_replace("/define\('DB_NAME',.*?\);/", "define('DB_NAME', '$name');", $configContent);
        file_put_contents(__DIR__.'/../includes/config.php', $configContent);

        header('Location: index.php?step=3'); exit;
    } catch (Exception $e) {
        $error = 'ডাটাবেস সংযোগ ব্যর্থ: '.$e->getMessage();
    }
}
?>
<div class="box">
    <div style="text-align:center;margin-bottom:24px;">
        <div style="font-size:48px;margin-bottom:8px;">🕌</div>
        <h1>স্কুল/মাদ্রাসা ম্যানেজমেন্ট সিস্টেম</h1>
        <p>ইনস্টলেশন সেটআপ — v1.0</p>
    </div>

    <div class="step-indicators">
        <div class="step-dot <?=$step>=1?'done':''?>"></div>
        <div class="step-dot <?=$step>=2?'done':''?>"></div>
        <div class="step-dot <?=$step>=3?'done':''?>"></div>
    </div>

    <?php if ($error): ?><div class="alert alert-danger"><?=htmlspecialchars($error)?></div><?php endif; ?>

    <?php if ($step===1): ?>
    <h2 style="font-size:17px;margin-bottom:16px;">ধাপ ১: সিস্টেম যাচাই</h2>
    <ul class="checklist">
        <?php foreach ($checks as $name => $ok): ?>
        <li class="<?=$ok?'ok':'fail'?>">
            <?=$ok?'✓ ':'✗ '?> <?=htmlspecialchars($name)?> <?=$ok?'':' — প্রয়োজনীয়!'?>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php if ($allOk): ?>
    <a href="?step=2"><button class="btn">পরবর্তী ধাপ →</button></a>
    <?php else: ?>
    <div class="alert alert-danger">কিছু প্রয়োজনীয়তা পূরণ হয়নি। PHP সেটিংস ঠিক করুন।</div>
    <?php endif; ?>

    <?php elseif ($step===2): ?>
    <h2 style="font-size:17px;margin-bottom:16px;">ধাপ ২: ডাটাবেস সেটআপ</h2>
    <form method="POST">
        <div class="form-group"><label>MySQL Host</label><input type="text" name="db_host" value="localhost" required></div>
        <div class="form-group"><label>Username</label><input type="text" name="db_user" value="root" required></div>
        <div class="form-group"><label>Password</label><input type="password" name="db_pass"></div>
        <div class="form-group"><label>Database Name</label><input type="text" name="db_name" value="school_db" required></div>
        <button type="submit" class="btn">ডাটাবেস তৈরি করুন →</button>
    </form>

    <?php elseif ($step===3): ?>
    <div style="text-align:center;">
        <div style="font-size:64px;margin-bottom:16px;">🎉</div>
        <h2 style="font-size:20px;color:#155724;margin-bottom:12px;">ইনস্টলেশন সম্পন্ন!</h2>
        <p style="margin-bottom:20px;">সিস্টেম সফলভাবে ইনস্টল হয়েছে।</p>
        <div class="alert alert-success">
            <strong>ডিফল্ট লগইন:</strong><br>
            Username: <code>admin</code><br>
            Password: <code>password</code>
        </div>
        <a href="../login.php"><button class="btn">লগইন করুন →</button></a>
        <p style="margin-top:16px;font-size:12px;color:#718096;">নিরাপত্তার জন্য <code>install/</code> ফোল্ডারটি মুছে ফেলুন।</p>
    </div>
    <?php endif; ?>
</div>
</body>
</html>
