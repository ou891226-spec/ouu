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

// 設置用戶ID變數
$_SESSION['user_id'] = $_SESSION['member_id'];

// 如果沒有設置名字或頭像，從資料庫獲取
if (!isset($_SESSION['name']) || empty($_SESSION['name']) || !isset($_SESSION['avatar_url'])) {
    require_once 'db.php';
    try {
        $sql = "SELECT member_name, avatar FROM member WHERE member_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$_SESSION['member_id']]);
        $result = $stmt->fetch();
        
        if ($result) {
            if (!isset($_SESSION['name']) || empty($_SESSION['name'])) {
                $_SESSION['name'] = $result['member_name'] ?? $_SESSION['account'] ?? '使用者';
            }
            // 強制更新頭像路徑，確保與資料庫同步
            $_SESSION['avatar_url'] = $result['avatar'] ?? null;
        } else {
            if (!isset($_SESSION['name']) || empty($_SESSION['name'])) {
                $_SESSION['name'] = $_SESSION['account'] ?? '使用者';
            }
        }
    } catch (Exception $e) {
        if (!isset($_SESSION['name']) || empty($_SESSION['name'])) {
            $_SESSION['name'] = $_SESSION['account'] ?? '使用者';
        }
        error_log("check_login.php 獲取用戶資料錯誤: " . $e->getMessage());
    }
}
?>