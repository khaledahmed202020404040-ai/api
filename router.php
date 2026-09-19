<?php
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$uri = $uri === '' ? '/' : $uri;
$rootDir = __DIR__;

$normalized = preg_replace('#/+#', '/', $uri) ?? '/';
$candidates = [];

if ($normalized === '/' || $normalized === '') {
    $candidates[] = $rootDir . '/api/android/index.php';
} else {
    $candidates[] = $rootDir . '/' . ltrim($normalized, '/');
    $candidates[] = $rootDir . str_replace('/', DIRECTORY_SEPARATOR, $normalized);
    $candidates[] = $rootDir . '/api' . $normalized;
}

foreach ($candidates as $candidate) {
    $candidate = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $candidate);
    if (file_exists($candidate) && is_file($candidate)) {
        require $candidate;
        return;
    }
}

if (preg_match('#^/api/?$#', $normalized) === 1) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status' => 'success',
        'success' => true,
        'message' => 'API root',
    ], JSON_UNESCAPED_SLASHES);
    return;
}

header('Content-Type: application/json; charset=utf-8');
http_response_code(404);
echo json_encode([
    'status' => 'error',
    'success' => false,
    'message' => 'Route not found',
    'path' => $normalized,
    'candidates' => $candidates,
], JSON_UNESCAPED_SLASHES);
