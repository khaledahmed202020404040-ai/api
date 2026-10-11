<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Database configuration (inline to avoid path issues)
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

    if ($driver === 'sqlite') {
        $pdo->exec('CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            email TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            balance NUMERIC DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )');
        return;
    }

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

    $runningOnRailway = getenv('RAILWAY_ENVIRONMENT') !== false || getenv('RAILWAY_PROJECT_ID') !== false;
    if ($runningOnRailway) {
        throw new RuntimeException('PostgreSQL is required on Railway. Set DATABASE_URL or PostgreSQL environment variables.');
    }

    if (in_array('sqlite', PDO::getAvailableDrivers(), true)) {
        $sqlitePath = __DIR__ . '/database.sqlite';
        $pdo = new PDO('sqlite:' . $sqlitePath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        _ensure_users_table($pdo);
        return $pdo;
    }

    throw new RuntimeException('No database configuration found. Set DATABASE_URL or PostgreSQL env vars.');
}

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
