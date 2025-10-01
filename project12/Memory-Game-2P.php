<?php
// 防止瀏覽器緩存
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// 如果是API請求，禁用錯誤輸出
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
    
    // 確保沒有額外的輸出
    ob_clean();
}

require_once 'check_login.php';
require_once 'db_connect.php';
require_once 'game_entry_tracker.php';

// 處理遊戲結果保存的 API 請求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);
   
    try {
        // 開始交易
        $pdo->beginTransaction();
       
        $gameId = 5;
       
        // 使用新追蹤邏輯 - 玩家1
        $record_id1 = recordGameEntry($data['player1_id'], '記憶力', $data['difficulty'], $gameId);
        if ($record_id1) {
            $play_time = isset($data['play_time']) ? $data['play_time'] : 0;
            $status = ($data['player1_score'] > 0) ? 'completed' : 'failed';
            updateGameRecord($record_id1, $data['player1_score'], $play_time, $status);
        }

        // 使用新追蹤邏輯 - 玩家2
        $record_id2 = recordGameEntry($data['player2_id'], '記憶力', $data['difficulty'], $gameId);
        if ($record_id2) {
            $play_time = isset($data['play_time']) ? $data['play_time'] : 0;
            $status = ($data['player2_score'] > 0) ? 'completed' : 'failed';
            updateGameRecord($record_id2, $data['player2_score'], $play_time, $status);
        }

        // 更新玩家1的總分數和記憶力分數
        $update_stmt = $pdo->prepare("UPDATE member SET total_score = total_score + :score, memory_score = memory_score + :score WHERE member_id = :member_id");
        $update_stmt->execute([
            'score' => $data['player1_score'],
            'member_id' => $data['player1_id']
        ]);

        // 更新玩家2的總分數和記憶力分數
        $update_stmt->execute([
            'score' => $data['player2_score'],
            'member_id' => $data['player2_id']
        ]);
        
        // 記錄雙人遊戲行為軌跡
        require_once 'log_game_behavior.php';
        logGameBehavior(
            $data['player1_id'], 
            '記憶力', 
            isset($data['play_time']) ? $data['play_time'] : 0, 
            $data['player1_score'], 
            $data['difficulty']
        );
        logGameBehavior(
            $data['player2_id'], 
            '記憶力', 
            isset($data['play_time']) ? $data['play_time'] : 0, 
            $data['player2_score'], 
            $data['difficulty']
        );
       
        // 提交交易
        $pdo->commit();
       
        echo json_encode(['success' => true, 'message' => '遊戲結果已儲存']);
        exit;
    } catch (Exception $e) {
        // 如果發生錯誤，回滾交易
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => '儲存失敗：' . $e->getMessage()]);
        exit;
    }
}

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

// 獲取主題列表
$stmt = $pdo->query("SELECT * FROM memory_game_themes WHERE is_active = true");
$themes = $stmt->fetchAll();

// 獲取難度設定
$stmt = $pdo->query("SELECT * FROM memory_game_difficulty_settings WHERE is_active = true");
$difficulties = $stmt->fetchAll();

// 獲取遊戲顏色
$stmt = $pdo->query("SELECT * FROM memory_game_colors WHERE is_active = true");
$colors = $stmt->fetchAll();

// 獲取遊戲顏色
$stmt = $pdo->query("SELECT * FROM memory_game_colors WHERE is_active = true");
$colors = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>翻牌對對樂 - 雙人模式</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🎮</text></svg>">
    <link rel="stylesheet" href="css/Memory-Game.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="css/Memory-Game-2P.css?v=<?php echo time(); ?>">
    
    <!-- 隱藏的用戶ID -->
    <input type="hidden" name="member_id" value="<?php echo $_SESSION['member_id']; ?>">
    <meta name="member_id" content="<?php echo $_SESSION['member_id']; ?>">

    <script src="js/unified-game-tracker.js"></script>
    <script>
        // 初始化遊戲追蹤器
        document.addEventListener("DOMContentLoaded", function() {
            gameTracker.init("記憶力", 5);
        });
    </script>
</head>
<body>
    <!-- 遊戲主畫面 -->
    <div id="game-container" class="game-container hidden">
        <h1>翻牌對對樂 - 雙人模式</h1>
        
        <!-- 玩家資訊面板 -->
        <div class="players-panel">
            <div class="player-info" id="player1-info">
                <div class="player-avatar">👤</div>
                <div class="player-details">
                    <div class="player-name">玩家 1</div>
                    <div class="player-score">分數: <span id="player1-score">0</span></div>
                    <div class="player-pairs">配對: <span id="player1-pairs">0</span></div>
                </div>
                <div class="current-player-indicator" id="player1-indicator">🎯</div>
            </div>
            
            <div class="game-stats">
                <div class="stat-item">
                    <span>總配對次數：</span>
                    <span id="total-moves">0</span>
                </div>
                <div class="stat-item">
                    <span>回合時間：</span>
                    <span id="turn-timer">10</span>
                </div>
            </div>
            
            <div class="player-info" id="player2-info">
                <div class="player-avatar">👤</div>
                <div class="player-details">
                    <div class="player-name">玩家 2</div>
                    <div class="player-score">分數: <span id="player2-score">0</span></div>
                    <div class="player-pairs">配對: <span id="player2-pairs">0</span></div>
                </div>
                <div class="current-player-indicator" id="player2-indicator">🎯</div>
            </div>
        </div>
        
        <div id="pause-indicator" class="pause-indicator hidden">
            <span>⏸️ 遊戲已暫停</span>
        </div>
        
        <div id="game-board" class="game-board">
        <!-- 卡片將由 JavaScript 動態生成 -->
        </div>
        
        <div class="control-buttons">
            <button id="pauseBtn" class="hidden">暫停遊戲</button>
            <button id="resumeBtn" class="hidden">繼續遊戲</button>
            <button id="endBtn" class="hidden">結束遊戲</button>
            <button id="resetBtn" class="hidden">重新開始</button>
            <button id="quitBtn" class="quit-btn" onclick="showQuitModal()">退出對戰</button>
        </div>
    </div>
    
    <!-- 好友邀請視窗 -->
    <div id="friend-invite-modal" class="modal">
        <div class="modal-content">
            <button class="back-button" onclick="history.back()" style="position:absolute;top:1rem;left:1.2rem;z-index:10;">
                <span class="back-arrow">⬅</span>
                <div class="back-label">返回</div>
            </button>
            <h2>邀請好友對戰</h2>
            <button class="help-button" onclick="showHelp()" style="position:absolute;top:1rem;right:1.2rem;z-index:10;">?
                <div class="help-label">說明</div>
            </button>
            
            <div class="invite-options">
                <div class="invite-option">
                    <h3>🎮 邀請好友</h3>
                    <p>從您的好友列表中選擇一位進行對戰</p>
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
                                        <button class="invite-friend-btn" onclick="if(typeof inviteFriend === 'function') { inviteFriend(<?php echo $friend['member_id']; ?>, '<?php echo htmlspecialchars($friend['member_name']); ?>'); } else { console.error('inviteFriend function not loaded'); }">
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
    <div id="waiting-modal" class="modal hidden">
        <div class="modal-content">
            <h2 id="waiting-title">等待好友回應</h2>
            <div class="waiting-content">
                <div class="loading-spinner"></div>
                <p id="waiting-message">正在等待 <span id="invited-friend-name"></span> 接受邀請...</p>
                <div class="waiting-actions">
                    <button onclick="cancelInvitation()" class="cancel-btn">取消邀請</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 收到遊戲邀請視窗 -->
    <div id="received-invitation-modal" class="modal hidden">
        <div class="modal-content">
            <h2>收到遊戲邀請</h2>
            <div class="invite-content">
                <p><span id="inviter-name"></span> 邀請您進行翻牌對戰</p>
                <div class="invite-actions">
                    <button onclick="acceptInvitation()" class="accept-btn">接受邀請</button>
                    <button onclick="rejectInvitation()" class="reject-btn">拒絕邀請</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 玩家設定視窗 -->
    <div id="player-setup-modal" class="modal hidden">
        <div class="modal-content">
            <div class="modal-header">
                <button class="back-button" onclick="showFriendInviteModal()">
                    <span class="back-arrow">⬅</span>
                    <div class="back-label">返回</div>
                </button>
                <h2 class="modal-title">雙人模式設定</h2>
                <button class="help-button" onclick="showHelp()">?
                    <div class="help-label">說明</div>
                </button>
            </div>
            
            <div class="player-setup">
                <div class="player-input">
                    <label for="player1-name">玩家 1 名稱：</label>
                    <input type="text" id="player1-name" placeholder="輸入玩家1名稱" value="玩家 1">
                </div>
                <div class="player-input">
                    <label for="player2-name">玩家 2 名稱：</label>
                    <input type="text" id="player2-name" placeholder="輸入玩家2名稱" value="玩家 2">
                </div>
            </div>
            
            <div class="setup-buttons">
                <button onclick="showThemeModal()">下一步：選擇主題</button>
            </div>
        </div>
    </div>
    
    <!-- 主題選擇視窗 -->
    <div id="theme-modal" class="modal hidden">
        <div class="modal-content">
            <div class="modal-header">
                <button id="backToSetupBtn" class="back-button" onclick="backToInviteFriends()">
                    <span class="back-arrow">⬅</span>
                    <div class="back-label">返回</div>
                </button>
                <h2 class="modal-title">選擇主題</h2>
                <button class="help-button" onclick="showHelp()">?
                    <div class="help-label">說明</div>
                </button>
            </div>
            <div class="theme-buttons">
                <button class="theme-btn fruit-theme" onclick="selectTheme('fruit')">
                    <span class="theme-icon">🍎</span>
                    <span class="theme-name">水果主題</span>
                </button>
                <button class="theme-btn animal-theme" onclick="selectTheme('animal')">
                    <span class="theme-icon">🐶</span>
                    <span class="theme-name">動物主題</span>
                </button>
                <button class="theme-btn daily-theme" onclick="selectTheme('daily')">
                    <span class="theme-icon">🕒</span>
                    <span class="theme-name">日常用品</span>
                </button>
                <button class="theme-btn vegetable-theme" onclick="selectTheme('vegetable')">
                    <span class="theme-icon">🥬</span>
                    <span class="theme-name">蔬菜主題</span>
                </button>
            </div>
        </div>
    </div>
    
    <!-- 難度選擇視窗 -->
    <div id="difficulty-modal" class="modal hidden">
        <div class="modal-content">
            <div class="modal-header">
                <button class="back-button" onclick="backToThemeSelection()" style="position:absolute;top:1rem;left:1.2rem;z-index:10;">
                    <span class="back-arrow">⬅</span>
                    <div class="back-label">返回</div>
                </button>
                <h2 class="modal-title">選擇難度</h2>
                <button class="help-button" onclick="showHelp()" style="position:absolute;top:1rem;right:1.2rem;z-index:10;">?
                    <div class="help-label">說明</div>
                </button>
            </div>
            <div class="difficulty-buttons">
                <button class="difficulty-btn easy" onclick="selectDifficulty('easy')">
                    <span class="difficulty-name">簡單模式</span>
                    <span class="difficulty-desc">(4x3 網格)</span>
                </button>
                <button class="difficulty-btn normal" onclick="selectDifficulty('normal')">
                    <span class="difficulty-name">普通模式</span>
                    <span class="difficulty-desc">(4x4 網格)</span>
                </button>
                <button class="difficulty-btn hard" onclick="selectDifficulty('hard')">
                    <span class="difficulty-name">困難模式</span>
                    <span class="difficulty-desc">(8x4 網格)</span>
                </button>
            </div>
        </div>
    </div>
    
    <!-- 遊戲說明視窗 -->
    <div id="help-modal" class="modal hidden">
        <div class="modal-content">
            <div class="help-header">
                <h2 style="margin:0;display:flex;align-items:center;justify-content:center;">
                    <span style="font-size:2rem;vertical-align:middle;margin-right:0.5rem;">🎮</span>
                <span style="font-weight:bold;vertical-align:middle;">遊戲說明</span>
            </h2>
                <span class="close-btn" onclick="closeHelpModal()">×</span>
            </div>
            <div class="help-content" style="margin-top:1.5rem;">
                <div style="display:flex;align-items:center;margin-bottom:0.5rem;">
                    <span style="color:#3b82f6;font-size:1.2rem;margin-right:0.5rem;">◆</span>
                    <span style="font-weight:bold;font-size:1.1rem;">目標</span>
                </div>
                <div style="margin-left:2.2rem;margin-bottom:1.2rem;">
                    兩位玩家輪流翻牌，找出配對的卡片，<br>配對最多的玩家獲勝！
                </div>
                <div style="display:flex;align-items:center;margin-bottom:0.5rem;">
                    <span style="color:#3b82f6;font-size:1.2rem;margin-right:0.5rem;">◆</span>
                    <span style="font-weight:bold;font-size:1.1rem;">玩法</span>
                </div>
                <ul style="margin-left:2.2rem;">
                    <li>邀請好友進行對戰</li>
                    <li>設定玩家名稱</li>
                    <li>選擇主題、難度</li>
                    <li>玩家輪流翻牌，配對成功可再翻一次</li>
                    <li>配對失敗換下一位玩家</li>
                    <li>時間結束時配對最多者獲勝</li>
                </ul>
            </div>
        </div>
    </div>
    
    <!-- 遊戲結束視窗 -->
    <div id="game-over-modal" class="modal hidden">
        <div class="modal-content">
            <h2 id="game-over-title">遊戲結束</h2>
            <div class="result-details">
                <div class="final-scores">
                    <div class="player-result" id="player1-result">
                        <span class="player-name"></span>
                        <span class="final-score"></span>
                        <span class="final-pairs"></span>
                    </div>
                    <div class="vs-indicator">VS</div>
                    <div class="player-result" id="player2-result">
                        <span class="player-name"></span>
                        <span class="final-score"></span>
                        <span class="final-pairs"></span>
                    </div>
                </div>
                <div class="winner-announcement" id="winner-announcement"></div>
                <p id="result-message"></p>
            </div>
            <div class="result-buttons">
                <button onclick="replayGame()">再玩一次</button>
                <button onclick="returnToMain()">返回主頁</button>
            </div>
        </div>
    </div>
    
    <!-- 邀請過期視窗 -->
    <div id="invitation-expired-modal" class="modal hidden">
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
    
    <!-- 退出對戰確認視窗 -->
    <div id="quit-game-modal" class="modal hidden">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">退出對戰</h2>
            </div>
            <div class="quit-content">
                <div class="quit-icon">🚪</div>
                <p class="quit-message">確定要退出當前對戰嗎？</p>
                <p class="quit-subtitle">退出後將無法繼續此局遊戲。</p>
            </div>
            <div class="quit-buttons">
                <button onclick="confirmQuitGame()" class="danger-btn">確定退出</button>
                <button onclick="hideQuitModal()" class="cancel-btn">取消</button>
            </div>
        </div>
    </div>
    
    <!-- 玩家退出提示視窗 -->
    <div id="player-quit-modal" class="modal hidden">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">對戰結束</h2>
            </div>
            <div class="player-quit-content">
                <div class="player-quit-icon">👋</div>
                <p class="player-quit-message">對手已退出對戰</p>

            </div>
            <div class="player-quit-buttons">
                <button onclick="returnToMainFromQuit()" class="primary-btn">返回主頁</button>
            </div>
        </div>
    </div>
    
    <!-- 好友拒絕邀請視窗 -->
    <div id="friend-reject-modal" class="modal hidden">
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
    <div id="return-confirm-modal" class="modal hidden">
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
    
    <!-- 退出對戰確認視窗 -->
    <div id="exit-battle-modal" class="modal hidden">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">退出對戰</h2>
            </div>
            <div class="exit-battle-content">
                <div class="exit-battle-icon">🚪</div>
                <p class="exit-battle-message">您已退出對戰</p>
                <p class="exit-battle-subtitle">對戰已結束，您可以重新開始新的對戰。</p>
            </div>
            <div class="exit-battle-buttons">
                <button onclick="hideExitBattleModal()" class="confirm-btn">確定</button>
                <button onclick="hideExitBattleModal()" class="cancel-btn">關閉</button>
            </div>
        </div>
    </div>
    
    <script>
        // 將PHP變數傳遞給JavaScript
        const themes = <?php echo json_encode($themes); ?>;
        const difficulties = <?php echo json_encode($difficulties); ?>;
        const colors = <?php echo json_encode($colors); ?>;
        const friends = <?php echo json_encode($friends); ?>;
        const phpCurrentUserId = <?php echo $_SESSION['member_id']; ?>;
        const currentUserName = '<?php echo htmlspecialchars($_SESSION['member_name'] ?? $_SESSION['name'] ?? '玩家'); ?>';
        
        // 如果 localStorage 沒有 member_id，就自動設一個
        if (!localStorage.getItem('member_id')) {
            localStorage.setItem('member_id', phpCurrentUserId);
        }
    </script>
    <script src="js/memory-game-http-sync.js?v=<?php echo time(); ?>"></script>
    <script src="js/websocket_client.js?v=<?php echo time(); ?>"></script>
    <script src="js/enhanced-sync.js?v=<?php echo time(); ?>"></script>
    <script src="js/memory-game-client.js?v=<?php echo time(); ?>"></script>
    <script src="js/Memory-Game-2P.js?v=<?php echo time(); ?>"></script>
    <script src="js/auto-save-time-fixed.js?v=<?php echo time(); ?>"></script>
    <script src="js/sync-optimization.js?v=<?php echo time(); ?>"></script>
    <script>
        // 初始化 WebSocket 事件處理器
        document.addEventListener('DOMContentLoaded', function() {
            if (window.memoryGameWebSocket) {
                // 處理玩家加入遊戲
                window.memoryGameWebSocket.on('playerJoined', function(data) {
                    console.log('玩家加入遊戲:', data);
                });
                
                // 處理困難度同步
                window.memoryGameWebSocket.on('difficultySynced', function(data) {
                    console.log('困難度同步:', data);
                    if (data.difficulty && data.difficulty !== currentDifficulty) {
                        currentDifficulty = data.difficulty;
                        // 重新初始化遊戲
                        setTimeout(() => {
                            initializeGame();
                        }, 200);
                    }
                });
                
                // 處理卡片翻轉
                window.memoryGameWebSocket.on('cardFlipped', function(data) {
                    console.log('卡片翻轉同步:', data);
                    // 確保是對手的翻牌動作
                    if (data.player_id !== getCurrentMemberId()) {
                        flipCard(data.index, data.value);
                    }
                });
                
                // 處理配對成功
                window.memoryGameWebSocket.on('matchSuccess', function(data) {
                    console.log('配對成功:', data);
                    // 標記卡片為已配對
                    data.indices.forEach(index => {
                        const card = document.querySelector(`[data-index="${index}"]`);
                        if (card) {
                            card.classList.add('matched');
                        }
                    });
                    
                    // 更新分數
                    updateScore(data.turn_player_id, data.score);
                    
                    // 配對成功，保持回合
                    isMyTurn = true;
                    canFlip = true;
                    flippedCards = [];
                    
                    updateCurrentPlayer();
                });
                
                // 處理配對失敗
                window.memoryGameWebSocket.on('matchFail', function(data) {
                    console.log('配對失敗:', data);
                    // 2秒後蓋回卡片
                    setTimeout(() => {
                        unflipCards(data.indices);
                    }, 2000);
                    
                    // 配對失敗，換人
                    isMyTurn = false;
                    canFlip = false;
                    flippedCards = [];
                    
                    updateCurrentPlayer();
                });
                
                // 處理回合切換
                window.memoryGameWebSocket.on('turnChanged', function(data) {
                    console.log('回合變更同步:', data);
                    isMyTurn = data.is_my_turn;
                    updateCurrentPlayer();
                    
                    // 如果不是我的回合，重置翻牌狀態
                    if (!isMyTurn) {
                        flippedCards = [];
                        canFlip = false;
                    } else {
                        canFlip = true;
                    }
                });
                
                // 處理玩家加入遊戲
                window.memoryGameWebSocket.on('playerJoined', function(data) {
                    console.log('玩家加入遊戲同步:', data);
                    // 確保回合指示器正確顯示
                    setTimeout(() => {
                        updateCurrentPlayer();
                    }, 100);
                });
                
                // 處理輪詢模式切換
                window.memoryGameWebSocket.on('pollingMode', function(data) {
                    console.log('切換到輪詢模式:', data.message);
                    // 顯示提示訊息
                    const statusDiv = document.createElement('div');
                    statusDiv.style.cssText = 'position: fixed; top: 10px; right: 10px; background: #ff9800; color: white; padding: 10px; border-radius: 5px; z-index: 9999;';
                    statusDiv.textContent = '使用輪詢模式同步';
                    document.body.appendChild(statusDiv);
                    
                    // 3秒後移除提示
                    setTimeout(() => {
                        if (statusDiv.parentNode) {
                            statusDiv.parentNode.removeChild(statusDiv);
                        }
                    }, 3000);
                });
                
                // 處理遊戲狀態更新
                window.memoryGameWebSocket.on('gameStateUpdate', function(gameState) {
                    console.log('收到遊戲狀態更新:', gameState);
                    if (typeof updateGameFromSync === 'function') {
                        updateGameFromSync(gameState);
                    }
                });
                
                // 處理邀請狀態更新
                window.memoryGameWebSocket.on('invitationStatusUpdate', function(data) {
                    console.log('收到邀請狀態更新:', data);
                    if (data.status === 'accepted' && data.game_settings) {
                        console.log('邀請已接受，遊戲設定已就緒');
                        if (typeof startOnlineGame === 'function') {
                            startOnlineGame(data);
                        }
                    }
                });
            }
        });
    </script>
    <script>
        // 在遊戲開始時初始化 WebSocket 和同步優化
        document.addEventListener('DOMContentLoaded', function() {
            // 延遲初始化，確保所有腳本都已載入
            setTimeout(() => {
                // 初始化 WebSocket 客戶端
                if (typeof WebSocket !== 'undefined') {
                    console.log('WebSocket 可用，初始化連接');
                    window.memoryGameWebSocket = new MemoryGameWebSocketClient();
                    window.memoryGameWebSocket.connect();
                    
                    // 添加連接狀態檢查
                    setTimeout(() => {
                        if (window.memoryGameWebSocket && !window.memoryGameWebSocket.isConnected) {
                            console.log('WebSocket 連接失敗，切換到輪詢模式');
                            window.memoryGameWebSocket.switchToPollingMode();
                        }
                    }, 3000);
                } else {
                    console.log('WebSocket 不可用，使用優化輪詢機制');
                    if (window.optimizedSync && window.optimizedSync.start) {
                        window.optimizedSync.start();
                    }
                }
            }, 1000);
        });
    </script>
</body>
</html> 