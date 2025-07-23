<?php
session_start();
include("DB_open.php");


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
        if ($row['password'] === $password) {
            $_SESSION["member_id"] = $row['member_id'];
            $_SESSION["account"] = $row['account'];
            $_SESSION["member_name"] = $row['member_name'];

            // ✅ 登入任務（task_id = 52）
            $member_id = $row['member_id'];
            $task_id = 52;
            $today = date('Y-m-d');

            try {
                // 首先檢查任務是否存在於 daily_tasks 表中
                $check_task_sql = "SELECT task_id FROM daily_tasks WHERE task_id = ?";
                $check_task_stmt = $pdo->prepare($check_task_sql);
                $check_task_stmt->execute([$task_id]);
                $task_exists = $check_task_stmt->fetch();

                if ($task_exists) {
                    // 查詢今天是否已經完成登入任務
                    $check_sql = "SELECT * FROM member_tasks WHERE member_id = ? AND task_id = ? AND DATE(completed_date) = ?";
                    $check_stmt = $pdo->prepare($check_sql);
                    $check_stmt->execute([$member_id, $task_id, $today]);
                    $check_row = $check_stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$check_row) {
                        $insert_sql = "INSERT INTO member_tasks (member_id, task_id, status, completed_date)
                                       VALUES (?, ?, 'completed', NOW())";
                        $insert_stmt = $pdo->prepare($insert_sql);
                        $insert_stmt->execute([$member_id, $task_id]);
                    }
                } else {
                    // 如果任務不存在，記錄錯誤但不影響登入
                    error_log("Task ID 52 not found in daily_tasks table");
                }
            } catch (PDOException $e) {
                // 記錄錯誤但不影響登入流程
                error_log("Error in login task processing: " . $e->getMessage());
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

</body>
</html>
