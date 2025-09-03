<?php
require_once 'db_connect.php';

// 處理取得食材資料的 API 請求
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['get_ingredients'])) {
    header('Content-Type: application/json');
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM vegetable_cost_ingredients ORDER BY category, name");
        $stmt->execute();
        $ingredients = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($ingredients);
        exit;
    } catch (Exception $e) {
        echo json_encode(['error' => '取得食材資料失敗：' . $e->getMessage()]);
        exit;
    }
}

// 處理遊戲結果保存的 API 請求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);
   
    try {
        // 開始交易
        $pdo->beginTransaction();
       
        $gameId = 3; // 算菜錢遊戲的 ID
       
        $stmt = $pdo->prepare("
            INSERT INTO game_records
            (member_id, game_id, difficulty, score, play_date, play_time, game_type, is_single_player, opponent_id)
            VALUES (:member_id, :game_id, :difficulty, :score, NOW(), :play_time, :game_type, :is_single_player, :opponent_id)
        ");
        $stmt->execute([
            'member_id' => $data['member_id'],
            'game_id' => $gameId,
            'difficulty' => $data['difficulty'],
            'score' => $data['score'],
            'play_time' => isset($data['play_time']) ? $data['play_time'] : null,
            'game_type' => '算數邏輯力',
            'is_single_player' => 1,
            'opponent_id' => null
        ]);
       
        // 更新會員總分數和邏輯力分數
        $update_stmt = $pdo->prepare("UPDATE member SET total_score = total_score + :score, logic_score = logic_score + :score WHERE member_id = :member_id");
        $update_stmt->execute([
            'score' => $data['score'],
            'member_id' => $data['member_id']
        ]);
       
        // 提交交易
        $pdo->commit();
       
        echo json_encode(['success' => true, 'message' => '遊戲結果已儲存']);
        exit;
    } catch (Exception $e) {
        // 如果發生錯誤，回滾交易
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => '儲存失敗：' . $e->getMessage()]);
        exit;
    }
}

// 檢查 session 中的 member_id
session_start();
if (!isset($_SESSION['member_id'])) {
    // 如果沒有登入，重定向到登入頁面
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>算菜錢遊戲</title>
    <link rel="stylesheet" href="css/Vegetable-Cost.css">
</head>
<body>
    <input type="hidden" id="member-id" value="<?php echo $_SESSION['member_id']; ?>">


    <div class="game-container" style="display:none;">
        <h1 class="main-title">算菜錢遊戲</h1>
        <div class="score-board new-score-board">
            <div class="score-item">
                <span>目前分數：</span>
                <span id="score" class="score-green">0</span>
            </div>
            <div class="score-item">
                <span>最高分數：</span>
                <span id="high-score" class="score-green">0</span>
            </div>
            <div class="score-item">
                <span>剩餘時間：</span>
                <span id="timer" class="score-red">60</span>
            </div>
        </div>
        <div id="question-container" class="main-question-container">
            <div id="question"></div>
            <div id="options-container" class="main-options-container"></div>
        </div>
        <div class="main-control-btns">
            <button id="pause-btn" class="main-btn pause-btn">暫停遊戲</button>
            <button id="end-btn" class="main-btn end-btn" onclick="endGame()">結束遊戲</button>
            <button id="restart-btn" class="main-btn restart-btn" onclick="restartGame()">重新開始</button>
        </div>
    </div>

    <!-- 遊戲結束視窗 -->
    <div id="game-over-modal" class="modal hidden">
        <div class="modal-content gameover-modal-content">
            <h2 class="gameover-title">遊戲結束</h2>
            <div class="gameover-desc">
                <p class="gameover-msg">難度：困難<br>未在時間內達成分數</p>
            </div>
            <div class="gameover-btn-group">
                <button id="modal-restart-btn" class="gameover-btn retry-btn" onclick="restartGame()">再玩一次</button>
                <button id="exit-btn" class="gameover-btn home-btn" onclick="exitGame()">返回主頁</button>
            </div>
        </div>
    </div>

    <!-- 難度選擇視窗 -->
    <div id="difficulty-modal" class="modal">
        <div class="modal-content" style="padding: 2.5rem 2rem 2rem 2rem;">
            <div class="difficulty-modal-header">
                <a href="index.php" class="back-btn">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="11" stroke="#222" stroke-width="2"/><polyline points="13 8 9 12 13 16" stroke="#222" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/><line x1="9" y1="12" x2="17" y2="12" stroke="#222" stroke-width="2" stroke-linecap="round"/></svg>
                    <span>返回</span>
                </a>
                <div class="difficulty-title">選擇難度</div>
                <button class="help-btn" type="button">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="11" stroke="#222" stroke-width="2"/><text x="12" y="18" text-anchor="middle" font-size="18" fill="#222" font-family="Arial" dy="0">?</text></svg>
                    <span>說明</span>
                </button>
            </div>
            <div class="difficulty-btn-group">
                <button class="difficulty-btn easy-mode" data-difficulty="easy">
                    <div class="diff-main">簡單模式（簡單加減計算）</div>
                    <div class="diff-sub">80秒，目標：20分</div>
                </button>
                <button class="difficulty-btn normal-mode" data-difficulty="normal">
                    <div class="diff-main">普通模式（促銷優惠計算）</div>
                    <div class="diff-sub">150秒，目標：30分</div>
                </button>
                <button class="difficulty-btn hard-mode" data-difficulty="hard">
                    <div class="diff-main">困難模式（複雜組合計算）</div>
                    <div class="diff-sub">200秒，目標：50分</div>
                </button>
            </div>
        </div>
    </div>

    <!-- 遊戲說明視窗 -->
    <div id="help-modal" class="modal hidden">
        <div class="modal-content">
            <h2 style="text-align:center;">
                <span style="font-size:2rem;vertical-align:middle;">🎮</span>
                <span style="font-weight:bold;vertical-align:middle;">遊戲說明</span>
            </h2>
            <div class="help-content" style="margin-top:2.5rem;padding:0 1rem;">
                <!-- 影片播放區域 -->
                <div id="vegetable-video-container" style="text-align:center;margin-bottom:2.5rem;">
                    <video id="vegetable-current-video" width="100%" height="auto" controls style="max-width:900px;width:95%;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,0.15);">
                        <source src="gd/vegetable1.mp4" type="video/mp4">
                        您的瀏覽器不支援視頻播放。
                    </video>
                </div>
                
                <!-- 說明文字和按鈕區域 (並排顯示) -->
                <div style="display:flex;justify-content:center;align-items:center;margin:0 1rem;margin-bottom:2rem; gap: 20px;">
                    <!-- 上一步按鈕 -->
                    <div id="vegetable-prev-step-btn" style="display:none;">
                        <button id="vegetable-prev-step-button" onclick="goToVegetablePrevStep()" class="game-step-button prev-step" style="padding:16px 32px;font-size:22px;">
                            上一步
                        </button>
                    </div>
                    
                    <!-- 說明文字 -->
                    <div id="vegetable-instruction-text" class="game-instruction-text" style="font-size:26px;flex:3;text-align:center;min-width:300px;">
                        計算阿嬤買菜的總金額
                    </div>
                    
                    <!-- 下一步按鈕 -->
                    <div id="vegetable-next-step-btn" style="margin-left:2rem;">
                        <button id="vegetable-next-step-button" class="game-step-button next-step" style="padding:16px 32px;font-size:22px;">
                            下一步
                        </button>
                    </div>
                </div>
                
                <!-- 進度指示器 -->
                <div style="text-align:center;margin-top:1.5rem;margin-bottom:1.5rem;">
                    <span id="vegetable-step-indicator" class="game-step-indicator" style="font-size:20px;">步驟 1/2</span>
                </div>
            </div>
            <span class="close-btn">×</span>
        </div>
    </div>

    <script>
        // 將PHP變數傳遞給JavaScript
        window.phpMemberId = <?php echo $_SESSION['member_id']; ?>;
    </script>
    <script src="js/Vegetable-Cost.js"></script>
</body>
</html> 