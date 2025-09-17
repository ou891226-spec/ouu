<?php
// 設定PHP時區為台北時間
date_default_timezone_set('Asia/Taipei');

$host = 'smartfun-senior.mysql.database.azure.com';
$user = 's1411131021';
$pass = 'Test12345'; // 變數名要和下面一致
$dbname = 'myproject'; // 你的資料庫名稱

try {
  $pdo = new PDO(
    "mysql:host=$host;dbname=$dbname;charset=utf8mb4;sslmode=require",
    $user,
    $pass,
    [
      PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
      PDO::MYSQL_ATTR_SSL_CA => false,
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_TIMEOUT => 30,
      PDO::MYSQL_ATTR_INIT_COMMAND => "SET SESSION wait_timeout=28800, interactive_timeout=28800"
    ]
  );
  
  // 設定時區為台北時間
  $pdo->exec("SET time_zone = '+08:00'");
  error_log("資料庫連線成功，時區已設定為 +08:00");
} catch (PDOException $e) {
  error_log("資料庫連線錯誤：" . $e->getMessage());
  
  // 嘗試重新連線
  try {
    $pdo = new PDO(
      "mysql:host=$host;dbname=$dbname;charset=utf8mb4;sslmode=require",
      $user,
      $pass,
      [
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
        PDO::MYSQL_ATTR_SSL_CA => false,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 30,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET SESSION wait_timeout=28800, interactive_timeout=28800"
      ]
    );
    $pdo->exec("SET time_zone = '+08:00'");
    error_log("資料庫重新連線成功");
  } catch (PDOException $e2) {
    error_log("資料庫重新連線失敗：" . $e2->getMessage());
    throw new Exception("資料庫連線錯誤：" . $e->getMessage());
  }
}