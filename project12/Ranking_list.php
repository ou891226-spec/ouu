<?php
require_once 'check_login.php';
require_once "DB_open.php";

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
    'logic' => '算數邏輯',
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

// 改用 PDO 查詢排行榜
$sql = "SELECT member_id, member_name, account, $score_field, avatar FROM member ORDER BY $score_field DESC LIMIT 100";
$stmt = $pdo->query($sql);
if (!$stmt) {
    die('查詢失敗: ' . print_r($pdo->errorInfo(), true));
}

$rankings = [];
$rank = 1;
$my_member_id = isset($_SESSION['member_id']) ? $_SESSION['member_id'] : null;

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
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

<header>
  <div id="menuButton" class="menu" onclick="toggleSidebar()">
    <img src="img/contents.jpg" alt="目錄" class="menu-icon">
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

// ====== 無限滾動排行榜 ======
let offset = 0;
const limit = 5;
let loading = false;
let end = false;
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

function loadRankings() {
  if (loading || end) return;
  loading = true;
  fetch(`ranking_api.php?offset=${offset}&limit=${limit}&tab=${tab}`)
    .then(res => res.json())
    .then(data => {
      const list = document.getElementById('ranking-list');
      if (data.rankings.length < limit) end = true;
      data.rankings.forEach(row => {
        list.insertAdjacentHTML('beforeend', renderRow(row));
      });
      offset += data.rankings.length;
      loading = false;
      // 首次載入時顯示自己的排名
      if (offset === data.rankings.length && data.my_ranking) {
        document.getElementById('my-ranking-row').innerHTML = renderRow(data.my_ranking, false);
      }
    });
}

window.addEventListener('scroll', function() {
  if (end || loading) return;
  const scrollY = window.scrollY || window.pageYOffset;
  const winH = window.innerHeight;
  const docH = document.body.offsetHeight;
  if (docH - (scrollY + winH) < 100) {
    loadRankings();
  }
});

document.addEventListener('DOMContentLoaded', function() {
  loadRankings();
});
</script>
<script src="js/auto-save-time.js"></script>
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
  <div class="profile-cards">
    <div class="profile-card">
      <img src="img/vegetable.jpg" alt="遊戲成就" />
      <div class="profile-card-label">遊戲成就</div>
    </div>
    <div class="profile-card">
      <span class="emoji-icon">😲</span>
      <div class="profile-card-label">任務達人</div>
    </div>
    <div class="profile-card">
      <img src="img/rhythm.jpg" alt="反應達人" />
      <div class="profile-card-label">反應達人</div>
    </div>
    <div class="profile-card">
      <div class="emoji-icon" style="background:#ffe082;display:flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:10px;font-weight:bold;font-size:20px;">2048</div>
      <div class="profile-card-label">記憶達人</div>
    </div>
  </div>
  <div class="profile-actions">
    <button class="profile-btn profile-manage"><span style="font-size:18px;">🖊️</span> 管理帳戶</button>
    <a href="logout.php" class="profile-btn profile-logout"><span style="font-size:18px;">[→]</span> 登出</a>
  </div>
</div>

<script>
function openProfileModal() {
  document.getElementById('profileModal').style.display = 'flex';
  document.getElementById('modalOverlay').style.display = 'block';
}
function closeProfileModal() {
  document.getElementById('profileModal').style.display = 'none';
  document.getElementById('modalOverlay').style.display = 'none';
}

function closeAllModals() {
  closeProfileModal();
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
</script>
</body>
</html>
