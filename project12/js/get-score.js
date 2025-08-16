function fetchUserScore() {
  console.log('開始獲取分數...');
  
  // 檢查元素是否存在
  const scoreElement = document.getElementById('scoreValue');
  if (!scoreElement) {
    console.error('找不到 scoreValue 元素');
    return;
  }
  
  console.log('找到 scoreValue 元素:', scoreElement);
  console.log('當前分數顯示:', scoreElement.textContent);
  
  fetch('get_score.php')
    .then(response => {
      console.log('API回應狀態:', response.status);
      return response.json();
    })
    .then(data => {
      console.log('分數API回應:', data);
      
      if (data.success) {
        console.log('準備更新分數從', scoreElement.textContent, '到', data.score);
        scoreElement.textContent = data.score;
        console.log('分數已更新為:', data.score);
        console.log('更新後的分數顯示:', scoreElement.textContent);
      } else {
        console.warn('未登入或無法取得分數:', data.message);
        if (data.debug) {
          console.log('除錯資訊:', data.debug);
        }
      }
    })
    .catch(error => {
      console.error('取得分數失敗:', error);
    });
}

// 立即更新分數的函數
function updateScoreImmediately() {
  console.log('立即更新分數...');
  fetchUserScore();
}

// 頁面載入時執行
document.addEventListener('DOMContentLoaded', function() {
    console.log('頁面載入完成，開始獲取分數...');
    fetchUserScore();
    
    // 立即執行一次，然後每30秒執行一次
    setTimeout(fetchUserScore, 1000); // 1秒後再執行一次
});

// 每30秒重新載入分數
setInterval(fetchUserScore, 30000);

// 添加手動刷新功能
window.refreshScore = fetchUserScore;
window.updateScoreImmediately = updateScoreImmediately;
