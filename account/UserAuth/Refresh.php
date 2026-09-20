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

$refreshToken = trim((string) ($input['RefreshToken'] ?? $input['refreshToken'] ?? $input['refresh_token'] ?? $_POST['RefreshToken'] ?? $_POST['refreshToken'] ?? ''));
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
$bearerToken = 'Bearer ' . $token;

$profile = [
    'id' => 0,
    'Id' => 0,
    'userId' => 0,
    'UserId' => 0,
    'username' => 'guest',
    'Username' => 'guest',
    'email' => null,
    'Email' => null,
    'balance' => 0.0,
    'Balance' => 0.0,
];

$payload = [
    'TokenExpiry' => 86400,
    'RefreshToken' => $refreshToken,
    'refreshToken' => $refreshToken,
    'RefreshExpiry' => 86400,
    'Token' => $token,
    'token' => $token,
    'Authorization' => $bearerToken,
    'authorization' => $bearerToken,
    'accessToken' => $token,
    'AccessToken' => $token,
    'refreshToken' => $refreshToken,
    'expiresIn' => 86400,
    'ExpiresIn' => 86400,
    'tokenType' => 'Bearer',
    'TokenType' => 'Bearer',
    'UserData' => $profile,
    'userData' => $profile,
    'User' => $profile,
    'user' => $profile,
];

echo json_encode([
    'Error' => null,
    'Success' => true,
    'ErrorCode' => null,
    'Value' => $payload,
], JSON_UNESCAPED_SLASHES);
exit;
