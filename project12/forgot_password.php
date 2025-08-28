<?php
session_start();
require_once 'db_connect.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/**
 * 生成隨機密碼字串
 * 
 * @param int $length 密碼長度，預設為 5 字元
 * @return string 包含數字和大小寫字母的隨機密碼
 * 
 * 安全性說明：
 * - 使用數字 (0-9) 和大小寫字母 (a-z, A-Z)
 * - 總共 62 個字元，提高密碼複雜度
 * - 使用 PHP 內建的 rand() 函數生成隨機數
 */
function generateRandomString($length = 5) {
    // 定義可用字元集：數字 + 小寫字母 + 大寫字母
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';

    // 取得字元集長度
    $charLength = strlen($characters);

    // 循環生成指定長度的隨機密碼
    for ($i = 0; $i < $length; $i++) {
        // 從字元集中隨機選擇一個字元
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
                // ========================================
                // 密碼重設流程 - 安全加密處理
                // ========================================
                
                // 1. 生成隨機密碼（6位字元）
                // 使用 generateRandomString() 函數生成包含數字和字母的隨機密碼
                $random_password = generateRandomString(6);
                
                // 2. 密碼加密處理
                // 使用 PHP 內建的 password_hash() 函數進行安全加密
                // PASSWORD_DEFAULT 使用當前 PHP 版本推薦的最佳加密演算法
                // 加密後的密碼長度約 60 字元，包含演算法、鹽值和雜湊值
                $hashed_password = password_hash($random_password, PASSWORD_DEFAULT);
                
                // 3. 將加密後的密碼儲存到資料庫
                // 使用預處理語句防止 SQL 注入攻擊
                // 只儲存加密後的密碼，不儲存明文密碼
                $update_sql = "UPDATE member SET password = ? WHERE account = ?";
                $update_stmt = $pdo->prepare($update_sql);
                $update_stmt->execute([$hashed_password, $account]);
                
                // 4. 顯示成功訊息
                // 向用戶顯示明文密碼（僅此一次），提醒立即修改
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
        body {
            background: #f5f6fa;
            min-height: 100vh;
            font-family: 'Microsoft JhengHei', Arial, sans-serif;
            margin: 0;
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        h1 {
            color: #4CAF50;
            text-align: center;
            font-size: 2.5em;
            margin-bottom: 30px;
            animation: fadeInDown 0.8s ease-out;
        }

        .message {
            padding: 15px;
            margin: 15px auto;
            width: 90%;
            max-width: 400px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 500;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            animation: slideInUp 0.6s ease-out;
            position: relative;
            overflow: hidden;
        }
        
        .message::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #4CAF50, #45a049);
        }
        
        .message.success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.success::before {
            background: linear-gradient(90deg, #4CAF50, #45a049);
        }
        
        .message.error {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .message.error::before {
            background: linear-gradient(90deg, #f44336, #d32f2f);
        }
        
        .back-link {
            display: inline-block;
            margin-top: 10px;
            padding: 12px 25px;
            font-size: 16px;
            color: white;
            text-decoration: none;
            background: #4CAF50;
            border-radius: 8px;
            transition: all 0.3s ease;
            animation: fadeIn 1s ease-out 0.5s both;
            border: none;
            cursor: pointer;
        }
        
        .back-link:hover {
            background: #45a049;
        }
        
        .info-box {
            background: #e7f3ff;
            border: 1px solid #b3d9ff;
            color: #0066cc;
            padding: 15px;
            margin: 15px auto;
            width: 90%;
            max-width: 400px;
            border-radius: 8px;
            font-size: 14px;
            line-height: 1.5;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            animation: slideInUp 0.6s ease-out 0.2s both;
        }

        .info-box strong {
            color: #d32f2f;
            font-size: 18px;
            font-weight: bold;
        }

        form {
            width: 90%;
            max-width: 450px;
            animation: slideInUp 0.6s ease-out 0.4s both;
            padding-bottom: 5px;
        }

        .input-box {
            background: white;
            border-radius: 5px;
            padding: 3px 15px;
            margin-bottom: 3px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            border: 1px solid #e0e0e0;
        }

        .input-box img {
            width: 25px;
            height: 25px;
            margin-right: 15px;
            opacity: 0.7;
        }

        .input-box input {
            flex: 1;
            border: none;
            outline: none;
            font-size: 16px;
            background: transparent;
            color: #333;
        }

        .input-box input::placeholder {
            color: #999;
        }

        .login-btn {
            width: auto;
            padding: 10px 25px;
            background: #4CAF50;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
            display: inline-block;
        }

        .login-btn:hover {
            background: #45a049;
        }

        .login-btn:active {
            transform: translateY(0);
        }

        /* 動畫效果 */
        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        /* 響應式設計 */
        @media (max-width: 768px) {
            h1 {
                font-size: 2em;
            }
            
            .message, .info-box, form {
                width: 95%;
            }
            
            .input-box {
                padding: 15px;
            }
        }
    </style>
</head>
<body>

<?php if (!empty($message)): ?>
    <div class="message <?php echo $message_type; ?>">
        <?php echo $message; ?>
    </div>
<?php endif; ?>

<div class="info-box">
    <strong>忘記密碼說明：</strong><br>
    1. 請輸入您的帳號<br>
    2. 系統會將您的密碼重設為隨機密碼<br>
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
