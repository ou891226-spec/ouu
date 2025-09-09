<?php
session_start();
require_once 'db_connect.php';

// 檢查用戶是否已登入
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? '玩家';
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>狼羊菜過河遊戲 - <?php echo htmlspecialchars($username); ?></title>
    <link rel="stylesheet" href="css/river.style.css">
    <link rel="stylesheet" href="css/main.css">
</head>
<body>


    <!-- 開始畫面 -->
    <div id="start-screen" class="screen active">
        <div class="help-button">
            <button id="help-btn" class="help-btn">❓</button>
        </div>
        <div class="start-container">
            <h1 class="game-title">🐺🐑🥬 狼羊菜過河遊戲</h1>
            <div class="game-intro">
                <p>經典邏輯謎題遊戲，考驗您的智慧與策略！</p>
                <p>將所有物品安全運送到對岸，避免違反遊戲規則。</p>
                <p><strong>玩家：<?php echo htmlspecialchars($username); ?></strong></p>
            </div>
            <div class="start-buttons">
                <button id="start-game-btn" class="btn-primary">開始遊戲</button>
            </div>
        </div>
    </div>

    <!-- 難度選擇畫面 -->
    <div id="difficulty-screen" class="screen">
        <div class="difficulty-overlay">
            <div class="difficulty-dialog">
                <h2 class="dialog-title">選擇難度</h2>
                <div class="difficulty-options">
                    <div class="difficulty-option" data-difficulty="easy">
                        <span class="option-name">簡單模式</span>
                    </div>
                    
                    <div class="difficulty-option" data-difficulty="normal">
                        <span class="option-name">普通模式</span>
                    </div>
                    
                    <div class="difficulty-option" data-difficulty="hard">
                        <span class="option-name">困難模式</span>
                    </div>
                </div>
                <div class="dialog-buttons">
                    <button id="back-to-start" class="btn-secondary">返回主頁</button>
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
                <button id="back-from-rules" class="btn-secondary">返回主頁</button>
                <button id="go-to-difficulty" class="btn-primary">選擇難度</button>
            </div>
        </div>
    </div>

    <!-- 遊戲畫面 -->
    <div id="game-screen" class="screen">
        <div class="game-header">
            <button id="back-to-difficulty" class="btn-small">← 返回選單</button>
            <h2 id="current-difficulty">簡單模式</h2>
            <button id="show-hint-btn" class="btn-small">💡 提示</button>
        </div>

        <div id="controls">
            <div id="game-info">
                <span id="step-count">步數: 0</span>
                <span id="score">分數: 0</span>
                <span id="boat-capacity">船容量: 1</span>
                <span id="boat-position">船位置: 左岸</span>
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
            <h2 class="modal-title">恭喜破關</h2>
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

    <!-- 隱藏的用戶ID和CSRF token -->
    <input type="hidden" id="user-id" value="<?php echo $user_id; ?>">
    <input type="hidden" id="csrf-token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">

    <script src="js/river.script.js"></script>
    <script src="js/auto-save-time-fixed.js"></script>
    <script>
        // 將PHP變數傳遞給JavaScript
        window.gameConfig = {
            userId: <?php echo $user_id; ?>,
            username: '<?php echo addslashes($username); ?>',
            csrfToken: '<?php echo $_SESSION['csrf_token'] ?? ''; ?>'
        };
    </script>
</body>
</html>
