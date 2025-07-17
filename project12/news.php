<!DOCTYPE html>
<html lang="zh-Hant">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>相關報導</title>
  <link rel="stylesheet" href="css/main.css" />
  <link rel="stylesheet" href="css/news.css" />
  <link rel="stylesheet" href="css/mission.css" />
</head>
<body>

<!-- 黑色半透明背景 -->
<div id="overlay" class="overlay" onclick="toggleSidebar()"></div>

<!-- 側邊欄 -->
<div id="sidebar" class="sidebar">
  <a href="game-category.php" class="jelly-btn jelly-red">全部遊戲</a>
  <a href="game-categories.php" class="jelly-btn jelly-red">遊戲分類</a>
  <a href="friend.php" class="jelly-btn jelly-green">好友列表</a>
  <a href="Ranking_list.php" class="jelly-btn jelly-green">排行榜</a>
  <div class="btn-group">
    <a href="an.php" class="jelly-btn jelly-yellow">分析圖表</a>
    <a href="news.php" class="jelly-btn jelly-yellow">相關報導</a>
    <a href="us.php" class="jelly-btn jelly-yellow">關於我們</a>
  </div>
</div>

<!-- 頁首 -->
<header>
  <div id="menuButton" class="menu" onclick="toggleSidebar()">
    <img src="img/contents.jpg" alt="目錄" class="menu-icon" />
    <span id="menuText" class="menu-text">目錄</span>
  </div>
  <form class="search-bar" action="game.php" method="GET" onsubmit="return validateSearch()">
    <input type="text" name="keyword" id="searchInput" placeholder="搜尋遊戲">
  </form>
  <span class="notification-bell" onclick="openMissionModal()">🔔</span>

<img src="img/big.jpg" alt="使用者" class="profile">

</header>

<!-- 狀態列 -->
<div class="status-bar">
  <div class="score">您的分數 <span id="scoreValue" style="color: red;">0</span> 💰</div>
  <div class="time">
    已遊玩時間 <span id="timeValue">00:00:00</span>
    <button onclick="showTimeDetail()" class="time-icon-btn">⏱️</button>
  </div>
</div>

<!-- 主內容 -->
<main>
  <h2 class="section-title">相關報導</h2>

  <section class="news-item">
    <div class="news-img">
      <img src="img/news1.png" alt="新聞1">
    </div>
    <div class="news-content">
      <h3>預防老化從遊戲著手</h3>
      <p>研究指出，體感遊戲與麻將遊戲對於預防老化具有顯著效果，讓我們一起來看看如何透過遊戲維持健康。</p>
      <a href="https://news.owlting.com/articles/223052?utm_source=chatgpt.com" target="_blank" class="more-link">查看更多</a>
    </div>
  </section>

  <section class="news-item">
    <div class="news-img">
      <img src="img/news2.png" alt="新聞2">
    </div>
    <div class="news-content">
      <h3>失智預防的科學方法</h3>
      <p>居家認知訓練不僅能提高老年人的認知能力，還能有效預防失智症，讓你輕鬆在家進行。</p>
      <a href="https://www.ltpasolution.com/home-training.html" target="_blank" class="more-link">查看更多</a>
    </div>
  </section>

  <section class="news-item">
    <div class="news-img">
      <img src="img/news3.png" alt="新聞3">
    </div>
    <div class="news-content">
      <h3>電腦遊戲助長者練腦</h3>
      <p>每天15分鐘的電腦遊戲可以幫助長者保持大腦活力，對抗記憶衰退。</p>
      <a href="https://news.owlting.com/articles/223052?utm_source=chatgpt.com" target="_blank" class="more-link">查看更多</a>
    </div>
  </section>
</main>

<!-- 彈跳視窗 -->
<div id="missionModal" class="mission-modal" style="display: none;">
  <div class="modal-content">
    <span class="close" onclick="closeMissionModal()">✕</span>
    <h2>每日任務</h2>

   <div id="daily-tasks-container"></div>

<!-- 側邊欄遮罩 -->
<div class="overlay" id="overlay" onclick="closeSidebar()"></div>

<!-- JS 控制 -->
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
<script src="js/auto-save-time.js"></script>
<script src="js/load-daily-tasks.js"></script>
<script src="js/mission.js"></script>
<script src="js/save-score.js"></script>
<script src="js/get-score.js"></script>

</body>
</html>