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
  <title>遊戲分類</title>
  <link rel="stylesheet" href="css/main.css" />
  <link rel="stylesheet" href="css/mission.css" />
  <link rel="stylesheet" href="css/profile-modal.css" />
  <style>
    .category-container {
      max-width: 1200px;
      margin: 0 auto;
      padding: 20px;
    }
    
    .category-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 30px;
      margin-top: 20px; /* 減少上方空白 */
    }
    
    .category-card {
      background: #fff; /* 改為白色 */
      border-radius: 15px;
      padding: 30px;
      text-align: center;
      color: #333;
      text-decoration: none;
      transition: transform 0.3s ease, box-shadow 0.3s ease;
      box-shadow: 0 4px 16px rgba(0,0,0,0.08);
      border: 1px solid #eee;
    }
    
    .category-card:hover {
      transform: translateY(-10px);
      box-shadow: 0 8px 32px rgba(0,0,0,0.12);
    }
    
    .category-icon {
      font-size: 3rem;
      margin-bottom: 16px;
      display: block;
    }
    
    .category-title {
      font-size: 1.5rem;
      font-weight: bold;
      margin-bottom: 10px;
    }
    
    .category-description {
      font-size: 1rem;
      opacity: 0.9;
      line-height: 1.5;
    }
    
    /* 移除返回首頁按鈕樣式 */
    .page-title {
      text-align: center;
      color: #333;
      margin-bottom: 70px; /* 再次增加標題與分類卡片間距 */
      margin-top: 30px; /* 增加標題上方間距，讓標題往下移動 */
      font-size: 2.2rem;
    }
  </style>
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

  <form class="search-bar" action="game.php" method="GET" onsubmit="return validateSearch()">
    <input type="text" name="keyword" id="searchInput" placeholder="搜尋遊戲">
  </form>

  <div class="user-icons">
    <a href="#" onclick="openMissionModal()">
      <span class="notification-bell">🔔</span>
    </a>
    <a href="#" onclick="openProfileModal();return false;">
      <img src="<?php echo $avatar_url; ?>" alt="使用者" class="profile">
    </a>
  </div>
</header>

<!-- 狀態列 -->
<div class="status-bar">
  <div class="score">您的分數 <span id="scoreValue" style="color: red;">0</span> 💰</div>
  <div class="time">
    已遊玩時間 <span id="timeValue">00:00:00</span>
    <button onclick="showTimeDetail()" class="time-icon-btn">⏱️</button>
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

<!-- 主要內容 -->
<div class="category-container">
  <h1 class="page-title">遊戲分類</h1>
  <div class="category-grid">
    <a href="react.php" class="category-card">
      <span class="category-icon">⚡</span>
      <div class="category-title">反應力</div>
      <div class="category-description">
        測試您的反應速度和手眼協調能力，挑戰各種需要快速反應的遊戲。
      </div>
    </a>
    <a href="memory.php" class="category-card">
      <span class="category-icon">🧠</span>
      <div class="category-title">記憶力</div>
      <div class="category-description">
        鍛鍊您的記憶能力，通過各種記憶遊戲提升認知功能。
      </div>
    </a>
    <a href="math.php" class="category-card">
      <span class="category-icon">🔢</span>
      <div class="category-title">算術邏輯</div>
      <div class="category-description">
        挑戰數學思維和邏輯推理，解決各種數字和邏輯謎題。
      </div>
    </a>
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

<!-- Script 區 -->
<script>
let sidebarOpen = false;

function toggleSidebar() {
  const sidebar = document.getElementById("sidebar");
  const menuText = document.getElementById("menuText");
  const overlay = document.getElementById("overlay");

  if (!sidebarOpen) {
    sidebar.style.left = "0";
    menuText.style.display = "none";
    overlay.style.display = "block";
  } else {
    sidebar.style.left = "-300px";
    menuText.style.display = "inline";
    overlay.style.display = "none";
  }
  sidebarOpen = !sidebarOpen;
}

function validateSearch() {
  const input = document.getElementById('searchInput').value.trim();
  if (input === '') {
    alert('請輸入關鍵字');
    return false;
  }
  return true;
}

function openMissionModal() {
  document.getElementById('missionModal').style.display = 'block';
}

function closeMissionModal() {
  document.getElementById('missionModal').style.display = 'none';
}

function showTimeDetail() {
  alert('詳細時間資訊將在此顯示');
}
</script>

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

<!-- 外部 JS -->
<script src="js/auto-save-time.js"></script>
<script src="js/load-daily-tasks.js"></script>
<script src="js/mission.js"></script>
<script src="js/save-score.js"></script>
<script src="js/get-score.js"></script>

</body>
</html> 