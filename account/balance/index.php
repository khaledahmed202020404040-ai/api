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
$amountValue = extract_first_value($input, ['amount', 'value', 'credit', 'deposit', 'bonus']);
$setBalanceValue = extract_first_value($input, ['balance', 'new_balance', 'newBalance', 'set_balance', 'setBalance']);

try {
    $database = database();

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($amountValue !== null && $amountValue !== '') || ($setBalanceValue !== null && $setBalanceValue !== ''))) {
        $targetId = null;
        $targetLogin = $login;

        if ($targetLogin !== null && $targetLogin !== '') {
            $lookup = $database->prepare('SELECT id, username, email, balance FROM users WHERE username = :login OR email = :login OR CAST(id AS TEXT) = :login LIMIT 1');
            $lookup->execute(['login' => $targetLogin]);
            $user = $lookup->fetch();
            if ($user) {
                $targetId = (int) $user['id'];
            }
        }

        if ($targetId === null && !empty($input['user_id'])) {
            $targetId = (int) $input['user_id'];
        }

        if ($targetId === null && !empty($input['userId'])) {
            $targetId = (int) $input['userId'];
        }

        if ($targetId === null && !empty($input['id'])) {
            $targetId = (int) $input['id'];
        }

        if ($targetId === null || $targetId <= 0) {
            http_response_code(400);
            echo json_encode([
                'Error' => 'user id is required',
                'Success' => false,
                'ErrorCode' => 'InvalidRequest',
                'Value' => null,
            ], JSON_UNESCAPED_SLASHES);
            exit;
        }

        $amount = (float) ($amountValue !== null && $amountValue !== '' ? $amountValue : 0.0);
        $newBalance = isset($setBalanceValue) && $setBalanceValue !== '' ? (float) $setBalanceValue : null;

        if ($newBalance !== null) {
            $update = $database->prepare('UPDATE users SET balance = :balance WHERE id = :id');
            $update->execute(['balance' => $newBalance, 'id' => $targetId]);
        } else {
            $update = $database->prepare('UPDATE users SET balance = balance + :amount WHERE id = :id');
            $update->execute(['amount' => $amount, 'id' => $targetId]);
        }

        $read = $database->prepare('SELECT id, username, email, balance FROM users WHERE id = :id LIMIT 1');
        $read->execute(['id' => $targetId]);
        $user = $read->fetch();

        echo json_encode([
            'Error' => null,
            'Success' => true,
            'ErrorCode' => null,
            'Value' => [
                'id' => (int) ($user['id'] ?? $targetId),
                'username' => $user['username'] ?? null,
                'email' => $user['email'] ?? null,
                'balance' => (float) ($user['balance'] ?? 0.0),
                'amount' => $amount,
            ],
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }

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
