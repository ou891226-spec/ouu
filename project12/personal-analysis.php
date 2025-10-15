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
  <a href="game-category.php" class="jelly-btn jelly-red">全部遊戲</a>
  <a href="friend.php" class="jelly-btn jelly-green">好友列表</a>
  <a href="Ranking_list.php" class="jelly-btn jelly-green">排行榜</a>
  <div class="btn-group">
    <a href="personal-analysis.php" class="jelly-btn jelly-yellow">個人分析</a>
    <a href="news.php" class="jelly-btn jelly-yellow">相關報導</a>
    <a href="us.php" class="jelly-btn jelly-yellow">關於我們</a>
  </div>
</div>

<!-- 功能選單 -->
<header>
  <div id="menuButton" class="menu" onclick="toggleSidebar()">
    <img src="img/contents.png" alt="功能選單" class="menu-icon" />
    <span id="menuText" class="menu-text">功能選單</span>
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
    
    <!-- 詳細統計內容 -->
    <div class="detailed-stats" style="text-align: center !important; margin-top: 30px;">
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
          <span class="stat-label">反應力平均時間：</span>
          <span class="stat-value" id="reactionAvg">0</span>
        </div>
        <div class="stat-item" style="text-align: center !important;">
          <span class="stat-label">記憶力平均時間：</span>
          <span class="stat-value" id="memoryAvg">0</span>
        </div>
        <div class="stat-item" style="text-align: center !important;">
          <span class="stat-label">邏輯力平均時間：</span>
          <span class="stat-value" id="logicAvg">0</span>
        </div>
      </div>
    </div>
  </div>
  
  <!-- 能力趨勢圖內容 -->
  <div id="trend-section" class="category-games" style="display: none;">
    <div class="trend-chart-container">
      <h3>能力趨勢變化（最近12個月）</h3>
      <canvas id="abilityTrendChart"></canvas>
    </div>
  </div>
  
  <!-- 分析報告內容 -->
  <div id="report-section" class="category-games" style="display: none;">
    <div class="analysis-report">
      <h3>能力分析報告</h3>
      
      <!-- AI分析按鈕 -->
      <div class="ai-analysis-controls" style="text-align: center; margin-bottom: 20px;">
        <button id="aiAnalysisBtn" class="ai-btn" onclick="generateAIAnalysis()" style="
          background: linear-gradient(45deg,rgb(212, 197, 158) 0%,rgb(245, 138, 89) 100%);
          color: white;
          border: none;
          padding: 12px 24px;
          border-radius: 25px;
          font-size: 16px;
          font-weight: bold;
          cursor: pointer;
          box-shadow: 0 4px 15px rgba(255, 107, 107, 0.4);
          transition: all 0.3s ease;
        ">
          🤖 生成AI智能分析
        </button>
        <div id="aiLoading" style="display: none; margin-top: 10px;">
          <span style="color: #ffffff; background-color:rgb(237, 174, 57); padding: 4px 8px; border-radius: 4px;">AI正在分析中...</span>
        </div>
      </div>
      
      <div class="report-content">
        <div class="player-type" id="playerType"></div>
        <div class="description" id="description"></div>
        <div class="suggestions" id="suggestions"></div>
        
        <!-- AI分析時間 -->
        <div id="aiIndicator" style="display: none; text-align: center; margin-top: 15px;">
          <div id="analysisTime" style="color: white; font-size: 16px; font-weight: bold; text-shadow: 1px 1px 3px rgba(0,0,0,0.3);"></div>
        </div>
      </div>
    </div>
    
    <!-- 分隔線 -->
    <div style="margin: 40px 0; border-top: 2px solid #e0e0e0;"></div>
    
    <!-- 歷史記錄區域 -->
    <div class="analysis-history">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h3 style="margin: 0; color: #333;">📜 分析歷史記錄</h3>
        <button onclick="toggleHistorySection()" id="toggleHistoryBtn" style="
          background: linear-gradient(45deg, #667eea, #764ba2);
          color: white;
          border: none;
          padding: 8px 16px;
          border-radius: 20px;
          font-size: 14px;
          font-weight: bold;
          cursor: pointer;
          transition: all 0.3s ease;
        ">
          <span id="toggleIcon">▼</span> 展開歷史記錄
        </button>
      </div>
      
      <div id="historyContainer" style="display: none;">
        <div id="historyLoading" style="text-align: center; padding: 40px; display: none;">
          <div style="color: #ffffff; background-color:rgb(237, 174, 57); padding: 8px 16px; border-radius: 4px; display: inline-block;">載入中...</div>
        </div>
        
        <div id="historyList" style="max-width: 900px; margin: 0 auto;">
          <!-- 歷史記錄將在這裡動態顯示 -->
        </div>
        
        <div id="loadMoreContainer" style="text-align: center; margin-top: 20px; display: none;">
          <button onclick="loadMoreHistory()" class="ai-btn" style="
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
          ">載入更多</button>
        </div>
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
  fetch('get_ability_analysis_baseline.php')
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        createRadarChart(data.data);
        // 移除自動顯示分析報告，只載入圖表和統計數據
        // updateAnalysisReport(data.data);
        updateDetailedStats(data.data);
        
        // 初始化空的報告區域
        initializeEmptyReport();
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
  
  // 使用基準時間效率數據，如果沒有數據則使用備用分數
  const reactionValue = analysisData.reaction > 0 ? analysisData.reaction : (analysisData.backup_scores?.reaction || 0);
  const memoryValue = analysisData.memory > 0 ? analysisData.memory : (analysisData.backup_scores?.memory || 0);
  const logicValue = analysisData.logic > 0 ? analysisData.logic : (analysisData.backup_scores?.logic || 0);
  
  radarChart = new Chart(ctx, {
    type: 'radar',
    data: {
      labels: ['反應力', '記憶力', '算術邏輯力'],
      datasets: [{
        label: '加權分數',
        data: [reactionValue, memoryValue, logicValue],
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
            stepSize: 20,
            callback: function(value) {
              return value + '%';
            }
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
              const label = context.dataset.label;
              const value = context.parsed.r.toFixed(1);
              const baselineData = analysisData.baseline_data;
              
              if (baselineData) {
                const category = ['reaction', 'memory', 'logic'][context.dataIndex];
                const baseline = baselineData[category];
                if (baseline && baseline.total_plays > 0) {
                  const timeWeight = (1 + ((baseline.baseline_time - baseline.avg_time) / baseline.baseline_time)).toFixed(2);
                  return `${label}: ${value}% (基準: ${baseline.baseline_time.toFixed(1)}s, 實際: ${baseline.avg_time.toFixed(1)}s, 時間加權: ${timeWeight})`;
                }
              }
              return `${label}: ${value}%`;
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

// 初始化空的報告區域
function initializeEmptyReport() {
  document.getElementById('playerType').innerHTML = 
    '<strong>玩家類型：</strong><span style="color: #ccc;">請點擊上方按鈕生成AI分析</span>';
  document.getElementById('description').innerHTML = 
    '<strong>分析說明：</strong><span style="color: #ccc;">等待AI分析...</span>';
  document.getElementById('suggestions').innerHTML = 
    '<strong>改進建議：</strong><span style="color: #ccc;">AI將為您提供個性化建議</span>';
  
  // 隱藏AI分析標識
  const aiIndicator = document.getElementById('aiIndicator');
  aiIndicator.style.display = 'none';
}

// 更新分析報告
function updateAnalysisReport(data) {
  const report = data.report || data; // 兼容不同的數據結構
  
  // 安全地獲取報告內容
  const playerType = report?.type || '智能分析玩家';
  const description = report?.description || '<span style="color: #ffffff; background-color: #2196F3; padding: 2px 6px; border-radius: 3px;">AI正在為您分析...</span>';
  const suggestions = report?.suggestions || [];
  const isAIEnhanced = report?.ai_enhanced || false;
  
  document.getElementById('playerType').innerHTML = 
    `<strong>玩家類型：</strong>${playerType}`;
  document.getElementById('description').innerHTML = 
    `<strong>分析說明：</strong>${description}`;
  
  if (suggestions && Array.isArray(suggestions) && suggestions.length > 0) {
    const suggestionsHtml = suggestions.map(suggestion => 
      `<li>${suggestion}</li>`
    ).join('');
    document.getElementById('suggestions').innerHTML = 
      `<strong>改進建議：</strong><ul>${suggestionsHtml}</ul>`;
  } else {
    // 如果 suggestions 是字符串，直接顯示
    const suggestionsText = typeof suggestions === 'string' ? suggestions : '您的能力發展得很好！';
    document.getElementById('suggestions').innerHTML = 
      `<strong>改進建議：</strong>${suggestionsText}`;
  }
  
  // 顯示分析時間
  const aiIndicator = document.getElementById('aiIndicator');
  const analysisTime = document.getElementById('analysisTime');
  if (isAIEnhanced) {
    aiIndicator.style.display = 'block';
    const now = new Date();
    const hours = String(now.getHours()).padStart(2, '0');
    const minutes = String(now.getMinutes()).padStart(2, '0');
    analysisTime.textContent = `分析時間：${now.getFullYear()}年${now.getMonth()+1}月${now.getDate()}日 ${hours}:${minutes}`;
  } else {
    aiIndicator.style.display = 'none';
  }
}

// 全局變量保存當前分析數據
let currentAnalysisData = null;

// 生成AI分析 - 使用 Google GenAI
async function generateAIAnalysis() {
  const btn = document.getElementById('aiAnalysisBtn');
  const loading = document.getElementById('aiLoading');
  
  // 顯示載入狀態
  btn.style.display = 'none';
  loading.style.display = 'block';
  
  try {
    // 首先獲取用戶數據
    const response = await fetch('get_ability_analysis.php');
    const userData = await response.json();
    
    if (!userData.success) {
      throw new Error('無法獲取用戶數據');
    }
    
    // 保存當前數據（用於後續保存）
    currentAnalysisData = userData.data;
    
    // 使用 Google GenAI 進行分析
    const aiResponse = await callGoogleGenAI(userData.data);
    
    // 顯示AI分析結果
    updateAnalysisReport(aiResponse);
    
    // 保存分析結果到數據庫
    await saveAnalysisToDatabase(userData.data, aiResponse);
    
    // 重新載入歷史記錄（如果已展開）
    if (isHistoryExpanded) {
      historyLoaded = false; // 重置載入狀態
      loadAnalysisHistory(true);
      historyLoaded = true;
    }
    
    // 顯示成功訊息
    loading.innerHTML = '<span style="color: #ffffff; background-color: #4CAF50; padding: 4px 8px; border-radius: 4px;">✅ AI分析完成並已保存</span>';
    
    // 3秒後隱藏載入訊息並恢復按鈕
    setTimeout(() => {
      loading.style.display = 'none';
      btn.style.display = 'inline-block';
      btn.innerHTML = '🔄 重新生成AI分析';
    }, 3000);
        
  } catch (error) {
    console.error('AI分析錯誤:', error);
    
    // 顯示錯誤訊息
    loading.innerHTML = '<span style="color: #ffffff; background-color: #f44336; padding: 4px 8px; border-radius: 4px;">❌ AI分析失敗: ' + error.message + '</span>';
    
    // 3秒後恢復按鈕
    setTimeout(() => {
      loading.style.display = 'none';
      btn.style.display = 'inline-block';
      btn.innerHTML = '🤖 生成AI智能分析';
    }, 3000);
  }
}

// 調用 Google GenAI
async function callGoogleGenAI(userData) {
  // 不再需要在前端建立 prompt，只傳遞用戶數據到後端
  try {
    // 將 userData (包含分數和統計資料) 傳遞給後端函式
    return await callGenAIThroughBackend(userData); 
    
  } catch (error) {
    throw new Error('AI 分析服務調用失敗: ' + error.message);
  }
}

// 調用 Node.js 後端服務
async function callGenAIThroughBackend(userData) {
    // 部署到 Azure 時，請替換為 Node.js 服務的實際 URL
    // const NODE_SERVER_URL = 'http://localhost:3001/api/ai/analysis'; 
    const NODE_SERVER_URL = 'https://smartfun-nodejs-ai-us-bfhxe7gsd9gbaehz.southeastasia-01.azurewebsites.net/api/ai/analysis';
  
    try {
        const response = await fetch(NODE_SERVER_URL, {
            method: 'POST',
            // 處理跨域請求
            mode: 'cors', 
            headers: {
                'Content-Type': 'application/json',
            },
            // 將用戶數據作為 JSON 傳送到 Node.js 後端
            body: JSON.stringify(userData) 
        });
        
        // 檢查 HTTP 狀態碼
        if (!response.ok) {
            const errorText = await response.text();
            throw new Error(`Node.js 後端請求失敗: ${response.status} - ${errorText}`);
        }
        
        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.message || 'Node.js 後端分析服務錯誤');
        }
        
        return result.report; 
        
    } catch (error) {
        throw new Error('呼叫 Node.js 後端失敗: ' + error.message);
    }
}

// 保存分析結果到數據庫
async function saveAnalysisToDatabase(userData, aiResponse) {
  try {
    const dataToSave = {
      reaction: userData.reaction,
      memory: userData.memory,
      logic: userData.logic,
      stats: userData.stats,
      report: aiResponse
    };
    
    const response = await fetch('save_ai_analysis.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(dataToSave)
    });
    
    const result = await response.json();
    
    if (result.success) {
      console.log('AI分析已保存，ID:', result.analysis_id);
    } else {
      console.error('保存失敗:', result.message);
    }
  } catch (error) {
    console.error('保存分析時發生錯誤:', error);
  }
}

// 載入歷史記錄
let historyOffset = 0;
const historyLimit = 5;

async function loadAnalysisHistory(reset = false) {
  if (reset) {
    historyOffset = 0;
    document.getElementById('historyList').innerHTML = '';
  }
  
  const loading = document.getElementById('historyLoading');
  loading.style.display = 'block';
  
  try {
    const response = await fetch(`get_ai_analysis_history.php?limit=${historyLimit}&offset=${historyOffset}`);
    const result = await response.json();
    
    if (result.success) {
      displayHistoryRecords(result.records);
      
      // 更新偏移量
      historyOffset += historyLimit;
      
      // 顯示或隱藏"載入更多"按鈕
      const loadMoreBtn = document.getElementById('loadMoreContainer');
      if (result.has_more) {
        loadMoreBtn.style.display = 'block';
      } else {
        loadMoreBtn.style.display = 'none';
      }
      
      // 如果沒有記錄，顯示提示
      if (result.total_count === 0) {
        document.getElementById('historyList').innerHTML = `
          <div style="text-align: center; padding: 60px 20px; color: #999;">
            <div style="font-size: 48px; margin-bottom: 20px;">📭</div>
            <div style="font-size: 18px;">尚無AI分析記錄</div>
            <div style="font-size: 14px; margin-top: 10px;">點擊「能力分析報告」標籤頁生成第一份AI分析吧！</div>
          </div>
        `;
      }
    } else {
      throw new Error(result.message);
    }
  } catch (error) {
    console.error('載入歷史記錄失敗:', error);
    document.getElementById('historyList').innerHTML = `
      <div style="text-align: center; padding: 40px; color: #f44336;">
        載入失敗：${error.message}
      </div>
    `;
  } finally {
    loading.style.display = 'none';
  }
}

// 顯示歷史記錄
function displayHistoryRecords(records) {
  const container = document.getElementById('historyList');
  
  records.forEach(record => {
    const recordCard = createHistoryCard(record);
    container.appendChild(recordCard);
  });
}

// 創建歷史記錄卡片
function createHistoryCard(record) {
  const card = document.createElement('div');
  card.className = 'history-card';
  card.style.cssText = `
    background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
    border-radius: 20px;
    padding: 30px;
    margin-bottom: 25px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.1);
    border: 1px solid rgba(255,255,255,0.2);
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    cursor: pointer;
    position: relative;
    overflow: hidden;
  `;
  
  // 準備建議內容
  let suggestionsHtml = '';
  if (record.suggestions && Array.isArray(record.suggestions) && record.suggestions.length > 0) {
    suggestionsHtml = '<ul style="margin: 10px 0; padding-left: 20px;">' + 
      record.suggestions.map(s => `<li style="margin: 5px 0;">${s}</li>`).join('') + 
      '</ul>';
  } else if (typeof record.suggestions === 'string') {
    suggestionsHtml = `<p style="margin: 10px 0;">${record.suggestions}</p>`;
  }
  
  card.innerHTML = `
    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 20px;">
      <div>
        <h4 style="margin: 0 0 8px 0; color: #2c3e50; font-size: 22px; font-weight: 700; background: linear-gradient(45deg, #667eea, #764ba2); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">
          ${record.ai_enhanced ? '✨ ' : ''}${record.player_type || 'AI分析報告'}
        </h4>
        <div style="color: #7f8c8d; font-size: 14px; font-weight: 500;">
          <span>${record.formatted_time}</span>
          <span style="margin-left: 12px; background: linear-gradient(45deg, #667eea, #764ba2); color: white; padding: 2px 8px; border-radius: 12px; font-size: 12px;">${record.time_ago}</span>
        </div>
      </div>
    </div>
    
    <div style="margin: 20px 0; padding: 20px; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 15px; border-left: 4px solid #667eea;">
      <div style="color: #495057; font-size: 15px; line-height: 1.7; font-weight: 500;">
        ${record.description || '無描述'}
      </div>
    </div>
    
    <div style="margin: 20px 0;">
      <strong style="color: #2c3e50; font-size: 16px; font-weight: 700;">🎯 能力分數</strong>
      <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-top: 15px;">
        <div style="text-align: center; padding: 20px; background: linear-gradient(135deg, rgba(255, 99, 132, 0.1) 0%, rgba(255, 99, 132, 0.05) 100%); border-radius: 15px; border: 2px solid rgba(255, 99, 132, 0.2); transition: all 0.3s ease;">
          <div style="font-size: 14px; color: #e74c3c; font-weight: 600; margin-bottom: 8px;">⚡ 反應力</div>
          <div style="font-size: 24px; font-weight: 800; color: #e74c3c; margin: 8px 0; text-shadow: 0 2px 4px rgba(231, 76, 60, 0.3);">${record.reaction_score}</div>
          <div style="font-size: 12px; color: #95a5a6; font-weight: 500;">${record.reaction_games}次</div>
        </div>
        <div style="text-align: center; padding: 20px; background: linear-gradient(135deg, rgba(54, 162, 235, 0.1) 0%, rgba(54, 162, 235, 0.05) 100%); border-radius: 15px; border: 2px solid rgba(54, 162, 235, 0.2); transition: all 0.3s ease;">
          <div style="font-size: 14px; color: #3498db; font-weight: 600; margin-bottom: 8px;">🧠 記憶力</div>
          <div style="font-size: 24px; font-weight: 800; color: #3498db; margin: 8px 0; text-shadow: 0 2px 4px rgba(52, 152, 219, 0.3);">${record.memory_score}</div>
          <div style="font-size: 12px; color: #95a5a6; font-weight: 500;">${record.memory_games}次</div>
        </div>
        <div style="text-align: center; padding: 20px; background: linear-gradient(135deg, rgba(75, 192, 192, 0.1) 0%, rgba(75, 192, 192, 0.05) 100%); border-radius: 15px; border: 2px solid rgba(75, 192, 192, 0.2); transition: all 0.3s ease;">
          <div style="font-size: 14px; color: #1abc9c; font-weight: 600; margin-bottom: 8px;">🔢 邏輯力</div>
          <div style="font-size: 24px; font-weight: 800; color: #1abc9c; margin: 8px 0; text-shadow: 0 2px 4px rgba(26, 188, 156, 0.3);">${record.logic_score}</div>
          <div style="font-size: 12px; color: #95a5a6; font-weight: 500;">${record.logic_games}次</div>
        </div>
      </div>
    </div>
    
    ${suggestionsHtml ? `
      <div style="margin-top: 20px; padding: 20px; background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%); border-radius: 15px; border-left: 4px solid #f39c12;">
        <strong style="color: #d68910; font-size: 16px; font-weight: 700;">💡 改進建議</strong>
        <div style="margin-top: 10px;">
          ${suggestionsHtml}
        </div>
      </div>
    ` : ''}
  `;
  
  // 添加懸停效果
  card.addEventListener('mouseenter', () => {
    card.style.transform = 'translateY(-8px) scale(1.02)';
    card.style.boxShadow = '0 20px 40px rgba(0,0,0,0.15)';
    card.style.borderColor = 'rgba(102, 126, 234, 0.3)';
  });
  
  card.addEventListener('mouseleave', () => {
    card.style.transform = 'translateY(0) scale(1)';
    card.style.boxShadow = '0 8px 32px rgba(0,0,0,0.1)';
    card.style.borderColor = 'rgba(255,255,255,0.2)';
  });
  
  return card;
}

// 載入更多歷史記錄
function loadMoreHistory() {
  loadAnalysisHistory(false);
}

// 切換歷史記錄區域的顯示/隱藏
let isHistoryExpanded = false;
let historyLoaded = false;

function toggleHistorySection() {
  const historyContainer = document.getElementById('historyContainer');
  const toggleBtn = document.getElementById('toggleHistoryBtn');
  const toggleIcon = document.getElementById('toggleIcon');
  
  isHistoryExpanded = !isHistoryExpanded;
  
  if (isHistoryExpanded) {
    historyContainer.style.display = 'block';
    toggleIcon.textContent = '▲';
    toggleBtn.innerHTML = '<span id="toggleIcon">▲</span> 收起歷史記錄';
    
    // 第一次展開時載入歷史記錄
    if (!historyLoaded) {
      loadAnalysisHistory(true);
      historyLoaded = true;
    }
  } else {
    historyContainer.style.display = 'none';
    toggleIcon.textContent = '▼';
    toggleBtn.innerHTML = '<span id="toggleIcon">▼</span> 展開歷史記錄';
  }
}

// 顯示分析區塊（類似遊戲分類的切換功能）
function showAnalysisSection(section) {
  // 隱藏所有內容區塊
  const sections = ['radar-section', 'trend-section', 'report-section'];
  sections.forEach(id => {
    document.getElementById(id).style.display = 'none';
  });
  
  // 移除所有按鈕的active類別
  const tabs = ['radarTab', 'trendTab', 'reportTab'];
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
  
  // 更新遊戲次數
  if (reactionGames) reactionGames.textContent = data.stats.reaction_games;
  if (memoryGames) memoryGames.textContent = data.stats.memory_games;
  if (logicGames) logicGames.textContent = data.stats.logic_games;
  
  // 更新平均時間（顯示為秒數）
  if (reactionAvg) reactionAvg.textContent = data.stats.reaction_avg + 's';
  if (memoryAvg) memoryAvg.textContent = data.stats.memory_avg + 's';
  if (logicAvg) logicAvg.textContent = data.stats.logic_avg + 's';
  
  
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
</html> 