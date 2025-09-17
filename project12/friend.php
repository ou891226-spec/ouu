<?php
require_once 'check_login.php';
$account = isset($_SESSION['account']) ? $_SESSION['account'] : '訪客';
$name = isset($_SESSION['name']) ? $_SESSION['name'] : '您好';
$avatar_url = isset($_SESSION['avatar_url']) && $_SESSION['avatar_url'] ? htmlspecialchars($_SESSION['avatar_url']) : 'img/big.jpg';
require_once "db.php";

$my_id = $_SESSION['member_id'];

// 查詢好友列表
$friends = [];
try {
    $sql = "
        SELECT m.member_id, m.member_name, m.account, m.avatar
        FROM friends f
        JOIN member m ON f.friend_id = m.member_id
        WHERE f.member_id = ?
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$my_id]);
    $friends = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // 如果查詢失敗，記錄錯誤但繼續執行
    error_log("好友列表查詢錯誤: " . $e->getMessage());
}

// 查詢我送出的交友邀請（外送邀請）
$sent_invites = [];
try {
    $sql = "
        SELECT fr.request_id, fr.receiver_id, fr.status, fr.created_at,
               m.member_name, m.account, m.avatar
        FROM friend_requests fr
        JOIN member m ON fr.receiver_id = m.member_id
        WHERE fr.sender_id = ?
        ORDER BY fr.created_at DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$my_id]);
    $sent_invites = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("送出邀請查詢錯誤: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="zh-Hant">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>好友列表</title>
  <link rel="stylesheet" href="css/main.css" />
  <link rel="stylesheet" href="css/friend.css">
  <link rel="stylesheet" href="css/mission.css" />
  <link rel="stylesheet" href="css/profile-modal.css" />
  <link rel="stylesheet" href="css/global-invitation.css" />
</head>
<body>

<!-- 黑色半透明背景 -->
<div id="overlay" class="overlay" onclick="toggleSidebar()"></div>
<div id="modalOverlay" class="overlay" style="display:none;" onclick="closeAllModals()"></div>

<!-- 側邊欄 -->
<div id="sidebar" class="sidebar">
  <a href="index.php" class="jelly-btn jelly-red">首頁</a>
  <a href="game-category.php" class="jelly-btn jelly-red">全部遊戲</a>
  <a href="friend.php" class="jelly-btn jelly-green">好友列表</a>
  <a href="Ranking_list.php" class="jelly-btn jelly-green">排行榜</a>
  <div class="btn-group">
    <div class="personal-history-group">
      <button class="jelly-btn jelly-yellow" id="personalHistoryBtn" type="button" onclick="togglePersonalHistoryMenu()">個人歷程 <span id="arrowIcon" style="font-size: 20px !important; margin-left: 10px !important; color: #333 !important; font-weight: bold !important; display: inline-block !important; visibility: visible !important; opacity: 1 !important; text-shadow: 1px 1px 2px rgba(0,0,0,0.3) !important;">▼</span></button>
      <div id="personalHistoryMenu" class="personal-history-menu" style="display:none;">
        <a href="personal-analysis.php" class="jelly-btn jelly-yellow sub-btn">分析圖表</a>
        <a href="history.php" class="jelly-btn jelly-yellow sub-btn">歷史紀錄</a>
      </div>
    </div>
    <a href="news.php" class="jelly-btn jelly-yellow">相關報導</a>
    <a href="us.php" class="jelly-btn jelly-yellow">關於我們</a>
  </div>
</div>

<!-- 頁首 -->
<header>
  <div id="menuButton" class="menu" onclick="toggleSidebar()">
    <img src="img/contents.png" alt="目錄" class="menu-icon">
    <span id="menuText" class="menu-text">目錄</span>
  </div>

  <form class="search-bar" action="game.php" method="GET" onsubmit="return validateSearch()">
    <input type="text" name="keyword" id="searchInput" placeholder="搜尋遊戲">
  </form>

  <div class="user-icons">
    <a href="#" onclick="openMissionModal()">
      <span class="notification-bell">🔔</span>
    </a>
    <a href="#" onclick="openProfileModal();return false;">
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

<!-- 好友列表區塊（取代熱門遊戲與最近常玩） -->
<div class="friend-container">
  <div class="friend-header">
    <div class="friend-title">好友列表</div>
  </div>
  <div class="friend-actions">
    <button class="add-friend-btn" onclick="window.location.href='add-friend.php'">+ 加入好友</button>
    <button class="invite-btn" onclick="window.location.href='invitation-friend.php'">&#128276; 交友邀請</button>
  </div>
  <div class="friend-list">
    <?php foreach ($friends as $friend): ?>
      <div class="friend-row">
        <div class="friend-avatar-block">
          <img src="<?php echo htmlspecialchars($friend['avatar'] ?? 'default.png'); ?>" class="friend-avatar">
          <span class="friend-status-dot"></span>
        </div>
        <div class="friend-info">
          <span class="friend-name"><?php echo htmlspecialchars($friend['member_name']); ?></span>
          <span class="friend-account">(<?php echo htmlspecialchars($friend['account']); ?>)</span>
        </div>
        <button class="delete-friend-btn" data-id="<?php echo $friend['member_id']; ?>">&#128465;</button>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- 我送出的交友邀請 -->
<div class="friend-container" style="margin-top: 16px;">
  <div class="friend-header">
    <div class="friend-title">我送出的邀請</div>
  </div>
  <div class="friend-list">
    <?php if (empty($sent_invites)): ?>
      <div class="empty-state">目前沒有送出的邀請</div>
    <?php else: ?>
      <?php foreach ($sent_invites as $invite): ?>
        <?php 
          $status_map = [
            'pending' => '待處理',
            'accepted' => '已接受',
            'rejected' => '已拒絕',
            'cancelled' => '已取消'
          ];
          $status_label = $status_map[$invite['status']] ?? $invite['status'];
        ?>
        <div class="friend-row">
          <div class="friend-avatar-block">
            <img src="<?php echo htmlspecialchars($invite['avatar'] ?? 'default.png'); ?>" class="friend-avatar">
          </div>
          <div class="friend-info">
            <span class="friend-name"><?php echo htmlspecialchars($invite['member_name']); ?></span>
            <span class="friend-account">(<?php echo htmlspecialchars($invite['account']); ?>)</span>
            <div style="font-size:12px;color:#888;margin-top:4px;">
              狀態：<?php echo htmlspecialchars($status_label); ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<!-- 每日任務彈窗 -->
<div id="missionModal" class="mission-modal" style="display: none;">
  <div class="modal-content">
    <span class="close" onclick="closeMissionModal()">✕</span>
    <h2>每日任務</h2>
    <div id="daily-tasks-container"></div>
  </div>
</div>

<!-- Script 區 -->
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

function validateSearch() {
  const input = document.getElementById('searchInput').value.trim();
  if (input === '') {
    alert('請輸入關鍵字');
    return false;
  }
  return true;
}

function togglePersonalHistoryMenu() {
  const menu = document.getElementById('personalHistoryMenu');
  const arrowIcon = document.getElementById('arrowIcon');
  const isVisible = menu.style.display === 'block';
  
  if (isVisible) {
    menu.style.display = 'none';
    arrowIcon.textContent = '▼';
  } else {
    menu.style.display = 'block';
    arrowIcon.textContent = '▲';
  }
}
</script>

<script src="js/auto-save-time-fixed.js"></script>
<script src="js/load-daily-tasks.js"></script>
<script src="js/mission.js"></script>
<script src="js/save-score.js"></script>
<script src="js/get-score.js"></script>

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
  
  // 添加調試信息
  console.log('打開個人資料彈窗');
  
  // 檢查loadUserAchievements函數是否存在
  if (typeof loadUserAchievements === 'function') {
    console.log('loadUserAchievements函數存在，開始載入成就');
    loadUserAchievements();
  } else {
    console.error('loadUserAchievements函數不存在！');
    // 如果函數不存在，直接調用API
    loadUserAchievementsDirect();
  }
}

// 直接載入成就的備用函數
function loadUserAchievementsDirect() {
  console.log('使用備用方法載入成就');
  
  const container = document.getElementById('achievementCards');
  if (container) {
    container.innerHTML = `
      <div style="text-align: center; padding: 20px; color: #666;">
        <div style="font-size: 24px; margin-bottom: 10px;">⏳</div>
        <div style="font-size: 14px; color: #999;">載入成就中...</div>
      </div>
    `;
  }
  
  fetch('get_user_achievements.php?v=' + Date.now())
    .then(response => response.json())
    .then(data => {
      console.log('API返回數據:', data);
      if (data.success) {
        displayAchievementsDirect(data.achievements, data.today_status);
      } else {
        console.error('載入成就失敗：', data.message);
        displayEmptyAchievementsDirect();
      }
    })
    .catch(error => {
      console.error('載入成就時發生錯誤：', error);
      displayEmptyAchievementsDirect();
    });
}

// 直接顯示成就的備用函數
function displayAchievementsDirect(achievements, todayStatus = null) {
  console.log('顯示成就:', achievements);
  
  const container = document.getElementById('achievementCards');
  
  if (!container) {
    console.error('找不到成就容器');
    return;
  }
  
  if (!achievements || achievements.length === 0) {
    displayEmptyAchievementsDirect();
    return;
  }

  container.innerHTML = '';
  
  // 添加今日成就狀態
  if (todayStatus) {
    const statusDiv = document.createElement('div');
    statusDiv.style.cssText = 'margin-bottom: 15px; padding: 10px; background: #f0f8ff; border-radius: 5px; border: 1px solid #d0e7ff; text-align: center;';
    
    const remaining = todayStatus.remaining;
    const todayCount = todayStatus.today_count;
    
    if (remaining > 0) {
      statusDiv.innerHTML = `
        <div style="font-size: 14px; color: #0066cc; margin-bottom: 5px;">
          📅 今日已獲得 ${todayCount}/3 個成就
        </div>
        <div style="font-size: 12px; color: #0066cc;">
          還可獲得 ${remaining} 個成就 • 凌晨12點重置
        </div>
      `;
    } else {
      statusDiv.innerHTML = `
        <div style="font-size: 14px; color: #ff6b6b; margin-bottom: 5px;">
          📅 今日成就已達上限 (3/3)
        </div>
        <div style="font-size: 12px; color: #ff6b6b;">
          凌晨12點重置後可繼續獲得成就
        </div>
      `;
    }
    
    container.appendChild(statusDiv);
  }
  
  // 創建成就卡片容器
  const achievementsContainer = document.createElement('div');
  achievementsContainer.style.cssText = 'display: flex; justify-content: center; align-items: center; gap: 10px; flex-wrap: wrap;';
  
  // 顯示所有成就卡片
  const displayAchievements = achievements.slice(0, 4);
  
  displayAchievements.forEach((achievement, index) => {
    const card = document.createElement('div');
    card.className = 'profile-card';
    card.style.cursor = 'pointer';
    card.onclick = () => showAchievementDetailDirect(achievement);
    
    card.innerHTML = `
      <div class="emoji-icon" style="background:#97f55c;display:flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:10px;font-weight:bold;font-size:20px;color:#333;text-shadow:1px 1px 2px rgba(0,0,0,0.1);">${achievement.icon || '🏆'}</div>
      <div class="profile-card-label" style="font-size:12px;margin-top:5px;">${achievement.achievement_name}</div>
    `;
    
    achievementsContainer.appendChild(card);
  });
  
  container.appendChild(achievementsContainer);
}

// 顯示空成就狀態的備用函數
function displayEmptyAchievementsDirect() {
  const container = document.getElementById('achievementCards');
  
  if (!container) {
    console.error('找不到成就容器');
    return;
  }
  
  container.innerHTML = `
    <div style="text-align: center; padding: 20px; color: #666;">
      <div style="font-size: 48px; margin-bottom: 10px;">🎯</div>
      <div style="font-size: 16px; margin-bottom: 5px;">尚未獲得成就</div>
      <div style="font-size: 14px; color: #999;">完成遊戲來獲得成就稱號！</div>
    </div>
  `;
}

// 顯示成就詳情的備用函數
function showAchievementDetailDirect(achievement) {
  const date = new Date(achievement.earned_date).toLocaleDateString('zh-TW');
  alert(`${achievement.icon} ${achievement.achievement_name}\n\n📝 ${achievement.achievement_description}\n\n📅 獲得時間：${date}`);
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

<!-- 外部 JS -->
<script src="js/achievements.js"></script>
<script src="js/avatar-upload.js"></script>
<script src="js/global-invitation-checker.js"></script>

</body>
</html>
