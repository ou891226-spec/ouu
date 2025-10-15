<?php
// 檢查是否為AJAX請求，如果是則不輸出HTML
if (
    isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest' &&
    $_SERVER['REQUEST_METHOD'] === 'POST'
) {
    // AJAX請求處理在後面
} else {
    // 只有非AJAX請求才需要check_login
    require_once 'check_login.php';
}

require_once 'game_entry_tracker.php';

// 只有非AJAX請求才設置HTML header
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
    header('Content-Type: text/html; charset=utf-8');
}

// 獲取難度設定
$difficultySettings = [];
$stmt = $pdo->prepare("SELECT * FROM difficulty_settings WHERE game_id = 4 ORDER BY difficulty_id");
$stmt->execute();
$settings = $stmt->fetchAll();

foreach ($settings as $setting) {
    $difficulty = $setting['difficulty'];
    $difficultySettings[$difficulty] = [
        'pass_score' => $setting['pass_score'],
        'pass_bounce' => $setting['pass_bounce'],
        'time_limit' => $setting['time_limit'],
        'points_per_correct' => $setting['points_per_correct']
    ];
}

if (
    isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest' &&
    $_SERVER['REQUEST_METHOD'] === 'POST'
) {
    // 清除任何之前的輸出緩衝
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // 禁用錯誤顯示，避免HTML錯誤訊息
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(E_ALL);
    
    // 啟動session（如果尚未啟動）
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    require_once 'db_connect.php';
    header('Content-Type: application/json');
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    // 驗證用戶登入狀態
    if (!isset($_SESSION['member_id']) || empty($_SESSION['member_id'])) {
        echo json_encode(['success' => false, 'message' => '用戶未登入']);
        exit;
    }
    
    try {
        // 檢查是否為遊戲勝利（分數大於0表示勝利）
        $score = isset($data['score']) ? intval($data['score']) : 0;
        $difficulty = isset($data['difficulty']) ? $data['difficulty'] : 'easy';
        
        
        // 根據是否過關決定記錄的分數
        $is_passed = ($score > 0);
        $record_score = 0;
        
        if ($is_passed) {
            // 從資料庫讀取過關獎勵分數
            $record_score = isset($difficultySettings[$difficulty]['pass_bounce']) ? $difficultySettings[$difficulty]['pass_bounce'] : 0;
        }
        // 如果沒過關，record_score 保持為 0

        // 使用統一遊戲結果處理系統
        $play_time = isset($data['play_time']) ? $data['play_time'] : 0;
        $isManualExit = isset($data['is_manual_exit']) && $data['is_manual_exit'] === true;
        
        $gameData = [
            'member_id' => $data['member_id'],
            'game_type' => '算術邏輯力',
            'difficulty' => $difficulty,
            'score' => $record_score,
            'play_time' => $play_time,
            'is_manual_exit' => $isManualExit,
            'is_passed' => $is_passed,
            'game_id' => 4
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
        
        // 再次確保清除任何輸出
        if (ob_get_level()) ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode($result);
    } catch (Exception $e) {
        if (ob_get_level()) ob_end_clean();
        header('Content-Type: application/json');
        error_log("2048遊戲結果處理失敗: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => '遊戲結果處理失敗: ' . $e->getMessage()]);
    }
} catch (Exception $e) {
    if (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
exit;
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>2048 遊戲</title>
    <link rel="stylesheet" href="css/2048css.css">
    <script>
        // 添加全局調試函數
        function debugLog(message) {
            console.log(`[2048 Debug] ${message}`);
        }
        
        // 頁面加載開始
        debugLog('頁面開始加載');
    </script>
    <script src="js/unified-game-tracker.js"></script>
    <script>
        // 將PHP難度設定傳遞給JavaScript
        const difficultySettings = <?php echo json_encode($difficultySettings); ?>;
        console.log('難度設定:', difficultySettings);
    </script>
</head>
<body>
    <!-- 難度選擇彈窗 -->
    <div id="difficultyModal" class="modal" style="align-items: flex-start !important; padding-top: 0vh !important;">
        <div class="modal-content" style="transform: translateY(-10vh) !important;">
            <button class="back-button" onclick="history.back()">
                <span class="back-arrow">←</span>
                <div class="back-label">返回</div>
            </button>
            <h2>選擇難度</h2>
            <button class="difficulty-help" id="show-instructions">
                <span class="help-icon">?</span>
                <span class="help-text">說明</span>
            </button>
            <div class="difficulty-buttons">
                <button class="difficulty-btn easy" data-target="<?php echo isset($difficultySettings['easy']['pass_score']) ? $difficultySettings['easy']['pass_score'] : 500; ?>" type="button">
                    <span class="difficulty-name">簡單模式</span>
                    <span class="difficulty-desc">(<strong><?php echo isset($difficultySettings['easy']['pass_score']) ? $difficultySettings['easy']['pass_score'] : 500; ?></strong>分)</span>
                </button>
                <button class="difficulty-btn normal" data-target="<?php echo isset($difficultySettings['normal']['pass_score']) ? $difficultySettings['normal']['pass_score'] : 1200; ?>" type="button">
                    <span class="difficulty-name">普通模式</span>
                    <span class="difficulty-desc">(<strong><?php echo isset($difficultySettings['normal']['pass_score']) ? $difficultySettings['normal']['pass_score'] : 1200; ?></strong>分)</span>
                </button>
                <button class="difficulty-btn hard" data-target="<?php echo isset($difficultySettings['hard']['pass_score']) ? $difficultySettings['hard']['pass_score'] : 2500; ?>" type="button">
                    <span class="difficulty-name">困難模式</span>
                    <span class="difficulty-desc">(<strong><?php echo isset($difficultySettings['hard']['pass_score']) ? $difficultySettings['hard']['pass_score'] : 2500; ?></strong>分)</span>
                </button>
            </div>
        </div>
    </div>

    <!-- 遊戲主畫面 -->
    <div id="game-page" class="container" style="display:none;">
        <div class="title">2048</div>
        <div class="score-bar">
            <span style="font-weight:bold;">目前分數：</span>
            <span class="score" id="score" style="color:#43a047;">0</span>
            <span style="font-weight:bold; margin-left:16px;">目標分數：</span>
            <span class="target-score" id="target-score" style="color:#f44336;">0</span>
        </div>
        <div class="board" id="board"></div>
    </div>
    <div class="btn-group">
            <button class="btn pause" id="pauseBtn">暫停遊戲</button>
            <button class="btn end" id="endBtn">結束遊戲</button>
            <button class="btn reset" id="resetBtn">重新開始</button>
    </div>

    <!-- 遊戲說明彈窗 -->
    <div id="instructions-modal" class="modal" style="display:none; align-items: flex-start !important; padding-top: 0vh !important;">
        <div class="modal-content" style="transform: translateY(-18vh) !important; min-width: 480px !important; max-width: 580px !important;">
            <span class="close-button" id="close-instructions">&times;</span>
            <h2 style="text-align:center;">
                <span style="font-size:2rem;vertical-align:middle;">🎮</span>
                <span style="font-weight:bold;vertical-align:middle;">遊戲說明</span>
            </h2>
            <div class="help-content" style="margin-top:2.5rem;padding:0 2rem;">
                <!-- 影片播放區域 -->
                <div id="2048-video-container" style="text-align:center;margin-bottom:2.5rem;">
                    <video id="2048-current-video" width="100%" height="auto" controls style="max-width:700px;width:80%;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,0.15);">
                        <source src="gd/2048-1.mp4" type="video/mp4">
                        您的瀏覽器不支援影片播放。
                    </video>
                </div>
                
                <!-- 說明文字和按鈕區域 (並排顯示) -->
                <div style="display:flex;justify-content:center;align-items:center;margin:0.5rem 1rem 1rem 1rem; gap: 20px;">
                    <!-- 上一步按鈕 -->
                    <div id="2048-prev-step-btn" style="display:none;">
                        <button id="2048-prev-step-button" onclick="goTo2048PrevStep()" class="game-step-button prev-step" style="padding:14px 28px;font-size:20px;">
                            上一步
                        </button>
                    </div>
                    
                    <!-- 說明文字 -->
                    <div id="2048-instruction-text" class="game-instruction-text">
                        先選擇遊戲困難度
                    </div>
                    
                    <!-- 下一步按鈕 -->
                    <div id="2048-next-step-btn" style="margin-left:2rem;">
                        <button id="2048-next-step-button" class="game-step-button next-step" style="padding:14px 28px;font-size:20px;">
                            下一步
                        </button>
                    </div>
                </div>
                
                <!-- 進度指示器 -->
                <div style="text-align:center;margin-top:1rem;margin-bottom:1rem;">
                    <span id="2048-step-indicator" class="game-step-indicator" style="font-size:18px;">步驟 1/2</span>
                </div>
            </div>
        </div>
    </div>

    <!-- 遊戲勝利彈窗 -->
    <div id="win-modal" class="modal" style="display:none;">
        <div class="modal-content">
            <h2>🎉 恭喜破關</h2>
            <br>
            <p>難度：<span id="win-difficulty"></span></p>
            <br>
            <p>遊戲分數：<span id="win-game-score"></span></p>
            <br>
            <p>獲得分數：+<span id="win-reward-score"></span></p>
            
            <div class="modal-buttons">
                <button id="continue-game" class="btn red-button">再玩一次</button>
                <button id="new-game" class="btn dark-blue-button">返回主頁</button>
            </div>
        </div>
    </div>

    <!-- 遊戲失敗彈窗 -->
    <div id="game-over-modal" class="modal" style="display:none;">
        <div class="modal-content">
            <h2>⏰ 遊戲失敗</h2>
            <br>
            <p>難度：<span id="game-over-difficulty"></span></p>
            <br>
            <p>目標分數：<span id="game-over-target-score"></span></p>
            <br>
            <p>獲得分數：<span id="game-over-reward-score">0</span></p>
            <br>
            <p>未達成目標分數！</p>
            
            <div class="modal-buttons">
                <button id="try-again" class="btn red-button">再玩一次</button>
                <button id="back-to-menu" class="btn dark-blue-button">返回主頁</button>
            </div>
        </div>
    </div>

    <!-- 隱藏的會員ID -->
    <input type="hidden" id="member-id" value="<?php echo $_SESSION['member_id'] ?? 1; ?>">
    
    <!-- 引入遊戲腳本 -->
    <script>
        // 獲取會員ID
        const memberIdElement = document.getElementById("member-id");
        const memberId = memberIdElement ? parseInt(memberIdElement.value) : 1;
        
        // 確保 DOM 加載完成後再執行遊戲界面初始化
        function initGameUI() {
            debugLog('開始初始化遊戲界面');
            
            // 設置難度按鈕點擊事件
            const difficultyButtons = document.querySelectorAll('.difficulty-btn');
            debugLog(`找到 ${difficultyButtons.length} 個難度按鈕`);
            
            difficultyButtons.forEach(button => {
                button.onclick = function(e) {
                    e.preventDefault();
                    debugLog(`難度按鈕被點擊: ${this.textContent}`);
                    
                    const difficultyPage = document.getElementById('difficulty-page');
                    const gamePage = document.getElementById('game-page');
                    
                    if (!difficultyPage || !gamePage) {
                        console.error('錯誤：找不到必要的頁面元素');
                        return;
                    }
                    
                    // 切換頁面
                    difficultyPage.style.display = 'none';
                    gamePage.style.display = 'block';
                    debugLog('頁面已切換到遊戲畫面');
                    
                    // 初始化遊戲
                    if (window.game) {
                        debugLog('設定遊戲難度和目標分數並重置遊戲');
                        window.game.difficulty = this.classList.contains('normal') ? 'normal' : 
                                               this.classList.contains('hard') ? 'hard' : 'easy';
                        window.game.targetScore = parseInt(this.dataset.target) || 1500;
                        window.game.resetGame();
                        debugLog('遊戲重置並初始化完成');
                    } else {
                        console.error('錯誤：找不到遊戲實例 window.game');
                    }
                };
            });
            
            // 設置遊戲說明按鈕事件
            const showInstructionsBtn = document.getElementById('show-instructions');
            const closeInstructionsBtn = document.getElementById('close-instructions');
            const instructionsModal = document.getElementById('instructions-modal');
            
            if (showInstructionsBtn && closeInstructionsBtn && instructionsModal) {
                showInstructionsBtn.onclick = () => {
                    const modalContent = instructionsModal.querySelector('.modal-content');
                    instructionsModal.style.display = 'flex';
                    instructionsModal.style.alignItems = 'flex-start';
                    instructionsModal.style.paddingTop = '0vh';
                    modalContent.style.transform = 'translateY(-18vh)';
                    modalContent.style.minWidth = '480px';
                    modalContent.style.maxWidth = '580px';
                    instructionsModal.classList.add('show');
                    // 初始化2048視頻播放邏輯
                    init2048VideoPlayback();
                };
                
                closeInstructionsBtn.onclick = () => {
                    // 停止視頻播放
                    const video = document.getElementById('2048-current-video');
                    if (video) {
                        video.pause();
                        video.currentTime = 0;
                    }
                    instructionsModal.style.display = 'none';
                    instructionsModal.classList.remove('show');
                };
                
                window.onclick = (event) => {
                    if (event.target === instructionsModal) {
                        // 停止視頻播放
                        const video = document.getElementById('2048-current-video');
                        if (video) {
                            video.pause();
                            video.currentTime = 0;
                        }
                        instructionsModal.style.display = 'none';
                        instructionsModal.classList.remove('show');
                    }
                };
            }
            
            // 初始化2048視頻播放邏輯
            function init2048VideoPlayback() {
                const video = document.getElementById('2048-current-video');
                const instructionText = document.getElementById('2048-instruction-text');
                const stepIndicator = document.getElementById('2048-step-indicator');
                const nextStepBtn = document.getElementById('2048-next-step-btn');
                const prevStepBtn = document.getElementById('2048-prev-step-btn');
                
                if (!video || !instructionText || !stepIndicator || !nextStepBtn || !prevStepBtn) {
                    console.error('找不到2048遊戲說明元素');
                    return;
                }
                
                // 設置第一個視頻
                video.src = 'gd/2048-1.mp4';
                instructionText.textContent = '先選擇遊戲困難度';
                stepIndicator.textContent = '步驟 1/2';
                
                // 設置當前視頻標記
                video.setAttribute('data-current-video', '2048-1');
                
                // 顯示下一步按鈕，隱藏上一步按鈕
                nextStepBtn.style.display = 'block';
                prevStepBtn.style.display = 'none';
                
                // 強制加載視頻
                video.load();
                
                // 添加下一步按鈕點擊事件
                const nextStepButton = document.getElementById('2048-next-step-button');
                if (nextStepButton) {
                    nextStepButton.onclick = goTo2048NextStep;
                    console.log('2048下一步按鈕事件已綁定');
                }
            }
            
            // 前往2048下一步
            function goTo2048NextStep() {
                const video = document.getElementById('2048-current-video');
                const instructionText = document.getElementById('2048-instruction-text');
                const stepIndicator = document.getElementById('2048-step-indicator');
                const nextStepBtn = document.getElementById('2048-next-step-btn');
                const prevStepBtn = document.getElementById('2048-prev-step-btn');
                
                // 切換到第二個視頻
                video.src = 'gd/2048-2.mp4';
                video.setAttribute('data-current-video', '2048-2');
                instructionText.innerHTML = '動動手指或鍵盤上下左右建滑動方塊，相同數字會合併（如2+2=4）最高128分，目標是在限時內達到指定分數就過關！';
                stepIndicator.textContent = '步驟 2/2';
                
                // 隱藏下一步按鈕，顯示上一步按鈕
                nextStepBtn.style.display = 'none';
                prevStepBtn.style.display = 'block';
                
                // 加載並播放視頻
                video.load();
                video.play();
            }
            
            // 回到2048上一步
            function goTo2048PrevStep() {
                const video = document.getElementById('2048-current-video');
                const instructionText = document.getElementById('2048-instruction-text');
                const stepIndicator = document.getElementById('2048-step-indicator');
                const nextStepBtn = document.getElementById('2048-next-step-btn');
                const prevStepBtn = document.getElementById('2048-prev-step-btn');
                
                // 切換到第一個視頻
                video.src = 'gd/2048-1.mp4';
                video.setAttribute('data-current-video', '2048-1');
                instructionText.textContent = '先選擇遊戲困難度';
                stepIndicator.textContent = '步驟 1/2';
                
                // 顯示下一步按鈕，隱藏上一步按鈕
                nextStepBtn.style.display = 'block';
                prevStepBtn.style.display = 'none';
                
                // 加載並播放視頻
                video.load();
                video.play();
            }
            
            // 設為全局可訪問
            window.goTo2048NextStep = goTo2048NextStep;
            window.goTo2048PrevStep = goTo2048PrevStep;
            
            debugLog('遊戲界面初始化完成');
        }

        // 初始化最高分數顯示 - 已移除
        // function initBestScore() {
        //     const bestScore = parseInt(localStorage.getItem('bestScore')) || 0;
        //     const bestScoreElement = document.getElementById('best-score');
        //     if (bestScoreElement) {
        //         bestScoreElement.textContent = bestScore;
        //     }
        //     debugLog('初始化最高分數: ' + bestScore);
        // }

        // 當 DOM 加載完成時初始化遊戲界面
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => {
                debugLog('DOM 加載完成，開始初始化遊戲界面');
                initGameUI();
                // initBestScore(); // 初始化最高分數 - 已移除
                // 顯示難度選擇彈窗
                const difficultyModal = document.getElementById('difficultyModal');
                const modalContent = difficultyModal.querySelector('.modal-content');
                difficultyModal.style.display = 'flex';
                difficultyModal.style.alignItems = 'flex-start';
                difficultyModal.style.paddingTop = '0vh';
                modalContent.style.transform = 'translateY(-10vh)';
                difficultyModal.classList.add('show');
            });
        } else {
            debugLog('DOM 已加載，立即初始化遊戲界面');
            initGameUI();
            // initBestScore(); // 初始化最高分數 - 已移除
            // 顯示難度選擇彈窗
            const difficultyModal = document.getElementById('difficultyModal');
            const modalContent = difficultyModal.querySelector('.modal-content');
            difficultyModal.style.display = 'flex';
            difficultyModal.style.alignItems = 'flex-start';
            difficultyModal.style.paddingTop = '0vh';
            modalContent.style.transform = 'translateY(-10vh)';
            difficultyModal.classList.add('show');
        }

        // 修改遊戲結束時的處理
        function handleGameEnd(score, difficulty, isWin, gameTime) {
            // 更新本地存儲的最高分 - 已移除
            // const currentBest = parseInt(localStorage.getItem('bestScore')) || 0;
            // const newBest = Math.max(score, currentBest);
            // if (score > currentBest) {
            //     localStorage.setItem('bestScore', score);
            //     // 更新頁面上的最高分顯示
            //     const bestScoreElement = document.getElementById('best-score');
            //     if (bestScoreElement) {
            //         bestScoreElement.textContent = score;
            //     }
            //     debugLog('更新最高分數: ' + score);
            // }
            
            // 顯示相應的彈窗並填入數據
            if (isWin === true) { // 只有真正勝利才顯示勝利彈窗
                // 填入勝利彈窗的數據
                const rewardScore = getRewardScore(difficulty);
                
                const winDifficulty = document.getElementById('win-difficulty');
                const winGameScore = document.getElementById('win-game-score');
                const winRewardScore = document.getElementById('win-reward-score');
                const winModal = document.getElementById('win-modal');
                
                if (winDifficulty) winDifficulty.textContent = getDifficultyName(difficulty);
                if (winGameScore) winGameScore.textContent = score;
                if (winRewardScore) winRewardScore.textContent = rewardScore;
                if (winModal) winModal.style.display = 'block';
            } else {
                // 失敗或手動退出都顯示失敗彈窗
                const gameOverDifficulty = document.getElementById('game-over-difficulty');
                const gameOverTargetScore = document.getElementById('game-over-target-score');
                const gameOverRewardScore = document.getElementById('game-over-reward-score');
                const gameOverModal = document.getElementById('game-over-modal');
                
                if (gameOverDifficulty) gameOverDifficulty.textContent = getDifficultyName(difficulty);
                if (gameOverTargetScore) gameOverTargetScore.textContent = getTargetScore(difficulty);
                if (gameOverRewardScore) gameOverRewardScore.textContent = '+0'; // 失敗或手動退出都是 0 分
                if (gameOverModal) gameOverModal.style.display = 'block';
            }
        }
        
        // 格式化遊戲時間
        function formatGameTime(seconds) {
            if (!seconds || seconds < 0) return '00:00';
            const minutes = Math.floor(seconds / 60);
            const remainingSeconds = seconds % 60;
            return `${minutes.toString().padStart(2, '0')}:${remainingSeconds.toString().padStart(2, '0')}`;
        }
        
        // 獲取目標分數
        function getTargetScore(difficulty) {
            if (difficultySettings && difficultySettings[difficulty]) {
                return difficultySettings[difficulty].pass_score.toString();
            }
            // 預設值
            switch(difficulty) {
                case 'easy': return '500';
                case 'normal': return '1200';
                case 'hard': return '2500';
                default: return '500';
            }
        }
        
        // 獲取難度名稱
        function getDifficultyName(difficulty) {
            switch(difficulty) {
                case 'easy': return '簡單';
                case 'normal': return '普通';
                case 'hard': return '困難';
                default: return difficulty;
            }
        }
        
        // 獲取獎勵分數
        function getRewardScore(difficulty) {
            switch(difficulty) {
                case 'easy': return '20';
                case 'normal': return '50';
                case 'hard': return '100';
                default: return '0';
            }
        }

        // 返回主頁按鈕處理
        function handleBackButton() {
            // 智能返回：回到上一頁，如果沒有上一頁則回到首頁
            if (document.referrer && document.referrer !== window.location.href) {
                history.back();
            } else {
                window.location.href = 'index.php';
            }
        }
    </script>
    <script>
        // 檢測觸控設備並顯示相應提示
        function checkTouchDevice() {
            const touchHint = document.querySelector('.touch-hint');
            if (touchHint) {
                // 檢測是否為觸控設備
                const isTouchDevice = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
                if (isTouchDevice) {
                    touchHint.style.display = 'block';
                } else {
                    touchHint.style.display = 'none';
                }
            }
        }
        
        // 頁面加載時檢測
        document.addEventListener('DOMContentLoaded', checkTouchDevice);
    </script>
    <script src="js/game-exit-handler.js"></script>
    <script src="js/game-common.js"></script>
    <script src="js/get-score.js"></script>
    <script src="js/game.js"></script>
    <script src="js/auto-save-time-fixed.js"></script>
    <script>
        // 配置遊戲退出處理器
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof gameExitHandler !== 'undefined') {
                gameExitHandler.updateConfig({
                    memberId: <?= $_SESSION['member_id'] ?? 'null' ?>,
                    gameType: '算術邏輯力',
                    gameId: 4,
                    difficulty: 'easy'
                });
                // 立即啟動遊戲退出追蹤，因為用戶已經進入遊戲頁面
                gameExitHandler.startGame();
                console.log('遊戲退出處理器已配置並啟動');
            }
            
            // 遊戲開始時啟動追蹤（在真正開始遊戲時調用）
            // 注意：這裡不應該在頁面載入時就啟動追蹤
            // 應該在用戶點擊開始遊戲按鈕時才啟動
        });
    </script>
</body>
</html>