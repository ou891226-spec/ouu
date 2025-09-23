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
  <title>各項能力分析</title>
  <link rel="stylesheet" href="css/main.css" />     <!-- 首頁樣式 -->
  <link rel="stylesheet" href="css/an.css" />        <!-- 能力分析專用樣式 -->
  <link rel="stylesheet" href="css/game-category-tabs.css" />  <!-- 遊戲分類標籤樣式 -->
  <link rel="stylesheet" href="css/mission.css" />
  <link rel="stylesheet" href="css/profile-modal.css" />
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<!-- 側邊欄 -->
<div id="sidebar" class="sidebar">
  <a href="index.php" class="jelly-btn jelly-red">首頁</a>
  <a href="game-category.php" class="jelly-btn jelly-red">🎮 全部遊戲</a>
  <a href="friend.php" class="jelly-btn jelly-green">好友列表</a>
  <a href="Ranking_list.php" class="jelly-btn jelly-green">排行榜</a>
  <div class="btn-group">
    <a href="personal-analysis.php" class="jelly-btn jelly-yellow">個人分析</a>
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

  <div class="user-icons">
    <a href="#" onclick="openMissionModal(event); return false;">
      <span class="notification-bell">🔔</span>
    </a>
    <a href="#" onclick="openProfileModal(event); return false;">
      <img src="<?php echo isset($avatar_url) ? $avatar_url : 'img/big.jpg'; ?>" alt="使用者" class="profile">
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


<!-- 能力分析區塊 -->
<div class="analysis-section">
  <h2>各項能力分析</h2>

  <!-- 分析功能標籤 -->
  <div class="category-tabs" style="display: flex !important; justify-content: center !important; gap: 8px !important; margin: 30px 0 !important; flex-wrap: nowrap !important; padding: 0 10px !important;">
    <button class="category-tab active" onclick="showAnalysisSection('radar')" id="radarTab" style="flex: 1; min-width: 0; padding: 15px 12px; font-size: 22px; font-weight: bold; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: flex; align-items: center; justify-content: center; text-align: center;">
      <span class="tab-icon" style="font-size: 24px;">🎯</span>
      能力雷達圖
    </button>
    <button class="category-tab" onclick="showAnalysisSection('trend')" id="trendTab" style="flex: 1; min-width: 0; padding: 15px 12px; font-size: 22px; font-weight: bold; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: flex; align-items: center; justify-content: center; text-align: center;">
      <span class="tab-icon" style="font-size: 24px;">📈</span>
      能力趨勢變化
    </button>
         <button class="category-tab" onclick="showAnalysisSection('stats')" id="statsTab" style="flex: 1; min-width: 0; padding: 15px 12px; font-size: 22px; font-weight: bold; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: flex; align-items: center; justify-content: center; text-align: center;">
           <span class="tab-icon" style="font-size: 24px;">📊</span>
           詳細統計
         </button>
    <button class="category-tab" onclick="showAnalysisSection('report')" id="reportTab" style="flex: 1; min-width: 0; padding: 15px 12px; font-size: 22px; font-weight: bold; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: flex; align-items: center; justify-content: center; text-align: center;">
      <span class="tab-icon" style="font-size: 24px;">📋</span>
      能力分析報告
    </button>
  </div>
  
  <!-- 能力雷達圖內容 -->
  <div id="radar-section" class="category-games active">
    <div class="radar-chart-container">
      <canvas id="abilityRadarChart"></canvas>
    </div>
  </div>
  
  <!-- 能力趨勢圖內容 -->
  <div id="trend-section" class="category-games" style="display: none;">
    <div class="trend-chart-container">
      <h3>能力趨勢變化（最近12個月）</h3>
      <canvas id="abilityTrendChart"></canvas>
    </div>
  </div>
  
  <!-- 詳細統計內容 -->
  <div id="stats-section" class="category-games" style="display: none;">
    <div class="detailed-stats" style="text-align: center !important;">
      <h3 style="text-align: center !important;">詳細統計</h3>
      <div class="stats-grid" style="text-align: center !important;">
        <div class="stat-item" style="text-align: center !important;">
          <span class="stat-label">反應力遊戲總次數：</span>
          <span class="stat-value" id="reactionGames">0</span>
        </div>
        <div class="stat-item" style="text-align: center !important;">
          <span class="stat-label">記憶力遊戲總次數：</span>
          <span class="stat-value" id="memoryGames">0</span>
        </div>
        <div class="stat-item" style="text-align: center !important;">
          <span class="stat-label">邏輯力遊戲總次數：</span>
          <span class="stat-value" id="logicGames">0</span>
        </div>
        <div class="stat-item" style="text-align: center !important;">
          <span class="stat-label">反應力平均分數：</span>
          <span class="stat-value" id="reactionAvg">0</span>
        </div>
        <div class="stat-item" style="text-align: center !important;">
          <span class="stat-label">記憶力平均分數：</span>
          <span class="stat-value" id="memoryAvg">0</span>
        </div>
        <div class="stat-item" style="text-align: center !important;">
          <span class="stat-label">邏輯力平均分數：</span>
          <span class="stat-value" id="logicAvg">0</span>
        </div>
      </div>
    </div>
  </div>
  
  <!-- 分析報告內容 -->
  <div id="report-section" class="category-games" style="display: none;">
    <div class="analysis-report">
      <h3>能力分析報告</h3>
      <div class="report-content">
        <div class="player-type" id="playerType"></div>
        <div class="description" id="description"></div>
        <div class="suggestions" id="suggestions"></div>
      </div>
    </div>
  </div>
</div>

<!-- 彈窗 -->
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
let radarChart = null;
let trendChart = null;

// 載入能力分析數據
function loadAbilityAnalysis() {
  fetch('get_ability_analysis.php')
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        createRadarChart(data.data);
        updateAnalysisReport(data.data);
        updateDetailedStats(data.data);
      } else {
        console.error('載入分析數據失敗:', data.message);
        // 顯示錯誤信息
        document.getElementById('abilityRadarChart').parentElement.innerHTML = 
          '<div style="text-align: center; padding: 20px; color: #666;">載入分析數據失敗</div>';
      }
    })
    .catch(error => {
      console.error('載入分析數據時發生錯誤:', error);
    });
}

// 載入能力趨勢數據
function loadAbilityTrend() {
  fetch('get_ability_trend.php')
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        createTrendChart(data.data);
      } else {
        console.error('載入趨勢數據失敗:', data.message);
        // 顯示錯誤信息
        document.getElementById('abilityTrendChart').parentElement.innerHTML = 
          '<div style="text-align: center; padding: 20px; color: #666;">載入趨勢數據失敗：' + data.message + '</div>';
      }
    })
    .catch(error => {
      console.error('載入趨勢數據時發生錯誤:', error);
      // 顯示錯誤信息
      document.getElementById('abilityTrendChart').parentElement.innerHTML = 
        '<div style="text-align: center; padding: 20px; color: #666;">載入趨勢數據時發生錯誤：' + error.message + '</div>';
    });
}

// 創建雷達圖
function createRadarChart(analysisData) {
  const ctx = document.getElementById('abilityRadarChart').getContext('2d');
  
  if (radarChart) {
    radarChart.destroy();
  }
  
  radarChart = new Chart(ctx, {
    type: 'radar',
    data: {
      labels: ['反應力', '記憶力', '算術邏輯力'],
      datasets: [{
        label: '能力強度',
        data: [
          analysisData.reaction,
          analysisData.memory,
          analysisData.logic
        ],
        backgroundColor: 'rgba(255, 159, 64, 0.2)',
        borderColor: 'rgba(255, 159, 64, 1)',
        borderWidth: 2,
        pointBackgroundColor: 'rgba(255, 159, 64, 1)',
        pointBorderColor: '#fff',
        pointHoverBackgroundColor: '#fff',
        pointHoverBorderColor: 'rgba(255, 159, 64, 1)'
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      scales: {
        r: {
          beginAtZero: true,
          max: 100,
          ticks: {
            stepSize: 20
          }
        }
      },
      plugins: {
        legend: {
          display: true,
          position: 'top'
        },
        tooltip: {
          callbacks: {
            label: function(context) {
              return context.label + ': ' + context.parsed.r + '%';
            }
          }
        }
      }
    }
  });
}

// 創建趨勢圖
function createTrendChart(trendData) {
  const ctx = document.getElementById('abilityTrendChart').getContext('2d');
  
  if (trendChart) {
    trendChart.destroy();
  }
  
  // 準備數據
  const labels = trendData.map(item => {
    const date = new Date(item.date + '-01'); // 添加日期部分以正確解析
    return `${date.getFullYear()}/${date.getMonth() + 1}`;
  });
  
  const reactionData = trendData.map(item => item.reaction);
  const memoryData = trendData.map(item => item.memory);
  const logicData = trendData.map(item => item.logic);
  
  trendChart = new Chart(ctx, {
    type: 'line',
    data: {
      labels: labels,
      datasets: [
        {
          label: '反應力',
          data: reactionData,
          borderColor: 'rgba(255, 99, 132, 1)',
          backgroundColor: 'rgba(255, 99, 132, 0.1)',
          borderWidth: 3,
          fill: false,
          tension: 0.2,
          pointBackgroundColor: 'rgba(255, 99, 132, 1)',
          pointBorderColor: '#fff',
          pointBorderWidth: 2
        },
        {
          label: '記憶力',
          data: memoryData,
          borderColor: 'rgba(54, 162, 235, 1)',
          backgroundColor: 'rgba(54, 162, 235, 0.1)',
          borderWidth: 3,
          fill: false,
          tension: 0.2,
          pointBackgroundColor: 'rgba(54, 162, 235, 1)',
          pointBorderColor: '#fff',
          pointBorderWidth: 2
        },
        {
          label: '邏輯力',
          data: logicData,
          borderColor: 'rgba(75, 192, 192, 1)',
          backgroundColor: 'rgba(75, 192, 192, 0.1)',
          borderWidth: 3,
          fill: false,
          tension: 0.2,
          pointBackgroundColor: 'rgba(75, 192, 192, 1)',
          pointBorderColor: '#fff',
          pointBorderWidth: 2
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      scales: {
        y: {
          beginAtZero: true,
          max: 100,
          min: 0,
          ticks: {
            stepSize: 20
          },
          grid: {
            drawBorder: false
          }
        },
        x: {
          ticks: {
            maxTicksLimit: 12
          },
          grid: {
            drawBorder: false
          }
        }
      },
      plugins: {
        legend: {
          display: true,
          position: 'top'
        },
        tooltip: {
          callbacks: {
            label: function(context) {
              return context.dataset.label + ': ' + context.parsed.y + ' 分';
            }
          }
        }
      },
      interaction: {
        intersect: false,
        mode: 'index'
      },
      elements: {
        point: {
          radius: 4,
          hoverRadius: 6
        },
        line: {
          tension: 0.2
        }
      },
      layout: {
        padding: {
          top: 30,
          bottom: 40,
          left: 30,
          right: 30
        }
      },
      clip: false
    }
  });
}

// 更新分析報告
function updateAnalysisReport(data) {
  const report = data.report;
  
  document.getElementById('playerType').innerHTML = 
    `<strong>玩家類型：</strong>${report.type}`;
  document.getElementById('description').innerHTML = 
    `<strong>分析說明：</strong>${report.description}`;
  
  if (report.suggestions && report.suggestions.length > 0) {
    const suggestionsHtml = report.suggestions.map(suggestion => 
      `<li>${suggestion}</li>`
    ).join('');
    document.getElementById('suggestions').innerHTML = 
      `<strong>改進建議：</strong><ul>${suggestionsHtml}</ul>`;
  } else {
    document.getElementById('suggestions').innerHTML = 
      '<strong>改進建議：</strong>您的能力發展得很好！';
  }
}

// 顯示分析區塊（類似遊戲分類的切換功能）
function showAnalysisSection(section) {
  // 隱藏所有內容區塊
  const sections = ['radar-section', 'trend-section', 'stats-section', 'report-section'];
  sections.forEach(id => {
    document.getElementById(id).style.display = 'none';
  });
  
  // 移除所有按鈕的active類別
  const tabs = ['radarTab', 'trendTab', 'statsTab', 'reportTab'];
  tabs.forEach(id => {
    document.getElementById(id).classList.remove('active');
  });
  
  // 顯示選中的區塊
  document.getElementById(section + '-section').style.display = 'block';
  document.getElementById(section + 'Tab').classList.add('active');
  
  // 如果是趨勢圖且還沒載入，載入它
  if (section === 'trend' && !trendChart) {
    loadAbilityTrend();
  }
}

// 更新詳細統計
function updateDetailedStats(data) {
  const reactionGames = document.getElementById('reactionGames');
  const memoryGames = document.getElementById('memoryGames');
  const logicGames = document.getElementById('logicGames');
  const reactionAvg = document.getElementById('reactionAvg');
  const memoryAvg = document.getElementById('memoryAvg');
  const logicAvg = document.getElementById('logicAvg');
  const detailedStats = document.getElementById('detailedStats');
  
  if (reactionGames) reactionGames.textContent = data.stats.reaction_games;
  if (memoryGames) memoryGames.textContent = data.stats.memory_games;
  if (logicGames) logicGames.textContent = data.stats.logic_games;
  if (reactionAvg) reactionAvg.textContent = data.stats.reaction_avg;
  if (memoryAvg) memoryAvg.textContent = data.stats.memory_avg;
  if (logicAvg) logicAvg.textContent = data.stats.logic_avg;
  
  if (detailedStats) detailedStats.style.display = 'block';
}

// 頁面載入時執行
document.addEventListener('DOMContentLoaded', function() {
  loadAbilityAnalysis();
  loadAbilityTrend();
});




  function openMissionModal() {
    const modal = document.getElementById('missionModal');
    const overlay = document.getElementById('modalOverlay');
    
    modal.style.display = 'flex';
    if (overlay) overlay.style.display = 'block';
    
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
      if (overlay) overlay.style.display = 'none';
    }, 150);
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
<script src="js/auto-save-time-fixed.js"></script>
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
// showTimeDetail 函數已在 auto-save-time-fixed.js 中定義

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

</body>
</html> 