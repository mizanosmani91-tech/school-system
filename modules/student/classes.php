<?php
require_once '../../includes/functions.php';
requireLogin(['super_admin','principal']);
$pageTitle = 'শ্রেণী ও বিভাগ';
$db = getDB();

$msg = ''; $err = '';

// ===== বিভাগ যোগ =====
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='add_division') {
    $name_bn = trim($_POST['division_name_bn']??'');
    $name    = trim($_POST['division_name']??'') ?: $name_bn;
    $code    = strtoupper(trim($_POST['division_code']??''));
    $sort    = (int)($_POST['sort_order']??0);
    if ($name_bn) {
        $db->prepare("INSERT INTO divisions (division_name, division_name_bn, division_code, sort_order, is_active) VALUES (?,?,?,?,1)")
           ->execute([$name, $name_bn, $code, $sort]);
        $msg = 'বিভাগ সফলভাবে যোগ হয়েছে।';
    } else { $err = 'বিভাগের নাম দিন।'; }
}

// ===== বিভাগ মুছুন =====
if (isset($_GET['delete_division'])) {
    $id = (int)$_GET['delete_division'];
    // check if any class uses it
    $cnt = $db->prepare("SELECT COUNT(*) FROM classes WHERE division_id=? AND is_active=1");
    $cnt->execute([$id]);
    if ($cnt->fetchColumn() > 0) {
        $err = 'এই বিভাগে শ্রেণী আছে, আগে শ্রেণী মুছুন।';
    } else {
        $db->prepare("UPDATE divisions SET is_active=0 WHERE id=?")->execute([$id]);
        $msg = 'বিভাগ মুছে ফেলা হয়েছে।';
    }
}

// ===== শ্রেণী যোগ =====
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='add_class') {
    $div_id  = (int)($_POST['division_id']??0);
    $name_bn = trim($_POST['class_name_bn']??'');
    $numeric = (int)($_POST['class_numeric']??0);
    if ($div_id && $name_bn) {
        $db->prepare("INSERT INTO classes (division_id, class_name, class_name_bn, class_numeric, is_active) VALUES (?,?,?,?,1)")
           ->execute([$div_id, $name_bn, $name_bn, $numeric]);
        $msg = 'শ্রেণী সফলভাবে যোগ হয়েছে।';
    } else { $err = 'বিভাগ ও শ্রেণীর নাম দিন।'; }
}

// ===== শ্রেণী মুছুন =====
if (isset($_GET['delete_class'])) {
    $id = (int)$_GET['delete_class'];
    $db->prepare("UPDATE classes SET is_active=0 WHERE id=?")->execute([$id]);
    $msg = 'শ্রেণী মুছে ফেলা হয়েছে।';
}

// ===== শাখা যোগ =====
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='add_section') {
    $class_id = (int)($_POST['class_id']??0);
    $name     = trim($_POST['section_name']??'');
    if ($class_id && $name) {
        $db->prepare("INSERT INTO sections (class_id, section_name) VALUES (?,?)")
           ->execute([$class_id, $name]);
        $msg = 'শাখা সফলভাবে যোগ হয়েছে।';
    } else { $err = 'শ্রেণী ও শাখার নাম দিন।'; }
}

// ===== শাখা মুছুন =====
if (isset($_GET['delete_section'])) {
    $id = (int)$_GET['delete_section'];
    $chk = $db->prepare("SELECT COUNT(*) FROM students WHERE section_id=? AND status='active'");
    $chk->execute([$id]);
    if ($chk->fetchColumn() > 0) {
        $err = 'এই শাখায় সক্রিয় ছাত্র আছে। আগে ছাত্রদের অন্য শাখায় সরান।';
    } else {
        $db->prepare("DELETE FROM sections WHERE id=?")->execute([$id]);
        $msg = 'শাখা মুছে ফেলা হয়েছে।';
    }
}

// ===== Data load =====
$divisions = $db->query("SELECT * FROM divisions WHERE is_active=1 ORDER BY sort_order, id")->fetchAll();
$classes   = $db->query("SELECT c.*, d.division_name_bn FROM classes c LEFT JOIN divisions d ON c.division_id=d.id WHERE c.is_active=1 ORDER BY c.division_id, c.class_numeric")->fetchAll();
$sections  = [];
foreach ($classes as $c) {
    $s = $db->prepare("SELECT * FROM sections WHERE class_id=? ORDER BY section_name");
    $s->execute([$c['id']]);
    $sections[$c['id']] = $s->fetchAll();
}

// classes grouped by division
$byDivision = [];
foreach ($classes as $c) {
    $byDivision[$c['division_id']][] = $c;
}

require_once '../../includes/header.php';
?>

<div class="section-header">
    <h2 class="section-title"><i class="fas fa-sitemap"></i> বিভাগ, শ্রেণী ও শাখা</h2>
</div>

<?php if ($msg): ?>
<div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= e($msg) ?></div>
<?php endif; ?>
<?php if ($err): ?>
<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= e($err) ?></div>
<?php endif; ?>

<!-- ===== তিনটি Add Form ===== -->
<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:24px;">

    <!-- নতুন বিভাগ -->
    <div class="card">
        <div class="card-header">
            <span class="card-title"><i class="fas fa-layer-group"></i> নতুন বিভাগ যোগ</span>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="add_division">
                <div class="form-group" style="margin-bottom:12px;">
                    <label>বিভাগের নাম (বাংলায়) <span style="color:red">*</span></label>
                    <input type="text" name="division_name_bn" class="form-control" placeholder="যেমন: জেনারেল বিভাগ" required>
                </div>
                <div class="form-group" style="margin-bottom:12px;">
                    <label>বিভাগের নাম (ইংরেজি)</label>
                    <input type="text" name="division_name" class="form-control" placeholder="যেমন: General">
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:12px;">
                    <div class="form-group">
                        <label>কোড</label>
                        <input type="text" name="division_code" class="form-control" placeholder="GEN" maxlength="10">
                    </div>
                    <div class="form-group">
                        <label>ক্রম</label>
                        <input type="number" name="sort_order" class="form-control" value="0" min="0">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%"><i class="fas fa-plus"></i> বিভাগ যোগ করুন</button>
            </form>
        </div>
    </div>

    <!-- নতুন শ্রেণী -->
    <div class="card">
        <div class="card-header">
            <span class="card-title"><i class="fas fa-chalkboard"></i> নতুন শ্রেণী যোগ</span>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="add_class">
                <div class="form-group" style="margin-bottom:12px;">
                    <label>বিভাগ নির্বাচন <span style="color:red">*</span></label>
                    <select name="division_id" class="form-control" required>
                        <option value="">-- বিভাগ বেছে নিন --</option>
                        <?php foreach ($divisions as $d): ?>
                        <option value="<?= $d['id'] ?>"><?= e($d['division_name_bn']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom:12px;">
                    <label>শ্রেণীর নাম <span style="color:red">*</span></label>
                    <input type="text" name="class_name_bn" class="form-control" placeholder="যেমন: প্রথম শ্রেণী" required>
                </div>
                <div class="form-group" style="margin-bottom:12px;">
                    <label>ক্রমিক নম্বর</label>
                    <input type="number" name="class_numeric" class="form-control" placeholder="1, 2, 3..." value="0">
                </div>
                <button type="submit" class="btn btn-success" style="width:100%"><i class="fas fa-plus"></i> শ্রেণী যোগ করুন</button>
            </form>
        </div>
    </div>

    <!-- নতুন শাখা -->
    <div class="card">
        <div class="card-header">
            <span class="card-title"><i class="fas fa-code-branch"></i> নতুন শাখা যোগ</span>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="add_section">
                <div class="form-group" style="margin-bottom:12px;">
                    <label>বিভাগ <span style="color:red">*</span></label>
                    <select name="filter_div" class="form-control" onchange="filterClasses(this.value)">
                        <option value="">-- সব বিভাগ --</option>
                        <?php foreach ($divisions as $d): ?>
                        <option value="<?= $d['id'] ?>"><?= e($d['division_name_bn']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom:12px;">
                    <label>শ্রেণী নির্বাচন <span style="color:red">*</span></label>
                    <select name="class_id" class="form-control" required id="classSelect">
                        <option value="">-- শ্রেণী বেছে নিন --</option>
                        <?php foreach ($classes as $c): ?>
                        <option value="<?= $c['id'] ?>" data-div="<?= $c['division_id'] ?>">
                            <?= e($c['division_name_bn']) ?> → <?= e($c['class_name_bn']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom:12px;">
                    <label>শাখার নাম <span style="color:red">*</span></label>
                    <input type="text" name="section_name" class="form-control" placeholder="যেমন: ক, খ, A, B" required>
                </div>
                <button type="submit" class="btn btn-info" style="width:100%"><i class="fas fa-plus"></i> শাখা যোগ করুন</button>
            </form>
        </div>
    </div>
</div>

<!-- ===== বিভাগ তালিকা ===== -->
<?php foreach ($divisions as $div): ?>
<?php
$divClasses = $byDivision[$div['id']] ?? [];
$divColors  = ['#1a5276','#1a8a3c','#8e44ad','#c0392b','#d68910','#0e6655'];
$ci = ($div['id'] - 1) % count($divColors);
$color = $divColors[$ci];
?>
<div class="card mb-16">
    <div class="card-header" style="background:<?= $color ?>;color:#fff;display:flex;align-items:center;justify-content:space-between;">
        <span style="font-size:16px;font-weight:700;">
            <i class="fas fa-layer-group"></i>
            <?= e($div['division_name_bn']) ?>
            <?php if ($div['division_code']): ?>
            <span style="background:rgba(255,255,255,.2);border-radius:4px;padding:2px 8px;font-size:11px;margin-left:8px;"><?= e($div['division_code']) ?></span>
            <?php endif; ?>
        </span>
        <div style="display:flex;align-items:center;gap:8px;">
            <span style="font-size:13px;opacity:.85;"><?= toBanglaNumber(count($divClasses)) ?>টি শ্রেণী</span>
            <a href="?delete_division=<?= $div['id'] ?>" onclick="return confirm('বিভাগ মুছবেন?')"
               style="background:rgba(255,255,255,.15);color:#fff;padding:4px 10px;border-radius:6px;font-size:12px;text-decoration:none;">
                <i class="fas fa-trash"></i>
            </a>
        </div>
    </div>
    <div class="card-body" style="padding:0;">
        <?php if (empty($divClasses)): ?>
        <div style="text-align:center;padding:24px;color:#718096;">
            <i class="fas fa-inbox" style="font-size:24px;opacity:.3;display:block;margin-bottom:8px;"></i>
            এই বিভাগে কোনো শ্রেণী নেই
        </div>
        <?php else: ?>
        <table style="width:100%;border-collapse:collapse;font-size:14px;">
            <thead>
                <tr style="background:#f7fafc;">
                    <th style="padding:10px 16px;text-align:left;font-size:12px;color:#718096;font-weight:600;border-bottom:1px solid #e2e8f0;">#</th>
                    <th style="padding:10px 16px;text-align:left;font-size:12px;color:#718096;font-weight:600;border-bottom:1px solid #e2e8f0;">শ্রেণীর নাম</th>
                    <th style="padding:10px 16px;text-align:left;font-size:12px;color:#718096;font-weight:600;border-bottom:1px solid #e2e8f0;">শাখাসমূহ</th>
                    <th style="padding:10px 16px;text-align:left;font-size:12px;color:#718096;font-weight:600;border-bottom:1px solid #e2e8f0;">ছাত্র</th>
                    <th style="padding:10px 16px;text-align:right;font-size:12px;color:#718096;font-weight:600;border-bottom:1px solid #e2e8f0;">অ্যাকশন</th>
                </tr>
            </thead>
            <tbody>
                <?php $i=1; foreach ($divClasses as $c):
                    $stuCount = $db->prepare("SELECT COUNT(*) FROM students WHERE class_id=? AND status='active'");
                    $stuCount->execute([$c['id']]);
                    $stuCnt = $stuCount->fetchColumn();
                ?>
                <tr style="border-bottom:1px solid #e2e8f0;">
                    <td style="padding:12px 16px;color:#718096;"><?= toBanglaNumber($i++) ?></td>
                    <td style="padding:12px 16px;font-weight:600;color:#1a202c;"><?= e($c['class_name_bn']) ?></td>
                    <td style="padding:12px 16px;">
                        <?php if (empty($sections[$c['id']])): ?>
                        <span style="color:#a0aec0;font-size:12px;font-style:italic;">কোনো শাখা নেই</span>
                        <?php else: foreach ($sections[$c['id']] as $sec): ?>
                        <span style="display:inline-flex;align-items:center;background:<?= $color ?>18;color:<?= $color ?>;border:1px solid <?= $color ?>44;border-radius:20px;padding:2px 10px;font-size:12px;font-weight:600;margin:2px;">
                            <?= e($sec['section_name']) ?>
                            <a href="?delete_section=<?= $sec['id'] ?>" onclick="return confirm('শাখা মুছবেন?')" style="color:<?= $color ?>;margin-left:6px;font-weight:700;text-decoration:none;">&times;</a>
                        </span>
                        <?php endforeach; endif; ?>
                    </td>
                    <td style="padding:12px 16px;">
                        <span style="background:#ebf8ff;color:#2b6cb0;border-radius:20px;padding:2px 10px;font-size:12px;font-weight:600;">
                            <?= toBanglaNumber($stuCnt) ?> জন
                        </span>
                    </td>
                    <td style="padding:12px 16px;text-align:right;">
                        <a href="?delete_class=<?= $c['id'] ?>" onclick="return confirm('শ্রেণী মুছবেন? এই শ্রেণীর সব ছাত্রের তথ্য প্রভাবিত হবে।')"
                           class="btn btn-danger btn-xs"><i class="fas fa-trash"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>

<?php if (empty($divisions)): ?>
<div class="card">
    <div class="card-body" style="text-align:center;padding:48px;color:#718096;">
        <i class="fas fa-layer-group" style="font-size:48px;opacity:.2;display:block;margin-bottom:16px;"></i>
        <p>কোনো বিভাগ নেই। উপরে থেকে নতুন বিভাগ যোগ করুন।</p>
    </div>
</div>
<?php endif; ?>

<script>
function filterClasses(divId) {
    const sel = document.getElementById('classSelect');
    Array.from(sel.options).forEach(opt => {
        if (!opt.value) return;
        opt.style.display = (!divId || opt.dataset.div === divId) ? '' : 'none';
    });
    sel.value = '';
}
</script>

<?php require_once '../../includes/footer.php'; ?>
