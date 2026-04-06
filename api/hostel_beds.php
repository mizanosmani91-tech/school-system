<?php
require_once '../includes/functions.php';
requireLogin();
header('Content-Type: application/json');
$db = getDB();
$roomId = (int)($_GET['room_id'] ?? 0);
if (!$roomId) { echo '[]'; exit; }
try {
    $stmt = $db->prepare("SELECT * FROM hostel_beds WHERE room_id=? ORDER BY bed_no");
    $stmt->execute([$roomId]);
    echo json_encode($stmt->fetchAll());
} catch(Exception $e) { echo '[]'; }
