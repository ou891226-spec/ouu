<?php
require_once 'check_login.php';
header('Content-Type: text/html; charset=utf-8');

if (
    isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest' &&
    $_SERVER['REQUEST_METHOD'] === 'POST'
) {
    require_once 'db_connect.php';
    $data = json_decode(file_get_contents('php://input'), true);
    error_log('收到資料: ' . print_r($data, true));
    try {
        // 檢查是否為遊戲勝利（分數大於0表示勝利）
        $score = isset($data['score']) ? intval($data['score']) : 0;
        $difficulty = isset($data['difficulty']) ? $data['difficulty'] : 'easy';
        
        error_log('收到分數: ' . $score . ', 難度: ' . $difficulty);
        
        // 根據是否過關決定記錄的分數
        $is_passed = ($score > 0);
        $record_score = 0;
        
        if ($is_passed) {
            // 遊戲勝利，依難度給固定獎勵分數
            switch ($difficulty) {
                case 'hard':
                    $record_score = 100;
                    break;
                case 'normal': // 保持向後兼容
                    $record_score = 50;
                    break;
                case 'easy':
                default:
                    $record_score = 20;
                    break;
            }
        }
        // 如果沒過關，record_score 保持為 0

        $stmt = $pdo->prepare("INSERT INTO game_records
            (member_id, game_id, difficulty, score, play_date, play_time, game_type, is_single_player, opponent_id)
            VALUES (:member_id, :game_id, :difficulty, :score, NOW(), :play_time, :game_type, :is_single_player, :opponent_id)");
        $stmt->execute([
            'member_id' => $data['member_id'],
            'game_id' => 4,
            'difficulty' => $difficulty,
            'score' => $record_score,
            'play_time' => null, // 設為null避免在行為軌跡分析中產生誤導
            'game_type' => '邏輯力',
            'is_single_player' => 1,
            'opponent_id' => null
        ]);
        
        // 只有過關時才更新會員總分數和邏輯分數
        if ($is_passed) {
            $update_stmt = $pdo->prepare("UPDATE member SET total_score = total_score + :score, logic_score = logic_score + :score WHERE member_id = :member_id");
            $update_stmt->execute([
                'score' => $record_score,
                'member_id' => $data['member_id']
            ]);
            error_log('遊戲勝利，記錄獎勵分數: ' . $record_score);
        } else {
            error_log('遊戲失敗，記錄0分');
        }
        
        error_log('寫入成功');
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        error_log('寫入失敗: ' . $e->getMessage());
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
                <button class="difficulty-btn easy" data-target="1500" type="button">
                    <span class="difficulty-name">簡單模式</span>
                    <span class="difficulty-desc">(1500分)</span>
                </button>
                <button class="difficulty-btn normal" data-target="5000" type="button">
                    <span class="difficulty-name">普通模式</span>
                    <span class="difficulty-desc">(5000分)</span>
                </button>
                <button class="difficulty-btn hard" data-target="10000" type="button">
                    <span class="difficulty-name">困難模式</span>
                    <span class="difficulty-desc">(10000分)</span>
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
            <span style="font-weight:bold; margin-left:16px;">最高分數：</span>
            <span class="best" id="best-score" style="color:#43a047;">0</span>
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
                    <div id="2048-instruction-text" class="game-instruction-text" style="font-size:24px;flex:3;text-align:center;min-width:300px;">
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
            <p>難度：<span id="win-difficulty"></span></p>
            <p>獲得分數：<span id="win-game-score"></span></p>
            <p>最高分數：<span id="win-best-score"></span></p>
            <p>過關分數：<span id="win-reward-score"></span></p>
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
            <p>難度：<span id="game-over-difficulty"></span></p>
            <p>未在時間內達成分數</p>
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
                    instructionsModal.style.display = 'none';
                    instructionsModal.classList.remove('show');
                };
                
                window.onclick = (event) => {
                    if (event.target === instructionsModal) {
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

        // 當 DOM 加載完成時初始化遊戲界面
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => {
                debugLog('DOM 加載完成，開始初始化遊戲界面');
                initGameUI();
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
        function handleGameEnd(score, difficulty, isWin) {
            // 更新本地存儲的最高分
            const currentBest = parseInt(localStorage.getItem('bestScore')) || 0;
            if (score > currentBest) {
                localStorage.setItem('bestScore', score);
            }
            
            // 顯示相應的彈窗
            if (isWin) {
                document.getElementById('win-modal').style.display = 'block';
            } else {
                document.getElementById('game-over-modal').style.display = 'block';
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
    <script src="js/game.js"></script>
    <script src="js/auto-save-time-fixed.js"></script>
</body>
</html>