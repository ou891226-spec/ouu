<?php
require_once 'check_login.php';
$account = isset($_SESSION['account']) ? $_SESSION['account'] : '訪客';
$name = isset($_SESSION['name']) ? $_SESSION['name'] : '您好';
$avatar_url = isset($_SESSION['avatar_url']) && $_SESSION['avatar_url'] ? htmlspecialchars($_SESSION['avatar_url']) : 'img/big.jpg';
require_once "db.php";

function renderAvatar($username, $avatar = null) {
    if ($avatar) {
        return '<img class="ranking-avatar" src="' . htmlspecialchars($avatar) . '" alt="頭像">';
    } else {
        $firstChar = mb_substr($username, 0, 1, 'UTF-8');
        return '<div class="ranking-avatar generated-avatar">' . htmlspecialchars($firstChar) . '</div>';
    }
}

$current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'total';
$tabs = [
    'total' => '總排行榜',
    'reaction' => '反應力',
    'memory' => '記憶力',
    'logic' => '算術邏輯',
];

switch ($current_tab) {
    case 'reaction':
        $score_field = 'reaction_score';
        break;
    case 'memory':
        $score_field = 'memory_score';
        break;
    case 'logic':
        $score_field = 'logic_score';
        break;
    default:
        $score_field = 'total_score';
}

try {
    $sql = "SELECT member_id, member_name, account, $score_field, avatar FROM member ORDER BY $score_field DESC LIMIT 100";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $rankings = [];
    $rank = 1;
    $my_member_id = isset($_SESSION['member_id']) ? $_SESSION['member_id'] : null;
    
    foreach ($results as $row) {
        $is_me = ($my_member_id && $row['member_id'] == $my_member_id);
        $rankings[] = [
            'rank' => $rank++,
            'avatar' => !empty($row['avatar']) ? $row['avatar'] : null,
            'username' => $row['member_name'],
            'account' => $row['account'],
            'score' => $row[$score_field],
            'is_me' => $is_me
        ];
    }
} catch (Exception $e) {
    die('查詢失敗: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>排行榜</title>
  <link rel="stylesheet" href="css/main.css" />
  <link rel="stylesheet" href="css/mission.css" />
  <link rel="stylesheet" href="css/Ranking.css">
  <link rel="stylesheet" href="css/profile-modal.css" />
</head>
<body>

<div id="overlay" class="overlay" onclick="toggleSidebar()"></div>
<div id="modalOverlay" class="overlay" style="display:none;" onclick="closeAllModals()"></div>
<div id="sidebar" class="sidebar">
  <a href="index.php" class="jelly-btn jelly-red">首頁</a>
  <a href="game-category.php" class="jelly-btn jelly-red">🎮 全部遊戲</a>
  <a href="friend.php" class="jelly-btn jelly-green">好友列表</a>
  <a href="Ranking_list.php" class="jelly-btn jelly-green">排行榜</a>
  <div class="btn-group">
    <div class="personal-history-group">
      <button class="jelly-btn jelly-yellow" id="personalHistoryBtn" type="button" onclick="togglePersonalHistoryMenu()">個人歷程 <span id="arrowIcon" style="font-size: 20px !important; margin-left: 10px !important; color: #333 !important; font-weight: bold !important; display: inline-block !important; visibility: visible !important; opacity: 1 !important; text-shadow: 1px 1px 2px rgba(0,0,0,0.3) !important;">▼</span></button>
      <div id="personalHistoryMenu" class="personal-history-menu" style="display:none;">
        <a href="personal-analysis.php" class="jelly-btn jelly-yellow sub-btn">分析圖表</a>
      </div>
    </div>
    <a href="news.php" class="jelly-btn jelly-yellow">相關報導</a>
    <a href="us.php" class="jelly-btn jelly-yellow">關於我們</a>
  </div>
</div>

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

<div class="status-bar">
  <div class="score">您的分數 <span id="scoreValue" style="color: red;">0</span> 💰</div>
  <div class="time">
    已遊玩時間 <span id="timeValue">00:00:00</span>
    <button onclick="showTimeDetail()" class="time-icon-btn">⏱️</button>
  </div>
</div>

<!-- 排行榜區塊 -->
<div class="ranking-container">
  <div class="ranking-title">排行榜</div>
  <div class="tab-bar">
    <?php foreach ($tabs as $key => $label): ?>
      <button class="<?php echo $current_tab === $key ? 'active' : ''; ?>" onclick="location.href='?tab=<?php echo $key; ?>'">
        <?php echo $label; ?>
      </button>
    <?php endforeach; ?>
  </div>
  <div id="ranking-list" class="ranking-list">
    <!-- JS 動態載入排行榜 -->
  </div>
  <div id="loading-indicator" class="loading-indicator" style="display: none;">
    <div class="loading-spinner"></div>
    <span>載入中...</span>
  </div>
  <div id="my-ranking-row" class="ranking-row me" >
    <!-- JS 動態載入自己的排名 -->
  </div>
</div>

<div id="missionModal" class="mission-modal" style="display: none;">
  <div class="modal-content">
    <span class="close" onclick="closeMissionModal()">✕</span>
    <h2>每日任務</h2>
    <div id="daily-tasks-container"></div>
  </div>
</div>

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

// ====== 顯示所有用戶排行榜 ======
const tab = '<?php echo $current_tab; ?>';

function renderAvatar(username, avatar) {
  if (avatar) {
    return `<img class="ranking-avatar" src="${avatar}" alt="頭像">`;
  } else {
    const firstChar = username.charAt(0);
    return `<div class="ranking-avatar generated-avatar">${firstChar}</div>`;
  }
}

function renderRow(row, includeWrapper = true) {
  const innerHTML = `
    <div class="ranking-rank">${row.rank}</div>
    ${renderAvatar(row.username, row.avatar)}
    <div class="ranking-info">
      <div class="ranking-username">${row.username}</div>
      <div class="ranking-account">(${row.account})</div>
    </div>
    <div class="ranking-score">${row.score}</div>
  `;

  if (includeWrapper) {
    return `<div class="ranking-row${row.is_me ? ' me' : ''}">${innerHTML}</div>`;
  } else {
    return innerHTML;
  }
}

function loadAllRankings() {
  // 顯示載入指示器
  const loadingIndicator = document.getElementById('loading-indicator');
  loadingIndicator.style.display = 'flex';
  
  // 載入所有用戶（設定一個很大的 limit）
  fetch(`ranking_api.php?offset=0&limit=1000&tab=${tab}`)
    .then(res => res.json())
    .then(data => {
      const list = document.getElementById('ranking-list');
      
      // 隱藏載入指示器
      loadingIndicator.style.display = 'none';
      
      // 清空現有內容
      list.innerHTML = '';
      
      // 顯示所有用戶
      data.rankings.forEach(row => {
        list.insertAdjacentHTML('beforeend', renderRow(row));
      });
      
      // 顯示自己的排名
      if (data.my_ranking) {
        document.getElementById('my-ranking-row').innerHTML = renderRow(data.my_ranking, false);
      }
    })
    .catch(error => {
      console.error('載入排行榜失敗:', error);
      loadingIndicator.style.display = 'none';
      
      // 顯示錯誤訊息
      const list = document.getElementById('ranking-list');
      list.innerHTML = '<div class="error-message">載入排行榜失敗，請重新整理頁面</div>';
    });
}

document.addEventListener('DOMContentLoaded', function() {
  loadAllRankings();
});
</script>
<script src="js/auto-save-time-fixed.js"></script>
<script src="js/load-daily-tasks.js"></script>
<script src="js/mission.js"></script>
<script src="js/save-score.js"></script>
<script src="js/get-score.js"></script>
<script src="js/achievements.js"></script>
<script src="js/avatar-upload.js"></script>

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
  loadUserAchievements(); // 載入成就
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

  function openMissionModal() {
    const modal = document.getElementById('missionModal');
    const overlay = document.getElementById('modalOverlay');
    
    modal.style.display = 'flex';
    if (overlay) overlay.style.display = 'block';
    
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
      if (overlay) overlay.style.display = 'none';
    }, 150);
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
</body>
</html>
