<?php
session_start();

// 檢查用戶是否已登入
if (!isset($_SESSION['member_id']) || empty($_SESSION['member_id'])) {
    // 如果沒有登入，跳轉到登入頁面
    header('Location: login.php');
    exit();
}

// 如果已登入，繼續執行頁面內容
?> 