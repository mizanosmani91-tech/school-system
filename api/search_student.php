<?php
require_once '../includes/functions.php';
startSession();
if (!isset($_SESSION['user_id'])) { echo json_encode([]); exit; }
header('Content-Type: application/json');
$db = getDB();

$q       = '%' . trim($_GET['q'] ?? '') . '%';
$classId = (int)($_GET['class_id'] ?? 0);

if ($classId) {
    $stmt = $db->prepare("SELECT s.id, s.name_bn, s.name, s.student_id, s.roll_number, c.class_name_bn
        FROM students s LEFT JOIN classes c ON s.class_id=c.id
        WHERE s.class_id=? AND s.status='active'
        AND (s.name LIKE ? OR s.name_bn LIKE ? OR s.student_id LIKE ?)
        ORDER BY s.roll_number LIMIT 30");
    $stmt->execute([$classId, $q, $q, $q]);
} else {
    $stmt = $db->prepare("SELECT s.id, s.name_bn, s.name, s.student_id, s.roll_number, c.class_name_bn
        FROM students s LEFT JOIN classes c ON s.class_id=c.id
        WHERE (s.name LIKE ? OR s.name_bn LIKE ? OR s.student_id LIKE ? OR s.father_phone LIKE ?)
        AND s.status='active' LIMIT 15");
    $stmt->execute([$q, $q, $q, $q]);
}

echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
