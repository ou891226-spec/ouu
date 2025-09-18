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
    PDO::ATTR_TIMEOUT => 10, // 設定連接超時為 10 秒
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
];

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4;sslmode=require", $user, $pass, $options);
} catch (PDOException $e) {
    error_log("資料庫連接失敗: " . $e->getMessage());
    // 如果是註冊頁面，重定向到註冊表單並顯示錯誤
    if (strpos($_SERVER['REQUEST_URI'], 'register') !== false) {
        header("Location: registerForm.php?error=系統暫時無法使用，請稍後再試");
        exit();
    }
    die("連線失敗：" . $e->getMessage());
}
?>