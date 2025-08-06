// 重置遊玩時間相關的 localStorage 數據
function resetPlaytimeData() {
  // 清除所有遊玩時間相關的數據
  localStorage.removeItem('playTimeTotal');
  localStorage.removeItem('lastSavedToDaily');
  
  console.log('✅ 已清除遊玩時間相關的 localStorage 數據');
  
  // 重新載入頁面
  location.reload();
}

// 顯示當前 localStorage 中的遊玩時間數據
function showCurrentPlaytimeData() {
  const totalSeconds = localStorage.getItem('playTimeTotal') || '0';
  const lastSaved = localStorage.getItem('lastSavedToDaily') || '0';
  
  console.log('當前 localStorage 數據：');
  console.log('- playTimeTotal:', totalSeconds);
  console.log('- lastSavedToDaily:', lastSaved);
  
  const totalHours = Math.floor(parseInt(totalSeconds) / 3600);
  const totalMinutes = Math.floor((parseInt(totalSeconds) % 3600) / 60);
  const totalSecs = parseInt(totalSeconds) % 60;
  
  console.log(`- 總遊玩時間: ${totalHours}小時${totalMinutes}分鐘${totalSecs}秒`);
}

// 頁面載入時顯示當前數據
document.addEventListener('DOMContentLoaded', function() {
  showCurrentPlaytimeData();
}); 