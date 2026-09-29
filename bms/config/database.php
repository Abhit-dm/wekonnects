
<?php
// config/database.php

// 1. Force PHP to use Indian Standard Time
date_default_timezone_set('Asia/Kolkata');

$local_config_path = __DIR__ . '/database.local.php';
$database_config = is_file($local_config_path) ? require $local_config_path : [];
$database_config = is_array($database_config) ? $database_config : [];

$host = $database_config['host'] ?? (getenv('WEKONNECTS_DB_HOST') ?: 'localhost');
$dbname = $database_config['dbname'] ?? (getenv('WEKONNECTS_DB_NAME') ?: 'wekonnects_local');
$username = $database_config['username'] ?? (getenv('WEKONNECTS_DB_USER') ?: 'root');
$password = $database_config['password'] ?? (getenv('WEKONNECTS_DB_PASSWORD') ?: '');

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    
    // Set PDO error mode to exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // 2. Force the MySQL Database to use Indian Standard Time (+05:30)
    $pdo->exec("SET time_zone = '+05:30'");
    
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>