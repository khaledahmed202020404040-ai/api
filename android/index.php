<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$latestVersion = '256.0.3';

function sanitizeVersion($value)
{
    $clean = preg_replace('/[^0-9.]/', '', (string) $value);
    return $clean !== '' ? $clean : '0.0.0';
}

$currentVersion = sanitizeVersion($_GET['version'] ?? $latestVersion);
$forceUpdate = false;
$updateRequired = version_compare($currentVersion, $latestVersion, '<');
$message = $updateRequired ? 'new version available' : 'up to date';

echo json_encode([
    'status' => 'success',
    'success' => true,
    'update_required' => (bool) $updateRequired,
    'force_update' => $forceUpdate,
    'latest_version' => $latestVersion,
    'version' => $currentVersion,
    'app' => 'Gooobet',
    'apk_url' => 'https://api-production-69b69.up.railway.app/api/android/apk/Gooobet.apk',
    'message' => $message,
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
