<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/account/db.php';

// AES Encryption Configuration
$AES_KEY = "rsSeRIGfYsedC5YopKeIoA==";
$AES_KEY_DECODED = base64_decode($AES_KEY);

function encrypt_aes($data, $key) {
    $iv = str_repeat("\0", 16);
    $encrypted = openssl_encrypt($data, 'AES-192-ECB', $key, OPENSSL_RAW_DATA);
    return base64_encode($encrypted);
}

$input = array_merge($_GET, $_POST, $_REQUEST);

$username = isset($input['username']) ? trim((string) $input['username']) : '';
$email = isset($input['email']) ? trim((string) $input['email']) : '';
$password = isset($input['password']) ? (string) $input['password'] : '';
$balance = isset($input['balance']) ? (float) $input['balance'] : 0;

if ($username === '' || $email === '' || $password === '') {
    echo json_encode([
        'Error' => 'username, email and password are required',
        'Success' => false,
    ]);
    exit;
}

try {
    $database = database();

    $exists = $database->prepare('SELECT id FROM users WHERE username = :username OR email = :email LIMIT 1');
    $exists->execute(['username' => $username, 'email' => $email]);

    if ($exists->fetch()) {
        echo json_encode([
            'Error' => 'user already exists',
            'Success' => false,
        ]);
        exit;
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $insert = $database->prepare('INSERT INTO users (username, email, password_hash, balance) VALUES (:username, :email, :password_hash, :balance)');
    $insert->execute([
        'username' => $username,
        'email' => $email,
        'password_hash' => $passwordHash,
        'balance' => $balance,
    ]);

    $userId = (int) $database->lastInsertId();

    echo json_encode([
        'Error' => null,
        'Success' => true,
        'userId' => $userId,
        'username' => $username,
        'email' => $email,
        'balance' => $balance,
        'password' => $password,
    ]);
} catch (Throwable $error) {
    echo json_encode([
        'Error' => $error->getMessage(),
        'Success' => false,
    ]);
}
