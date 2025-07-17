<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$hostname = 'smartfun-senior.mysql.database.azure.com';
$username = 's1411131021'; // 帳號要加 @smartfun-senior
$password = 'Test12345'; // 你的密碼
$dbname   = 'myproject'; // 你的資料庫名稱
$ssl_ca   = __DIR__ . '/BaltimoreCyberTrustRoot.crt.pem';

try {
    $pdo = new PDO(
        "mysql:host=$hostname;dbname=$dbname;charset=utf8mb4;port=3306",
        $username,
        $password,
        [
            PDO::MYSQL_ATTR_SSL_CA => $ssl_ca,
            PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false
        ]
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "連線成功！";
} catch (PDOException $e) {
    echo "連接失敗: " . $e->getMessage();
}
?>