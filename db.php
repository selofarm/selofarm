<?php

function env_value(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    if ($value === false || $value === '') {
        return $default;
    }

    return $value;
}

function database_url_config(): array
{
    $databaseUrl = env_value('DATABASE_URL')
        ?? env_value('AIVEN_DATABASE_URL')
        ?? env_value('DB_URL');

    if ($databaseUrl === null) {
        return [];
    }

    $parts = parse_url($databaseUrl);
    if ($parts === false) {
        return [];
    }

    parse_str($parts['query'] ?? '', $query);

    return [
        'host' => $parts['host'] ?? null,
        'port' => isset($parts['port']) ? (string)$parts['port'] : null,
        'name' => isset($parts['path']) ? ltrim($parts['path'], '/') : null,
        'user' => isset($parts['user']) ? urldecode($parts['user']) : null,
        'password' => isset($parts['pass']) ? urldecode($parts['pass']) : null,
        'ssl_mode' => isset($query['ssl-mode']) ? (string)$query['ssl-mode'] : null,
    ];
}

function database_config(): array
{
    $urlConfig = database_url_config();

    return [
        'host' => env_value('DB_HOST', $urlConfig['host'] ?? '127.0.0.1'),
        'port' => env_value('DB_PORT', $urlConfig['port'] ?? '3306'),
        'name' => env_value('DB_NAME', $urlConfig['name'] ?? ''),
        'user' => env_value('DB_USER')
            ?? env_value('DB_USERNAME')
            ?? ($urlConfig['user'] ?? ''),
        'password' => env_value('DB_PASSWORD')
            ?? env_value('DB_PASS')
            ?? ($urlConfig['password'] ?? ''),
        'charset' => env_value('DB_CHARSET', 'utf8mb4'),
        'ssl_mode' => strtoupper((string)env_value('DB_SSL_MODE', $urlConfig['ssl_mode'] ?? 'REQUIRED')),
        'ssl_ca_path' => env_value('DB_SSL_CA_PATH') ?? env_value('AIVEN_CA_CERT_PATH'),
        'ssl_ca_cert' => env_value('DB_SSL_CA_CERT') ?? env_value('AIVEN_CA_CERT'),
        'ssl_verify' => filter_var(env_value('DB_SSL_VERIFY', 'false'), FILTER_VALIDATE_BOOLEAN),
    ];
}

function ensure_ssl_ca_file(?string $caPath, ?string $caCert): ?string
{
    if (is_string($caPath) && $caPath !== '' && is_file($caPath)) {
        return $caPath;
    }

    if (!is_string($caCert) || trim($caCert) === '') {
        return null;
    }

    $target = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'aiven-ca.pem';
    file_put_contents($target, str_replace("\r\n", "\n", trim($caCert) . "\n"));

    return $target;
}

function create_pdo(): PDO
{
    $config = database_config();

    $missing = [];
    if ($config['host'] === '') {
        $missing[] = 'DB_HOST';
    }
    if ($config['name'] === '') {
        $missing[] = 'DB_NAME';
    }
    if ($config['user'] === '') {
        $missing[] = 'DB_USER';
    }

    if ($missing !== []) {
        throw new RuntimeException(
            'Не заданы переменные окружения подключения к БД: ' . implode(', ', $missing)
        );
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        $config['host'],
        $config['port'],
        $config['name'],
        $config['charset']
    );

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    if ($config['ssl_mode'] !== 'DISABLED') {
        $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = $config['ssl_verify'];
        $caFile = ensure_ssl_ca_file($config['ssl_ca_path'], $config['ssl_ca_cert']);
        if ($caFile !== null) {
            $options[PDO::MYSQL_ATTR_SSL_CA] = $caFile;
        }
    }

    return new PDO($dsn, $config['user'], $config['password'], $options);
}

function db(): PDO
{
    static $pdo = null;

    if (!$pdo instanceof PDO) {
        $pdo = create_pdo();
    }

    return $pdo;
}

$conn = db();
