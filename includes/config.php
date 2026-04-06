<?php
// ================================================================
// কনফিগারেশন ফাইল — Railway Production Ready
// ================================================================

// ── Database (Railway environment variables থেকে নেবে) ──────────
define('DB_HOST',    getenv('MYSQLHOST')     ?: 'mysql.railway.internal');
define('DB_USER',    getenv('MYSQLUSER')     ?: 'root');
define('DB_PASS',    getenv('MYSQLPASSWORD') ?: '');
define('DB_NAME',    getenv('MYSQLDATABASE') ?: 'railway');
define('DB_PORT',    getenv('MYSQLPORT')     ?: 3306);
define('DB_CHARSET', 'utf8mb4');

// ── App URL (Railway deployment URL) ────────────────────────────
// Railway এ APP_URL environment variable সেট করুন
// যেমন: https://school-system-production-d310.up.railway.app
define('BASE_URL',  rtrim(getenv('APP_URL') ?: 'http://localhost/school_system', '/'));
define('BASE_PATH', dirname(__DIR__));

// ── App Info ────────────────────────────────────────────────────
define('APP_NAME',    'স্কুল ম্যানেজমেন্ট সিস্টেম');
define('APP_VERSION', '1.0.0');
define('APP_LANG',    'bn');

// ── File Uploads ────────────────────────────────────────────────
define('UPLOAD_PATH', BASE_PATH . '/assets/uploads/');
define('UPLOAD_URL',  BASE_URL  . '/assets/uploads/');

// ── Session ─────────────────────────────────────────────────────
define('SESSION_LIFETIME', 3600 * 8); // 8 hours
define('SESSION_NAME',     'school_sess');

// ── AI API (Anthropic) ──────────────────────────────────────────
define('AI_API_KEY', getenv('AI_API_KEY') ?: '');
define('AI_MODEL',   'claude-sonnet-4-20250514');

// ── Timezone ────────────────────────────────────────────────────
date_default_timezone_set('Asia/Dhaka');

// ── Error Reporting ─────────────────────────────────────────────
// Railway production এ DEBUG_MODE = false রাখুন
define('DEBUG_MODE', (bool)(getenv('DEBUG_MODE') ?: false));
if (DEBUG_MODE) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}
