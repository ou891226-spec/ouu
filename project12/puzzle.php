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
    <script src="js/unified-game-tracker.js"></script>
</head>
<body>
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
                <span class="close-icon-only">&times;</span>
            </div>
        
            <div class="help-header-new">
                <span class="help-header-icon">🎮</span>
                <h2>遊戲說明</h2>
            </div>
        
            <div class="modal-body help-body-new">
                <div id="help-page-1" class="help-page">
                    <div class="video-container">
                        <video id="help-video" class="help-video-player" src="gd/puzzle.mp4" title="遊戲玩法影片" width="100%" controls playsinline>
                            您的瀏覽器不支援此影片。
                        </video>
                    </div>
                    <p class="help-description">
                        先選擇遊戲難度，遊戲開始後，<br>透過點擊或滑動，<br>與空格相鄰的方塊來移動它們，<br>將所有數字由 1 開始順序排好。
                    </p>
                </div>

                <div id="help-page-2" class="help-page hidden">
                    <div class="image-gallery">
                        <img src="img/puzzle_easy.jpg" alt="遊戲示意圖1" class="gallery-img">
                        <img src="img/puzzle_normal.jpg" alt="遊戲示意圖2" class="gallery-img">
                        <img src="img/puzzle_hard.jpg" alt="遊戲示意圖3" class="gallery-img">
                        <p class="gallery-label">簡單</p>
                        <p class="gallery-label">普通</p>
                        <p class="gallery-label">困難</p>
                    </div>
                    <p class="help-description">
                        隨著難度選擇的不同，方格數量會增加，<br>在指定步數內完成拼圖即可過關。
                    </p>
                </div>
            </div>

            <div class="help-modal-footer">
                <button id="help-back-btn" class="btn help-nav-btn prev hidden">上一步</button>
                <span id="help-page-indicator" class="help-step-indicator">步驟 1/2</span>
                <button id="help-next-btn" class="btn help-nav-btn next">下一步</button>
            </div>
        </div>
    </div>

    <div id="win-modal-overlay" class="modal-overlay hidden">
        <div class="modal-content win-modal-content">
            <h2 class="win-title">🎉 恭喜過關！</h2>
        
            <div class="win-details-container">
                <div class="win-detail">難度：<span id="win-difficulty-name">--</span></div>
                <div class="win-detail">總移動步數：<span id="win-move-count">--</span></div>
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

    <div id="solution-modal-overlay" class="modal-overlay hidden">
        <div class="modal-content">
            <h2>完成參考圖</h2>
            <div id="solution-board" class="game-board"></div>
            <div class="modal-body">
                <button id="close-solution-btn" class="btn blue-btn">關閉</button>
            </div>
        </div>
    </div>

    <div class="game-container">
        <h1>數字排排樂</h1>

        <div id="score-board" class="score-board hidden">
            <span>總移動步數: <span id="move-count" class="green-text">0</span></span>
            <span>遊戲時間: <span id="time-elapsed" class="red-text">0 秒</span></span>
            <button id="show-solution-btn" class="btn solution-btn">💡 完成參考圖</button>
        </div>

        <div id="game-board" class="game-board"></div>
        
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
    <script src="js/auto-save-time-fixed.js"></script>
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
                // 遊戲追蹤將在真正開始遊戲時啟動
                console.log('遊戲退出處理器已配置並啟動');
            }
        });
    </script>
</body>
</html>