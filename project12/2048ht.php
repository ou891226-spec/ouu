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
        $stmt = $pdo->prepare("INSERT INTO game_records
            (member_id, game_id, score, difficulty, play_date, play_time, game_type, is_single_player, opponent_id)
            VALUES (:member_id, :game_id, :score, :difficulty, NOW(), :play_time, :game_type, 1, NULL)");
        $stmt->execute([
            'member_id' => $data['member_id'],
            'game_id' => 4,
            'score' => $data['score'],
            'difficulty' => $data['difficulty'],
            'play_time' => $data['play_time'],
            'game_type' => '邏輯力'
        ]);
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
    <!-- 難度選擇畫面 -->
    <div id="difficulty-page" class="main-bg">
        <div class="main-title">2048</div>
        <div class="difficulty-modal">
            <div class="difficulty-header">
                <span class="difficulty-title">難度選擇</span>
                <button class="difficulty-help" id="show-instructions">
                    <span class="help-icon">?</span>
                    <span class="help-text">說明</span>
                </button>
            </div>
            <button class="difficulty-btn easy" data-target="1500" type="button">簡單 目標：1500分</button>
            <button class="difficulty-btn normal" data-target="5000" type="button">普通 目標：5000分</button>
            <button class="difficulty-btn hard" data-target="10000" type="button">困難 目標：10000分</button>
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
        </div>
        <div class="board" id="board"></div>
        <div class="btn-group">
            <button class="btn pause" id="pauseBtn">暫停遊戲</button>
            <button class="btn end" id="endBtn">結束遊戲</button>
            <button class="btn reset" id="resetBtn">重新開始</button>
        </div>
    </div>

    <!-- 遊戲說明彈窗 -->
    <div id="instructions-modal" class="modal" style="display:none;">
        <div class="modal-content">
            <span class="close-button" id="close-instructions">&times;</span>
            <h2>遊戲說明</h2>
            <ul>
                <li>使用鍵盤方向鍵移動方塊。</li>
                <li>相同數字的方塊合併會加分。</li>
                <li>每次移動後會隨機出現新方塊（2或4）。</li>
                <li>無法移動時遊戲結束。</li>
                <li>達到目標分數即可破關。</li>
            </ul>
        </div>
    </div>

    <!-- 遊戲勝利彈窗 -->
    <div id="win-modal" class="modal" style="display:none;">
        <div class="modal-content">
            <h2>恭喜破關</h2>
            <p>難度：<span id="win-difficulty"></span></p>
            <p>獲得分數：<span id="win-score"></span></p>
            <p>最高分數：<span id="win-best-score"></span></p>
            <p>目標分數：<span id="win-target"></span></p>
            <div class="modal-buttons">
                <button id="continue-game" class="btn red-button">繼續遊戲</button>
                <button id="new-game" class="btn dark-blue-button">返回主頁</button>
            </div>
        </div>
    </div>

    <!-- 遊戲失敗彈窗 -->
    <div id="game-over-modal" class="modal" style="display:none;">
        <div class="modal-content">
            <h2>遊戲失敗</h2>
            <p>難度：<span id="game-over-difficulty"></span></p>
            <p>獲得分數：<span id="game-over-score"></span></p>
            <p>最高分數：<span id="game-over-best-score"></span></p>
            <p>目標分數：<span id="game-over-target"></span></p>
            <div class="modal-buttons">
                <button id="try-again" class="btn red-button">再玩一次</button>
                <button id="back-to-menu" class="btn dark-blue-button">返回主頁</button>
            </div>
        </div>
    </div>

    <!-- 引入遊戲腳本 -->
    <script>
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
                        window.game.difficulty = this.classList.contains('normal') ? 'medium' : 
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
                    instructionsModal.style.display = 'block';
                };
                
                closeInstructionsBtn.onclick = () => {
                    instructionsModal.style.display = 'none';
                };
                
                window.onclick = (event) => {
                    if (event.target === instructionsModal) {
                        instructionsModal.style.display = 'none';
                    }
                };
            }
            
            debugLog('遊戲界面初始化完成');
        }

        // 當 DOM 加載完成時初始化遊戲界面
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => {
                debugLog('DOM 加載完成，開始初始化遊戲界面');
                initGameUI();
            });
        } else {
            debugLog('DOM 已加載，立即初始化遊戲界面');
            initGameUI();
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
    </script>
    <script src="js/game.js"></script>
    <script src="js/auto-save-time.js"></script>
</body>
</html>