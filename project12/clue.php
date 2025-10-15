<?php
session_start();
require_once 'db_connect.php';
require_once 'game_entry_tracker.php';

// 新增：AJAX 處理區塊（必須放最前面）
if (isset($_POST['ajax']) && $_POST['ajax'] === '1') {
    // 取得難度參數（POST > GET）
    $difficulty = $_POST['difficulty'] ?? $_GET['difficulty'] ?? null;
    if (!$difficulty) {
        echo json_encode(['error' => '缺少難度參數']);
        exit;
    }
    
    // 處理開始新遊戲
    if (isset($_POST['action']) && $_POST['action'] === 'start_game') {
        // 重置所有遊戲相關的session
        $_SESSION['clue_total'] = 0;
        $_SESSION['clue_correct'] = 0;
        $_SESSION['used_question_ids'] = [];
        unset($_SESSION['game_start_time']);
        unset($_SESSION['game_record_saved']);
        unset($_SESSION['clue_record_id']);
        
        echo json_encode(['success' => true, 'message' => '遊戲已重置']);
        exit;
    }
    
    // 初始化 session
    if (!isset($_SESSION['clue_total'])) $_SESSION['clue_total'] = 0;
    if (!isset($_SESSION['clue_correct'])) $_SESSION['clue_correct'] = 0;
    if (!isset($_SESSION['used_question_ids'])) $_SESSION['used_question_ids'] = [];
    
    // 調試信息：記錄session狀態
    error_log("線索遊戲AJAX - Session狀態: clue_total=" . $_SESSION['clue_total'] . ", clue_correct=" . $_SESSION['clue_correct']);
    
    // 記錄遊戲開始時間（只在第一次初始化時記錄）
    if (!isset($_SESSION['game_start_time'])) {
        $_SESSION['game_start_time'] = time();
        // 重置防重复保存标志（只在新的游戏会话开始时）
        unset($_SESSION['game_record_saved']);
        
        // 記錄遊戲進入（統一系統會自動處理）
        // 移除舊的記錄邏輯，統一系統會自動處理
    }

    // 檢查上一題答案
    $user_answer = $_POST['user_answer'] ?? null;
    $correct_answer = $_POST['correct_answer'] ?? null;
    if ($user_answer !== null && $correct_answer !== null) {
        $_SESSION['clue_total']++;
        if ($user_answer === $correct_answer) {
            $_SESSION['clue_correct']++;
        }
        
        // 強制保存session
        session_write_close();
        session_start();
        
        // 調試信息：記錄答案處理結果
        error_log("線索遊戲答案處理 - 用戶答案: $user_answer, 正確答案: $correct_answer, 總題數: " . $_SESSION['clue_total'] . ", 答對數: " . $_SESSION['clue_correct']);
    }

    // 檢查是否達到過關條件或5題結束
    if ($_SESSION['clue_correct'] >= 3 || $_SESSION['clue_total'] >= 5) {
        $pass = $_SESSION['clue_correct'] >= 3; // 直接使用3題過關
        $score = $_SESSION['clue_correct'];
        // 查詢 pass_bounce
        $game_id = 8;
        $stmt2 = $pdo->prepare('SELECT pass_bounce FROM difficulty_settings WHERE game_id = ? AND difficulty = ? LIMIT 1');
        $stmt2->execute([$game_id, $difficulty]);
        $pass_bounce = ($row = $stmt2->fetch()) ? (int)$row['pass_bounce'] : 0;
        
        // 使用統一API處理遊戲結果
        if (isset($_SESSION['member_id'])) {
            $member_id = $_SESSION['member_id'];
            $play_time = $_POST['game_time'] ?? (isset($_SESSION['game_start_time']) ? 
                time() - $_SESSION['game_start_time'] : 0);
            // 🔑 AJAX 結束是正常結束，不是手動退出
            $isManualExit = false;
            
            $gameData = [
                'member_id' => $member_id,
                'game_type' => '記憶力', // 圖片線索問答屬於記憶力類型
                'difficulty' => $difficulty,
                'score' => $pass ? $pass_bounce : 0,
                'play_time' => $play_time,
                'is_manual_exit' => $isManualExit,
                'is_passed' => $pass,
                'game_id' => 8
            ];
            
            // 直接調用API處理函數，避免curl問題
            try {
                require_once 'api/game_result.php';
                
                // 檢查資料庫連接
                global $pdo;
                if (!$pdo) {
                    error_log("圖片線索遊戲：資料庫連接失敗");
                } else {
                    // 直接調用processGameResult函數
                    $result = processGameResult($gameData);
                    
                    if (!$result || !$result['success']) {
                        error_log("圖片線索問答API處理失敗: " . ($result['message'] ?? '未知錯誤'));
                    } else {
                        error_log("圖片線索遊戲結果儲存成功: record_id=" . ($result['record_id'] ?? 'unknown'));
                    }
                }
            } catch (Exception $e) {
                error_log("圖片線索遊戲結果處理失敗: " . $e->getMessage());
            }
            
                // 清除記錄ID，防止重複更新
                unset($_SESSION['clue_record_id']);
        }
        
        // 清空 session（保留防重复标志）
        $_SESSION['clue_total'] = 0;
        $_SESSION['clue_correct'] = 0;
        $_SESSION['used_question_ids'] = [];
        // 注意：不删除 $_SESSION['game_start_time']，需要保留用於計算遊戲時間
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
        $pass = $final_score >= 3; // 直接使用3題過關
        
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
        
        // 更新遊戲記錄（新追蹤邏輯）
        if (isset($_SESSION['member_id']) && isset($_SESSION['clue_record_id'])) {
            $member_id = $_SESSION['member_id'];
            $record_id = $_SESSION['clue_record_id'];
            
            // 使用前端傳送的遊戲時間
            $play_time = $_POST['game_time'] ?? 0;
            
            // 根據是否過關決定保存的分數和狀態
            $score_to_save = $pass ? $pass_bounce : 0;
            
            // 區分手動退出和遊戲失敗
            $isManualExit = isset($_POST['is_manual_exit']) && $_POST['is_manual_exit'] === '1';
            if ($isManualExit) {
                // 手動退出遊戲
                $status = $pass ? 'completed' : 'exited';
            } else {
                // 正常遊戲結束（時間到或達到目標）
                $status = $pass ? 'completed' : 'failed';
            }
            
            // 更新遊戲記錄
            updateGameRecord($record_id, $score_to_save, $play_time, $status);
            
            // 檢查並完成所有相關任務
            require_once 'check_and_grant_achievements.php';
            checkAndGrantAchievements($member_id, 'memory_game', $score_to_save, $play_time);
            checkAndCompleteAllTasks($member_id, '記憶力');
            
            // 清除記錄ID，防止重複更新
            unset($_SESSION['clue_record_id']);
        }
        
        // 清空 session（保留防重复标志）
        $_SESSION['clue_total'] = 0;
        $_SESSION['clue_correct'] = 0;
        $_SESSION['used_question_ids'] = [];
        // 注意：不删除 $_SESSION['game_start_time']，需要保留用於計算遊戲時間
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


// 處理非 AJAX 的答案提交
if (isset($_POST['user_answer']) && isset($_POST['correct_answer'])) {
    // 取得難度參數（從 POST 或 GET）
    $difficulty = $_POST['difficulty'] ?? $_GET['difficulty'] ?? null;
    if (!$difficulty) {
        header('Location: clue.php');
        exit;
    }
    
    // 初始化 session（只在第一次時初始化）
    if (!isset($_SESSION['clue_total'])) $_SESSION['clue_total'] = 0;
    if (!isset($_SESSION['clue_correct'])) $_SESSION['clue_correct'] = 0;
    if (!isset($_SESSION['used_question_ids'])) $_SESSION['used_question_ids'] = [];
    
    // 處理答案
    $user_answer = $_POST['user_answer'];
    $correct_answer = $_POST['correct_answer'];
    $_SESSION['clue_total']++;
    if ($user_answer === $correct_answer) {
        $_SESSION['clue_correct']++;
    }
    
    
    // 檢查是否達到過關條件或5題結束
    if ($_SESSION['clue_correct'] >= 3 || $_SESSION['clue_total'] >= 5) {
        $pass = $_SESSION['clue_correct'] >= 3; // 直接使用3題過關
        $score = $_SESSION['clue_correct'];
        
        // 查詢 pass_bounce
        $game_id = 8;
        $stmt2 = $pdo->prepare('SELECT pass_bounce FROM difficulty_settings WHERE game_id = ? AND difficulty = ? LIMIT 1');
        $stmt2->execute([$game_id, $difficulty]);
        $pass_bounce = ($row = $stmt2->fetch()) ? (int)$row['pass_bounce'] : 0;
        
        // 使用統一API處理遊戲結果
        if (isset($_SESSION['member_id'])) {
            $gameData = [
                'member_id' => $_SESSION['member_id'],
                'game_type' => '記憶力',
                'game_id' => 8,
                'difficulty' => $difficulty,
                'score' => $pass ? $pass_bounce : 0,
                'play_time' => isset($_SESSION['game_start_time']) ? (time() - $_SESSION['game_start_time']) : 0,
                'is_manual_exit' => false,
                'is_passed' => $pass
            ];
            
            // 使用API端點處理遊戲結果
            $apiUrl = 'api/game_result.php';
            // 直接調用API處理函數，避免curl問題
            try {
                require_once 'api/game_result.php';
                
                // 檢查資料庫連接
                global $pdo;
                if (!$pdo) {
                    error_log("圖片線索遊戲：資料庫連接失敗");
                } else {
                    // 直接調用processGameResult函數
                    $result = processGameResult($gameData);
                    
                    if (!$result || !$result['success']) {
                        error_log("圖片線索問答API處理失敗: " . ($result['message'] ?? '未知錯誤'));
                    } else {
                        error_log("圖片線索遊戲結果儲存成功: record_id=" . ($result['record_id'] ?? 'unknown'));
                    }
                }
            } catch (Exception $e) {
                error_log("圖片線索遊戲結果處理失敗: " . $e->getMessage());
            }
        }
        
        // 清除 session（保留用於結果顯示）
        // 注意：不立即清除session，讓結果頁面能正確顯示
        // session將在重新開始遊戲時被清除
        
        // 顯示結果頁面
        ?>
        <!DOCTYPE html>
        <html lang="zh-Hant">
        <head>
            <meta charset="UTF-8">
            <title>遊戲結果</title>
            <link rel="stylesheet" href="css/clue.css?v=<?php echo time(); ?>">
            <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
            <meta http-equiv="Pragma" content="no-cache">
            <meta http-equiv="Expires" content="0">
        </head>
        <body>
            <div class="result-container">
                <div class="modal-content">
                    <h2><?= $pass ? '🎉恭喜破關' : '⏰遊戲結束' ?></h2>
                    <div class="result-info">
                        <p><strong>答對題數：</strong><?= $score ?>/5</p>
                        <p><strong>過關條件：</strong>3題</p>
                        <?php if (!$pass): ?>
                            <p>未達成目標分數!</p>
                        <?php endif; ?>
                        <?php if ($pass): ?>
                            <p><strong>獲得分數：+</strong><?= $pass_bounce ?>分</p>
                        <?php endif; ?>
                    </div>
                    <div class="result-btns">
                        <button onclick="restartGame()">再玩一次</button>
                        <button onclick="handleBackButton()">返回主頁</button>
                    </div>
                </div>
            </div>
            
            <script>
                // 返回主頁按鈕處理
                function handleBackButton() {
                    // 智能返回：回到上一頁，如果沒有上一頁則回到遊戲分類頁面
                    if (document.referrer && document.referrer !== window.location.href) {
                        history.back();
                    } else {
                        window.location.href = 'game-category.php';
                    }
                }
                
                // 重新開始遊戲
                function restartGame() {
                    // 發送AJAX請求重置遊戲
                    fetch('clue.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'ajax=1&action=start_game'
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // 重置成功，跳轉到難度選擇頁面
                            window.location.href = 'clue.php';
                        } else {
                            console.error('重置遊戲失敗:', data.message);
                            // 即使重置失敗也跳轉
                            window.location.href = 'clue.php';
                        }
                    })
                    .catch(error => {
                        console.error('重置遊戲請求失敗:', error);
                        // 即使請求失敗也跳轉
                        window.location.href = 'clue.php';
                    });
                }
            </script>
        </body>
        </html>
        <?php
        exit;
    }
    
    // 繼續下一題，返回 JSON 數據而不是重新載入頁面
    // 獲取新題目（使用正確的表格名稱）
    $used_ids = $_SESSION['used_question_ids'];
    $placeholders = implode(',', array_fill(0, count($used_ids), '?'));
    $sql = 'SELECT * FROM questions WHERE difficulty = ? ' . (count($used_ids) ? 'AND question_id NOT IN (' . $placeholders . ') ' : '') . 'ORDER BY RAND() LIMIT 1';
    $params = array_merge([$difficulty], $used_ids);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $question = $stmt->fetch();
    
    if ($question) {
        $_SESSION['used_question_ids'][] = $question['question_id'];
        
        // 返回新題目的 JSON 數據
        echo json_encode([
            'end' => false,
            'question' => [
                'question_id' => $question['question_id'],
                'question_text' => $question['question_text'],
                'image_path' => $question['image_path'],
                'option_1' => $question['option_1'],
                'option_2' => $question['option_2'],
                'option_3' => $question['option_3'],
                'option_4' => $question['option_4'],
                'correct_answer_text' => $question['correct_answer_text'],
                'display_time' => $question['display_time']
            ],
            'session_data' => [
                'clue_total' => $_SESSION['clue_total'],
                'clue_correct' => $_SESSION['clue_correct']
            ]
        ]);
    } else {
        // 沒有更多題目，遊戲結束
        $pass = $_SESSION['clue_correct'] >= 3;
        $score = $_SESSION['clue_correct'];
        
        // 查詢 pass_bounce
        $game_id = 8;
        $stmt2 = $pdo->prepare('SELECT pass_bounce FROM difficulty_settings WHERE game_id = ? AND difficulty = ? LIMIT 1');
        $stmt2->execute([$game_id, $difficulty]);
        $pass_bounce = ($row = $stmt2->fetch()) ? (int)$row['pass_bounce'] : 0;
        
        echo json_encode([
            'end' => true,
            'pass' => $pass,
            'score' => $score,
            'difficulty' => $difficulty,
            'pass_bounce' => $pass_bounce
        ]);
    }
    exit;
}

// 移除重複的結果頁面處理邏輯，統一使用第一個結果頁面

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
            
            /* 難度選擇彈窗專屬樣式 - 與算菜錢遊戲一致 */
            #difficulty-modal {
                position: fixed;
                top: 0; left: 0;
                width: 100vw;
                height: 100vh;
                background-color: rgba(0,0,0,0.5);
                display: flex;
                justify-content: center;
                align-items: center;
                z-index: 1000;
            }

            #difficulty-modal .modal-content {
                background: #fff;
                padding: 2rem;
                border-radius: 1rem;
                width: 90%;
                max-width: 500px;
                animation: fadeIn 0.3s ease-in-out;
                position: relative;
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            }

            @keyframes fadeIn {
                from { transform: scale(0.9); opacity: 0; }
                to { transform: scale(1); opacity: 1; }
            }

            /* 難度選擇 Modal Header 區塊 */
            .difficulty-modal-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                margin-bottom: 2.2rem;
                position: relative;
            }

            /* 返回按鈕樣式 (.back-btn) */
            .back-btn {
                display: flex;
                flex-direction: column;
                align-items: center;
                text-decoration: none;
                color: #222;
                font-size: 18px;
                font-weight: 500;
                background: none;
                border: none;
                cursor: pointer;
                padding: 0;
                gap: 0;
            }
            .back-btn span {
                font-size: 15px;
                margin-top: -2px;
            }

            /* 標題樣式 */
            .difficulty-title {
                flex: 1;
                text-align: center;
                font-size: 2rem;
                font-weight: bold;
                color: #222;
                letter-spacing: 1px;
            }

            /* 說明按鈕樣式 (.help-btn) */
            .help-btn {
                display: flex;
                flex-direction: column;
                align-items: center;
                background: none;
                border: none;
                cursor: pointer;
                color: #222;
                font-size: 18px;
                font-weight: 500;
                gap: 0;
                padding: 0;
            }
            .help-btn span {
                font-size: 15px;
                margin-top: -2px;
            }

            /* 難度按鈕群組 */
            .difficulty-btn-group {
                display: flex;
                flex-direction: column;
                gap: 28px;
                margin-top: 18px;
            }

            /* 個別難度按鈕樣式 */
            .difficulty-btn {
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                border: none;
                border-radius: 20px;
                font-size: 1.6rem;
                font-weight: bold;
                padding: 28px 0 18px 0;
                cursor: pointer;
                transition: transform 0.1s, box-shadow 0.2s;
                box-shadow: 0 2px 10px rgba(0,0,0,0.07);
                width: 100%;
                max-width: 420px;
                margin: 0 auto;
                position: relative;
                z-index: 1000;
                pointer-events: auto;
            }

            .difficulty-btn .diff-main {
                font-size: 1.5rem;
                font-weight: bold;
                margin-bottom: 8px;
            }
            .difficulty-btn .diff-sub {
                font-size: 1.1rem;
                font-weight: 500;
            }

            /* 顏色主題與懸停效果 */
            .easy-mode {
                background: #7bc96f;
                color: #222;
            }
            .easy-mode:hover {
                background: #6bb85e;
            }

            .normal-mode {
                background: #ffe066;
                color: #222;
            }
            .normal-mode:hover {
                background: #ffd23b;
            }

            .hard-mode {
                background: #f25f5c;
                color: #000000;
            }
            .hard-mode:hover {
                background: #d94340;
            }

            /* 手機板響應式調整 */
            @media (max-width: 600px) {
                #difficulty-modal .modal-content {
                    padding: 1.2rem;
                    border-radius: 0.8rem;
                    max-width: 96vw;
                }
                .difficulty-btn {
                    font-size: 1.1rem;
                    padding: 18px 0 12px 0;
                }
                .difficulty-title {
                    font-size: 1.3rem;
                }
            }
            
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
        </style>
        <script src="js/unified-game-tracker.js"></script>
    <script>
        // 初始化遊戲追蹤器
        document.addEventListener("DOMContentLoaded", function() {
            gameTracker.init("記憶力", 8);
        });
        
        // 返回主頁按鈕處理
        function handleBackButton() {
            // 智能返回：回到上一頁，如果沒有上一頁則回到遊戲分類頁面
            if (document.referrer && document.referrer !== window.location.href) {
                history.back();
            } else {
                window.location.href = 'game-category.php';
            }
        }
    </script>
</head>
    <body>
        <div id="difficulty-modal">
            <div class="modal-content" style="padding: 2.5rem 2rem 2rem 2rem;">
                <div class="difficulty-modal-header">
                    <a href="javascript:void(0)" onclick="handleBackButton()" class="back-btn">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="11" stroke="#222" stroke-width="2"/><polyline points="13 8 9 12 13 16" stroke="#222" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/><line x1="9" y1="12" x2="17" y2="12" stroke="#222" stroke-width="2" stroke-linecap="round"/></svg>
                        <span>返回</span>
                    </a>
                    <div class="difficulty-title">選擇難度</div>
                    <button class="help-btn" type="button" id="show-help">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="11" stroke="#222" stroke-width="2"/><text x="12" y="18" text-anchor="middle" font-size="18" fill="#222" font-family="Arial" dy="0">?</text></svg>
                        <span>說明</span>
                    </button>
                </div>
                <div class="difficulty-btn-group">
                    <button class="difficulty-btn easy-mode" data-difficulty="easy">
                        <div class="diff-main">簡單模式（基本圖片觀察）</div>
                        <div class="diff-sub">5題，目標：3題正確</div>
                    </button>
                    <button class="difficulty-btn normal-mode" data-difficulty="normal">
                        <div class="diff-main">普通模式（細節圖片觀察）</div>
                        <div class="diff-sub">5題，目標：3題正確</div>
                    </button>
                    <button class="difficulty-btn hard-mode" data-difficulty="hard">
                        <div class="diff-main">困難模式（複雜圖片觀察）</div>
                        <div class="diff-sub">5題，目標：3題正確</div>
                    </button>
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
                        <div id="clue-instruction-text" class="game-instruction-text">
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
                console.log('綁定按鈕事件:', btn);
                btn.onclick = function() {
                    const diff = this.getAttribute('data-difficulty');
                    console.log('點擊難度按鈕:', diff);
                    // 開始新遊戲，重置session
                    fetch(window.location.pathname, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'ajax=1&action=start_game&difficulty=' + encodeURIComponent(diff)
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            window.location.href = window.location.pathname + '?difficulty=' + encodeURIComponent(diff);
                        } else {
                            console.error('開始遊戲失敗:', data.message);
                    window.location.href = window.location.pathname + '?difficulty=' + encodeURIComponent(diff);
                        }
                    })
                    .catch(error => {
                        console.error('開始遊戲錯誤:', error);
                    window.location.href = window.location.pathname + '?difficulty=' + encodeURIComponent(diff);
                    });
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
if (!isset($_SESSION['clue_total'])) $_SESSION['clue_total'] = 0;
if (!isset($_SESSION['clue_correct'])) $_SESSION['clue_correct'] = 0;
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
    <link rel="stylesheet" href="css/clue.css?v=<?php echo time(); ?>">
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
    <script src="js/unified-game-tracker.js"></script>
    <script>
        // 初始化遊戲追蹤器
        document.addEventListener("DOMContentLoaded", function() {
            gameTracker.init("記憶力", 8);
            
            // 倒數計時器邏輯
            const timeLeftElement = document.getElementById('time-left');
            const questionBlock = document.getElementById('question-block');
            const imageBlock = document.getElementById('image-block');
            let gameTimer = null;
            
            if (timeLeftElement) {
                let timeLeft = parseInt(timeLeftElement.textContent);
                gameTimer = setInterval(function() {
                    timeLeft--;
                    timeLeftElement.textContent = timeLeft;
                    
                    if (timeLeft <= 0) {
                        clearInterval(gameTimer);
                        // 時間到，顯示問題
                        imageBlock.style.display = 'none';
                        questionBlock.style.display = 'block';
                    }
                }, 1000);
            }
            
            // 選項按鈕事件處理
            document.querySelectorAll('.option-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const selectedValue = this.getAttribute('data-value');
                    const correctAnswer = document.querySelector('.main-container').getAttribute('data-correct-answer');
                    const difficulty = new URLSearchParams(window.location.search).get('difficulty');
                    console.log('選擇答案:', selectedValue);
                    console.log('正確答案:', correctAnswer);
                    console.log('難度:', difficulty);
                    
                    // 顯示答案結果
                    const isCorrect = selectedValue === correctAnswer;
                    const resultMsg = document.getElementById('result-msg');
                    const correctAnswerSpan = document.getElementById('correct-answer');
                    
                    if (resultMsg) {
                        resultMsg.textContent = isCorrect ? '答對了！' : '答錯了！';
                        resultMsg.style.color = isCorrect ? '#4CAF50' : '#f44336';
                    }
                    
                    if (correctAnswerSpan) {
                        correctAnswerSpan.textContent = correctAnswer;
                    }
                    
                    // 更新計數器 - 使用data-initial屬性確保正確的初始值
                    const correctCountEl = document.getElementById('correct-count');
                    const remainingCountEl = document.getElementById('remaining-count');
                    
                    if (correctCountEl && remainingCountEl) {
                        // 從當前 DOM 元素獲取值，而不是重新計算
                        let currentCorrect = parseInt(correctCountEl.getAttribute('data-initial')) || parseInt(correctCountEl.textContent) || 0;
                        let currentTotal = parseInt(remainingCountEl.getAttribute('data-initial')) || parseInt(remainingCountEl.textContent) || 5;
                        currentTotal = 5 - currentTotal; // 轉換為已答題數
                        
                        if (isCorrect) {
                            currentCorrect++;
                        }
                        currentTotal++; // 總題數加1
                        
                        // 剩餘題數 = 總題數(5) - 當前總題數
                        let currentRemaining = Math.max(0, 5 - currentTotal);
                        
                        correctCountEl.textContent = currentCorrect;
                        remainingCountEl.textContent = currentRemaining;
                        
                        // 更新data-initial屬性以保持同步
                        correctCountEl.setAttribute('data-initial', currentCorrect);
                        remainingCountEl.setAttribute('data-initial', currentRemaining);
                        
                        console.log('更新計數器 - 答對:', currentCorrect, '總題數:', currentTotal, '剩餘:', currentRemaining);
                        console.log('Session數據 - 總題數:', <?= $_SESSION['clue_total'] ?? 0 ?>, '答對數:', <?= $_SESSION['clue_correct'] ?? 0 ?>);
                        
                        // 通知全局變數更新
                        if (typeof window !== 'undefined') {
                            window.clueCorrect = currentCorrect;
                            window.clueTotal = currentTotal;
                        }
                    }
                    
                    // 顯示結果區塊
                    const resultBlock = document.getElementById('result-block');
                    if (resultBlock) {
                        resultBlock.style.display = 'block';
                    }
                    
                    // 隱藏選項區塊
                    const questionBlock = document.getElementById('question-block');
                    if (questionBlock) {
                        questionBlock.style.display = 'none';
                    }
                    
                    // 使用 AJAX 提交答案，而不是表單提交
                    if (typeof loadQuestion === 'function') {
                        // 延遲後載入下一題（讓 js/clue.js 處理）
                        setTimeout(function() {
                            loadQuestion(selectedValue);
                        }, 1200);
                    }
                });
            });
            
            // 控制按鈕事件處理
            const pauseBtn = document.getElementById('pauseBtn');
            const endBtn = document.getElementById('endBtn');
            const resetBtn = document.getElementById('resetBtn');
            
            if (pauseBtn) {
                let isPaused = false;
                
                pauseBtn.addEventListener('click', function() {
                    if (!isPaused) {
                        // 暫停遊戲
                        isPaused = true;
                        pauseBtn.textContent = '繼續遊戲';
                        pauseBtn.style.setProperty('background-color', '#28a745', 'important'); // 綠色
                        
                        // 暫停倒數計時器
                        if (gameTimer) {
                            clearInterval(gameTimer);
                        }
                        
                        // 隱藏圖片和問題
                        if (imageBlock) imageBlock.style.display = 'none';
                        if (questionBlock) questionBlock.style.display = 'none';
                        
                        console.log('遊戲已暫停');
                    } else {
                        // 繼續遊戲
                        isPaused = false;
                        pauseBtn.textContent = '暫停遊戲';
                        pauseBtn.style.setProperty('background-color', '#ffa500', 'important'); // 橘色
                        
                        // 重新顯示圖片
                        if (imageBlock) imageBlock.style.display = 'block';
                        
                        // 恢復倒數計時器
                        if (timeLeftElement) {
                            let timeLeft = parseInt(timeLeftElement.textContent);
                            gameTimer = setInterval(function() {
                                timeLeft--;
                                timeLeftElement.textContent = timeLeft;
                                
                                if (timeLeft <= 0) {
                                    clearInterval(gameTimer);
                                    // 時間到，顯示問題
                                    if (imageBlock) imageBlock.style.display = 'none';
                                    if (questionBlock) questionBlock.style.display = 'block';
                                }
                            }, 1000);
                        }
                        
                        console.log('遊戲已繼續');
                    }
                });
            }
            
            if (endBtn) {
                endBtn.addEventListener('click', function() {
                    console.log('結束遊戲');
                    // 先保存遊戲記錄到API，然後顯示結果頁面
                    const difficulty = new URLSearchParams(window.location.search).get('difficulty');
                    const currentScore = parseInt(document.getElementById('correct-count').textContent) || 0;
                    const playTime = Math.floor((Date.now() - window.gameStartTime) / 1000) || 0;
                    
                    // 準備遊戲數據
                    const gameData = {
                        member_id: window.memberId || null,
                        game_type: '記憶力',
                        game_id: 8,
                        difficulty: difficulty,
                        score: currentScore >= 3 ? 50 : 0, // 根據是否過關給分
                        play_time: playTime,
                        is_manual_exit: false, // 🔑 改為 false，這是正常結束
                        is_passed: currentScore >= 3
                    };
                    
                    console.log('準備保存的遊戲數據:', gameData);
                    
                    // 調用API保存遊戲記錄
                    fetch('api/game_result.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify(gameData)
                    })
                    .then(response => response.json())
                    .then(data => {
                        console.log('遊戲記錄保存結果:', data);
                        
                        // 🔑 關鍵：停止遊戲追蹤，防止重複記錄
                        if (typeof gameExitHandler !== 'undefined') {
                            gameExitHandler.endGame();
                            console.log('✅ 遊戲追蹤已停止，防止重複記錄');
                        }
                        
                        // 不顯示結果頁面，讓 js/clue.js 處理結果顯示
                    })
                    .catch(error => {
                        console.error('保存遊戲記錄失敗:', error);
                        
                        // 🔑 即使失敗也要停止追蹤
                        if (typeof gameExitHandler !== 'undefined') {
                            gameExitHandler.endGame();
                            console.log('⚠️ 保存失敗，但已停止追蹤以防重複');
                        }
                        
                        // 不顯示結果頁面，讓 js/clue.js 處理結果顯示
                    });
                });
            }
            
            if (resetBtn) {
                resetBtn.addEventListener('click', function() {
                    console.log('重新開始');
                    // 發送AJAX請求重置遊戲
                    fetch('clue.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'ajax=1&action=start_game'
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // 重置成功，跳轉到難度選擇頁面
                            window.location.href = 'clue.php';
                        } else {
                            console.error('重置遊戲失敗:', data.message);
                            // 即使重置失敗也跳轉
                            window.location.href = 'clue.php';
                        }
                    })
                    .catch(error => {
                        console.error('重置遊戲請求失敗:', error);
                        // 即使請求失敗也跳轉
                        window.location.href = 'clue.php';
                    });
                });
            }
            
            // 顯示結果頁面的函數 - 改為直接顯示結果，不跳轉
            function showResultPage(pass, score, difficulty, pass_bounce) {
                // 創建結果頁面HTML並替換當前頁面內容
                const resultHtml = `
                    <div class="result-container" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); display: flex; justify-content: center; align-items: center; z-index: 9999;">
                        <div class="modal-content" style="background: white; padding: 2rem; border-radius: 12px; text-align: center; max-width: 400px;">
                            <h2>${pass ? '🎉恭喜破關' : '⏰遊戲結束'}</h2>
                            <div class="result-info">
                                <p><strong>答對題數：</strong>${score}/5</p>
                                <p><strong>過關條件：</strong>3題</p>
                                ${!pass ? '<p>未達成目標分數!</p>' : ''}
                                ${pass ? `<p><strong>獲得分數：+</strong>${pass_bounce}分</p>` : ''}
                            </div>
                            <div class="result-btns">
                                <button onclick="restartGame()" style="margin: 0.5rem; padding: 0.5rem 1rem; background: #F91519; color: white; border: none; border-radius: 4px; cursor: pointer;">再玩一次</button>
                                <button onclick="handleBackButton()" style="margin: 0.5rem; padding: 0.5rem 1rem; background: #1D3557; color: white; border: none; border-radius: 4px; cursor: pointer;">返回主頁</button>
                            </div>
                        </div>
                    </div>
                `;
                document.body.innerHTML = resultHtml;
            }
        });
        
        // 記錄遊戲開始時間
        window.gameStartTime = Date.now();
        window.memberId = <?= json_encode($_SESSION['member_id'] ?? null) ?>;
    </script>
</head>
<body>
    <div class="main-container"
         data-display-time="<?= (int)$question['display_time'] ?>"
         data-correct-answer="<?= htmlspecialchars($question['correct_answer_text']) ?>">
        <h2>請仔細觀察下方圖片，<?= (int)$question['display_time'] ?>秒後將進行提問！</h2>
        
        <!-- 答題狀況狀態欄 - 放在標題下方 -->
        <div class="status-bar">
            <div class="status-item">
                <span class="status-label">答對題數 :</span>
                <span class="status-value correct-count" id="correct-count" data-initial="<?= $_SESSION['clue_correct'] ?? 0 ?>"><?= $_SESSION['clue_correct'] ?? 0 ?></span>
            </div>
            <div class="status-item">
                <span class="status-label">過關題數 :</span>
                <span class="status-value pass-count" id="pass-count">3</span>
            </div>
            <div class="status-item">
                <span class="status-label">剩餘題數 :</span>
                <span class="status-value remaining-count" id="remaining-count" data-initial="<?= max(0, 5 - ($_SESSION['clue_total'] ?? 0)) ?>"><?= max(0, 5 - ($_SESSION['clue_total'] ?? 0)) ?></span>
            </div>
        </div>
        
        <!-- 調試信息 -->
        <div style="display: none;" id="debug-info">
            <p>Session總題數: <?= $_SESSION['clue_total'] ?? 0 ?></p>
            <p>Session答對數: <?= $_SESSION['clue_correct'] ?? 0 ?></p>
        </div>
        
        
        <div id="timer-block">
            <div id="countdown">剩餘時間：<span id="time-left"><?= (int)$question['display_time'] ?></span> 秒</div>
        </div>
        <div id="image-block">
            <img src="<?= htmlspecialchars($image_path) ?>" alt="題目圖片">
        </div>
        <div id="question-block">
            <h3><?= htmlspecialchars($question['question_text']) ?></h3>
            <form id="answer-form" class="options-grid" method="POST" action="clue.php">
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
    <script src="js/game-exit-handler.js"></script>
    <script src="js/game-common.js"></script>
    <script src="js/clue.js"></script>
    <script src="js/auto-save-time-fixed.js"></script>
    <script>
        // 配置遊戲退出處理器
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof gameExitHandler !== 'undefined') {
                gameExitHandler.updateConfig({
                    memberId: <?= $_SESSION['member_id'] ?? 'null' ?>,
                    gameType: '記憶力',
                    gameId: 8,
                    difficulty: '<?= $difficulty ?? "easy" ?>'
                });
                console.log('✅ 遊戲退出處理器已配置');
            }
        });
        
        // 返回主頁按鈕處理
        function handleBackButton() {
            // 智能返回：回到上一頁，如果沒有上一頁則回到遊戲分類頁面
            if (document.referrer && document.referrer !== window.location.href) {
                history.back();
            } else {
                window.location.href = 'game-category.php';
            }
        }
    </script>
    <script src="js/get-score.js"></script>
</body>
</html> 