<?php
session_start();
include("DB_open.php");

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 檢查是否已登入
if (!isset($_SESSION['member_id']) || empty($_SESSION['member_id'])) {
    header('Location: login.php');
    exit();
}

$message = '';
$message_type = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $current_password = trim($_POST["current_password"]);
    $new_password = trim($_POST["new_password"]);
    $confirm_password = trim($_POST["confirm_password"]);
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $message = "請填寫所有欄位";
        $message_type = "error";
    } elseif ($new_password !== $confirm_password) {
        $message = "新密碼與確認密碼不符";
        $message_type = "error";
    } elseif (strlen($new_password) < 6) {
        $message = "新密碼至少需要6個字元";
        $message_type = "error";
    } else {
        // 驗證當前密碼
        $member_id = $_SESSION['member_id'];
        $sql = "SELECT password FROM member WHERE member_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$member_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$row || $row['password'] !== $current_password) {
            $message = "當前密碼錯誤";
            $message_type = "error";
        } else {
            // 更新密碼
            $update_sql = "UPDATE member SET password = ? WHERE member_id = ?";
            $update_stmt = $pdo->prepare($update_sql);
            
            try {
                $update_stmt->execute([$new_password, $member_id]);
                $message = "密碼修改成功！";
                $message_type = "success";
            } catch (PDOException $e) {
                $message = "密碼修改失敗，請稍後再試";
                $message_type = "error";
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
    <title>樂齡智趣網 - 修改密碼</title>
    <link rel="stylesheet" href="css/login.css">
    <style>
        .message {
            padding: 15px;
            margin: 20px auto;
            width: 95%;
            max-width: 600px;
            border-radius: 6px;
            font-size: 20px;
            font-weight: bold;
        }
        
        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .back-link {
            display: inline-block;
            margin-top: 20px;
            font-size: 20px;
            color: #4CAF50;
            text-decoration: none;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        .password-requirements {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            margin: 20px auto;
            width: 95%;
            max-width: 600px;
            border-radius: 6px;
            font-size: 16px;
        }
    </style>
</head>
<body>

<h1>樂齡智趣網 🔐</h1>

<?php if (!empty($message)): ?>
    <div class="message <?php echo $message_type; ?>">
        <?php echo $message; ?>
    </div>
<?php endif; ?>

<div class="password-requirements">
    <strong>密碼要求：</strong><br>
    • 至少6個字元<br>
    • 建議包含數字和字母
</div>

<form action="" method="post">
    <div class="input-box">
        <img src="img/lock.png" alt="Current Password">
        <input type="password" name="current_password" placeholder="請輸入當前密碼" required>
    </div>
    <div class="input-box">
        <img src="img/lock.png" alt="New Password">
        <input type="password" name="new_password" placeholder="請輸入新密碼" required>
    </div>
    <div class="input-box">
        <img src="img/lock.png" alt="Confirm Password">
        <input type="password" name="confirm_password" placeholder="請確認新密碼" required>
    </div>
    <button class="login-btn" type="submit">修改密碼</button>
</form>

<a class="back-link" href="index.php">返回主頁</a>

</body>
</html>
