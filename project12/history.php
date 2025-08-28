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
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>歷史紀錄</title>
  <link rel="stylesheet" href="css/main.css" />
  <link rel="stylesheet" href="css/mission.css" />
  <link rel="stylesheet" href="css/profile-modal.css" />
  <link rel="stylesheet" href="css/history.css" />
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

<header>
  <div id="menuButton" class="menu" onclick="toggleSidebar()">
    <img src="img/contents.png" alt="目錄" class="menu-icon" />
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
      <img src="<?php echo isset(
        $_SESSION['avatar_url']) && $_SESSION['avatar_url'] ? htmlspecialchars($_SESSION['avatar_url']) : 'img/big.jpg'; ?>" alt="使用者" class="profile" />
    </a>
  </div>
</header>

<!-- 你的每日任務彈窗保留不動 -->
<div id="missionModal" class="mission-modal" style="display:none;">
  <div class="modal-content">
    <span class="close" onclick="closeMissionModal()">✕</span>
    <h2>每日任務</h2>
    <div id="daily-tasks-container"></div>
  </div>
</div>

<!-- 新增的個人資訊彈窗 -->
<div id="profileModal" class="profile-modal" style="display:none;">
  <span class="close" onclick="closeProfileModal()">✕</span>
  <div class="profile-header">
    <div class="profile-account">
      帳號：<?php echo isset($_SESSION['account']) ? htmlspecialchars($_SESSION['account']) : '訪客'; ?>
    </div>
    <div class="profile-avatar-wrap">
      <img id="profileAvatarImg" src="<?php echo isset($_SESSION['avatar_url']) && $_SESSION['avatar_url'] ? htmlspecialchars($_SESSION['avatar_url']) : 'img/big.jpg'; ?>" alt="頭像" class="profile-avatar" />
      <span class="profile-avatar-edit" onclick="document.getElementById('avatarInput').click();">
        📷
      </span>
      <form id="avatarForm" action="upload_avatar.php" method="POST" enctype="multipart/form-data" style="display:none;">
        <input type="file" id="avatarInput" name="avatar" accept="image/*" onchange="previewAndUploadAvatar(event)" />
      </form>
    </div>
  </div>
  <div class="profile-greeting">
    <?php echo isset($_SESSION['name']) ? htmlspecialchars($_SESSION['name']) : '您好'; ?>，您好!
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
    <img src="<?php echo isset($_SESSION['avatar_url']) && $_SESSION['avatar_url'] ? htmlspecialchars($_SESSION['avatar_url']) : 'img/big.jpg'; ?>" alt="頭像" class="profile-avatar" style="width: 90px; height: 90px;" />
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

<div class="status-bar">
  <div class="score">
    您的分數 <span id="scoreValue" style="color: red;">0</span> 💰
  </div>
  <div class="time">
    已遊玩時間 <span id="timeValue">00:00:00</span>
    <button onclick="showTimeDetail()" class="time-icon-btn">⏱️</button>
  </div>
</div>

<!-- 玩家個人歷程區域 -->
<div class="section">
  <h2>🎮 我的遊戲歷程</h2>
  <div style="display: flex; flex-direction: column; gap: 20px; max-width: 800px; margin: 0 auto;">
    
    <!-- 個人成就牆 -->
    <div style="background: #f8f9fa; border-radius: 15px; padding: 25px;">
      <h3 style="margin: 0 0 20px 0; color: #333; font-size: 1.5em;">🏆 個人成就</h3>
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
        <div style="background: white; border-radius: 10px; padding: 15px; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
          <div id="totalGames" style="font-size: 2em; font-weight: bold; color: #007bff; margin: 10px 0;">載入中...</div>
          <div style="color: #6c757d; font-size: 0.9em;">玩過的遊戲種類</div>
        </div>
        <div style="background: white; border-radius: 10px; padding: 15px; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
          <div id="bestScore" style="font-size: 2em; font-weight: bold; color: #28a745; margin: 10px 0;">載入中...</div>
          <div style="color: #6c757d; font-size: 0.9em;">最高分記錄</div>
        </div>
        <div style="background: white; border-radius: 10px; padding: 15px; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
          <div id="loginStreak" style="font-size: 2em; font-weight: bold; color: #ffc107; margin: 10px 0;">載入中...</div>
          <div style="color: #6c757d; font-size: 0.9em;">連續登入天數</div>
        </div>
      </div>
    </div>
    
    <!-- 遊戲偏好分析 -->
    <div style="background: #f8f9fa; border-radius: 15px; padding: 25px;">
      <h3 style="margin: 0 0 20px 0; color: #333; font-size: 1.5em;">🎯 我的遊戲偏好</h3>
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px;">
        <div style="background: white; border-radius: 10px; padding: 20px; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
          <div id="favoriteGame" style="font-size: 1.5em; font-weight: bold; color: #007bff; margin: 10px 0;">載入中...</div>
          <div style="color: #6c757d; font-size: 0.9em;">最常玩的遊戲</div>
        </div>
        <div style="background: white; border-radius: 10px; padding: 20px; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
          <div id="totalPlayTime" style="font-size: 1.5em; font-weight: bold; color: #28a745; margin: 10px 0;">載入中...</div>
          <div style="color: #6c757d; font-size: 0.9em;">總遊玩時間</div>
        </div>
        <div style="background: white; border-radius: 10px; padding: 20px; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
          <div id="bestTime" style="font-size: 1.5em; font-weight: bold; color: #ffc107; margin: 10px 0;">載入中...</div>
          <div style="color: #6c757d; font-size: 0.9em;">最佳表現遊戲</div>
        </div>
      </div>
    </div>
    
    <!-- 趣味數據 -->
    <div style="background: #f8f9fa; border-radius: 15px; padding: 25px;">
      <h3 style="margin: 0 0 20px 0; color: #333; font-size: 1.5em;">🎪 趣味數據</h3>
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
        <div style="background: white; border-radius: 10px; padding: 15px; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
          <div id="gameStyle" style="font-size: 1.3em; font-weight: bold; color: #6f42c1; margin: 10px 0;">載入中...</div>
          <div style="color: #6c757d; font-size: 0.9em;">遊戲風格標籤</div>
        </div>
        <div style="background: white; border-radius: 10px; padding: 15px; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
          <div id="achievementCount" style="font-size: 1.3em; font-weight: bold; color: #fd7e14; margin: 10px 0;">載入中...</div>
          <div style="color: #6c757d; font-size: 0.9em;">獲得成就數量</div>
        </div>
        <div style="background: white; border-radius: 10px; padding: 15px; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
          <div id="improvementRate" style="font-size: 1.3em; font-weight: bold; color: #20c997; margin: 10px 0;">載入中...</div>
          <div style="color: #6c757d; font-size: 0.9em;">進步幅度</div>
        </div>
      </div>
    </div>
    
    <!-- 本月精彩回顧 -->
    <div style="background: white; border-radius: 15px; padding: 25px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
      <h3 style="margin: 0 0 20px 0; color: #333; font-size: 1.5em;">📅 本月精彩回顧</h3>
      
      <!-- 類別篩選 -->
      <div style="text-align: center; margin-bottom: 20px;">
        <label for="categoryFilter" style="font-weight: bold; color: #333; margin-right: 10px;">選擇類別：</label>
        <select id="categoryFilter" style="padding: 8px 12px; border: 2px solid #007bff; border-radius: 5px; font-size: 14px; background: white; cursor: pointer;" onchange="loadMonthlyStats()">
          <option value="all">全部類別</option>
          <option value="reaction">反應力</option>
          <option value="memory">記憶力</option>
          <option value="logic">邏輯力</option>
        </select>
      </div>
      
      <div id="monthlyRecordsLoading" style="text-align: center; color: #666; padding: 20px;">
        載入中...
      </div>
      <div id="monthlyRecordsTable" style="display: none;">
        <div id="monthlyRecordsBody">
          <!-- 動態載入資料 -->
        </div>
      </div>
      <div id="monthlyRecordsEmpty" style="text-align:center; margin-top:15px; color:#888; font-size: 14px; display: none;">
        本月尚無遊玩記錄
      </div>
    </div>
  </div>
</div>
    

    

  </div>
</div>

<script>
function togglePersonalHistoryMenu() {
  const menu = document.getElementById('personalHistoryMenu');
  menu.style.display = (menu.style.display === 'none' || menu.style.display === '') ? 'block' : 'none';
}



// 載入玩家個人歷程數據
function loadMonthlyStats() {
  // 獲取選擇的類別
  const categoryFilter = document.getElementById('categoryFilter');
  const selectedCategory = categoryFilter ? categoryFilter.value : 'all';
  
  // 顯示載入狀態
  document.getElementById('totalGames').textContent = '載入中...';
  document.getElementById('bestScore').textContent = '載入中...';
  document.getElementById('loginStreak').textContent = '載入中...';
  document.getElementById('favoriteGame').textContent = '載入中...';
  document.getElementById('totalPlayTime').textContent = '載入中...';
  document.getElementById('bestTime').textContent = '載入中...';
  document.getElementById('gameStyle').textContent = '載入中...';
  document.getElementById('achievementCount').textContent = '載入中...';
  document.getElementById('improvementRate').textContent = '載入中...';
  document.getElementById('monthlyRecordsLoading').style.display = 'block';
  document.getElementById('monthlyRecordsTable').style.display = 'none';
  document.getElementById('monthlyRecordsEmpty').style.display = 'none';
  
  // 構建請求URL，包含類別參數
  const url = `get_monthly_stats.php?category=${selectedCategory}`;
  
  fetch(url)
    .then(response => response.json())
    .then(data => {
      console.log('收到數據:', data);
      if (data.success) {
        // 更新個人成就數據
        document.getElementById('totalGames').textContent = data.game_types_count || '0';
        document.getElementById('bestScore').textContent = data.best_score || '0';
        document.getElementById('loginStreak').textContent = data.login_streak || '0';
        
        // 更新遊戲偏好數據
        document.getElementById('favoriteGame').textContent = data.favorite_game || '無數據';
        document.getElementById('totalPlayTime').textContent = data.total_playtime || '00:00';
        document.getElementById('bestTime').textContent = data.best_game || '無數據';
        
        // 更新趣味數據
        document.getElementById('gameStyle').textContent = data.game_style || '無數據';
        document.getElementById('achievementCount').textContent = data.achievement_count || '0';
        document.getElementById('improvementRate').textContent = data.improvement_rate || '0%';
        
        // 更新每日記錄表格
        const loadingDiv = document.getElementById('monthlyRecordsLoading');
        const table = document.getElementById('monthlyRecordsTable');
        const tbody = document.getElementById('monthlyRecordsBody');
        const emptyDiv = document.getElementById('monthlyRecordsEmpty');
        
        loadingDiv.style.display = 'none';
        
        if (data.daily_records.length > 0) {
          table.style.display = 'table';
          tbody.innerHTML = '';
          
          data.daily_records.forEach(record => {
            const row = document.createElement('tr');
            
            // 處理遊戲類型顯示
            let gamesDisplay = '❌ 無遊玩';
            if (record.seconds > 0) {
              // 獲取選擇的類別
              const categoryFilter = document.getElementById('categoryFilter');
              const selectedCategory = categoryFilter ? categoryFilter.value : 'all';
              
              if (selectedCategory === 'all') {
                // 全部類別：顯示遊戲類型（記憶力、反應力等）
                if (record.game_types && record.game_types.length > 0) {
                  gamesDisplay = record.game_types.map(gameType => {
                    const gameIcons = {
                      '記憶力': '🧠',
                      '反應力': '⚡',
                      '邏輯力': '🧩',
                      '算術邏輯': '🧮'
                    };
                    const icon = gameIcons[gameType] || '🎮';
                    return `${icon} ${gameType}`;
                  }).join('<br>');
                }
              } else {
                // 特定類別：顯示具體遊戲名稱
                if (record.game_names && record.game_names.length > 0) {
                  gamesDisplay = record.game_names.map(gameName => {
                    const gameIcons = {
                      '看字選色遊戲': '🎨',
                      '接金蛋': '🥚',
                      '算菜錢': '🧮',
                      '2048': '🔢',
                      '翻牌對對樂': '🃏',
                      '記憶力': '🧠',
                      '節奏遊戲': '🎵',
                      '追蹤犯人遊戲': '🔍',
                      '圖片線索問答': '❓',
                      '算菜錢遊戲': '🥬',
                      '邏輯力': '🧩'
                    };
                    const icon = gameIcons[gameName] || '🎮';
                    return `${icon} ${gameName}`;
                  }).join('<br>');
                }
              }
            }
            
            row.innerHTML = `
              <td style="padding: 12px; border: 1px solid #ccc;">${record.date}</td>
              <td style="padding: 12px; border: 1px solid #ccc;">${record.playtime}</td>
              <td style="padding: 12px; border: 1px solid #ccc;">${record.game_count || 0}</td>
              <td style="padding: 12px; border: 1px solid #ccc;">${gamesDisplay}</td>
            `;
            tbody.appendChild(row);
          });
        } else {
          emptyDiv.style.display = 'block';
        }
      } else {
        // 顯示錯誤
        document.getElementById('playCount').textContent = '載入失敗';
        document.getElementById('totalTime').textContent = '載入失敗';
        document.getElementById('playDays').textContent = '載入失敗';
        document.getElementById('avgTime').textContent = '載入失敗';
        document.getElementById('monthlyRecordsLoading').textContent = '載入失敗：' + (data.message || '未知錯誤');
      }
    })
    .catch(error => {
      console.error('載入失敗:', error);
      document.getElementById('playCount').textContent = '載入失敗';
      document.getElementById('totalTime').textContent = '載入失敗';
      document.getElementById('playDays').textContent = '載入失敗';
      document.getElementById('avgTime').textContent = '載入失敗';
      document.getElementById('monthlyRecordsLoading').textContent = '網路錯誤：' + error.message;
    });
}

// 頁面載入時自動載入本月統計
document.addEventListener('DOMContentLoaded', function() {
  loadMonthlyStats();
});
let sidebarOpen = false;
function toggleSidebar() {
  const sidebar = document.getElementById("sidebar");
  const menuText = document.getElementById("menuText");
  const overlay = document.getElementById("overlay");
  if (!sidebarOpen) {
    sidebar.style.left = "0";
    menuText.style.display = "none";
    if(overlay) overlay.style.display = "block";
  } else {
    sidebar.style.left = "-300px";
    menuText.style.display = "inline";
    if(overlay) overlay.style.display = "none";
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
function openProfileModal() {
  document.getElementById('profileModal').style.display = 'flex';
  document.getElementById('modalOverlay').style.display = 'block';
  loadUserAchievements(); // 載入成就
}
function closeProfileModal() {
  document.getElementById('profileModal').style.display = 'none';
  document.getElementById('modalOverlay').style.display = 'none';
}
function openMissionModal() {
  document.getElementById('missionModal').style.display = 'flex';
  
  // 檢查今天是否已經載入過任務
  const today = new Date().toDateString();
  const lastLoadDate = localStorage.getItem('missionLoadDate');
  const hasLoadedToday = localStorage.getItem('missionLoadedToday') === 'true';
  
  // 如果是新的一天，重置狀態
  if (lastLoadDate !== today) {
    localStorage.setItem('missionLoadDate', today);
    localStorage.setItem('missionLoadedToday', 'false');
  }
  
  // 如果今天還沒載入過任務，才載入
  if (!hasLoadedToday) {
    loadDailyTasks(); // 載入每日任務
    localStorage.setItem('missionLoadedToday', 'true');
  }
}
function closeMissionModal() {
  document.getElementById('missionModal').style.display = 'none';
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
// showTimeDetail 函數已在 auto-save-time.js 中定義

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

  <script src="js/auto-save-time.js"></script>
  <script src="js/load-daily-tasks.js"></script>
  <script src="js/mission.js"></script>
  <script src="js/save-score.js"></script>
  <script src="js/get-score.js"></script>
  <script src="js/achievements.js"></script>
<script src="js/avatar-upload.js"></script>
  
  <script>
    // 頁面載入時初始化
    document.addEventListener('DOMContentLoaded', function() {
      // 載入玩家個人歷程數據
      loadMonthlyStats();
      
      // 載入成就數據
      if (typeof loadUserAchievements === 'function') {
        loadUserAchievements();
      }
    });
  </script>
</body>
</html> 