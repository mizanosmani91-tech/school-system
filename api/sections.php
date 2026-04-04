<?php
require_once '../includes/functions.php';
startSession();
header('Content-Type: application/json');

$action = $_GET['action'] ?? basename($_SERVER['PHP_SELF'], '.php');
$db = getDB();

// Sections by class
if (isset($_GET['class_id'])) {
    $classId = (int)$_GET['class_id'];
    $stmt = $db->prepare("SELECT id, section_name FROM sections WHERE class_id=? ORDER BY section_name");
    $stmt->execute([$classId]);
    echo json_encode($stmt->fetchAll());
    exit;
}

echo json_encode([]);
