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
  <title>搜尋遊戲</title>
  <link rel="stylesheet" href="css/game.css" />
  <link rel="stylesheet" href="css/main.css" />
  <link rel="stylesheet" href="css/mission.css" />
  <link rel="stylesheet" href="css/profile-modal.css" />
</head>
<body>

<!-- 黑色半透明背景 (彈窗遮罩) -->
<div id="modalOverlay" class="overlay" style="display:none;"></div>
<!-- 側邊欄遮罩 -->
<div id="overlay" class="overlay" onclick="toggleSidebar()"></div>

<!-- 側邊欄 -->
<div id="sidebar" class="sidebar">
  <a href="index.php" class="jelly-btn jelly-red">首頁</a>
  <a href="game-category.php" class="jelly-btn jelly-red">🎮 全部遊戲</a>
  <a href="friend.php" class="jelly-btn jelly-green">好友列表</a>
  <a href="Ranking_list.php" class="jelly-btn jelly-green">排行榜</a>
  <div class="btn-group">
    <div class="personal-history-group">
      <button class="jelly-btn jelly-yellow" id="personalHistoryBtn" type="button" onclick="togglePersonalHistoryMenu()">個人歷程 <span id="arrowIcon" style="font-size: 20px !important; margin-left: 10px !important; color: #333 !important; font-weight: bold !important; display: inline-block !important; visibility: visible !important; opacity: 1 !important; text-shadow: 1px 1px 2px rgba(0,0,0,0.3) !important;">▼</span></button>
      <div id="personalHistoryMenu" class="personal-history-menu" style="display:none;">
        <a href="personal-analysis.php" class="jelly-btn jelly-yellow sub-btn">分析圖表</a>
        <a href="history.php" class="jelly-btn jelly-yellow sub-btn">歷史紀錄</a>
      </div>
    </div>
    <a href="news.php" class="jelly-btn jelly-yellow">相關報導</a>
    <a href="us.php" class="jelly-btn jelly-yellow">關於我們</a>
  </div>
</div>

<!-- 頁首 -->
<header>
  <div id="menuButton" class="menu" onclick="toggleSidebar()">
    <img src="img/contents.png" alt="目錄" class="menu-icon">
    <span id="menuText" class="menu-text">目錄</span>
  </div>
  <form class="search-bar" action="game.php" method="GET" onsubmit="return validateSearch()">
    <input type="text" name="keyword" id="searchInput" placeholder="搜尋遊戲" />
  </form>
  <div class="user-icons">
    <a href="#" onclick="openMissionModal(event); return false;">
      <span class="notification-bell">🔔</span>
    </a>
    <a href="#" onclick="openProfileModal(event); return false;">
      <img src="<?php echo $avatar_url; ?>" alt="使用者" class="profile">
    </a>
  </div>
</header>

<!-- 狀態列 -->
<div class="status-bar">
  <div class="score">
    您的分數 <span id="scoreValue" style="color: red;">0</span> 💰
  </div>
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
  <div class="profile-cards" id="achievementCards">
    <!-- 成就卡片將在這裡動態生成 -->
  </div>
  <div class="profile-actions">
    <button class="profile-btn profile-manage" onclick="openAccountModal()"><span style="font-size:18px;">🖊️</span> 管理帳戶</button>
    <a href="logout.php" class="profile-btn profile-logout"><span style="font-size:18px;">[→]</span> 登出</a>
  </div>
</div>

<!-- 新版個人資訊彈窗 -->
<div id="accountModal" class="profile-modal" style="display:none;">
  <span class="close" onclick="closeAccountModal()">✕</span>
  <div style="display: flex; flex-direction: column; align-items: center; gap: 12px; margin-bottom: 18px;">
    <img src="<?php echo $avatar_url; ?>" alt="頭像" class="profile-avatar" style="width: 90px; height: 90px;" />
    <div style="background: #97f55c; color: #222; font-weight: bold; font-size: 22px; border-radius: 20px; padding: 8px 28px; margin-top: 8px;">個人資料</div>
  </div>
  <form id="accountEditForm" method="POST" action="update_account.php" style="width: 100%; max-width: 320px; margin: 0 auto;">
    <div style="margin-bottom: 18px; display: flex; align-items: center; gap: 0;">
      <label style="font-size: 20px; color: #222; min-width: 60px;">名字：</label>
      <div style="flex:1; display: flex; align-items: center; gap: 0;">
        <input type="text" name="name" id="editName" value="<?php echo htmlspecialchars($name); ?>" style="font-size: 20px; border: none; background: transparent; border-bottom: 1.5px solid #bbb; width: 100%; outline: none; text-align: left;" required readonly />
        <div style="display: flex; align-items: center; min-width: 60px; justify-content: flex-end;">
          <span style="font-size: 20px; color: #888; cursor:pointer;" onclick="enableEdit('editName')">🖊️</span>
        </div>
      </div>
    </div>
    <div style="margin-bottom: 28px; display: flex; align-items: center; gap: 0;">
      <label style="font-size: 20px; color: #222; min-width: 60px;">密碼：</label>
      <div style="flex:1; display: flex; align-items: center; gap: 0;">
        <input type="password" name="password" id="editPassword" value="" placeholder="請輸入新密碼（可選）" style="font-size: 20px; border: none; background: transparent; border-bottom: 1.5px solid #bbb; width: 100%; outline: none; text-align: left;" readonly />
        <div style="display: flex; align-items: center; min-width: 60px; justify-content: flex-end; gap: 6px;">
          <span id="togglePwd" style="font-size: 20px; color: #888; cursor:pointer;" onclick="togglePassword()">👁️</span>
          <span style="font-size: 20px; color: #888; cursor:pointer;" onclick="enableEdit('editPassword')">🖊️</span>
        </div>
      </div>
    </div>
    <button type="submit" style="width: 100%; background: #97f55c; color: #222; font-size: 22px; font-weight: bold; border: none; border-radius: 20px; padding: 10px 0; margin-top: 10px; cursor: pointer;">儲存</button>
  </form>
</div>


<!-- 搜尋結果區塊 -->
<div class="section">
  <h2>搜尋結果</h2>
  <div class="game-grid" id="gameGrid">
    <div class="game-block">
      <div class="game-item" data-title="看字選色">
        <a href="text-color.php"><img src="img/text_color111.png" alt="看字選色"></a>
      </div>
      <div class="game-title">看字選色</div>
    </div>
    <div class="game-block">
      <div class="game-item" data-title="接金蛋">
        <a href="Catch-Egg Game.php"><img src="img/egg1.png" alt="接金蛋"></a>
      </div>
      <div class="game-title">接金蛋</div>
    </div>
    <div class="game-block">
      <div class="game-item" data-title="2048">
        <a href="2048ht.php"><img src="img/game_20481.png" alt="2048"></a>
      </div>
      <div class="game-title">2048</div>
    </div>
    <div class="game-block">
      <div class="game-item" data-title="翻牌對對樂">
        <a href="Memory-Game.php"><img src="img/card1.png" alt="翻牌對對樂"></a>
      </div>
      <div class="game-title">翻牌對對樂</div>
    </div>
    <div class="game-block">
      <div class="game-item" data-title="算菜錢">
        <a href="Vegetable-Cost.php"><img src="img/vegetable1.png" alt="算菜錢"></a>
      </div>
      <div class="game-title">算菜錢</div>
    </div>
    <div class="game-block">
      <div class="game-item" data-title="追蹤犯人">
        <a href="prisoner.php"><img src="img/prisoner1.png" alt="追蹤犯人"></a>
      </div>
      <div class="game-title">追蹤犯人</div>
    </div>
    <div class="game-block">
      <div class="game-item" data-title="節奏遊戲">
        <a href="rhythm_game.php"><img src="img/rhythm1.png" alt="節奏遊戲"></a>
      </div>
      <div class="game-title">節奏遊戲</div>
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

  function togglePersonalHistoryMenu() {
    const menu = document.getElementById('personalHistoryMenu');
    const arrowIcon = document.getElementById('arrowIcon');
    const isVisible = menu.style.display === 'block';
    
    if (isVisible) {
      menu.style.display = 'none';
      arrowIcon.textContent = '▼';
    } else {
      menu.style.display = 'block';
      arrowIcon.textContent = '▲';
    }
  }

  // 搜尋欄驗證
  function validateSearch() {
    const input = document.getElementById('searchInput').value.trim();
    if (input === '') {
      alert('請輸入關鍵字');
      return false;
    }
    return true;
  }

  // 搜尋篩選功能（自動根據網址的 keyword 參數）
  window.onload = () => {
    const urlParams = new URLSearchParams(window.location.search);
    const keyword = urlParams.get('keyword')?.toLowerCase() || '';
    const searchInput = document.getElementById('searchInput');
    const gameBlocks = document.querySelectorAll('.game-block');

    searchInput.value = keyword;

    gameBlocks.forEach(block => {
      const item = block.querySelector('.game-item');
      const title = item.dataset.title.toLowerCase();
      block.style.display = title.includes(keyword) ? 'block' : 'none';
    });

    // 即時搜尋功能
    searchInput.addEventListener('input', () => {
      const value = searchInput.value.toLowerCase();
      gameBlocks.forEach(block => {
        const item = block.querySelector('.game-item');
        const title = item.dataset.title.toLowerCase();
        block.style.display = title.includes(value) ? 'block' : 'none';
      });
    });
  };

  function openMissionModal() {
    const modal = document.getElementById('missionModal');
    const overlay = document.getElementById('modalOverlay');
    
    modal.style.display = 'flex';
    overlay.style.display = 'block';
    
    // 立即顯示彈窗，然後觸發動畫
    setTimeout(() => {
      modal.classList.add('show');
    }, 10);
    
    // 立即載入任務，不等待動畫
    console.log("打開每日任務彈窗，重新載入任務");
    loadDailyTasks();
  }
  
  function closeMissionModal() {
    const modal = document.getElementById('missionModal');
    const overlay = document.getElementById('modalOverlay');
    
    modal.classList.remove('show');
    
    // 等待動畫完成後隱藏
    setTimeout(() => {
      modal.style.display = 'none';
      overlay.style.display = 'none';
    }, 150);
  }

  function openProfileModal() {
    document.getElementById('profileModal').style.display = 'flex';
    document.getElementById('modalOverlay').style.display = 'block';
    loadUserAchievements(); // 載入成就
  }
  function closeProfileModal() {
    document.getElementById('profileModal').style.display = 'none';
    document.getElementById('modalOverlay').style.display = 'none';
  }

  function closeAllModals() {
    closeProfileModal();
    closeMissionModal();
    closeAccountModal();
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

  function openAccountModal() {
    // 先關閉個人資訊彈窗
    document.getElementById('profileModal').style.display = 'none';
    // 再打開管理帳戶視窗
    document.getElementById('accountModal').style.display = 'flex';
    document.getElementById('modalOverlay').style.display = 'block';
  }
  var originalAccount = document.getElementById('editAccount') ? document.getElementById('editAccount').value : '';
  function closeAccountModal() {
    document.getElementById('accountModal').style.display = 'none';
    // 如果帳號欄位是空的就還原
    var accInput = document.getElementById('editAccount');
    if (accInput && accInput.value.trim() === '') {
      accInput.value = originalAccount;
      accInput.setAttribute('readonly', true);
    }
    // 只有當 profileModal 也關閉時才關掉遮罩
    if (!document.getElementById('profileModal') || document.getElementById('profileModal').style.display === 'none') {
      document.getElementById('modalOverlay').style.display = 'none';
    }
  }
</script>

<script>
function enableEdit(id) {
  var input = document.getElementById(id);
  if (id === 'editAccount') {
    originalAccount = input.value;
    input.value = '';
  }
  input.removeAttribute('readonly');
  input.focus();
}
</script>

<script>
function togglePassword() {
  var pwdInput = document.getElementById('editPassword');
  var eye = document.getElementById('togglePwd');
  if (pwdInput.type === 'password') {
    pwdInput.type = 'text';
    eye.textContent = '🙈';
  } else {
    pwdInput.type = 'password';
    eye.textContent = '👁️';
  }
}
</script>

<!-- 外部 JS -->
<script src="js/auto-save-time-fixed.js"></script>
<script src="js/load-daily-tasks.js"></script>
<script src="js/mission.js"></script>
<script src="js/save-score.js"></script>
<script src="js/get-score.js"></script>
<script src="js/achievements.js"></script>
<script src="js/avatar-upload.js"></script>
</body>
</html>
