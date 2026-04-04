<?php
require_once '../../includes/functions.php';
requireLogin();
$pageTitle = 'শ্রেণী ও বিভাগ';
$db = getDB();

$msg = '';
$err = '';

// Add Class
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_class') {
    $name = trim($_POST['class_name_bn'] ?? '');
    $numeric = (int)($_POST['class_numeric'] ?? 0);
    $type = $_POST['class_type'] ?? 'general';
    if ($name) {
        $db->prepare("INSERT INTO classes (class_name_bn, class_numeric, class_type, is_active) VALUES (?,?,?,1)")
           ->execute([$name, $numeric, $type]);
        $msg = 'শ্রেণী সফলভাবে যোগ হয়েছে।';
    } else {
        $err = 'শ্রেণীর নাম দিন।';
    }
}

// Add Section
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_section') {
    $classId = (int)($_POST['class_id'] ?? 0);
    $name = trim($_POST['section_name'] ?? '');
    if ($classId && $name) {
        $db->prepare("INSERT INTO sections (class_id, section_name, is_active) VALUES (?,?,1)")
           ->execute([$classId, $name]);
        $msg = 'শাখা সফলভাবে যোগ হয়েছে।';
    } else {
        $err = 'শ্রেণী ও শাখার নাম দিন।';
    }
}

// Delete Class
if (isset($_GET['delete_class'])) {
    $id = (int)$_GET['delete_class'];
    $db->prepare("UPDATE classes SET is_active=0 WHERE id=?")->execute([$id]);
    $msg = 'শ্রেণী মুছে ফেলা হয়েছে।';
}

// Delete Section
if (isset($_GET['delete_section'])) {
    $id = (int)$_GET['delete_section'];
    $db->prepare("UPDATE sections SET is_active=0 WHERE id=?")->execute([$id]);
    $msg = 'শাখা মুছে ফেলা হয়েছে।';
}

$classes = $db->query("SELECT * FROM classes WHERE is_active=1 ORDER BY class_type, class_numeric")->fetchAll();

$sections = [];
foreach ($classes as $c) {
    $s = $db->prepare("SELECT * FROM sections WHERE class_id=? AND is_active=1 ORDER BY section_name");
    $s->execute([$c['id']]);
    $sections[$c['id']] = $s->fetchAll();
}

require_once '../../includes/header.php';
?>

<div class="section-header">
    <h2 class="section-title"><i class="fas fa-school"></i> শ্রেণী ও বিভাগ</h2>
</div>

<?php if ($msg): ?>
<div class="alert alert-success" style="padding:12px 16px;background:#f0fff4;border:1px solid #9ae6b4;border-radius:8px;color:#276749;margin-bottom:16px;">
    <i class="fas fa-check-circle"></i> <?= e($msg) ?>
</div>
<?php endif; ?>
<?php if ($err): ?>
<div class="alert alert-danger" style="padding:12px 16px;background:#fff5f5;border:1px solid #fc8181;border-radius:8px;color:#c53030;margin-bottom:16px;">
    <i class="fas fa-exclamation-circle"></i> <?= e($err) ?>
</div>
<?php endif; ?>

<div class="grid-2 mb-24">
    <!-- নতুন শ্রেণী যোগ -->
    <div class="card">
        <div class="card-header">
            <span class="card-title"><i class="fas fa-plus-circle"></i> নতুন শ্রেণী যোগ</span>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="add_class">
                <div class="form-group">
                    <label>বিভাগ</label>
                    <select name="class_type" class="form-control" required>
                        <option value="general">সাধারণ বিভাগ</option>
                        <option value="hifz">হিফজ বিভাগ</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>শ্রেণীর নাম (বাংলায়)</label>
                    <input type="text" name="class_name_bn" class="form-control" placeholder="যেমন: প্রথম শ্রেণী, কায়দা" required>
                </div>
                <div class="form-group">
                    <label>ক্রমিক নম্বর (সাজানোর জন্য)</label>
                    <input type="number" name="class_numeric" class="form-control" placeholder="যেমন: 1, 2, 3..." value="0">
                </div>
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-plus"></i> শ্রেণী যোগ করুন</button>
            </form>
        </div>
    </div>

    <!-- নতুন শাখা যোগ -->
    <div class="card">
        <div class="card-header">
            <span class="card-title"><i class="fas fa-code-branch"></i> নতুন শাখা যোগ</span>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="add_section">
                <div class="form-group">
                    <label>শ্রেণী নির্বাচন করুন</label>
                    <select name="class_id" class="form-control" required>
                        <option value="">-- শ্রেণী বেছে নিন --</option>
                        <?php foreach ($classes as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= e($c['class_name_bn']) ?> (<?= $c['class_type'] === 'hifz' ? 'হিফজ' : 'সাধারণ' ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>শাখার নাম</label>
                    <input type="text" name="section_name" class="form-control" placeholder="যেমন: ক, খ, গ, A, B">
                </div>
                <button type="submit" class="btn btn-success w-100"><i class="fas fa-plus"></i> শাখা যোগ করুন</button>
            </form>
        </div>
    </div>
</div>

<!-- সাধারণ বিভাগ -->
<div class="card mb-16">
    <div class="card-header">
        <span class="card-title"><i class="fas fa-graduation-cap"></i> সাধারণ বিভাগ</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>শ্রেণীর নাম</th>
                    <th>শাখাসমূহ</th>
                    <th>অ্যাকশন</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $general = array_filter($classes, fn($c) => $c['class_type'] === 'general');
                if (empty($general)): ?>
                <tr><td colspan="4" style="text-align:center;padding:20px;color:#718096;">কোনো শ্রেণী নেই</td></tr>
                <?php else: $i=1; foreach ($general as $c): ?>
                <tr>
                    <td><?= toBanglaNumber($i++) ?></td>
                    <td style="font-weight:600;"><?= e($c['class_name_bn']) ?></td>
                    <td>
                        <?php if (empty($sections[$c['id']])): ?>
                        <span style="color:#718096;font-size:12px;">কোনো শাখা নেই</span>
                        <?php else: foreach ($sections[$c['id']] as $sec): ?>
                        <span class="badge badge-primary" style="margin:2px;">
                            <?= e($sec['section_name']) ?>
                            <a href="?delete_section=<?= $sec['id'] ?>" onclick="return confirm('শাখা মুছবেন?')" style="color:white;margin-left:4px;">×</a>
                        </span>
                        <?php endforeach; endif; ?>
                    </td>
                    <td>
                        <a href="?delete_class=<?= $c['id'] ?>" onclick="return confirm('শ্রেণী মুছবেন?')" class="btn btn-danger btn-xs"><i class="fas fa-trash"></i></a>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- হিফজ বিভাগ -->
<div class="card">
    <div class="card-header">
        <span class="card-title"><i class="fas fa-book-open"></i> হিফজ বিভাগ</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>শ্রেণীর নাম</th>
                    <th>শাখাসমূহ</th>
                    <th>অ্যাকশন</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $hifz = array_filter($classes, fn($c) => $c['class_type'] === 'hifz');
                if (empty($hifz)): ?>
                <tr><td colspan="4" style="text-align:center;padding:20px;color:#718096;">কোনো শ্রেণী নেই</td></tr>
                <?php else: $i=1; foreach ($hifz as $c): ?>
                <tr>
                    <td><?= toBanglaNumber($i++) ?></td>
                    <td style="font-weight:600;"><?= e($c['class_name_bn']) ?></td>
                    <td>
                        <?php if (empty($sections[$c['id']])): ?>
                        <span style="color:#718096;font-size:12px;">কোনো শাখা নেই</span>
                        <?php else: foreach ($sections[$c['id']] as $sec): ?>
                        <span class="badge badge-success" style="margin:2px;">
                            <?= e($sec['section_name']) ?>
                            <a href="?delete_section=<?= $sec['id'] ?>" onclick="return confirm('শাখা মুছবেন?')" style="color:white;margin-left:4px;">×</a>
                        </span>
                        <?php endforeach; endif; ?>
                    </td>
                    <td>
                        <a href="?delete_class=<?= $c['id'] ?>" onclick="return confirm('শ্রেণী মুছবেন?')" class="btn btn-danger btn-xs"><i class="fas fa-trash"></i></a>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
