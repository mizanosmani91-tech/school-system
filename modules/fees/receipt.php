<?php
require_once '../../includes/functions.php';
requireLogin();
$db = getDB();
$id   = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare("SELECT fc.*, ft.fee_name_bn, s.name_bn as sname, s.name as sname_en,
    s.student_id as sid, s.father_name, c.class_name_bn, d.division_name_bn
    FROM fee_collections fc
    JOIN fee_types ft ON fc.fee_type_id=ft.id
    JOIN students s ON fc.student_id=s.id
    LEFT JOIN classes c ON s.class_id=c.id
    LEFT JOIN divisions d ON s.division_id=d.id
    WHERE fc.id=?");
$stmt->execute([$id]);
$rec = $stmt->fetch();
if (!$rec) { setFlash('danger', 'রসিদ পাওয়া যায়নি।'); header('Location: collection.php'); exit; }
$instituteName = getSetting('institute_name');
$pageTitle     = 'ফি রসিদ';
require_once '../../includes/header.php';
?>

<div class="section-header no-print">
    <h2 class="section-title"><i class="fas fa-receipt"></i> ফি রসিদ</h2>
    <div style="display:flex;gap:8px;">
        <button onclick="window.print()" class="btn btn-primary btn-sm"><i class="fas fa-print"></i> প্রিন্ট</button>
        <a href="collection.php" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> ফিরুন</a>
    </div>
</div>

<div class="card" style="max-width:580px;margin:0 auto;">
    <div style="padding:24px;">
        <div style="text-align:center;border-bottom:2px dashed var(--border);padding-bottom:16px;margin-bottom:16px;">
            <div style="font-size:22px;font-weight:700;color:var(--primary);"><?= e($instituteName) ?></div>
            <div style="font-size:13px;color:var(--text-muted);"><?= e(getSetting('address')) ?></div>
            <div style="font-size:13px;color:var(--text-muted);">ফোন: <?= e(getSetting('phone')) ?></div>
            <div style="font-size:18px;font-weight:700;margin-top:12px;color:var(--accent);">ফি পরিশোধের রসিদ</div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:14px;margin-bottom:16px;">
            <?php $info = [
                'রসিদ নম্বর'  => $rec['receipt_number'] ?? $rec['id'],
                'তারিখ'       => banglaDate($rec['payment_date']),
                'ছাত্রের নাম' => $rec['sname'] ?? $rec['sname_en'],
                'ছাত্র ID'    => $rec['sid'],
                'বিভাগ'       => $rec['division_name_bn'] ?? '',
                'শ্রেণী'      => $rec['class_name_bn'] ?? '',
                'পিতার নাম'  => $rec['father_name'] ?? '',
                'ফির ধরন'    => $rec['fee_name_bn'] ?? '',
                'মাস'         => $rec['month_year'] ?? '-',
            ];
            foreach ($info as $k => $v): if (!$v) continue; ?>
            <div>
                <span style="color:var(--text-muted);"><?= e($k) ?>: </span>
                <strong><?= e($v) ?></strong>
            </div>
            <?php endforeach; ?>
        </div>

        <div style="background:var(--bg);border-radius:8px;padding:16px;margin:16px 0;">
            <div style="display:flex;justify-content:space-between;padding:4px 0;font-size:13px;">
                <span>মূল পরিমাণ</span><span>৳<?= number_format($rec['amount'] ?? 0) ?></span>
            </div>
            <?php if (($rec['discount'] ?? 0) > 0): ?>
            <div style="display:flex;justify-content:space-between;padding:4px 0;font-size:13px;color:var(--success);">
                <span>ছাড়</span><span>-৳<?= number_format($rec['discount']) ?></span>
            </div>
            <?php endif; ?>
            <?php if (($rec['fine'] ?? 0) > 0): ?>
            <div style="display:flex;justify-content:space-between;padding:4px 0;font-size:13px;color:var(--danger);">
                <span>জরিমানা</span><span>+৳<?= number_format($rec['fine']) ?></span>
            </div>
            <?php endif; ?>
            <div style="display:flex;justify-content:space-between;padding:8px 0 0;font-size:18px;font-weight:700;border-top:1px solid var(--border);margin-top:8px;">
                <span>মোট পরিশোধ</span>
                <span style="color:var(--success);">৳<?= number_format($rec['paid_amount'] ?? (($rec['amount'] ?? 0) - ($rec['discount'] ?? 0) + ($rec['fine'] ?? 0))) ?></span>
            </div>
        </div>

        <div style="display:flex;justify-content:space-between;font-size:13px;color:var(--text-muted);">
            <span>পরিশোধ পদ্ধতি: <strong style="color:var(--text);"><?= e($rec['payment_method'] ?? '') ?></strong></span>
            <?php if (!empty($rec['transaction_id'])): ?>
            <span>TXN: <?= e($rec['transaction_id']) ?></span>
            <?php endif; ?>
        </div>

        <div style="text-align:center;margin-top:24px;padding-top:16px;border-top:1px dashed var(--border);font-size:12px;color:var(--text-muted);">
            <p>এটি একটি কম্পিউটার-জেনারেটেড রসিদ, কোনো স্বাক্ষরের প্রয়োজন নেই।</p>
            <p style="margin-top:4px;">প্রিন্ট করা হয়েছে: <?= banglaDate() ?></p>
        </div>
    </div>
</div>

<script>window.onload = function () { window.print(); }</script>
<?php require_once '../../includes/footer.php'; ?>
