// ===== 樂齡智趣網 - 全站共用時間追蹤 =====

// 讀取 localStorage 中已累積的秒數，沒有的話從 0 開始
let totalSeconds = parseInt(localStorage.getItem('playTimeTotal')) || 0;

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
    timeValue.textContent = formatTime(totalSeconds);
  }
}

// 每秒執行：時間 +1 並更新畫面與 localStorage
setInterval(() => {
  totalSeconds++;
  localStorage.setItem('playTimeTotal', totalSeconds);
  updateTimeDisplay();
}, 1000);

// 確保 DOM 載入完成後才執行一次畫面更新（避免 timeValue 未出現）
document.addEventListener('DOMContentLoaded', () => {
  updateTimeDisplay();
});

// 顯示目前時間的提示（⏱️按鈕）
function showTimeDetail() {
  alert("您這次已累積瀏覽時間：" + formatTime(totalSeconds));
}

// ===== 離開網站時儲存資料並清除時間 =====
window.addEventListener('beforeunload', function () {
  // 若遊玩時間太短就不紀錄
  if (totalSeconds < 5) return;

  // 使用 sendBeacon 傳送資料
  const data = new URLSearchParams({
    game_id: 0,
    score: 0,
    play_time: totalSeconds,
    difficulty: 'N/A',
    game_type: '網站瀏覽總時間',
  });

  navigator.sendBeacon('save_game_result.php', data);

  // ✅ 清除時間，只在真正關閉/跳出時才做（而不是每次換頁）
  // 判斷方式：如果目標網址不是同一網站內頁
  // const destination = document.activeElement?.href || "";
  // if (!destination || !destination.includes(location.hostname)) {
  //   localStorage.removeItem('playTimeTotal');
  // }
});

// ===== 新增：頁面可見性變化處理 =====
// 當頁面被隱藏或顯示時，確保計時器繼續運行
document.addEventListener('visibilitychange', function() {
  // 頁面可見性變化時，確保計時器狀態正確
  // 計時器會持續運行，不受頁面可見性影響
});

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
  
  // 在遊戲頁面中，可以添加額外的時間顯示（如果需要）
  // 例如在遊戲界面中顯示總遊玩時間
  function addGameTimeDisplay() {
    // 如果遊戲頁面需要顯示總時間，可以在這裡添加
    // 目前主頁面已有顯示，所以這裡暫時不需要額外處理
  }
}
