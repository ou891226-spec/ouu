// ===== 樂齡智趣網 - 全站共用時間追蹤 =====

// 獲取今天凌晨12點的時間戳
function getTodayStart() {
  const now = new Date();
  const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
  return today.getTime();
}

// 會話級別的計時器，使用 localStorage 保存狀態
let todayStart = getTodayStart();
let sessionStartTime = Date.now();
let sessionSeconds = 0;
let lastSaveTime = Date.now();

// 從 localStorage 載入或初始化會話時間
function loadSessionTime() {
  const savedTodayStart = localStorage.getItem('todayStart');
  const savedSessionStart = localStorage.getItem('sessionStartTime');
  const savedSessionSeconds = localStorage.getItem('sessionSeconds');
  
  const currentTodayStart = getTodayStart();
  
  // 如果是新的一天，重置所有計時器
  if (savedTodayStart && parseInt(savedTodayStart) !== currentTodayStart) {
    console.log('新的一天，重置計時器');
    resetSessionTime();
    return;
  }
  
  // 如果有保存的會話時間，載入它
  if (savedTodayStart && savedSessionStart && savedSessionSeconds) {
    todayStart = parseInt(savedTodayStart);
    sessionStartTime = parseInt(savedSessionStart);
    sessionSeconds = parseInt(savedSessionSeconds);
    console.log('載入已保存的會話時間:', sessionSeconds, '秒');
  } else {
    // 第一次載入，初始化
    resetSessionTime();
  }
}

// 重置會話時間
function resetSessionTime() {
  todayStart = getTodayStart();
  sessionStartTime = Date.now();
  sessionSeconds = 0;
  lastSaveTime = Date.now();
  
  // 保存到 localStorage
  saveSessionTimeToStorage();
}

// 保存會話時間到 localStorage
function saveSessionTimeToStorage() {
  localStorage.setItem('todayStart', todayStart.toString());
  localStorage.setItem('sessionStartTime', sessionStartTime.toString());
  localStorage.setItem('sessionSeconds', sessionSeconds.toString());
}

// 檢查是否需要重置（新的一天）
function checkAndResetDaily() {
  const currentTodayStart = getTodayStart();
  if (currentTodayStart !== todayStart) {
    // 新的一天，重置所有計時器
    resetSessionTime();
    console.log('新的一天，重置計時器');
  }
}

// 初始化時載入會話時間
loadSessionTime();

// 時間格式化：轉成 HH:MM:SS
function formatTime(seconds) {
  const hrs = String(Math.floor(seconds / 3600)).padStart(2, '0');
  const mins = String(Math.floor((seconds % 3600) / 60)).padStart(2, '0');
  const secs = String(seconds % 60).padStart(2, '0');
  return `${hrs}:${mins}:${secs}`;
}

// 顯示目前累積時間到畫面上
function updateTimeDisplay() {
  const timeValue = document.getElementById('timeValue');
  if (timeValue) {
    timeValue.textContent = formatTime(sessionSeconds);
  }
}

// 每秒執行：更新會話時間並更新畫面
setInterval(() => {
  // 檢查是否需要重置（新的一天）
  checkAndResetDaily();
  
  // 計算從會話開始的總時間
  const now = Date.now();
  sessionSeconds = Math.floor((now - sessionStartTime) / 1000);
  
  // 每10秒保存一次到 localStorage
  if (sessionSeconds % 10 === 0) {
    saveSessionTimeToStorage();
  }
  
  updateTimeDisplay();
}, 1000);

// 確保 DOM 載入完成後才執行一次畫面更新（避免 timeValue 未出現）
document.addEventListener('DOMContentLoaded', () => {
  updateTimeDisplay();
});

// 顯示目前時間的提示（⏱️按鈕）
function showTimeDetail() {
  alert("您今天已累積瀏覽時間：" + formatTime(sessionSeconds));
}

// 手動保存當前時間到資料庫
function saveCurrentTime() {
  if (sessionSeconds > 0) {
    saveSessionTime();
    alert("✅ 已保存當前遊玩時間：" + formatTime(sessionSeconds));
  } else {
    alert("⚠️ 尚無遊玩時間可保存");
  }
}

// ===== 手動儲存時間到資料庫 =====
// 保存時間到資料庫（只保存會話時間）
function saveSessionTime() {
  const currentTime = Date.now();
  const timeSinceLastSave = Math.floor((currentTime - lastSaveTime) / 1000);
  
  if (timeSinceLastSave > 0) {
    // 保存到遊戲記錄
    const gameData = new URLSearchParams({
      game_id: 0,
      score: 0,
      play_time: timeSinceLastSave,
      difficulty: 'N/A',
      game_type: '網站瀏覽總時間',
    });

    fetch('save_game_result.php', {
      method: 'POST',
      body: gameData,
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
      }
    }).catch(error => {
      console.log('遊戲時間儲存失敗');
    });

    // 保存到每日遊玩時間紀錄
    const dailyData = new URLSearchParams({
      play_time: timeSinceLastSave,
    });

    fetch('save_daily_playtime.php', {
      method: 'POST',
      body: dailyData,
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
      }
    }).then(() => {
      console.log(`✅ 已保存 ${timeSinceLastSave} 秒到每日紀錄`);
    }).catch(error => {
      console.log('每日時間儲存失敗');
    });

    lastSaveTime = currentTime;
  }
}

// 每5分鐘保存一次（可選，如果不需要可以註釋掉）
// setInterval(saveSessionTime, 300000);

// ===== 頁面可見性變化處理 =====
document.addEventListener('visibilitychange', function() {
  // 當頁面被隱藏時，不保存時間（用戶要求）
  // if (document.hidden) {
  //   saveSessionTime();
  // }
});

// 頁面卸載前不保存（用戶要求）
// window.addEventListener('beforeunload', function() {
  // saveSessionTime();
// });

// ===== 新增：遊戲頁面特殊處理 =====
// 檢測是否在遊戲頁面
function isGamePage() {
  const gamePages = [
    '2048ht.php',
    'Memory-Game.php', 
    'Memory-Game-2P.php',
    'Catch-Egg Game.php',
    'rhythm_game.php',
    'prisoner.php',
    'Vegetable-Cost.php'
  ];
  
  const currentPage = window.location.pathname.split('/').pop();
  return gamePages.includes(currentPage);
}

// 在遊戲頁面中，確保計時器持續運行
if (isGamePage()) {
  console.log('檢測到遊戲頁面，計時器將持續運行');
}
