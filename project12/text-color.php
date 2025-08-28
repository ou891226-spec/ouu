<?php
// 防止快取
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

session_start();
require_once "db_connect.php";

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
// 1. 只撈 game_id = 1 的難度設定
$settings_query = "SELECT * FROM difficulty_settings WHERE game_id = 1";
$settings_stmt = $pdo->query($settings_query);
while ($row = $settings_stmt->fetch(PDO::FETCH_ASSOC)) {
    $difficulty_settings[$row['difficulty']] = [
        'time_limit' => $row['time_limit'],
        'points_per_correct' => $row['points_per_correct'],
        'pass_score' => $row['pass_score'],
        'pass_bounce' => $row['pass_bounce']
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

// 在頁面載入時，根據當前難度讀取最高分數
$high_score = 0;
if (isset($_SESSION['account'])) {
    try {
        // 1. 先獲取會員ID
        $member_query = "SELECT member_id FROM member WHERE account = ?";
        $member_stmt = $pdo->prepare($member_query);
        if (!$member_stmt) {
            error_log("Failed to prepare member query: " . $pdo->errorInfo()[2]);
            throw new Exception("Database error");
        }
        $member_stmt->execute([$_SESSION['account']]);
        $member_result = $member_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($member_result) {
            $member_id = $member_result['member_id'];
            
            // 2. 獲取遊戲ID
            $game_query = "SELECT game_id FROM games WHERE game_name = '看字選色遊戲'";
            $game_stmt = $pdo->prepare($game_query);
            if (!$game_stmt) {
                error_log("Failed to prepare game query: " . $pdo->errorInfo()[2]);
                throw new Exception("Database error");
            }
            $game_stmt->execute();
            $game_result = $game_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($game_result) {
                $game_id = $game_result['game_id'];
                
                // 3. 讀取對應難度的最高分數
                $score_query = "SELECT high_score 
                              FROM game_high_scores 
                              WHERE member_id = ? 
                              AND game_id = ? 
                              AND difficulty_level = ?";
                $score_stmt = $pdo->prepare($score_query);
                if (!$score_stmt) {
                    error_log("Failed to prepare score query: " . $pdo->errorInfo()[2]);
                    throw new Exception("Database error");
                }
                $score_stmt->execute([$member_id, $game_id, $difficulty]);
                $score_result = $score_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($score_result) {
                    $high_score = $score_result['high_score'];
                    $_SESSION['high_score_' . $difficulty] = $high_score;
                }
                $score_stmt->closeCursor();
            }
            $game_stmt->closeCursor();
        }
        $member_stmt->closeCursor();
        
    } catch (Exception $e) {
        error_log("Error reading high score: " . $e->getMessage());
        $high_score = 0;
    }
}

// 更新最高分數的函數
function updateHighScore($newScore) {
    global $pdo, $difficulty;
    if (!isset($_SESSION['account'])) {
        error_log("No account session found when updating high score");
        return false;
    }

    try {
        // 先獲取會員ID
        $member_query = "SELECT member_id FROM member WHERE account = ?";
        $member_stmt = $pdo->prepare($member_query);
        if (!$member_stmt) {
            error_log("Failed to prepare member query: " . $pdo->errorInfo()[2]);
            return false;
        }
        $member_stmt->execute([$_SESSION['account']]);
        $member_result = $member_stmt->fetch(PDO::FETCH_ASSOC);
        if (!$member_result) {
            $member_stmt->closeCursor();
            return false;
        }
        $member_id = $member_result['member_id'];
        $member_stmt->closeCursor();

        // 再獲取遊戲ID
        $game_query = "SELECT game_id FROM games WHERE game_name = '看字選色遊戲'";
        $game_stmt = $pdo->prepare($game_query);
        if (!$game_stmt) {
            error_log("Failed to prepare game query: " . $pdo->errorInfo()[2]);
            return false;
        }
        $game_stmt->execute();
        $game_result = $game_stmt->fetch(PDO::FETCH_ASSOC);
        if (!$game_result) {
            $game_stmt->closeCursor();
            return false;
        }
        $game_id = $game_result['game_id'];
        $game_stmt->closeCursor();

        // 檢查是否已有記錄
        $check_query = "SELECT high_score FROM game_high_scores WHERE member_id = ? AND game_id = ? AND difficulty_level = ?";
        $check_stmt = $pdo->prepare($check_query);
        $check_stmt->execute([$member_id, $game_id, $difficulty]);
        $check_result = $check_stmt->fetch(PDO::FETCH_ASSOC);
        $has_record = $check_stmt->rowCount() > 0;
        $check_stmt->closeCursor();

        if ($has_record) {
            // 更新現有記錄（只在新分數更高時更新）
            $update_query = "UPDATE game_high_scores SET high_score = ? WHERE member_id = ? AND game_id = ? AND difficulty_level = ? AND high_score < ?";
            $update_stmt = $pdo->prepare($update_query);
            $update_stmt->execute([$newScore, $member_id, $game_id, $difficulty, $newScore]);
            $update_stmt->closeCursor();
        } else {
            // 插入新記錄
            $insert_query = "INSERT INTO game_high_scores (member_id, game_id, difficulty_level, high_score) VALUES (?, ?, ?, ?)";
            $insert_stmt = $pdo->prepare($insert_query);
            $insert_stmt->execute([$member_id, $game_id, $difficulty, $newScore]);
            $insert_stmt->closeCursor();
        }
        return true;
    } catch (Exception $e) {
        error_log("Error updating high score: " . $e->getMessage());
        return false;
    }
}

// 記錄遊戲結果的函數
function recordGameResult($score, $playTime, $difficulty) {
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
        if ($member_result) {
            $member_id = $member_result['member_id'];
            // 獲取遊戲ID
            $game_query = "SELECT game_id, game_type FROM games WHERE game_name = '看字選色遊戲'";
            $game_stmt = $pdo->prepare($game_query);
            if (!$game_stmt) {
                error_log("Failed to prepare game query: " . $pdo->errorInfo()[2]);
                return false;
            }
            $game_stmt->execute();
            $game_result = $game_stmt->fetch(PDO::FETCH_ASSOC);
            if ($game_result) {
                $game_id = $game_result['game_id'];
                $game_type = $game_result['game_type'];

                // 檢查是否過關
                $settings_query = "SELECT pass_score, pass_bounce FROM difficulty_settings WHERE difficulty = ?";
                $settings_stmt = $pdo->prepare($settings_query);
                if (!$settings_stmt) {
                    error_log("Failed to prepare settings query: " . $pdo->errorInfo()[2]);
                    return false;
                }
                $settings_stmt->execute([$difficulty]);
                $settings_result = $settings_stmt->fetch(PDO::FETCH_ASSOC);
                if ($settings_result) {
                    $pass_score = $settings_result['pass_score'];
                    $pass_bounce = $settings_result['pass_bounce'];

                    // 如果分數達到過關標準，更新會員總分和反應分數
                    if ($score >= $pass_score) {
                        // 更新會員總分
                        $update_score_query = "UPDATE member SET total_score = total_score + $pass_bounce WHERE member_id = $member_id";
                        $pdo->query($update_score_query);
                        
                        // 更新會員反應分數
                        $update_reaction_query = "UPDATE member SET reaction_score = reaction_score + $pass_bounce WHERE member_id = $member_id";
                        $pdo->query($update_reaction_query);
                        
                        // 檢查並授予成就
                        require_once 'check_and_grant_achievements.php';
                        checkAndGrantAchievements($member_id, 'reaction_game', $pass_bounce, $playTime);
                        
                        // 檢查並完成所有相關任務
                        checkAndCompleteAllTasks($member_id, '看字選色遊戲');
                    }
                }

                // 根據是否過關決定記錄的分數
                $record_score = ($score >= $pass_score) ? $pass_bounce : 0;
                
                // 插入遊戲記錄
                $record_query = "INSERT INTO game_records 
                               (member_id, game_id, difficulty, score, play_date, play_time, game_type, is_single_player, opponent_id) 
                               VALUES (:member_id, :game_id, :difficulty, :score, NOW(), :play_time, :game_type, :is_single_player, :opponent_id)";
                $record_stmt = $pdo->prepare($record_query);
                $record_stmt->execute([
                    'member_id' => $member_id,
                    'game_id' => $game_id,
                    'difficulty' => $difficulty,
                    'score' => $record_score, // 根據是否過關決定分數
                    'play_time' => $playTime,
                    'game_type' => $game_type,
                    'is_single_player' => 1,
                    'opponent_id' => null
                ]);
                $record_stmt->closeCursor();
                return true;
            }
        }
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
        if ($_POST['action'] === 'update_high_score' && isset($_POST['high_score'])) {
            $newScore = intval($_POST['high_score']);
            if (updateHighScore($newScore)) {
                echo "High score updated successfully";
            } else {
                echo "No new high score";
            }
        } elseif ($_POST['action'] === 'record_game' && isset($_POST['score']) && isset($_POST['play_time'])) {
            $score = intval($_POST['score']);
            $playTime = intval($_POST['play_time']);
            if (recordGameResult($score, $playTime, $difficulty)) {
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
        } elseif ($_POST['action'] === 'get_high_score' && isset($_POST['difficulty'])) {
            $current_difficulty = $_POST['difficulty'];
            $high_score = 0;
            
            if (isset($_SESSION['account'])) {
                try {
                    // 1. 獲取會員ID
                    $member_query = "SELECT member_id FROM member WHERE account = ?";
                    $member_stmt = $pdo->prepare($member_query);
                    if (!$member_stmt) {
                        throw new Exception("Failed to prepare member query");
                    }
                    $member_stmt->execute([$_SESSION['account']]);
                    $member_result = $member_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($member_result) {
                        $member_id = $member_result['member_id'];
                        
                        // 2. 獲取遊戲ID
                        $game_query = "SELECT game_id FROM games WHERE game_name = '看字選色遊戲'";
                        $game_stmt = $pdo->prepare($game_query);
                        if (!$game_stmt) {
                            throw new Exception("Failed to prepare game query");
                        }
                        $game_stmt->execute();
                        $game_result = $game_stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($game_result) {
                            $game_id = $game_result['game_id'];
                            
                            // 3. 讀取對應難度的最高分數
                            $score_query = "SELECT high_score 
                                          FROM game_high_scores 
                                          WHERE member_id = ? 
                                          AND game_id = ? 
                                          AND difficulty_level = ?";
                            $score_stmt = $pdo->prepare($score_query);
                            if (!$score_stmt) {
                                throw new Exception("Failed to prepare score query");
                            }
                            $score_stmt->execute([$member_id, $game_id, $current_difficulty]);
                            $score_result = $score_stmt->fetch(PDO::FETCH_ASSOC);
                            
                            if ($score_result) {
                                $high_score = $score_result['high_score'];
                            }
                            $score_stmt->closeCursor();
                        }
                        $game_stmt->closeCursor();
                    }
                    $member_stmt->closeCursor();
                    
                } catch (Exception $e) {
                    error_log("Error reading high score: " . $e->getMessage());
                }
            }
            echo $high_score;
            exit;
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
</head>
<body>
    <input type="hidden" id="member-id" value="<?php echo $_SESSION['member_id'] ?? 1; ?>">
    <!-- 難度選擇視窗 -->
    <div id="difficultyModal" class="modal">
        <div class="modal-content">
            <button class="back-button" onclick="handleBackButton()">
                <span class="back-arrow">⬅</span>
                <div class="back-label">返回</div>
            </button>
            <h2>選擇難度</h2>
            <button class="help-button" id="helpBtn">?
                <div class="help-label">說明</div>
            </button>
            <div class="difficulty-buttons">
                <button class="difficulty-btn easy" onclick="selectDifficulty('easy')">
                    <span class="difficulty-name">簡單模式</span>
                    <span class="difficulty-desc">(60秒)</span>
                </button>
                <button class="difficulty-btn normal" onclick="selectDifficulty('normal')">
                    <span class="difficulty-name">普通模式</span>
                    <span class="difficulty-desc">(50秒)</span>
                </button>
                <button class="difficulty-btn hard" onclick="selectDifficulty('hard')">
                    <span class="difficulty-name">困難模式</span>
                    <span class="difficulty-desc">(40秒)</span>
                </button>
            </div>
        </div>
    </div>
    <!-- 遊戲說明視窗 -->
    <div id="help-modal" class="modal">
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
                    根據文字的意思選擇正確的顏色，在時間內獲得高分！
                </div>
                <div style="display:flex;align-items:center;margin-bottom:0.5rem;">
                    <span style="color:#3b82f6;font-size:1.2rem;margin-right:0.5rem;">◆</span>
                    <span style="font-weight:bold;font-size:1.1rem;">玩法</span>
                </div>
                <ul style="margin-left:2.2rem;">
                    <li>畫面上會出現一個顏色名稱，如「紅」、「藍」、「綠」</li>
                    <li>注意：我們看的是字的「意思」，不是字的顏色</li>
                    <li>例如：寫著「紅」字，就要選「紅色」選項</li>
                    <li>在時間內選擇正確的顏色獲得分數</li>
                </ul>
            </div>
            <span class="close-btn" onclick="closeHelpModal()">×</span>
        </div>
    </div>

    <div class="game-container">
        <h1>看字選色遊戲</h1>
        <div class="score-board">
            <div class="score-item">目前分數：<span id="score" style="color: #2ecc71; font-weight: bold;"><?php echo $_SESSION['score']; ?></span></div>
            <div class="score-item">最高分數：<span id="highScore" style="color: #2ecc71; font-weight: bold;"><?php echo $high_score; ?></span></div>
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
        const highScoreEl = document.getElementById('highScore');
        const timeEl = document.getElementById('time');
        const startBtn = document.getElementById('startBtn');
        const endBtn = document.getElementById('endBtn');

        const distractionContainer = document.getElementById('distractionContainer');

        // 初始化分數顯示
        scoreEl.textContent = '0';
        highScoreEl.textContent = highScore;

        const colorToChinese = (color) => {
            const colorObj = colors.find(c => c.name === color);
            return colorObj ? colorObj.chinese : color;
        };

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
                    const trapType = Math.floor(Math.random() * 5);
                    
                    switch(trapType) {
                        case 0:
                            document.getElementById('targetColorText').innerHTML = 
                                `<span style="color:${randomDisplayColor.code}; font-weight:bold; opacity:0.2;">${displayText}</span>`;
                            break;
                        case 1:
                            document.getElementById('targetColorText').innerHTML = 
                                `<span style="color:${randomDisplayColor.code}; font-weight:bold; animation:blink 0.3s infinite;">${displayText}</span>`;
                            break;
                        case 2:
                            document.getElementById('targetColorText').innerHTML = 
                                `<span style="color:${randomDisplayColor.code}; font-weight:bold; animation:gradient 0.5s infinite;">${displayText}</span>`;
                            break;
                        case 3:
                            document.getElementById('targetColorText').innerHTML = 
                                `<span style="color:${randomDisplayColor.code}; font-weight:bold; animation:shake 0.2s infinite;">${displayText}</span>`;
                            break;
                        case 4:
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

            selectedColors.forEach(color => {
                let btn = document.createElement('button');
                btn.className = 'color-btn';
                if (difficulty === 'hard' && Math.random() < 0.4) {
                    const effectType = Math.floor(Math.random() * 4);
                    switch(effectType) {
                        case 0:
                            btn.style.opacity = '0.3';
                            break;
                        case 1:
                            btn.classList.add('pulse');
                            break;
                        case 2:
                            btn.classList.add('shake');
                            break;
                        case 3:
                            btn.classList.add('rotate');
                            break;
                    }
                }
                btn.style.backgroundColor = color.code;
                btn.onclick = () => checkAnswer(color.name);
                container.appendChild(btn);
            });

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

        function checkAnswer(selectedColor) {
            if (!gameStarted) return;

            if (difficulty !== 'easy' && questionTimer) {
                clearInterval(questionTimer);
            }

            if (selectedColor === correctColor.name) {
                score += parseInt(difficultySettings[difficulty].points_per_correct);
                scoreEl.textContent = score.toString();
                if (score > highScore) {
                    highScore = score;
                    highScoreEl.textContent = highScore.toString();
                    // 更新最高分數到伺服器，帶上正確的難度
                    fetch('text-color.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'action=update_high_score&high_score=' + highScore + '&difficulty=' + difficulty
                    });
                }
            }
            generateQuestion();
        }

        function setDifficulty(level) {
            difficulty = level || 'normal'; // 預設為普通難度
            distractionContainer.innerHTML = '';
            timeLeft = difficultySettings[level].time_limit;
            
            // 從後端獲取新難度的最高分數
            fetch('text-color.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_high_score&difficulty=' + level
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.text();
            })
            .then(score => {
                console.log('Received high score for difficulty ' + level + ': ' + score); // 除錯用
                highScore = parseInt(score) || 0;
                highScoreEl.textContent = highScore.toString();
            })
            .catch(error => {
                console.error('Error fetching high score:', error);
            });
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
            
            // 確保顯示正確難度的最高分數
            fetch('text-color.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_high_score&difficulty=' + difficulty
            })
            .then(response => response.text())
            .then(score => {
                console.log('Starting game with high score: ' + score); // 除錯用
                highScore = parseInt(score) || 0;
                highScoreEl.textContent = highScore.toString();
            })
            .catch(error => console.error('Error fetching high score:', error));

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
    } else {
        // 暫停遊戲
        gamePaused = true;
        pauseBtn.textContent = '繼續遊戲';
        pauseBtn.style.setProperty('background-color', '#4CAF50', 'important');

        // 暫停計時器
        clearInterval(timer);
        clearInterval(distractionInterval);
        clearInterval(questionTimer);
    }
}

// 綁定暫停按鈕的點擊事件
document.getElementById('pauseBtn').addEventListener('click', togglePauseGame);


        function endGame() {
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
                highScoreEl.textContent = highScore.toString();
                // 更新最高分數到伺服器
                fetch('text-color.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=update_high_score&high_score=' + highScore + '&difficulty=' + difficulty
                });
            }
            
            // 記錄遊戲結果
            fetch('text-color.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=record_game&score=' + score + '&play_time=' + playTime + '&difficulty=' + difficulty
            })
            .then(response => response.text())
            .then(result => {
                console.log('遊戲結果已記錄:', result);
                // 清除成就快取
                if (typeof clearAchievementsCache === 'function') {
                    clearAchievementsCache();
                }
                // 立即刷新分數顯示
                if (typeof fetchUserScore === 'function') {
                    fetchUserScore();
                }
            })
            .catch(error => {
                console.error('記錄遊戲結果時發生錯誤:', error);
            });
            
            // 取得過關分數
            const passScore = parseInt(difficultySettings[difficulty].pass_score);
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
                    <h2>恭喜破關</h2>
                    <p>難度：${difficulty === 'easy' ? '簡單' : difficulty === 'normal' ? '普通' : '困難'}</p>
                    <p>獲得分數：${passBonus}</p>
                    <button class="red-btn" onclick="location.reload()">再玩一次</button>
                    <button class="blue-btn" onclick="location.href='index.php'">返回主頁</button>
                `;
            } else {
                // 失敗
                modalHtml = `
                    <h2>遊戲失敗</h2>
                    <p>難度：${difficulty === 'easy' ? '簡單' : difficulty === 'normal' ? '普通' : '困難'}</p>
                    <p>未在時間內達成分數</p>
                    <button class="red-btn" onclick="location.reload()">再玩一次</button>
                    <button class="blue-btn" onclick="location.href='index.php'">返回主頁</button>
                `;
            }
            document.getElementById('endGameContent').innerHTML = modalHtml;
            document.getElementById('endGameModal').style.display = 'block';

            // 最後再歸零分數
            score = 0;
            scoreEl.textContent = '0';
        }

        endBtn.addEventListener('click', endGame);

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
            
            // 清除可能的JavaScript快取
            if (typeof clearAchievementsCache === 'function') {
                clearAchievementsCache();
            }
            
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
            window.location.href = 'index.php';
        }

        function showHelp() {
            console.log('showHelp function called'); // 除錯用
            const helpModal = document.getElementById('help-modal');
            if (helpModal) {
                helpModal.classList.remove('hidden');
                helpModal.classList.add('show');
                console.log('Help modal should be visible now');
            } else {
                console.error('Help modal not found');
            }
        }

        function closeHelpModal() {
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
    <script src="js/achievements.js"></script>
</body>
</html>
