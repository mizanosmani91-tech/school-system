<?php
require_once '../../includes/functions.php';
requireLogin(['super_admin','principal','accountant']);
$pageTitle = 'ফি সংগ্রহ';
$db = getDB();

$feeTypes = $db->query("SELECT * FROM fee_types WHERE is_active=1")->fetchAll();

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
$customFeeMap = []; // student-specific fee amounts
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
        <form method="GET" style="display:flex;gap:10px;">
            <div class="input-group" style="flex:1;max-width:400px;">
                <input type="text" name="q" id="studentSearch" class="form-control" placeholder="নাম, ID, বা ফোন নম্বর দিয়ে খুঁজুন...">
                <div class="input-group-append">
                    <button type="button" class="btn btn-primary" onclick="searchStudent()"><i class="fas fa-search"></i></button>
                </div>
            </div>
            <input type="hidden" name="student_id" id="selectedStudentId">
        </form>
        <!-- Auto-complete results -->
        <div id="searchResults" style="max-width:400px;position:relative;">
            <div id="searchDropdown" style="display:none;position:absolute;top:0;left:0;right:0;background:#fff;border:1px solid var(--border);border-radius:8px;box-shadow:var(--shadow-md);z-index:100;max-height:200px;overflow-y:auto;"></div>
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
function searchStudent() {
    const q = document.getElementById('studentSearch').value;
    if (q.length < 2) return;
    fetch('<?= BASE_URL ?>/api/search_student.php?q=' + encodeURIComponent(q))
        .then(r => r.json())
        .then(data => {
            const dd = document.getElementById('searchDropdown');
            if (!data.length) { dd.style.display = 'none'; return; }
            dd.innerHTML = data.map(s =>
                `<div onclick="selectStudent(${s.id},'${s.name_bn||s.name}')" style="padding:10px 14px;cursor:pointer;border-bottom:1px solid var(--border);font-size:13px;">
                    <strong>${s.name_bn||s.name}</strong> &mdash; ${s.student_id} &mdash; ${s.class_name_bn||''}
                </div>`
            ).join('');
            dd.style.display = 'block';
        });
}

function setAmount(sel) {
    const opt = sel.selectedOptions[0];
    const amount = opt.dataset.amount || '';
    const defaultAmt = opt.dataset.default || '';
    const isCustom = opt.dataset.custom === '1';
    document.getElementById('feeAmount').value = amount;
    // Show hint if custom
    let hint = document.getElementById('feeAmountHint');
    if (!hint) {
        hint = document.createElement('small');
        hint.id = 'feeAmountHint';
        hint.style.cssText = 'font-size:11px;display:block;margin-top:4px;';
        document.getElementById('feeAmount').parentNode.appendChild(hint);
    }
    if (isCustom) {
        hint.style.color = '#e67e22';
        hint.innerHTML = `<i class="fas fa-tag"></i> ব্যক্তিগত নির্ধারিত: ৳${parseFloat(amount).toLocaleString()} (ডিফল্ট: ৳${parseFloat(defaultAmt).toLocaleString()})`;
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

// Auto-complete search
let searchTimeout;
document.getElementById('studentSearch').addEventListener('input', function() {
    clearTimeout(searchTimeout);
    const q = this.value;
    if (q.length < 2) { document.getElementById('searchDropdown').style.display = 'none'; return; }
    searchTimeout = setTimeout(() => {
        fetch('<?= BASE_URL ?>/api/search_student.php?q=' + encodeURIComponent(q))
            .then(r => r.json())
            .then(data => {
                const dd = document.getElementById('searchDropdown');
                if (!data.length) { dd.style.display = 'none'; return; }
                dd.innerHTML = data.map(s =>
                    `<div onclick="selectStudent(${s.id},'${s.name_bn||s.name}')" style="padding:10px 14px;cursor:pointer;border-bottom:1px solid var(--border);font-size:13px;">
                        <strong>${s.name_bn||s.name}</strong> &mdash; ${s.student_id} &mdash; ${s.class_name_bn||''}
                    </div>`
                ).join('');
                dd.style.display = 'block';
            });
    }, 300);
});

function selectStudent(id, name) {
    document.getElementById('selectedStudentId').value = id;
    document.getElementById('studentSearch').value = name;
    document.getElementById('searchDropdown').style.display = 'none';
    window.location.href = 'collection.php?student_id=' + id;
}

document.getElementById('feeAmount').addEventListener('input', calcTotal);
</script>

<?php require_once '../../includes/footer.php'; ?>
