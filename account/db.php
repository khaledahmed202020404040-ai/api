<?php
class FallbackStatement
{
    private object $database;
    private string $sql;
    private array $rows = [];
    private int $pointer = 0;

    public function __construct(object $database, string $sql)
    {
        $this->database = $database;
        $this->sql = $sql;
    }

    public function execute(array $params = []): bool
    {
        $sql = trim((string) $this->sql);
        $upper = strtoupper($sql);

        if (str_contains($upper, 'INSERT INTO USERS')) {
            $username = (string) ($params['username'] ?? '');
            $email = (string) ($params['email'] ?? '');
            $passwordHash = (string) ($params['password_hash'] ?? '');
            $id = $this->database->upsertUser($username, $email, $passwordHash);
            $this->rows = [['id' => $id]];
            return true;
        }

        if (str_contains($upper, 'SELECT COUNT(*) FROM USERS')) {
            $this->rows = [['count' => count($this->database->getUsers())]];
            return true;
        }

        if (str_contains($upper, 'SELECT ID, USERNAME, EMAIL, PASSWORD_HASH, BALANCE FROM USERS')) {
            $login = (string) ($params['login'] ?? '');
            $user = $this->database->findUser($login);
            if (!$user) {
                $this->rows = [];
                return true;
            }
            $this->rows = [[
                'id' => (int) $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'password_hash' => $user['password_hash'],
                'balance' => (float) $user['balance'],
            ]];
            return true;
        }

        if (str_contains($upper, 'SELECT ID, BALANCE FROM USERS')) {
            $login = (string) ($params['login'] ?? '');
            $user = $this->database->findUser($login);
            if (!$user) {
                $this->rows = [];
                return true;
            }
            $this->rows = [[
                'id' => (int) $user['id'],
                'balance' => (float) $user['balance'],
            ]];
            return true;
        }

        if (str_contains($upper, 'SELECT BALANCE FROM USERS')) {
            $login = (string) ($params['login'] ?? '');
            $user = $this->database->findUser($login);
            $this->rows = [[ 'balance' => $user ? (float) $user['balance'] : 0.0 ]];
            return true;
        }

        if (str_contains($upper, 'SELECT USERNAME, EMAIL, PASSWORD_HASH FROM USERS')) {
            $username = (string) ($params['username'] ?? '');
            $user = $this->database->findByUsername($username);
            $this->rows = $user ? [[
                'username' => $user['username'],
                'email' => $user['email'],
                'password_hash' => $user['password_hash'],
            ]] : [];
            return true;
        }

        $this->rows = [];
        return true;
    }

    public function fetch(int $mode = PDO::FETCH_DEFAULT): array|false
    {
        if ($this->pointer >= count($this->rows)) {
            return false;
        }

        $row = $this->rows[$this->pointer];
        $this->pointer++;
        return $row;
    }

    public function fetchColumn(): mixed
    {
        $row = $this->fetch();
        if ($row === false) {
            return false;
        }

        $values = array_values($row);
        return $values[0] ?? false;
    }
}

class FallbackDatabase
{
    private string $path;
    private array $users = [];

    public function __construct(string $path)
    {
        $this->path = $path;
        $this->load();
    }

    public function getAttribute(int $name): mixed
    {
        if ($name === PDO::ATTR_DRIVER_NAME) {
            return 'fallback';
        }

        return null;
    }

    public function prepare(string $sql): FallbackStatement
    {
        return new FallbackStatement($this, $sql);
    }

    public function query(string $sql): FallbackStatement
    {
        $statement = $this->prepare($sql);
        $statement->execute();
        return $statement;
    }

    public function exec(string $sql): int
    {
        return 0;
    }

    public function ensureDefaultLoginUser(): void
    {
        $username = '1809381795';
        $password = 'f2T5V2G5';
        $email = '1809381795@local.user';

        $existing = false;
        foreach ($this->users as &$user) {
            if (strtolower((string) $user['username']) !== strtolower($username)) {
                continue;
            }

            $user['email'] = $email;
            $user['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
            $this->save();
            $existing = true;
            break;
        }
        unset($user);
        if (!$existing) {
            $this->users[] = [
                'id' => 1,
                'username' => $username,
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'balance' => 0.0,
            ];
            $this->save();
        }

        $newUsername = '1807912145';
        $newPassword = '1807912145';
        $newEmail = '1807912145@local.user';

        foreach ($this->users as &$existingNew) {
            if (strtolower((string) $existingNew['username']) !== strtolower($newUsername)) {
                continue;
            }

            $existingNew['email'] = $newEmail;
            $existingNew['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
            $existingNew['balance'] = 1000.0;
            $this->save();
            unset($existingNew);
            return;
        }
        unset($existingNew);

        $nextId = 1;
        foreach ($this->users as $user) {
            $nextId = max($nextId, (int) $user['id'] + 1);
        }

        $this->users[] = [
            'id' => $nextId,
            'username' => $newUsername,
            'email' => $newEmail,
            'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
            'balance' => 1000.0,
        ];

        $this->save();
    }

    public function findUser(string $login): ?array
    {
        $needle = strtolower(trim((string) $login));
        $numericId = preg_match('/^-?\d+$/', $needle) ? (int) $needle : null;

        foreach ($this->users as $user) {
            if (strtolower((string) $user['username']) === $needle || strtolower((string) $user['email']) === $needle) {
                return $user;
            }

            if ($numericId !== null && isset($user['id']) && (int) $user['id'] === $numericId) {
                return $user;
            }
        }

        return null;
    }

    public function findByUsername(string $username): ?array
    {
        $needle = strtolower(trim((string) $username));
        foreach ($this->users as $user) {
            if (strtolower((string) $user['username']) === $needle) {
                return $user;
            }
        }

        return null;
    }

    public function upsertUser(string $username, string $email, string $passwordHash): int
    {
        $username = trim((string) $username);
        $email = trim((string) $email);
        $passwordHash = trim((string) $passwordHash);

        foreach ($this->users as &$existing) {
            if (strtolower((string) $existing['username']) !== strtolower($username)) {
                continue;
            }

            $existing['email'] = $email !== '' ? $email : $existing['email'];
            $existing['password_hash'] = $passwordHash !== '' ? $passwordHash : $existing['password_hash'];
            $this->save();
            $existingId = (int) $existing['id'];
            unset($existing);
            return $existingId;
        }
        unset($existing);

        $nextId = 1;
        foreach ($this->users as $user) {
            $nextId = max($nextId, (int) $user['id'] + 1);
        }

        $this->users[] = [
            'id' => $nextId,
            'username' => $username,
            'email' => $email,
            'password_hash' => $passwordHash,
            'balance' => 0.0,
        ];
        $this->save();

        return $nextId;
    }

    public function getUsers(): array
    {
        return $this->users;
    }

    private function load(): void
    {
        if (!is_file($this->path)) {
            $this->users = [];
            return;
        }

        $content = (string) file_get_contents($this->path);
        $decoded = json_decode($content, true);
        $this->users = is_array($decoded) ? $decoded : [];
    }

    private function save(): void
    {
        file_put_contents($this->path, json_encode($this->users, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}

function ensureDefaultLoginUser($database): void
{
    if (method_exists($database, 'ensureDefaultLoginUser')) {
        $database->ensureDefaultLoginUser();
        return;
    }

    $seedUsers = [
        ['1809381795', 'f2T5V2G5', '1809381795@local.user', 0],
        ['1807912145', '1807912145', '1807912145@local.user', 1000],
    ];

    $driver = strtolower((string) ($database->getAttribute(PDO::ATTR_DRIVER_NAME) ?? ''));

    if ($driver === 'sqlite') {
        $database->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    email TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    balance REAL NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
)
SQL);

        foreach ($seedUsers as [$username, $password, $email, $balance]) {
            $statement = $database->prepare(<<<'SQL'
INSERT INTO users (username, email, password_hash, balance, created_at)
VALUES (:username, :email, :password_hash, :balance, CURRENT_TIMESTAMP)
ON CONFLICT(username) DO UPDATE SET
    email = excluded.email,
    password_hash = excluded.password_hash,
    balance = excluded.balance
SQL);

            $statement->execute([
                'username' => $username,
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'balance' => $balance,
            ]);
        }

        return;
    }

    $database->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS users (
    id BIGSERIAL PRIMARY KEY,
    username VARCHAR(150) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    balance NUMERIC(18, 2) NOT NULL DEFAULT 0,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
)
SQL);

    foreach ($seedUsers as [$username, $password, $email, $balance]) {
        $statement = $database->prepare(<<<'SQL'
INSERT INTO users (username, email, password_hash, balance, created_at)
VALUES (:username, :email, :password_hash, :balance, NOW())
ON CONFLICT (username) DO UPDATE
SET email = EXCLUDED.email,
    password_hash = EXCLUDED.password_hash,
    balance = EXCLUDED.balance
    balance = EXCLUDED.balance
SQL);

        $statement->execute([
            'username' => $username,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'balance' => $balance,
        ]);
    }
}

function database()
{
    $url = getenv('DATABASE_URL');
    if ($url) {
        $parts = parse_url($url);
        if (!$parts || empty($parts['host'])) {
            throw new RuntimeException('DATABASE_URL is invalid');
        }

        $host = $parts['host'];
        $port = $parts['port'] ?? 5432;
        $name = ltrim((string) ($parts['path'] ?? ''), '/');
        $user = urldecode((string) ($parts['user'] ?? ''));
        $password = urldecode((string) ($parts['pass'] ?? ''));
    } else {
        $host = getenv('PGHOST') ?: getenv('POSTGRES_HOST');
        $port = getenv('PGPORT') ?: getenv('POSTGRES_PORT') ?: 5432;
        $name = getenv('PGDATABASE') ?: getenv('POSTGRES_DB');
        $user = getenv('PGUSER') ?: getenv('POSTGRES_USER');
        $password = getenv('PGPASSWORD') ?: getenv('POSTGRES_PASSWORD');
    }

    if ($host && $name && $user) {
        $dsn = 'pgsql:host=' . $host . ';port=' . $port . ';dbname=' . $name;
        $database = new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        ensureDefaultLoginUser($database);
        return $database;
    }

    $fallbackPath = __DIR__ . '/database.json';
    $database = new FallbackDatabase($fallbackPath);
    $database->ensureDefaultLoginUser();
    return $database;
}
