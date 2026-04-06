<?php
// ===== Cloudinary Upload Helper =====
// includes/ ফোল্ডারে রাখুন: includes/cloudinary_upload.php

define('CLOUDINARY_CLOUD_NAME', 'dtvfwxwmz');
define('CLOUDINARY_API_KEY',    '218297424444165');
define('CLOUDINARY_API_SECRET', 'PHYeQcF8ryKHLfTB4sM8o_SaRmU');

/**
 * Cloudinary তে ছবি upload করে public URL return করে।
 * সফল হলে URL string, ব্যর্থ হলে false।
 *
 * @param string $tmpPath   $_FILES['photo']['tmp_name']
 * @param string $publicId  যেমন: students/ANT-2026-NP4X
 * @return string|false
 */
function uploadToCloudinary(string $tmpPath, string $publicId): string|false {
    $timestamp = time();
    // Passport size crop: 200x257
    $params = [
        'public_id'  => $publicId,
        'timestamp'  => $timestamp,
        'gravity'    => 'face',
        'crop'       => 'fill',
        'width'      => 200,
        'height'     => 257,
        'quality'    => 'auto',
        'fetch_format' => 'auto',
    ];

    // Signature তৈরি
    ksort($params);
    $sigStr = http_build_query($params) . CLOUDINARY_API_SECRET;
    $signature = sha1($sigStr);

    // multipart POST data
    $postFields = $params;
    $postFields['signature'] = $signature;
    $postFields['api_key']   = CLOUDINARY_API_KEY;
    $postFields['file']      = new CURLFile($tmpPath);

    $url = 'https://api.cloudinary.com/v1_1/' . CLOUDINARY_CLOUD_NAME . '/image/upload';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $postFields,
        CURLOPT_TIMEOUT        => 30,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) return false;

    $data = json_decode($response, true);
    return $data['secure_url'] ?? false;
}
