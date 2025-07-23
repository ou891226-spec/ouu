<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$host = 'smartfun-senior.mysql.database.azure.com';
$user = 's1411131021';
$pass = 'Test12345';
$dbname = 'myproject';

try {
  $pdo = new PDO(
    "mysql:host=$host;dbname=$dbname;charset=utf8mb4;sslmode=require",
    $user,
    $pass,
    [
      PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
      PDO::MYSQL_ATTR_SSL_CA => false,
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]
  );
} catch (PDOException $e) {
  die("資料庫連線錯誤：" . $e->getMessage());
}
?>
