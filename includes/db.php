<?php
$defaultConfig = [
    'APP_DB' => ['host' => 'localhost', 'user' => 'root', 'password' => '', 'database' => 'gw08', 'port' => 3306],
    'VSTU_DB' => ['host' => 'localhost', 'user' => 'root', 'password' => '', 'database' => 'mmdb2026', 'port' => 3306],
];
$config = $defaultConfig;
$localConfigPath = __DIR__ . '/db.local.php';
if (is_file($localConfigPath)) {
    $localConfig = require $localConfigPath;
    if (is_array($localConfig)) {
        if (isset($localConfig['APP_DB'])) {
            $config = array_replace_recursive($config, $localConfig);
        } else {
            $config['APP_DB'] = normalizeDbConfig($localConfig, $defaultConfig['APP_DB']);
        }
    }
}

function normalizeDbConfig(array $settings, array $defaults): array
{
    return [
        'host' => $settings['host'] ?? $defaults['host'],
        'user' => $settings['user'] ?? $defaults['user'],
        'password' => $settings['password'] ?? $settings['pass'] ?? $defaults['password'],
        'database' => $settings['database'] ?? $settings['dbname'] ?? $defaults['database'],
        'port' => (int) ($settings['port'] ?? $defaults['port']),
    ];
}

function connectDatabase(array $settings, string $label): mysqli
{
    $db = normalizeDbConfig($settings, ['host' => 'localhost', 'user' => 'root', 'password' => '', 'database' => '', 'port' => 3306]);
    $connection = new mysqli($db['host'], $db['user'], $db['password'], $db['database'], $db['port']);
    $connection->set_charset('utf8mb4');
    return $connection;
}

$conn = null;
try {
    $conn = connectDatabase($config['APP_DB'], 'APP_DB');
} catch (mysqli_sql_exception $e) {
    http_response_code(503);
    error_log('SonicSync database connection failed: ' . $e->getMessage());
    exit('SonicSync database is unavailable. Start MySQL in XAMPP, wait a few seconds, then refresh this page.');
}

function getVstuConnection(): ?mysqli
{
    static $vstuConn = null;
    global $config;

    if ($vstuConn instanceof mysqli) {
        return $vstuConn;
    }

    try {
        $vstuConn = connectDatabase($config['VSTU_DB'], 'VSTU_DB');
        return $vstuConn;
    } catch (mysqli_sql_exception $e) {
        error_log('SonicSync VSTU database connection failed: ' . $e->getMessage());
        return null;
    }
}
?>
