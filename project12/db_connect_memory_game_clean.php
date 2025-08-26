<?php
$hostname = 'smartfun-senior.mysql.database.azure.com';
$username = 's1411131021';
$password = 'Test12345';
$dbname   = 'myproject';
$ssl_ca   = __DIR__ . '/BaltimoreCyberTrustRoot.crt.pem';

// 在API請求中禁用錯誤輸出
if (strpos($_SERVER['REQUEST_URI'], 'api') !== false || 
    strpos($_SERVER['REQUEST_URI'], 'sync') !== false || 
    strpos($_SERVER['REQUEST_URI'], 'game-sync') !== false ||
    strpos($_SERVER['REQUEST_URI'], 'invitation_handler.php') !== false) {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
} else {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

try {
    $pdo = new PDO(
        "mysql:host=$hostname;dbname=$dbname;charset=utf8",
        $username,
        $password,
        [
            PDO::MYSQL_ATTR_SSL_CA => $ssl_ca,
            PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false
        ]
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    // 檢查是否在API請求中
    if (strpos($_SERVER['REQUEST_URI'], 'api') !== false || 
        strpos($_SERVER['REQUEST_URI'], 'sync') !== false || 
        strpos($_SERVER['REQUEST_URI'], 'game-sync') !== false ||
        strpos($_SERVER['REQUEST_URI'], 'invitation_handler.php') !== false) {
        // 在API請求中，返回JSON錯誤
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        echo json_encode(['success' => false, 'message' => '資料庫連接失敗: ' . $e->getMessage()]);
        exit;
    } else {
        // 在一般頁面中，顯示錯誤
        echo "連接失敗: " . $e->getMessage();
        die();
    }
}
