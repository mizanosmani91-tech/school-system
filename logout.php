<?php
require_once 'includes/functions.php';
startSession();
logActivity($_SESSION['user_id'] ?? 0, 'logout', 'auth', 'লগআউট');
session_destroy();
header('Location: ' . BASE_URL . '/login.php');
exit;
