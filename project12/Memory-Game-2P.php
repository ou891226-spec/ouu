<?php
require_once 'check_login.php';
require_once 'db_connect_memory_game.php';

// 處理遊戲結果保存的 API 請求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);
   
    try {
        // 開始交易
        $pdo->beginTransaction();
       
        $gameId = 5;
       
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
            'game_type' => '記憶力',
            'is_single_player' => 0,
            'opponent_id' => $data['player2_id']
        ]);

        // 保存玩家2的記錄
        $stmt->execute([
            'member_id' => $data['player2_id'],
            'game_id' => $gameId,
            'difficulty' => $data['difficulty'],
            'score' => $data['player2_score'],
            'play_time' => isset($data['play_time']) ? $data['play_time'] : null,
            'game_type' => '記憶力',
            'is_single_player' => 0,
            'opponent_id' => $data['player1_id']
        ]);
       
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

// 獲取主題列表
$stmt = $pdo->query("SELECT * FROM memory_game_themes WHERE is_active = true");
$themes = $stmt->fetchAll();

// 獲取難度設定
$stmt = $pdo->query("SELECT * FROM memory_game_difficulty_settings WHERE is_active = true");
$difficulties = $stmt->fetchAll();

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
    <link rel="stylesheet" href="css/Memory-Game.css">
    <link rel="stylesheet" href="css/Memory-Game-2P.css">
    <script src="https://cdn.socket.io/4.7.5/socket.io.min.js"></script>
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
                    <span>剩餘時間：</span>
                    <span id="timer">60</span>
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
        
        <div id="game-board" class="game-board">
        <!-- 卡片將由 JavaScript 動態生成 -->
        </div>
        
        <div class="control-buttons">
            <button id="pauseBtn" class="hidden">暫停遊戲</button>
            <button id="resumeBtn" class="hidden">繼續遊戲</button>
            <button id="endBtn" class="hidden">結束遊戲</button>
            <button id="resetBtn" class="hidden">重新開始</button>
        </div>
    </div>
    
    <!-- 玩家設定視窗 -->
    <div id="player-setup-modal" class="modal">
        <div class="modal-content">
            <button class="help-button help-top-right" onclick="showHelp()">?
                <div class="help-label">說明</div>
            </button>
            <h2>雙人模式設定</h2>
            
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
            <button id="backToSetupBtn" class="back-button" onclick="showPlayerSetupModal()" style="position:absolute;top:1rem;left:1.2rem;z-index:10;">
                <span class="back-arrow">⬅</span>
                <div class="back-label">返回</div>
            </button>
            <h2>選擇主題</h2>
            <button class="help-button" onclick="showHelp()">?
                <div class="help-label">說明</div>
            </button>
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
                    <span class="theme-icon">⌚</span>
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
            <button id="backToThemeBtn" class="back-button" onclick="showThemeModal()" style="position:absolute;top:1rem;left:1.2rem;z-index:10;">
                <span class="back-arrow">⬅</span>
                <div class="back-label">返回</div>
            </button>
            <h2>選擇難度</h2>
            <button class="help-button" onclick="showHelp()">?
                <div class="help-label">說明</div>
            </button>
            <div class="difficulty-buttons">
                <button class="difficulty-btn easy" onclick="selectDifficulty('easy')">
                    <span class="difficulty-name">簡單模式</span>
                    <span class="difficulty-desc">(4x3 網格，60秒)</span>
                </button>
                <button class="difficulty-btn normal" onclick="selectDifficulty('normal')">
                    <span class="difficulty-name">普通模式</span>
                    <span class="difficulty-desc">(4x4 網格，120秒)</span>
                </button>
                <button class="difficulty-btn hard" onclick="selectDifficulty('hard')">
                    <span class="difficulty-name">困難模式</span>
                    <span class="difficulty-desc">(8x4 網格，180秒)</span>
                </button>
            </div>
        </div>
    </div>
    
    <!-- 遊戲說明視窗 -->
    <div id="help-modal" class="modal hidden">
        <div class="modal-content">
            <h2 style="text-align:center;">
                <span style="font-size:2rem;vertical-align:middle;">🎮</span>
                <span style="font-weight:bold;vertical-align:middle;">遊戲說明</span>
            </h2>
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
                    <li>設定玩家名稱</li>
                    <li>選擇主題、難度</li>
                    <li>玩家輪流翻牌，配對成功可再翻一次</li>
                    <li>配對失敗換下一位玩家</li>
                    <li>時間結束時配對最多者獲勝</li>
                </ul>
            </div>
            <span class="close-btn" onclick="closeHelpModal()">×</span>
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
                <button onclick="returnToMain()">返回主選單</button>
            </div>
        </div>
    </div>
    
    <script>
        // 將PHP變數傳遞給JavaScript
        const themes = <?php echo json_encode($themes); ?>;
        const difficulties = <?php echo json_encode($difficulties); ?>;
        const colors = <?php echo json_encode($colors); ?>;
        // 如果 localStorage 沒有 member_id，就自動設一個（這裡用 8，請改成你自己的會員ID）
        if (!localStorage.getItem('member_id')) {
            localStorage.setItem('member_id', 8);
        }
    </script>
    <script src="js/Memory-Game-2P.js"></script>
    <script src="js/auto-save-time.js"></script>
</body>
</html> 