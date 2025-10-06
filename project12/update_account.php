<?php
// 設定錯誤報告等級，確保雲端環境相容性
error_reporting(E_ERROR | E_WARNING | E_PARSE);

// 開始輸出緩衝，防止意外輸出影響 headers
ob_start();

// 檢查 session 狀態，如果未啟動則啟動
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'db.php';

// 檢查登入狀態（不使用 check_login.php 避免重導向問題）
if (!isset($_SESSION['member_id']) || empty($_SESSION['member_id'])) {
    ob_end_clean();
    header('Location: login.php');
    exit();
}

$member_id = $_SESSION['member_id'];
$new_name = isset($_POST['name']) ? trim($_POST['name']) : '';
$new_password = isset($_POST['password']) ? trim($_POST['password']) : '';

if (!$member_id || !$new_name) {
    ob_end_clean();
    echo "<script>alert('資料不完整'); window.history.back();</script>";
    exit;
}

try {
    // 檢查名字是否重複（排除自己）
    $sql_check = "SELECT member_id FROM member WHERE member_name = ? AND member_id != ?";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute([$new_name, $member_id]);
    if ($stmt_check->fetch()) {
        ob_end_clean();
        echo "<script>alert('此名字已被使用，請換一個'); window.history.back();</script>";
        exit;
    }

    // 根據是否有輸入新密碼來決定更新方式
    if (!empty($new_password)) {
        // 使用 password_hash() 進行安全的密碼加鹽處理
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        // 更新名字與密碼
        $sql = "UPDATE member SET member_name = ?, password = ? WHERE member_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$new_name, $hashed_password, $member_id]);
    } else {
        // 只更新名字
        $sql = "UPDATE member SET member_name = ? WHERE member_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$new_name, $member_id]);
    }

    if ($stmt->rowCount() > 0) {
        // 建立成功頁面的參數
        $success_params = array();
        $success_params['name'] = urlencode($new_name);
        
        // 如果有更新密碼，添加密碼更新標記
        if (!empty($new_password)) {
            $success_params['password_updated'] = '1';
        }
        
        // 無論更新什麼，都需要重新登入
        
        // 重定向到美觀的成功頁面
        $success_url = "update_success.php?" . http_build_query($success_params);
        
        // 無論更新什麼，都清除 session 強制重新登入
        // 確保在 nginx 環境下正確清除 session
        $_SESSION = array();
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
        
        // 清除輸出緩衝並重導向
        ob_end_clean();
        header("Location: $success_url", true, 302);
        exit();
    } else {
        ob_end_clean();
        echo "<script>alert('更新失敗或資料未變更'); window.location.href='index.php';</script>";
    }
} catch (Exception $e) {
    ob_end_clean();
    echo "<script>alert('更新失敗：資料庫錯誤'); window.history.back();</script>";
    error_log("update_account.php 錯誤: " . $e->getMessage());
}
?> 