<?php
// modules/fees/collection.php
// ফি সংগ্রহ পেজ - ফিক্সড ভার্সন

require_once '../../includes/functions.php';
requireLogin();
$pdo = getDB();

// বিভাগ লোড
$divisions = $pdo->query("SELECT * FROM divisions ORDER BY name_bn")->fetchAll();

// শ্রেণী লোড
$classes = $pdo->query("SELECT c.*, d.name_bn as division_name FROM classes c LEFT JOIN divisions d ON c.division_id = d.id ORDER BY c.name_bn")->fetchAll();

// URL থেকে student_id ও division_id নেওয়া
$selected_division = isset($_GET['division_id']) ? intval($_GET['division_id']) : '';
$selected_student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : '';
$selected_class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : '';

// সিলেক্টেড স্টুডেন্ট তথ্য
$student = null;
if ($selected_student_id) {
    $stmt = $pdo->prepare("SELECT s.*, c.name_bn as class_name, d.name_bn as division_name 
                           FROM students s 
                           LEFT JOIN classes c ON s.class_id = c.id 
                           LEFT JOIN divisions d ON c.division_id = d.id 
                           WHERE s.id = ?");
    $stmt->execute([$selected_student_id]);
    $student = $stmt->fetch();
}

// লেনদেন হিস্ট্রি লোড (fee_collections টেবিল থেকে)
$transactions = [];
if ($selected_student_id) {
    $stmt = $pdo->prepare("SELECT fc.*, ft.fee_name_bn as fee_type_name 
                           FROM fee_collections fc 
                           LEFT JOIN fee_types ft ON fc.fee_type_id = ft.id 
                           WHERE fc.student_id = ? 
                           ORDER BY fc.payment_date DESC 
                           LIMIT 20");
    $stmt->execute([$selected_student_id]);
    $transactions = $stmt->fetchAll();
}

$pageTitle = 'ফি সংগ্রহ';
require_once '../../includes/header.php';
?>

<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4><i class="fas fa-money-bill-wave"></i> ফি সংগ্রহ</h4>
        <div>
            <a href="due.php" class="btn btn-danger btn-sm me-2"><i class="fas fa-exclamation-circle"></i> বাকিয়া</a>
            <a href="report.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-chart-bar"></i> রিপোর্ট</a>
        </div>
    </div>

    <!-- ছাত্র খুঁজুন সেকশন -->
    <div class="card mb-3">
        <div class="card-body">
            <h6 class="mb-3"><i class="fas fa-search"></i> ছাত্র খুঁজুন</h6>
            
            <div class="row g-2 align-items-end">
                <!-- বিভাগ -->
                <div class="col-md-2">
                    <label class="form-label small">বিভাগ</label>
                    <select id="division_id" class="form-select form-select-sm" onchange="filterClasses()">
                        <option value="">সকল বিভাগ</option>
                        <?php foreach ($divisions as $div): ?>
                            <option value="<?= $div['id'] ?>" <?= ($selected_division == $div['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($div['name_bn']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- শ্রেণী -->
                <div class="col-md-2">
                    <label class="form-label small">শ্রেণী দিয়ে ফিল্টার</label>
                    <select id="class_filter" class="form-select form-select-sm" onchange="loadStudentsByClass()">
                        <option value="">সব শ্রেণী</option>
                        <?php foreach ($classes as $cls): ?>
                            <option value="<?= $cls['id'] ?>" 
                                    data-division="<?= $cls['division_id'] ?>"
                                    <?= ($selected_class_id == $cls['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cls['name_bn']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- ছাত্র সিলেক্ট ড্রপডাউন -->
                <div class="col-md-3">
                    <label class="form-label small">ছাত্র নির্বাচন</label>
                    <select id="student_select" class="form-select form-select-sm" onchange="selectStudent(this.value)">
                        <option value="">-- ছাত্র নির্বাচন করুন --</option>
                    </select>
                </div>

                <!-- নাম/ID সার্চ -->
                <div class="col-md-4">
                    <label class="form-label small">নাম বা ID দিয়ে খুঁজুন</label>
                    <div class="input-group input-group-sm">
                        <input type="text" id="search_input" class="form-control" 
                               placeholder="নাম, ID, বা ফোন নম্বর..." 
                               onkeyup="searchStudent(this.value)" autocomplete="off">
                        <button class="btn btn-primary" onclick="searchStudent(document.getElementById('search_input').value)">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                    <div id="search_results" class="list-group position-absolute shadow-sm" 
                         style="z-index:1050; max-height:250px; overflow-y:auto; display:none; width:calc(100% - 24px);">
                    </div>
                </div>

                <div class="col-md-1">
                    <button class="btn btn-outline-secondary btn-sm w-100" onclick="resetSearch()">
                        <i class="fas fa-redo"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- মেইন কন্টেন্ট -->
    <div class="row">
        <!-- বাম পাশ: ফি জমা দিন -->
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header bg-white">
                    <h6 class="mb-0 text-success"><i class="fas fa-check-circle"></i> ফি জমা দিন</h6>
                </div>
                <div class="card-body">
                    <?php if ($student): ?>
                        <div class="d-flex align-items-center p-2 mb-3 rounded" style="background:#e8f4fd;">
                            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center me-3" 
                                 style="width:45px;height:45px;font-size:18px;font-weight:bold;">
                                <?= mb_substr($student['name_bn'] ?? $student['name'], 0, 1, 'UTF-8') ?>
                            </div>
                            <div>
                                <strong><?= htmlspecialchars($student['name_bn'] ?? $student['name']) ?></strong><br>
                                <small class="text-muted">
                                    ID: <?= htmlspecialchars($student['student_id'] ?? $student['id']) ?> 
                                    • <?= htmlspecialchars($student['class_name'] ?? '') ?>
                                    <?= !empty($student['division_name']) ? ' • ' . htmlspecialchars($student['division_name']) : '' ?>
                                </small>
                            </div>
                        </div>

                        <form method="POST" action="process_payment.php" id="feeForm">
                            <input type="hidden" name="student_id" value="<?= $student['id'] ?>">
                            
                            <div class="mb-3">
                                <label class="form-label">ফিস ধরন *</label>
                                <select name="fee_type_id" class="form-select" required onchange="updateAmount(this)">
                                    <option value="">ফিস ধরন নির্বাচন করুন</option>
                                    <?php
                                    $fee_types = $pdo->query("SELECT * FROM fee_types WHERE is_active = 1 ORDER BY fee_name_bn")->fetchAll();
                                    foreach ($fee_types as $ft):
                                    ?>
                                        <option value="<?= $ft['id'] ?>" data-amount="<?= $ft['amount'] ?>">
                                            <?= htmlspecialchars($ft['fee_name_bn']) ?> (<?= $ft['amount'] ?> ৳)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="row mb-3">
                                <div class="col-6">
                                    <label class="form-label">মাস/বছর</label>
                                    <input type="month" name="month_year" class="form-control" 
                                           value="<?= date('Y-m') ?>" required>
                                </div>
                                <div class="col-6">
                                    <label class="form-label">পরিমাণ (৳) *</label>
                                    <input type="number" name="amount" id="fee_amount" class="form-control" 
                                           min="0" step="1" required onchange="calculateTotal()">
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-6">
                                    <label class="form-label">ছাড় (৳)</label>
                                    <input type="number" name="discount" id="discount" class="form-control" 
                                           value="0" min="0" onchange="calculateTotal()">
                                </div>
                                <div class="col-6">
                                    <label class="form-label">জরিমানা (৳)</label>
                                    <input type="number" name="fine" id="fine" class="form-control" 
                                           value="0" min="0" onchange="calculateTotal()">
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-6">
                                    <label class="form-label">পরিশোধের পদ্ধতি</label>
                                    <select name="payment_method" class="form-select">
                                        <option value="cash">নগদ</option>
                                        <option value="bkash">বিকাশ</option>
                                        <option value="nagad">নগদ (মোবাইল)</option>
                                        <option value="bank">ব্যাংক</option>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label">পরিশোধের তারিখ</label>
                                    <input type="date" name="payment_date" class="form-control" 
                                           value="<?= date('Y-m-d') ?>">
                                </div>
                            </div>

                            <div class="p-3 rounded mb-3 d-flex justify-content-between align-items-center" 
                                 style="background:#0d6efd;color:#fff;">
                                <strong>মোট পরিশোধযোগ্য</strong>
                                <span id="total_display" style="font-size:1.5rem;font-weight:bold;">৳০</span>
                            </div>
                            <input type="hidden" name="total_amount" id="total_amount" value="0">

                            <div class="mb-3">
                                <label class="form-label">মন্তব্য</label>
                                <textarea name="remarks" class="form-control" rows="2" placeholder="ঐচ্ছিক মন্তব্য..."></textarea>
                            </div>

                            <button type="submit" class="btn btn-success w-100">
                                <i class="fas fa-save"></i> পেমেন্ট সংরক্ষণ করুন
                            </button>
                        </form>
                    <?php else: ?>
                        <div class="text-center text-muted py-5">
                            <i class="fas fa-user-graduate fa-3x mb-3 d-block opacity-50"></i>
                            <p>প্রথমে একজন ছাত্র নির্বাচন করুন</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ডান পাশ: লেনদেন হিস্ট্রি -->
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header bg-white">
                    <h6 class="mb-0"><i class="fas fa-history"></i> পূর্ববর্তী পরিশোধ</h6>
                </div>
                <div class="card-body p-0">
                    <?php if ($selected_student_id && count($transactions) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>তারিখ</th>
                                        <th>ফিস ধরন</th>
                                        <th>মাস</th>
                                        <th class="text-end">পরিমাণ</th>
                                        <th>পদ্ধতি</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $total_paid = 0;
                                    foreach ($transactions as $tr): 
                                        $total_paid += $tr['paid_amount'];
                                    ?>
                                        <tr>
                                            <td class="small"><?= date('d/m/Y', strtotime($tr['payment_date'])) ?></td>
                                            <td class="small"><?= htmlspecialchars($tr['fee_type_name'] ?? '-') ?></td>
                                            <td class="small"><?= $tr['month_year'] ?? '-' ?></td>
                                            <td class="small text-end fw-bold"><?= number_format($tr['paid_amount']) ?> ৳</td>
                                            <td class="small"><?= htmlspecialchars($tr['payment_method'] ?? 'cash') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="table-light">
                                    <tr>
                                        <td colspan="3"><strong>সর্বমোট পরিশোধ</strong></td>
                                        <td class="text-end"><strong><?= number_format($total_paid) ?> ৳</strong></td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    <?php elseif ($selected_student_id): ?>
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-inbox fa-2x mb-2 d-block opacity-50"></i>
                            <small>কোনো পরিশোধের তথ্য নেই</small>
                        </div>
                    <?php else: ?>
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-inbox fa-2x mb-2 d-block opacity-50"></i>
                            <small>ছাত্র নির্বাচন করুন</small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function filterClasses() {
    const divId = document.getElementById('division_id').value;
    const classSelect = document.getElementById('class_filter');
    const options = classSelect.querySelectorAll('option[data-division]');
    options.forEach(opt => {
        opt.style.display = (!divId || opt.dataset.division === divId) ? '' : 'none';
    });
    classSelect.value = '';
    document.getElementById('student_select').innerHTML = '<option value="">-- ছাত্র নির্বাচন করুন --</option>';
}

function loadStudentsByClass() {
    const classId = document.getElementById('class_filter').value;
    const divId = document.getElementById('division_id').value;
    const studentSelect = document.getElementById('student_select');
    if (!classId) { studentSelect.innerHTML = '<option value="">-- ছাত্র নির্বাচন করুন --</option>'; return; }
    studentSelect.innerHTML = '<option value="">লোড হচ্ছে...</option>';
    let url = '../../api/search_student.php?class_id=' + classId;
    if (divId) url += '&division_id=' + divId;
    fetch(url).then(r => r.json()).then(data => {
        let html = '<option value="">-- ছাত্র নির্বাচন করুন (' + data.length + ' জন) --</option>';
        data.forEach(s => { html += '<option value="' + s.id + '">' + (s.name_bn||s.name) + ' - রোল: ' + (s.roll_number||'-') + ' (' + s.student_id + ')</option>'; });
        studentSelect.innerHTML = html;
    }).catch(() => { studentSelect.innerHTML = '<option value="">লোড ব্যর্থ</option>'; });
}

function selectStudent(studentId) {
    if (!studentId) return;
    const divId = document.getElementById('division_id').value;
    let url = 'collection.php?student_id=' + studentId;
    if (divId) url += '&division_id=' + divId;
    window.location.href = url;
}

let searchTimer = null;
function searchStudent(query) {
    clearTimeout(searchTimer);
    const resultsDiv = document.getElementById('search_results');
    if (query.length < 2) { resultsDiv.style.display = 'none'; return; }
    searchTimer = setTimeout(() => {
        const divId = document.getElementById('division_id').value;
        let url = '../../api/search_student.php?q=' + encodeURIComponent(query);
        if (divId) url += '&division_id=' + divId;
        fetch(url).then(r => r.json()).then(data => {
            if (data.length === 0) {
                resultsDiv.innerHTML = '<div class="list-group-item text-muted small">কোনো ছাত্র পাওয়া যায়নি</div>';
            } else {
                let html = '';
                data.forEach(s => {
                    html += '<a href="collection.php?student_id=' + s.id + '&division_id=' + (divId||'') + '" class="list-group-item list-group-item-action py-2">';
                    html += '<strong>' + (s.name_bn||s.name) + '</strong><br>';
                    html += '<small class="text-muted">ID: ' + s.student_id + ' • ' + (s.class_name||'') + '</small></a>';
                });
                resultsDiv.innerHTML = html;
            }
            resultsDiv.style.display = 'block';
        });
    }, 300);
}

function resetSearch() {
    document.getElementById('search_input').value = '';
    document.getElementById('search_results').style.display = 'none';
    document.getElementById('division_id').value = '';
    document.getElementById('class_filter').value = '';
    document.getElementById('student_select').innerHTML = '<option value="">-- ছাত্র নির্বাচন করুন --</option>';
    window.location.href = 'collection.php';
}

function updateAmount(sel) {
    const opt = sel.options[sel.selectedIndex];
    document.getElementById('fee_amount').value = opt.dataset.amount || 0;
    calculateTotal();
}

function calculateTotal() {
    const amount = parseFloat(document.getElementById('fee_amount').value) || 0;
    const discount = parseFloat(document.getElementById('discount').value) || 0;
    const fine = parseFloat(document.getElementById('fine').value) || 0;
    const total = amount - discount + fine;
    document.getElementById('total_display').textContent = '৳' + total;
    document.getElementById('total_amount').value = total;
}

document.addEventListener('DOMContentLoaded', function() {
    filterClasses();
    const classFilter = document.getElementById('class_filter');
    if (classFilter.value) loadStudentsByClass();
});

document.addEventListener('click', function(e) {
    if (!e.target.closest('#search_results') && !e.target.closest('#search_input')) {
        document.getElementById('search_results').style.display = 'none';
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>
