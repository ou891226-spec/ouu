<?php
$hostname = 'smartfun-senior.mysql.database.azure.com';
$username = 's1411131021'; // 帳號要加 @smartfun-senior
$password = 'Test12345'; // 你的密碼
$dbname   = 'myproject'; // 你的資料庫名稱
$ssl_ca   = __DIR__ . '/BaltimoreCyberTrustRoot.crt.pem';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    $pdo = new PDO(
        "mysql:host=$hostname;dbname=$dbname;charset=utf8",
        $username,
        $password,
        [
            PDO::MYSQL_ATTR_SSL_CA => $ssl_ca,
            PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false
        ]
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    echo "連接失敗: " . $e->getMessage();
    die();
}
?> 