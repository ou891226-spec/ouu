<?php
$host = 'smartfun-senior.mysql.database.azure.com';
$user = 's1411131021@smartfun-senior';
$pass = 'Test12345'; // 變數名要和下面一致
$dbname = 'myproject'; // 你的資料庫名稱
$ssl_ca = __DIR__ . '/BaltimoreCyberTrustRoot.crt.pem';

if (!file_exists($ssl_ca)) {
    die("SSL 憑證不存在於: $ssl_ca");
}

try {
  $pdo = new PDO(
    "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
    $user,
    $pass,
    [
      PDO::MYSQL_ATTR_SSL_CA => $ssl_ca,
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]
  );
} catch (PDOException $e) {
  var_dump($e);
  die("資料庫連線錯誤：" . $e->getMessage());
}