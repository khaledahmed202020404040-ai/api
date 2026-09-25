<?php

declare(strict_types=1);

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
        return $pdo;
    }

    throw new RuntimeException('No database configuration found. Set DATABASE_URL or PostgreSQL env vars.');
}

