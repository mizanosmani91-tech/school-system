<?php
require_once '../../includes/functions.php';
requireLogin();
$pageTitle = 'ছাত্রের প্রোফাইল';
$db = getDB();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: list.php'); exit; }

$stmt = $db->prepare("SELECT s.*, c.class_name_bn, c.class_name, sec.section_name
    FROM students s
    LEFT JOIN classes c ON s.class_id=c.id
    LEFT JOIN sections sec ON s.section_id=sec.id
    WHERE s.id=?");
$stmt->execute([$id]);
$student = $stmt->fetch();
if (!$student) { setFlash('danger','ছাত্র পাওয়া যায়নি।'); header('Location: list.php'); exit; }

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
                    <span><i class="fas fa-school"></i> <?=e($student['class_name_bn'])?> <?=e($student['section_name']??'')?></span>
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

<?php require_once '../../includes/footer.php'; ?>
