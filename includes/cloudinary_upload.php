<?php
/**
 * Cloudinary Upload Helper
 * ফাইল: includes/cloudinary_upload.php
 *
 * Credentials আসবে environment variables থেকে:
 *   CLOUDINARY_CLOUD_NAME
 *   CLOUDINARY_API_KEY
 *   CLOUDINARY_API_SECRET
 *
 * Railway Dashboard → Variables → এই তিনটা add করুন
 */

function uploadToCloudinary(string $filePath, string $folder = 'students'): ?string {

    // ===== Credentials লোড =====
    // আগে environment variable দেখো, না পেলে define করা constant দেখো
    $cloudName = getenv('CLOUDINARY_CLOUD_NAME')
        ?: (defined('CLOUDINARY_CLOUD_NAME') ? CLOUDINARY_CLOUD_NAME : '');
    $apiKey    = getenv('CLOUDINARY_API_KEY')
        ?: (defined('CLOUDINARY_API_KEY')    ? CLOUDINARY_API_KEY    : '');
    $apiSecret = getenv('CLOUDINARY_API_SECRET')
        ?: (defined('CLOUDINARY_API_SECRET') ? CLOUDINARY_API_SECRET : '');

    // Credentials না থাকলে সাথে সাথে bail out
    if (!$cloudName || !$apiKey || !$apiSecret) {
        error_log('[Cloudinary] Credentials missing! CLOUDINARY_CLOUD_NAME / CLOUDINARY_API_KEY / CLOUDINARY_API_SECRET environment variable set করুন।');
        return null;
    }

    // ===== ফাইল চেক =====
    if (!file_exists($filePath)) {
        error_log('[Cloudinary] File not found: ' . $filePath);
        return null;
    }
    if (filesize($filePath) === 0) {
        error_log('[Cloudinary] File is empty: ' . $filePath);
        return null;
    }

    // ===== Signature তৈরি =====
    $timestamp = time();
    $params    = [
        'folder'    => $folder,
        'timestamp' => $timestamp,
    ];
    ksort($params);

    $sigParts = [];
    foreach ($params as $k => $v) {
        $sigParts[] = "$k=$v";
    }
    $signature = sha1(implode('&', $sigParts) . $apiSecret);

    // ===== cURL দিয়ে আপলোড =====
    $url = "https://api.cloudinary.com/v1_1/{$cloudName}/image/upload";

    $postFields = [
        'file'      => new CURLFile($filePath),
        'folder'    => $folder,
        'timestamp' => $timestamp,
        'api_key'   => $apiKey,
        'signature' => $signature,
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $postFields,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 60,          // ৬০ সেকেন্ড সময় দাও
        CURLOPT_CONNECTTIMEOUT => 15,          // connect timeout
        CURLOPT_SSL_VERIFYPEER => true,        // SSL verify রাখো
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    $curlErrNo= curl_errno($ch);
    curl_close($ch);

    // cURL level error
    if ($curlErr) {
        error_log("[Cloudinary] cURL error ({$curlErrNo}): {$curlErr}");
        return null;
    }

    // HTTP response parse
    $data = json_decode($response, true);

    if ($httpCode === 200 && !empty($data['secure_url'])) {
        return $data['secure_url']; // ✅ সফল — Cloudinary URL ফেরত দাও
    }

    // Failure log — Railway logs এ দেখা যাবে
    $errMsg = $data['error']['message'] ?? $response;
    error_log("[Cloudinary] Upload failed. HTTP: {$httpCode} | Error: {$errMsg} | Folder: {$folder}");
    return null;
}
