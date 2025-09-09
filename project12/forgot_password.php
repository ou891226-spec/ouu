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
                
                // 4. 重導向到登入頁面
                // 將新密碼作為 session 暫存，在登入頁面顯示
                $_SESSION['reset_password_success'] = true;
                $_SESSION['reset_account'] = $account;
                $_SESSION['new_password'] = $random_password;
                
                header("Location: login.php?password_reset=1");
                exit();
            } catch (PDOException $e) {
                $message = "重設密碼時發生錯誤，請稍後再試";
                $message_type = "error";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>樂齡智趣網 - 忘記密碼</title>
    <link rel="stylesheet" href="css/login.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <!-- 品牌標誌 -->
            <div class="brand-logo">
                <div class="logo-icon">
                    <i class="fas fa-key"></i>
                </div>
                <h1 class="brand-title">忘記密碼</h1>
                <p class="brand-subtitle">重設您的密碼，重新開始遊戲之旅</p>
            </div>
            
            <!-- 訊息顯示 -->
            <?php if (!empty($message)): ?>
            <div class="message-card <?php echo $message_type; ?>">
                <div class="message-icon">
                    <?php if ($message_type == 'success'): ?>
                        <i class="fas fa-check-circle"></i>
                    <?php else: ?>
                        <i class="fas fa-exclamation-triangle"></i>
                    <?php endif; ?>
                </div>
                <div class="message-content">
                    <?php echo $message; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- 說明卡片 -->
            <div class="info-card">
                <h3><i class="fas fa-info-circle"></i> 密碼重設說明</h3>
                <div class="info-steps">
                    <div class="step-item">
                        <span class="step-number">1</span>
                        <span class="step-text">請輸入您的帳號</span>
                    </div>
                    <div class="step-item">
                        <span class="step-number">2</span>
                        <span class="step-text">系統會將您的密碼重設為隨機密碼</span>
                    </div>
                    <div class="step-item">
                        <span class="step-number">3</span>
                        <span class="step-text">請使用新密碼登入後立即修改密碼</span>
                    </div>
                </div>
            </div>
            
            <!-- 重設密碼表單 -->
            <form action="" method="post" class="login-form" id="resetForm">
                <div class="form-group">
                    <div class="input-wrapper">
                        <div class="input-icon">
                            <i class="fas fa-user"></i>
                        </div>
                        <input type="text" name="account" placeholder="請輸入帳號" required class="form-input">
                    </div>
                </div>
                
                <button type="submit" class="login-btn" id="resetBtn">
                    <i class="fas fa-redo-alt"></i>
                    重設密碼
                </button>
            </form>
            
            <!-- 返回連結 -->
            <div class="form-links">
                <a href="login.php" class="register-link">
                    <i class="fas fa-arrow-left"></i>
                    <?php echo ($message_type == 'success') ? '重新登入' : '返回登入頁面'; ?>
                </a>
            </div>
        </div>
        
        <!-- 背景裝飾 -->
        <div class="bg-decoration">
            <div class="floating-shape shape-1"></div>
            <div class="floating-shape shape-2"></div>
            <div class="floating-shape shape-3"></div>
            <div class="floating-shape shape-4"></div>
            <div class="floating-shape shape-5"></div>
        </div>
    </div>
    
    <!-- 自定義樣式 -->
    <style>
        .message-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            margin: 20px 0;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            gap: 15px;
            border-left: 4px solid;
            opacity: 0;
            transform: translateY(30px);
            animation: slideInUp 0.6s ease-out forwards;
        }
        
        .message-card.success {
            border-left-color: #4CAF50;
            background: linear-gradient(135deg, #e8f5e8 0%, #f0f8f0 100%);
        }
        
        .message-card.error {
            border-left-color: #f44336;
            background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%);
        }
        
        .message-icon {
            font-size: 24px;
            min-width: 30px;
        }
        
        .message-card.success .message-icon {
            color: #4CAF50;
        }
        
        .message-card.error .message-icon {
            color: #f44336;
        }
        
        .message-content {
            flex: 1;
            font-size: 16px;
            line-height: 1.5;
            font-weight: 500;
        }
        
        .message-card.success .message-content {
            color: #2d5a2d;
        }
        
        .message-card.error .message-content {
            color: #c62828;
        }
        
        .info-card {
            background: linear-gradient(135deg, #e3f2fd 0%, #f1f8e9 100%);
            border-radius: 16px;
            padding: 20px;
            margin: 20px 0;
            border-left: 4px solid #2196f3;
            opacity: 0;
            transform: translateY(30px);
            animation: slideInUp 0.6s ease-out 0.2s forwards;
        }
        
        .info-card h3 {
            font-size: 18px;
            color: #1565c0;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .info-steps {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .step-item {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .step-number {
            background: #2196f3;
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
            min-width: 24px;
        }
        
        .step-text {
            font-size: 14px;
            color: #37474f;
            line-height: 1.4;
        }
        
        .logo-icon i.fa-key {
            color: white;
        }
        
        @keyframes slideInUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @media (max-width: 768px) {
            .message-card {
                flex-direction: column;
                text-align: center;
                gap: 10px;
            }
            
            .step-item {
                flex-direction: column;
                text-align: center;
                gap: 8px;
            }
            
            .step-text {
                font-size: 13px;
            }
        }
    </style>
    
    <script>
        // 頁面載入動畫
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(() => {
                document.querySelector('.brand-logo').classList.add('animate');
            }, 200);
            
            setTimeout(() => {
                document.querySelector('.login-form').classList.add('animate');
            }, 600);
            
            setTimeout(() => {
                document.querySelector('.form-links').classList.add('animate');
            }, 800);
        });
        
        // 輸入框焦點效果
        const inputs = document.querySelectorAll('.form-input');
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.classList.add('focused');
            });
            
            input.addEventListener('blur', function() {
                if (this.value === '') {
                    this.parentElement.classList.remove('focused');
                }
            });
        });
        
        // 防止重複提交
        const resetForm = document.getElementById('resetForm');
        const resetBtn = document.getElementById('resetBtn');
        let isSubmitting = false;
        
        if (resetForm) {
            resetForm.addEventListener('submit', function(e) {
                if (isSubmitting) {
                    e.preventDefault();
                    return false;
                }
                
                isSubmitting = true;
                resetBtn.disabled = true;
                resetBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 處理中...';
                resetBtn.style.opacity = '0.7';
                resetBtn.style.cursor = 'not-allowed';
            });
        }
    </script>
</body>
</html>
