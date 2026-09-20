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
            foreach ($keys as $candidate) {
                if ($normalizedKey === strtolower((string) $candidate)) {
                    if (is_array($value)) {
                        $nested = extract_first_value($value, $keys);
                        if ($nested !== null) {
                            return trim((string) $nested);
                        }
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

$input = array_merge($_GET, $_POST, $_REQUEST, $input);

$login = extract_first_value($input, ['username', 'user_name', 'userName', 'userid', 'user_id', 'userId', 'uid', 'id', 'login', 'account', 'user']);

try {
    $database = database();
    $balance = 0.0;

    if ($login !== null && $login !== '') {
        $query = $database->prepare('SELECT balance FROM users WHERE username = :login OR email = :login LIMIT 1');
        $query->execute(['login' => $login]);
        $row = $query->fetch();
        if ($row && isset($row['balance'])) {
            $balance = (float) $row['balance'];
        }
    }

    echo json_encode([
        'Error' => null,
        'Success' => true,
        'ErrorCode' => null,
        'Value' => [
            [
                'id' => 0,
                'balance' => $balance,
                'isActive' => true,
                'Balance' => $balance,
            ],
        ],
    ], JSON_UNESCAPED_SLASHES);
} catch (Throwable $error) {
    http_response_code(503);
    echo json_encode([
        'Error' => 'balance unavailable',
        'Success' => false,
        'ErrorCode' => 'ServiceUnavailable',
        'Value' => []
    ], JSON_UNESCAPED_SLASHES);
}
exit;
