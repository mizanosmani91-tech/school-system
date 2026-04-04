<?php
// ================================================================
// ডাটাবেস কানেকশন ও কোর ফাংশন
// ================================================================

require_once __DIR__ . '/config.php';

// PDO কানেকশন
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $port = defined('DB_PORT') ? DB_PORT : '3306';
            $dsn = "mysql:host=" . DB_HOST . ";port=" . $port . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            die('<div style="color:red;padding:20px;font-family:sans-serif;">
                <h3>ডাটাবেস সংযোগ ব্যর্থ!</h3>
                <p>' . htmlspecialchars($e->getMessage()) . '</p>
                <p><a href="install/">ইনস্টল করুন</a></p>
            </div>');
        }
    }
    return $pdo;
}

// Session শুরু
function startSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_set_cookie_params(SESSION_LIFETIME);
        session_start();
    }
}

// Login চেক
function requireLogin($roles = []) {
    startSession();
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
    if (!empty($roles) && !in_array($_SESSION['role_slug'], $roles)) {
        header('Location: ' . BASE_URL . '/unauthorized.php');
        exit;
    }
}

// বর্তমান ব্যবহারকারী
function getCurrentUser() {
    startSession();
    if (!isset($_SESSION['user_id'])) return null;
    $db = getDB();
    $stmt = $db->prepare("SELECT u.*, r.role_name, r.role_slug, r.permissions FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

// সেটিং পড়া
function getSetting($key, $default = '') {
    static $settings = [];
    if (empty($settings)) {
        $db = getDB();
        $rows = $db->query("SELECT setting_key, setting_value FROM settings")->fetchAll();
        foreach ($rows as $r) $settings[$r['setting_key']] = $r['setting_value'];
    }
    return $settings[$key] ?? $default;
}

// XSS প্রতিরোধ
function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

// CSRF Token
function getCsrfToken() {
    startSession();
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf($token) {
    startSession();
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// গ্রেড হিসাব (বাংলাদেশ শিক্ষা বোর্ড অনুযায়ী)
function calculateGrade($marks, $fullMarks = 100) {
    $percent = ($marks / $fullMarks) * 100;
    if ($percent >= 80) return ['grade' => 'A+', 'point' => 5.00, 'remark' => 'অসাধারণ'];
    if ($percent >= 70) return ['grade' => 'A',  'point' => 4.00, 'remark' => 'খুব ভালো'];
    if ($percent >= 60) return ['grade' => 'A-', 'point' => 3.50, 'remark' => 'ভালো'];
    if ($percent >= 50) return ['grade' => 'B',  'point' => 3.00, 'remark' => 'মোটামুটি ভালো'];
    if ($percent >= 40) return ['grade' => 'C',  'point' => 2.00, 'remark' => 'গড়'];
    if ($percent >= 33) return ['grade' => 'D',  'point' => 1.00, 'remark' => 'উত্তীর্ণ'];
    return ['grade' => 'F', 'point' => 0.00, 'remark' => 'অনুত্তীর্ণ'];
}

// বাংলা সংখ্যা রূপান্তর
function toBanglaNumber($n) {
    $en = ['0','1','2','3','4','5','6','7','8','9'];
    $bn = ['০','১','২','৩','৪','৫','৬','৭','৮','৯'];
    return str_replace($en, $bn, $n);
}

// বাংলা তারিখ
function banglaDate($date = null) {
    $months = ['','জানুয়ারি','ফেব্রুয়ারি','মার্চ','এপ্রিল','মে','জুন','জুলাই','আগস্ট','সেপ্টেম্বর','অক্টোবর','নভেম্বর','ডিসেম্বর'];
    $ts = $date ? strtotime($date) : time();
    $d = date('j', $ts);
    $m = (int)date('n', $ts);
    $y = date('Y', $ts);
    return toBanglaNumber($d) . ' ' . $months[$m] . ' ' . toBanglaNumber($y);
}

// রসিদ নম্বর তৈরি
function generateReceiptNo() {
    return 'RCP-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
}

// ছাত্র ID তৈরি
function generateStudentId($classId) {
    $db = getDB();
    $count = $db->query("SELECT COUNT(*) FROM students")->fetchColumn();
    return 'STU-' . date('Y') . '-' . str_pad($classId, 2, '0', STR_PAD_LEFT) . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
}

// Pagination
function paginate($total, $perPage, $currentPage, $url) {
    $totalPages = ceil($total / $perPage);
    if ($totalPages <= 1) return '';
    $html = '<nav><ul class="pagination">';
    for ($i = 1; $i <= $totalPages; $i++) {
        $active = $i == $currentPage ? 'active' : '';
        $html .= '<li class="page-item ' . $active . '"><a class="page-link" href="' . $url . '?page=' . $i . '">' . toBanglaNumber($i) . '</a></li>';
    }
    $html .= '</ul></nav>';
    return $html;
}

// Activity Log
function logActivity($userId, $action, $module, $details = '') {
    try {
        $db = getDB();
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, module, details, ip_address) VALUES (?,?,?,?,?)");
        $stmt->execute([$userId, $action, $module, $details, $ip]);
    } catch (Exception $e) { /* silent */ }
}

// AI API কল (Claude)
function callAI($message, $systemPrompt = '', $history = []) {
    $apiKey = getSetting('ai_api_key', AI_API_KEY);
    if (empty($apiKey)) return ['error' => 'AI API Key সেট করা নেই।'];

    $messages = [];
    foreach ($history as $h) $messages[] = $h;
    $messages[] = ['role' => 'user', 'content' => $message];

    $body = [
        'model' => AI_MODEL,
        'max_tokens' => 1024,
        'messages' => $messages,
    ];
    if ($systemPrompt) $body['system'] = $systemPrompt;

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($body),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'x-api-key: ' . $apiKey,
            'anthropic-version: 2023-06-01',
        ],
        CURLOPT_TIMEOUT => 30,
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($resp, true);
    if (isset($data['content'][0]['text'])) {
        return ['success' => true, 'text' => $data['content'][0]['text']];
    }
    return ['error' => $data['error']['message'] ?? 'অজানা ত্রুটি'];
}

// Flash Message
function setFlash($type, $msg) {
    startSession();
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function getFlash() {
    startSession();
    if (isset($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}
