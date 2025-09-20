<?php
session_start();
require_once 'db_connect.php';

// 新增：AJAX 處理區塊（必須放最前面）
if (isset($_POST['ajax']) && $_POST['ajax'] === '1') {
    // 取得難度參數（POST > GET）
    $difficulty = $_POST['difficulty'] ?? $_GET['difficulty'] ?? null;
    if (!$difficulty) {
        echo json_encode(['error' => '缺少難度參數']);
        exit;
    }
    // 初始化 session
    if (!isset($_SESSION['clue_total'])) $_SESSION['clue_total'] = 0;
    if (!isset($_SESSION['clue_correct'])) $_SESSION['clue_correct'] = 0;
    if (!isset($_SESSION['used_question_ids'])) $_SESSION['used_question_ids'] = [];
    
    // 記錄遊戲開始時間（只在第一次初始化時記錄）
    if (!isset($_SESSION['game_start_time'])) {
        $_SESSION['game_start_time'] = time();
        // 重置防重复保存标志（只在新的游戏会话开始时）
        unset($_SESSION['game_record_saved']);
    }

    // 檢查上一題答案
    $user_answer = $_POST['user_answer'] ?? null;
    $correct_answer = $_POST['correct_answer'] ?? null;
    if ($user_answer !== null && $correct_answer !== null) {
        $_SESSION['clue_total']++;
        if ($user_answer === $correct_answer) {
            $_SESSION['clue_correct']++;
        }
    }

    // 5 題結束，回傳結果
    if ($_SESSION['clue_total'] >= 5) {
        $pass = $_SESSION['clue_correct'] >= 3;
        $score = $_SESSION['clue_correct'];
        // 查詢 pass_bounce
        $game_id = 8;
        $stmt2 = $pdo->prepare('SELECT pass_bounce FROM difficulty_settings WHERE game_id = ? AND difficulty = ? LIMIT 1');
        $stmt2->execute([$game_id, $difficulty]);
        $pass_bounce = ($row = $stmt2->fetch()) ? (int)$row['pass_bounce'] : 0;
        
        // 如果過關且有登入會員，更新 total_score
        if ($pass && isset($_SESSION['member_id'])) {
            $member_id = $_SESSION['member_id'];
            $stmt3 = $pdo->prepare('UPDATE member SET total_score = total_score + ? WHERE member_id = ?');
            $stmt3->execute([$pass_bounce, $member_id]);
        }
        
        // 儲存遊戲紀錄到 game_records 表（防重复保存）
        if (isset($_SESSION['member_id']) && !isset($_SESSION['game_record_saved'])) {
            $member_id = $_SESSION['member_id'];
            $play_date = date('Y-m-d H:i:s');
            $game_type = '記憶力';
            $is_single_player = 1;
            
            // 使用前端傳送的遊戲時間，如果沒有則使用後端計算的時間
            $play_time = $_POST['game_time'] ?? (isset($_SESSION['game_start_time']) ? 
                time() - $_SESSION['game_start_time'] : null);
            
            // 修正：根據是否過關決定保存的分數
            $score_to_save = $pass ? $pass_bounce : 0;
            
            // 檢查是否已經有相同的記錄（防止重複）
            $check_sql = "SELECT COUNT(*) as count FROM game_records 
                         WHERE member_id = ? AND game_id = ? AND difficulty = ? 
                         AND score = ? AND play_date = ? AND play_time = ?";
            $check_stmt = $pdo->prepare($check_sql);
            $check_stmt->execute([$member_id, $game_id, $difficulty, $score_to_save, $play_date, $play_time]);
            $existing_count = $check_stmt->fetch()['count'];
            
            if ($existing_count == 0) {
                $stmt4 = $pdo->prepare('INSERT INTO game_records (member_id, game_id, difficulty, score, play_date, play_time, game_type, is_single_player, opponent_id) VALUES (:member_id, :game_id, :difficulty, :score, :play_date, :play_time, :game_type, :is_single_player, :opponent_id)');
                $stmt4->execute([
                    'member_id' => $member_id,
                    'game_id' => $game_id,
                    'difficulty' => $difficulty,
                    'score' => $score_to_save, // 修正：使用正確的分數
                    'play_date' => $play_date,
                    'play_time' => $play_time,
                    'game_type' => $game_type,
                    'is_single_player' => $is_single_player,
                    'opponent_id' => null
                ]);
                
                // 記錄遊戲行為軌跡
                require_once 'log_game_behavior.php';
                logGameBehavior($member_id, '記憶力', $play_time, $score_to_save, $difficulty);
                
                // 檢查並完成所有相關任務
                require_once 'check_and_grant_achievements.php';
                checkAndGrantAchievements($member_id, 'memory_game', $score_to_save, $play_time);
                checkAndCompleteAllTasks($member_id, '記憶力');
            }
            
            // 標記已保存，防止重複保存
            $_SESSION['game_record_saved'] = true;
        }
        
        // 清空 session（保留防重复标志）
        $_SESSION['clue_total'] = 0;
        $_SESSION['clue_correct'] = 0;
        $_SESSION['used_question_ids'] = [];
        unset($_SESSION['game_start_time']); // 清除遊戲開始時間
        // 注意：不删除 $_SESSION['game_record_saved']，防止重复保存
        
        echo json_encode([
            'end' => true,
            'pass' => $pass,
            'score' => $score,
            'difficulty' => $difficulty,
            'pass_bounce' => $pass_bounce
        ]);
        exit;
    }
    
    // 處理強制結束遊戲
    if (isset($_POST['force_end']) && $_POST['force_end'] === '1') {
        $final_score = (int)($_POST['final_score'] ?? 0);
        $pass = $final_score >= 3;
        
        // 查詢 pass_bounce
        $game_id = 8;
        $stmt2 = $pdo->prepare('SELECT pass_bounce FROM difficulty_settings WHERE game_id = ? AND difficulty = ? LIMIT 1');
        $stmt2->execute([$game_id, $difficulty]);
        $pass_bounce = ($row = $stmt2->fetch()) ? (int)$row['pass_bounce'] : 0;
        
        // 如果過關且有登入會員，更新 total_score
        if ($pass && isset($_SESSION['member_id'])) {
            $member_id = $_SESSION['member_id'];
            $stmt3 = $pdo->prepare('UPDATE member SET total_score = total_score + ? WHERE member_id = ?');
            $stmt3->execute([$pass_bounce, $member_id]);
        }
        
        // 儲存遊戲紀錄到 game_records 表（防重复保存）
        if (isset($_SESSION['member_id']) && !isset($_SESSION['game_record_saved'])) {
            $member_id = $_SESSION['member_id'];
            $play_date = date('Y-m-d H:i:s');
            $game_type = '記憶力';
            $is_single_player = 1;
            
            // 使用前端傳送的遊戲時間
            $play_time = $_POST['game_time'] ?? null;
            
            // 修正：根據是否過關決定保存的分數
            $score_to_save = $pass ? $pass_bounce : 0;
            
            // 檢查是否已經有相同的記錄（防止重複）
            $check_sql = "SELECT COUNT(*) as count FROM game_records 
                         WHERE member_id = ? AND game_id = ? AND difficulty = ? 
                         AND score = ? AND play_date = ? AND play_time = ?";
            $check_stmt = $pdo->prepare($check_sql);
            $check_stmt->execute([$member_id, $game_id, $difficulty, $score_to_save, $play_date, $play_time]);
            $existing_count = $check_stmt->fetch()['count'];
            
            if ($existing_count == 0) {
                $stmt4 = $pdo->prepare('INSERT INTO game_records (member_id, game_id, difficulty, score, play_date, play_time, game_type, is_single_player, opponent_id) VALUES (:member_id, :game_id, :difficulty, :score, :play_date, :play_time, :game_type, :is_single_player, :opponent_id)');
                $stmt4->execute([
                    'member_id' => $member_id,
                    'game_id' => $game_id,
                    'difficulty' => $difficulty,
                    'score' => $score_to_save, // 修正：使用正確的分數
                    'play_date' => $play_date,
                    'play_time' => $play_time,
                    'game_type' => $game_type,
                    'is_single_player' => $is_single_player,
                    'opponent_id' => null
                ]);
                
                // 記錄遊戲行為軌跡
                require_once 'log_game_behavior.php';
                logGameBehavior($member_id, '記憶力', $play_time, $score_to_save, $difficulty);
                
                // 檢查並完成所有相關任務
                require_once 'check_and_grant_achievements.php';
                checkAndGrantAchievements($member_id, 'memory_game', $score_to_save, $play_time);
                checkAndCompleteAllTasks($member_id, '記憶力');
            }
            
            // 標記已保存，防止重複保存
            $_SESSION['game_record_saved'] = true;
        }
        
        // 清空 session（保留防重复标志）
        $_SESSION['clue_total'] = 0;
        $_SESSION['clue_correct'] = 0;
        $_SESSION['used_question_ids'] = [];
        unset($_SESSION['game_start_time']); // 清除遊戲開始時間
        // 注意：不删除 $_SESSION['game_record_saved']，防止重复保存
        
        echo json_encode([
            'end' => true,
            'pass' => $pass,
            'score' => $final_score,
            'difficulty' => $difficulty,
            'pass_bounce' => $pass_bounce
        ]);
        exit;
    }

    // 取得所有題目 question_id（依難度）
    $all_ids = $pdo->prepare('SELECT question_id FROM questions WHERE difficulty = ?');
    $all_ids->execute([$difficulty]);
    $all_ids = $all_ids->fetchAll(PDO::FETCH_COLUMN);
    // 排除已出現過的題目
    $used_ids = $_SESSION['used_question_ids'];
    $placeholders = implode(',', array_fill(0, count($used_ids), '?'));
    $sql = 'SELECT * FROM questions WHERE difficulty = ? ' . (count($used_ids) ? 'AND question_id NOT IN (' . $placeholders . ') ' : '') . 'ORDER BY RAND() LIMIT 1';
    $params = array_merge([$difficulty], $used_ids);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $question = $stmt->fetch();
    if (!$question) {
        echo json_encode(['error' => '找不到題目']);
        exit;
    }
    $_SESSION['used_question_ids'][] = $question['question_id'];
    $image_path = 'img/' . $question['image_path']; // 修正為 img/clue/
    echo json_encode([
        'end' => false,
        'question' => [
            'question_text' => $question['question_text'],
            'option_1' => $question['option_1'],
            'option_2' => $question['option_2'],
            'option_3' => $question['option_3'],
            'option_4' => $question['option_4'],
            'correct_answer_text' => $question['correct_answer_text'],
            'image_path' => $image_path,
            'display_time' => (int)$question['display_time'],
        ],
        'current' => $_SESSION['clue_total'] + 1,
        'score' => $_SESSION['clue_correct'],
        'difficulty' => $difficulty
    ]);
    exit;
}

// 取得難度參數
$difficulty = $_GET['difficulty'] ?? null;
if (!$difficulty) {
    // 若沒選難度，前端會顯示modal，不執行出題
    // 只輸出 HTML，JS 控制
    ?>
    <!DOCTYPE html>
    <html lang="zh-Hant">
    <head>
        <meta charset="UTF-8">
        <title>選擇難度 - 圖片線索問答遊戲</title>
        <link rel="stylesheet" href="css/clue.css">
        <style>
            body {
                background: #f5f6fa;
            }
            #difficulty-modal {
                position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
                background: rgba(0,0,0,0.15); display: flex; align-items: center; justify-content: center; z-index: 9999;
            }
            #difficulty-modal .modal-content {
                background: #fff; padding: 32px 36px 28px 36px; border-radius: 20px; text-align: center;
                box-shadow: 0 8px 32px rgba(0,0,0,0.18);
                min-width: 350px;
                width: 450px;
                position: relative;
                font-size: 1.0rem;
                min-height: 60vh;
                max-height: 75vh;
                overflow: auto;
                display: flex;
                flex-direction: column;
            }
            .modal-header {
                display: flex; justify-content: space-between; align-items: center; margin-bottom: 28px;
                position: relative;
            }
            .modal-title {
                font-size: 1.8rem; font-weight: bold; color: #222;
                letter-spacing: 1px;
                white-space: nowrap;
            }
            .icon-block { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px; }
            .icon-block .circle {
                width: 40px; height: 40px; border-radius: 50%; border: 2px solid #000; display: flex; align-items: center; justify-content: center;
                font-weight: bold; color: #000; background: #fff;
            }
            .icon-block .label { font-size: 1.05rem; color: #555; }
            .icon-link { text-decoration: none; color: inherit; }
            .difficulty-btn {
                display: block; width: 100%; margin: 0 auto 25px auto; padding: 28px 0; font-size: 1.4rem; font-weight: bold;
                border: none; border-radius: 14px; cursor: pointer; transition: filter 0.15s;
                box-shadow: 0 2px 8px rgba(0,0,0,0.06);
                letter-spacing: 1px;
            }
            .difficulty-list {
                display: flex; flex-direction: column; gap: 15px; flex: 1; justify-content: center; margin: 8px 0 16px 0;
            }
            .difficulty-btn:last-child { margin-bottom: 0; }
            .difficulty-btn.easy { background: #2ecc40; color: #000; }
            .difficulty-btn.easy:hover { filter: brightness(0.95); }
            .difficulty-btn.medium { background: #ffe066; color: #000; }
            .difficulty-btn.medium:hover { filter: brightness(0.97); }
            .difficulty-btn.hard { background: #ff4d4f; color: #000; }
            .difficulty-btn.hard:hover { filter: brightness(0.95); }
            /* 說明彈窗 */
            #help-modal-bg {
                display: none; position: fixed; z-index: 10000; left: 0; top: 0; width: 100vw; height: 100vh;
                background: rgba(0,0,0,0.25); align-items: center; justify-content: center;
            }
            #help-modal-bg.active { display: flex; }
            #help-modal {
                background: #fff; border-radius: 18px; box-shadow: 0 6px 28px rgba(0,0,0,0.2);
                padding: 36px 32px 28px 32px; max-width: 560px; width: 560px; text-align: left; position: relative;
            }
            #help-modal h3 { margin-top: 0; font-size: 2rem; font-weight: bold; }
            #help-modal p { font-size: 1.5rem; color: #333; line-height: 2.0; margin-bottom: 0; }
            #help-modal .close-btn {
                position: absolute; top: 12px; right: 16px; background: none; border: none; font-size: 1.6rem; color: #888; cursor: pointer;
            }
            #help-modal .close-btn:hover { color: #222; }
            /* 返回按鈕 */
            .back-button {
                position: absolute !important;
                top: 0.1rem !important;
                left: 0.2rem !important;
                width: 45px !important;
                height: 45px !important;
                border-radius: 50% !important;
                border: 2px solid #000 !important;
                background: #fff !important;
                color: #222 !important;
                font-size: 1.6rem !important;
                cursor: pointer !important;
                transition: all 0.2s ease !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                z-index: 9999 !important;
            }
            .back-button .back-arrow {
                font-size: 1.6rem;
                font-weight: 900;
                line-height: 1;
            }
            .back-button .back-label {
                position: absolute !important;
                left: 50% !important;
                top: 100% !important;
                transform: translateX(-50%) !important;
                font-size: 1.05rem !important;
                color: #666 !important;
                margin-top: 0.5rem !important;
                user-select: none;
                white-space: nowrap;
                line-height: 1.1;
            }
            .back-button:hover {
                background: #f2f2f2;
                color: #111;
                border-color: #000 !important;
            }
        </style>
    </head>
    <body>
        <div id="difficulty-modal">
            <div class="modal-content">
                <div class="modal-header">
                    <button class="back-button" onclick="history.back()" style="position:absolute;top:1rem;left:1.2rem;z-index:10;">
                        <span class="back-arrow">←</span>
                        <div class="back-label">返回</div>
                    </button>
                    <div style="flex: 1; text-align: center; margin-left: 3rem;">
                        <span class="modal-title">選擇難度</span>
                    </div>
                    <div class="icon-block" id="show-help" style="cursor:pointer;">
                        <div class="circle">?</div>
                        <span class="label">說明</span>
                    </div>
                </div>
                <div class="difficulty-list">
                    <button class="difficulty-btn easy" data-difficulty="easy">簡單</button>
                    <button class="difficulty-btn medium" data-difficulty="normal">普通</button>
                    <button class="difficulty-btn hard" data-difficulty="hard">困難</button>
                </div>
            </div>
        </div>
        <!-- 說明彈窗 -->
        <div id="help-modal-bg">
            <div id="help-modal">
                <button class="close-btn" id="close-help">×</button>
                <h3 style="text-align:center;">
                    <span style="font-size:2rem;vertical-align:middle;">🎮</span>
                    <span style="font-weight:bold;vertical-align:middle;">遊戲說明</span>
                </h3>
                <div class="help-content" style="margin-top:2.5rem;padding:0 2rem;">
                    <!-- 影片播放區域 -->
                    <div id="clue-video-container" style="text-align:center;margin-bottom:2.5rem;">
                        <video id="clue-current-video" width="100%" height="auto" controls style="max-width:700px;width:80%;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,0.15);">
                            <source src="gd/clue1.mp4" type="video/mp4">
                            您的瀏覽器不支援影片播放。
                        </video>
                    </div>
                    
                    <!-- 說明文字和按鈕區域 (並排顯示) -->
                    <div style="display:flex;justify-content:center;align-items:center;margin:0 1rem;margin-bottom:2rem; gap: 20px;">
                        <!-- 上一步按鈕 -->
                        <div id="clue-prev-step-btn" style="display:none;">
                            <button id="clue-prev-step-button" onclick="goToCluePrevStep()" class="game-step-button prev-step" style="padding:14px 28px;font-size:20px;">
                                上一步
                            </button>
                        </div>
                        
                        <!-- 說明文字 -->
                        <div id="clue-instruction-text" class="game-instruction-text" style="font-size:24px;flex:3;text-align:center;min-width:300px;">
                            選擇難度後即可進入到遊戲畫面
                        </div>
                        
                        <!-- 下一步按鈕 -->
                        <div id="clue-next-step-btn" style="margin-left:2rem;">
                            <button id="clue-next-step-button" class="game-step-button next-step" style="padding:14px 28px;font-size:20px;">
                                下一步
                            </button>
                            </button>
                        </div>
                    </div>
                    
                    <!-- 進度指示器 -->
                    <div style="text-align:center;margin-top:1.5rem;margin-bottom:1.5rem;">
                        <span id="clue-step-indicator" class="game-step-indicator" style="font-size:18px;">步驟 1/2</span>
                    </div>
                </div>
            </div>
        </div>
        <script>

            document.querySelectorAll('.difficulty-btn').forEach(btn => {
                btn.onclick = function() {
                    const diff = this.getAttribute('data-difficulty');
                    window.location.href = window.location.pathname + '?difficulty=' + encodeURIComponent(diff);
                }
            });
            // 說明彈窗
            const helpModalBg = document.getElementById('help-modal-bg');
            document.getElementById('show-help').onclick = function() {
                helpModalBg.classList.add('active');
                // 初始化圖片線索影片播放邏輯
                initClueVideoPlayback();
            };
            document.getElementById('close-help').onclick = function() {
                // 停止視頻播放
                const video = document.getElementById('clue-current-video');
                if (video) {
                    video.pause();
                    video.currentTime = 0; // 重置到開始位置
                }
                
                helpModalBg.classList.remove('active');
            };
            helpModalBg.onclick = function(e) {
                if (e.target === helpModalBg) helpModalBg.classList.remove('active');
            };
            
            // 初始化圖片線索視影片播放邏輯
            function initClueVideoPlayback() {
                const video = document.getElementById('clue-current-video');
                const instructionText = document.getElementById('clue-instruction-text');
                const stepIndicator = document.getElementById('clue-step-indicator');
                const nextStepBtn = document.getElementById('clue-next-step-btn');
                const prevStepBtn = document.getElementById('clue-prev-step-btn');
                
                if (!video || !instructionText || !stepIndicator || !nextStepBtn || !prevStepBtn) {
                    console.error('找不到圖片線索遊戲說明元素');
                    return;
                }
                
                // 設置第一個影片
                video.src = 'gd/clue1.mp4';
                instructionText.textContent = '選擇難度後即可進入到遊戲畫面';
                stepIndicator.textContent = '步驟 1/2';
                
                // 設置當前影片標記
                video.setAttribute('data-current-video', 'clue1');
                
                // 顯示下一步按鈕，隱藏上一步按鈕
                nextStepBtn.style.display = 'block';
                prevStepBtn.style.display = 'none';
                
                // 強制加載影片
                video.load();
                
                // 添加下一步按鈕點擊事件
                const nextStepButton = document.getElementById('clue-next-step-button');
                if (nextStepButton) {
                    nextStepButton.onclick = goToClueNextStep;
                    console.log('圖片線索下一步按鈕事件已綁定');
                }
            }
            
            // 前往圖片線索下一步
            function goToClueNextStep() {
                const video = document.getElementById('clue-current-video');
                const instructionText = document.getElementById('clue-instruction-text');
                const stepIndicator = document.getElementById('clue-step-indicator');
                const nextStepBtn = document.getElementById('clue-next-step-btn');
                const prevStepBtn = document.getElementById('clue-prev-step-btn');
                
                // 切換到第二個影片
                video.src = 'gd/clue2.mp4';
                video.setAttribute('data-current-video', 'clue2');
                instructionText.innerHTML = '仔細觀察圖片，倒數結束後選擇答案';
                stepIndicator.textContent = '步驟 2/2';
                
                // 隱藏下一步按鈕，顯示上一步按鈕
                nextStepBtn.style.display = 'none';
                prevStepBtn.style.display = 'block';
                
                // 加載並播放視影片
                video.load();
                video.play();
            }
            
            // 回到圖片線索上一步
            function goToCluePrevStep() {
                const video = document.getElementById('clue-current-video');
                const instructionText = document.getElementById('clue-instruction-text');
                const stepIndicator = document.getElementById('clue-step-indicator');
                const nextStepBtn = document.getElementById('clue-next-step-btn');
                const prevStepBtn = document.getElementById('clue-prev-step-btn');
                
                // 切換到第一個影片
                video.src = 'gd/clue1.mp4';
                video.setAttribute('data-current-video', 'clue1');
                instructionText.textContent = '選擇難度後即可進入到遊戲畫面';
                stepIndicator.textContent = '步驟 1/2';
                
                // 顯示下一步按鈕，隱藏上一步按鈕
                nextStepBtn.style.display = 'block';
                prevStepBtn.style.display = 'none';
                
                // 加載並播放視影片
                video.load();
                video.play();
            }
            
            // 設為全局可訪問
            window.goToClueNextStep = goToClueNextStep;
            window.goToCluePrevStep = goToCluePrevStep;
        </script>
    </body>
    </html>
    <?php
    exit;
}

// 取得所有題目 question_id（依難度）
$all_ids = $pdo->prepare('SELECT question_id FROM questions WHERE difficulty = ?');
$all_ids->execute([$difficulty]);
$all_ids = $all_ids->fetchAll(PDO::FETCH_COLUMN);

// 初始化 session
if (!isset($_SESSION['used_question_ids']) || count($_SESSION['used_question_ids']) >= count($all_ids)) {
    $_SESSION['used_question_ids'] = [];
}

// 排除已出現過的題目
$used_ids = $_SESSION['used_question_ids'];
$placeholders = implode(',', array_fill(0, count($used_ids), '?'));
$sql = 'SELECT * FROM questions WHERE difficulty = ? ' . (count($used_ids) ? 'AND question_id NOT IN (' . $placeholders . ') ' : '') . 'ORDER BY RAND() LIMIT 1';
$params = array_merge([$difficulty], $used_ids);
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$question = $stmt->fetch();

if (!$question) {
    die('找不到題目');
}

// 記錄這題已出現過
$_SESSION['used_question_ids'][] = $question['question_id'];

// 圖片路徑
$image_path = 'img/' . $question['image_path']; // 修正為 img/clue/
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <title>圖片線索問答遊戲</title>
    <link rel="stylesheet" href="css/clue.css">
    <style>
        body { font-family: Arial, sans-serif; text-align: center; margin-top: 40px; }
        #question-block, #result-block { display: none; }
        .option-btn { margin: 8px; padding: 10px 30px; font-size: 18px; cursor: pointer; }
        //#image-block img { max-width: 400px; max-height: 300px; }
        #timer-block { 
            margin: 20px 0; 
            padding: 10px; 
            background-color: #f0f0f0;
            border: 2px solid #ddd;
            border-radius: 5px;
            color: #333;
            font-size: 18px;
        }
        #time-left { 
            font-size: 20px; 
            color: #e74c3c; 
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="main-container"
         data-display-time="<?= (int)$question['display_time'] ?>"
         data-correct-answer="<?= htmlspecialchars($question['correct_answer_text']) ?>">
        <h2>請仔細觀察下方圖片，<?= (int)$question['display_time'] ?>秒後將進行提問！</h2>
        <div id="timer-block">
            <div id="countdown">剩餘時間：<span id="time-left"><?= (int)$question['display_time'] ?></span> 秒</div>
        </div>
        <div id="image-block">
            <img src="<?= htmlspecialchars($image_path) ?>" alt="題目圖片">
        </div>
        <div id="question-block">
            <h3><?= htmlspecialchars($question['question_text']) ?></h3>
            <form id="answer-form" class="options-flex">
                <button type="button" class="option-btn" data-value="<?= htmlspecialchars($question['option_1']) ?>"> <?= htmlspecialchars($question['option_1']) ?> </button>
                <button type="button" class="option-btn" data-value="<?= htmlspecialchars($question['option_2']) ?>"> <?= htmlspecialchars($question['option_2']) ?> </button>
                <button type="button" class="option-btn" data-value="<?= htmlspecialchars($question['option_3']) ?>"> <?= htmlspecialchars($question['option_3']) ?> </button>
                <button type="button" class="option-btn" data-value="<?= htmlspecialchars($question['option_4']) ?>"> <?= htmlspecialchars($question['option_4']) ?> </button>
            </form>
        </div>
        <div id="result-block">
            <h3 id="result-msg"></h3>
            <p>正確答案：<span id="correct-answer"></span></p>
        </div>
        <div class="control-btns">
            <button id="pauseBtn">暫停遊戲</button>
            <button id="endBtn" class="red-btn">結束遊戲</button>
            <button id="resetBtn" class="blue-btn">重新開始</button>
        </div>
    </div>
    <script src="js/clue.js"></script>
    <script src="js/auto-save-time-fixed.js"></script>
</body>
</html> 