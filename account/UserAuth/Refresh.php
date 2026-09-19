<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$rawBody = trim((string) file_get_contents('php://input'));
$input = json_decode($rawBody, true);
if (!is_array($input)) {
    parse_str($rawBody, $input);
}
if (!is_array($input)) {
    $input = [];
}

$refreshToken = trim((string) ($input['RefreshToken'] ?? $input['refreshToken'] ?? $_POST['RefreshToken'] ?? ''));
if ($refreshToken === '') {
    http_response_code(400);
    echo json_encode([
        'Error' => 'RefreshToken is required',
        'Success' => false,
        'ErrorCode' => 'NotValidRefreshToken',
        'Value' => null,
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

$token = hash('sha256', 'cairo-city:' . $refreshToken . ':' . time());

echo json_encode([
    'Error' => null,
    'Success' => true,
    'ErrorCode' => null,
    'Value' => [
        'TokenExpiry' => 86400,
        'RefreshToken' => $refreshToken,
        'Token' => $token,
    ],
], JSON_UNESCAPED_SLASHES);
exit;
