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
            
            // 從統一的 difficulty_settings 表讀取過關分數
            $stmt = $pdo->prepare("SELECT pass_score FROM difficulty_settings WHERE game_id = 2 AND difficulty = ?");
            $stmt->execute([$difficulty]);
            $setting = $stmt->fetch();
            $pass_score = $setting ? $setting['pass_score'] : 200; // 預設值
            
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

                // 更新會員總分數和反應力分數
                $update_stmt = $pdo->prepare("UPDATE member SET total_score = total_score + :score, reaction_score = reaction_score + :score WHERE member_id = :member_id");
                $update_stmt->execute([
                    'score' => $score,
                    'member_id' => $member_id
                ]);

                // 檢查並完成所有相關任務
                require_once 'check_and_grant_achievements.php';
                $completed_tasks = checkAndCompleteAllTasks($member_id, '反應力', $playTime);
                
                // 確保沒有其他輸出
                if (ob_get_length()) ob_clean();
                
                $pdo->commit();

                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'member_id' => (string)$member_id,
                    'difficulty' => $difficulty,
                    'status' => $status,
                    'score' => (int)$score,
                    'play_time' => (int)$playTime,
                    'task_completed' => !empty($completed_tasks)
                ]);
            } catch (Exception $e) {
                $pdo->rollBack();
                header('Content-Type: application/json');
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
    <link rel="stylesheet" href="css/Catch-Egg.css" type="text/css">
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
            <?php
            // 從統一的 difficulty_settings 表讀取接金蛋遊戲的難度設定
            $stmt = $pdo->query("SELECT difficulty, pass_score FROM difficulty_settings WHERE game_id = 2 ORDER BY difficulty");
            $difficulties = $stmt->fetchAll();
            
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
            <div class="help-content" style="margin-top:2.5rem;padding:0 2rem;">
                <!-- 影片播放區域 -->
                <div id="egg-video-container" style="text-align:center;margin-bottom:2.5rem;">
                    <video id="egg-current-video" width="100%" height="auto" controls style="max-width:700px;width:80%;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,0.15);">
                        <source src="gd/egg1.mp4" type="video/mp4">
                        您的瀏覽器不支援視頻播放。
                    </video>
                </div>
                
                <!-- 說明文字和按鈕區域 (並排顯示) -->
                <div style="display:flex;justify-content:center;align-items:center;margin:0 1rem;margin-bottom:2rem; gap: 20px;">
                    <!-- 上一步按鈕 -->
                    <div id="egg-prev-step-btn" style="display:none;">
                        <button id="egg-prev-step-button" onclick="goToEggPrevStep()" class="game-step-button prev-step" style="padding:14px 28px;font-size:20px;">
                            上一步
                        </button>
                    </div>
                    
                    <!-- 說明文字 -->
                    <div id="egg-instruction-text" class="game-instruction-text" style="font-size:24px;flex:1;text-align:center;">
                        動動手指左右拖曳籃子接蛋
                    </div>
                    
                    <!-- 下一步按鈕 -->
                    <div id="egg-next-step-btn" style="margin-left:2rem;">
                        <button id="egg-next-step-button" class="game-step-button next-step" style="padding:14px 28px;font-size:20px;">
                            下一步
                        </button>
                    </div>
                </div>
                
                <!-- 進度指示器 -->
                <div style="text-align:center;margin-top:1.5rem;margin-bottom:1.5rem;">
                    <span id="egg-step-indicator" class="game-step-indicator" style="font-size:18px;">步驟 1/2</span>
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
                    <div class="gameover-row">獲得分數：<span id="egg-gameover-earned-score">20</span></div>
                    <div class="gameover-row">遊戲時間：<span id="egg-gameover-time">60秒</span></div>
                    <div class="gameover-row">過關分數：<span id="egg-gameover-bonus">+20</span></div>
                </div>
            </div>
            <div class="result-buttons">
                <button onclick="eggReplayGame()">再玩一次</button>
                <button onclick="eggReturnToMain()">返回主選單</button>
            </div>
        </div>
    </div>

    <!-- 音頻元素 -->
    <audio id="catchSound">
        <source src="music/gett.mp4" type="audio/mp4">
    </audio>
    <audio id="bombSound">
        <source src="music/gett.mp4" type="audio/mp4">
    </audio>
    <audio id="gameOverSound">
        <source src="music/gett.mp4" type="audio/mp4">
    </audio> 

    <script>
        // 如果 localStorage 沒有 member_id，就自動設一個（這裡用 8，請改成你自己的會員ID）
        if (!localStorage.getItem('member_id')) {
            localStorage.setItem('member_id', 8);
        }
        
    </script>
    <script src="js/Catch-Egg.js"></script>
    <!-- <script src="js/auto-save-time-fixed.js"></script> -->
</body>
</html> 