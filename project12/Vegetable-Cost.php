<?php
require_once 'db_connect.php';
require_once 'game_entry_tracker.php';

// 從資料庫讀取算菜錢遊戲的難度設定
$difficultySettings = [];
$stmt = $pdo->prepare("SELECT * FROM difficulty_settings WHERE game_id = 3 ORDER BY difficulty");
$stmt->execute();
$settings = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($settings as $setting) {
    $difficultySettings[$setting['difficulty']] = [
        'time_limit' => $setting['time_limit'],
        'points_per_correct' => $setting['points_per_correct'],
        'pass_score' => $setting['pass_score']
    ];
}

// 處理取得食材資料的 API 請求
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['get_ingredients'])) {
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

// 處理遊戲結果保存的 API 請求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);
   
    try {
        // 使用統一API處理遊戲結果
        $data['game_type'] = '算術邏輯力';
        $data['game_id'] = 3;
        
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

// 檢查 session 中的 member_id
session_start();
if (!isset($_SESSION['member_id'])) {
    // 如果沒有登入，重定向到登入頁面
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>算菜錢遊戲</title>
    <link rel="stylesheet" href="css/Vegetable-Cost.css?v=<?php echo time(); ?>">
    <script src="js/unified-game-tracker.js"></script>
</head>
<body>
    <input type="hidden" id="member-id" value="<?php echo $_SESSION['member_id']; ?>">


    <div class="game-container" style="display:none;">
        <h1 class="main-title">算菜錢遊戲</h1>
        <div class="score-board new-score-board">
            <div class="score-item">
                <span>目前分數：</span>
                <span id="score" class="score-green">0</span>
            </div>
            <div class="score-item">
                <span>過關分數：</span>
                <span id="high-score" class="score-green">0</span>
            </div>
            <div class="score-item">
                <span>剩餘時間：</span>
                <span id="timer" class="score-red">60</span>
            </div>
        </div>
        <div id="pause-indicator" class="pause-indicator hidden">
            <span>⏸️ 遊戲已暫停</span>
        </div>
        <div id="question-container" class="main-question-container">
            <div id="question"></div>
            <div id="options-container" class="main-options-container"></div>
        </div>
        <div class="main-control-btns">
            <button id="pause-btn" class="main-btn pause-btn">暫停遊戲</button>
            <button id="end-btn" class="main-btn end-btn" onclick="endGame(true)">結束遊戲</button>
            <button id="restart-btn" class="main-btn restart-btn" onclick="restartGame()">重新開始</button>
        </div>
    </div>

    <!-- 遊戲結束視窗 -->
    <div id="game-over-modal" class="modal hidden">
        <div class="modal-content">
            <h2 id="vegetable-game-over-title">遊戲結束</h2>
            <div class="result-details">
                <div class="gameover-info">
                    <div class="gameover-row">難度：<span id="vegetable-gameover-difficulty">簡單模式</span></div>
                    <div class="gameover-row" id="vegetable-score-row">遊戲分數：<span id="vegetable-gameover-score">0</span></div>
                    <div class="gameover-row" id="vegetable-time-row">遊戲時間：<span id="vegetable-gameover-time">0秒</span></div>
                    <div class="gameover-row" id="vegetable-bonus-row">獲得分數：<span id="vegetable-gameover-bonus">+0</span></div>
                    <div class="gameover-row" id="vegetable-fail-message" style="display: none;">未達成目標分數！</div>
                </div>
            </div>
            <div class="result-buttons">
                <button onclick="restartGame()">再玩一次</button>
                <button onclick="exitGame()">返回主頁</button>
            </div>
        </div>
    </div>

    <!-- 難度選擇視窗 -->
    <div id="difficulty-modal" class="modal">
        <div class="modal-content" style="padding: 2.5rem 2rem 2rem 2rem;">
            <div class="difficulty-modal-header">
                <a href="javascript:void(0)" onclick="smartReturn()" class="back-btn">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="11" stroke="#222" stroke-width="2"/><polyline points="13 8 9 12 13 16" stroke="#222" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/><line x1="9" y1="12" x2="17" y2="12" stroke="#222" stroke-width="2" stroke-linecap="round"/></svg>
                    <span>返回</span>
                </a>
                <div class="difficulty-title">選擇難度</div>
                <button class="help-btn" type="button">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="11" stroke="#222" stroke-width="2"/><text x="12" y="18" text-anchor="middle" font-size="18" fill="#222" font-family="Arial" dy="0">?</text></svg>
                    <span>說明</span>
                </button>
            </div>
            <div class="difficulty-btn-group">
                <button class="difficulty-btn easy-mode" data-difficulty="easy">
                    <div class="diff-main">簡單模式（簡單加減計算）</div>
                    <div class="diff-sub"><?php echo isset($difficultySettings['easy']['time_limit']) ? $difficultySettings['easy']['time_limit'] : 80; ?>秒，目標：<?php echo isset($difficultySettings['easy']['pass_score']) ? $difficultySettings['easy']['pass_score'] : 15; ?>分</div>
                </button>
                <button class="difficulty-btn normal-mode" data-difficulty="normal">
                    <div class="diff-main">普通模式（促銷優惠計算）</div>
                    <div class="diff-sub"><?php echo isset($difficultySettings['normal']['time_limit']) ? $difficultySettings['normal']['time_limit'] : 150; ?>秒，目標：<?php echo isset($difficultySettings['normal']['pass_score']) ? $difficultySettings['normal']['pass_score'] : 20; ?>分</div>
                </button>
                <button class="difficulty-btn hard-mode" data-difficulty="hard">
                    <div class="diff-main">困難模式（複雜組合計算）</div>
                    <div class="diff-sub"><?php echo isset($difficultySettings['hard']['time_limit']) ? $difficultySettings['hard']['time_limit'] : 200; ?>秒，目標：<?php echo isset($difficultySettings['hard']['pass_score']) ? $difficultySettings['hard']['pass_score'] : 30; ?>分</div>
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
            <div class="help-content" style="margin-top:1.5rem;padding:0 1rem;">
                <!-- 影片播放區域 -->
                <div id="vegetable-video-container" style="text-align:center;margin-bottom:1.5rem;">
                    <video id="vegetable-current-video" width="100%" height="auto" controls preload="none" style="max-width:400px;width:60%;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,0.15);">
                        <source src="gd/vegetable1.mp4" type="video/mp4">
                        您的瀏覽器不支援視頻播放。
                    </video>
                </div>
                
                <!-- 說明文字和按鈕區域 (並排顯示) -->
                <div style="display:flex;justify-content:center;align-items:center;margin:0 1rem;margin-bottom:2rem; gap: 20px;">
                    <!-- 上一步按鈕 -->
                    <div id="vegetable-prev-step-btn" style="display:none;">
                        <button id="vegetable-prev-step-button" onclick="goToVegetablePrevStep()" class="game-step-button prev-step">
                            上一步
                        </button>
                    </div>
                    
                    <!-- 說明文字 -->
                    <div id="vegetable-instruction-text" class="game-instruction-text">
                        先選擇遊戲困難度
                    </div>
                    
                    <!-- 下一步按鈕 -->
                    <div id="vegetable-next-step-btn" style="margin-left:1rem;">
                        <button id="vegetable-next-step-button" class="game-step-button next-step">
                            下一步
                        </button>
                    </div>
                </div>
                
                <!-- 進度指示器 -->
                <div style="text-align:center;margin-top:1.5rem;margin-bottom:1.5rem;">
                    <span id="vegetable-step-indicator" class="game-step-indicator" style="font-size:20px;">步驟 1/2</span>
                </div>
            </div>
            <span class="close-btn">×</span>
        </div>
    </div>

    <script>
        // 將PHP變數傳遞給JavaScript
        window.phpMemberId = <?php echo $_SESSION['member_id']; ?>;
        window.difficultySettings = <?php echo json_encode($difficultySettings); ?>;

        function smartReturn() {
            // 智能返回：回到上一頁，如果沒有上一頁則回到首頁
            if (document.referrer && document.referrer !== window.location.href) {
                history.back();
            } else {
                window.location.href = 'index.php';
            }
        }
    </script>
    <script src="js/game-exit-handler.js"></script>
    <script src="js/game-common.js"></script>
    <script src="js/get-score.js"></script>
    <script src="js/Vegetable-Cost.js"></script>
    <script src="js/auto-save-time-fixed.js"></script>
    <script>
        // 配置遊戲退出處理器
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof gameExitHandler !== 'undefined') {
                gameExitHandler.updateConfig({
                    memberId: <?= $_SESSION['member_id'] ?? 'null' ?>,
                    gameType: '算術邏輯力',
                    gameId: 3,
                    difficulty: 'easy'
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