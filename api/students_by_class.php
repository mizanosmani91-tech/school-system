<?php
require_once '../../includes/functions.php';
requireLogin(['super_admin','principal','teacher']);

header('Content-Type: application/json');

$classId = (int)($_GET['class_id'] ?? 0);
$diaryId = (int)($_GET['diary_id'] ?? 0);

if (!$classId) { echo json_encode([]); exit; }

$db = getDB();

$stmt = $db->prepare("
    SELECT s.id, s.name, s.name_bn, s.roll_number,
           se.lesson_status, se.homework_status, se.is_inattentive, se.note
    FROM students s
    LEFT JOIN student_evaluations se ON se.student_id = s.id AND se.diary_id = ?
    WHERE s.class_id = ? AND s.status = 'active'
    ORDER BY s.roll_number ASC, s.name_bn ASC
");
$stmt->execute([$diaryId, $classId]);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
