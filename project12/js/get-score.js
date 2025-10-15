function fetchUserScore() {
  console.log('開始獲取分數...');
  
  // 檢查是否在遊戲頁面，如果是則跳過分數更新
  const isGamePage = window.location.pathname.includes('prisoner.php') ||
                     window.location.pathname.includes('clue.php') ||
                     window.location.pathname.includes('rhythm_game.php') ||
                     window.location.pathname.includes('Memory-Game.php') ||
                     window.location.pathname.includes('Catch-Egg-Game.php') ||
                     window.location.pathname.includes('Vegetable-Cost.php') ||
                     window.location.pathname.includes('2048ht.php') ||
                     window.location.pathname.includes('puzzle.php') ||
                     window.location.pathname.includes('text-color.php');
  
  if (isGamePage) {
    console.log('檢測到遊戲頁面，跳過總分更新');
    return;
  }
  
  // 檢查元素是否存在（只尋找 scoreValue 元素）
  let scoreElement = document.getElementById('scoreValue');
  
  if (!scoreElement) {
    console.log('找不到 scoreValue 元素，可能不在主頁面，跳過分數更新');
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

// 通知主頁面更新分數的函數
function notifyMainPageScoreUpdate() {
  console.log('通知主頁面更新分數...');
  
  // 嘗試通知父頁面（如果是在iframe中）
  if (window.parent && window.parent !== window) {
    try {
      window.parent.postMessage({type: 'scoreUpdate'}, '*');
      console.log('已通知父頁面更新分數');
    } catch (e) {
      console.log('無法通知父頁面:', e.message);
    }
  }
  
  // 嘗試通知opener（如果是從主頁面打開的）
  if (window.opener && !window.opener.closed) {
    try {
      window.opener.postMessage({type: 'scoreUpdate'}, '*');
      console.log('已通知opener更新分數');
    } catch (e) {
      console.log('無法通知opener:', e.message);
    }
  }
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

// 監聽來自其他頁面的分數更新通知
window.addEventListener('message', function(event) {
  if (event.data && event.data.type === 'scoreUpdate') {
    console.log('收到分數更新通知，重新載入分數...');
    fetchUserScore();
  }
});

// 智能分數更新函數
function smartScoreUpdate() {
  console.log('智能分數更新...');
  
  // 先嘗試直接更新（如果在主頁面）
  fetchUserScore();
  
  // 然後嘗試通知其他頁面
  notifyMainPageScoreUpdate();
}

// 添加手動刷新功能
window.refreshScore = fetchUserScore;
window.updateScoreImmediately = updateScoreImmediately;
window.forceRefreshScore = smartScoreUpdate;
