<?php
$host = 'smartfun-senior.mysql.database.azure.com'; // 改成你的 Azure MySQL 主機
$dbname = 'myproject';
$username = 's1411131021'; // 帳號要加 @smartfun-senior
$password = 'Test12345';
$ssl_ca = __DIR__ . '/BaltimoreCyberTrustRoot.crt.pem';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8",
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
    echo "連接失敗: " . $e->getMessage();
    die();
}
?>