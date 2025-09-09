<?php
require_once 'check_login.php';
require_once 'db_connect.php';
 
// 獲取難度設定
$difficulties = [];

try {
    // 使用統一的 difficulty_settings 表查詢節奏遊戲設定 (game_id = 7)
    $stmt = $pdo->query("SELECT * FROM difficulty_settings WHERE game_id = 7 ORDER BY difficulty");
    $db_difficulties = $stmt->fetchAll();
    
    // 轉換為節奏遊戲需要的格式
    $difficulties = [];
    foreach ($db_difficulties as $setting) {
        $difficulties[] = [
            'difficulty_level' => $setting['difficulty'],
            'note_count' => $setting['difficulty'] === 'easy' ? 3 : ($setting['difficulty'] === 'normal' ? 5 : 7),
            'time_limit' => $setting['time_limit'] ?? 60,
            'speed' => $setting['difficulty'] === 'easy' ? 1.0 : ($setting['difficulty'] === 'normal' ? 1.5 : 2.0),
            'pass_score' => $setting['pass_score'] ?? 100,
            'is_active' => true
        ];
    }
} catch (PDOException $e) {
    // 如果查詢失敗，使用預設設定
    $difficulties = [
        [
            'difficulty_level' => 'easy',
            'note_count' => 3,
            'time_limit' => 60,
            'speed' => 1.0,
            'pass_score' => 1200,
            'is_active' => true
        ],
        [
            'difficulty_level' => 'normal',
            'note_count' => 5,
            'time_limit' => 60,
            'speed' => 1.5,
            'pass_score' => 800,
            'is_active' => true
        ],
        [
            'difficulty_level' => 'hard',
            'note_count' => 7,
            'time_limit' => 60,
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

                <h2>選擇難度</h2>
                <div class="difficulty-option easy" data-difficulty="easy">簡單 (3個)</div>
                <div class="difficulty-option medium" data-difficulty="normal">普通 (5個)</div>
                <div class="difficulty-option hard" data-difficulty="hard">困難 (7個)</div>
            </div>
        </div>

        <!-- 🟡 新增：遊戲說明彈窗 -->
        <div id="info-modal" class="modal" style="display:none;">
            <div class="modal-content">
                <span class="close-btn" onclick="closeInfoModal()" style="position:absolute; top:10px; right:15px; cursor:pointer; font-size: 30px;">×</span>
                <h2 style="text-align:center;">
                    <span style="font-size:2rem;vertical-align:middle;">🎮</span>
                    <span style="font-weight:bold;vertical-align:middle;">遊戲說明</span>
                </h2>
                <div class="help-content" style="margin-top:2.5rem;padding:0 2rem;">
                    <!-- 影片播放區域 -->
                    <div id="rhythm-video-container" style="text-align:center;margin-bottom:2.5rem;">
                        <video id="rhythm-current-video" width="100%" height="auto" controls style="max-width:700px;width:80%;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,0.15);">
                            <source src="gd/rhythm1.mp4" type="video/mp4">
                            您的瀏覽器不支援影片播放。
                        </video>
                    </div>
                    
                    <!-- 說明文字和按鈕區域 (並排顯示) -->
                    <div style="display:flex;justify-content:center;align-items:center;margin:0 1rem;margin-bottom:2rem; gap: 20px;">
                        <!-- 上一步按鈕 -->
                        <div id="rhythm-prev-step-btn" style="display:none;">
                            <button id="rhythm-prev-step-button" onclick="goToRhythmPrevStep()" class="game-step-button prev-step" style="padding:14px 28px;font-size:20px;">
                                上一步
                            </button>
                        </div>
                        
                        <!-- 說明文字 -->
                        <div id="rhythm-instruction-text" class="game-instruction-text" style="font-size:24px;flex:3;text-align:center;min-width:300px;">
                            跟著音符的節奏點擊打擊區
                        </div>
                        
                        <!-- 下一步按鈕 -->
                        <div id="rhythm-next-step-btn" style="margin-left:2rem;">
                            <button id="rhythm-next-step-button" class="game-step-button next-step" style="padding:14px 28px;font-size:20px;">
                                下一步
                            </button>
                        </div>
                    </div>
                    
                    <!-- 進度指示器 -->
                    <div style="text-align:center;margin-top:1.5rem;margin-bottom:1.5rem;">
                        <span id="rhythm-step-indicator" class="game-step-indicator" style="font-size:18px;">步驟 1/2</span>
                    </div>
                </div>
            </div>
        </div>

        <div id="result-modal" class="modal" style="display: none;">
            <div class="modal-content">
                <h2 id="result-title"></h2>
                <p id="result-difficulty"></p>
                <p id="result-score"></p>
                <div>
                    <button onclick="location.reload()">再玩一次</button>
                    <button onclick="smartReturn()">返回主頁</button>
                </div>
            </div>
        </div>

        <div id="gameArea">
            <div id="noteTrack"></div>
            <div id="hitZone">
                <span class="hit-label">打擊區</span>
            </div>    
            <div id="bat">
                <img src="img/bat.png" alt="球棒" />
            </div> <!-- ← 球棒圖案 -->
        </div>

        <div class="button-group">
            <button class="control-btn" id="pause-btn">暫停遊戲</button>
            <button class="control-btn" id="end-btn">結束遊戲</button>
            <button class="control-btn" id="restart-btn">重新開始</button>
        </div>

        <audio id="bgm" preload="auto"></audio>
    </div>    
    <script>
        function smartReturn() {
            // 智能返回：回到上一頁，如果沒有上一頁則回到首頁
            if (document.referrer && document.referrer !== window.location.href) {
                history.back();
            } else {
                window.location.href = 'index.php';
            }
        }
    </script>
    <script src="js/rhythm_game.js"></script>
    <script src="js/auto-save-time-fixed.js"></script>
</body>
</html>