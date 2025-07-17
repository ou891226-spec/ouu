<?php
require_once 'check_login.php';
$account = isset($_SESSION['account']) ? $_SESSION['account'] : '訪客';
$name = isset($_SESSION['name']) ? $_SESSION['name'] : '您好';
$avatar_url = isset($_SESSION['avatar_url']) && $_SESSION['avatar_url'] ? htmlspecialchars($_SESSION['avatar_url']) : 'img/big.jpg';
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>算術邏輯遊戲</title>
  <link rel="stylesheet" href="css/mains.css" />
  <link rel="stylesheet" href="css/mission.css" />
  <link rel="stylesheet" href="css/profile-modal.css" />
</head>
<body>

<!-- 黑色半透明背景 (彈窗遮罩) -->
<div id="modalOverlay" class="overlay" style="display:none;" onclick="closeAllModals()"></div>
<!-- 側邊欄遮罩 -->
<div id="overlay" class="overlay" onclick="toggleSidebar()"></div>

<!-- 側邊欄 -->
<div id="sidebar" class="sidebar">
  <a href="game-category.php" class="jelly-btn jelly-red">全部遊戲</a>
  <a href="game-categories.php" class="jelly-btn jelly-red">遊戲分類</a>
  <a href="friend.php" class="jelly-btn jelly-green">好友列表</a>
  <a href="Ranking_list.php" class="jelly-btn jelly-green">排行榜</a>
  <div class="btn-group">
    <a href="an.php" class="jelly-btn jelly-yellow">分析圖表</a>
    <a href="news.php" class="jelly-btn jelly-yellow">相關報導</a>
    <a href="us.php" class="jelly-btn jelly-yellow">關於我們</a>
  </div>
</div>

<!-- 頁首 -->
<header>
  <div id="menuButton" class="menu" onclick="toggleSidebar()">
    <img src="img/contents.jpg" alt="目錄" class="menu-icon">
    <span id="menuText" class="menu-text">目錄</span>
  </div>

  <!-- 搜尋欄表單 -->
  <form class="search-bar" action="game.php" method="GET" onsubmit="return validateSearch()">
    <input type="text" name="keyword" id="searchInput" placeholder="搜尋遊戲">
  </form>

  <span class="notification-bell" onclick="openMissionModal()">🔔</span>

  <a href="#" onclick="openProfileModal();return false;">
    <img src="<?php echo $avatar_url; ?>" alt="使用者" class="profile">
  </a>
</header>

<!-- 狀態列 -->
<div class="status-bar">
  <div class="score">您的分數 <span id="scoreValue" style="color: red;">0</span> 💰</div>
  <div class="time">
    已遊玩時間 <span id="timeValue">00:00:00</span>
    <button onclick="showTimeDetail()" class="time-icon-btn">⏱️</button>
  </div>
</div>

<!-- 算術邏輯標題 -->
<div style="text-align: center; margin: 20px 0; padding: 0 20px;">
  <h1 style="font-size: 2.5rem; color: #333; margin: 0; font-family: 'Microsoft JhengHei Bold', 'Microsoft JhengHei', sans-serif;">🔢算術邏輯</h1>
</div>

<!-- 切換按鈕 -->
<div class="mode-switch">
  <button id="singleBtn" class="mode-btn active">單人遊戲</button>
  <button id="doubleBtn" class="mode-btn">雙人遊戲</button>
</div>

<!-- 單人遊戲區塊 -->
<div id="single-player-section" class="section">
  <div class="game-grid">
    <div class="game-block">
      <div class="game-item">
        <a href="Vegetable-Cost Game.php"><img src="img/vegetable.jpg" alt="算菜錢"></a>
      </div>
      <div class="game-title">算菜錢</div>
    </div>
    <div class="game-block">
      <div class="game-item">
        <a href="2048ht.php"><img src="img/2048.png" alt="2048"></a>
      </div>
      <div class="game-title">2048</div>
    </div>
  </div>
</div>

<!-- 雙人遊戲區塊 -->
<div id="double-player-section" class="section" style="display: none;">
  <div class="game-grid">
    <div class="game-block">
      <div class="game-item">
        <a href="Vegetable-Cost Game.php"><img src="img/vegetable.jpg" alt="算菜錢"></a>
      </div>
      <div class="game-title">算菜錢</div>
    </div>
  </div>
</div>

<!-- 彈跳視窗 -->
<div id="missionModal" class="mission-modal" style="display: none;">
  <div class="modal-content">
    <span class="close" onclick="closeMissionModal()">✕</span>
    <h2>每日任務</h2>
    
    <div id="daily-tasks-container"></div>
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
  <div class="profile-cards">
    <div class="profile-card">
      <img src="img/vegetable.jpg" alt="遊戲成就" />
      <div class="profile-card-label">遊戲成就</div>
    </div>
    <div class="profile-card">
      <span class="emoji-icon">😲</span>
      <div class="profile-card-label">任務達人</div>
    </div>
    <div class="profile-card">
      <img src="img/rhythm.jpg" alt="反應達人" />
      <div class="profile-card-label">反應達人</div>
    </div>
    <div class="profile-card">
      <div class="emoji-icon" style="background:#ffe082;display:flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:10px;font-weight:bold;font-size:20px;">2048</div>
      <div class="profile-card-label">記憶達人</div>
    </div>
  </div>
  <div class="profile-actions">
    <button class="profile-btn profile-manage"><span style="font-size:18px;">🖊️</span> 管理帳戶</button>
    <a href="logout.php" class="profile-btn profile-logout"><span style="font-size:18px;">[→]</span> 登出</a>
  </div>
</div>

<!-- JavaScript -->
<script>
  let sidebarOpen = false;

  function toggleSidebar() {
    const sidebar = document.getElementById("sidebar");
    const overlay = document.getElementById("overlay");
    const menuText = document.getElementById("menuText");

    if (!sidebarOpen) {
      sidebar.style.left = "0";
      overlay.style.display = "block";
      menuText.style.display = "none";
    } else {
      sidebar.style.left = "-300px";
      overlay.style.display = "none";
      menuText.style.display = "inline";
    }
    sidebarOpen = !sidebarOpen;
  }

  document.addEventListener('click', function(event) {
    const sidebar = document.getElementById("sidebar");
    const menuButton = document.getElementById("menuButton");
    if (sidebarOpen && !sidebar.contains(event.target) && !menuButton.contains(event.target)) {
      toggleSidebar();
    }
  });

  const singleBtn = document.getElementById("singleBtn");
  const doubleBtn = document.getElementById("doubleBtn");
  const singleSection = document.getElementById("single-player-section");
  const doubleSection = document.getElementById("double-player-section");

  singleBtn.addEventListener("click", () => {
    singleSection.style.display = "block";
    doubleSection.style.display = "none";
    singleBtn.classList.add("active");
    doubleBtn.classList.remove("active");
  });

  doubleBtn.addEventListener("click", () => {
    singleSection.style.display = "none";
    doubleSection.style.display = "block";
    doubleBtn.classList.add("active");
    singleBtn.classList.remove("active");
  });

  function validateSearch() {
    const input = document.getElementById('searchInput').value.trim();
    if (input === '') {
      alert('請輸入關鍵字');
      return false;
    }
    return true;
  }
</script>
<!-- 外部 JS -->
<script src="js/auto-save-time.js"></script>
<script src="js/load-daily-tasks.js"></script>
<script src="js/mission.js"></script>
<script src="js/save-score.js"></script>
<script src="js/get-score.js"></script>
<script>
  function openProfileModal() {
    document.getElementById('profileModal').style.display = 'flex';
    document.getElementById('modalOverlay').style.display = 'block';
  }
  function closeProfileModal() {
    document.getElementById('profileModal').style.display = 'none';
    document.getElementById('modalOverlay').style.display = 'none';
  }
  function closeAllModals() {
    closeProfileModal();
    // 若有其他彈窗可在此一併關閉
  }
  function previewAndUploadAvatar(event) {
    const file = event.target.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = function(e) {
      document.getElementById('profileAvatarImg').src = e.target.result;
    };
    reader.readAsDataURL(file);
    document.getElementById('avatarForm').submit();
  }
</script>

</body>
</html>
