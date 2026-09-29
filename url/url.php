<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function send_json(int $statusCode, array $body): never
{
    http_response_code($statusCode);
    echo json_encode($body, JSON_UNESCAPED_SLASHES);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? '';

if ($method === 'GET') {
    send_json(200, [
        'ok' => true,
        'mock' => true,
        'message' => 'URL route is available',
    ]);
}

if ($method !== 'POST') {
    http_response_code(405);
    header('Allow: GET, POST');
    send_json(405, [
        'ok' => false,
        'mock' => true,
        'message' => 'GET or POST only',
    ]);
}

$rawBody = file_get_contents('php://input', false, null, 0, 2049);
if (strlen($rawBody) > 2048) {
    send_json(413, [
        'ok' => false,
        'mock' => true,
        'message' => 'Request body too large',
    ]);
}

$payload = json_decode($rawBody, true);
$keys = is_array($payload) ? array_keys($payload) : [];
$expectedKeys = ['demoId', 'mock', 'resource'];
sort($keys);
sort($expectedKeys);

if (
    !is_array($payload)
    || $keys !== $expectedKeys
    || ($payload['mock'] ?? null) !== true
    || ($payload['demoId'] ?? null) !== 'demo-001'
    || ($payload['resource'] ?? null) !== 'sample.js'
) {
    send_json(400, [
        'ok' => false,
        'mock' => true,
        'message' => 'Expected the fixed dummy payload only',
    ]);
}

send_json(200, [
    'ok' => true,
    'mock' => true,
    'received' => [
        'demoId' => 'demo-001',
        'resource' => 'sample.js',
    ],
    'response' => [
        'contentType' => 'application/javascript; charset=utf-8',
        'body' => 'window.__loopbackLab = "mock-response";',
    ],
]);
