<?php
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
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]
  );
} catch (PDOException $e) {
  var_dump($e);
  die("資料庫連線錯誤：" . $e->getMessage());
}
