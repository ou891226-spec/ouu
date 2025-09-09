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

// 處理取得食材資料的 API 請求（在登入檢查之前）
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['get_ingredients'])) {
    require_once 'db_connect.php';
    header('Content-Type: application/json');
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM vegetable_cost_ingredients ORDER BY category, name");
        $stmt->execute();
        $ingredients = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($ingredients);
        exit;
    } catch (Exception $e) {
        echo json_encode(['error' => '取得食材資料失敗：' . $e->getMessage()]);
        exit;
    }
}

require_once 'check_login.php';
require_once 'db_connect.php';

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




// 處理遊戲結果保存的 API 請求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);
   
    try {
        // 開始交易
        $pdo->beginTransaction();
       
        $gameId = 3; // 算菜錢遊戲的 ID
       
        // 保存玩家1的記錄
        $stmt = $pdo->prepare("
            INSERT INTO game_records
            (member_id, game_id, difficulty, score, play_date, play_time, game_type, is_single_player, opponent_id)
            VALUES (:member_id, :game_id, :difficulty, :score, NOW(), :play_time, :game_type, :is_single_player, :opponent_id)
        ");
        $stmt->execute([
            'member_id' => $data['player1_id'],
            'game_id' => $gameId,
            'difficulty' => $data['difficulty'],
            'score' => $data['player1_score'],
            'play_time' => isset($data['play_time']) ? $data['play_time'] : null,
            'game_type' => '算數邏輯力',
            'is_single_player' => 0,
            'opponent_id' => $data['player2_id']
        ]);

        // 保存玩家2的記錄（本地玩家）
        if ($data['player2_id'] !== 'local_player') {
            $stmt->execute([
                'member_id' => $data['player2_id'],
                'game_id' => $gameId,
                'difficulty' => $data['difficulty'],
                'score' => $data['player2_score'],
                'play_time' => isset($data['play_time']) ? $data['play_time'] : null,
                'game_type' => '算數邏輯力',
                'is_single_player' => 0,
                'opponent_id' => $data['player1_id']
            ]);
        }

        // 更新玩家1的總分數和邏輯力分數
        $update_stmt = $pdo->prepare("UPDATE member SET total_score = total_score + :score, logic_score = logic_score + :score WHERE member_id = :member_id");
        $update_stmt->execute([
            'score' => $data['player1_score'],
            'member_id' => $data['player1_id']
        ]);

        // 如果是本地玩家，不更新玩家2的分數
        if ($data['player2_id'] !== 'local_player') {
            $update_stmt->execute([
                'score' => $data['player2_score'],
                'member_id' => $data['player2_id']
            ]);
        }
        
        // 記錄雙人遊戲行為軌跡
        require_once 'log_game_behavior.php';
        logGameBehavior(
            $data['player1_id'], 
            '算數邏輯力', 
            isset($data['play_time']) ? $data['play_time'] : 0, 
            $data['player1_score'], 
            $data['difficulty']
        );
        // 只有真實玩家才記錄行為軌跡
        if ($data['player2_id'] !== 'local_player') {
            logGameBehavior(
                $data['player2_id'], 
                '算數邏輯力', 
                isset($data['play_time']) ? $data['play_time'] : 0, 
                $data['player2_score'], 
                $data['difficulty']
            );
        }
       
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

// 邀請系統相關函數




// 獲取當前用戶資訊
$my_id = $_SESSION['member_id'];
$stmt = $pdo->prepare("SELECT member_id, member_name, account, avatar FROM member WHERE member_id = ?");
$stmt->execute([$my_id]);
$current_user = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>算菜錢遊戲 - 雙人模式</title>
    <link rel="stylesheet" href="css/vegetable_cost_2P.css">
</head>
<body>
    <input type="hidden" id="member-id" value="<?php echo $_SESSION['member_id']; ?>">

    <!-- 好友邀請視窗 -->
    <div id="friend-invite-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <button class="back-button" onclick="window.location.href='index.php'">
                    <span class="back-label">返回</span>
                </button>
                <h2 class="modal-title">邀請好友對戰</h2>
                <button class="help-button" onclick="openHelpModal()">
                    <span class="help-label">說明</span>
                </button>
            </div>
            
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

    <!-- 等待對手選擇難度視窗 -->
    <div id="waiting-difficulty-modal" class="modal hidden">
        <div class="modal-content">
            <h2 id="waiting-difficulty-title">等待對手選擇難度</h2>
            <div class="waiting-difficulty-content">
                <div class="loading-spinner"></div>
                <p id="waiting-difficulty-message">正在等待 <span id="opponent-name"></span> 選擇遊戲難度...</p>
                <div class="waiting-difficulty-actions">
                    <button onclick="cancelInvitation()" class="cancel-btn">取消對戰</button>
                </div>
            </div>
        </div>
    </div>

    <!-- 收到遊戲邀請視窗 -->
    <div id="received-invitation-modal" class="modal hidden">
        <div class="modal-content">
            <h2>收到遊戲邀請</h2>
            <div class="invite-content">
                <p><span id="inviter-name"></span> 邀請您進行算菜錢對戰</p>
                <div class="invite-actions">
                    <button onclick="acceptInvitation()" class="accept-btn">接受邀請</button>
                    <button onclick="rejectInvitation()" class="reject-btn">拒絕邀請</button>
                </div>
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

    <!-- 好友拒絕邀請視窗 -->
    <div id="friend-reject-modal" class="modal hidden">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">邀請被拒絕</h2>
            </div>
            <div class="reject-content">
                <div class="reject-icon">❌</div>
                <p class="reject-message">好友拒絕了您的邀請
                    <button class="restore-btn" onclick="restoreInvitation()">往上還原</button>
                </p>
                <p class="reject-subtitle">您可以邀請其他好友或稍後再試。</p>
            </div>
            <div class="reject-buttons">
                <button onclick="hideRejectModal()" class="primary-btn">確定</button>
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

    <!-- 難度選擇視窗 -->
    <div id="difficulty-modal" class="modal hidden">
        <div class="modal-content">
            <div class="modal-header">
                <button class="back-button" onclick="showFriendInviteModal()">
                    <span class="back-label">返回</span>
                </button>
                <h2 class="modal-title">選擇難度</h2>
                <button class="help-button" onclick="openHelpModal()">
                    <span class="help-label">說明</span>
                </button>
            </div>
            <div class="difficulty-content">
                <div class="difficulty-description">
                    <span class="game-icon">🎮</span>
                    <span>算菜錢遊戲 - 雙人模式</span>
                </div>
                <div class="difficulty-options">
                    <button class="difficulty-btn easy" onclick="selectDifficulty('easy')">
                        <span class="difficulty-icon">🌱</span>
                        <span class="difficulty-name">簡單</span>
                        <span class="difficulty-desc">適合初學者</span>
                    </button>
                    <button class="difficulty-btn normal" onclick="selectDifficulty('normal')">
                        <span class="difficulty-icon">🌿</span>
                        <span class="difficulty-name">普通</span>
                        <span class="difficulty-desc">一般難度</span>
                    </button>
                    <button class="difficulty-btn hard" onclick="selectDifficulty('hard')">
                        <span class="difficulty-icon">🌳</span>
                        <span class="difficulty-name">困難</span>
                        <span class="difficulty-desc">挑戰高難度</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- 遊戲主界面 -->
    <div id="game-container" class="game-container" style="display:none;">
        <h1 class="main-title">算菜錢遊戲 - 雙人模式</h1>
        
        <!-- 玩家資訊面板 -->
        <div class="players-panel">
            <div class="player-info" id="player1-info">
                <div class="player-avatar">
                    <?php
                    $avatar_path = '';
                    if ($current_user['avatar']) {
                        $full_path = 'img/avatars/' . $current_user['avatar'];
                        if (file_exists($full_path)) {
                            $avatar_path = $full_path;
                        } else {
                            // 如果檔案不存在，嘗試找到類似的檔案
                            $dir = 'img/avatars/';
                            $filename = basename($current_user['avatar'], '.png');
                            $files = glob($dir . $filename . '*');
                            if (!empty($files)) {
                                $avatar_path = $files[0]; // 使用第一個找到的檔案
                            }
                        }
                    }
                    if (!$avatar_path) {
                        $avatar_path = 'img/user.png';
                    }
                    ?>
                    <img src="<?php echo $avatar_path; ?>" alt="玩家1頭像">
                </div>
                <div class="player-details">
                    <div class="player-name" id="player1-name-display"><?php echo htmlspecialchars($current_user['member_name']); ?></div>
                    <div class="player-score">分數：<span id="player1-score">0</span></div>
                    <div class="player-correct">答對：<span id="player1-correct">0</span></div>
                </div>
                <div class="current-player-indicator">🎯</div>
            </div>
            
            <div class="game-stats">
                <div class="stat-item">
                    <span>剩餘時間：</span>
                    <span id="timer" class="score-red">60</span>
                </div>
                <div class="stat-item">
                    <span>當前回合：</span>
                    <span id="current-turn">玩家1</span>
                </div>
                <div class="stat-item">
                    <span>總題數：</span>
                    <span id="total-questions">0</span>
                </div>
            </div>
            
            <div class="player-info" id="player2-info">
                <div class="player-avatar">
                    <img src="img/user.png" alt="玩家2頭像" id="player2-avatar">
                </div>
                <div class="player-details">
                    <div class="player-name" id="player2-name-display">玩家2</div>
                    <div class="player-score">分數：<span id="player2-score">0</span></div>
                    <div class="player-correct">答對：<span id="player2-correct">0</span></div>
                </div>
                <div class="current-player-indicator">🎯</div>
            </div>
        </div>

        <div id="question-container" class="main-question-container">
            <div id="question"></div>
            <div id="options-container" class="main-options-container"></div>
        </div>
        
        <div class="main-control-btns">
            <button id="pause-btn" class="main-btn pause-btn">暫停遊戲</button>
            <button id="end-btn" class="main-btn end-btn" onclick="endGame()">結束遊戲</button>
            <button id="restart-btn" class="main-btn restart-btn" onclick="restartGame()">重新開始</button>
        </div>
    </div>

    <!-- 遊戲結束視窗 -->
    <div id="game-over-modal" class="modal hidden">
        <div class="modal-content gameover-modal-content">
            <h2 class="gameover-title">遊戲結束</h2>
            <div class="final-scores">
                <div class="player-result" id="player1-result">
                    <div class="player-name"></div>
                    <div class="final-score"></div>
                    <div class="final-correct"></div>
                </div>
                <div class="vs-indicator">VS</div>
                <div class="player-result" id="player2-result">
                    <div class="player-name"></div>
                    <div class="final-score"></div>
                    <div class="final-correct"></div>
                </div>
            </div>
            <div class="winner-announcement" id="winner-announcement"></div>
            <div class="gameover-btn-group">
                <button id="modal-restart-btn" class="gameover-btn retry-btn" onclick="restartGame()">再玩一次</button>
                <button id="exit-btn" class="gameover-btn home-btn" onclick="exitGame()">返回主頁</button>
            </div>
        </div>
    </div>

    <!-- 遊戲說明視窗 -->
    <div id="help-modal" class="modal hidden">
        <div class="modal-content">
            <h2 style="text-align:center;">
                <span style="font-size:2rem;vertical-align:middle;">🎮</span>
                <span style="font-weight:bold;vertical-align:middle;">雙人遊戲說明</span>
            </h2>
            <div class="help-content" style="margin-top:1.5rem;">
                <div style="display:flex;align-items:center;margin-bottom:0.5rem;">
                    <span style="color:#3b82f6;font-size:1.2rem;margin-right:0.5rem;">◆</span>
                    <span style="font-weight:bold;font-size:1.1rem;">目標</span>
                </div>
                <div style="margin-left:2.2rem;margin-bottom:1.2rem;">
                    兩位玩家輪流答題，計算阿嬤買菜的總金額，看看誰能得更高分！
                </div>
                <div style="display:flex;align-items:center;margin-bottom:0.5rem;">
                    <span style="color:#3b82f6;font-size:1.2rem;margin-right:0.5rem;">◆</span>
                    <span style="font-weight:bold;font-size:1.1rem;">玩法</span>
                </div>
                <ul style="margin-left:2.2rem;">
                    <li>邀請好友進行對戰</li>
                    <li>兩位玩家輪流答題</li>
                    <li>每答對一題得3分</li>
                    <li>答錯不扣分，但會輪到另一位玩家</li>
                    <li>時間結束後比較總分決定勝負</li>
                    <li>簡單模式：15分過關獲得20分獎勵</li>
                    <li>中等模式：20分過關獲得50分獎勵</li>
                    <li>困難模式：25分過關獲得100分獎勵</li>
                </ul>
            </div>
            <span class="close-btn" onclick="closeHelpModal()" style="position:absolute; top:10px; right:15px; cursor:pointer; font-size: 30px;">×</span>
        </div>
    </div>

    <script>
        // 將PHP變數傳遞給JavaScript
        window.phpMemberId = <?php echo $_SESSION['member_id']; ?>;
        window.currentUser = <?php echo json_encode($current_user); ?>;
        window.friends = <?php echo json_encode($friends); ?>;
    </script>
    <script src="js/vegetable_cost_2P.js"></script>
</body>
</html>