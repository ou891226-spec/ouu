<?php
session_start();
include("DB_open.php");

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

function generateRandomString($length = 5) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';

    $charLength = strlen($characters);

    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charLength - 1)];
    }

    return $randomString;
}

$message = '';
$message_type = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $account = trim(htmlspecialchars($_POST["account"]));
    
    if (empty($account)) {
        $message = "請輸入帳號";
        $message_type = "error";
    } else {
        // 檢查帳號是否存在
        $sql = "SELECT * FROM member WHERE account = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$account]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$row) {
            $message = "此帳號不存在，請檢查帳號是否正確";
            $message_type = "error";
        } else {
            try {
                // 生成隨機密碼
                $random_password = generateRandomString(6);
                $update_sql = "UPDATE member SET password = ? WHERE account = ?";
                $update_stmt = $pdo->prepare($update_sql);
                $update_stmt->execute([$random_password, $account]);
                
                $message = "密碼已重設為隨機密碼：{$random_password}，請登入後立即修改密碼";
                $message_type = "success";
            } catch (PDOException $e) {
                $message = "重設密碼時發生錯誤，請稍後再試";
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
    <title>樂齡智趣網 - 忘記密碼</title>
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
        
        .info-box {
            background-color: #e7f3ff;
            border: 1px solid #b3d9ff;
            color: #0066cc;
            padding: 15px;
            margin: 20px auto;
            width: 95%;
            max-width: 600px;
            border-radius: 6px;
            font-size: 18px;
        }
    </style>
</head>
<body>

<h1>樂齡智趣網 🔑</h1>

<?php if (!empty($message)): ?>
    <div class="message <?php echo $message_type; ?>">
        <?php echo $message; ?>
    </div>
<?php endif; ?>

<div class="info-box">
    <strong>忘記密碼說明：</strong><br>
    1. 請輸入您的帳號<br>
    2. 系統會將您的密碼重設為隨機密碼（6位字元）<br>
    3. 請使用新密碼登入後立即修改密碼
</div>

<form action="" method="post">
    <div class="input-box">
        <img src="img/user.png" alt="User">
        <input type="text" name="account" placeholder="請輸入帳號" required>
    </div>
    <button class="login-btn" type="submit">重設密碼</button>
</form>

<a class="back-link" href="login.php">返回登入頁面</a>

</body>
</html>
