<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Generate random ID and password
$userId = rand(1000000000, 9999999999);
$password = substr(str_shuffle("abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789"), 0, 8);
$username = "user" . $userId;
$email = $username . "@auto.generated";

// Database configuration (inline)
function _database_parse_postgres_url(string $url): array
{
    $parsed = parse_url($url);
    if (!is_array($parsed) || empty($parsed['host'])) {
        throw new RuntimeException('Invalid DATABASE_URL format');
    }

    $username = $parsed['user'] ?? '';
    $password = $parsed['pass'] ?? '';
    $database = ltrim((string) ($parsed['path'] ?? ''), '/');
    $port = $parsed['port'] ?? 5432;
    $host = $parsed['host'];

    return [
        'dsn' => sprintf('pgsql:host=%s;port=%d;dbname=%s', $host, $port, $database),
        'username' => $username,
        'password' => $password,
    ];
}

function _ensure_users_table(PDO $pdo): void
{
    $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

    if ($driver === 'pgsql') {
        $pdo->exec('CREATE TABLE IF NOT EXISTS users (
            id SERIAL PRIMARY KEY,
            username TEXT NOT NULL UNIQUE,
            email TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            balance NUMERIC DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )');
    }
}

function database(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $databaseUrl = getenv('DATABASE_URL') ?: getenv('POSTGRES_URL') ?: getenv('POSTGRESQL_URL') ?: getenv('PGDATABASE_URL');
    if (is_string($databaseUrl) && trim($databaseUrl) !== '') {
        $config = _database_parse_postgres_url(trim($databaseUrl));
        $pdo = new PDO(
            $config['dsn'],
            $config['username'],
            $config['password'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
        _ensure_users_table($pdo);
        return $pdo;
    }

    $postgresHost = getenv('PGHOST') ?: getenv('POSTGRES_HOST');
    $postgresPort = getenv('PGPORT') ?: getenv('POSTGRES_PORT') ?: '5432';
    $postgresDb = getenv('PGDATABASE') ?: getenv('POSTGRES_DB') ?: getenv('POSTGRES_DATABASE');
    $postgresUser = getenv('PGUSER') ?: getenv('POSTGRES_USER');
    $postgresPassword = getenv('PGPASSWORD') ?: getenv('POSTGRES_PASSWORD');

    if (is_string($postgresHost) && $postgresHost !== '' && is_string($postgresDb) && $postgresDb !== '' && is_string($postgresUser) && $postgresUser !== '') {
        $pdo = new PDO(
            sprintf('pgsql:host=%s;port=%s;dbname=%s', $postgresHost, $postgresPort, $postgresDb),
            $postgresUser,
            $postgresPassword ?: '',
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
        _ensure_users_table($pdo);
        return $pdo;
    }

    throw new RuntimeException('No database configuration found.');
}

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

    // Return in the format the app expects
    echo json_encode([
        'Error' => null,
        'Success' => true,
        'ErrorCode' => null,
        'Value' => [
            'id' => $userId,
            'userId' => $userId,
            'Id' => $userId,
            'user_id' => $userId,
            'username' => $username,
            'password' => $password,
            'balance' => 1000,
        ],
    ]);
} catch (Throwable $error) {
    echo json_encode([
        'Error' => $error->getMessage(),
        'Success' => false,
        'ErrorCode' => 'DatabaseError',
        'Value' => null,
    ]);
}
