<?php
// ================================================================
// কনফিগারেশন ফাইল — Railway Environment Variables সাপোর্ট
// ================================================================

// Railway MySQL plugin দিলে এগুলো auto সেট হয়
define('DB_HOST',    getenv('MYSQLHOST')     ?: 'localhost');
define('DB_USER',    getenv('MYSQLUSER')     ?: 'root');
define('DB_PASS',    getenv('MYSQLPASSWORD') ?: '');
define('DB_NAME',    getenv('MYSQLDATABASE') ?: 'school_db');
define('DB_PORT',    getenv('MYSQLPORT')     ?: '3306');
define('DB_CHARSET', 'utf8mb4');

// Railway public domain auto detect
$_railwayDomain = getenv('RAILWAY_PUBLIC_DOMAIN');
define('BASE_URL', $_railwayDomain ? 'https://'.$_railwayDomain : (getenv('APP_URL') ?: 'http://localhost'));
define('BASE_PATH', dirname(__DIR__));

define('APP_NAME', 'স্কুল ম্যানেজমেন্ট সিস্টেম');
define('APP_VERSION', '1.0.0');
define('APP_LANG', 'bn');

define('UPLOAD_PATH', BASE_PATH . '/assets/uploads/');
define('UPLOAD_URL', BASE_URL . '/assets/uploads/');

// Session
define('SESSION_LIFETIME', 3600 * 8); // 8 hours
define('SESSION_NAME', 'school_sess');

// AI API (Claude / Anthropic)
define('AI_API_KEY', ''); // আপনার Anthropic API Key এখানে
define('AI_MODEL', 'claude-sonnet-4-20250514');

// Timezone
date_default_timezone_set('Asia/Dhaka');

// Error Reporting (production এ false করুন)
define('DEBUG_MODE', true);
if (DEBUG_MODE) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}
