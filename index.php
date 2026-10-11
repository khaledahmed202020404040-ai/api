<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// AES Encryption Configuration
$AES_KEY = "rsSeRIGfYsedC5YopKeIoA==";
$AES_KEY_DECODED = base64_decode($AES_KEY);

function encrypt_aes($data, $key) {
    $iv = str_repeat("\0", 16);
    $encrypted = openssl_encrypt($data, 'AES-192-ECB', $key, OPENSSL_RAW_DATA);
    return base64_encode($encrypted);
}

$response = json_encode([
    'status' => 'success',
    'success' => true,
    'update_required' => false,
    'force_update' => false,
    'latest_version' => '256.0.3',
    'version' => '256.0.3',
    'app' => 'Gooobet',
    'apk_url' => 'https://api-production-69b69.up.railway.app/api/android/apk/Gooobet.apk',
    'message' => 'up to date'
], JSON_UNESCAPED_SLASHES);

echo encrypt_aes($response, $AES_KEY_DECODED);
