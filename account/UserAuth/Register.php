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

function read_request_input(): array
{
    $rawBody = trim((string) file_get_contents('php://input'));
    if ($rawBody === '') {
        return [];
    }

    $decoded = json_decode($rawBody, true);
    if (is_array($decoded) && count($decoded) > 0) {
        return $decoded;
    }

    parse_str($rawBody, $parsed);
    if (is_array($parsed) && count($parsed) > 0) {
        return $parsed;
    }

    $pairs = [];
    if (preg_match_all('/(?:"|\')?([A-Za-z0-9_\-]+)(?:"|\')?\s*[:=]\s*(?:"|\')?([^"\'&,}\s]+)(?:"|\')?(?:\s*(?:,|}|$))/i', $rawBody, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $key = trim((string) ($match[1] ?? ''));
            $value = trim((string) ($match[2] ?? ''));
            if ($key !== '' && $value !== '') {
                $pairs[$key] = $value;
            }
        }
    }

    return $pairs;
}

$input = read_request_input();
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

    $registrationData = [
        'userId' => $userId,
        'UserId' => $userId,
        'id' => $userId,
        'Id' => $userId,
        'username' => $username,
        'Username' => $username,
        'login' => $username,
        'Login' => $username,
        'email' => $email,
        'Email' => $email,
        'accessToken' => $token,
        'AccessToken' => $token,
        'refreshToken' => $refreshToken,
        'RefreshToken' => $refreshToken,
        'expiresIn' => 86400,
        'ExpiresIn' => 86400,
        'token' => $token,
        'Token' => $token,
        'Authorization' => 'Bearer ' . $token,
        'authorization' => 'Bearer ' . $token,
        'user' => $profile,
        'User' => $profile,
        'UserData' => $profile,
        'userData' => $profile,
    ];

    echo json_encode([
        'Error' => null,
        'Success' => true,
        'ErrorCode' => null,
        'Value' => [
            'User' => $profile,
            'user' => $profile,
            'UserData' => $profile,
            'userData' => $profile,
            'Authorization' => 'Bearer ' . $token,
            'authorization' => 'Bearer ' . $token,
            'Token' => $token,
            'token' => $token,
            'RefreshToken' => $refreshToken,
            'refreshToken' => $refreshToken,
            'TokenExpiry' => 86400,
            'RefreshExpiry' => 86400,
            'Balance' => 0,
            'balance' => 0,
            'Form' => [
                'Errors' => []
            ],
            'data' => $registrationData,
        ],
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
