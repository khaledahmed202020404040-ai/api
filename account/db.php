<?php

function ensureDefaultLoginUser(PDO $database): void
{
    $username = '1809381795';
    $password = 'f2T5V2G5';
    $email = '1809381795@local.user';

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

    $statement = $database->prepare(<<<'SQL'
INSERT INTO users (username, email, password_hash, balance, created_at)
VALUES (:username, :email, :password_hash, 0, NOW())
ON CONFLICT (username) DO UPDATE
SET email = EXCLUDED.email,
    password_hash = EXCLUDED.password_hash
SQL);
    $statement->execute([
        'username' => $username,
        'email' => $email,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    ]);
}

function database(): PDO
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

        if (!$host || !$name || !$user) {
            throw new RuntimeException('PostgreSQL connection variables are not configured');
        }
    }

    $dsn = 'pgsql:host=' . $host . ';port=' . $port . ';dbname=' . $name;

    $database = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    ensureDefaultLoginUser($database);

    return $database;
}
