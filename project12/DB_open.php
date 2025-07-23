<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = 'smartfun-senior.mysql.database.azure.com';
$db = 'myproject';
$user = 's1411131021';
$pass = 'Test12345'; // 你的密碼

$options = [
    PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
    PDO::MYSQL_ATTR_SSL_CA => false,
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4;sslmode=require", $user, $pass, $options);
} catch (PDOException $e) {
    die("連線失敗：" . $e->getMessage());
}
?>
