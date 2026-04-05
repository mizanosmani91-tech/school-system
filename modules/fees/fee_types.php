<?php
require_once '../../includes/functions.php';
requireLogin(['super_admin','principal']);
$pageTitle = 'ফী ধরন ম্যানেজমেন্ট';
$db = getDB();

$classes = $db->query("SELECT * FROM classes WHERE is_active=1 ORDER BY class_numeric")->fetchAll();

// Save / Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_fee_type'])) {
    if (!verifyCsrf($_POST['csrf'] ?? '')) die('CSRF');
    $id          = (int)($_POST['id'] ?? 0);
    $feeName     = trim($_POST['fee_name'] ?? '');
    $feeNameBn   = trim($_POST['fee_name_bn'] ?? '');
    $amount      = (float)($_POST['amount'] ?? 0);
    $category    = $_POST['fee_category'] ?? 'monthly';
    $appClass    = $_POST['applicable_class'] ?? 'all';
    $isActive    = isset($_POST['is_active']) ? 1 : 0;

    if (!$feeName || !$feeNameBn) {
        setFlash('danger', 'ফীর নাম (বাংলা ও ইংরেজি) আবশ্যক।');
    } else {
        if ($id) {
            $db->prepare("UPDATE fee_types SET fee_name=?, fee_name_bn=?, amount=?, fee_category=?, applicable_class=?, is_active=? WHERE id=?")
               ->execute([$feeName, $feeNameBn, $amount, $category, $appClass, $isActive, $id]);
            setFlash('success', 'ফী ধরন আপডেট হয়েছে।');
        } else {
            $db->prepare("INSERT INTO fee_types (fee_name, fee_name_bn, amount, fee_category, applicable_class, is_active) VALUES (?,?,?,?,?,?)")
               ->execute([$feeName, $feeNameBn, $amount, $category, $appClass, $isActive]);
            setFlash('success', 'নতুন ফী ধরন যোগ হয়েছে।');
        }
    }
    header('Location: fee_types.php'); exit;
}

// Toggle active
if (isset($_GET['toggle']) && in_array($_SESSION['role_slug'], ['super_admin','principal'])) {
    $toggleId = (int)$_GET['toggle'];
    $db->prepare("UPDATE fee_types SET is_active = NOT is_active WHERE id=?")->execute([$toggleId]);
    header('Location: fee_types.php'); exit;
}

// Delete
if (isset($_GET['delete']) && in_array($_SESSION['role_slug'], ['super_admin'])) {
    $delId = (int)$_GET['delete'];
    // Check if used
    $used = $db->prepare("SELECT COUNT(*) FROM fee_collections WHERE fee_type_id=?");
    $used->execute([$delId]);
    if ($used->fetchColumn() > 0) {
        setFlash('danger', 'এই ফী ধরনটি ব্যবহার হয়েছে, মুছা যাবে না। নিষ্ক্রিয় করুন।');
    } else {
        $db->prepare("DELETE FROM fee_types WHERE id=?")->execute([$delId]);
        setFlash('success', 'ফী ধরন মুছে ফেলা হয়েছে।');
    }
    header('Location: fee_types.php'); exit;
}

// Load all fee types
$feeTypes = $db->query("SELECT * FROM fee_types ORDER BY fee_category, fee_name_bn")->fetchAll();

// Edit mode
$editData = null;
if (isset($_GET['edit'])) {
    $editStmt = $db->prepare("SELECT * FROM fee_types WHERE id=?");
    $editStmt->execute([(int)$_GET['edit']]);
    $editData = $editStmt->fetch();
}

require_once '../../includes/header.php';

$categoryLabels = ['monthly'=>'মাসিক','yearly'=>'বার্ষিক','one_time'=>'একবার','optional'=>'ঐচ্ছিক'];
$categoryColors = ['monthly'=>'info','yearly'=>'primary','one_time'=>'warning','optional'=>'secondary'];
?>

<div class="section-header">
    <h2 class="section-title"><i class="fas fa-tags"></i> ফী ধরন ম্যানেজমেন্ট</h2>
    <button onclick="openModal('addFeeModal')" class="btn btn-primary btn-sm">
        <i class="fas fa-plus"></i> নতুন ফী যোগ করুন
    </button>
</div>

<!-- Summary Cards -->
<div class="stat-grid mb-16" style="grid-template-columns:repeat(4,1fr);">
    <?php
    $cats = ['monthly'=>'মাসিক','yearly'=>'বার্ষিক','one_time'=>'একবার','optional'=>'ঐচ্ছিক'];
    foreach ($cats as $cat => $label):
        $count = count(array_filter($feeTypes, fn($f) => $f['fee_category']===$cat && $f['is_active']));
    ?>
    <div class="stat-card blue">
        <div class="stat-icon"><i class="fas fa-tag"></i></div>
        <div>
            <div class="stat-value"><?=toBanglaNumber($count)?></div>
            <div class="stat-label"><?=$label?> ফী</div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Fee Types Table -->
<?php foreach ($cats as $cat => $label): 
    $catFees = array_filter($feeTypes, fn($f) => $f['fee_category']===$cat);
    if (empty($catFees)) continue;
?>
<div class="card mb-16">
    <div class="card-header">
        <span class="card-title">
            <span class="badge badge-<?=$categoryColors[$cat]?>"><?=$label?></span>
            <?=$label?> ফী সমূহ
        </span>
        <span style="font-size:12px;color:#718096;"><?=count($catFees)?> টি</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ফীর নাম (বাংলা)</th>
                    <th>ফীর নাম (ইংরেজি)</th>
                    <th style="text-align:center;">ডিফল্ট পরিমাণ</th>
                    <th style="text-align:center;">প্রযোজ্য শ্রেণী</th>
                    <th style="text-align:center;">অবস্থা</th>
                    <th style="text-align:center;">অ্যাকশন</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($catFees as $ft): ?>
                <tr style="<?=!$ft['is_active']?'opacity:.5;':''?>">
                    <td style="font-weight:600;"><?=e($ft['fee_name_bn'])?></td>
                    <td style="color:#718096;font-size:13px;"><?=e($ft['fee_name'])?></td>
                    <td style="text-align:center;font-weight:700;color:#27ae60;">
                        ৳<?=number_format($ft['amount'], 0)?>
                    </td>
                    <td style="text-align:center;">
                        <?php if ($ft['applicable_class'] === 'all'): ?>
                        <span class="badge badge-info">সব শ্রেণী</span>
                        <?php else: ?>
                        <span class="badge badge-warning" style="font-size:11px;"><?=e($ft['applicable_class'])?></span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:center;">
                        <a href="?toggle=<?=$ft['id']?>" onclick="return confirm('অবস্থা পরিবর্তন করবেন?')">
                            <?php if ($ft['is_active']): ?>
                            <span class="badge badge-success">সক্রিয়</span>
                            <?php else: ?>
                            <span class="badge badge-danger">নিষ্ক্রিয়</span>
                            <?php endif; ?>
                        </a>
                    </td>
                    <td style="text-align:center;">
                        <button onclick="editFee(<?=htmlspecialchars(json_encode($ft))?> )" class="btn btn-warning btn-xs">
                            <i class="fas fa-edit"></i>
                        </button>
                        <?php if ($_SESSION['role_slug'] === 'super_admin'): ?>
                        <a href="?delete=<?=$ft['id']?>" onclick="return confirm('মুছে ফেলবেন?')" class="btn btn-danger btn-xs">
                            <i class="fas fa-trash"></i>
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endforeach; ?>

<!-- Add/Edit Modal -->
<div class="modal-overlay" id="addFeeModal">
    <div class="modal-box" style="max-width:560px;">
        <div class="modal-header">
            <span style="font-weight:700;" id="modalTitle"><i class="fas fa-tag"></i> নতুন ফী যোগ করুন</span>
            <button onclick="closeModal('addFeeModal')" class="btn btn-outline btn-xs">✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="csrf" value="<?=getCsrfToken()?>">
            <input type="hidden" name="save_fee_type" value="1">
            <input type="hidden" name="id" id="feeId" value="">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label>ফীর নাম (বাংলায়) <span style="color:red;">*</span></label>
                        <input type="text" name="fee_name_bn" id="feeNameBn" class="form-control" placeholder="যেমন: টিউশন ফি" required>
                    </div>
                    <div class="form-group">
                        <label>ফীর নাম (ইংরেজিতে) <span style="color:red;">*</span></label>
                        <input type="text" name="fee_name" id="feeName" class="form-control" placeholder="e.g. Tuition Fee" required>
                    </div>
                    <div class="form-group">
                        <label>ডিফল্ট পরিমাণ (৳)</label>
                        <input type="number" name="amount" id="feeAmount" class="form-control" placeholder="0" min="0" step="0.01" value="0">
                        <small style="color:#718096;font-size:11px;">ফী সংগ্রহের সময় পরিবর্তন করা যাবে</small>
                    </div>
                    <div class="form-group">
                        <label>ফীর ধরন</label>
                        <select name="fee_category" id="feeCat" class="form-control">
                            <option value="monthly">মাসিক</option>
                            <option value="yearly">বার্ষিক</option>
                            <option value="one_time">একবার (ভর্তি/পরীক্ষা)</option>
                            <option value="optional">ঐচ্ছিক</option>
                        </select>
                    </div>
                    <div class="form-group" style="grid-column:1/-1;">
                        <label>প্রযোজ্য শ্রেণী</label>
                        <select name="applicable_class" id="feeClass" class="form-control">
                            <option value="all">সব শ্রেণী</option>
                            <?php foreach ($classes as $c): ?>
                            <option value="<?=e($c['class_name_bn'])?>">শুধু <?=e($c['class_name_bn'])?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="grid-column:1/-1;">
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                            <input type="checkbox" name="is_active" id="feeActive" value="1" checked style="width:16px;height:16px;">
                            সক্রিয় রাখুন (ফী সংগ্রহে দেখাবে)
                        </label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="closeModal('addFeeModal')" class="btn btn-outline">বাতিল</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> সংরক্ষণ করুন</button>
            </div>
        </form>
    </div>
</div>

<script>
function editFee(ft) {
    document.getElementById('feeId').value      = ft.id;
    document.getElementById('feeNameBn').value  = ft.fee_name_bn || '';
    document.getElementById('feeName').value    = ft.fee_name || '';
    document.getElementById('feeAmount').value  = ft.amount || 0;
    document.getElementById('feeCat').value     = ft.fee_category || 'monthly';
    document.getElementById('feeClass').value   = ft.applicable_class || 'all';
    document.getElementById('feeActive').checked = ft.is_active == 1;
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit"></i> ফী ধরন সম্পাদনা';
    openModal('addFeeModal');
}
</script>

<?php require_once '../../includes/footer.php'; ?>
