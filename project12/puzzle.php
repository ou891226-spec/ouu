<?php
// 啟動輸出緩衝
ob_start();

// 只在會話未啟動時啟動會話
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'db_connect.php';

// 獲取會員ID（從session或預設值）
$member_id = $_SESSION['member_id'] ?? 1;
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>數字排排樂</title>
    <link rel="stylesheet" href="css/puzzle.css">
    <meta name="member_id" content="<?php echo $member_id; ?>">
    <script src="js/unified-game-tracker.js"></script>
</head>
<body>
    <!-- 隱藏的會員ID輸入框，供JavaScript使用 -->
    <input type="hidden" name="member_id" value="<?php echo $member_id; ?>">

    <div id="difficulty-modal-overlay" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <div class="header-control" id="modal-back-button">
                    <span class="modal-icon back-icon">←</span>
                    <div class="control-label">返回</div>
                </div>
                <h2>選擇難度</h2>
                <div class="header-control" id="modal-help-button">
                    <span class="modal-icon help-icon">?</span>
                    <div class="control-label">說明</div>
                </div>
            </div>
            
            <div class="modal-body">
                <button class="btn modal-btn" data-size="3" data-difficulty-name="簡單">簡單 (3x3)</button>
                <button class="btn modal-btn" data-size="4" data-difficulty-name="普通">普通 (4x4)</button>
                <button class="btn modal-btn" data-size="5" data-difficulty-name="困難">困難 (5x5)</button>
            </div>
        </div>
    </div>

    <div id="help-modal-overlay" class="modal-overlay hidden">
        <div class="modal-content help-modal-content">
        
            <div class="close-button-absolute" id="help-modal-close-button"> 
                <span class="close-icon-only">X</span>
            </div>
        
            <div class="help-header">
                <h2>🎮遊戲說明</h2>
            </div>
        
            <div class="modal-body help-body">
                <p><strong>遊戲目標：</strong></p>
                <p>您的目標是將數字方塊按順序排列，最終形成從1到N-1的數字，最後一個是空白格。</p>
            
                <p><strong>如何移動：</strong></p>
                <ul>
                    <li><strong>點擊：</strong>點擊與空白格相鄰的方塊。</li>
                    <li><strong>滑動/拖曳：</strong>在遊戲區域內，朝著空白格的方向滑動或拖曳。</li>
                </ul>
            
                <p><strong>計分方式：</strong></p>
                <ul>
                    <li><strong>步數：</strong>記錄您移動方塊的總次數。</li>
                    <li><strong>時間：</strong>記錄您完成遊戲所花費的總時間。</li>
                </ul>
            </div>
        </div>
    </div>

    <div id="win-modal-overlay" class="modal-overlay hidden">
        <div class="modal-content win-modal-content">
            <h2 class="win-title">🎉 恭喜過關！</h2>
        
            <div class="win-details-container">
                <div class="win-detail">難度：<span id="win-difficulty-name">--</span></div>
                <div class="win-detail">總移動步數：<span id="win-move-count">--</span></div>
                <div class="win-detail">剩餘步數：<span id="win-moves-left">--</span></div>
                <div class="win-detail">遊戲時間：<span id="win-time-elapsed">--</span></div>
                <div class="win-detail">獲得分數：<span id="win-score">+0 分</span></div>
            </div>

            <div id="win-fail-message" class="win-fail-message hidden">
                未在步數內完成遊戲!
            </div>
        
            <div class="modal-body win-buttons">
                <button id="play-again-button" class="win-btn retry-btn">再玩一次</button>
                <button id="back-to-home-button" class="win-btn back-btn">返回主頁</button>
            </div>
        </div>
    </div>

    <div class="game-container">
        <h1>數字排排樂</h1>

        <div id="score-board" class="score-board hidden">
            <span>剩餘步數: <span id="move-count" class="green-text">--</span></span>
            <span>遊戲時間: <span id="time-elapsed" class="red-text">0 秒</span></span>
        </div>

        <div id="game-board" class="game-board">
            </div>
        
        <div class="controls game-controls">
            <button id="pause-continue-button" class="btn orange-btn">暫停遊戲</button> 
            <button id="end-game-button" class="btn red-btn">結束遊戲</button>
            <button id="reset-button" class="btn blue-btn">重新開始</button>
        </div>

        <div id="message" class="message hidden">
            恭喜！您成功了！
        </div>
    </div>

    <script>
        // 初始化遊戲追蹤器
        document.addEventListener("DOMContentLoaded", function() {
            gameTracker.init("算術邏輯力", 10);
        });
    </script>
    <script src="js/game-exit-handler.js"></script>
    <script src="js/game-common.js"></script>
    <script src="js/get-score.js"></script>
    <script src="js/puzzle.js"></script>
    <script>
        // 配置遊戲退出處理器
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof gameExitHandler !== 'undefined') {
                gameExitHandler.updateConfig({
                    memberId: <?= $_SESSION['member_id'] ?? 'null' ?>,
                    gameType: '算術邏輯力',
                    gameId: 10,
                    difficulty: '<?= isset($_GET["difficulty"]) ? $_GET["difficulty"] : "easy" ?>'
                });
                // 立即啟動遊戲退出追蹤，因為用戶已經進入遊戲頁面
                gameExitHandler.startGame();
                console.log('遊戲退出處理器已配置並啟動');
            }
        });
    </script>
</body>
</html>