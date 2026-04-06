<?php
require_once '../../includes/functions.php';
requireLogin();
$pageTitle = 'ছাত্রের প্রোফাইল';
$db = getDB();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: list.php'); exit; }

$stmt = $db->prepare("SELECT s.*, 
    c.class_name_bn, c.class_name, c.class_numeric,
    sec.section_name,
    ac.class_name_bn AS admission_class_name_bn,
    ac.class_numeric AS admission_class_numeric
    FROM students s
    LEFT JOIN classes c ON s.class_id=c.id
    LEFT JOIN sections sec ON s.section_id=sec.id
    LEFT JOIN classes ac ON s.admission_class_id=ac.id
    WHERE s.id=?");
$stmt->execute([$id]);
$student = $stmt->fetch();
if (!$student) { setFlash('danger','ছাত্র পাওয়া যায়নি।'); header('Location: list.php'); exit; }

// ══════════════════════════════════════════════════
// Current Class Auto-Calculation
// ভর্তির বছর থেকে এখন পর্যন্ত কত বছর পার হয়েছে
// সেই অনুযায়ী current class বের করা হচ্ছে
// ══════════════════════════════════════════════════
$admYear = !empty($student['admission_year'])
    ? (int)$student['admission_year']
    : (int)date('Y', strtotime($student['admission_date'] ?? 'now'));

$currentYear    = (int)date('Y');
$yearsPassed    = max(0, $currentYear - $admYear);
$baseNumeric    = (int)($student['admission_class_numeric'] ?? $student['class_numeric'] ?? 1);
$targetNumeric  = $baseNumeric + $yearsPassed;

// classes table থেকে calculated class নিন
$calcStmt = $db->prepare("SELECT id, class_name_bn, class_name, class_numeric FROM classes WHERE class_numeric=? AND is_active=1 LIMIT 1");
$calcStmt->execute([$targetNumeric]);
$calculatedClass = $calcStmt->fetch();

// যদি calculated class পাওয়া যায় এবং ভর্তির class থেকে আলাদা হয়
$showCurrentClass   = $calculatedClass && ($calculatedClass['id'] != ($student['admission_class_id'] ?? $student['class_id']));
$displayClassName   = $calculatedClass
    ? ($calculatedClass['class_name_bn'] ?? $calculatedClass['class_name'])
    : ($student['class_name_bn'] ?? $student['class_name']);
$displayClassId     = $calculatedClass ? $calculatedClass['id'] : $student['class_id'];

// DB তে class_id auto-update করুন (যদি পরিবর্তন হয়েছে)
if ($showCurrentClass && $calculatedClass['id'] != $student['class_id']) {
    $db->prepare("UPDATE students SET class_id=?, academic_year=? WHERE id=?")
       ->execute([$calculatedClass['id'], $currentYear, $id]);
    $student['class_id']      = $calculatedClass['id'];
    $student['class_name_bn'] = $calculatedClass['class_name_bn'];
    $student['class_name']    = $calculatedClass['class_name'];
}
// ══════════════════════════════════════════════════

// Attendance summary
$attStmt = $db->prepare("SELECT
    COUNT(*) as total, SUM(status='present') as present,
    SUM(status='absent') as absent, SUM(status='late') as late
    FROM attendance WHERE student_id=? AND YEAR(date)=?");
$attStmt->execute([$id, date('Y')]);
$att = $attStmt->fetch();
$attRate = $att['total'] > 0 ? round(($att['present']/$att['total'])*100) : 0;

// Recent exam marks
$marks = $db->prepare("SELECT em.*, e.exam_name_bn, s.subject_name_bn FROM exam_marks em
    JOIN exams e ON em.exam_id=e.id JOIN subjects s ON em.subject_id=s.id
    WHERE em.student_id=? ORDER BY e.start_date DESC LIMIT 10");
$marks->execute([$id]);
$examMarks = $marks->fetchAll();

// Fee history
$fees = $db->prepare("SELECT fc.*, ft.fee_name_bn FROM fee_collections fc
    JOIN fee_types ft ON fc.fee_type_id=ft.id WHERE fc.student_id=? ORDER BY fc.payment_date DESC LIMIT 8");
$fees->execute([$id]);
$feeHistory = $fees->fetchAll();
$totalPaid = $db->prepare("SELECT COALESCE(SUM(paid_amount),0) FROM fee_collections WHERE student_id=? AND YEAR(payment_date)=?");
$totalPaid->execute([$id, date('Y')]); $totalFee = $totalPaid->fetchColumn();

// Fee assignments
$feeTypes = $db->query("SELECT * FROM fee_types WHERE is_active=1 ORDER BY fee_category, fee_name_bn")->fetchAll();
$assignStmt = $db->prepare("SELECT sfa.*, ft.fee_name_bn, ft.amount as default_amount, ft.fee_category
    FROM student_fee_assignments sfa
    JOIN fee_types ft ON sfa.fee_type_id = ft.id
    WHERE sfa.student_id=? AND sfa.is_active=1");
$assignStmt->execute([$id]);
$feeAssignments = $assignStmt->fetchAll();
$assignedMap = [];
foreach ($feeAssignments as $fa) { $assignedMap[$fa['fee_type_id']] = $fa; }

// Save fee assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_fee_assign'])) {
    $feeTypeId    = (int)$_POST['fee_type_id'];
    $customAmount = (float)$_POST['custom_amount'];
    $reason       = trim($_POST['discount_reason'] ?? '');
    $db->prepare("INSERT INTO student_fee_assignments (student_id, fee_type_id, custom_amount, discount_reason, created_by)
        VALUES (?,?,?,?,?)
        ON DUPLICATE KEY UPDATE custom_amount=VALUES(custom_amount), discount_reason=VALUES(discount_reason), is_active=1")
       ->execute([$id, $feeTypeId, $customAmount, $reason, $_SESSION['user_id']]);
    setFlash('success', 'ফী নির্ধারণ সংরক্ষিত হয়েছে।');
    header("Location: view.php?id=$id"); exit;
}

// Remove fee assignment
if (isset($_GET['remove_fee']) && in_array($_SESSION['role_slug'], ['super_admin','principal'])) {
    $db->prepare("UPDATE student_fee_assignments SET is_active=0 WHERE id=? AND student_id=?")
       ->execute([(int)$_GET['remove_fee'], $id]);
    setFlash('success', 'ফী নির্ধারণ সরানো হয়েছে।');
    header("Location: view.php?id=$id"); exit;
}

require_once '../../includes/header.php';
?>
<div class="section-header">
    <h2 class="section-title"><i class="fas fa-user-graduate"></i> ছাত্রের প্রোফাইল</h2>
    <div style="display:flex;gap:8px;">
        <a href="edit.php?id=<?=$id?>" class="btn btn-warning btn-sm"><i class="fas fa-edit"></i> সম্পাদনা</a>
        <a href="list.php" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> তালিকায় ফিরুন</a>
        <button onclick="window.print()" class="btn btn-outline btn-sm no-print"><i class="fas fa-print"></i> প্রিন্ট</button>
    </div>
</div>

<!-- Profile Card -->
<div class="card mb-24" style="background:linear-gradient(135deg,#1a5276,#0d2137);color:#fff;">
    <div class="card-body" style="padding:24px;">
        <div style="display:flex;align-items:center;gap:20px;flex-wrap:wrap;">
            <?php if ($student['photo']): ?>
            <img src="<?=UPLOAD_URL.e($student['photo'])?>" style="width:90px;height:90px;border-radius:14px;object-fit:cover;border:3px solid rgba(255,255,255,.3);">
            <?php else: ?>
            <div style="width:90px;height:90px;border-radius:14px;background:rgba(255,255,255,.15);display:flex;align-items:center;justify-content:center;font-size:36px;font-weight:700;">
                <?=mb_substr($student['name_bn']??$student['name'],0,1)?>
            </div>
            <?php endif; ?>
            <div style="flex:1;">
                <h2 style="font-size:22px;font-weight:700;"><?=e($student['name_bn']??$student['name'])?></h2>
                <p style="opacity:.8;margin-top:4px;"><?=e($student['name'])?></p>
                <div style="display:flex;flex-wrap:wrap;gap:16px;margin-top:12px;font-size:13px;opacity:.9;">
                    <span><i class="fas fa-id-card"></i> <?=e($student['student_id'])?></span>
                    <span>
                        <i class="fas fa-school"></i>
                        <?=e($displayClassName)?> <?=e($student['section_name']??'')?>
                        <?php if ($showCurrentClass): ?>
                            <span style="font-size:11px;background:rgba(255,255,255,.2);border-radius:4px;padding:1px 6px;margin-left:4px;">
                                ভর্তি: <?=e($student['admission_class_name_bn'] ?? '')?>
                            </span>
                        <?php endif; ?>
                    </span>
                    <span><i class="fas fa-hashtag"></i> রোল: <?=toBanglaNumber($student['roll_number']??'')?></span>
                    <span><i class="fas fa-calendar"></i> ভর্তি: <?=banglaDate($student['admission_date']??'')?></span>
                </div>
            </div>
            <div>
                <span class="badge badge-<?=$student['status']==='active'?'success':'secondary'?>" style="font-size:13px;padding:6px 14px;">
                    <?=$student['status']==='active'?'সক্রিয়':e($student['status'])?>
                </span>
            </div>
        </div>
    </div>
</div>

<!-- Secret Code Box -->
<?php if ($student['secret_code']): ?>
<div class="card mb-24 no-print" style="border:2px dashed #e67e22;">
    <div class="card-body" style="padding:16px 20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
        <div style="display:flex;align-items:center;gap:12px;">
            <i class="fas fa-key" style="font-size:22px;color:#e67e22;"></i>
            <div>
                <div style="font-size:12px;color:#718096;font-weight:600;">অভিভাবক/ছাত্র লগইনের জন্য Secret Code</div>
                <div style="font-size:22px;font-weight:700;color:#e67e22;letter-spacing:4px;"><?=e($student['secret_code'])?></div>
                <div style="font-size:11px;color:#a0aec0;margin-top:2px;">Student ID: <?=e($student['student_id'])?> | এই কোড গোপন রাখুন</div>
            </div>
        </div>
        <button onclick="printSlip()" class="btn btn-sm" style="background:#e67e22;color:#fff;">
            <i class="fas fa-print"></i> Admission Slip প্রিন্ট
        </button>
    </div>
</div>

<!-- Print Slip (hidden, shown only when printing) -->
<div id="admissionSlip" style="display:none;">
    <div style="font-family:'Hind Siliguri',sans-serif;max-width:400px;margin:0 auto;border:2px solid #1a5276;border-radius:12px;padding:24px;">
        <div style="text-align:center;margin-bottom:16px;">
            <div style="font-size:20px;font-weight:700;color:#1a5276;"><?=e(getSetting('institute_name','মাদ্রাসা'))?></div>
            <div style="font-size:12px;color:#718096;">ভর্তি নিশ্চিতকরণ স্লিপ</div>
        </div>
        <table style="width:100%;font-size:13px;border-collapse:collapse;">
            <tr><td style="padding:6px 0;color:#718096;width:130px;">ছাত্রের নাম</td><td style="font-weight:700;"><?=e($student['name_bn']??$student['name'])?></td></tr>
            <tr><td style="padding:6px 0;color:#718096;">শ্রেণী</td><td style="font-weight:700;"><?=e($student['class_name_bn'])?> <?=e($student['section_name']??'')?></td></tr>
            <tr><td style="padding:6px 0;color:#718096;">রোল নম্বর</td><td style="font-weight:700;"><?=e($student['roll_number'])?></td></tr>
            <tr><td style="padding:6px 0;color:#718096;">Student ID</td><td style="font-weight:700;color:#1a5276;"><?=e($student['student_id'])?></td></tr>
            <tr><td style="padding:6px 0;color:#718096;">ভর্তির তারিখ</td><td><?=e($student['admission_date'])?></td></tr>
        </table>
        <div style="margin-top:16px;background:#fff8f0;border:2px dashed #e67e22;border-radius:8px;padding:14px;text-align:center;">
            <div style="font-size:12px;color:#718096;margin-bottom:6px;">অভিভাবক পোর্টাল লগইনের জন্য</div>
            <div style="font-size:13px;color:#1a5276;">Student ID: <strong><?=e($student['student_id'])?></strong></div>
            <div style="font-size:24px;font-weight:700;color:#e67e22;letter-spacing:6px;margin-top:4px;"><?=e($student['secret_code'])?></div>
            <div style="font-size:11px;color:#a0aec0;margin-top:6px;">এই কোড গোপন রাখুন — শুধু অভিভাবকের সাথে শেয়ার করুন</div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Stats Row -->
<div class="stat-grid" style="margin-bottom:24px;">
    <div class="stat-card blue">
        <div class="stat-icon"><i class="fas fa-clipboard-check"></i></div>
        <div><div class="stat-value"><?=toBanglaNumber($attRate)?>%</div><div class="stat-label">উপস্থিতি হার</div></div>
    </div>
    <div class="stat-card red">
        <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
        <div><div class="stat-value"><?=toBanglaNumber($att['absent']??0)?></div><div class="stat-label">অনুপস্থিতি</div></div>
    </div>
    <div class="stat-card green">
        <div class="stat-icon"><i class="fas fa-money-bill"></i></div>
        <div><div class="stat-value">৳<?=number_format($totalFee)?></div><div class="stat-label">ফি পরিশোধ (<?=date('Y')?>)</div></div>
    </div>
    <?php if ($student['hifz_para_complete']): ?>
    <div class="stat-card orange">
        <div class="stat-icon"><i class="fas fa-book-open"></i></div>
        <div><div class="stat-value"><?=toBanglaNumber($student['hifz_para_complete'])?>/৩০</div><div class="stat-label">হিফজ সম্পন্ন পারা</div></div>
    </div>
    <?php endif; ?>
</div>

<div class="grid-2">
    <!-- Personal Info -->
    <div class="card">
        <div class="card-header"><span class="card-title"><i class="fas fa-user"></i> ব্যক্তিগত তথ্য</span></div>
        <div class="card-body">
            <?php $rows = [
                ['জন্ম তারিখ', banglaDate($student['date_of_birth']??'')],
                ['লিঙ্গ', $student['gender']==='male'?'ছেলে':'মেয়ে'],
                ['ধর্ম', $student['religion']??''],
                ['রক্তের গ্রুপ', $student['blood_group']??''],
                ['জন্ম নিবন্ধন', $student['birth_certificate_no']??''],
                ['বোর্ড রেজি.', $student['board_registration_no']??''],
                ['পূর্ববর্তী প্রতিষ্ঠান', $student['previous_school']??''],
                ['ঠিকানা', $student['address_present']??''],
            ];
            foreach ($rows as [$label, $val]): if (!$val) continue; ?>
            <div style="display:flex;padding:8px 0;border-bottom:1px solid var(--border);font-size:13px;">
                <span style="width:140px;color:var(--text-muted);flex-shrink:0;"><?=e($label)?></span>
                <span style="font-weight:500;"><?=e($val)?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Parent Info -->
    <div class="card">
        <div class="card-header"><span class="card-title"><i class="fas fa-users"></i> অভিভাবকের তথ্য</span></div>
        <div class="card-body">
            <?php $rows2 = [
                ['পিতার নাম', $student['father_name']??''],
                ['পিতার ফোন', $student['father_phone']??''],
                ['মাতার নাম', $student['mother_name']??''],
                ['অভিভাবকের ফোন', $student['guardian_phone']??''],
                ['পিতার পেশা', $student['father_occupation']??''],
            ];
            foreach ($rows2 as [$label, $val]): if (!$val) continue; ?>
            <div style="display:flex;padding:8px 0;border-bottom:1px solid var(--border);font-size:13px;">
                <span style="width:140px;color:var(--text-muted);flex-shrink:0;"><?=e($label)?></span>
                <span style="font-weight:500;"><?=e($val)?></span>
            </div>
            <?php endforeach; ?>
            <?php if ($student['father_phone']||$student['guardian_phone']): ?>
            <div style="margin-top:12px;">
                <a href="tel:<?=e($student['father_phone']??$student['guardian_phone'])?>" class="btn btn-success btn-sm">
                    <i class="fas fa-phone"></i> কল করুন
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Marks -->
    <div class="card">
        <div class="card-header">
            <span class="card-title"><i class="fas fa-file-alt"></i> সাম্প্রতিক ফলাফল</span>
            <a href="<?=BASE_URL?>/modules/exam/result.php?student_id=<?=$id?>" class="btn btn-outline btn-sm">সব দেখুন</a>
        </div>
        <div class="card-body" style="padding:0;">
            <table>
                <thead><tr><th>পরীক্ষা</th><th>বিষয়</th><th>নম্বর</th><th>গ্রেড</th></tr></thead>
                <tbody>
                    <?php if (empty($examMarks)): ?>
                    <tr><td colspan="4" style="text-align:center;padding:20px;color:#718096;">কোনো ফলাফল নেই</td></tr>
                    <?php else: foreach ($examMarks as $m): ?>
                    <tr>
                        <td style="font-size:12px;"><?=e($m['exam_name_bn'])?></td>
                        <td style="font-size:12px;"><?=e($m['subject_name_bn'])?></td>
                        <td style="font-weight:700;"><?=$m['is_absent']?'AB':e($m['total_marks'])?></td>
                        <td><span class="badge badge-<?=$m['grade']==='F'?'danger':($m['grade']==='A+'?'success':'info')?>"><?=e($m['grade'])?></span></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Fee History -->
    <div class="card">
        <div class="card-header">
            <span class="card-title"><i class="fas fa-money-bill"></i> ফি ইতিহাস</span>
            <a href="<?=BASE_URL?>/modules/fees/student.php?id=<?=$id?>" class="btn btn-success btn-sm">ফি নিন</a>
        </div>
        <div class="card-body" style="padding:0;">
            <table>
                <thead><tr><th>তারিখ</th><th>ফির ধরন</th><th>পরিমাণ</th></tr></thead>
                <tbody>
                    <?php if (empty($feeHistory)): ?>
                    <tr><td colspan="3" style="text-align:center;padding:20px;color:#718096;">কোনো পরিশোধ নেই</td></tr>
                    <?php else: foreach ($feeHistory as $f): ?>
                    <tr>
                        <td style="font-size:12px;"><?=banglaDate($f['payment_date'])?></td>
                        <td style="font-size:13px;"><?=e($f['fee_name_bn'])?></td>
                        <td style="font-weight:700;color:var(--success);">৳<?=number_format($f['paid_amount'])?></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Fee Assignment Section -->
<?php if (in_array($_SESSION['role_slug'], ['super_admin','principal','accountant'])): ?>
<div class="card mt-24" style="border:2px solid #e67e22;">
    <div class="card-header" style="background:#fff8f0;">
        <span class="card-title" style="color:#e67e22;"><i class="fas fa-tags"></i> ব্যক্তিগত ফী নির্ধারণ</span>
        <button onclick="openModal('feeAssignModal')" class="btn btn-sm" style="background:#e67e22;color:#fff;">
            <i class="fas fa-plus"></i> ফী যোগ করুন
        </button>
    </div>
    <div class="card-body" style="padding:0;">
        <?php if (empty($feeAssignments)): ?>
        <div style="text-align:center;padding:20px;color:#718096;font-size:13px;">
            <i class="fas fa-info-circle"></i> কোনো ব্যক্তিগত ফী নির্ধারণ নেই — ডিফল্ট ফী প্রযোজ্য হবে।
        </div>
        <?php else: ?>
        <table>
            <thead><tr><th>ফীর নাম</th><th>ডিফল্ট</th><th>নির্ধারিত</th><th>পার্থক্য</th><th>কারণ</th><th>অ্যাকশন</th></tr></thead>
            <tbody>
            <?php foreach ($feeAssignments as $fa): ?>
            <tr>
                <td style="font-weight:600;"><?=e($fa['fee_name_bn'])?></td>
                <td style="color:#718096;">৳<?=number_format($fa['default_amount'])?></td>
                <td style="font-weight:700;color:#e67e22;">৳<?=number_format($fa['custom_amount'])?></td>
                <td>
                    <?php $diff = $fa['custom_amount'] - $fa['default_amount']; ?>
                    <span style="color:<?=$diff<0?'#27ae60':'#e74c3c';?>;font-weight:600;">
                        <?=$diff<0?'(-৳'.number_format(abs($diff)).')':'(+৳'.number_format($diff).')'?>
                    </span>
                </td>
                <td style="font-size:12px;color:#718096;"><?=e($fa['discount_reason']??'—')?></td>
                <td>
                    <a href="?id=<?=$id?>&remove_fee=<?=$fa['id']?>" onclick="return confirm('সরিয়ে দেবেন?')" class="btn btn-danger btn-xs">
                        <i class="fas fa-times"></i>
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- Fee Assign Modal -->
<div class="modal-overlay" id="feeAssignModal">
    <div class="modal-box" style="max-width:480px;">
        <div class="modal-header">
            <span style="font-weight:700;"><i class="fas fa-tag"></i> ফী নির্ধারণ — <?=e($student['name_bn']??$student['name'])?></span>
            <button onclick="closeModal('feeAssignModal')" class="btn btn-outline btn-xs">✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="save_fee_assign" value="1">
            <div class="modal-body">
                <div class="form-group">
                    <label>ফীর ধরন নির্বাচন করুন</label>
                    <select name="fee_type_id" class="form-control" id="feeTypeSelect" onchange="setDefaultAmount(this)" required>
                        <option value="">ফী নির্বাচন করুন</option>
                        <?php
                        $catLabels = ['monthly'=>'মাসিক','yearly'=>'বার্ষিক','one_time'=>'একবার','optional'=>'ঐচ্ছিক'];
                        $lastCat = '';
                        foreach ($feeTypes as $ft):
                            if ($ft['fee_category'] !== $lastCat) {
                                if ($lastCat) echo '</optgroup>';
                                echo '<optgroup label="'.$catLabels[$ft['fee_category']].' ফী">';
                                $lastCat = $ft['fee_category'];
                            }
                        ?>
                        <option value="<?=$ft['id']?>" data-amount="<?=$ft['amount']?>"
                            <?=isset($assignedMap[$ft['id']])?'disabled title="ইতোমধ্যে নির্ধারিত"':''?>>
                            <?=e($ft['fee_name_bn'])?> (ডিফল্ট: ৳<?=number_format($ft['amount'])?>)
                        </option>
                        <?php endforeach; if ($lastCat) echo '</optgroup>'; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>নির্ধারিত পরিমাণ (৳)</label>
                    <input type="number" name="custom_amount" id="customAmount" class="form-control" min="0" step="0.01" required placeholder="পরিমাণ লিখুন">
                    <small style="color:#718096;font-size:11px;" id="defaultHint"></small>
                </div>
                <div class="form-group">
                    <label>কারণ / মন্তব্য</label>
                    <input type="text" name="discount_reason" class="form-control" placeholder="যেমন: গরিব ছাত্র, বৃত্তিপ্রাপ্ত...">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="closeModal('feeAssignModal')" class="btn btn-outline">বাতিল</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> সংরক্ষণ</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
function setDefaultAmount(sel) {
    const opt = sel.options[sel.selectedIndex];
    const amount = opt.dataset.amount || '';
    document.getElementById('customAmount').value = amount;
    document.getElementById('defaultHint').textContent = amount ? 'ডিফল্ট পরিমাণ: ৳' + parseFloat(amount).toLocaleString() : '';
}
function printSlip() {
    const slip = document.getElementById('admissionSlip').innerHTML;
    const win = window.open('', '_blank', 'width=500,height=600');
    win.document.write(`
        <html><head>
        <meta charset="UTF-8">
        <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;600;700&display=swap" rel="stylesheet">
        <style>body{margin:20px;background:#fff;} @media print{body{margin:0;}}</style>
        </head><body>${slip}</body></html>
    `);
    win.document.close();
    setTimeout(() => { win.print(); win.close(); }, 800);
}
</script>

<?php require_once '../../includes/footer.php'; ?>
