// ===== 樂齡智趣網 - 全站共用時間追蹤（修復版） =====

// 會話級別的計時器，避免重複累加問題
let sessionStartTime = Date.now();
let sessionSeconds = 0;
let lastSaveTime = 0;
let isPageActive = true; // 追蹤頁面是否活躍

// 從 localStorage 讀取之前累積的時間（僅用於顯示）
let totalDisplaySeconds = parseInt(localStorage.getItem('playTimeTotal')) || 0;

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
    timeValue.textContent = formatTime(totalDisplaySeconds + sessionSeconds);
  }
}

// 每秒執行：更新會話時間並更新畫面（只在頁面活躍時）
setInterval(() => {
  if (isPageActive) {
    sessionSeconds = Math.floor((Date.now() - sessionStartTime) / 1000);
    updateTimeDisplay();
  }
}, 1000);

// 確保 DOM 載入完成後才執行一次畫面更新
document.addEventListener('DOMContentLoaded', () => {
  updateTimeDisplay();
});

// 顯示目前時間的提示（⏱️按鈕）
function showTimeDetail() {
  alert("您這次已累積瀏覽時間：" + formatTime(totalDisplaySeconds + sessionSeconds));
}

// 保存時間到資料庫（只保存會話時間）
function saveSessionTime() {
  const currentTime = Date.now();
  const timeSinceLastSave = Math.floor((currentTime - lastSaveTime) / 1000);
  
  if (timeSinceLastSave > 0 && isPageActive) {
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

// 定期保存（每5分鐘）
setInterval(saveSessionTime, 300000);

// 頁面隱藏時保存並暫停計時
document.addEventListener('visibilitychange', function() {
  if (document.hidden) {
    console.log('頁面隱藏，暫停計時');
    isPageActive = false;
    saveSessionTime();
    // 更新 localStorage 中的總顯示時間
    localStorage.setItem('playTimeTotal', totalDisplaySeconds + sessionSeconds);
  } else {
    console.log('頁面顯示，恢復計時');
    isPageActive = true;
    sessionStartTime = Date.now(); // 重置會話開始時間
    lastSaveTime = Date.now(); // 重置最後保存時間
  }
});

// 頁面卸載時保存
window.addEventListener('beforeunload', function() {
  console.log('頁面即將卸載，保存時間');
  isPageActive = false;
  saveSessionTime();
  localStorage.setItem('playTimeTotal', totalDisplaySeconds + sessionSeconds);
});

// 檢查是否為遊戲頁面
function isGamePage() {
  const currentPath = window.location.pathname;
  const gamePages = [
    '/2048ht.php',
    '/Catch-Egg Game.php',
    '/Vegetable-Cost.php',
    '/Memory-Game.php',
    '/Memory-Game-2P.php',
    '/prisoner.php',
    '/rhythm_game.php',
    '/text-color.php',
    '/clue.php'
  ];
  
  return gamePages.some(page => currentPath.includes(page));
}

// 只在非遊戲頁面啟用自動計時
if (!isGamePage()) {
  console.log('啟用自動計時功能');
} else {
  console.log('遊戲頁面，停用自動計時');
} 