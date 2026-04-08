<?php
require_once '../includes/functions.php';
startSession();
if (!isset($_SESSION['user_id'])) { echo json_encode([]); exit; }
header('Content-Type: application/json; charset=utf-8');
$db = getDB();

$q        = trim($_GET['q'] ?? '');
$classId  = (int)($_GET['class_id'] ?? 0);
$divId    = (int)($_GET['division_id'] ?? 0);

$where  = "s.status='active'";
$params = [];

// নাম / ID / ফোন দিয়ে খোঁজা
if ($q !== '') {
    $where .= " AND (s.name_bn LIKE ? OR s.name LIKE ? OR s.student_id LIKE ? OR s.father_phone LIKE ? OR s.guardian_phone LIKE ?)";
    $like = "%$q%";
    $params = array_merge($params, [$like, $like, $like, $like, $like]);
}

// শ্রেণী ফিল্টার
if ($classId) {
    $where .= " AND s.class_id = ?";
    $params[] = $classId;
}

// বিভাগ ফিল্টার — classes টেবিলের division_id দিয়ে ফিল্টার
if ($divId) {
    $where .= " AND c.division_id = ?";
    $params[] = $divId;
}

// q খালি এবং class/division দেওয়া না থাকলে খালি return
if ($q === '' && !$classId && !$divId) {
    echo json_encode([]);
    exit;
}

$sql = "SELECT s.id, s.student_id, s.name_bn, s.name, s.roll_number,
               s.father_phone, s.guardian_phone,
               c.class_name_bn, d.division_name_bn
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN divisions d ON c.division_id = d.id
        WHERE $where
        ORDER BY d.sort_order, c.class_numeric, s.roll_number
        LIMIT 30";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($students, JSON_UNESCAPED_UNICODE);
