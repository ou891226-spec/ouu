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
       
        $stmt = $pdo->prepare("
            INSERT INTO game_records
            (member_id, game_id, difficulty, score, play_date, play_time, game_type, is_single_player, opponent_id)
            VALUES (:member_id, :game_id, :difficulty, :score, NOW(), :play_time, :game_type, :is_single_player, :opponent_id)
        ");
        $stmt->execute([
            'member_id' => $data['member_id'],
            'game_id' => $gameId,
            'difficulty' => $data['difficulty'],
            'score' => $data['score'],
            'play_time' => isset($data['play_time']) ? $data['play_time'] : null,
            'game_type' => '記憶力',
            'is_single_player' => 1,
            'opponent_id' => null
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
    <title>翻牌對對樂</title>
    <link rel="stylesheet" href="css/Memory-Game.css">
</head>
<body>
    <!-- 遊戲主畫面 -->
    <div id="game-container" class="game-container hidden">
        <h1>翻牌對對樂</h1>
        <div class="score-board">
            <span>配對次數：</span>
            <span id="moves">0</span>
            <span>剩餘時間：</span>
            <span id="timer">60</span>
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
    <!-- 主題選擇視窗 -->
    <div id="theme-modal" class="modal">
        <div class="modal-content">
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
                    時間內翻開卡片找出一樣的兩張，全部配對成功就過關！
                </div>
                <div style="display:flex;align-items:center;margin-bottom:0.5rem;">
                    <span style="color:#3b82f6;font-size:1.2rem;margin-right:0.5rem;">◆</span>
                    <span style="font-weight:bold;font-size:1.1rem;">玩法</span>
                </div>
                <ul style="margin-left:2.2rem;">
                    <li>選主題、選難度</li>
                    <li>點卡片翻面，比對圖案</li>
                    <li>時間內完成配對！</li>
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
    <script src="js/Memory-Game.js"></script>
    <script src="js/auto-save-time.js"></script>
</body>
</html> 