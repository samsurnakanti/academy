<?php
declare(strict_types=1);

const DB_HOST_DEFAULT = 'localhost';
const DB_PORT_DEFAULT = 3307;
const DB_NAME_DEFAULT = 'elldy_academy';
const DB_USER_DEFAULT = 'root';
const DB_PASS_DEFAULT = '';

function db_config_value(string $key, string|int $default): string|int
{
    $value = getenv($key);

    if ($value === false || $value === '') {
        return $default;
    }

    return $key === 'DB_PORT' ? (int) $value : $value;
}

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = (string) db_config_value('DB_HOST', DB_HOST_DEFAULT);
    $port = (int) db_config_value('DB_PORT', DB_PORT_DEFAULT);
    $name = (string) db_config_value('DB_NAME', DB_NAME_DEFAULT);
    $user = (string) db_config_value('DB_USER', DB_USER_DEFAULT);
    $pass = (string) db_config_value('DB_PASS', DB_PASS_DEFAULT);

    $dsn = 'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $name . ';charset=utf8mb4';
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}
