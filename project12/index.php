<?php
// 設定 PHP 執行時間和記憶體限制，避免 502 錯誤
ini_set('max_execution_time', 30); // 30 秒執行時間限制
ini_set('memory_limit', '128M'); // 128MB 記憶體限制

require_once 'check_login.php';
require_once 'avatar_helper.php';
$account = isset($_SESSION['account']) ? $_SESSION['account'] : '訪客';
$name = isset($_SESSION['name']) ? $_SESSION['name'] : '使用者';

// 強制從資料庫讀取頭像路徑，忽略Session中的舊值
require_once 'db.php';
try {
    $sql = "SELECT avatar FROM member WHERE member_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$_SESSION['member_id']]);
    $result = $stmt->fetch();
    
    if ($result && $result['avatar']) {
        // 如果資料庫有頭像路徑，使用它
        $avatar_url = $result['avatar'];
        // 同時更新Session
        $_SESSION['avatar_url'] = $avatar_url;
    } else {
        // 如果資料庫沒有頭像路徑，強制生成
        $avatar_url = getAvatarPath($_SESSION['member_id'], $name, null);
        // 更新資料庫
        if ($avatar_url) {
            $update_sql = "UPDATE member SET avatar = ? WHERE member_id = ?";
            $update_stmt = $pdo->prepare($update_sql);
            $update_stmt->execute([$avatar_url, $_SESSION['member_id']]);
        }
    }
} catch (Exception $e) {
    // 如果出錯，使用預設邏輯
    $avatar_url = getAvatarPath($_SESSION['member_id'], $name, null);
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>遊戲首頁</title>
  <link rel="stylesheet" href="css/main.css" />
  <link rel="stylesheet" href="css/mission.css" />
  <link rel="stylesheet" href="css/profile-modal.css" />
  <link rel="stylesheet" href="css/global-invitation.css" />
</head>
<body>

<!-- 黑色半透明背景 (彈窗遮罩) -->
<div id="modalOverlay" class="overlay" style="display:none;"></div>
<!-- 側邊欄遮罩 -->
<div id="overlay" class="overlay" onclick="toggleSidebar()"></div>

<!-- 側邊欄 -->
<div id="sidebar" class="sidebar">
  <a href="index.php" class="jelly-btn jelly-red">首頁</a>
  <a href="game-category.php" class="jelly-btn jelly-red">全部遊戲</a>
  <a href="friend.php" class="jelly-btn jelly-green">好友列表</a>
  <a href="Ranking_list.php" class="jelly-btn jelly-green">排行榜</a>
  <div class="btn-group">
    <a href="personal-analysis.php" class="jelly-btn jelly-yellow">個人分析</a>
    <a href="news.php" class="jelly-btn jelly-yellow">相關報導</a>
    <a href="us.php" class="jelly-btn jelly-yellow">關於我們</a>
  </div>
</div>

<!-- 功能選單 -->
<header>
  <div id="menuButton" class="menu" onclick="toggleSidebar()">
    <img src="img/contents.png" alt="功能選單" class="menu-icon" />
    <span id="menuText" class="menu-text">功能選單</span>
  </div>

  <form class="search-bar" action="game.php" method="GET" onsubmit="return validateSearch()">
    <input type="text" name="keyword" id="searchInput" placeholder="搜尋遊戲" />
  </form>

  <div class="user-icons">
    <a href="#" onclick="openMissionModal(event); return false;">
      <span class="notification-bell">🔔</span>
    </a>
    <a href="#" onclick="openProfileModal(event); return false;">
      <img src="<?php echo htmlspecialchars($avatar_url); ?>" alt="使用者" class="profile" />
    </a>
  </div>
</header>

<!-- 狀態列 -->
<div class="status-bar">
  <div class="score">
    您的分數 <span id="scoreValue" style="color: red;">0</span> 💰
  </div>

  <div class="time">
    已遊玩時間 <span id="timeValue">00:00:00</span>
    <button onclick="showTimeDetail()" class="time-icon-btn">⏱️</button>
  </div>
</div>

<!-- 浮動全部遊戲按鈕 -->
<div class="floating-all-games-btn">
  <a href="game-category.php" class="floating-btn" title="查看全部遊戲">
    <span class="floating-btn-icon">🎮</span>
    <span class="floating-btn-text">全部遊戲</span>
  </a>
</div>

<!-- 熱門遊戲 -->
<div class="section">
  <h2>熱門遊戲</h2>
  <div class="game-grid" id="popularGamesGrid">
    <!-- 載入中... -->
    <div class="game-block">
      <div class="game-item">
        <div style="display: flex; align-items: center; justify-content: center; height: 250px; background: #f0f0f0; border-radius: 10px;">
          <span style="color: #666;">載入中...</span>
        </div>
      </div>
      <div class="game-title">載入中...</div>
    </div>
  </div>
</div>

<!-- 最近常玩 -->
<div class="section">
  <h2>最近常玩</h2>
  <div class="game-grid" id="recentGamesGrid">
    <!-- 載入中... -->
    <div class="game-block">
      <div class="game-item">
        <div style="display: flex; align-items: center; justify-content: center; height: 250px; background: #f0f0f0; border-radius: 10px;">
          <span style="color: #666;">載入中...</span>
        </div>
      </div>
      <div class="game-title">載入中...</div>
    </div>
  </div>
</div>

<!-- 你的每日任務彈窗保留不動 -->
<div id="missionModal" class="mission-modal" style="display:none;">
  <div class="modal-content">
    <span class="close" onclick="closeMissionModal()">✕</span>
    <h2>每日任務</h2>
    <div id="daily-tasks-container"></div>
  </div>
</div>

<!-- 新增的個人資訊彈窗 -->
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

<script>
  let sidebarOpen = false;

  function toggleSidebar() {
    const sidebar = document.getElementById("sidebar");
    const overlay = document.getElementById("overlay");
    const menuText = document.getElementById("menuText");
    if (!sidebarOpen) {
      sidebar.style.left = "0";
      overlay.style.display = "block";
      menuText.style.display = "none";
    } else {
      sidebar.style.left = "-300px";
      overlay.style.display = "none";
      menuText.style.display = "inline";
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
    
    // 更新自動顯示設置的checkbox狀態
    updateAutoShowCheckbox();
  }
  
  // 移除手動刷新功能 - 任務完全固定，只有凌晨12點重置
  function refreshMissionTasks() {
    // 完全禁用手動刷新
    alert('任務已固定，請等待凌晨12點重置！');
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

  // previewAndUploadAvatar 函數已在 avatar-upload.js 中定義

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

  // 當個人資訊彈窗打開時載入成就
  function openProfileModal() {
    document.getElementById('profileModal').style.display = 'flex';
    document.getElementById('modalOverlay').style.display = 'block';
    
    // 檢查 loadUserAchievements 函數是否已載入
    if (typeof loadUserAchievements === 'function') {
      loadUserAchievements(); // 載入成就
    } else {
      console.log('loadUserAchievements 函數尚未載入，稍後再試');
      // 延遲執行，等待 JS 文件載入完成
      setTimeout(() => {
        if (typeof loadUserAchievements === 'function') {
          loadUserAchievements();
        } else {
          console.error('無法載入成就：loadUserAchievements 函數不存在');
        }
      }, 100);
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

<script src="js/auto-save-time-fixed.js"></script>
<script>
// 檢查是否有快取清除參數
if (window.location.search.includes('cache_cleared')) {
  console.log("檢測到快取清除參數，強制重新載入任務");
  // 清除所有任務相關快取
  localStorage.removeItem('missionLoadDate');
  localStorage.removeItem('missionLoadedToday');
  localStorage.removeItem('missionShownToday');
  localStorage.removeItem('daily_tasks_cache');
  sessionStorage.clear();
  
  // 強制重新載入任務
  setTimeout(() => {
    if (typeof loadDailyTasks === 'function') {
      loadDailyTasks();
    }
  }, 1000);
}
</script>
<script src="js/load-daily-tasks.js"></script>
<script src="js/mission.js"></script>
<script src="js/save-score.js"></script>
<script src="js/get-score.js"></script>
<script src="js/achievements.js"></script>
<script src="js/avatar-upload.js"></script>
<script src="js/global-invitation-checker.js"></script>

<script>
// 檢查每日重置
document.addEventListener('DOMContentLoaded', function() {
    fetch('check_daily_reset.php')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.message.includes('每日重置已執行')) {
                console.log('每日重置已執行，重新載入任務...');
                // 只有在真正需要重置時才重新載入任務
                // 清除localStorage，讓任務重新載入
                localStorage.removeItem('missionLoadDate');
                localStorage.removeItem('missionLoadedToday');
                // 重新載入每日任務
                if (window.loadDailyTasks) {
                    window.loadDailyTasks();
                }
            }
        })
        .catch(error => {
            console.error('檢查每日重置失敗:', error);
        });
});
</script>

<script>
// 載入最近遊戲
function loadRecentGames() {
  // 添加時間戳防止緩存
  const timestamp = new Date().getTime();
  fetch(`get_recent_games.php?t=${timestamp}`)
    .then(response => response.json())
    .then(data => {
      console.log('API 響應:', data);
      if (data.success) {
        displayRecentGames(data.data);
      } else {
        console.error('載入最近遊戲失敗:', data.message);
        displayDefaultGames();
      }
    })
    .catch(error => {
      console.error('載入最近遊戲時發生錯誤:', error);
      displayDefaultGames();
    });
}

// 顯示最近遊戲
function displayRecentGames(games) {
  const grid = document.getElementById('recentGamesGrid');
  grid.innerHTML = '';
  
  // 如果沒有遊戲記錄，顯示提示訊息
  if (games.length === 0) {
    grid.innerHTML = `
      <div style="grid-column: 1 / -1; text-align: center; padding: 40px 20px; color: #666; font-size: 1.1em;">
        <div style="font-size: 3em; margin-bottom: 15px;">🎮</div>
        <div style="margin-bottom: 10px;">還沒有遊玩記錄</div>
        <div style="font-size: 0.9em; color: #888;">快去體驗遊戲吧！</div>
      </div>
    `;
    return;
  }
  
  games.forEach(game => {
    const gameBlock = document.createElement('div');
    gameBlock.className = 'game-block';
    
    gameBlock.innerHTML = `
      <div class="game-item">
        <a href="${game.link}">
          <img src="${game.img}" alt="${game.title}" />
        </a>
      </div>
      <div class="game-title">${game.title}</div>
    `;
    
    grid.appendChild(gameBlock);
  });
}

// 顯示預設遊戲（當載入失敗時）
function displayDefaultGames() {
  const grid = document.getElementById('recentGamesGrid');
  grid.innerHTML = `
    <div class="game-block">
      <div class="game-item">
        <a href="Catch-Egg-Game.php"><img src="img/egg1.png" alt="接金蛋" /></a>
      </div>
      <div class="game-title">接金蛋</div>
    </div>
    <div class="game-block">
      <div class="game-item">
        <a href="rhythm_game.php"><img src="img/rhythm1.png" alt="節奏遊戲" /></a>
      </div>
      <div class="game-title">節奏遊戲</div>
    </div>
    <div class="game-block">
      <div class="game-item">
        <a href="text-color.php"><img src="img/text_color111.png" alt="看字選色"></a>
      </div>
      <div class="game-title">看字選色</div>
    </div>
  `;
}

// 載入熱門遊戲
function loadPopularGames() {
  fetch('get_popular_games.php')
    .then(response => response.json())
    .then(data => {
      console.log('熱門遊戲 API 響應:', data);
      if (data.success) {
        displayPopularGames(data.data);
      } else {
        console.error('載入熱門遊戲失敗:', data.message);
        displayDefaultPopularGames();
      }
    })
    .catch(error => {
      console.error('載入熱門遊戲時發生錯誤:', error);
      displayDefaultPopularGames();
    });
}

// 顯示熱門遊戲
function displayPopularGames(games) {
  const grid = document.getElementById('popularGamesGrid');
  grid.innerHTML = '';
  
  games.forEach(game => {
    const gameBlock = document.createElement('div');
    gameBlock.className = 'game-block';
    
    gameBlock.innerHTML = `
      <div class="game-item">
        <a href="${game.link}">
          <img src="${game.img}" alt="${game.title}" />
        </a>
      </div>
      <div class="game-title">${game.title}</div>
    `;
    
    grid.appendChild(gameBlock);
  });
}

// 顯示預設熱門遊戲（當載入失敗時）
function displayDefaultPopularGames() {
  const grid = document.getElementById('popularGamesGrid');
  grid.innerHTML = `
    <div class="game-block">
      <div class="game-item">
        <a href="2048ht.php"><img src="img/game_20481.png" alt="2048" /></a>
      </div>
      <div class="game-title">2048</div>
    </div>
    <div class="game-block">
      <div class="game-item">
        <a href="Memory-Game.php"><img src="img/card1.png" alt="翻牌對對樂" /></a>
      </div>
      <div class="game-title">翻牌對對樂</div>
    </div>
    <div class="game-block">
      <div class="game-item">
        <a href="prisoner.php"><img src="img/prisoner1.png" alt="追蹤犯人"></a>
      </div>
      <div class="game-title">追蹤犯人</div>
    </div>
  `;
}



// 頁面載入時執行
document.addEventListener('DOMContentLoaded', function() {
  loadRecentGames();
  loadPopularGames();
  
  // 檢查任務狀態並更新鈴鐺
  setTimeout(() => {
    checkPendingTasks();
  }, 1000); // 延遲1秒載入，確保頁面完全載入
  
  // 每30秒檢查一次任務狀態
  setInterval(checkPendingTasks, 30000);
});

// 手動檢查任務狀態（只在需要時調用）
function checkAndUpdateTasks() {
  // 這個函數現在被禁用了，因為任務已經固定
  // 如果需要檢查任務狀態，請使用手動刷新按鈕
  console.log('任務狀態檢查已禁用，任務已固定');
}

// 檢查是否有未完成任務並更新鈴鐺狀態
function checkPendingTasks() {
  fetch('get_daily_tasks_fixed.php')
    .then(response => response.json())
    .then(tasks => {
      const bell = document.querySelector('.notification-bell');
      if (!bell) return;
      
      // 檢查是否有未完成的任務
      const hasPendingTasks = tasks.some(task => {
        const current = parseInt(task.progress) || 0;
        const required = parseInt(task.required) || 1;
        return current < required && task.status !== 'claimed';
      });
      
      // 檢查是否有已完成但未領取的任務
      const hasCompletedTasks = tasks.some(task => {
        const current = parseInt(task.progress) || 0;
        const required = parseInt(task.required) || 1;
        return current >= required && task.status !== 'claimed';
      });
      
      // 移除舊的狀態類別
      bell.classList.remove('has-pending', 'has-completed');
      
      if (hasCompletedTasks) {
        // 有可領取的任務 - 金色閃爍
        bell.classList.add('has-completed');
      } else if (hasPendingTasks) {
        // 有進行中的任務 - 普通動畫
        bell.classList.add('has-pending');
      }
    })
    .catch(error => {
      console.log('檢查任務狀態失敗:', error);
    });
}
</script>


</body>
</html>
