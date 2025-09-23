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
  <title>相關報導</title>
  <link rel="stylesheet" href="css/main.css" />
  <link rel="stylesheet" href="css/news.css" />
  <link rel="stylesheet" href="css/mission.css" />
  <link rel="stylesheet" href="css/profile-modal.css" />
  <link rel="stylesheet" href="css/global-invitation.css" />
  <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
</head>
<body>

<!-- 側邊欄遮罩 -->
<div id="sidebarOverlay" class="overlay" onclick="toggleSidebar()"></div>

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
    <input type="text" name="keyword" id="searchInput" placeholder="搜尋遊戲">
  </form>
  <div class="user-icons">
    <a href="#" onclick="openMissionModal(event); return false;">
      <span class="notification-bell">🔔</span>
    </a>
    <a href="#" onclick="openProfileModal(event); return false;">
      <img src="<?php echo $avatar_url; ?>" alt="使用者" class="profile" />
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

<!-- 主內容 -->
<div class="news-section">
  <h2 class="section-title">📰 相關報導</h2>

  <section class="news-item" data-aos="fade-up" data-aos-delay="100">
    <div class="news-img">
      <img src="img/news1.png" alt="預防老化從遊戲著手">
      <div class="news-badge">🏆 熱門推薦</div>
    </div>
    <div class="news-content">
      <div class="news-meta">
        <span class="news-category">健康生活</span>
        <span class="news-date">2024年最新研究</span>
      </div>
      <h3>預防老化從遊戲著手</h3>
      <p>研究指出，體感遊戲與麻將遊戲對於預防老化具有顯著效果，讓我們一起來看看如何透過遊戲維持健康。科學證實，適度的遊戲活動能夠刺激大腦神經元，提升認知功能。</p>
      <a href="https://news.owlting.com/articles/223052?utm_source=chatgpt.com" target="_blank" class="more-link">
        <span>閱讀全文</span>
      </a>
    </div>
  </section>

  <section class="news-item" data-aos="fade-up" data-aos-delay="200">
    <div class="news-img">
      <img src="img/news2.png" alt="失智預防的科學方法">
      <div class="news-badge">🧠 專業研究</div>
    </div>
    <div class="news-content">
      <div class="news-meta">
        <span class="news-category">認知訓練</span>
        <span class="news-date">醫學期刊發表</span>
      </div>
      <h3>失智預防的科學方法</h3>
      <p>居家認知訓練不僅能提高老年人的認知能力，還能有效預防失智症，讓你輕鬆在家進行。透過系統性的腦力訓練，可以顯著延緩認知衰退的進程。</p>
      <a href="https://www.ltpasolution.com/home-training.html" target="_blank" class="more-link">
        <span>了解更多</span>
      </a>
    </div>
  </section>

  <section class="news-item" data-aos="fade-up" data-aos-delay="300">
    <div class="news-img">
      <img src="img/news3.png" alt="電腦遊戲助長者練腦">
      <div class="news-badge">💻 科技新知</div>
    </div>
    <div class="news-content">
      <div class="news-meta">
        <span class="news-category">數位健康</span>
        <span class="news-date">專家建議</span>
      </div>
      <h3>電腦遊戲助長者練腦</h3>
      <p>每天15分鐘的電腦遊戲可以幫助長者保持大腦活力，對抗記憶衰退。現代科技為健康管理提供了全新的解決方案，讓遊戲成為健康生活的一部分。</p>
      <a href="https://news.owlting.com/articles/223052?utm_source=chatgpt.com" target="_blank" class="more-link">
        <span>查看詳情</span>
      </a>
    </div>
  </section>
</div>

<!-- 黑色半透明背景 (彈窗遮罩) -->
<div id="modalOverlay" class="overlay" style="display:none;"></div>

<!-- 彈跳視窗 -->
<div id="missionModal" class="mission-modal" style="display: none;">
  <div class="modal-content">
    <span class="close" onclick="closeMissionModal()">✕</span>
    <h2>每日任務</h2>
    <div id="daily-tasks-container"></div>
  </div>
</div>

<!-- 個人資訊彈窗 -->
<div id="profileModal" class="profile-modal" style="display:none;">
  <span class="close" onclick="closeProfileModal()">✕</span>
  <div class="profile-header">
    <div class="profile-account">
      帳號：<?php echo htmlspecialchars($account); ?>
    </div>
    <div class="profile-avatar-wrap">
      <img id="profileAvatarImg" src="<?php echo $avatar_url; ?>" alt="頭像" class="profile-avatar" />
      <span class="profile-avatar-edit" onclick="document.getElementById('avatarInput').click();">
        📷
      </span>
      <form id="avatarForm" action="upload_avatar.php" method="POST" enctype="multipart/form-data" style="display:none;">
        <input type="file" id="avatarInput" name="avatar" accept="image/*" onchange="previewAndUploadAvatar(event)" />
      </form>
    </div>
  </div>
  <div class="profile-greeting">
    <?php echo htmlspecialchars($name); ?>，您好!
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
    <img src="<?php echo $avatar_url; ?>" alt="頭像" class="profile-avatar" style="width: 90px; height: 90px;" />
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



<!-- JS 控制 -->
<script>
  let sidebarOpen = false;

  function toggleSidebar() {
    const sidebar = document.getElementById("sidebar");
    const menuText = document.getElementById("menuText");
    const overlay = document.getElementById("sidebarOverlay");

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
    document.getElementById("sidebarOverlay").style.display = "none";
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


  function openProfileModal() {
    document.getElementById('profileModal').style.display = 'flex';
    document.getElementById('modalOverlay').style.display = 'block';
    loadUserAchievements(); // 載入成就
  }
  function closeProfileModal() {
    document.getElementById('profileModal').style.display = 'none';
    document.getElementById('modalOverlay').style.display = 'none';
  }

  function openMissionModal() {
    const modal = document.getElementById('missionModal');
    const overlay = document.getElementById('modalOverlay');
    
    modal.style.display = 'flex';
    overlay.style.display = 'block';
    
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
      overlay.style.display = 'none';
    }, 150);
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
</script>

<!-- 外部 JS -->
<script src="js/auto-save-time-fixed.js"></script>
<script src="js/load-daily-tasks.js"></script>
<script src="js/mission.js"></script>
<script src="js/save-score.js"></script>
<script src="js/get-score.js"></script>
<script src="js/achievements.js"></script>
<script src="js/avatar-upload.js"></script>
<script src="js/global-invitation-checker.js"></script>
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>
  // 初始化AOS動畫
  AOS.init({
    duration: 800,
    easing: 'ease-in-out',
    once: true,
    offset: 100
  });
</script>

</body>
</html>