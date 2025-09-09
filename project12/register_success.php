<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>註冊成功 - 歡迎加入!</title>
    <link href="css/register_success.css" rel="stylesheet" type="text/css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="success-container">
        <div class="success-card">
            <!-- 成功圖示動畫 -->
            <div class="success-icon-wrapper">
                <div class="success-icon">
                    <i class="fas fa-check"></i>
                </div>
                <div class="success-ripple"></div>
            </div>
            
            <!-- 歡迎標題 -->
            <h1 class="success-title">註冊成功！</h1>
            <p class="success-subtitle">歡迎加入樂齡智趣網</p>
            
            <!-- 用戶資訊卡片 -->
            <div class="user-info-card">
                <div class="info-item">
                    <div class="info-label">
                        <i class="fas fa-user"></i>
                        <span>姓名</span>
                    </div>
                    <div class="info-value"><?php echo isset($_GET['name']) ? htmlspecialchars($_GET['name']) : ''; ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">
                        <i class="fas fa-id-card"></i>
                        <span>帳號</span>
                    </div>
                    <div class="info-value"><?php echo isset($_GET['account']) ? htmlspecialchars($_GET['account']) : ''; ?></div>
                </div>
            </div>
            
            <!-- 行動按鈕 -->
            <div class="action-buttons">
                <a href="login.php" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt"></i>
                    立即登入
                </a>
            </div>
            
            <!-- 歡迎訊息 -->
            <div class="welcome-message">
                <p>🎉 恭喜您成功註冊！我們已經為您準備了專屬的每日任務，開始您的遊戲之旅吧！</p>
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
            // 延遲顯示各個元素，創造漸進式動畫效果
            setTimeout(() => {
                document.querySelector('.success-icon-wrapper').classList.add('animate');
            }, 300);
            
            setTimeout(() => {
                document.querySelector('.success-title').classList.add('animate');
            }, 600);
            
            setTimeout(() => {
                document.querySelector('.success-subtitle').classList.add('animate');
            }, 800);
            
            setTimeout(() => {
                document.querySelector('.user-info-card').classList.add('animate');
            }, 1000);
            
            setTimeout(() => {
                document.querySelector('.features-preview').classList.add('animate');
            }, 1200);
            
            setTimeout(() => {
                document.querySelector('.action-buttons').classList.add('animate');
            }, 1400);
            
            setTimeout(() => {
                document.querySelector('.welcome-message').classList.add('animate');
            }, 1600);
        });
    </script>
</body>
</html>
