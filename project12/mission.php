<?php
require_once 'check_login.php';
$account = isset($_SESSION['account']) ? $_SESSION['account'] : '訪客';
$name = isset($_SESSION['name']) ? $_SESSION['name'] : '您好';
$avatar_url = isset($_SESSION['avatar_url']) && $_SESSION['avatar_url'] ? htmlspecialchars($_SESSION['avatar_url']) : 'img/big.jpg';
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>每日任務</title>
    <link rel="stylesheet" href="css/mission.css">
    <link rel="stylesheet" href="css/profile-modal.css">
</head>
<body>
    <div id="modalOverlay" class="overlay" style="display:none;"></div>
    
    <!-- 頁首 -->
    <header>
        <div class="back-button" onclick="history.back()">
            <div class="back-icon">
                <span class="back-arrow">←</span>
            </div>
            <div class="back-label">返回</div>
        </div>
        <h1>每日任務</h1>
        <div class="user-icons">
            <a href="#" onclick="openProfileModal(event); return false;">
                <img src="<?php echo $avatar_url; ?>" alt="使用者" class="profile" />
            </a>
        </div>
    </header>

    <!-- 任務列表 -->
    <div class="mission-container">
        <div class="mission-header">
            <h2>今日任務</h2>
            <div class="mission-progress">
                <span id="completedCount">0</span> / <span id="totalCount">0</span>
            </div>
        </div>
        
        <div id="missionList" class="mission-list">
            <!-- 任務將在這裡動態載入 -->
        </div>
    </div>

    <!-- 個人資訊彈窗 -->
    <div id="profileModal" class="profile-modal" style="display:none;">
        <span class="close" onclick="closeProfileModal()">✕</span>
        <div class="profile-header">
            <div class="profile-account">
                帳號：<?php echo htmlspecialchars($account); ?>
            </div>
            <div class="profile-avatar-wrap">
                <img id="profileAvatarImg" src="<?php echo $avatar_url; ?>" alt="頭像" class="profile-avatar" />
                <span class="profile-avatar-edit" onclick="document.getElementById('avatarInput').click();">📷</span>
                <form id="avatarForm" action="upload_avatar.php" method="POST" enctype="multipart/form-data" style="display:none;">
                    <input type="file" id="avatarInput" name="avatar" accept="image/*" onchange="previewAndUploadAvatar(event)" />
                </form>
            </div>
        </div>
        <div class="profile-greeting">
            <?php echo htmlspecialchars($name); ?>，您好!
        </div>
        <div class="profile-cards" id="achievementCards">
            <!-- 成就卡片將在這裡動態生成 -->
        </div>
        <div class="profile-actions">
            <button class="profile-btn profile-manage" onclick="openAccountModal()"><span style="font-size:18px;">🖊️</span> 管理帳戶</button>
            <a href="logout.php" class="profile-btn profile-logout"><span style="font-size:18px;">[→]</span> 登出</a>
        </div>
    </div>

    <script src="js/mission.js"></script>
    <script src="js/achievements.js"></script>
<script src="js/avatar-upload.js"></script>
</body>
</html>


