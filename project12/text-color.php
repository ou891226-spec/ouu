<?php
// 防止快取
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

session_start();
require_once "db_connect.php";
require_once 'game_entry_tracker.php';

// 從資料庫讀取顏色
$colors_query = "SELECT * FROM text_color_colors";
$colors_stmt = $pdo->query($colors_query);
$all_colors = [];
while ($row = $colors_stmt->fetch(PDO::FETCH_ASSOC)) {
    $all_colors[] = [
        'name' => $row['color_name'],
        'chinese' => $row['color_name_chinese'],
        'code' => $row['color_code']
    ];
}

// 從資料庫讀取難度設定
$difficulty_settings = [];
$stmt = $pdo->prepare("SELECT * FROM difficulty_settings WHERE game_id = 1 ORDER BY difficulty");
$stmt->execute();
$settings = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($settings as $setting) {
    $difficulty_settings[$setting['difficulty']] = [
        'time_limit' => $setting['time_limit'],
        'points_per_correct' => $setting['points_per_correct'],
        'pass_score' => $setting['pass_score'],
        'pass_bounce' => $setting['pass_bounce']
    ];
}
// 2. 看字選色專屬難度設定（顏色數量、每題作答時間）
// 由於已統一使用 difficulty_settings 表，這些設定直接在程式中定義
$difficulty_settings['easy']['color_count'] = 3;
$difficulty_settings['easy']['question_time'] = 10;
$difficulty_settings['normal']['color_count'] = 4;
$difficulty_settings['normal']['question_time'] = 8;
$difficulty_settings['hard']['color_count'] = 5;
$difficulty_settings['hard']['question_time'] = 6;

if (!isset($_SESSION['score'])) {
    $_SESSION['score'] = 0;
}

// 確保每次 POST 都正確取得難度
$difficulty = isset($_POST['difficulty']) ? $_POST['difficulty'] : (isset($_GET['difficulty']) ? $_GET['difficulty'] : 'normal');

// 移除舊的最高分數讀取系統，統一使用API系統
$high_score = 0;

// 移除舊的updateHighScore函數，統一使用API系統

// 記錄遊戲結果的函數
function recordGameResult($score, $playTime, $difficulty, $isManualExit = false) {
    global $pdo;
    if (isset($_SESSION['account'])) {
        $account = $_SESSION['account'];
        // 獲取會員ID
        $member_query = "SELECT member_id FROM member WHERE account = ?";
        $member_stmt = $pdo->prepare($member_query);
        if (!$member_stmt) {
            error_log("Failed to prepare member query: " . $pdo->errorInfo()[2]);
            return false;
        }
        $member_stmt->execute([$account]);
        $member_result = $member_stmt->fetch(PDO::FETCH_ASSOC);
        error_log("查詢 member 結果: account=$account, result=" . ($member_result ? 'found' : 'not found'));
        if ($member_result) {
            $member_id = $member_result['member_id'];
            error_log("找到 member_id: $member_id");
        } else {
            error_log("❌ 找不到 account=$account 的 member 記錄");
            return false;
        }
        
        // 獲取遊戲ID - 直接使用已知的遊戲ID和類型
        $game_id = 1; // 自選色遊戲的ID
        $game_type = '看字選色遊戲'; // 自選色遊戲的類型

            // 檢查是否過關
            $settings_query = "SELECT pass_score, pass_bounce FROM difficulty_settings WHERE game_id = ? AND difficulty = ?";
            $settings_stmt = $pdo->prepare($settings_query);
            if (!$settings_stmt) {
                error_log("Failed to prepare settings query: " . $pdo->errorInfo()[2]);
                return false;
            }
            $settings_stmt->execute([$game_id, $difficulty]);
            error_log("查詢 difficulty_settings: game_id=$game_id, difficulty=$difficulty");
            $settings_result = $settings_stmt->fetch(PDO::FETCH_ASSOC);
            if ($settings_result) {
                $pass_score = $settings_result['pass_score'];
                $pass_bounce = $settings_result['pass_bounce'];
                error_log("找到設定: pass_score=$pass_score, pass_bounce=$pass_bounce");

                // 統一系統會自動處理分數更新、成就和任務檢查
                
                // 使用統一遊戲結果處理系統
                $record_score = ($score >= $pass_score) ? $pass_bounce : 0;
                $is_passed = ($score >= $pass_score);
                error_log("計算結果: score=$score, record_score=$record_score, is_passed=" . ($is_passed ? 'true' : 'false'));
            } else {
                error_log("找不到 game_id=$game_id, difficulty=$difficulty 的設定");
                return false;
            }
            
            $gameData = [
                'member_id' => $member_id,
                'game_type' => $game_type,
                'difficulty' => $difficulty,
                'score' => $record_score,
                'play_time' => $playTime,
                'is_manual_exit' => $isManualExit,
                'is_passed' => $is_passed,
                'game_id' => $game_id
            ];
            
            try {
                // 直接調用處理函數，避免curl問題
                require_once 'game_entry_tracker.php';
                
                // 檢查資料庫連接
                global $pdo;
                if (!$pdo) {
                    error_log("資料庫連接失敗");
                    return false;
                }
                
                // 直接調用processGameResult函數
                $result = processGameResult($gameData);
                
                if (!$result || !$result['success']) {
                    error_log("看字選色遊戲API處理失敗: " . ($result['message'] ?? '未知錯誤'));
                } else {
                    error_log("看字選色遊戲結果儲存成功: record_id=" . ($result['record_id'] ?? 'unknown'));
                }
            } catch (Exception $e) {
                error_log("看字選色遊戲結果處理失敗: " . $e->getMessage());
            }
            return true;
    }
    return false;
}

// 重置分數的函數
function resetScore() {
    $_SESSION['score'] = 0;
    return true;
}

// 處理 AJAX 請求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'record_game' && isset($_POST['score']) && isset($_POST['play_time'])) {
            $score = intval($_POST['score']);
            $playTime = intval($_POST['play_time']);
            $difficulty = $_POST['difficulty'] ?? 'easy';
            $isManualExit = isset($_POST['is_manual_exit']) && $_POST['is_manual_exit'] === '1';
            error_log("收到記錄請求: score=$score, playTime=$playTime, difficulty=$difficulty, isManualExit=" . ($isManualExit ? 'true' : 'false'));
            error_log("會員ID: " . ($_SESSION['member_id'] ?? 'null'));
            if (recordGameResult($score, $playTime, $difficulty, $isManualExit)) {
                echo "Game recorded successfully";
            } else {
                echo "Failed to record game";
            }
        } elseif ($_POST['action'] === 'reset_score') {
            if (resetScore()) {
                echo "Score reset successfully";
            } else {
                echo "Failed to reset score";
            }
        // 移除舊的get_high_score處理，統一使用API系統
        }
    }
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>看字選色遊戲</title>
    <link rel="stylesheet" href="css/text-color-1.css?v=<?php echo time(); ?>">
    <script src="js/unified-game-tracker.js"></script>
    <script>
        // 初始化遊戲追蹤器
        document.addEventListener("DOMContentLoaded", function() {
            gameTracker.init("反應力", 1);
        });
    </script>
</head>
<body>
    <input type="hidden" id="member-id" value="<?php echo $_SESSION['member_id'] ?? 1; ?>">
    <!-- 難度選擇視窗 -->
    <div id="difficultyModal" class="modal">
        <div class="modal-content">
            <button class="back-button" onclick="handleBackButton()">
                <span class="back-arrow">←</span>
                <div class="back-label">返回</div>
            </button>
            <h2>選擇難度</h2>
            <button class="help-button" id="helpBtn">?
                <div class="help-label">說明</div>
            </button>
            <div class="difficulty-buttons">
                <button class="difficulty-btn easy" onclick="selectDifficulty('easy')">
                    <span class="difficulty-name">簡單模式</span>
                    <span class="difficulty-desc">(<?php echo isset($difficulty_settings['easy']['time_limit']) ? $difficulty_settings['easy']['time_limit'] : 60; ?>秒，目標：<?php echo isset($difficulty_settings['easy']['pass_score']) ? $difficulty_settings['easy']['pass_score'] : 15; ?>分)</span>
                </button>
                <button class="difficulty-btn normal" onclick="selectDifficulty('normal')">
                    <span class="difficulty-name">普通模式</span>
                    <span class="difficulty-desc">(<?php echo isset($difficulty_settings['normal']['time_limit']) ? $difficulty_settings['normal']['time_limit'] : 50; ?>秒，目標：<?php echo isset($difficulty_settings['normal']['pass_score']) ? $difficulty_settings['normal']['pass_score'] : 20; ?>分)</span>
                </button>
                <button class="difficulty-btn hard" onclick="selectDifficulty('hard')">
                    <span class="difficulty-name">困難模式</span>
                    <span class="difficulty-desc">(<?php echo isset($difficulty_settings['hard']['time_limit']) ? $difficulty_settings['hard']['time_limit'] : 40; ?>秒，目標：<?php echo isset($difficulty_settings['hard']['pass_score']) ? $difficulty_settings['hard']['pass_score'] : 30; ?>分)</span>
                </button>
            </div>
        </div>
    </div>
    <!-- 遊戲說明視窗 -->
    <div id="help-modal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeHelpModal()">×</span>
            <h2 style="text-align:center;">
                <span style="font-size:2rem;vertical-align:middle;">🎮</span>
                <span style="font-weight:bold;vertical-align:middle;">遊戲說明</span>
            </h2>
            <div class="help-content" style="margin-top:2.5rem;padding:0 2rem;">
                <!-- 影片播放區域 -->
                <div id="textcolor-video-container" style="text-align:center;margin-bottom:2.5rem;">
                    <video id="textcolor-current-video" width="100%" height="auto" controls style="max-width:700px;width:80%;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,0.15);">
                        <source src="gd/textcolor1.mp4" type="video/mp4">
                        您的瀏覽器不支援影片播放。
                    </video>
                </div>
                
                <!-- 說明文字和按鈕區域 (並排顯示) -->
                <div style="display:flex;justify-content:center;align-items:center;margin:0 1rem;margin-bottom:2rem; gap: 20px;">
                    <!-- 上一步按鈕 -->
                    <div id="textcolor-prev-step-btn" style="display:none;">
                        <button id="textcolor-prev-step-button" onclick="goToTextColorPrevStep()" class="game-step-button prev-step" style="padding:14px 28px;font-size:20px;">
                            上一步
                        </button>
                    </div>
                    
                    <!-- 說明文字 -->
                    <div id="textcolor-instruction-text" class="game-instruction-text" style="font-size:24px;flex:3;text-align:center;min-width:300px;">
                        根據文字的意思選擇正確的顏色
                    </div>
                    
                    <!-- 下一步按鈕 -->
                    <div id="textcolor-next-step-btn" style="margin-left:2rem;">
                        <button id="textcolor-next-step-button" class="game-step-button next-step" style="padding:14px 28px;font-size:20px;">
                            下一步
                        </button>
                    </div>
                </div>
                
                <!-- 進度指示器 -->
                <div style="text-align:center;margin-top:1.5rem;margin-bottom:1.5rem;">
                    <span id="textcolor-step-indicator" class="game-step-indicator" style="font-size:18px;">步驟 1/2</span>
                </div>
            </div>
        </div>
    </div>

    <!-- 音效元素 -->
    <audio id="correctSound" preload="auto">
        <source src="voice/correct.mp3" type="audio/mpeg">
    </audio>
    <audio id="incorrectSound" preload="auto">
        <source src="voice/incorrect.mp3" type="audio/mpeg">
    </audio>

    <div class="game-container">
        <h1>看字選色遊戲</h1>
        <div class="score-board">
            <div class="score-item">目前分數：<span id="score" style="color: #2ecc71; font-weight: bold;"><?php echo $_SESSION['score']; ?></span></div>
            <div class="score-item">過關分數：<span id="passScore" style="color: #2ecc71; font-weight: bold;"><?php echo isset($difficulty_settings[$difficulty]['pass_score']) ? $difficulty_settings[$difficulty]['pass_score'] : 0; ?></span></div>
            <div class="score-item">剩餘時間：<span id="time" style="color: #e74c3c; font-weight: bold;">0</span></div>
        </div>

        <h2 style="font-size: 1.2em; margin: 18px 0 10px 0;">請選擇這個顏色：<span id="targetColorText"></span></h2>
        <div id="buttonContainer"></div>
        <div id="distractionContainer"></div>
        <div style="margin-top: 24px;">
            <button id="pauseBtn" class="orange-btn">暫停遊戲</button>
            <button id="endBtn" class="red-btn">結束遊戲</button>
            <button id="resetBtn" class="blue-btn">重新開始</button>
        </div>
    </div>

    <!-- 新增結束彈窗 -->
    <div id="endGameModal" class="modal">
        <div class="modal-content" id="endGameContent"></div>
    </div>

    <script>
        let score = 0;  // 初始化為數字 0
        let highScore = <?php echo $high_score; ?>;
        let timeLeft = 60;
        let timer = null;
        let gameStarted = false;
        let colors = <?php echo json_encode($all_colors); ?>;
        let correctColor = '';
        let difficulty = '<?php echo $difficulty; ?>';
        let distractionInterval = null;
        let questionTimer = null;
        let questionTimeLeft = 5;
        let startTime = null;
        let endTime = null;

        // 從 PHP 傳入的難度設定
        const difficultySettings = <?php echo json_encode($difficulty_settings); ?>;

        console.log(difficultySettings);

        const scoreEl = document.getElementById('score');
        const passScoreEl = document.getElementById('passScore');
        const timeEl = document.getElementById('time');
        const startBtn = document.getElementById('startBtn');
        const endBtn = document.getElementById('endBtn');

        const distractionContainer = document.getElementById('distractionContainer');

        // 初始化分數顯示
        scoreEl.textContent = '0';
        if (passScoreEl) {
            passScoreEl.textContent = difficultySettings[difficulty].pass_score;
        }

        const colorToChinese = (color) => {
            const colorObj = colors.find(c => c.name === color);
            return colorObj ? colorObj.chinese : color;
        };

        // 播放音效的函數
        function playSound(soundType) {
            try {
                const audio = document.getElementById(soundType + 'Sound');
                if (audio) {
                    // 重置音效到開始位置
                    audio.currentTime = 0;
                    // 播放音效
                    audio.play().catch(error => {
                        console.log('音效播放失敗:', error);
                    });
                }
            } catch (error) {
                console.log('音效播放錯誤:', error);
            }
        }

        function shuffleColors(arr) {
            for (let i = arr.length - 1; i > 0; i--) {
                const j = Math.floor(Math.random() * (i + 1));
                [arr[i], arr[j]] = [arr[j], arr[i]];
            }
            return arr;
        }

        function createDistraction() {
            if (!gameStarted || difficulty !== 'normal') return;
            
            const distraction = document.createElement('div');
            distraction.className = 'distraction';
            const randomColor = colors[Math.floor(Math.random() * colors.length)];
            const randomText = colorToChinese(colors[Math.floor(Math.random() * colors.length)].name);
            
            distraction.textContent = randomText;
            distraction.style.color = randomColor.code;
            distraction.style.left = Math.random() * 80 + '%';
            distraction.style.top = Math.random() * 80 + '%';
            
            distractionContainer.appendChild(distraction);
            
            setTimeout(() => {
                distraction.remove();
            }, 3000);
        }

        // 動態調整按鈕大小
        function adjustButtonSize(colorCount) {
            const container = document.getElementById('buttonContainer');
            const containerWidth = container.offsetWidth;
            
            // 計算每行可以放幾個按鈕
            const buttonsPerRow = Math.min(Math.floor(containerWidth / 170), colorCount);
            // 170 = 按鈕寬度(150px) + 間距(20px)
            
            // 調整按鈕大小
            const buttonSize = Math.min(150, (containerWidth - (buttonsPerRow - 1) * 20) / buttonsPerRow);
            
            // 更新所有按鈕的大小
            const buttons = container.getElementsByClassName('color-btn');
            for (let btn of buttons) {
                btn.style.width = buttonSize + 'px';
                btn.style.height = buttonSize + 'px';
            }
        }

        // 根據難度獲取顏色數量
        function getColorCountByDifficulty(level) {
            return difficultySettings[level].color_count;
        }

        function generateQuestion() {
            const colorCount = getColorCountByDifficulty(difficulty);
            const selectedColors = shuffleColors([...colors]).slice(0, colorCount);
            correctColor = selectedColors[Math.floor(Math.random() * selectedColors.length)];
            let randomDisplayColor = selectedColors[Math.floor(Math.random() * selectedColors.length)];
            let displayText = colorToChinese(correctColor.name);
            
            if (difficulty === 'hard') {
                if (Math.random() < 0.5) {
                    const trapType = Math.floor(Math.random() * 4); // 減少到4種，移除透明度效果
                    
                    switch(trapType) {
                        case 0:
                            document.getElementById('targetColorText').innerHTML = 
                                `<span style="color:${randomDisplayColor.code}; font-weight:bold; animation:blink 0.3s infinite;">${displayText}</span>`;
                            break;
                        case 1:
                            document.getElementById('targetColorText').innerHTML = 
                                `<span style="color:${randomDisplayColor.code}; font-weight:bold; animation:gradient 0.5s infinite;">${displayText}</span>`;
                            break;
                        case 2:
                            document.getElementById('targetColorText').innerHTML = 
                                `<span style="color:${randomDisplayColor.code}; font-weight:bold; animation:shake 0.2s infinite;">${displayText}</span>`;
                            break;
                        case 3:
                            document.getElementById('targetColorText').innerHTML = 
                                `<span style="color:${randomDisplayColor.code}; font-weight:bold; animation:rotate 1s infinite;">${displayText}</span>`;
                            break;
                    }
                } else {
                    document.getElementById('targetColorText').innerHTML = 
                        `<span style="color:${randomDisplayColor.code}; font-weight:bold;">${displayText}</span>`;
                }
            } else {
                document.getElementById('targetColorText').innerHTML = 
                    `<span style="color:${randomDisplayColor.code}; font-weight:bold;">${displayText}</span>`;
            }

            let container = document.getElementById('buttonContainer');
            container.innerHTML = '';
            
            // 移除之前的難度模式樣式
            container.classList.remove('hard-mode', 'normal-mode');

            if (difficulty === 'hard') {
                // 困難模式：上面3個，下面2個
                container.classList.add('hard-mode');
                
                // 創建上面一行（3個按鈕）
                const topRow = document.createElement('div');
                topRow.className = 'top-row';
                
                // 創建下面一行（2個按鈕）
                const bottomRow = document.createElement('div');
                bottomRow.className = 'bottom-row';
                
                selectedColors.forEach((color, index) => {
                    let btn = document.createElement('button');
                    btn.className = 'color-btn';
                    
                    // 添加隨機效果
                    if (Math.random() < 0.4) {
                        const effectType = Math.floor(Math.random() * 3);
                        switch(effectType) {
                            case 0:
                                btn.classList.add('pulse');
                                break;
                            case 1:
                                btn.classList.add('shake');
                                break;
                            case 2:
                                btn.classList.add('rotate');
                                break;
                        }
                    }
                    
                    btn.style.backgroundColor = color.code;
                    btn.onclick = (e) => checkAnswer(color.name, e.currentTarget);
                    
                    // 前3個放在上面，後2個放在下面
                    if (index < 3) {
                        topRow.appendChild(btn);
                    } else {
                        bottomRow.appendChild(btn);
                    }
                });
                
                container.appendChild(topRow);
                container.appendChild(bottomRow);
            } else if (difficulty === 'normal') {
                // 普通模式：上面2個，下面2個
                container.classList.add('normal-mode');
                
                // 創建上面一行（2個按鈕）
                const topRow = document.createElement('div');
                topRow.className = 'top-row';
                
                // 創建下面一行（2個按鈕）
                const bottomRow = document.createElement('div');
                bottomRow.className = 'bottom-row';
                
                selectedColors.forEach((color, index) => {
                    let btn = document.createElement('button');
                    btn.className = 'color-btn';
                    btn.style.backgroundColor = color.code;
                    btn.onclick = (e) => checkAnswer(color.name, e.currentTarget);
                    
                    // 前2個放在上面，後2個放在下面
                    if (index < 2) {
                        topRow.appendChild(btn);
                    } else {
                        bottomRow.appendChild(btn);
                    }
                });
                
                container.appendChild(topRow);
                container.appendChild(bottomRow);
            } else {
                // 簡單模式：保持原來的水平排列
                selectedColors.forEach(color => {
                    let btn = document.createElement('button');
                    btn.className = 'color-btn';
                    btn.style.backgroundColor = color.code;
                    btn.onclick = (e) => checkAnswer(color.name, e.currentTarget);
                    container.appendChild(btn);
                });
            }

            // 調整按鈕大小
            adjustButtonSize(selectedColors.length);

            // 根據難度設定答題時間
            const questionTime = difficultySettings[difficulty].question_time;
            if (questionTime > 0) {
                questionTimeLeft = questionTime;
                if (questionTimer) {
                    clearInterval(questionTimer);
                }
                questionTimer = setInterval(() => {
                    questionTimeLeft--;
                    if (questionTimeLeft <= 0) {
                        clearInterval(questionTimer);
                        generateQuestion();
                    }
                }, 1000);
            }
        }

        function checkAnswer(selectedColor, clickedBtn) {
            if (!gameStarted) return;
            if (gamePaused) return; // 暫停期間禁止作答

            if (difficulty !== 'easy' && questionTimer) {
                clearInterval(questionTimer);
            }

            if (selectedColor === correctColor.name) {
                // 答對了，播放正確音效
                playSound('correct');
                score += parseInt(difficultySettings[difficulty].points_per_correct);
                scoreEl.textContent = score.toString();
                // 達成過關分數即結束遊戲並顯示過關
                const passScoreNow = parseInt(difficultySettings[difficulty].pass_score);
                if (!isNaN(passScoreNow) && score >= passScoreNow) {
                    endGame();
                    return;
                }
                if (score > highScore) {
                    highScore = score;
                    // 移除舊的最高分數更新，統一使用API系統
                }
            } else {
                // 答錯了，播放錯誤音效並顯示整個畫面震動效果
                playSound('incorrect');
                showScreenWrongEffect();
                // 延遲切換題目，讓動畫能播放
                setTimeout(() => {
                    generateQuestion();
                }, 700);
                return;
            }
            generateQuestion();
        }

        // 移除單一按鈕答錯效果（改用整個畫面效果）

        // 整個畫面的答錯震動外框
        function showScreenWrongEffect() {
            const container = document.querySelector('.game-container');
            if (!container) return;
            container.classList.add('screen-wrong');
            setTimeout(() => {
                container.classList.remove('screen-wrong');
            }, 850);
        }

        function setDifficulty(level) {
            difficulty = level || 'normal'; // 預設為普通難度
            distractionContainer.innerHTML = '';
            timeLeft = difficultySettings[level].time_limit;
            
            // 顯示對應難度的過關分數
            if (passScoreEl) {
                passScoreEl.textContent = difficultySettings[level].pass_score;
            }
        }

        function startGame() {
            if (gameStarted) return;

            console.log('Starting game with difficulty: ' + difficulty); // 除錯用
            gameStarted = true;
            score = 0;
            scoreEl.textContent = '0';
            timeLeft = difficultySettings[difficulty].time_limit;
            timeEl.textContent = timeLeft.toString();
            startTime = new Date();
            
            // 顯示目前難度的過關分數
            if (passScoreEl) {
                passScoreEl.textContent = difficultySettings[difficulty].pass_score;
            }

            generateQuestion();

            timer = setInterval(() => {
                timeLeft--;
                timeEl.textContent = timeLeft.toString();
                if (timeLeft <= 0) {
                    endGame();
                }
            }, 1000);

            if (difficulty === 'normal') {
                distractionInterval = setInterval(createDistraction, 2000);
            }
        }

        let gamePaused = false; // 新增變數來追蹤遊戲是否暫停

function togglePauseGame() {
    const pauseBtn = document.getElementById('pauseBtn');
    if (gamePaused) {
        // 繼續遊戲
        gamePaused = false;
        pauseBtn.textContent = '暫停遊戲';
        pauseBtn.style.backgroundColor = '#ffa500 !important'; // 變回橘色
        pauseBtn.style.setProperty('background-color', '#ffa500', 'important');

        // 恢復計時器
        timer = setInterval(() => {
            timeLeft--;
            timeEl.textContent = timeLeft.toString();
            if (timeLeft <= 0) {
                endGame();
            }
        }, 1000);

        // 恢復干擾項目
        if (difficulty === 'normal') {
            distractionInterval = setInterval(createDistraction, 2000);
        }

        // 恢復問題計時器
        if (questionTimeLeft > 0) {
            questionTimer = setInterval(() => {
                questionTimeLeft--;
                if (questionTimeLeft <= 0) {
                    clearInterval(questionTimer);
                    generateQuestion();
                }
            }, 1000);
        }
        // 解除按鈕禁用樣式
        const container = document.querySelector('.game-container');
        if (container) container.classList.remove('paused');
    } else {
        // 暫停遊戲
        gamePaused = true;
        pauseBtn.textContent = '繼續遊戲';
        pauseBtn.style.setProperty('background-color', '#4CAF50', 'important');

        // 暫停計時器
        clearInterval(timer);
        clearInterval(distractionInterval);
        clearInterval(questionTimer);

        // 禁用按鈕點擊
        const container = document.querySelector('.game-container');
        if (container) container.classList.add('paused');
    }
}

// 綁定暫停按鈕的點擊事件
document.getElementById('pauseBtn').addEventListener('click', togglePauseGame);


        function endGame(isManualExit = false) {
            gameStarted = false;
            clearInterval(timer);
            if (distractionInterval) {
                clearInterval(distractionInterval);
                distractionInterval = null;
            }
            if (questionTimer) {
                clearInterval(questionTimer);
                questionTimer = null;
            }
            distractionContainer.innerHTML = '';
            document.getElementById('buttonContainer').innerHTML = '';
            document.getElementById('targetColorText').textContent = '遊戲結束';
            timeEl.textContent = '0';
            
            endTime = new Date();
            const playTime = Math.floor((endTime - startTime) / 1000); // 計算遊玩時間（秒）
            
            if (score > highScore) {
                highScore = score;
                // 移除舊的最高分數更新，統一使用API系統
            }
            
            console.log('遊戲結束，分數:', score, '遊戲時間:', playTime, '難度:', difficulty);
            
            // 保存遊戲記錄到資料庫
            const memberId = document.getElementById('member-id')?.value || 1;
            const passScore = parseInt(difficultySettings[difficulty].pass_score);
            const isPassed = score >= passScore;
            const recordScore = isPassed ? parseInt(difficultySettings[difficulty].pass_bounce) : 0;
            
            console.log('準備保存遊戲記錄:', {
                memberId: memberId,
                score: recordScore,
                playTime: playTime,
                difficulty: difficulty,
                isPassed: isPassed
            });
            
            // 調用統一的遊戲結果 API
            fetch('api/game_result.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    member_id: memberId,
                    game_type: '反應力',
                    game_id: 1,
                    difficulty: difficulty,
                    score: recordScore,
                    play_time: playTime,
                    is_manual_exit: isManualExit,
                    is_passed: isPassed
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('遊戲記錄保存成功:', data);
                } else {
                    console.error('遊戲記錄保存失敗:', data.message);
                }
            })
            .catch(error => {
                console.error('保存遊戲記錄時發生錯誤:', error);
            });
            
            // 注意：不需要在遊戲結束時清除成就快取，這會導致成就資料丟失
            // 立即刷新分數顯示
            if (typeof fetchUserScore === 'function') {
                fetchUserScore();
            }
            
            // 取得過關分數（已在上面定義過）
            const passBonus = parseInt(difficultySettings[difficulty].pass_bounce);

            // 檢查並更新任務狀態
            if (parseInt(score) >= passScore && difficulty === 'normal') {
                fetch("update_task_status.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json"
                    },
                    body: JSON.stringify({
                        task_type: "achievement",
                        difficulty: difficulty,
                        game_type: "看字選色遊戲"
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        console.log('任務狀態已更新');
                    } else {
                        console.error('更新任務狀態失敗:', data.message);
                    }
                })
                .catch(error => {
                    console.error('更新任務狀態時發生錯誤:', error);
                });
            }

            // 彈窗內容
            let modalHtml = '';
            if (parseInt(score) >= passScore) {
                // 過關
                modalHtml = `
                    <h2>🎉恭喜破關</h2>
                    <p>難度：${difficulty === 'easy' ? '簡單' : difficulty === 'normal' ? '普通' : '困難'}</p>
                    <p>獲得分數：${score}</p>
                    <p>遊戲時間：${playTime} 秒</p>
                    <p>過關分數：+${passBonus}</p>
                   
                    <button class="red-btn" onclick="location.reload()">再玩一次</button>
                    <button class="blue-btn" onclick="returnToMain()">返回主頁</button>
                `;
            } else {
                // 失敗
                modalHtml = `
                    <h2>⏰遊戲結束</h2>
                    <p>難度：${difficulty === 'easy' ? '簡單' : difficulty === 'normal' ? '普通' : '困難'}</p>
                    <p>目標分數：${passScore}</p>
                    <p>獲得分數：+${score}</p>
                    <p>未在時間內達成分數！</p>
                    <button class="red-btn" onclick="location.reload()">再玩一次</button>
                    <button class="blue-btn" onclick="returnToMain()">返回主頁</button>
                `;
            }
            document.getElementById('endGameContent').innerHTML = modalHtml;
            document.getElementById('endGameModal').style.display = 'block';

            // 最後再歸零分數
            score = 0;
            scoreEl.textContent = '0';
        }

        endBtn.addEventListener('click', () => endGame(true)); // 手動退出

        // 預設選擇普通難度
        setDifficulty('normal');

        // 修改重新開始按鈕的點擊事件
        document.getElementById('resetBtn').onclick = function() {
            fetch('text-color.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=reset_score'
            }).then(() => {
                // 重置所有遊戲狀態
                score = 0;
                scoreEl.textContent = '0';
                timeLeft = difficultySettings[difficulty].time_limit;
                timeEl.textContent = timeLeft.toString();
                gameStarted = false;
                clearInterval(timer);
                clearInterval(distractionInterval);
                clearInterval(questionTimer);
                distractionContainer.innerHTML = '';
                document.getElementById('buttonContainer').innerHTML = '';
                document.getElementById('targetColorText').textContent = '';
                
                // 顯示難度選擇視窗
                document.getElementById('difficultyModal').classList.add('show');
            });
        };

        // 監聽視窗大小變化
        window.addEventListener('resize', () => {
            if (gameStarted) {
                adjustButtonSize(getColorCountByDifficulty(difficulty));
            }
        });

        // 新增難度選擇相關的 JavaScript
        window.onload = function() {
            const modal = document.getElementById('difficultyModal');
            modal.classList.add('show');
            
            // 強制刷新CSS
            const link = document.querySelector('link[href*="text-color-1.css"]');
            if (link) {
                link.href = link.href.split('?')[0] + '?v=' + Date.now();
            }
            
            // 注意：不需要清除成就快取，這會導致成就資料丟失
            
            // 強制重新載入顏色資料
            console.log('載入的顏色資料：', colors);
            console.log('顏色數量：', colors.length);
        }

        function selectDifficulty(level) {
            console.log('Selecting difficulty: ' + level); // 除錯用
            setDifficulty(level);
            const modal = document.getElementById('difficultyModal');
            modal.classList.remove('show');
            startGame();
        }

        function handleBackButton() {
            if (document.referrer && document.referrer !== window.location.href) {
                history.back();
            } else {
                window.location.href = 'game-category.php';
            }
        }

        function returnToMain() {
            if (document.referrer && document.referrer !== window.location.href) {
                history.back();
            } else {
                window.location.href = 'game-category.php';
            }
        }

        function showHelp() {
            console.log('showHelp function called'); // 除錯用
            const helpModal = document.getElementById('help-modal');
            if (helpModal) {
                helpModal.classList.remove('hidden');
                helpModal.classList.add('show');
                console.log('Help modal should be visible now');
                // 初始化看字選色影片播放邏輯
                initTextColorVideoPlayback();
            } else {
                console.error('Help modal not found');
            }
        }
        
        // 初始化看字選色視頻播放邏輯
        function initTextColorVideoPlayback() {
            const video = document.getElementById('textcolor-current-video');
            const instructionText = document.getElementById('textcolor-instruction-text');
            const stepIndicator = document.getElementById('textcolor-step-indicator');
            const nextStepBtn = document.getElementById('textcolor-next-step-btn');
            const prevStepBtn = document.getElementById('textcolor-prev-step-btn');
            
            if (!video || !instructionText || !stepIndicator || !nextStepBtn || !prevStepBtn) {
                console.error('找不到看字選色遊戲說明元素');
                return;
            }
            
            // 設置第一個影片
            video.src = 'gd/textcolor1.mp4';
            instructionText.textContent = '選擇難度後即可進入遊戲畫面';
            stepIndicator.textContent = '步驟 1/2';
            
            // 設置當前影片標記
            video.setAttribute('data-current-video', 'textcolor1');
            
            // 顯示下一步按鈕，隱藏上一步按鈕
            nextStepBtn.style.display = 'block';
            prevStepBtn.style.display = 'none';
            
            // 強制加載影片
            video.load();
            
            // 添加下一步按鈕點擊事件
            const nextStepButton = document.getElementById('textcolor-next-step-button');
            if (nextStepButton) {
                nextStepButton.onclick = goToTextColorNextStep;
                console.log('看字選色下一步按鈕事件已綁定');
            }
        }
        
        // 前往看字選色下一步
        function goToTextColorNextStep() {
            const video = document.getElementById('textcolor-current-video');
            const instructionText = document.getElementById('textcolor-instruction-text');
            const stepIndicator = document.getElementById('textcolor-step-indicator');
            const nextStepBtn = document.getElementById('textcolor-next-step-btn');
            const prevStepBtn = document.getElementById('textcolor-prev-step-btn');
            
            // 切換到第二個視頻
            video.src = 'gd/textcolor2.mp4';
            video.setAttribute('data-current-video', 'textcolor2');
            instructionText.innerHTML = '注意：我們看的是字的「意思」<br>不是字的顏色，在時間內選擇正確的顏色獲得分數！';
            stepIndicator.textContent = '步驟 2/2';
            
            // 隱藏下一步按鈕，顯示上一步按鈕
            nextStepBtn.style.display = 'none';
            prevStepBtn.style.display = 'block';
            
            // 加載並播放視頻
            video.load();
            video.play();
        }
        
        // 回到看字選色上一步
        function goToTextColorPrevStep() {
            const video = document.getElementById('textcolor-current-video');
            const instructionText = document.getElementById('textcolor-instruction-text');
            const stepIndicator = document.getElementById('textcolor-step-indicator');
            const nextStepBtn = document.getElementById('textcolor-next-step-btn');
            const prevStepBtn = document.getElementById('textcolor-prev-step-btn');
            
            // 切換到第一個視頻
            video.src = 'gd/textcolor1.mp4';
            video.setAttribute('data-current-video', 'textcolor1');
            instructionText.textContent = '選擇難度後即可進入遊戲畫面';
            stepIndicator.textContent = '步驟 1/2';
            
            // 顯示下一步按鈕，隱藏上一步按鈕
            nextStepBtn.style.display = 'block';
            prevStepBtn.style.display = 'none';
            
            // 加載並播放視頻
            video.load();
            video.play();
        }
        
        // 設為全局可訪問
        window.goToTextColorNextStep = goToTextColorNextStep;
        window.goToTextColorPrevStep = goToTextColorPrevStep;

        // 關閉說明彈窗函數
        function closeHelpModal() {
            // 停止視頻播放
            const video = document.getElementById('textcolor-current-video');
            if (video) {
                video.pause();
                video.currentTime = 0; // 重置到開始位置
            }
            
            const helpModal = document.getElementById('help-modal');
            if (helpModal) {
                helpModal.classList.remove('show');
                helpModal.classList.add('hidden');
            }
        }

        // 點擊視窗外關閉彈跳視窗
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target == modal) {
                    modal.classList.remove('show');
                }
            });
        };

        // 確保說明按鈕事件綁定
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOMContentLoaded event fired'); // 除錯用
            
            const helpBtn = document.getElementById('helpBtn');
            console.log('Help button found:', helpBtn); // 除錯用
            
            if(helpBtn){
                // 移除所有舊的事件監聽器
                helpBtn.replaceWith(helpBtn.cloneNode(true));
                const newHelpBtn = document.getElementById('helpBtn');
                
                newHelpBtn.addEventListener('click', function(e){
                    e.preventDefault();
                    e.stopPropagation();
                    console.log('Help button clicked'); // 除錯用
                    showHelp();
                });
            }
            
            // 關閉說明彈窗
            const closeBtns = document.querySelectorAll('.close-btn');
            closeBtns.forEach(function(btn){
                btn.addEventListener('click', function(e){
                    e.preventDefault();
                    e.stopPropagation();
                    closeHelpModal();
                });
            });
        });
    </script>
    <script src="js/game-common.js"></script>
    <script src="js/achievements.js"></script>
</body>
</html>
