<?php
// 啟動輸出緩衝
ob_start();

// 只在會話未啟動時啟動會話
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'db_connect.php';

// 只在非 AJAX 請求時檢查登入狀態
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    require_once 'check_login.php';
}

// 處理遊戲結果保存的 API 請求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 清除任何之前的輸出
    if (ob_get_length()) ob_clean();
    
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
       
        // 更新會員總分數和記憶力分數
        $update_stmt = $pdo->prepare("UPDATE member SET total_score = total_score + :score, memory_score = memory_score + :score WHERE member_id = :member_id");
        $update_stmt->execute([
            'score' => $data['score'],
            'member_id' => $data['member_id']
        ]);
       
        // 提交交易
        $pdo->commit();
        
        // 觸發任務和成就檢查
        require_once 'check_and_grant_achievements.php';
        checkAndCompleteAllTasks($data['member_id'], '記憶力');
        
        // 確保沒有其他輸出
        if (ob_get_length()) ob_clean();
       
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

// 獲取難度設定 (使用統一的 difficulty_settings 表)
$stmt = $pdo->query("SELECT * FROM difficulty_settings WHERE game_id = 5 ORDER BY difficulty");
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
            <button class="back-button" onclick="handleBackButton()" style="position:absolute;top:1rem;left:1.2rem;z-index:10;">
                <span class="back-arrow">←</span>
                <div class="back-label">返回</div>
            </button>
            <h2>選擇主題</h2>
            <button class="help-button" onclick="showHelp()" style="position:absolute;top:1rem;right:1.2rem;z-index:10;">?
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
                <span class="back-arrow">←</span>
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
            <div class="help-content" style="margin-top:2.5rem;padding:0 2rem;">
                <div id="video-container" style="text-align:center;margin-bottom:2.5rem;">
                    <video id="current-video" width="100%" height="auto" controls style="max-width:700px;width:80%;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,0.15);">
                        <source src="gd/card1.mp4" type="video/mp4">
                        您的瀏覽器不支援視頻播放。
                    </video>
                </div>
                
                <div style="display:flex;justify-content:center;align-items:center;margin:0 1rem;margin-bottom:2rem; gap: 20px;">
                    <div id="prev-step-btn">
                        <button id="prev-step-button" onclick="goToPrevStep()" class="game-step-button prev-step" style="padding:14px 28px;font-size:20px;">
                            上一步
                        </button>
                    </div>
                    
                    <div id="instruction-text" class="game-instruction-text" style="font-size:24px;text-align:center; min-width: 180px;">
                        選主題、選難度
                    </div>
                    
                    <div id="next-step-btn">
                        <button id="next-step-button" onclick="goToNextStep()" class="game-step-button next-step" style="padding:14px 28px;font-size:20px;">
                            下一步
                        </button>
                    </div>
                </div>
                
                <div style="text-align:center;margin-top:1.5rem;margin-bottom:1.5rem;">
                    <span id="step-indicator" class="game-step-indicator" style="font-size:18px;">步驟 1/2</span>
                </div>
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
        // 設置正確的會員ID
        const memberId = <?php echo $_SESSION['member_id'] ?? 0; ?>;
        localStorage.setItem('member_id', memberId);
    </script>
    <script src="js/Memory-Game.js"></script>
    <script src="js/auto-save-time.js"></script>
</body>
</html> 