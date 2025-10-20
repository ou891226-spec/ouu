<?php
// 啟動輸出緩衝
ob_start();

// 只在會話未啟動時啟動會話
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'db_connect.php';

// 登入檢查已移除，避免 session 警告

// 檢查是否已登入
//if (!isset($_SESSION['member_id'])) {
    // 如果沒有 session，檢查是否有 AJAX 請求中的 member_id
 //   if (isset($_POST['member_id'])) {
   //     $_SESSION['member_id'] = $_POST['member_id'];
  //  } else {
   //     header('Location: login.php');
   //     exit();
  //  }
//}

// 處理 AJAX 請求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    $action = $_POST['action'] ?? '';
    $member_id = $_POST['member_id'] ?? ($_SESSION['member_id'] ?? 8);
    
    switch ($action) {
        case 'start_game':
            $difficulty = $_POST['difficulty'] ?? 'normal';
            $_SESSION['game_start_time'] = time();
            $_SESSION['current_difficulty'] = $difficulty;
            
            // 接金蛋遊戲的特殊設定（根據難度）
            $egg_settings = [
                'easy' => [
                    'egg_spawn_rate' => 2500,
                    'basket_speed' => 12,
                    'egg_fall_speed' => 2
                ],
                'normal' => [
                    'egg_spawn_rate' => 2000,
                    'basket_speed' => 10,
                    'egg_fall_speed' => 3
                ],
                'hard' => [
                    'egg_spawn_rate' => 1500,
                    'basket_speed' => 8,
                    'egg_fall_speed' => 4
                ]
            ];
            
            $current_settings = $egg_settings[$difficulty] ?? $egg_settings['normal'];
            
            echo json_encode([
                'success' => true,
                'settings' => $current_settings
            ]);
            break;
            
        case 'end_game':
            // 確保沒有之前的輸出
            if (ob_get_length()) ob_clean();
            
            $score = $_POST['score'] ?? 0;
            $playTime = isset($_SESSION['game_start_time']) ? (time() - $_SESSION['game_start_time']) : 0;
            $difficulty = $_SESSION['current_difficulty'] ?? 'easy';
            
            // 簡化會員ID處理，直接使用預設值
            if (!$member_id || $member_id < 1) {
                $member_id = 8; // 使用預設會員ID
            }
            
            // 從資料庫讀取過關分數
            $pass_score = 200; // 預設值
            try {
                $stmt = $pdo->prepare("SELECT pass_score FROM difficulty_settings WHERE game_id = 2 AND difficulty = ?");
                $stmt->execute([$difficulty]);
                $result = $stmt->fetch();
                if ($result) {
                    $pass_score = $result['pass_score'];
                }
            } catch (Exception $e) {
                error_log("讀取接金蛋遊戲難度設定失敗: " . $e->getMessage());
                // 使用預設值
                if ($difficulty === 'normal') $pass_score = 450;
                if ($difficulty === 'hard') $pass_score = 600;
            }
            
            // 區分手動退出和遊戲失敗
            $isManualExit = isset($_POST['is_manual_exit']) && $_POST['is_manual_exit'] === '1';
            $isPassed = $score >= $pass_score;
            
            // 使用統一遊戲結果處理系統
            require_once 'game_entry_tracker.php';
            
            $gameData = [
                'member_id' => $member_id,
                'game_type' => '反應力',
                'difficulty' => $difficulty,
                'score' => $score,
                'play_time' => $playTime,
                'is_manual_exit' => $isManualExit,
                'is_passed' => $isPassed,
                'game_id' => 2
            ];
            
            try {
                // 使用API端點處理遊戲結果
                $apiUrl = 'api/game_result.php';
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $apiUrl);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($gameData));
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen(json_encode($gameData))
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
                
                header('Content-Type: application/json');
                echo json_encode($result);
            } catch (Exception $e) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => '儲存失敗: ' . $e->getMessage()
                ]);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => '無效的請求方法']);
            break;
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>接金蛋遊戲</title>
    <link rel="stylesheet" href="css/Catch-Egg.css?v=<?php echo time(); ?>" type="text/css">
    <script src="js/Catch-Egg.js?v=<?php echo time(); ?>"></script>
    <script>
        // 設置會員ID供JavaScript使用
        window.memberId = <?php echo $_SESSION['member_id'] ?? 'null'; ?>;
    </script>
</head>
<body>
    <!-- 遊戲主畫面 -->
    <div id="game-container" class="game-container">
        <h1>接金蛋遊戲</h1>
        
        <div class="score-board">
            <span class="label">目前分數：</span>
            <span id="score" class="value">0</span>
            <span class="label">目標分數：</span>
            <span id="high-score" class="value">0</span>
            <span class="label">剩餘時間：</span>
            <span id="timer" class="value">60</span>
        </div>
        
        <div class="score-guide">
            <span class="guide-item">
                <img src="img/egg.png" alt="金蛋" class="guide-icon">
                <span class="guide-text">金蛋 +10分</span>
            </span>
            <span class="guide-item">
                <img src="img/catch_egg.png" alt="白蛋" class="guide-icon">
                <span class="guide-text">白蛋 +3分</span>
            </span>
            <span class="guide-item">
                <img src="img/bomb.png" alt="炸彈" class="guide-icon">
                <span class="guide-text">炸彈 -20分</span>
            </span>
        </div>

        <div id="game">
            <div id="basket"></div>
        </div>

        <div class="control-buttons">
            <button id="pauseBtn">暫停遊戲</button>
            <button id="resumeBtn" class="hidden">繼續遊戲</button>
            <button id="endBtn">結束遊戲</button>
            <button id="resetBtn">重新開始</button>
        </div>

        <div id="countdownDisplay" class="hidden">遊戲開始倒數：5</div>
        <div id="countdownOverlay" class="hidden"></div>
    </div>

    <!-- 難度選擇彈窗 -->
    <div id="difficulty-modal" class="modal">
        <div class="modal-content">
            <!-- 🔙 返回鍵：左上角 -->
            <div class="back-button">
                <button class="back-arrow" id="back-btn" title="返回" onclick="history.back()">
                    <svg width="32" height="32" viewBox="0 0 32 32" fill="none">
                        <circle cx="16" cy="16" r="15" stroke="black" stroke-width="2.5" fill="white"/>
                        <polygon points="20,9 12,16 20,23 20,18 25,18 25,14 20,14" fill="black"/>
                    </svg>
                </button>
                <div class="btn-label">返回</div>
            </div>
            <!-- ❓ 說明鍵：右上角 -->
            <button class="help-button" onclick="showEggHelp()">
                <span class="help-icon">?</span>
                <div class="help-label">說明</div>
            </button>
            <h2>選擇難度</h2>
            <?php
            // 從統一的 difficulty_settings 表讀取接金蛋遊戲的難度設定
            $difficulties = [];
            $stmt = $pdo->prepare("SELECT * FROM difficulty_settings WHERE game_id = 2 ORDER BY difficulty");
            $stmt->execute();
            $settings = $stmt->fetchAll();
            
            // 調試：檢查查詢結果
            error_log("接金蛋遊戲難度設定查詢結果: " . print_r($settings, true));
            
            foreach ($settings as $setting) {
                $difficulties[] = [
                    'difficulty' => $setting['difficulty'],
                    'pass_score' => $setting['pass_score'],
                    'pass_bounce' => $setting['pass_bounce'],
                    'time_limit' => $setting['time_limit']
                ];
            }
            
            // 調試：檢查最終數組
            error_log("最終 difficulties 數組: " . print_r($difficulties, true));
            
            // 定義正確的順序：簡單、普通、困難
            $correct_order = ['easy', 'normal', 'hard'];
            $difficulty_map = [];
            
            // 先建立難度對應表
            foreach ($difficulties as $diff) {
                $difficulty_map[$diff['difficulty']] = $diff['pass_score'];
            }
            
            // 按照正確順序顯示
            foreach ($correct_order as $difficulty_class) {
                if (isset($difficulty_map[$difficulty_class])) {
                    $target_score = $difficulty_map[$difficulty_class];
                    
                    // 將英文難度轉換為中文
                    $difficulty_text = '';
                    switch ($difficulty_class) {
                        case 'easy':
                            $difficulty_text = '簡單';
                            break;
                        case 'normal':
                            $difficulty_text = '普通';
                            break;
                        case 'hard':
                            $difficulty_text = '困難';
                            break;
                        default:
                            $difficulty_text = $difficulty_class;
                    }
                    
                    echo "<button class='difficulty-btn {$difficulty_class}' onclick='selectDifficulty(\"{$difficulty_class}\")'>";
                    echo "{$difficulty_text} 目標：{$target_score}分";
                    echo "</button>";
                }
            }
            ?>
        </div>
    </div>

    <!-- 金蛋遊戲說明視窗 -->
    <div id="egg-help-modal" class="modal hidden">
        <div class="modal-content">
            <span class="close-btn" onclick="closeEggHelpModal()">×</span>
            <h2 style="text-align:center;">
                <span style="font-size:2rem;vertical-align:middle;">🎮</span>
                <span style="font-weight:bold;vertical-align:middle;">遊戲說明</span>
            </h2>
            <div class="help-content" style="margin-top:1.5rem;padding:0 1.5rem;">
                <!-- 影片播放區域 -->
                <div id="egg-video-container" style="text-align:center;margin-bottom:1.5rem;">
                    <video id="egg-current-video" width="100%" height="auto" controls style="max-width:400px;width:60%;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,0.15);">
                        <source src="gd/egg1.mp4" type="video/mp4">
                        您的瀏覽器不支援視頻播放。
                    </video>
                </div>
                
                <!-- 說明文字區域 -->
                <div style="text-align:center;margin:0 1rem;margin-bottom:2rem;">
                    <div id="egg-instruction-text" class="game-instruction-text">
                        先選擇遊戲困難度
                    </div>
                </div>
                
                <!-- 按鈕和步驟指示器區域 -->
                <div class="help-modal-footer">
                    <!-- 上一步按鈕 -->
                    <div id="egg-prev-step-btn" style="display:none;">
                        <button id="egg-prev-step-button" onclick="goToEggPrevStep()" class="game-step-button prev-step">
                            上一步
                        </button>
                    </div>
                    
                    <!-- 進度指示器 -->
                    <span id="egg-step-indicator" class="game-step-indicator">步驟 1/2</span>
                    
                    <!-- 下一步按鈕 -->
                    <div id="egg-next-step-btn">
                        <button id="egg-next-step-button" class="game-step-button next-step">
                            下一步
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 金蛋遊戲結束視窗 -->
    <div id="egg-game-over-modal" class="modal hidden">
        <div class="modal-content">
            <h2 id="egg-game-over-title">遊戲結束</h2>
            <div class="result-details">
                <div class="gameover-info">
                    <div class="gameover-row">難度：<span id="egg-gameover-difficulty">簡單</span></div>
                    <div class="gameover-row" id="egg-score-row">遊戲分數：<span id="egg-gameover-score">0</span></div>
                    <div class="gameover-row" id="egg-time-row">遊戲時間：<span id="egg-gameover-time">0秒</span></div>
                    <div class="gameover-row" id="egg-bonus-row">獲得分數：<span id="egg-gameover-bonus">+0</span></div>
                    <div class="gameover-row" id="egg-fail-message" style="display: none;">未達成目標分數！</div>
                </div>
            </div>
            <div class="result-buttons">
                <button onclick="eggReplayGame()">再玩一次</button>
                <button onclick="eggReturnToMain()">返回主頁</button>
            </div>
        </div>
    </div>

    <!-- 音頻元素 -->
    <audio id="catchSound" preload="auto">
        <source src="music/gett.mp4" type="audio/mp4">
        <source src="music/gett.mp4" type="video/mp4">
    </audio>
    <audio id="bombSound" preload="auto">
        <source src="music/boom.m4a" type="audio/mp4">
        <source src="music/boom.mp3" type="audio/mpeg">
    </audio>
    <audio id="gameOverSound">
        <source src="music/gett.mp4" type="audio/mp4">
        <source src="music/gett.mp4" type="video/mp4">
    </audio> 

    <script>
        // 如果 localStorage 沒有 member_id，就自動設一個（這裡用 8，請改成你自己的會員ID）
        if (!localStorage.getItem('member_id')) {
            localStorage.setItem('member_id', 8);
        }
        
        // 將資料庫的難度設定傳遞給 JavaScript
        console.log('PHP difficulties:', <?php echo json_encode($difficulties); ?>);
        const difficultySettings = <?php echo json_encode($difficulties); ?>;
        console.log('JavaScript difficultySettings:', difficultySettings);
        
        // 如果資料庫設定為空，使用硬編碼的設定
        if (!difficultySettings || difficultySettings.length === 0) {
            console.log('使用硬編碼設定');
            window.difficultySettings = [
                {difficulty: 'easy', pass_score: 200, time_limit: 60},
                {difficulty: 'normal', pass_score: 450, time_limit: 60},
                {difficulty: 'hard', pass_score: 600, time_limit: 60}
            ];
        } else {
            window.difficultySettings = difficultySettings;
        }
    </script>
    <script src="js/auto-save-time-fixed.js"></script>
    <script src="js/game-exit-handler.js"></script>
    <script src="js/game-common.js"></script>
    <script src="js/get-score.js"></script>
    <script>
        // 配置遊戲退出處理器
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof gameExitHandler !== 'undefined') {
                gameExitHandler.updateConfig({
                    memberId: <?= $_SESSION['member_id'] ?? 'null' ?>,
                    gameType: '反應力',
                    gameId: 2,
                    difficulty: 'easy'
                });
                console.log('遊戲退出處理器已配置');
            }
            
            // 遊戲追蹤將在真正開始遊戲時啟動
        });
    </script>
</body>
</html> 