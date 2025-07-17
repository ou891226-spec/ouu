<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$hostname = 'smartfun-senior.mysql.database.azure.com';
$username = 's1411131021'; // 帳號要加 @smartfun-senior
$password = 'Test12345'; // 你的密碼
$dbname   = 'myproject'; // 你的資料庫名稱
$ssl_ca   = __DIR__ . '/BaltimoreCyberTrustRoot.crt.pem';

try {
    // 先嘗試連接 MySQL 服務器
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 檢查資料庫是否存在
    $stmt = $pdo->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$dbname'");
    if (!$stmt->fetch()) {
        throw new Exception("資料庫 '$dbname' 不存在，請確認資料庫名稱是否正確");
    }
    
    // 連接到指定的資料庫
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // 測試連接
    $pdo->query("SELECT 1");
    
} catch(PDOException $e) {
    $error = [
        'success' => false,
        'message' => '資料庫連接失敗',
        'details' => [
            'error_code' => $e->getCode(),
            'error_message' => $e->getMessage(),
            'connection_info' => [
                'host' => $host,
                'database' => $dbname,
                'username' => $username
            ]
        ]
    ];
    error_log("資料庫連接錯誤: " . print_r($error, true));
    die(json_encode($error, JSON_UNESCAPED_UNICODE));
}
?> 