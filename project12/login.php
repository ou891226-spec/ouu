<?php
session_start();
include("DB_open.php");
require_once "avatar_helper.php";


ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 如果已經登入，直接跳轉到主頁
if (isset($_SESSION['member_id']) && !empty($_SESSION['member_id'])) {
    header('Location: index.php');
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $account = trim(htmlspecialchars($_POST["account"]));
    $password = trim($_POST["password"]);

    // PDO 預處理查詢帳號
    $sql = "SELECT * FROM member WHERE account = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$account]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo "<script>alert('此帳號尚未註冊，請註冊新帳號'); window.location.href='register.php';</script>";
        exit;
    } else {
        // 檢查密碼是否已加密（長度超過20字元）
        if (strlen($row['password']) > 20) {
            // 使用 password_verify 驗證加密密碼
            if (password_verify($password, $row['password'])) {
                $_SESSION["member_id"] = $row['member_id'];
                $_SESSION["account"] = $row['account'];
                $_SESSION["member_name"] = $row['member_name'];
                $_SESSION["name"] = $row['name'] ?? $row['member_name'];
                
                // 修復頭像邏輯：如果沒有頭像就強制生成
                if ($row['avatar'] && $row['avatar'] !== 'img/big.jpg') {
                    $_SESSION["avatar_url"] = $row['avatar'];
                } else {
                    // 強制生成頭像
                    $avatar_path = generateDefaultAvatar($row['member_id'], $row['member_name']);
                    if ($avatar_path) {
                        // 更新資料庫
                        $update_sql = "UPDATE member SET avatar = ? WHERE member_id = ?";
                        $update_stmt = $pdo->prepare($update_sql);
                        $update_stmt->execute([$avatar_path, $row['member_id']]);
                        $_SESSION["avatar_url"] = $avatar_path;
                    } else {
                        $_SESSION["avatar_url"] = null;
                    }
                }

                // 登入任務不再自動完成，需要用戶手動完成
                // 移除自動完成登入任務的邏輯

                // 直接跳轉到主頁，不顯示alert
                header('Location: index.php');
                exit;
            } else {
                // 密碼驗證失敗
                echo "<script>alert('密碼錯誤，請重新輸入'); window.history.back();</script>";
                exit;
            }
        } else {
            // 舊密碼格式（未加密），直接比較
            if ($row['password'] === $password) {
                $_SESSION["member_id"] = $row['member_id'];
                $_SESSION["account"] = $row['account'];
                $_SESSION["member_name"] = $row['member_name'];
                $_SESSION["name"] = $row['name'] ?? $row['member_name'];
                
                // 修復頭像邏輯：如果沒有頭像就強制生成
                if ($row['avatar'] && $row['avatar'] !== 'img/big.jpg') {
                    $_SESSION["avatar_url"] = $row['avatar'];
                } else {
                    // 強制生成頭像
                    $avatar_path = generateDefaultAvatar($row['member_id'], $row['member_name']);
                    if ($avatar_path) {
                        // 更新資料庫
                        $update_sql = "UPDATE member SET avatar = ? WHERE member_id = ?";
                        $update_stmt = $pdo->prepare($update_sql);
                        $update_stmt->execute([$avatar_path, $row['member_id']]);
                        $_SESSION["avatar_url"] = $avatar_path;
                    } else {
                        $_SESSION["avatar_url"] = null;
                    }
                }

                // 直接跳轉到主頁，不顯示alert
                header('Location: index.php');
                exit;
            } else {
                echo "<script>alert('密碼錯誤，請重新輸入'); window.history.back();</script>";
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>樂齡智趣網 - 登入</title>
    <link rel="stylesheet" href="css/login.css">
</head>
<body>

<h1>樂齡智趣網 🎉</h1>

<form action="" method="post">
    <div class="input-box">
        <img src="img/user.png" alt="User">
        <input type="text" name="account" placeholder="請輸入帳號" required>
    </div>
    <div class="input-box">
        <img src="img/lock.png" alt="Password">
        <input type="password" name="password" placeholder="請輸入密碼" required>
    </div>
    <button class="login-btn" type="submit">登入</button>
</form>

<a class="register-link" href="register.php">註冊新帳號</a>

<a class="forgot-password-link" href="forgot_password.php">忘記密碼</a>

</body>
</html>
