<?php
require_once '../../includes/functions.php';
requireLogin(['super_admin','principal','accountant']);
$pageTitle = 'ফি সংগ্রহ';
$db = getDB();

$feeTypes  = $db->query("SELECT * FROM fee_types WHERE is_active=1")->fetchAll();
$divisions = $db->query("SELECT * FROM divisions WHERE is_active=1 ORDER BY sort_order, id")->fetchAll();
$divisionId = (int)($_GET['division_id'] ?? 0);

// Collect fee
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['collect_fee'])) {
    if (!verifyCsrf($_POST['csrf'] ?? '')) die('CSRF Error');

    $studentId = (int)$_POST['student_id'];
    $feeTypeId = (int)$_POST['fee_type_id'];
    $amount = (float)$_POST['amount'];
    $discount = (float)($_POST['discount'] ?? 0);
    $fine = (float)($_POST['fine'] ?? 0);
    $paidAmount = $amount - $discount + $fine;
    $paymentDate = $_POST['payment_date'] ?? date('Y-m-d');
    $method = $_POST['payment_method'] ?? 'cash';
    $txnId = trim($_POST['transaction_id'] ?? '');
    $monthYear = $_POST['month_year'] ?? date('Y-m');
    $notes = trim($_POST['notes'] ?? '');
    $receiptNo = generateReceiptNo();

    $stmt = $db->prepare("INSERT INTO fee_collections 
        (student_id, fee_type_id, amount, discount, fine, paid_amount, payment_date, payment_method, transaction_id, month_year, receipt_number, collected_by, notes)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute([$studentId,$feeTypeId,$amount,$discount,$fine,$paidAmount,$paymentDate,$method,$txnId,$monthYear,$receiptNo,$_SESSION['user_id'],$notes]);

    logActivity($_SESSION['user_id'], 'fee_collect', 'fees', "ফি আদায়: ছাত্র #$studentId, ৳$paidAmount, রসিদ: $receiptNo");
    setFlash('success', "ফি সংগ্রহ সফল! রসিদ নম্বর: $receiptNo");
    header('Location: receipt.php?id=' . $db->lastInsertId());
    exit;
}

// Search student
$studentResult = null;
$studentFees = [];
$customFeeMap = [];
if (isset($_GET['student_id'])) {
    $sid = (int)$_GET['student_id'];
    $stmt = $db->prepare("SELECT s.*, c.class_name_bn FROM students s LEFT JOIN classes c ON s.class_id=c.id WHERE s.id=?");
    $stmt->execute([$sid]);
    $studentResult = $stmt->fetch();

    $stmt2 = $db->prepare("SELECT fc.*, ft.fee_name_bn FROM fee_collections fc
        JOIN fee_types ft ON fc.fee_type_id = ft.id
        WHERE fc.student_id=? ORDER BY fc.payment_date DESC LIMIT 12");
    $stmt2->execute([$sid]);
    $studentFees = $stmt2->fetchAll();

    // Load custom fee assignments for this student
    $cfStmt = $db->prepare("SELECT fee_type_id, custom_amount FROM student_fee_assignments WHERE student_id=? AND is_active=1");
    $cfStmt->execute([$sid]);
    foreach ($cfStmt->fetchAll() as $cf) {
        $customFeeMap[$cf['fee_type_id']] = $cf['custom_amount'];
    }
}

require_once '../../includes/header.php';
?>
<div class="section-header">
    <h2 class="section-title"><i class="fas fa-money-bill-wave"></i> ফি সংগ্রহ</h2>
    <div style="display:flex;gap:8px;">
        <a href="due.php" class="btn btn-warning btn-sm"><i class="fas fa-exclamation"></i> বকেয়া</a>
        <a href="report.php" class="btn btn-outline btn-sm"><i class="fas fa-chart-bar"></i> রিপোর্ট</a>
    </div>
</div>

<!-- Search Student -->
<div class="card mb-16">
    <div class="card-header"><span class="card-title"><i class="fas fa-search"></i> ছাত্র খুঁজুন</span></div>
    <div class="card-body" style="padding:14px 20px;">
        <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;">
            <!-- Division filter -->
            <div style="flex:1;min-width:150px;">
                <label style="font-size:12px;color:#718096;display:block;margin-bottom:4px;">বিভাগ</label>
                <select id="divisionFilter" class="form-control" style="padding:8px;" onchange="onDivisionChange(this.value)">
                    <option value="">সব বিভাগ</option>
                    <?php foreach ($divisions as $d): ?>
                    <option value="<?= $d['id'] ?>" <?= $divisionId == $d['id'] ? 'selected' : '' ?>>
                        <?= e($d['division_name_bn']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <!-- Class filter -->
            <div style="flex:1;min-width:160px;">
                <label style="font-size:12px;color:#718096;display:block;margin-bottom:4px;">শ্রেণী দিয়ে ফিল্টার</label>
                <select id="classFilter" class="form-control" style="padding:8px;" onchange="loadStudentsByClass(this.value)">
                    <option value="">সব শ্রেণী</option>
                    <?php
                    if ($divisionId) {
                        $clsStmt = $db->prepare("SELECT c.*, d.division_name_bn FROM classes c LEFT JOIN divisions d ON c.division_id=d.id WHERE c.is_active=1 AND c.division_id=? ORDER BY c.class_numeric");
                        $clsStmt->execute([$divisionId]);
                        $classes = $clsStmt->fetchAll();
                    } else {
                        $classes = $db->query("SELECT c.*, d.division_name_bn FROM classes c LEFT JOIN divisions d ON c.division_id=d.id WHERE c.is_active=1 ORDER BY d.sort_order, c.class_numeric")->fetchAll();
                    }
                    foreach ($classes as $c):
                    ?>
                    <option value="<?=$c['id']?>" data-div="<?=$c['division_id']?>">
                        <?php if (!$divisionId): ?><?= e($c['division_name_bn'] ?? '') ?> → <?php endif; ?>
                        <?=e($c['class_name_bn'])?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <!-- Name/ID search -->
            <div style="flex:2;min-width:200px;">
                <label style="font-size:12px;color:#718096;display:block;margin-bottom:4px;">নাম বা ID দিয়ে খুঁজুন</label>
                <div style="display:flex;gap:8px;">
                    <input type="text" id="studentSearch" class="form-control" placeholder="নাম, ID, বা ফোন নম্বর...">
                    <button type="button" class="btn btn-primary" onclick="searchStudent()"><i class="fas fa-search"></i></button>
                </div>
            </div>
        </div>
        <!-- Dropdown results -->
        <div style="position:relative;max-width:620px;">
            <div id="searchDropdown" style="display:none;position:absolute;top:4px;left:0;right:0;background:#fff;border:1px solid var(--border);border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,.1);z-index:200;max-height:250px;overflow-y:auto;"></div>
        </div>
    </div>
</div>

<div class="grid-2">
    <!-- Fee Collection Form -->
    <div class="card">
        <div class="card-header" style="background:#eafaf1;">
            <span class="card-title" style="color:var(--success);"><i class="fas fa-plus-circle"></i> ফি জমা নিন</span>
        </div>
        <div class="card-body">
            <?php if ($studentResult): ?>
            <div style="background:var(--bg);border-radius:8px;padding:12px;margin-bottom:16px;display:flex;align-items:center;gap:12px;">
                <div class="avatar" style="width:44px;height:44px;font-size:16px;">
                    <?= mb_substr($studentResult['name_bn'] ?? $studentResult['name'], 0, 1) ?>
                </div>
                <div>
                    <div style="font-weight:700;font-size:15px;"><?= e($studentResult['name_bn'] ?? $studentResult['name']) ?></div>
                    <div style="font-size:12px;color:var(--text-muted);">
                        ID: <?= e($studentResult['student_id']) ?> &bull; <?= e($studentResult['class_name_bn']) ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="csrf" value="<?= getCsrfToken() ?>">
                <input type="hidden" name="collect_fee" value="1">
                <input type="hidden" name="student_id" value="<?= e($studentResult['id'] ?? '') ?>" id="formStudentId">

                <div class="form-grid">
                    <div class="form-group" style="grid-column:1/-1;">
                        <label>ফির ধরন <span>*</span></label>
                        <select name="fee_type_id" class="form-control" required onchange="setAmount(this)">
                            <option value="">ফির ধরন নির্বাচন করুন</option>
                            <?php foreach ($feeTypes as $ft):
                                $customAmt = $customFeeMap[$ft['id']] ?? null;
                                $displayAmt = $customAmt ?? $ft['amount'];
                                $isCustom = $customAmt !== null;
                            ?>
                            <option value="<?= $ft['id'] ?>"
                                data-amount="<?= $displayAmt ?>"
                                data-default="<?= $ft['amount'] ?>"
                                data-custom="<?= $isCustom ? 1 : 0 ?>">
                                <?= e($ft['fee_name_bn']) ?>
                                (৳<?= number_format($displayAmt) ?>
                                <?= $isCustom ? ' — ব্যক্তিগত' : '' ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!empty($customFeeMap)): ?>
                        <small style="color:#e67e22;font-size:11px;"><i class="fas fa-info-circle"></i> এই ছাত্রের জন্য কিছু ফী আলাদাভাবে নির্ধারিত আছে।</small>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label>মাস/বছর</label>
                        <input type="month" name="month_year" class="form-control" value="<?= date('Y-m') ?>">
                    </div>
                    <div class="form-group">
                        <label>পরিমাণ (৳) <span>*</span></label>
                        <input type="number" name="amount" class="form-control" id="feeAmount" min="0" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label>ছাড় (৳)</label>
                        <input type="number" name="discount" class="form-control" id="feeDiscount" value="0" min="0" step="0.01" onchange="calcTotal()">
                    </div>
                    <div class="form-group">
                        <label>জরিমানা (৳)</label>
                        <input type="number" name="fine" class="form-control" id="feeFine" value="0" min="0" step="0.01" onchange="calcTotal()">
                    </div>
                    <div class="form-group">
                        <label>পরিশোধের পদ্ধতি</label>
                        <select name="payment_method" class="form-control" onchange="toggleTxn(this)">
                            <option value="cash">নগদ</option>
                            <option value="bkash">bKash</option>
                            <option value="nagad">Nagad</option>
                            <option value="rocket">Rocket</option>
                            <option value="bank">ব্যাংক</option>
                        </select>
                    </div>
                    <div class="form-group" id="txnGroup" style="display:none;">
                        <label>ট্রানজ্যাকশন ID</label>
                        <input type="text" name="transaction_id" class="form-control" placeholder="TXN ID">
                    </div>
                    <div class="form-group">
                        <label>পরিশোধের তারিখ</label>
                        <input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                </div>

                <!-- Total display -->
                <div style="background:var(--primary);color:#fff;border-radius:8px;padding:14px 16px;margin:16px 0;display:flex;justify-content:space-between;align-items:center;">
                    <span style="font-size:14px;">মোট পরিশোধযোগ্য</span>
                    <span style="font-size:22px;font-weight:700;" id="totalDisplay">৳০</span>
                </div>

                <div class="form-group">
                    <label>মন্তব্য</label>
                    <textarea name="notes" class="form-control" rows="2"></textarea>
                </div>

                <button type="submit" class="btn btn-success" <?= !$studentResult ? 'disabled' : '' ?>>
                    <i class="fas fa-check-circle"></i> ফি সংগ্রহ করুন
                </button>
            </form>
        </div>
    </div>

    <!-- Recent payments for this student -->
    <div class="card">
        <div class="card-header">
            <span class="card-title"><i class="fas fa-history"></i> পূর্ববর্তী পরিশোধ</span>
        </div>
        <div class="card-body" style="padding:0;">
            <?php if (empty($studentFees)): ?>
            <div style="text-align:center;padding:30px;color:var(--text-muted);">
                <?= $studentResult ? 'কোনো পরিশোধের তথ্য নেই' : 'ছাত্র নির্বাচন করুন' ?>
            </div>
            <?php else: ?>
            <table>
                <thead><tr><th>মাস</th><th>ফির ধরন</th><th>পরিমাণ</th><th>পদ্ধতি</th></tr></thead>
                <tbody>
                    <?php foreach ($studentFees as $f): ?>
                    <tr>
                        <td style="font-size:12px;"><?= e($f['month_year']) ?></td>
                        <td style="font-size:13px;"><?= e($f['fee_name_bn']) ?></td>
                        <td style="font-weight:700;color:var(--success);">৳<?= number_format($f['paid_amount']) ?></td>
                        <td><span class="badge badge-info" style="font-size:10px;"><?= e($f['payment_method']) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function onDivisionChange(divId) {
    const url = new URL(window.location.href);
    url.searchParams.set('division_id', divId);
    url.searchParams.delete('student_id');
    window.location.href = url.toString();
}

function loadStudentsByClass(classId) {
    const dd = document.getElementById('searchDropdown');
    if (!classId) { dd.style.display = 'none'; return; }
    const divId = document.getElementById('divisionFilter').value;
    let url = '<?= BASE_URL ?>/api/search_student.php?class_id=' + classId + '&q=';
    if (divId) url += '&division_id=' + divId;
    fetch(url)
        .then(r => r.json())
        .then(data => showDropdown(data));
}

function searchStudent() {
    const q = document.getElementById('studentSearch').value;
    const classId = document.getElementById('classFilter').value;
    const divId = document.getElementById('divisionFilter').value;
    let url = '<?= BASE_URL ?>/api/search_student.php?q=' + encodeURIComponent(q);
    if (classId) url += '&class_id=' + classId;
    if (divId) url += '&division_id=' + divId;
    fetch(url).then(r => r.json()).then(data => showDropdown(data));
}

function showDropdown(data) {
    const dd = document.getElementById('searchDropdown');
    if (!data.length) {
        dd.innerHTML = '<div style="padding:12px 14px;color:#718096;font-size:13px;">কোনো ছাত্র পাওয়া যায়নি</div>';
        dd.style.display = 'block'; return;
    }
    dd.innerHTML = data.map(s =>
        `<div onclick="selectStudent(${s.id},'${(s.name_bn||s.name).replace(/'/g,"\\'")}')"
            style="padding:10px 14px;cursor:pointer;border-bottom:1px solid #f0f4f8;font-size:13px;display:flex;align-items:center;gap:10px;"
            onmouseover="this.style.background='#f0f4f8'" onmouseout="this.style.background='#fff'">
            <div style="width:32px;height:32px;background:#1a5276;color:#fff;border-radius:8px;display:flex;align-items:center;justify-content:center;font-weight:700;flex-shrink:0;">
                ${(s.name_bn||s.name).charAt(0)}
            </div>
            <div>
                <div style="font-weight:600;">${s.name_bn||s.name}</div>
                <div style="font-size:11px;color:#718096;">${s.student_id} &bull; ${s.division_name_bn||''} → ${s.class_name_bn||''} &bull; রোল: ${s.roll_number||''}</div>
            </div>
        </div>`
    ).join('');
    dd.style.display = 'block';
}

function selectStudent(id, name) {
    document.getElementById('studentSearch').value = name;
    document.getElementById('searchDropdown').style.display = 'none';
    const url = new URL(window.location.href);
    url.searchParams.set('student_id', id);
    window.location.href = url.toString();
}

// Close dropdown on outside click
document.addEventListener('click', function(e) {
    if (!e.target.closest('#searchDropdown') && !e.target.closest('#studentSearch')) {
        document.getElementById('searchDropdown').style.display = 'none';
    }
});

// Live search with debounce
let searchTimeout;
document.getElementById('studentSearch').addEventListener('input', function() {
    clearTimeout(searchTimeout);
    const q = this.value;
    if (q.length < 2) { document.getElementById('searchDropdown').style.display = 'none'; return; }
    searchTimeout = setTimeout(() => searchStudent(), 300);
});

function setAmount(sel) {
    const opt = sel.selectedOptions[0];
    const amount = opt.dataset.amount || '';
    const defaultAmt = opt.dataset.default || '';
    const isCustom = opt.dataset.custom === '1';
    document.getElementById('feeAmount').value = amount;
    let hint = document.getElementById('feeAmountHint');
    if (!hint) {
        hint = document.createElement('small');
        hint.id = 'feeAmountHint';
        hint.style.cssText = 'font-size:11px;display:block;margin-top:4px;';
        document.getElementById('feeAmount').parentNode.appendChild(hint);
    }
    if (isCustom) {
        hint.style.color = '#e67e22';
        hint.innerHTML = '<i class="fas fa-tag"></i> ব্যক্তিগত নির্ধারিত: ৳' + parseFloat(amount).toLocaleString() + ' (ডিফল্ট: ৳' + parseFloat(defaultAmt).toLocaleString() + ')';
    } else {
        hint.innerHTML = '';
    }
    calcTotal();
}

function calcTotal() {
    const a = parseFloat(document.getElementById('feeAmount').value) || 0;
    const d = parseFloat(document.getElementById('feeDiscount').value) || 0;
    const f = parseFloat(document.getElementById('feeFine').value) || 0;
    document.getElementById('totalDisplay').textContent = '৳' + (a - d + f).toFixed(2);
}

function toggleTxn(sel) {
    document.getElementById('txnGroup').style.display = sel.value !== 'cash' ? 'block' : 'none';
}

document.getElementById('feeAmount').addEventListener('input', calcTotal);
</script>

<?php require_once '../../includes/footer.php'; ?>
