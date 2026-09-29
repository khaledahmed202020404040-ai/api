<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo json_encode([
        'ok' => false,
        'mock' => true,
        'message' => 'GET only',
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

http_response_code(200);
echo json_encode([
    'ok' => true,
    'mock' => true,
    'message' => 'URL route is available',
], JSON_UNESCAPED_SLASHES);