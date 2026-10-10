<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../db.php';

// AES Encryption Configuration
$AES_KEY = "rsSeRIGfYsedC5YopKeIoA=="; // المفتاح الجديد - نفس الموجود في d.smali
$AES_KEY_DECODED = base64_decode($AES_KEY);

function encrypt_aes($data, $key) {
    $iv = str_repeat("\0", 16); // ECB mode doesn't use IV
    $encrypted = openssl_encrypt($data, 'AES-192-ECB', $key, OPENSSL_RAW_DATA);
    return base64_encode($encrypted);
}

function read_request_input(): array
{
    $rawBody = trim((string) file_get_contents('php://input'));
    $sources = [];

    if ($rawBody !== '') {
        $decoded = json_decode($rawBody, true);
        if (is_array($decoded) && count($decoded) > 0) {
            $sources[] = $decoded;
        }

        parse_str($rawBody, $parsed);
        if (is_array($parsed) && count($parsed) > 0) {
            $sources[] = $parsed;
        }

        $pairs = [];
        $bodyCandidate = $rawBody;
        if (preg_match('/^\s*\{.*\}\s*$/s', $bodyCandidate)) {
            $bodyCandidate = trim($bodyCandidate, "{} ");
        }

        if (preg_match_all('/([A-Za-z0-9_\-]+)\s*[:=]\s*([^,}\s]+(?:\s*[^,}\s]+)*)/i', $bodyCandidate, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $key = trim((string) ($match[1] ?? ''));
                $value = trim((string) ($match[2] ?? ''));
                $value = rtrim($value, "}\]'");
                if ($key !== '' && $value !== '') {
                    $pairs[$key] = $value;
                }
            }
        }

        if (count($pairs) > 0) {
            $sources[] = $pairs;
        }
    }

    foreach ([$_POST, $_GET, $_REQUEST] as $source) {
        if (is_array($source) && count($source) > 0) {
            $sources[] = $source;
        }
    }

    $merged = [];
    foreach ($sources as $source) {
        if (is_array($source)) {
            $merged = array_replace_recursive($merged, $source);
        }
    }

    return $merged;
}

function first_value(array $source, array $keys): ?string
{
    $stack = [$source];

    while (!empty($stack)) {
        $current = array_pop($stack);
        if (!is_array($current)) {
            continue;
        }

        foreach ($current as $key => $value) {
            $normalized = strtolower((string) $key);
            foreach ($keys as $candidate) {
                $candidateKey = strtolower((string) $candidate);
                if ($normalized === $candidateKey || str_contains($normalized, $candidateKey) || str_contains($candidateKey, $normalized)) {
                    if (is_array($value)) {
                        $nested = first_value($value, $keys);
                        if ($nested !== null) {
                            return trim((string) $nested);
                        }
                        continue 2;
                    }

                    if ($value === null) {
                        continue 2;
                    }

                    return trim((string) $value);
                }
            }

            if (is_array($value)) {
                $stack[] = $value;
            }
        }
    }

    return null;
}

try {
    $input = read_request_input();
    if (!is_array($input)) {
        $input = [];
    }
    $input = array_replace_recursive($_GET, $_POST, $_REQUEST, $input);

    $username = first_value($input, ['username', 'user_name', 'userName', 'login', 'user', 'account', 'phone', 'mobile', 'name']);
    $email = first_value($input, ['email', 'mail', 'email_address', 'emailAddress']);
    $password = first_value($input, ['password', 'pass', 'passwd', 'pwd', 'secret']);
    $username = $username !== null ? trim($username) : '';
    $email = $email !== null ? trim($email) : '';
    $password = $password !== null ? (string) $password : '';
    if ($email === '' && $username !== '') {
        $email = str_contains($username, '@') ? $username : ($username . '@cairo.local');
    }

    if ($username === '' || $email === '' || $password === '') {
        http_response_code(400);
        $response = json_encode([
            'Error' => 'username, email and password are required',
            'Success' => false,
            'ErrorCode' => 'InvalidRequest',
            'Value' => null,
        ], JSON_UNESCAPED_SLASHES);
        echo encrypt_aes($response, $AES_KEY_DECODED);
        exit;
    }

    $database = database();

    $exists = $database->prepare('SELECT id FROM users WHERE username = :username OR email = :email LIMIT 1');
    $exists->execute([
        'username' => $username,
        'email' => $email,
    ]);

    if ($exists->fetch()) {
        http_response_code(409);
        $response = json_encode([
            'Error' => 'user already exists',
            'Success' => false,
            'ErrorCode' => 'DuplicateUser',
            'Value' => null,
        ], JSON_UNESCAPED_SLASHES);
        echo encrypt_aes($response, $AES_KEY_DECODED);
        exit;
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $insert = $database->prepare('INSERT INTO users (username, email, password_hash, balance) VALUES (:username, :email, :password_hash, 0)');
    $insert->execute([
        'username' => $username,
        'email' => $email,
        'password_hash' => $passwordHash,
    ]);

    $userId = (int) $database->lastInsertId();
    $profile = [
        'id' => $userId,
        'Id' => $userId,
        'user_id' => $userId,
        'userId' => $userId,
        'UserId' => $userId,
        'username' => $username,
        'Username' => $username,
        'email' => $email,
        'Email' => $email,
        'balance' => 0.0,
        'Balance' => 0.0,
    ];

    $token = hash('sha256', 'cairo-city:' . $userId . ':' . $username);
    $refreshToken = hash('sha256', 'cairo-city-refresh:' . $userId . ':' . $username);
    $bearerToken = 'Bearer ' . $token;

    $loginData = [
        'userId' => $userId,
        'UserId' => $userId,
        'Id' => $userId,
        'id' => $userId,
        'accessToken' => $token,
        'AccessToken' => $token,
        'refreshToken' => $refreshToken,
        'RefreshToken' => $refreshToken,
        'expiresIn' => 86400,
        'ExpiresIn' => 86400,
        'token' => $token,
        'Token' => $token,
        'tokenType' => 'Bearer',
        'TokenType' => 'Bearer',
        'Authorization' => $bearerToken,
        'authorization' => $bearerToken,
        'user' => $profile,
        'User' => $profile,
        'userData' => $profile,
        'UserData' => $profile,
        'user_id' => $userId,
    ];

    $response = json_encode([
        'Error' => null,
        'Success' => true,
        'ErrorCode' => null,
        'Value' => [
            'RefreshToken' => $refreshToken,
            'refreshToken' => $refreshToken,
            'RefreshExpiry' => 86400,
            'Token' => $token,
            'token' => $token,
            'TokenExpiry' => 86400,
            'UserData' => $profile,
            'userData' => $profile,
            'User' => $profile,
            'user' => $profile,
            'Question' => null,
            'Authorization' => $bearerToken,
            'authorization' => $bearerToken,
            'tokenType' => 'Bearer',
            'token_type' => 'Bearer',
            'accessToken' => $token,
            'AccessToken' => $token,
            'refreshToken' => $refreshToken,
            'expiresIn' => 86400,
            'ExpiresIn' => 86400,
            'data' => $loginData,
        ],
    ], JSON_UNESCAPED_SLASHES);

    echo encrypt_aes($response, $AES_KEY_DECODED);
} catch (Throwable $error) {
    error_log($error->getMessage());
    http_response_code(500);
    $response = json_encode([
        'Error' => 'registration failed',
        'Success' => false,
        'ErrorCode' => 'ServiceUnavailable',
        'Value' => null,
    ], JSON_UNESCAPED_SLASHES);
    echo encrypt_aes($response, $AES_KEY_DECODED);
}
