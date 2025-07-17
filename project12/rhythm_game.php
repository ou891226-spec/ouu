<?php
require_once 'check_login.php';
require_once 'rhythm_game_db.php';
 
// 獲取難度設定
$difficulties = [];

try {
    // 嘗試查詢專門的節奏遊戲難度設定表
    $stmt = $pdo->query("SELECT * FROM rhythm_game_difficulty_settings WHERE is_active = true");
    $difficulties = $stmt->fetchAll();
} catch (PDOException $e) {
    // 如果表不存在，使用預設設定
    $difficulties = [
        [
            'difficulty_level' => 'easy',
            'note_count' => 3,
            'time_limit' => 60,
            'speed' => 1.0,
            'pass_score' => 100,
            'is_active' => true
        ],
        [
            'difficulty_level' => 'normal',
            'note_count' => 5,
            'time_limit' => 90,
            'speed' => 1.5,
            'pass_score' => 200,
            'is_active' => true
        ],
        [
            'difficulty_level' => 'hard',
            'note_count' => 7,
            'time_limit' => 120,
            'speed' => 2.0,
            'pass_score' => 300,
            'is_active' => true
        ]
    ];
}

?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>節奏遊戲</title>
  <link rel="stylesheet" href="css/rhythm_game.css" />
</head>
<body>
    <div class="game-container">
        <input type="hidden" id="member-id" value="<?php echo $_SESSION['member_id'] ?? 1; ?>">
        <h1>節奏遊戲</h1>

        <div class="score-board">
            <h2>目前分數: <span id="score">0</span></h2>
            <h2>最高分數: <span id="high-score">0</span></h2>
            <h2>剩餘時間: <span id="timer">60</span> 秒</h2>
        </div>

        <!-- 難度選擇 Modal -->
        <div id="difficulty-modal" class="modal">
            <div class="modal-content" style="position:relative;">
                <!-- 🔙 返回鍵：左上角 -->
                <div class="back-button">
                    <button class="back-arrow" id="back-btn" title="返回">
                        <span class="arrow">&larr;</span>
                    </button>
                    <div class="btn-label">返回</div>
                </div>

                <!-- ❓ 說明鍵：右上角 -->
                <div style="position:absolute; top:1.2rem; right:1.2rem; text-align:center; z-index:10;">
                    <button class="help-btn" id="info-btn" title="說明">?</button>
                    <div class="help-label">說明</div>
                </div>

                <h2>難度選擇</h2>
                <div class="difficulty-option easy" data-difficulty="easy">簡單 (3個)</div>
                <div class="difficulty-option medium" data-difficulty="normal">普通 (5個)</div>
                <div class="difficulty-option hard" data-difficulty="hard">困難 (7個)</div>
            </div>
        </div>

        <!-- 🟡 新增：遊戲說明彈窗 -->
        <div id="info-modal" class="modal" style="display:none;">
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
                        跟著音符的節奏點擊打擊區，時間內累積足夠分數就過關！
                    </div>
                    <div style="display:flex;align-items:center;margin-bottom:0.5rem;">
                        <span style="color:#3b82f6;font-size:1.2rem;margin-right:0.5rem;">◆</span>
                        <span style="font-weight:bold;font-size:1.1rem;">玩法</span>
                    </div>
                    <ul style="margin-left:2.2rem;">
                        <li>選擇難度後開始遊戲</li>
                        <li>跟著音符節奏點擊打擊區</li>
                        <li>Miss不給分、Good+10分、Perfect+20分</li>
                        <li>時間內累積足夠分數過關！</li>
                    </ul>
                </div>
                <span class="close-btn" onclick="closeInfoModal()" style="position:absolute; top:10px; right:15px; cursor:pointer; font-size: 30px;">×</span>
            </div>
        </div>

        <div id="result-modal" class="modal" style="display: none;">
            <div class="modal-content">
                <h2 id="result-title"></h2>
                <p id="result-difficulty"></p>
                <p id="result-score"></p>
                <div>
                    <button onclick="location.reload()">再玩一次</button>
                    <button onclick="window.location.href='index.php'">返回主頁</button>
                </div>
            </div>
        </div>

        <div id="gameArea">
            <div id="noteTrack"></div>
            <div id="hitZone">
                <span class="hit-label">打擊區</span>
                <div id="bat">
                    <img src="img/bat.png" alt="球棒" />
                </div> <!-- ← 球棒圖案 -->
            </div>
        </div>

        <div class="button-group">
            <button class="control-btn" id="pause-btn">暫停遊戲</button>
            <button class="control-btn" id="end-btn">結束遊戲</button>
            <button class="control-btn" id="restart-btn">重新開始</button>
        </div>

        <audio id="bgm" preload="auto"></audio>
    </div>    
    <script src="js/rhythm_game.js"></script>
    <script src="js/auto-save-time.js"></script>
</body>
</html>
