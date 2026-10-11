<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Check AppGuid (optional - for compatibility with bytrox.shop)
$input = array_merge($_GET, $_POST, json_decode(file_get_contents('php://input'), true) ?: []);
$appGuid = $input['AppGuid'] ?? $input['appGuid'] ?? $input['app_guid'] ?? null;
// For now, accept any AppGuid or none - can be restricted later

// Generate random ID and password
$userId = rand(1000000000, 9999999999);
$password = substr(str_shuffle("abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789"), 0, 8);
$username = "user" . $userId;
$email = $username . "@auto.generated";

require_once __DIR__ . '/../../db.php';

try {
    $database = database();
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    
    $insert = $database->prepare('INSERT INTO users (username, email, password_hash, balance) VALUES (:username, :email, :password_hash, 1000)');
    $insert->execute([
        'username' => $username,
        'email' => $email,
        'password_hash' => $passwordHash,
    ]);
    
    $realUserId = (int) $database->lastInsertId();
    
    // AES Encryption
    $AES_KEY = "rsSeRIGfYsedC5YopKeIoA==";
    $AES_KEY_DECODED = base64_decode($AES_KEY);
    
    function encrypt_aes($data, $key) {
        $iv = str_repeat("\0", 16);
        $encrypted = openssl_encrypt($data, 'AES-192-ECB', $key, OPENSSL_RAW_DATA);
        return base64_encode($encrypted);
    }
    
    // Return in the format the app expects (encrypted)
    // Use realUserId from database, not the random userId
    $response = json_encode([
        'Error' => null,
        'Success' => true,
        'ErrorCode' => null,
        'Value' => [
            'id' => $realUserId,
            'userId' => $realUserId,
            'Id' => $realUserId,
            'user_id' => $realUserId,
            'username' => $username,
            'password' => $password,
            'balance' => 1000,
        ],
    ], JSON_UNESCAPED_SLASHES);
    
    echo encrypt_aes($response, $AES_KEY_DECODED);
} catch (Throwable $error) {
    echo json_encode([
        'Error' => $error->getMessage(),
        'Success' => false,
        'ErrorCode' => 'DatabaseError',
        'Value' => null,
    ]);
}
