<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = 'smartfun-senior.mysql.database.azure.com';
$db = 'myproject';
$user = 's1411131021';
$pass = 'Test12345';
$ssl_ca = __DIR__ . '/BaltimoreCyberTrustRoot.crt.pem';

$options = [
    PDO::MYSQL_ATTR_SSL_CA => $ssl_ca,
    PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
];

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;port=3306;charset=utf8mb4", $user, $pass, $options);
    echo "連線成功！";
} catch (PDOException $e) {
    echo "連線失敗：" . $e->getMessage();
}
?>