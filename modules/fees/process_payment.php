<?php
// modules/fees/process_payment.php
// ফি পেমেন্ট সংরক্ষণ

require_once '../../includes/functions.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: collection.php');
    exit;
}

$db = getDB();

// ফর্ম ডেটা নেওয়া
$student_id     = (int)($_POST['student_id'] ?? 0);
$fee_type_id    = (int)($_POST['fee_type_id'] ?? 0);
$amount         = floatval($_POST['amount'] ?? 0);
$discount       = floatval($_POST['discount'] ?? 0);
$fine           = floatval($_POST['fine'] ?? 0);
$total_amount   = floatval($_POST['total_amount'] ?? 0);
$payment_method = $_POST['payment_method'] ?? 'cash';
$payment_date   = $_POST['payment_date'] ?? date('Y-m-d');
$month_year     = $_POST['month_year'] ?? date('Y-m');
$remarks        = trim($_POST['remarks'] ?? '');

// ভ্যালিডেশন
if (!$student_id || !$fee_type_id || $amount <= 0) {
    setFlash('danger', 'অনুগ্রহ করে সব প্রয়োজনীয় তথ্য দিন।');
    header('Location: collection.php?student_id=' . $student_id);
    exit;
}

// paid_amount ক্যালকুলেট
$paid_amount = $amount - $discount + $fine;
if ($paid_amount < 0) $paid_amount = 0;

// রসিদ নম্বর তৈরি (FEE-YYYYMMDD-XXXX)
$today = date('Ymd');
$stmt = $db->prepare("SELECT COUNT(*) as cnt FROM fee_collections WHERE DATE(created_at) = CURDATE()");
$stmt->execute();
$count = $stmt->fetch()['cnt'] + 1;
$receipt_number = 'FEE-' . $today . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);

// লগইন ইউজারের ID
$collected_by = $_SESSION['user_id'] ?? null;

try {
    $stmt = $db->prepare("INSERT INTO fee_collections 
        (student_id, fee_type_id, amount, discount, fine, paid_amount, payment_date, payment_method, transaction_id, month_year, receipt_number, collected_by, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $stmt->execute([
        $student_id,
        $fee_type_id,
        $amount,
        $discount,
        $fine,
        $paid_amount,
        $payment_date,
        $payment_method,
        null, // transaction_id - বিকাশ/নগদ হলে পরে যোগ করা যাবে
        $month_year,
        $receipt_number,
        $collected_by,
        $remarks
    ]);

    $insert_id = $db->lastInsertId();

    setFlash('success', 'পেমেন্ট সফলভাবে সংরক্ষিত হয়েছে। রসিদ নম্বর: ' . $receipt_number);
    header('Location: receipt.php?id=' . $insert_id);
    exit;

} catch (PDOException $e) {
    setFlash('danger', 'পেমেন্ট সংরক্ষণে ত্রুটি: ' . $e->getMessage());
    header('Location: collection.php?student_id=' . $student_id);
    exit;
}