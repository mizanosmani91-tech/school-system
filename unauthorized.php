<?php
require_once 'includes/functions.php';
startSession();
$roleSlug = $_SESSION['role_slug'] ?? '';

// Role অনুযায়ী ড্যাশবোর্ডে পাঠানো
if ($roleSlug === 'teacher') {
    header('Location: ' . BASE_URL . '/modules/teacher/dashboard.php');
} elseif ($roleSlug === 'parent') {
    header('Location: ' . BASE_URL . '/modules/parent/portal.php');
} elseif ($roleSlug === 'student') {
    header('Location: ' . BASE_URL . '/modules/student/portal.php');
} elseif (in_array($roleSlug, ['super_admin','principal','accountant'])) {
    header('Location: ' . BASE_URL . '/index.php');
} else {
    header('Location: ' . BASE_URL . '/login.php');
}
exit;
