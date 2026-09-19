<?php
header('Content-Type: application/json; charset=utf-8');

$latestVersion = '256.0.3';
$currentVersion = $_GET['version'] ?? $latestVersion;
$forceUpdate = false;
$updateRequired = version_compare($currentVersion, $latestVersion, '<');

if ($updateRequired) {
    $message = 'new version available';
} else {
    $message = 'up to date';
}

echo json_encode([
    'status' => 'success',
    'success' => true,
    'update_required' => $updateRequired,
    'force_update' => $forceUpdate,
    'latest_version' => $latestVersion,
    'version' => $currentVersion,
    'app' => 'Gooobet',
    'apk_url' => 'https://api-production-69b69.up.railway.app/api/android/apk/Gooobet.apk',
    'message' => $message,
], JSON_UNESCAPED_SLASHES);
