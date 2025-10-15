<?php
// 只在會話未啟動時啟動會話
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 檢查用戶是否已登入
if (!isset($_SESSION['member_id']) || empty($_SESSION['member_id'])) {
    // 如果未登入，重定向到登入頁面
    header('Location: login.php');
    exit;
}

// 確保必要的會話變數存在
if (!isset($_SESSION['account'])) {
    $_SESSION['account'] = '訪客';
}

if (!isset($_SESSION['name'])) {
    $_SESSION['name'] = '您好';
}

if (!isset($_SESSION['avatar_url'])) {
    $_SESSION['avatar_url'] = 'img/big.jpg';
}
?>
