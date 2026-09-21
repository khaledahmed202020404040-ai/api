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

$input = array_merge($_GET, $_POST, $_REQUEST, $input);

$login = extract_first_value($input, ['username', 'user_name', 'userName', 'userid', 'user_id', 'userId', 'uid', 'id', 'login', 'account', 'user']);

try {
    $database = database();
    $balance = 0.0;
    $userId = 0;

    if ($login !== null && $login !== '') {
        $query = $database->prepare('SELECT id, balance FROM users WHERE username = :login OR email = :login OR CAST(id AS TEXT) = :login LIMIT 1');
        $query->execute(['login' => $login]);
        $row = $query->fetch();
        if ($row) {
            $userId = isset($row['id']) ? (int) $row['id'] : 0;
            if (isset($row['balance'])) {
                $balance = (float) $row['balance'];
            }
        }
    }

    echo json_encode([
        'Error' => null,
        'Success' => true,
        'ErrorCode' => null,
        'Value' => [
            [
                'id' => $userId,
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
