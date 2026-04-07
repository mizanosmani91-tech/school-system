<?php
/**
 * dashboard_settings_api.php
 * Place at: /api/dashboard_settings_api.php
 *
 * GET  → returns current user's card config
 * POST → saves current user's card config
 */

require_once __DIR__ . '/../config.php'; // আপনার config path অনুযায়ী ঠিক করুন
require_once __DIR__ . '/../functions.php';

header('Content-Type: application/json');

// Auth check
if (!isset($currentUser['id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = (int) $currentUser['id'];
$db = getDB();

// ──────────────────────────────────────────────
// GET — load settings
// ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $row = $db->prepare("SELECT cards_json FROM dashboard_card_settings WHERE user_id = ?");
    $row->execute([$userId]);
    $result = $row->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        echo json_encode(['success' => true, 'cards' => json_decode($result['cards_json'], true)]);
    } else {
        // Default cards — first time user
        echo json_encode(['success' => true, 'cards' => null]);
    }
    exit;
}

// ──────────────────────────────────────────────
// POST — save settings
// ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);

    if (!isset($body['cards']) || !is_array($body['cards'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid data']);
        exit;
    }

    // Sanitize — only allow known keys per card
    $allowed = ['id', 'label', 'icon', 'color', 'size', 'visible', 'order', 'dataKey'];
    $cards = array_map(function($card) use ($allowed) {
        return array_intersect_key($card, array_flip($allowed));
    }, $body['cards']);

    $json = json_encode($cards, JSON_UNESCAPED_UNICODE);

    $stmt = $db->prepare("
        INSERT INTO dashboard_card_settings (user_id, cards_json)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE cards_json = VALUES(cards_json)
    ");
    $stmt->execute([$userId, $json]);

    echo json_encode(['success' => true, 'message' => 'সেটিংস সংরক্ষিত হয়েছে']);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
