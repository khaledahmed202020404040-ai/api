<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../account/db.php';

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

$login = extract_first_value($input, ['username', 'user_name', 'userName', 'userid', 'user_id', 'userId', 'uid', 'id', 'login', 'email', 'mobile', 'phone', 'account', 'user']);
$email = extract_first_value($input, ['email', 'mail', 'email_address', 'emailAddress']);
$password = extract_first_value($input, ['password', 'pass', 'passwd', 'pwd', 'password1', 'passWord', 'secret']);
$action = strtolower((string) (extract_first_value($input, ['action', 'type', 'mode', 'operation']) ?? ''));

if ($login === null || $login === '' || $password === null || $password === '') {
    http_response_code(400);
    echo json_encode([
        'Error' => 'username and password are required',
        'Success' => false,
        'ErrorCode' => 'InvalidRequest',
        'Value' => null,
    ]);
    exit;
}

try {
    $database = database();
    $user = null;

    $query = $database->prepare('SELECT id, username, email, password_hash, balance FROM users WHERE username = :login OR email = :login LIMIT 1');
    $query->execute(['login' => $login]);
    $user = $query->fetch();

    if (!$user || !password_verify($password, (string) $user['password_hash'])) {
        http_response_code(401);
        echo json_encode([
            'Error' => 'incorrect username or password',
            'Success' => false,
            'ErrorCode' => 'InvalidCredentials',
            'Value' => null,
        ]);
        exit;
    }

    $token = hash('sha256', 'cairo-city:' . $user['id'] . ':' . $user['username']);
    $refreshToken = hash('sha256', 'cairo-city-refresh:' . $user['id'] . ':' . $user['username']);
    $profile = [
        'id' => (int) $user['id'],
        'Id' => (int) $user['id'],
        'user_id' => (int) $user['id'],
        'userId' => (int) $user['id'],
        'UserId' => (int) $user['id'],
        'username' => $user['username'],
        'Username' => $user['username'],
        'email' => $user['email'],
        'Email' => $user['email'],
        'balance' => (float) $user['balance'],
        'Balance' => (float) $user['balance'],
    ];

    $bearerToken = 'Bearer ' . $token;
    $loginData = [
        'userId' => (int) $user['id'],
        'UserId' => (int) $user['id'],
        'Id' => (int) $user['id'],
        'id' => (int) $user['id'],
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
        'user_id' => (int) $user['id'],
    ];

    echo json_encode([
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
} catch (Throwable $error) {
    error_log($error->getMessage());
    http_response_code(503);
    echo json_encode([
        'Error' => 'database unavailable',
        'Success' => false,
        'ErrorCode' => 'ServiceUnavailable',
        'Value' => null,
    ]);
}
