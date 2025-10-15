<?php
// 啟動輸出緩衝
ob_start();
require_once 'game_entry_tracker.php';

// 只在會話未啟動時啟動會話
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'db_connect.php';

// 只在非 AJAX 請求時檢查登入狀態
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    require_once 'check_login.php';
}

// 處理遊戲結果保存的 API 請求 - 使用統一系統
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 清除任何之前的輸出
    if (ob_get_length()) ob_clean();
    
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);
   
    try {
        // 使用統一API處理遊戲結果
        $data['game_type'] = '記憶力';
        $data['game_id'] = 5;
        
        // 使用API端點處理遊戲結果
        $apiUrl = 'api/game_result.php';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen(json_encode($data))
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception("API調用失敗: HTTP $httpCode, Response: $response");
        }
        
        $result = json_decode($response, true);
        if (!$result || !$result['success']) {
            throw new Exception("API處理失敗: " . ($result['message'] ?? '未知錯誤'));
        }
        
        echo json_encode($result);
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
$difficulties = [];
$stmt = $pdo->prepare("SELECT * FROM difficulty_settings WHERE game_id = 5 ORDER BY difficulty");
$stmt->execute();
$settings = $stmt->fetchAll();

foreach ($settings as $setting) {
    $difficulties[$setting['difficulty']] = $setting;
}

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
        <h1>翻牌對對樂</h1>
        <div class="score-board">
            <span>配對次數：</span>
            <span id="moves">0</span>
            <span>剩餘時間：</span>
            <span id="timer">60</span>
        </div>
        <div id="preview-countdown" class="preview-countdown hidden">
            <span>記憶時間：</span>
            <span id="countdown-timer">5</span>
            <span>秒</span>
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
        </div>
    </div>
    <!-- 主題選擇視窗 -->
    <div id="theme-modal" class="modal">
        <div class="modal-content">
            <button class="back-button" onclick="history.back()" style="position:absolute;top:1rem;left:1.2rem;z-index:10;">
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
                    <span class="difficulty-desc">(4x3 網格，<?php echo isset($difficulties['easy']['time_limit']) ? $difficulties['easy']['time_limit'] : 60; ?>秒)</span>
                </button>
                <button class="difficulty-btn normal" onclick="selectDifficulty('normal')">
                    <span class="difficulty-name">普通模式</span>
                    <span class="difficulty-desc">(4x4 網格，<?php echo isset($difficulties['normal']['time_limit']) ? $difficulties['normal']['time_limit'] : 120; ?>秒)</span>
                </button>
                <button class="difficulty-btn hard" onclick="selectDifficulty('hard')">
                    <span class="difficulty-name">困難模式</span>
                    <span class="difficulty-desc">(8x4 網格，<?php echo isset($difficulties['hard']['time_limit']) ? $difficulties['hard']['time_limit'] : 180; ?>秒)</span>
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
                <div id="video-container" style="display:flex;flex-direction:column;align-items:center;justify-content:center;margin-bottom:2.5rem;">
                    <video id="current-video" width="100%" height="auto" controls preload="auto" style="max-width:700px;width:80%;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,0.15);display:block;">
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
                    
                    <div id="instruction-text" class="game-instruction-text">
                        先選擇主題，再選擇難度
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
                <div class="gameover-info">
                    <div class="gameover-row">難度：<span id="memory-gameover-difficulty">簡單</span></div>
                    <div class="gameover-row" id="memory-time-row">遊戲時間：<span id="memory-gameover-time">0秒</span></div>
                        <div class="gameover-row" id="memory-bonus-row">獲得分數：<span id="memory-gameover-bonus">+0</span></div>
                    <div class="gameover-row" id="memory-fail-message" style="display: none;">未達成目標分數！</div>
                </div>
            </div>
            <div class="result-buttons">
                <button onclick="replayGame()">再玩一次</button>
                <button onclick="returnToMain()">返回主頁</button>
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
    <script src="js/game-exit-handler.js"></script>
    <script src="js/game-common.js?v=<?php echo time(); ?>"></script>
    <script src="js/get-score.js?v=<?php echo time(); ?>"></script>
    <script src="js/Memory-Game.js?v=<?php echo time(); ?>"></script>
    <script>
        // 配置遊戲退出處理器
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof gameExitHandler !== 'undefined') {
                gameExitHandler.updateConfig({
                    memberId: <?= $_SESSION['member_id'] ?? 'null' ?>,
                    gameType: '記憶力',
                    gameId: 5,
                    difficulty: '<?= isset($_GET["difficulty"]) ? $_GET["difficulty"] : "easy" ?>'
                });
                console.log('遊戲退出處理器已配置');
            }
            
            // 遊戲開始時啟動追蹤
            if (typeof gameExitHandler !== 'undefined') {
                gameExitHandler.startGame();
                console.log('遊戲追蹤已啟動');
            }
        });
    </script>
</body>
</html> 