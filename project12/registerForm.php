<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>用戶註冊 - 樂齡智趣網</title>
    <link href="css/login.css" rel="stylesheet" type="text/css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="bg-decoration"></div>
    
    <div class="login-container">
        <div class="login-card">
            <!-- 品牌標誌 -->
            <div class="brand-logo">
                <div class="logo-icon">
                    <i class="fas fa-user-plus"></i>
                </div>
                <h1>樂齡智趣網</h1>
                <p>歡迎加入！開始您的智能遊戲之旅</p>
            </div>

            <!-- 錯誤訊息 -->
            <?php if (isset($_GET['error'])): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-triangle"></i>
                <span><?php echo htmlspecialchars($_GET['error']); ?></span>
            </div>
            <?php endif; ?>
            
            <!-- 註冊表單 -->
            <form action="register.php" method="post" class="login-form">
                <div class="form-group">
                    <div class="input-wrapper">
                        <i class="fas fa-user"></i>
                        <input type="text" name="name" class="form-input" placeholder="姓名" required>
                    </div>
                </div>

                <div class="form-group">
                    <div class="input-wrapper">
                        <i class="fas fa-id-card"></i>
                        <input type="text" name="id" class="form-input" placeholder="帳號" required>
                    </div>
                </div>

                <div class="form-group">
                    <div class="input-wrapper">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="password" class="form-input" placeholder="密碼" required>
                    </div>
                </div>

                <div class="form-group">
                    <div class="input-wrapper">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="confirm_password" class="form-input" placeholder="確認密碼" required>
                    </div>
                </div>

                <button type="submit" class="login-btn">
                    <i class="fas fa-user-plus"></i>
                    註冊帳號
                </button>
            </form>

            <!-- 表單連結 -->
            <div class="form-links">
                <a href="login.php" class="register-link">
                    <i class="fas fa-sign-in-alt"></i>
                    已有帳號？立即登入
                </a>
            </div>

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

            // 檢查初始值
            if (input.value !== '') {
                input.parentElement.classList.add('focused');
            }
        });

        // 密碼確認驗證
        const password = document.querySelector('input[name="password"]');
        const confirmPassword = document.querySelector('input[name="confirm_password"]');
        
        function validatePasswords() {
            if (password.value !== confirmPassword.value) {
                confirmPassword.setCustomValidity('密碼不一致');
            } else {
                confirmPassword.setCustomValidity('');
            }
        }

        password.addEventListener('input', validatePasswords);
        confirmPassword.addEventListener('input', validatePasswords);
    </script>
</body>
</html>
