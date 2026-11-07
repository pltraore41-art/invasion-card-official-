<?php
declare(strict_types=1);

function db(): PDO
{
    static $connection;
    if ($connection instanceof PDO) {
        return $connection;
    }

    $dsn = env_value('DATABASE_DSN');
    if ($dsn === null) {
        $databaseUrl = env_value('DATABASE_URL', 'sqlite:///invasion.db');
        if (str_starts_with($databaseUrl, 'sqlite:///')) {
            $dsn = 'sqlite:' . ROOT_PATH . '/' . substr($databaseUrl, strlen('sqlite:///'));
        } elseif (str_starts_with($databaseUrl, 'sqlite:')) {
            $dsn = $databaseUrl;
        } elseif (preg_match('~^postgres(?:ql)?://~i', $databaseUrl)) {
            $parts = parse_url($databaseUrl);
            if ($parts === false || empty($parts['host']) || empty($parts['path'])) {
                throw new RuntimeException('La variable DATABASE_URL PostgreSQL est invalide.');
            }
            $dsn = 'pgsql:host=' . $parts['host'];
            if (isset($parts['port'])) {
                $dsn .= ';port=' . (int) $parts['port'];
            }
            $dsn .= ';dbname=' . rawurldecode(ltrim($parts['path'], '/'));
            $query = [];
            parse_str($parts['query'] ?? '', $query);
            foreach (['sslmode', 'sslrootcert', 'sslcert', 'sslkey', 'connect_timeout', 'application_name'] as $option) {
                if (isset($query[$option]) && is_string($query[$option])) {
                    $dsn .= ';' . $option . '=' . $query[$option];
                }
            }
            $databaseUser = isset($parts['user']) ? rawurldecode($parts['user']) : env_value('DATABASE_USER');
            $databasePassword = isset($parts['pass']) ? rawurldecode($parts['pass']) : env_value('DATABASE_PASSWORD');
        } else {
            throw new RuntimeException(
                'Configurez DATABASE_DSN pour SQLite, MySQL ou PostgreSQL ; le DATABASE_URL actuel n’est pas pris en charge.'
            );
        }
    }

    $connection = new PDO(
        $dsn,
        $databaseUser ?? env_value('DATABASE_USER'),
        $databasePassword ?? env_value('DATABASE_PASSWORD'),
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    initialize_database($connection);
    return $connection;
}

function initialize_database(PDO $connection): void
{
    $driver = $connection->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
        $connection->exec(
            'CREATE TABLE IF NOT EXISTS event_registrations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                reference VARCHAR(30) NOT NULL UNIQUE,
                last_name VARCHAR(120) NOT NULL,
                first_name VARCHAR(120) NOT NULL,
                age INTEGER,
                city VARCHAR(120),
                attendance VARCHAR(40) NOT NULL,
                companions_count INTEGER DEFAULT 0,
                beverages VARCHAR(40) NOT NULL,
                cardigan_knowledge VARCHAR(50) NOT NULL,
                intention VARCHAR(60) NOT NULL,
                desired_size VARCHAR(30),
                preferred_color VARCHAR(120),
                budget VARCHAR(50),
                comment TEXT,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL
            )'
        );
        return;
    }

    if ($driver === 'mysql') {
        $connection->exec(
            'CREATE TABLE IF NOT EXISTS event_registrations (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                reference VARCHAR(30) NOT NULL UNIQUE,
                last_name VARCHAR(120) NOT NULL,
                first_name VARCHAR(120) NOT NULL,
                age INTEGER NULL,
                city VARCHAR(120) NULL,
                attendance VARCHAR(40) NOT NULL,
                companions_count INTEGER DEFAULT 0,
                beverages VARCHAR(40) NOT NULL,
                cardigan_knowledge VARCHAR(50) NOT NULL,
                intention VARCHAR(60) NOT NULL,
                desired_size VARCHAR(30) NULL,
                preferred_color VARCHAR(120) NULL,
                budget VARCHAR(50) NULL,
                comment TEXT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                INDEX event_registrations_created_at_idx (created_at)
            ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
        );
        return;
    }

    if ($driver === 'pgsql') {
        $connection->exec(
            'CREATE TABLE IF NOT EXISTS event_registrations (
                id SERIAL PRIMARY KEY,
                reference VARCHAR(30) NOT NULL UNIQUE,
                last_name VARCHAR(120) NOT NULL,
                first_name VARCHAR(120) NOT NULL,
                age INTEGER NULL,
                city VARCHAR(120) NULL,
                attendance VARCHAR(40) NOT NULL,
                companions_count INTEGER DEFAULT 0,
                beverages VARCHAR(40) NOT NULL,
                cardigan_knowledge VARCHAR(50) NOT NULL,
                intention VARCHAR(60) NOT NULL,
                desired_size VARCHAR(30) NULL,
                preferred_color VARCHAR(120) NULL,
                budget VARCHAR(50) NULL,
                comment TEXT NULL,
                created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP WITHOUT TIME ZONE NOT NULL
            )'
        );
        $connection->exec(
            'CREATE INDEX IF NOT EXISTS event_registrations_created_at_idx ON event_registrations (created_at)'
        );
        return;
    }

    throw new RuntimeException('Pilote de base de données non pris en charge : ' . $driver);
}
