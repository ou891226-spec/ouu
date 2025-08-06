<?php
require_once 'check_login.php';
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>各項能力分析</title>
  <link rel="stylesheet" href="css/main.css" />     <!-- 首頁樣式 -->
  <link rel="stylesheet" href="css/an.css" />        <!-- 能力分析專用樣式 -->
  <link rel="stylesheet" href="css/mission.css" />
  <link rel="stylesheet" href="css/profile-modal.css" />
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<!-- 側邊欄 -->
<div id="sidebar" class="sidebar">
  <a href="game-category.php" class="jelly-btn jelly-red">全部遊戲</a>
  <a href="game-categories.php" class="jelly-btn jelly-red">遊戲分類</a>
  <a href="friend.php" class="jelly-btn jelly-green">好友列表</a>
  <a href="Ranking_list.php" class="jelly-btn jelly-green">排行榜</a>
  <div class="btn-group">
    <div class="personal-history-group">
      <button class="jelly-btn jelly-yellow" id="personalHistoryBtn" type="button" onclick="togglePersonalHistoryMenu()">個人歷程</button>
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
    <img src="img/contents.png" alt="目錄" class="menu-icon" />
    <span id="menuText" class="menu-text">目錄</span>
  </div>

  <form class="search-bar" action="game.php" method="GET" onsubmit="return validateSearch()">
    <input type="text" name="keyword" id="searchInput" placeholder="搜尋遊戲" />
  </form>

  <span class="notification-bell" onclick="openMissionModal()">🔔</span>

  <a href="#" onclick="openProfileModal();return false;">
    <img src="<?php echo isset($avatar_url) ? $avatar_url : 'img/big.jpg'; ?>" alt="使用者" class="profile">
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


<!-- 能力分析與成就/統計整合區塊 -->
<div class="analysis-section">
  <h2>各項能力分析</h2>

  <div class="radar-chart-container">
    <canvas id="abilityRadarChart"></canvas>
  </div>

  <div class="dropdown-area">
    <select onchange="showAchievement(this.value)">
      <option disabled selected>請選擇成就</option>
      <option value="A">破百得分者</option>
      <option value="B">連續答對10題</option>
      <option value="C">每日挑戰完成者</option>
    </select>

    <select onchange="showStatistic(this.value)">
      <option disabled selected>請選擇統計</option>
      <option value="1">本週總分</option>
      <option value="2">本月遊玩次數</option>
      <option value="3">平均答對率</option>
    </select>

    <div id="resultText" style="margin-top: 15px; font-size: 16px;"></div>
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

<!-- 遮罩 -->
<div class="overlay" id="overlay" onclick="closeSidebar()"></div>
<div id="modalOverlay" class="overlay" style="display:none;" onclick="closeAllModals()"></div>
<!-- 側邊欄遮罩 -->
<div id="overlay" class="overlay" onclick="toggleSidebar()"></div>

<!-- Chart.js -->
<script>
  const ctx = document.getElementById('abilityRadarChart').getContext('2d');
  new Chart(ctx, {
    type: 'radar',
    data: {
      labels: ['反應力', '記憶力', '算術邏輯'],
      datasets: [{
        label: '能力分析',
        data: [85, 90, 75],
        backgroundColor: 'rgba(255, 159, 64, 0.2)',
        borderColor: 'rgba(255, 159, 64, 1)',
        borderWidth: 1
      }]
    },
    options: {
      scales: {
        r: {
          suggestedMin: 0,
          suggestedMax: 100
        }
      }
    }
  });

  function showAchievement(value) {
    const text = {
      A: "恭喜你成為破百高手！",
      B: "反應超快！你連續答對10題！",
      C: "每日任務達成，持之以恆！"
    };
    document.getElementById('resultText').textContent = text[value];
  }

  function showStatistic(value) {
    const text = {
      1: "本週總分：356 分",
      2: "本月遊玩次數：42 次",
      3: "平均答對率：88%"
    };
    document.getElementById('resultText').textContent = text[value];
  }
</script>

<!-- 側邊欄控制 -->
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

  document.addEventListener('click', function(event) {
    const sidebar = document.getElementById("sidebar");
    const menuButton = document.getElementById("menuButton");
    const overlay = document.getElementById("overlay");

    if (!sidebar.contains(event.target) && !menuButton.contains(event.target)) {
      sidebar.style.left = "-300px";
      document.getElementById("menuText").style.display = "inline";
      overlay.style.display = "none";
      sidebarOpen = false;
    }
  });

  function closeSidebar() {
    document.getElementById("sidebar").style.left = "-300px";
    document.getElementById("menuText").style.display = "inline";
    document.getElementById("overlay").style.display = "none";
    sidebarOpen = false;
  }

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
<script src="js/achievements.js"></script>
<script src="js/avatar-upload.js"></script>

<!-- 個人資訊彈窗 -->
<div id="profileModal" class="profile-modal" style="display:none;">
  <span class="close" onclick="closeProfileModal()">✕</span>
  <div class="profile-header">
    <div class="profile-account">
      帳號：<?php echo isset($account) ? htmlspecialchars($account) : '使用者'; ?>
    </div>
    <div class="profile-avatar-wrap">
      <img id="profileAvatarImg" src="<?php echo isset($avatar_url) ? $avatar_url : 'img/big.jpg'; ?>" alt="頭像" class="profile-avatar" />
      <span class="profile-avatar-edit" onclick="document.getElementById('avatarInput').click();">
        📷
      </span>
      <form id="avatarForm" action="upload_avatar.php" method="POST" enctype="multipart/form-data" style="display:none;">
        <input type="file" id="avatarInput" name="avatar" accept="image/*" onchange="previewAndUploadAvatar(event)" />
      </form>
    </div>
  </div>
  <div class="profile-greeting">
    <?php echo isset($name) ? htmlspecialchars($name) : '使用者'; ?>，您好!
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
    <img src="<?php echo isset($avatar_url) ? $avatar_url : 'img/big.jpg'; ?>" alt="頭像" class="profile-avatar" style="width: 90px; height: 90px;" />
    <div style="background: #97f55c; color: #222; font-weight: bold; font-size: 22px; border-radius: 20px; padding: 8px 28px; margin-top: 8px;">個人資料</div>
  </div>
  <form id="accountEditForm" method="POST" action="update_account.php" style="width: 100%; max-width: 320px; margin: 0 auto;">
    <div style="margin-bottom: 18px; display: flex; align-items: center; gap: 0;">
      <label style="font-size: 20px; color: #222; min-width: 60px;">名字：</label>
      <div style="flex:1; display: flex; align-items: center; gap: 0;">
        <input type="text" name="name" id="editName" value="<?php echo isset($name) ? htmlspecialchars($name) : '使用者'; ?>" style="font-size: 20px; border: none; background: transparent; border-bottom: 1.5px solid #bbb; width: 100%; outline: none; text-align: left;" required readonly />
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

<script>
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

function enableEdit(id) {
  var input = document.getElementById(id);
  if (id === 'editAccount') {
    originalAccount = input.value;
    input.value = '';
  }
  input.removeAttribute('readonly');
  input.focus();
}

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

</body>
</html>