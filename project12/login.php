<?php
// 設定 PHP 執行時間和記憶體限制，避免 502 錯誤
ini_set('max_execution_time', 30); // 30 秒執行時間限制
ini_set('memory_limit', '128M'); // 128MB 記憶體限制
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include("DB_open.php");
require_once __DIR__ . "/avatar_helper.php";

// 如果已經登入，直接跳轉到主頁
if (isset($_SESSION['member_id']) && !empty($_SESSION['member_id'])) {
    header('Location: index.php');
    exit();
}

// 檢查是否從密碼重設頁面跳轉過來
$password_reset_success = false;
$reset_account = '';
$new_password = '';

if (isset($_GET['password_reset']) && $_GET['password_reset'] == '1' && 
    isset($_SESSION['reset_password_success']) && $_SESSION['reset_password_success']) {
    $password_reset_success = true;
    $reset_account = $_SESSION['reset_account'] ?? '';
    $new_password = $_SESSION['new_password'] ?? '';
    
    // 清除 session 資料，避免重複顯示
    unset($_SESSION['reset_password_success']);
    unset($_SESSION['reset_account']);
    unset($_SESSION['new_password']);
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
        // 設置錯誤訊息並重定向到註冊頁面
        header("Location: registerForm.php?error=" . urlencode("此帳號尚未註冊，請註冊新帳號"));
        exit;
    } else {
        // 檢查密碼是否已加密（長度超過20字元）
        if (strlen($row['password']) > 20) {
            // 使用 password_verify 驗證加密密碼
            if (password_verify($password, $row['password'])) {
                $_SESSION["member_id"] = $row['member_id'];
                $_SESSION["account"] = $row['account'];
                $_SESSION["member_name"] = $row['member_name'];
                $_SESSION["name"] = $row['member_name'];
                
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

                // 記錄登入行為到 user_behavior_log
                try {
                    $login_log_sql = "
                        INSERT INTO user_behavior_log 
                        (member_id, action_type, page_url, session_id, ip_address, user_agent, created_at)
                        VALUES (?, 'login', ?, ?, ?, ?, NOW())
                    ";
                    $session_id = 'login_' . time() . '_' . rand(1000, 9999);
                    $login_stmt = $pdo->prepare($login_log_sql);
                    $login_stmt->execute([
                        $row['member_id'],
                        $_SERVER['REQUEST_URI'] ?? '/login.php',
                        $session_id,
                        $_SERVER['REMOTE_ADDR'] ?? '',
                        $_SERVER['HTTP_USER_AGENT'] ?? ''
                    ]);
                    error_log("用戶 " . $row['member_id'] . " 登入行為已記錄");
                } catch (Exception $e) {
                    error_log("記錄登入行為失敗: " . $e->getMessage());
                }

                // 登入任務不再自動完成，需要用戶手動完成
                // 移除自動完成登入任務的邏輯
                
                // 檢查並修復用戶任務 - 已移除auto_task_fix.php依賴
                // 用戶任務分配已整合到register.php和daily_reset.php中

                // 直接跳轉到主頁，不顯示alert
                header('Location: index.php');
                exit;
            } else {
                // 密碼驗證失敗
                header('Location: login.php?error=password_incorrect');
                exit;
            }
        } else {
            // 舊密碼格式（未加密），直接比較
            if ($row['password'] === $password) {
                $_SESSION["member_id"] = $row['member_id'];
                $_SESSION["account"] = $row['account'];
                $_SESSION["member_name"] = $row['member_name'];
                $_SESSION["name"] = $row['member_name'];
                
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

                // 記錄登入行為到 user_behavior_log
                try {
                    $login_log_sql = "
                        INSERT INTO user_behavior_log 
                        (member_id, action_type, page_url, session_id, ip_address, user_agent, created_at)
                        VALUES (?, 'login', ?, ?, ?, ?, NOW())
                    ";
                    $session_id = 'login_' . time() . '_' . rand(1000, 9999);
                    $login_stmt = $pdo->prepare($login_log_sql);
                    $login_stmt->execute([
                        $row['member_id'],
                        $_SERVER['REQUEST_URI'] ?? '/login.php',
                        $session_id,
                        $_SERVER['REMOTE_ADDR'] ?? '',
                        $_SERVER['HTTP_USER_AGENT'] ?? ''
                    ]);
                    error_log("用戶 " . $row['member_id'] . " 登入行為已記錄");
                } catch (Exception $e) {
                    error_log("記錄登入行為失敗: " . $e->getMessage());
                }
                
                // 檢查並修復用戶任務 - 已移除auto_task_fix.php依賴
                // 用戶任務分配已整合到register.php和daily_reset.php中

                // 直接跳轉到主頁，不顯示alert
                header('Location: index.php');
                exit;
            } else {
                header('Location: login.php?error=password_incorrect');
                exit;
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
    <title>樂齡智趣網 - 登入</title>
    <link rel="stylesheet" href="css/login.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <!-- 品牌標誌 -->
            <div class="brand-logo">
                <div class="logo-icon">
                    <i class="fas fa-brain"></i>
                </div>
                <h1 class="brand-title">樂齡智趣網</h1>
                <p class="brand-subtitle">歡迎回來！開始您的智能遊戲之旅</p>
            </div>
            
            <!-- 密碼重設成功訊息 -->
            <?php if ($password_reset_success): ?>
            <div class="password-reset-success">
                <div class="success-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="success-content">
                    <h3>密碼重設成功！</h3>
                    <p>您的新密碼是：<strong class="new-password"><?php echo htmlspecialchars($new_password); ?></strong></p>
                    <p class="hint">請使用上方密碼登入，登入後建議立即修改為您熟悉的密碼</p>
                </div>
                <button class="copy-password-btn" onclick="copyNewPassword('<?php echo htmlspecialchars($new_password); ?>')">
                    <i class="fas fa-copy"></i> 複製密碼
                </button>
            </div>
            
            <script>
                // 自動填入帳號
                document.addEventListener('DOMContentLoaded', function() {
                    const accountInput = document.querySelector('input[name="account"]');
                    if (accountInput) {
                        accountInput.value = '<?php echo htmlspecialchars($reset_account); ?>';
                    }
                });
                
                function copyNewPassword(password) {
                    navigator.clipboard.writeText(password).then(function() {
                        showNotification('密碼已複製到剪貼簿！', 'success');
                    }).catch(function(err) {
                        console.error('複製失敗:', err);
                        showNotification('複製失敗，請手動選取密碼', 'error');
                    });
                }
                
                function showNotification(message, type) {
                    // 創建通知元素
                    const notification = document.createElement('div');
                    notification.className = `custom-notification ${type}`;
                    notification.innerHTML = `
                        <div class="notification-content">
                            <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'}"></i>
                            <span>${message}</span>
                        </div>
                    `;
                    
                    // 添加到頁面
                    document.body.appendChild(notification);
                    
                    // 觸發顯示動畫
                    setTimeout(() => {
                        notification.classList.add('show');
                    }, 100);
                    
                    // 3秒後自動消失
                    setTimeout(() => {
                        notification.classList.remove('show');
                        setTimeout(() => {
                            if (notification.parentNode) {
                                notification.parentNode.removeChild(notification);
                            }
                        }, 300);
                    }, 3000);
                }
            </script>
            <?php endif; ?>
            
            <!-- 登入錯誤訊息 -->
            <?php if (isset($_GET['error']) && $_GET['error'] === 'password_incorrect'): ?>
            <div class="login-error-message">
                <div class="error-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="error-content">
                    <h3>登入失敗</h3>
                    <p>密碼錯誤，請重新輸入</p>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- 登入表單 -->
            <form action="" method="post" class="login-form">
                <div class="form-group">
                    <div class="input-wrapper">
                        <div class="input-icon">
                            <i class="fas fa-user"></i>
                        </div>
                        <input type="text" name="account" placeholder="請輸入帳號" required class="form-input">
                    </div>
                </div>
                
                <div class="form-group">
                    <div class="input-wrapper">
                        <div class="input-icon">
                            <i class="fas fa-lock"></i>
                        </div>
                        <input type="password" name="password" placeholder="請輸入密碼" required class="form-input">
                    </div>
                </div>
                
                <button type="submit" class="login-btn">
                    <i class="fas fa-sign-in-alt"></i>
                    登入
                </button>
            </form>
            
            <!-- 其他選項 -->
            <div class="form-links">
                <a href="registerForm.php" class="register-link">
                    <i class="fas fa-user-plus"></i>
                    註冊新帳號
                </a>
                <a href="forgot_password.php" class="forgot-password-link">
                    <i class="fas fa-question-circle"></i>
                    忘記密碼
                </a>
            </div>
            
            <!-- 特色提示 -->
            <div class="features-hint">
                <div class="hint-item">
                    <i class="fas fa-gamepad"></i>
                    <span>多種益智遊戲</span>
                </div>
                <div class="hint-item">
                    <i class="fas fa-trophy"></i>
                    <span>成就獎勵系統</span>
                </div>
                <div class="hint-item">
                    <i class="fas fa-users"></i>
                    <span>好友互動功能</span>
                </div>
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
    
    <script>
        // 頁面載入動畫
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(() => {
                document.querySelector('.brand-logo').classList.add('animate');
            }, 200);
            
            setTimeout(() => {
                document.querySelector('.login-form').classList.add('animate');
            }, 400);
            
            setTimeout(() => {
                document.querySelector('.form-links').classList.add('animate');
            }, 600);
            
            setTimeout(() => {
                document.querySelector('.features-hint').classList.add('animate');
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
    </script>
</body>
</html>
