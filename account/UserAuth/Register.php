<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../db.php';

function extract_first_value(array $source, array $keys): ?string
{
    $stack = [$source];

    while (!empty($stack)) {
        $current = array_pop($stack);
        if (!is_array($current)) {
            continue;
        }

        foreach ($current as $key => $value) {
            $normalizedKey = strtolower((string) $key);
            $matchedKey = null;

            foreach ($keys as $candidate) {
                if ($normalizedKey === strtolower((string) $candidate)) {
                    $matchedKey = $candidate;
                    break;
                }
            }

            if ($matchedKey !== null) {
                if (is_array($value)) {
                    $nested = extract_first_value($value, $keys);
                    if ($nested !== null) {
                        return trim((string) $nested);
                    }
                    continue;
                }

                return trim((string) $value);
            }

            if (is_array($value)) {
                $stack[] = $value;
            }
        }
    }

    return null;
}

$rawBody = trim((string) file_get_contents('php://input'));
$input = [];
if ($rawBody !== '') {
    $decoded = json_decode($rawBody, true);
    if (is_array($decoded)) {
        $input = $decoded;
    } else {
        parse_str($rawBody, $parsed);
        if (is_array($parsed)) {
            $input = $parsed;
        }
    }
}
if (!is_array($input) || count($input) === 0) {
    $input = $_POST;
}
if (!is_array($input)) {
    $input = [];
}
$input = array_merge($_GET, $_POST, $_REQUEST, $input);

$username = extract_first_value($input, ['username', 'user_name', 'userName', 'newUsername', 'new_username', 'userid', 'user_id', 'userId', 'uid', 'id', 'login', 'account', 'user']);
$email = extract_first_value($input, ['email', 'mail', 'email_address', 'emailAddress']);
if ($email === null || $email === '') {
    $email = $username ?? '';
}
$password = extract_first_value($input, ['password', 'pass', 'passwd', 'pwd', 'password1', 'passWord', 'secret']);
$registrationType = strtolower((string) (extract_first_value($input, ['registrationType', 'registration_type', 'regType', 'reg_type', 'af_registration_method', 'type', 'mode']) ?? ''));
$oneClickRegistration = in_array($registrationType, ['one_click', 'oneclick', 'registration_one_click'], true);

if (!$oneClickRegistration && ($username === null || $username === '' || $email === '' || $password === null || $password === '')) {
    http_response_code(400);
    echo json_encode([
        'Error' => 'username, email and password are required',
        'Success' => false,
        'ErrorCode' => 'InvalidRequest',
        'Value' => [
            'User' => null,
            'Form' => ['Errors' => []],
        ],
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $database = database();

    if ($oneClickRegistration && ($username === null || $username === '' || $password === null || $password === '')) {
        $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
        if ($password === null || $password === '') {
            $password = '';
            for ($index = 0; $index < 8; $index++) {
                $password .= $characters[random_int(0, strlen($characters) - 1)];
            }
        }

        if ($username === null || $username === '') {
            do {
                $username = (string) random_int(1800000000, 1899999999);
                $exists = $database->prepare('SELECT 1 FROM users WHERE username = :username LIMIT 1');
                $exists->execute(['username' => $username]);
            } while ($exists->fetchColumn());
        }
    }

    if ($oneClickRegistration) {
        $email = $username . '@local.user';
    }

    $query = $database->prepare('INSERT INTO users (username, email, password_hash, balance, created_at) VALUES (:username, :email, :password_hash, 0, NOW()) RETURNING id');
    $query->execute([
        'username' => $username,
        'email' => $email,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    ]);
    $userId = (int) $query->fetchColumn();
    $token = hash('sha256', 'cairo-city:' . $userId . ':' . $username);
    $refreshToken = hash('sha256', 'cairo-city-refresh:' . $userId . ':' . $username);

    $profile = [
        'id' => $userId,
        'user_id' => $userId,
        'userId' => $userId,
        'username' => $username,
        'email' => $email,
        'balance' => 0,
    ];

    $userPayload = [
        'UserId' => $userId,
        'userId' => $userId,
        'Id' => $userId,
        'id' => $userId,
        'Username' => $username,
        'username' => $username,
        'Password' => $password,
        'password' => $password,
        'Login' => $username,
        'login' => $username,
        'Email' => $email,
        'email' => $email,
        'Message' => 'account created',
    ];

    $registrationData = [
        'userId' => $userId,
        'UserId' => $userId,
        'id' => $userId,
        'Id' => $userId,
        'username' => $username,
        'login' => $username,
        'password' => $password,
        'accessToken' => $token,
        'refreshToken' => $refreshToken,
        'expiresIn' => 86400,
        'token' => $token,
        'Authorization' => 'Bearer ' . $token,
        'authorization' => 'Bearer ' . $token,
        'user' => $profile,
        'UserData' => $profile,
        'userData' => $profile,
        'User' => $userPayload,
    ];

    echo json_encode([
        'Error' => null,
        'Success' => true,
        'ErrorCode' => null,
        'Value' => [
            'User' => $profile,
            'UserId' => $username,
            'userId' => $username,
            'Login' => $username,
            'login' => $username,
            'Username' => $username,
            'username' => $username,
            'RefreshToken' => $refreshToken,
            'RefreshExpiry' => 86400,
            'Token' => $token,
            'TokenExpiry' => 86400,
            'UserData' => $profile,
            'Question' => null,
            'Authorization' => 'Bearer ' . $token,
            'authorization' => 'Bearer ' . $token,
            'tokenType' => 'Bearer',
            'token_type' => 'Bearer',
            'accessToken' => $token,
            'refreshToken' => $refreshToken,
            'expiresIn' => 86400,
            'data' => $registrationData,
        ],
        'UserId' => $username,
        'Login' => $username,
        'Username' => $username,
    ], JSON_UNESCAPED_SLASHES);
} catch (PDOException $error) {
    http_response_code($error->getCode() === '23505' ? 409 : 503);
    echo json_encode([
        'Error' => 'account could not be created',
        'Success' => false,
        'ErrorCode' => $error->getCode() === '23505' ? 'AlreadyExists' : 'ServiceUnavailable',
        'Value' => [
            'User' => null,
            'Form' => ['Errors' => []],
        ],
    ], JSON_UNESCAPED_SLASHES);
}
