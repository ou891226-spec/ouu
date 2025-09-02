// 分類切換功能
function showCategory(category) {
  // 隱藏所有分類遊戲區域
  const allCategoryGames = document.querySelectorAll('.category-games');
  allCategoryGames.forEach(area => {
    area.classList.remove('active');
  });
  
  // 移除所有標籤的active狀態
  const allTabs = document.querySelectorAll('.category-tab');
  allTabs.forEach(tab => {
    tab.classList.remove('active');
  });
  
  // 顯示選中的分類
  const selectedArea = document.getElementById(category + '-games');
  if (selectedArea) {
    selectedArea.classList.add('active');
  }
  
  // 激活對應的標籤
  const selectedTab = event.target.closest('.category-tab');
  if (selectedTab) {
    selectedTab.classList.add('active');
  }
  
  // 添加切換動畫效果
  if (selectedArea) {
    selectedArea.style.animation = 'none';
    selectedArea.offsetHeight; // 觸發重繪
    selectedArea.style.animation = 'fadeIn 0.3s ease-in-out';
  }
}

// 頁面載入時初始化
document.addEventListener('DOMContentLoaded', function() {
  // 確保默認顯示全部遊戲
  const allGamesTab = document.querySelector('.category-tab[onclick*="all"]');
  const allGamesArea = document.getElementById('all-games');
  
  if (allGamesTab && allGamesArea) {
    allGamesTab.classList.add('active');
    allGamesArea.classList.add('active');
  }
  
  // 為標籤添加點擊效果
  const categoryTabs = document.querySelectorAll('.category-tab');
  categoryTabs.forEach(tab => {
    tab.addEventListener('click', function() {
      // 添加點擊反饋
      this.style.transform = 'scale(0.92)';
      setTimeout(() => {
        this.style.transform = '';
      }, 200);
    });
  });
});
