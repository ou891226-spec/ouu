<?php
require_once 'check_login.php';
require_once 'rhythm_game_db.php';

// 獲取當前用戶的好友列表
$my_id = $_SESSION['member_id'];
$sql = "
    SELECT m.member_id, m.member_name, m.account, m.avatar
    FROM friends f
    JOIN member m ON f.friend_id = m.member_id
    WHERE f.member_id = ?
    ORDER BY m.member_name
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$my_id]);
$friends = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 獲取難度設定
$difficulties = [];

try {
    $stmt = $pdo->query("SELECT * FROM rhythm_game_difficulty_settings WHERE is_active = true");
    $difficulties = $stmt->fetchAll();
} catch (PDOException $e) {
    $difficulties = [
        ['difficulty_level' => 'easy', 'note_count' => 3, 'time_limit' => 60, 'speed' => 1.0, 'pass_score' => 100, 'is_active' => true],
        ['difficulty_level' => 'normal', 'note_count' => 5, 'time_limit' => 90, 'speed' => 1.5, 'pass_score' => 200, 'is_active' => true],
        ['difficulty_level' => 'hard', 'note_count' => 7, 'time_limit' => 120, 'speed' => 2.0, 'pass_score' => 300, 'is_active' => true]
    ];
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>雙人節奏對戰</title>
  <link rel="stylesheet" href="css/rhythm_game_multiplayer.css" />
</head>
<body>
  <!-- 遊戲主畫面 -->
  <div class="multiplayer-game-container" id="game-container" style="display: none;">
    <input type="hidden" id="member-id" value="<?php echo $_SESSION['member_id'] ?? 1; ?>">

    <!-- 玩家 1 畫面 -->
    <div class="player-area" id="player-top">
      <div class="player-name" id="player1-name">玩家 1</div>
      <div class="game-info">
        <div class="info-item">
          <span class="info-label">目前分數:</span>
          <span class="info-value score-value" id="score-top">0</span>
        </div>
        <div class="info-item">
          <span class="info-label">剩餘時間:</span>
          <span class="info-value time-value" id="timer-top">60</span>
          <span class="info-label">秒</span>
        </div>
      </div>
      <div class="noteTrack" id="noteTrack-top"></div>
      <div class="hitZone" id="hitZone-top">
        <span class="hit-label">打擊區</span>
        <div class="bat" id="bat-top">
          <img src="img/bat.png" alt="球棒" />
        </div>
      </div>
    </div>

    <!-- 玩家 2 畫面 -->
    <div class="player-area" id="player-bottom">
      <div class="player-name" id="player2-name">玩家 2</div>
      <div class="game-info">
        <div class="info-item">
          <span class="info-label">目前分數:</span>
          <span class="info-value score-value" id="score-bottom">0</span>
        </div>
        <div class="info-item">
          <span class="info-label">剩餘時間:</span>
          <span class="info-value time-value" id="timer-bottom">60</span>
          <span class="info-label">秒</span>
        </div>
      </div>
      <div class="noteTrack" id="noteTrack-bottom"></div>
      <div class="hitZone" id="hitZone-bottom">
        <span class="hit-label">打擊區</span>
        <div class="bat" id="bat-bottom">
          <img src="img/bat.png" alt="球棒" />
        </div>
      </div>
    </div>

    <div class="button-group">
      <button class="control-btn" id="pause-btn">暫停遊戲</button>
      <button class="control-btn" id="end-btn">結束遊戲</button>
      <button class="control-btn" id="restart-btn">重新開始</button>
    </div>

    <audio id="bgm" preload="auto"></audio>
  </div>

  <!-- 好友邀請視窗 -->
  <div id="friend-invite-modal" class="modal show">
    <div class="modal-content">
      <button class="back-button" onclick="handleBackButton()">
        <span class="back-arrow">⬅</span>
        <div class="back-label">返回</div>
      </button>
      <h2>邀請好友對戰</h2>
      <button class="help-button" onclick="showHelp()">
        <span class="help-btn">?</span>
        <div class="help-label">說明</div>
      </button>
      
      <div class="invite-options">
        <div class="invite-option">
          <h3>🎮 邀請好友</h3>
          <p>從您的好友列表中選擇一位進行節奏對戰</p>
          <div class="friend-list-container">
            <?php if (empty($friends)): ?>
              <div class="no-friends">
                <p>您還沒有好友</p>
                <button onclick="window.location.href='add-friend.php'" class="add-friend-btn">添加好友</button>
              </div>
            <?php else: ?>
              <div class="friend-list">
                <?php foreach ($friends as $friend): ?>
                  <div class="friend-item" data-friend-id="<?php echo $friend['member_id']; ?>" data-friend-name="<?php echo htmlspecialchars($friend['member_name']); ?>">
                    <img src="<?php echo htmlspecialchars($friend['avatar'] ?? 'img/user.png'); ?>" class="friend-avatar" alt="頭像">
                    <div class="friend-info">
                      <div class="friend-name"><?php echo htmlspecialchars($friend['member_name']); ?></div>
                      <div class="friend-account"><?php echo htmlspecialchars($friend['account']); ?></div>
                    </div>
                    <button class="invite-friend-btn" onclick="inviteFriend(<?php echo $friend['member_id']; ?>, '<?php echo htmlspecialchars($friend['member_name']); ?>')">
                      邀請對戰
                    </button>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- 等待好友接受邀請視窗 -->
  <div id="waiting-modal" class="modal">
    <div class="modal-content">
      <h2 id="waiting-title">等待遊戲設定</h2>
      <div class="waiting-content">
        <div class="loading-spinner"></div>
        <p id="waiting-message">正在等待邀請者設定遊戲...</p>
        <div class="waiting-actions">
          <button onclick="cancelInvitation()" class="cancel-btn">取消邀請</button>
        </div>
      </div>
    </div>
  </div>

  <!-- 收到遊戲邀請視窗 -->
  <div id="received-invitation-modal" class="modal">
    <div class="modal-content">
      <h2>收到遊戲邀請</h2>
      <div class="invite-content">
        <p><span id="inviter-name"></span> 邀請你進行節奏遊戲</p>
        <div class="invite-actions">
          <button onclick="acceptInvitation()" class="accept-btn">接受邀請</button>
          <button onclick="rejectInvitation()" class="reject-btn">拒絕邀請</button>
        </div>
      </div>
    </div>
  </div>

  <!-- 難度選擇 Modal -->
  <div id="difficulty-modal" class="modal">
    <div class="modal-content">
      <!-- 🔙 返回鍵 -->
      <button class="back-button" onclick="backFromDifficultyModal()">
        <span class="back-arrow">⬅</span>
        <div class="back-label">返回</div>
      </button>

      <!-- ❓ 說明鍵 -->
      <button class="help-button" onclick="showHelp()">
        <span class="help-btn">?</span>
        <div class="help-label">說明</div>
      </button>

      <h2>難度選擇</h2>
      <div class="difficulty-options">
        <div class="difficulty-option easy" data-difficulty="easy">簡單 (3個)</div>
        <div class="difficulty-option medium" data-difficulty="normal">普通 (5個)</div>
        <div class="difficulty-option hard" data-difficulty="hard">困難 (7個)</div>
      </div>
    </div>
  </div>

  <!-- 🟡 遊戲說明 Modal -->
  <div id="info-modal" class="modal">
    <div class="modal-content">
      <h2 class="info-title">
        <span class="game-icon">🎮</span>
        <span class="title-text">遊戲說明</span>
      </h2>
      <div class="help-content">
        <div class="help-section">
          <span class="section-icon">◆</span>
          <span class="section-title">目標</span>
        </div>
        <div class="section-content">
          兩位玩家同時進行節奏遊戲，跟著音符的節奏點擊打擊區，時間內累積分數較高者獲勝！
        </div>
        <div class="help-section">
          <span class="section-icon">◆</span>
          <span class="section-title">玩法</span>
        </div>
        <ul class="game-rules">
          <li>邀請好友進行對戰</li>
          <li>選擇難度後開始遊戲</li>
          <li>跟著音符節奏點擊打擊區</li>
          <li>Miss不給分、Good+10分、Perfect+20分</li>
          <li>時間內累積分數較高者獲勝！</li>
        </ul>
      </div>
      <span class="close-btn" onclick="closeInfoModal()">×</span>
    </div>
  </div>

  <!-- 🎉 結果視窗 -->
  <div id="result-modal" class="modal">
    <div class="modal-content">
      <h2 id="result-title"></h2>
      <p id="result-difficulty"></p>
      <p id="result-score"></p>
      <div class="result-buttons">
        <button onclick="location.reload()">再玩一次</button>
        <button onclick="window.location.href='index.php'">返回主頁</button>
      </div>
    </div>
  </div>

  <!-- 邀請過期視窗 -->
  <div id="invitation-expired-modal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title">邀請已過期</h2>
      </div>
      <div class="expired-content">
        <div class="expired-icon">⏰</div>
        <p class="expired-message">很抱歉，您的遊戲邀請已經過期了。</p>
        <p class="expired-subtitle">請重新發送邀請或選擇其他好友進行對戰。</p>
      </div>
      <div class="expired-buttons">
        <button onclick="hideExpiredModal()" class="primary-btn">確定</button>
      </div>
    </div>
  </div>

  <!-- 好友拒絕邀請視窗 -->
  <div id="friend-reject-modal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title">邀請被拒絕</h2>
      </div>
      <div class="reject-content">
        <div class="reject-icon">❌</div>
        <p class="reject-message">好友拒絕了您的邀請</p>
        <p class="reject-subtitle">您可以邀請其他好友或稍後再試。</p>
      </div>
      <div class="reject-buttons">
        <button onclick="hideRejectModal()" class="primary-btn">確定</button>
      </div>
    </div>
  </div>

  <!-- 返回確認對話框 -->
  <div id="return-confirm-modal" class="modal">
    <div class="modal-content return-confirm-content">
      <div class="modal-header">
        <h2 class="modal-title">確認返回</h2>
      </div>
      <div class="return-confirm-body">
        <div class="return-confirm-icon">⚠️</div>
        <p class="return-confirm-message">您正在進行線上對戰，返回將自動退出戰局。</p>
        <p class="return-confirm-subtitle">確定要返回嗎？</p>
      </div>
      <div class="return-confirm-buttons">
        <button onclick="confirmReturn()" class="danger-btn">確定</button>
        <button onclick="cancelReturn()" class="cancel-btn">取消</button>
      </div>
    </div>
  </div>

  <script>
    // 將PHP變數傳遞給JavaScript
    const friends = <?php echo json_encode($friends); ?>;
    const phpCurrentUserId = <?php echo $_SESSION['member_id']; ?>;
    const currentUserName = '<?php echo htmlspecialchars($_SESSION['member_name'] ?? $_SESSION['name'] ?? '玩家'); ?>';
    
    // 如果 localStorage 沒有 member_id，就自動設一個
    if (!localStorage.getItem('member_id')) {
      localStorage.setItem('member_id', phpCurrentUserId);
    }
  </script>
  <script src="js/rhythm_game_multiplayer.js"></script>
</body>
</html>
