<?php
require_once 'check_login.php';
require_once 'db_connect_catch_egg_game.php';

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
            echo json_encode(['success' => true]);
            break;
            
        case 'end_game':
            $score = $_POST['score'] ?? 0;
            $playTime = isset($_SESSION['game_start_time']) ? (time() - $_SESSION['game_start_time']) : 0;
            $difficulty = $_SESSION['current_difficulty'] ?? 'easy';
            $pass_score = ($difficulty === 'easy') ? 200 : (($difficulty === 'normal') ? 450 : 600);
            $status = ($score >= $pass_score) ? 'success' : 'failed';
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("INSERT INTO game_records (member_id, game_id, difficulty, score, play_date, play_time, game_type, is_single_player, opponent_id) VALUES (:member_id, :game_id, :difficulty, :score, NOW(), :play_time, :game_type, :is_single_player, :opponent_id)");
                $stmt->execute([
                    'member_id' => $member_id,
                    'game_id' => 2,
                    'difficulty' => $difficulty,
                    'score' => $score,
                    'play_time' => $playTime,
                    'game_type' => '反應力',
                    'is_single_player' => 1,
                    'opponent_id' => null
                ]);

                $pdo->commit();

                echo json_encode([
                    'success' => true,
                    'member_id' => (string)$member_id,
                    'difficulty' => $difficulty,
                    'status' => $status,
                    'score' => (int)$score,
                    'play_time' => (int)$playTime
                ]);
            } catch (Exception $e) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>接金蛋遊戲</title>
    <link rel="stylesheet" href="css/Catch-Egg.css">
</head>
<body>
    <!-- 遊戲主畫面 -->
    <div id="game-container" class="game-container">
        <h1>接金蛋遊戲</h1>
        
        <div class="score-board">
            <span class="label">目前分數：</span>
            <span id="score" class="value">0</span>
            <span class="label">最高分數：</span>
            <span id="high-score" class="value">0</span>
            <span class="label">剩餘時間：</span>
            <span id="timer" class="value">60</span>
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
                <button class="back-arrow" id="back-btn" title="返回">
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
            <h2>難度選擇</h2>
            <button class="difficulty-btn easy" onclick="selectDifficulty('easy')">
                簡單 目標：200分
            </button>
            <button class="difficulty-btn normal" onclick="selectDifficulty('normal')">
                普通 目標：450分
            </button>
            <button class="difficulty-btn hard" onclick="selectDifficulty('hard')">
                困難 目標：600分
            </button>
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
            <div class="help-content" style="margin-top:1.5rem;">
                <div style="display:flex;align-items:center;margin-bottom:0.5rem;">
                    <span style="color:#3b82f6;font-size:1.2rem;margin-right:0.5rem;">◆</span>
                    <span style="font-weight:bold;font-size:1.1rem;">目標</span>
                </div>
                <div style="margin-left:2.2rem;margin-bottom:1.2rem;">
                    在時間內用籃子接住從上方掉下來的金蛋，避免掉落地面，爭取高分！
                </div>
                <div style="display:flex;align-items:center;margin-bottom:0.5rem;">
                    <span style="color:#3b82f6;font-size:1.2rem;margin-right:0.5rem;">◆</span>
                    <span style="font-weight:bold;font-size:1.1rem;">玩法</span>
                </div>
                <ul style="margin-left:2.2rem;">
                    <li>左右拖曳籃子接蛋</li>
                    <li>接到金蛋+10分，白蛋+3分，炸彈-20分</li>
                    <li>時間內達到目標分數就過關！</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- 金蛋遊戲結束視窗 -->
    <div id="egg-game-over-modal" class="modal hidden">
        <div class="modal-content">
            <h2 id="egg-game-over-title">遊戲結束</h2>
            <div class="result-details">
                <p id="egg-result-message"></p>
            </div>
            <div class="result-buttons">
                <button onclick="eggReplayGame()">再玩一次</button>
                <button onclick="eggReturnToMain()">返回主選單</button>
            </div>
        </div>
    </div>

    <!-- 音頻元素 -->
    <audio id="bgm" loop>
        <source src="music/m.mp3" type="audio/mpeg">
    </audio>
    <audio id="catchSound">
        <source src="music/m.mp3" type="audio/mpeg">
    </audio>
    <audio id="bombSound">
        <source src="music/m.mp3" type="audio/mpeg">
    </audio>
    <audio id="gameOverSound">
        <source src="music/m.mp3" type="audio/mpeg">
    </audio>

    <script>
        // 如果 localStorage 沒有 member_id，就自動設一個（這裡用 8，請改成你自己的會員ID）
        if (!localStorage.getItem('member_id')) {
            localStorage.setItem('member_id', 8);
        }
    </script>
    <script src="js/Catch-Egg.js"></script>
    <script src="js/auto-save-time.js"></script>
</body>
</html> 
