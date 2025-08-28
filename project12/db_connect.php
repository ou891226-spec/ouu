<?php
// 只在非 AJAX 請求時顯示錯誤
if (!isset($_POST['ajax']) || $_POST['ajax'] !== '1') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
}

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
  
  // 設定時區為台北時間
  $pdo->exec("SET time_zone = '+08:00'");
  error_log("db_connect.php: 資料庫時區已設定為 +08:00");
} catch (PDOException $e) {
  die("資料庫連線錯誤：" . $e->getMessage());
}
?>