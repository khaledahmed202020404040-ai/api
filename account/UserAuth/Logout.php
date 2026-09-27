<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

echo json_encode([
    'Error' => null,
    'Success' => true,
    'ErrorCode' => null,
    'Value' => true,
    'status' => 'success',
    'message' => 'logged out',
    'logout' => true,
], JSON_UNESCAPED_SLASHES);
