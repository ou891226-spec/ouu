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
        WHERE fr.sender_id = ? AND fr.status != 'accepted'
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
  <style>
    /* 交友邀請通知徽章樣式 */
    .invite-btn {
      position: relative;
    }
    
    .invitation-badge {
      position: absolute;
      top: -8px;
      right: -8px;
      background: #ff4444;
      color: white;
      border-radius: 50%;
      min-width: 20px;
      height: 20px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 12px;
      font-weight: bold;
      border: 2px solid white;
      box-shadow: 0 2px 4px rgba(0,0,0,0.2);
      transition: all 0.3s ease;
    }
    
    /* 有邀請時的樣式 */
    .invitation-badge.has-invitations {
      background: #ff4444;
      animation: pulse 2s infinite;
    }
    
    /* 無邀請時的樣式 */
    .invitation-badge.no-invitations {
      background: #cccccc;
      animation: none;
      min-width: 16px;
      height: 16px;
      font-size: 10px;
      border: 1px solid #999999;
    }
    
    /* 錯誤狀態的樣式 */
    .invitation-badge.error-state {
      background: #ffa500;
      animation: none;
    }
    
    @keyframes pulse {
      0% { transform: scale(1); }
      50% { transform: scale(1.1); }
      100% { transform: scale(1); }
    }
    
    /* 通知樣式 */
    .notification-container {
      position: fixed;
      top: 20px;
      right: 20px;
      z-index: 10000;
      pointer-events: none;
    }
    
    .notification {
      background: #fff;
      border-radius: 8px;
      padding: 16px 20px;
      margin-bottom: 10px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
      border-left: 4px solid;
      font-size: 14px;
      font-weight: 500;
      max-width: 300px;
      opacity: 0;
      transform: translateX(100%);
      transition: all 0.3s ease;
      pointer-events: auto;
      position: relative;
    }
    
    .notification.show {
      opacity: 1;
      transform: translateX(0);
    }
    
    .notification.success {
      border-left-color: #4CAF50;
      color: #2E7D32;
      background: linear-gradient(135deg, #E8F5E8 0%, #F1F8E9 100%);
    }
    
    .notification.error {
      border-left-color: #f44336;
      color: #C62828;
      background: linear-gradient(135deg, #FFEBEE 0%, #FFCDD2 100%);
    }
    
    .notification::before {
      content: '';
      position: absolute;
      left: 8px;
      top: 50%;
      transform: translateY(-50%);
      width: 6px;
      height: 6px;
      border-radius: 50%;
      background: currentColor;
    }
    
    .notification-text {
      margin-left: 16px;
    }
    
    @media (max-width: 768px) {
      .notification-container {
        top: 10px;
        right: 10px;
        left: 10px;
      }
      
      .notification {
        max-width: none;
      }
    }
    
    /* 刪除好友彈窗樣式 */
    .delete-modal {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.5);
      z-index: 10001;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    
    .delete-modal-content {
      background: white;
      border-radius: 12px;
      padding: 30px;
      max-width: 400px;
      width: 90%;
      text-align: center;
      box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
      animation: modalFadeIn 0.3s ease;
    }
    
    @keyframes modalFadeIn {
      from {
        opacity: 0;
        transform: scale(0.9);
      }
      to {
        opacity: 1;
        transform: scale(1);
      }
    }
    
    .delete-modal-content h3 {
      margin: 0 0 20px 0;
      color: #333;
      font-size: 20px;
      font-weight: bold;
    }
    
    .delete-modal-content p {
      margin: 0 0 30px 0;
      color: #666;
      font-size: 16px;
      line-height: 1.5;
    }
    
    .delete-modal-buttons {
      display: flex;
      gap: 15px;
      justify-content: center;
    }
    
    .delete-modal-btn {
      padding: 12px 24px;
      border: none;
      border-radius: 8px;
      font-size: 16px;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.2s ease;
      min-width: 100px;
    }
    
    .cancel-btn {
      background: #f5f5f5;
      color: #666;
    }
    
    .cancel-btn:hover {
      background: #e8e8e8;
      color: #333;
    }
    
    .confirm-btn {
      background: #ff4757;
      color: white;
    }
    
    .confirm-btn:hover {
      background: #ff3838;
    }
    
    .delete-modal-content .confirm-btn {
      background: #4CAF50;
    }
    
    .delete-modal-content .confirm-btn:hover {
      background: #45a049;
    }
  </style>
</head>
<body>

<!-- 黑色半透明背景 -->
<div id="overlay" class="overlay" onclick="toggleSidebar()"></div>
<div id="modalOverlay" class="overlay" style="display:none;"></div>

<!-- 側邊欄 -->
<div id="sidebar" class="sidebar">
  <a href="index.php" class="jelly-btn jelly-red">首頁</a>
  <a href="game-category.php" class="jelly-btn jelly-red">全部遊戲</a>
  <a href="friend.php" class="jelly-btn jelly-green">好友列表</a>
  <a href="Ranking_list.php" class="jelly-btn jelly-green">排行榜</a>
  <div class="btn-group">
    <div class="personal-history-group">
      <a href="personal-analysis.php" class="jelly-btn jelly-yellow">個人分析</a>
    </div>
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
    <button class="invite-btn" onclick="window.location.href='invitation-friend.php'" id="invitationBtn">
      &#128276; 交友邀請
      <span class="invitation-badge" id="invitationBadge" style="display: none;"></span>
    </button>
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
            <?php if ($invite['status'] === 'pending'): ?>
            <div style="font-size:12px;color:#888;margin-top:4px;">
              狀態：待處理
            </div>
            <?php endif; ?>
          </div>
          <button class="delete-friend-btn" data-request-id="<?php echo $invite['request_id']; ?>" data-receiver-name="<?php echo htmlspecialchars($invite['member_name']); ?>">&#128465;</button>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<!-- 通知容器 -->
<div id="notification-container" class="notification-container"></div>

<!-- 刪除好友確認彈窗 -->
<div id="deleteConfirmModal" class="delete-modal" style="display: none;">
  <div class="delete-modal-content">
    <h3>確認刪除好友</h3>
    <p id="deleteConfirmMessage">確定要移除好友「」嗎？</p>
    <div class="delete-modal-buttons">
      <button class="delete-modal-btn cancel-btn" onclick="closeDeleteConfirmModal()">取消</button>
      <button class="delete-modal-btn confirm-btn" onclick="confirmDeleteFriend()">確定刪除</button>
    </div>
  </div>
</div>

<!-- 刪除結果彈窗 -->
<div id="deleteResultModal" class="delete-modal" style="display: none;">
  <div class="delete-modal-content">
    <h3 id="deleteResultTitle">操作結果</h3>
    <p id="deleteResultMessage"></p>
    <div class="delete-modal-buttons">
      <button class="delete-modal-btn confirm-btn" onclick="closeDeleteResultModal()">確定</button>
    </div>
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
// 強制刷新緩存版本 v4.0
console.log('Friend.php script loaded at:', new Date().toISOString());
console.log('Script version: 4.0 - Fixed deleteInfo null issue in confirmDeleteFriend');

// 測試函數是否正確定義
setTimeout(() => {
  console.log('函數定義檢查:');
  console.log('window.confirmDeleteFriend:', typeof window.confirmDeleteFriend);
  console.log('window.showDeleteConfirmModal:', typeof window.showDeleteConfirmModal);
  console.log('window.closeDeleteConfirmModal:', typeof window.closeDeleteConfirmModal);
  console.log('window.deleteInfo:', window.deleteInfo);
}, 1000);

// 頁面載入時立即檢查邀請數量
window.addEventListener('load', function() {
  checkInvitationCount();
});

// 腳本載入完成後立即檢查一次
setTimeout(() => {
  checkInvitationCount();
}, 100);
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

// 檢查交友邀請數量並更新徽章
function checkInvitationCount() {
  fetch('get_invitation_count.php')
    .then(response => response.json())
    .then(data => {
      const badge = document.getElementById('invitationBadge');
      if (!badge) return;
      
      // 移除所有狀態類別
      badge.classList.remove('has-invitations', 'no-invitations', 'error-state');
      
      if (data.success) {
        badge.style.display = 'flex';
        
        // 根據數量添加對應的樣式類別
        if (data.count > 0) {
          badge.textContent = data.count;
          badge.classList.add('has-invitations'); // 紅色 + 脈衝動畫
          badge.style.display = 'flex';
        } else {
          badge.style.display = 'none'; // 無邀請時隱藏徽章
        }
      } else {
        // 即使獲取失敗也顯示徽章
        badge.textContent = '?';
        badge.style.display = 'flex';
        badge.classList.add('error-state'); // 橙色 + 無動畫
      }
    })
    .catch(error => {
      console.log('檢查邀請數量失敗:', error);
      const badge = document.getElementById('invitationBadge');
      if (badge) {
        badge.classList.remove('has-invitations', 'no-invitations', 'error-state');
        badge.textContent = '?';
        badge.style.display = 'flex';
        badge.classList.add('error-state'); // 橙色 + 無動畫
      }
    });
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

// 刪除好友功能
document.addEventListener('DOMContentLoaded', function() {
  // 立即檢查任務狀態和邀請數量
  checkPendingTasks();
  checkInvitationCount();
  
  // 每30秒檢查一次任務狀態和邀請數量
  setInterval(checkPendingTasks, 30000);
  setInterval(checkInvitationCount, 30000);
  
  // 為所有刪除按鈕添加事件監聽器
  const deleteButtons = document.querySelectorAll('.delete-friend-btn');
  
  deleteButtons.forEach(button => {
    button.addEventListener('click', function() {
      const friendId = this.getAttribute('data-id');
      const requestId = this.getAttribute('data-request-id');
      const friendRow = this.closest('.friend-row');
      const friendNameElement = friendRow.querySelector('.friend-name');
      const receiverName = this.getAttribute('data-receiver-name');
      
      console.log('刪除按鈕被點擊:', { friendId, requestId, friendRow, friendNameElement, receiverName });
      
      // 判斷是刪除好友還是取消邀請
      if (friendId) {
        // 刪除好友
        if (!friendNameElement) {
          console.error('無法獲取好友名稱元素');
          showDeleteErrorModal('無法獲取好友信息，請重新載入頁面');
          return;
        }
        
        const friendName = friendNameElement.textContent;
        showDeleteConfirmModal(friendName, friendId, friendRow, 'friend');
      } else if (requestId) {
        // 取消邀請
        if (!receiverName) {
          console.error('無法獲取接收者名稱');
          showDeleteErrorModal('無法獲取邀請信息，請重新載入頁面');
          return;
        }
        
        showDeleteConfirmModal(receiverName, requestId, friendRow, 'invitation');
      } else {
        console.error('無法獲取刪除目標ID');
        showDeleteErrorModal('無法獲取刪除目標，請重新載入頁面');
        return;
      }
    });
  });
});

function deleteFriend(friendId, friendRow) {
  console.log('=== deleteFriend 開始 ===');
  console.log('friendId:', friendId);
  console.log('friendRow:', friendRow);
  console.log('typeof friendRow:', typeof friendRow);
  
  if (!friendRow) {
    console.error('friendRow 參數為 null 或 undefined！');
    showDeleteErrorModal('好友行信息錯誤，請重新操作');
    return;
  }
  
  // 顯示刪除中狀態
  const deleteBtn = friendRow.querySelector('.delete-friend-btn');
  const originalText = deleteBtn.innerHTML;
  deleteBtn.innerHTML = '⏳';
  deleteBtn.disabled = true;
  
  // 發送刪除請求
  fetch('delete-friend.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: `friend_id=${friendId}`
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      // 成功刪除，移除該行
      friendRow.style.opacity = '0.5';
      friendRow.style.transition = 'opacity 0.3s ease';
      
      setTimeout(() => {
        friendRow.remove();
        
        // 檢查是否還有好友
        const friendList = document.querySelector('.friend-list');
        const remainingFriends = friendList.querySelectorAll('.friend-row');
        
        if (remainingFriends.length === 0) {
          friendList.innerHTML = '<div class="empty-state" style="text-align: center; padding: 20px; color: #666;">目前沒有好友</div>';
        }
      }, 300);
      
      showDeleteSuccessModal('已成功移除好友');
    } else {
      // 刪除失敗，恢復按鈕
      deleteBtn.innerHTML = originalText;
      deleteBtn.disabled = false;
      showDeleteErrorModal('刪除好友失敗：' + data.message);
    }
  })
  .catch(error => {
    console.error('刪除好友時發生錯誤：', error);
    // 恢復按鈕
    deleteBtn.innerHTML = originalText;
    deleteBtn.disabled = false;
    showDeleteErrorModal('刪除好友時發生錯誤，請稍後再試');
  });
}

function deleteInvitation(requestId, invitationRow) {
  console.log('=== deleteInvitation 開始 ===');
  console.log('requestId:', requestId);
  console.log('invitationRow:', invitationRow);
  console.log('typeof invitationRow:', typeof invitationRow);
  
  if (!invitationRow) {
    console.error('invitationRow 參數為 null 或 undefined！');
    showDeleteErrorModal('邀請行信息錯誤，請重新操作');
    return;
  }
  
  // 顯示刪除中狀態
  const deleteBtn = invitationRow.querySelector('.delete-friend-btn');
  const originalText = deleteBtn.innerHTML;
  deleteBtn.innerHTML = '⏳';
  deleteBtn.disabled = true;
  
  // 發送刪除請求
  fetch('delete-invitation.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: `request_id=${requestId}`
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      // 成功刪除，移除該行
      invitationRow.style.opacity = '0.5';
      invitationRow.style.transition = 'opacity 0.3s ease';
      
      setTimeout(() => {
        invitationRow.remove();
        
        // 檢查是否還有邀請
        const invitationContainer = invitationRow.closest('.friend-container');
        const invitationList = invitationContainer.querySelector('.friend-list');
        const remainingInvitations = invitationList.querySelectorAll('.friend-row');
        
        if (remainingInvitations.length === 0) {
          invitationList.innerHTML = '<div class="empty-state">目前沒有送出的邀請</div>';
        }
      }, 300);
      
      showDeleteSuccessModal('已成功取消邀請');
    } else {
      // 刪除失敗，恢復按鈕
      deleteBtn.innerHTML = originalText;
      deleteBtn.disabled = false;
      showDeleteErrorModal('取消邀請失敗：' + data.message);
    }
  })
  .catch(error => {
    console.error('取消邀請時發生錯誤：', error);
    // 恢復按鈕
    deleteBtn.innerHTML = originalText;
    deleteBtn.disabled = false;
    showDeleteErrorModal('取消邀請時發生錯誤，請稍後再試');
  });
}

// 全域變數儲存刪除資訊
window.deleteInfo = null;

// 顯示刪除確認彈窗
window.showDeleteConfirmModal = function(name, id, row, type) {
  // 添加調試信息
  console.log('showDeleteConfirmModal 參數:', { name, id, row, type });
  
  if (!id) {
    console.error('id 是空的！');
    showDeleteErrorModal('無法獲取目標ID，請重新載入頁面');
    return;
  }
  
  window.deleteInfo = { id: id, row: row, type: type };
  
  const modal = document.getElementById('deleteConfirmModal');
  const message = document.getElementById('deleteConfirmMessage');
  
  if (type === 'friend') {
    message.textContent = `確定要移除好友「${name}」嗎？`;
  } else if (type === 'invitation') {
    message.textContent = `確定要取消對「${name}」的邀請嗎？`;
  } else {
    message.textContent = `確定要刪除「${name}」嗎？`;
  }
  
  modal.style.display = 'flex';
};

// 關閉刪除確認彈窗
window.closeDeleteConfirmModal = function() {
  document.getElementById('deleteConfirmModal').style.display = 'none';
  window.deleteInfo = null;
};

// 確認刪除好友或邀請
window.confirmDeleteFriend = function() {
  console.log('=== confirmDeleteFriend 開始 ===');
  console.log('window.deleteInfo:', window.deleteInfo);
  console.log('typeof window.deleteInfo:', typeof window.deleteInfo);
  
  if (!window.deleteInfo) {
    console.error('deleteInfo 是空的！');
    showDeleteErrorModal('刪除信息丟失，請重新操作');
    return;
  }
  
  console.log('window.deleteInfo.id:', window.deleteInfo.id);
  console.log('window.deleteInfo.row:', window.deleteInfo.row);
  console.log('window.deleteInfo.type:', window.deleteInfo.type);
  
  if (!window.deleteInfo.id) {
    console.error('deleteInfo.id 是空的！');
    showDeleteErrorModal('目標ID丟失，請重新操作');
    return;
  }
  
  if (!window.deleteInfo.row) {
    console.error('deleteInfo.row 是空的！');
    showDeleteErrorModal('行信息丟失，請重新操作');
    return;
  }
  
  console.log('準備調用刪除函數');
  
  // 先保存 deleteInfo 的值，因為 closeDeleteConfirmModal 會將其設為 null
  const id = window.deleteInfo.id;
  const row = window.deleteInfo.row;
  const type = window.deleteInfo.type;
  
  closeDeleteConfirmModal();
  
  if (type === 'friend') {
    deleteFriend(id, row);
  } else if (type === 'invitation') {
    deleteInvitation(id, row);
  } else {
    showDeleteErrorModal('未知的刪除類型');
  }
  
  console.log('=== confirmDeleteFriend 結束 ===');
};

// 顯示刪除成功彈窗
function showDeleteSuccessModal(message) {
  const modal = document.getElementById('deleteResultModal');
  const title = document.getElementById('deleteResultTitle');
  const messageEl = document.getElementById('deleteResultMessage');
  
  title.textContent = '刪除成功';
  title.style.color = '#4CAF50';
  messageEl.textContent = message;
  
  modal.style.display = 'flex';
}

// 顯示刪除錯誤彈窗
function showDeleteErrorModal(message) {
  const modal = document.getElementById('deleteResultModal');
  const title = document.getElementById('deleteResultTitle');
  const messageEl = document.getElementById('deleteResultMessage');
  
  title.textContent = '刪除失敗';
  title.style.color = '#ff4757';
  messageEl.textContent = message;
  
  modal.style.display = 'flex';
}

// 關閉結果彈窗
function closeDeleteResultModal() {
  document.getElementById('deleteResultModal').style.display = 'none';
}

// 顯示通知函數
function showNotification(message, type = 'success') {
  const container = document.getElementById('notification-container');
  
  // 創建通知元素
  const notification = document.createElement('div');
  notification.className = `notification ${type}`;
  notification.innerHTML = `<div class="notification-text">${message}</div>`;
  
  // 添加到容器
  container.appendChild(notification);
  
  // 觸發顯示動畫
  setTimeout(() => {
    notification.classList.add('show');
  }, 10);
  
  // 3秒後自動移除
  setTimeout(() => {
    notification.classList.remove('show');
    setTimeout(() => {
      if (notification.parentNode) {
        notification.parentNode.removeChild(notification);
      }
    }, 300);
  }, 3000);
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
  <div class="avatar-info">
    <small>📝 支援 JPG、PNG、GIF 格式 | 💾 建議小於 2MB，最大 5MB</small>
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
  
  // 延遲載入成就，確保achievements.js已經載入
  setTimeout(function() {
    // 檢查loadUserAchievements函數是否存在
    if (typeof loadUserAchievements === 'function') {
      console.log('loadUserAchievements函數存在，開始載入成就');
      loadUserAchievements();
    } else {
      console.error('loadUserAchievements函數不存在！');
      // 如果函數不存在，直接調用API
      loadUserAchievementsDirect();
    }
  }, 100); // 延遲100毫秒
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
