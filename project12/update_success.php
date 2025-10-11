<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>更新成功 - 樂齡智趣網</title>
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
            
            <!-- 成功標題 -->
            <h1 class="success-title">更新成功！</h1>
            <p class="success-subtitle">您的帳戶資訊已成功更新</p>
            
            <!-- 更新資訊卡片 -->
            <div class="user-info-card">
                <?php if (isset($_GET['name'])): ?>
                <div class="info-item">
                    <div class="info-label">
                        <i class="fas fa-user"></i>
                        <span>新姓名</span>
                    </div>
                    <div class="info-value"><?php echo htmlspecialchars(urldecode($_GET['name'])); ?></div>
                </div>
                <?php endif; ?>
                
                <?php if (isset($_GET['password_updated']) && $_GET['password_updated'] == '1'): ?>
                <div class="info-item">
                    <div class="info-label">
                        <i class="fas fa-lock"></i>
                        <span>密碼</span>
                    </div>
                    <div class="info-value">已更新</div>
                </div>
                <?php endif; ?>
                
                <div class="info-item">
                    <div class="info-label">
                        <i class="fas fa-clock"></i>
                        <span>更新時間</span>
                    </div>
                    <div class="info-value"><?php echo date('Y-m-d H:i:s'); ?></div>
                </div>
            </div>
            
            <!-- 安全提示 -->
            <div class="security-tips">
                <h3><i class="fas fa-shield-alt"></i> 安全提醒</h3>
                <div class="tips-content">
                    <?php if (isset($_GET['password_updated']) && $_GET['password_updated'] == '1'): ?>
                    <div class="tip-item">
                        <i class="fas fa-info-circle"></i>
                        <span>密碼已成功更新，請妥善保管新密碼</span>
                    </div>
                    <?php endif; ?>
                    <div class="tip-item">
                        <i class="fas fa-user-shield"></i>
                        <span>定期更新密碼可提升帳戶安全性</span>
                    </div>
                    <div class="tip-item">
                        <i class="fas fa-eye-slash"></i>
                        <span>請勿與他人分享您的登入資訊</span>
                    </div>
                </div>
            </div>
            
            <!-- 行動按鈕已移除，因為會自動重導向 -->
            
            <!-- 成功訊息 -->
            <div class="welcome-message">
                <p>🎉 您的帳戶資訊已成功更新！現在可以繼續享受我們的遊戲服務。</p>
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
        .security-tips {
            background: linear-gradient(135deg, #e3f2fd 0%, #f1f8e9 100%);
            border-radius: 16px;
            padding: 20px;
            margin: 25px 0;
            border-left: 4px solid #2196f3;
            opacity: 0;
            transform: translateY(30px);
            transition: all 0.6s ease-out;
        }
        
        .security-tips.animate {
            opacity: 1;
            transform: translateY(0);
        }
        
        .security-tips h3 {
            font-size: 18px;
            color: #1565c0;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .tips-content {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .tip-item {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            color: #37474f;
        }
        
        .tip-item i {
            color: #2196f3;
            font-size: 16px;
            width: 20px;
            text-align: center;
        }
        
        @media (max-width: 768px) {
            .tip-item {
                font-size: 13px;
            }
            
            .security-tips h3 {
                font-size: 16px;
            }
        }
    </style>
    
    <script>
        // 頁面載入動畫
        document.addEventListener('DOMContentLoaded', function() {
            // 延遲顯示各個元素，創造漸進式動畫效果
            setTimeout(() => {
                const iconWrapper = document.querySelector('.success-icon-wrapper');
                if (iconWrapper) {
                    iconWrapper.classList.add('animate');
                }
            }, 300);
            
            setTimeout(() => {
                const successTitle = document.querySelector('.success-title');
                if (successTitle) {
                    successTitle.classList.add('animate');
                }
            }, 600);
            
            setTimeout(() => {
                const subtitle = document.querySelector('.success-subtitle');
                if (subtitle) {
                    subtitle.classList.add('animate');
                }
            }, 800);
            
            setTimeout(() => {
                const userInfoCard = document.querySelector('.user-info-card');
                if (userInfoCard) {
                    userInfoCard.classList.add('animate');
                }
            }, 1000);
            
            setTimeout(() => {
                const securityTips = document.querySelector('.security-tips');
                if (securityTips) {
                    securityTips.classList.add('animate');
                }
            }, 1200);
            
            setTimeout(() => {
                const actionButtons = document.querySelector('.action-buttons');
                if (actionButtons) {
                    actionButtons.classList.add('animate');
                }
            }, 1400);
            
            setTimeout(() => {
                const welcomeMessage = document.querySelector('.welcome-message');
                if (welcomeMessage) {
                    welcomeMessage.classList.add('animate');
                }
            }, 1600);
            
            // 自動重導向功能
            <?php if (isset($_GET['password_updated']) && $_GET['password_updated'] == '1'): ?>
            // 如果密碼已更新，3秒後重導向到登入頁面
            let countdown = 3;
            const redirectMessage = document.createElement('div');
            redirectMessage.style.cssText = `
                position: fixed;
                top: 50%;
                left: 50%;
                background: linear-gradient(135deg, #2196F3, #1976D2);
                color: white;
                padding: 18px 28px;
                border-radius: 12px;
                font-size: 16px;
                font-weight: 500;
                box-shadow: 0 8px 32px rgba(33, 150, 243, 0.4);
                backdrop-filter: blur(15px);
                z-index: 1000;
                min-width: 280px;
                text-align: center;
                transform: translate(-50%, -50%) scale(0.8);
                opacity: 0;
                transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            `;
            
            const updateCountdown = () => {
                redirectMessage.innerHTML = `<i class="fas fa-info-circle"></i> ${countdown} 秒後自動前往登入頁面`;
                countdown--;
                
                if (countdown < 0) {
                    redirectMessage.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 正在前往登入頁面...';
                    setTimeout(() => {
                        window.location.href = 'login.php';
                    }, 500);
                } else {
                    setTimeout(updateCountdown, 1000);
                }
            };
            
            // 2秒後開始倒數
            setTimeout(() => {
                document.body.appendChild(redirectMessage);
                // 觸發顯示動畫
                setTimeout(() => {
                    redirectMessage.style.opacity = '1';
                    redirectMessage.style.transform = 'translate(-50%, -50%) scale(1)';
                }, 100);
                updateCountdown();
            }, 2000);
            <?php else: ?>
            // 如果只是更新姓名，也需要3秒倒數後重導向到登入頁面
            let countdown = 3;
            const redirectMessage = document.createElement('div');
            redirectMessage.style.cssText = `
                position: fixed;
                top: 50%;
                left: 50%;
                background: linear-gradient(135deg, #2196F3, #1976D2);
                color: white;
                padding: 18px 28px;
                border-radius: 12px;
                font-size: 16px;
                font-weight: 500;
                box-shadow: 0 8px 32px rgba(33, 150, 243, 0.4);
                backdrop-filter: blur(15px);
                z-index: 1000;
                min-width: 280px;
                text-align: center;
                transform: translate(-50%, -50%) scale(0.8);
                opacity: 0;
                transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            `;
            
            const updateCountdown = () => {
                redirectMessage.innerHTML = `<i class="fas fa-info-circle"></i> ${countdown} 秒後自動前往登入頁面`;
                countdown--;
                
                if (countdown < 0) {
                    redirectMessage.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 正在前往登入頁面...';
                    setTimeout(() => {
                        window.location.href = 'login.php';
                    }, 500);
                } else {
                    setTimeout(updateCountdown, 1000);
                }
            };
            
            // 2秒後開始倒數
            setTimeout(() => {
                document.body.appendChild(redirectMessage);
                // 觸發顯示動畫
                setTimeout(() => {
                    redirectMessage.style.opacity = '1';
                    redirectMessage.style.transform = 'translate(-50%, -50%) scale(1)';
                }, 100);
                updateCountdown();
            }, 2000);
            <?php endif; ?>
        });
        
        // 通知動畫已內聯處理
    </script>
</body>
</html>
