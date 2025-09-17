<?php
session_start();
require_once 'db_connect.php';

// 從資料庫讀取過河遊戲的難度設定
$difficultySettings = [];
try {
    $stmt = $pdo->query("SELECT * FROM difficulty_settings WHERE game_id = 9 ORDER BY difficulty");
    $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($settings as $setting) {
        $difficultySettings[$setting['difficulty']] = [
            'time_limit' => $setting['time_limit'],
            'points_per_correct' => $setting['points_per_correct'],
            'pass_score' => $setting['pass_bounce'] // 使用 pass_bounce 欄位
        ];
    }
} catch (PDOException $e) {
    // 如果查詢失敗，使用預設設定
    $difficultySettings = [
        'easy' => ['time_limit' => 0, 'points_per_correct' => 0, 'pass_score' => 20],
        'normal' => ['time_limit' => 0, 'points_per_correct' => 0, 'pass_score' => 50],
        'hard' => ['time_limit' => 0, 'points_per_correct' => 0, 'pass_score' => 100]
    ];
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>狼羊菜過河遊戲</title>
    <link rel="stylesheet" href="css/river.style.css">
</head>
<body>
    <!-- 開始畫面 -->
    <div id="start-screen" class="screen">
        <div class="help-button help-button-top-left">
            <button id="help-btn" class="help-btn">❓</button>
        </div>
        <div class="start-container">
            <h1 class="game-title">🐺🐑🥬 狼羊菜過河遊戲</h1>
            <div class="game-intro">
                <p>經典邏輯謎題遊戲，考驗您的智慧與策略！</p>
                <p>將所有物品安全運送到對岸，避免違反遊戲規則。</p>
            </div>
            <div class="start-buttons">
                <button id="start-game-btn" class="btn-primary">開始遊戲</button>
            </div>
        </div>
    </div>

    <!-- 難度選擇畫面 -->
    <div id="difficulty-screen" class="screen active">
        <div class="difficulty-container">
            <div class="difficulty-header">
                <button id="back-to-start" class="back-button">
                    <span class="back-arrow">↶</span>
                    <div class="back-label">返回</div>
                </button>
                <h2 class="difficulty-title">難度選擇</h2>
                <button id="help-from-difficulty" class="help-button">
                    <span class="help-icon">?</span>
                    <div class="help-label">說明</div>
                </button>
            </div>
            <div class="difficulty-options">
                <div class="difficulty-option easy-option" data-difficulty="easy">
                    <span class="option-name">簡單</span>
                </div>
                
                <div class="difficulty-option normal-option" data-difficulty="normal">
                    <span class="option-name">普通</span>
                </div>
                
                <div class="difficulty-option hard-option" data-difficulty="hard">
                    <span class="option-name">困難</span>
                </div>
            </div>
        </div>
    </div>

    <!-- 遊戲規則畫面 -->
    <div id="rules-screen" class="screen">
        <div class="rules-page">
            <div class="rules-header">
                <h1>📖 遊戲規則說明</h1>
                <p class="rules-subtitle">了解遊戲玩法與策略</p>
            </div>
            
            <div class="rules-sections">
                <div class="rule-section">
                    <div class="section-icon">🎯</div>
                    <div class="section-content">
                        <h3>遊戲目標</h3>
                        <p>將所有物品從左岸安全運送到右岸，避免任何物品被吃掉。</p>
                    </div>
                </div>

                <div class="rule-section">
                    <div class="section-icon">🎮</div>
                    <div class="section-content">
                        <h3>基本操作</h3>
                        <ul>
                            <li><strong>選擇物品：</strong>點擊岸上的物品來選擇要運送的物品</li>
                            <li><strong>移動船：</strong>點擊船來移動到對岸</li>
                            <li><strong>重新開始：</strong>點擊「重新開始」按鈕重置遊戲</li>
                        </ul>
                    </div>
                </div>

                <div class="rule-section">
                    <div class="section-icon">⚠️</div>
                    <div class="section-content">
                        <h3>違規情況</h3>
                        <div class="violation-rules">
                            <div class="violation-item">
                                <span class="violation-emoji">🐺 + 🐑</span>
                                <span class="violation-text">狼和羊單獨在同一岸 → 羊被吃</span>
                            </div>
                            <div class="violation-item">
                                <span class="violation-emoji">🐑 + 🥬</span>
                                <span class="violation-text">羊和菜單獨在同一岸 → 菜被吃</span>
                            </div>
                            <div class="violation-item">
                                <span class="violation-emoji">🐺 + 🐕</span>
                                <span class="violation-text">狼和狗單獨在同一岸 → 狼被咬（普通/困難模式）</span>
                            </div>
                            <div class="violation-item">
                                <span class="violation-emoji">🦊 + 🥬</span>
                                <span class="violation-text">狐狸和菜單獨在同一岸 → 菜被偷（困難模式）</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rule-section">
                    <div class="section-icon">🎉</div>
                    <div class="section-content">
                        <h3>勝利條件</h3>
                        <p>所有物品安全到達右岸，沒有人或物品被吃掉。</p>
                    </div>
                </div>

                <div class="rule-section">
                    <div class="section-icon">🌦️</div>
                    <div class="section-content">
                        <h3>困難模式特殊事件</h3>
                        <div class="special-events">
                            <div class="event-item">
                                <span class="event-emoji">🌩️</span>
                                <div class="event-info">
                                    <strong>暴風雨：</strong>船被吹回上一步
                                </div>
                            </div>
                            <div class="event-item">
                                <span class="event-emoji">🔧</span>
                                <div class="event-info">
                                    <strong>船壞了：</strong>只能載一個物品
                                </div>
                            </div>
                            <div class="event-item">
                                <span class="event-emoji">🌀</span>
                                <div class="event-info">
                                    <strong>物品自己移動：</strong>隨機物品改變位置
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="rules-footer">
                <button id="back-from-rules" class="btn-secondary">返回主選單</button>
                <button id="watch-help-btn" class="btn-watch-help" onclick="showHelpModal()">🎮 觀看遊戲說明</button>
                <button id="go-to-difficulty" class="btn-primary">選擇難度</button>
            </div>
        </div>
    </div>

    <!-- 遊戲畫面 -->
    <div id="game-screen" class="screen">
        <div class="game-header">
            <button id="back-to-difficulty" class="btn-small">← 返回選單</button>
            <h2 id="current-difficulty">簡單模式</h2>
        </div>

        <div id="controls">
            <div id="game-info">
                <span id="step-count">步數: 0</span>
                <span id="boat-capacity">船容量: 1</span>
                <span id="boat-position">船位置: 左岸</span>
                <button id="show-hint-btn" class="btn-small">💡 提示</button>
            </div>
        </div>

        <div id="game">
            <div class="river-side" id="left-side">
                <h2>左岸</h2>
                <div class="items" id="left-items"></div>
                <div class="farmer" id="left-farmer">👨‍🌾</div>
            </div>
            <div class="river">
                <div id="boat">⛵</div>
                <div id="boat-items"></div>
            </div>
            <div class="river-side" id="right-side">
                <h2>右岸</h2>
                <div class="items" id="right-items"></div>
                <div class="farmer" id="right-farmer"></div>
            </div>
        </div>

        <p id="message"></p>
        <div id="weather-info"></div>
        
        <div class="game-instructions">
            <div class="instruction-item">1.點選物品上船</div>
            <div class="instruction-item">2.點選船隻移動</div>
            <div class="instruction-item">3.可空船移動</div>
        </div>
        
        <div class="game-controls">
            <button id="pauseBtn" class="game-control-btn">暫停遊戲</button>
            <button id="endGameBtn" class="game-control-btn">結束遊戲</button>
            <button id="resetBtn" class="game-control-btn">重新開始</button>
        </div>
    </div>

    <!-- 遊戲失敗彈出對話框 -->
    <div id="game-fail-modal" class="modal-overlay">
        <div class="modal-dialog">
            <h2 class="modal-title">遊戲失敗</h2>
            <div class="modal-content">
                <p class="modal-detail">難度: <span id="fail-difficulty">簡單</span></p>
                <p class="modal-detail">未在時間內達成分數</p>
            </div>
            <div class="modal-buttons">
                <button id="play-again-btn" class="modal-btn modal-btn-red">再玩一次</button>
                <button id="return-home-btn" class="modal-btn modal-btn-blue">返回主頁</button>
            </div>
        </div>
    </div>

    <!-- 遊戲成功彈出對話框 -->
    <div id="game-success-modal" class="modal-overlay">
        <div class="modal-dialog">
            <h2 class="modal-title">🎉 恭喜破關</h2>
            <div class="modal-content">
                <p class="modal-detail">難度: <span id="success-difficulty">簡單</span></p>
                <p class="modal-detail">獲得分數: <span id="success-score">0</span></p>
            </div>
            <div class="modal-buttons">
                <button id="play-again-success-btn" class="modal-btn modal-btn-red">再玩一次</button>
                <button id="return-home-success-btn" class="modal-btn modal-btn-blue">返回主頁</button>
            </div>
        </div>
    </div>

    <!-- 主題選擇視窗 -->

    <!-- 遊戲說明視窗 -->
    <div id="help-modal" class="modal hidden">
        <div class="modal-content">
            <h2 style="text-align:center;">
                <span style="font-size:2rem;vertical-align:middle;">🎮</span>
                <span style="font-weight:bold;vertical-align:middle;">遊戲說明</span>
            </h2>
            <div class="help-content" style="margin-top:2.5rem;padding:0 2rem;">
                <div id="video-container" style="text-align:center;margin-bottom:2.5rem;">
                    <video id="current-video" width="100%" height="auto" controls style="max-width:700px;width:80%;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,0.15);">
                        <source src="gd/river1.mp4" type="video/mp4">
                        您的瀏覽器不支援視頻播放。
                    </video>
                </div>
                
                <div style="display:flex;justify-content:center;align-items:center;margin:0 1rem;margin-bottom:2rem; gap: 20px;">
                    <div id="prev-step-btn">
                        <button id="prev-step-button" onclick="goToPrevStep()" class="game-step-button prev-step" style="display: none; border: none; border-radius: 12px; cursor: pointer; font-weight: bold; min-width: 120px; padding: 14px 28px; font-size: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); background: #f5f5f5; color: #666; border: 2px solid #ddd;">
                            上一步
                        </button>
                    </div>
                    
                    <div id="instruction-text" class="game-instruction-text" style="font-size:24px;text-align:center; min-width: 180px;">
                        選主題、選難度
                    </div>
                    
                    <div id="next-step-btn">
                        <button id="next-step-button" onclick="goToNextStep()" class="game-step-button next-step" style="display: block; border: none; border-radius: 12px; cursor: pointer; font-weight: bold; min-width: 120px; padding: 14px 28px; font-size: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); background: #2196F3; color: white; border: 2px solid #1976D2;">
                            下一步
                        </button>
                    </div>
                </div>
                
                <div style="text-align:center;margin-top:1.5rem;margin-bottom:1.5rem;">
                    <span id="step-indicator" class="game-step-indicator" style="font-size:18px;">步驟 1/2</span>
                </div>
            </div>
            <span class="close-btn" onclick="closeHelpModal()" style="position: absolute; top: 1rem; right: 1rem; font-size: 4rem; font-weight: 700; color: black; cursor: pointer; line-height: 1; z-index: 10; width: 60px; height: 60px; display: flex; align-items: center; justify-content: center; border-radius: 50%; background: transparent; user-select: none;">×</span>
        </div>
    </div>

    <script src="js/river.script.js"></script>
    
    <!-- 確保函數可用的備用定義 -->
    <script>
        // 確保函數在全局作用域中可用
        if (typeof showHelpModal === 'undefined') {
            function showHelpModal() {
                console.log("showHelpModal 函數被調用 (備用定義)");
                const helpModal = document.getElementById("help-modal");
                if (helpModal) {
                    console.log("找到 help-modal 元素，正在顯示");
                    helpModal.classList.remove("hidden");
                    helpModal.style.display = "flex";
                    
                    // 初始化視頻
                    const video = document.getElementById('current-video');
                    if (video) {
                        video.src = 'gd/river1.mp4';
                        video.setAttribute('data-current-video', 'river1');
                        video.load();
                    }
                    
                    // 更新UI
                    const instructionText = document.getElementById('instruction-text');
                    const stepIndicator = document.getElementById('step-indicator');
                    const prevStepButton = document.getElementById('prev-step-button');
                    const nextStepButton = document.getElementById('next-step-button');
                    
                    if (instructionText) instructionText.textContent = '選主題、選難度';
                    if (stepIndicator) stepIndicator.textContent = '步驟 1/2';
                    if (prevStepButton) prevStepButton.style.display = 'none';
                    if (nextStepButton) nextStepButton.style.display = 'block';
                } else {
                    console.error("找不到 help-modal 元素");
                }
            }
        }
        
        if (typeof closeHelpModal === 'undefined') {
            function closeHelpModal() {
                const helpModal = document.getElementById("help-modal");
                if (helpModal) {
                    // 停止影片播放
                    const video = document.getElementById('current-video');
                    if (video) {
                        video.pause();
                        video.currentTime = 0; // 重置到開始位置
                    }
                    
                    // 隱藏模態視窗
                    helpModal.classList.add("hidden");
                    helpModal.style.display = "none";
                }
            }
        }
        
        if (typeof goToNextStep === 'undefined') {
            function goToNextStep() {
                const video = document.getElementById('current-video');
                if (video) {
                    video.src = 'gd/river2.mp4';
                    video.setAttribute('data-current-video', 'river2');
                    video.load();
                    video.play();
                    
                    // 更新UI
                    const instructionText = document.getElementById('instruction-text');
                    const stepIndicator = document.getElementById('step-indicator');
                    const prevStepButton = document.getElementById('prev-step-button');
                    const nextStepButton = document.getElementById('next-step-button');
                    
                    if (instructionText) instructionText.textContent = '點擊岸上的物品運送，點擊船移動到對岸，將所有物品從左岸安全運送到右岸。';
                    if (stepIndicator) stepIndicator.textContent = '步驟 2/2';
                    if (prevStepButton) prevStepButton.style.display = 'block';
                    if (nextStepButton) nextStepButton.style.display = 'none';
                }
            }
        }
        
        if (typeof goToPrevStep === 'undefined') {
            function goToPrevStep() {
                const video = document.getElementById('current-video');
                if (video) {
                    video.src = 'gd/river1.mp4';
                    video.setAttribute('data-current-video', 'river1');
                    video.load();
                    video.play();
                    
                    // 更新UI
                    const instructionText = document.getElementById('instruction-text');
                    const stepIndicator = document.getElementById('step-indicator');
                    const prevStepButton = document.getElementById('prev-step-button');
                    const nextStepButton = document.getElementById('next-step-button');
                    
                    if (instructionText) instructionText.textContent = '選主題、選難度';
                    if (stepIndicator) stepIndicator.textContent = '步驟 1/2';
                    if (prevStepButton) prevStepButton.style.display = 'none';
                    if (nextStepButton) nextStepButton.style.display = 'block';
                }
            }
        }
        
        // 將PHP變數傳遞給JavaScript
        window.difficultySettings = <?php echo json_encode($difficultySettings); ?>;
        window.memberId = <?php echo isset($_SESSION['member_id']) ? $_SESSION['member_id'] : 'null'; ?>;
    </script>
</body>
</html>
