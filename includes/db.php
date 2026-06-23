<?php
$config = ['host' => 'localhost', 'user' => 'root', 'pass' => '', 'dbname' => 'gw08'];
$localConfigPath = __DIR__ . '/db.local.php';
if (is_file($localConfigPath)) {
    $localConfig = require $localConfigPath;
    if (is_array($localConfig)) {
        $config = array_merge($config, $localConfig);
    }
}

$host = $config['host'];
$user = $config['user'];
$pass = $config['pass'];
$dbname = $config['dbname'];

$conn = null;
try {
    $conn = new mysqli($host, $user, $pass, $dbname);
    $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $e) {
    http_response_code(503);
    error_log('SonicSync database connection failed: ' . $e->getMessage());
    exit('SonicSync database is unavailable. Start MySQL in XAMPP, wait a few seconds, then refresh this page.');
}
?>
