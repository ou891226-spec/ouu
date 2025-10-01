<?php
// 設定PHP時區為台北時間
date_default_timezone_set('Asia/Taipei');

// 檢查是否為API請求
$isApiRequest = (
    (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') ||
    (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) ||
    (isset($_POST['ajax']) && $_POST['ajax'] === '1')
);

if ($isApiRequest) {
    // API請求：禁用所有錯誤顯示
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(E_ALL); // 記錄但不顯示
} else {
    // 一般頁面請求：顯示錯誤
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
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
  error_log("資料庫連線錯誤：" . $e->getMessage());
  // 清除任何輸出緩衝
  if (ob_get_level()) ob_end_clean();
  // 不輸出錯誤到頁面，避免影響 JSON 響應
  http_response_code(500);
  die(json_encode(['success' => false, 'message' => '資料庫連線錯誤']));
}
?>