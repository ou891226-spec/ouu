<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>翻牌遊戲 HTTP 同步測試</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        
        .test-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .test-section {
            margin-bottom: 30px;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        
        .test-section h3 {
            margin-top: 0;
            color: #333;
        }
        
        .button {
            background: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            margin: 5px;
        }
        
        .button:hover {
            background: #0056b3;
        }
        
        .button:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        
        .input-group {
            margin: 10px 0;
        }
        
        .input-group label {
            display: inline-block;
            width: 120px;
            font-weight: bold;
        }
        
        .input-group input, .input-group select {
            padding: 5px;
            border: 1px solid #ddd;
            border-radius: 3px;
            width: 200px;
        }
        
        .log-container {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            max-height: 300px;
            overflow-y: auto;
            font-family: monospace;
            font-size: 12px;
        }
        
        .log-entry {
            margin: 2px 0;
            padding: 2px 0;
        }
        
        .log-success { color: #28a745; }
        .log-error { color: #dc3545; }
        .log-info { color: #17a2b8; }
        
        .status-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 5px;
        }
        
        .status-connected { background: #28a745; }
        .status-disconnected { background: #dc3545; }
        .status-connecting { background: #ffc107; }
    </style>
</head>
<body>
    <h1>翻牌遊戲 HTTP 同步測試</h1>
    
    <div class="test-container">
        <h2>連接設置</h2>
        <div class="test-section">
            <div class="input-group">
                <label>邀請 ID:</label>
                <input type="text" id="invitationId" placeholder="輸入邀請 ID">
            </div>
            <div class="input-group">
                <label>玩家 ID:</label>
                <input type="text" id="playerId" placeholder="輸入玩家 ID">
            </div>
            <div class="input-group">
                <label>困難度:</label>
                <select id="difficulty">
                    <option value="easy">簡單</option>
                    <option value="medium">中等</option>
                    <option value="hard">困難</option>
                </select>
            </div>
            <button class="button" onclick="initSync()">初始化同步</button>
            <button class="button" onclick="initGame()">初始化遊戲</button>
            <button class="button" onclick="stopSync()">停止同步</button>
        </div>
    </div>
    
    <div class="test-container">
        <h2>遊戲操作</h2>
        <div class="test-section">
            <div class="input-group">
                <label>卡片索引:</label>
                <input type="number" id="cardIndex" placeholder="0-23" min="0" max="23">
            </div>
            <div class="input-group">
                <label>卡片符號:</label>
                <input type="text" id="cardSymbol" placeholder="🍎">
            </div>
            <button class="button" onclick="flipCard()">翻牌</button>
            <button class="button" onclick="checkMatch()">檢查配對</button>
            <button class="button" onclick="getGameState()">獲取遊戲狀態</button>
        </div>
    </div>
    
    <div class="test-container">
        <h2>同步狀態</h2>
        <div class="test-section">
            <p>
                <span class="status-indicator" id="syncStatus"></span>
                <span id="syncStatusText">未連接</span>
            </p>
            <p>輪詢間隔: <span id="pollingInterval">1000ms</span></p>
            <p>最後同步時間: <span id="lastSyncTime">-</span></p>
        </div>
    </div>
    
    <div class="test-container">
        <h2>日誌</h2>
        <div class="log-container" id="logContainer">
            <div class="log-entry log-info">HTTP 同步測試頁面已載入</div>
        </div>
        <button class="button" onclick="clearLog()">清除日誌</button>
    </div>

    <script src="js/memory-game-http-sync.js"></script>
    <script>
        let httpSync = null;
        
        // 初始化同步
        function initSync() {
            const invitationId = document.getElementById('invitationId').value;
            const playerId = document.getElementById('playerId').value;
            
            if (!invitationId || !playerId) {
                log('請輸入邀請 ID 和玩家 ID', 'error');
                return;
            }
            
            if (!window.memoryGameHttpSync) {
                log('HTTP 同步系統未載入', 'error');
                return;
            }
            
            httpSync = window.memoryGameHttpSync;
            httpSync.init(invitationId, playerId);
            
            // 設置事件處理器
            httpSync.on('gameStateUpdate', function(data) {
                log(`遊戲狀態更新: 回合 ${data.currentPlayer}, 是否我的回合: ${data.isMyTurn}`, 'info');
                updateSyncStatus(true);
                updateLastSyncTime();
            });
            
            httpSync.on('cardFlipped', function(data) {
                log(`對手翻牌: 索引 ${data.cardIndex}, 符號 ${data.cardSymbol}`, 'info');
            });
            
            httpSync.on('matchSuccess', function(data) {
                log(`配對成功: 卡片 ${data.cardIndexes.join(', ')}`, 'success');
            });
            
            httpSync.on('matchFail', function(data) {
                log(`配對失敗: 卡片 ${data.cardIndexes.join(', ')}`, 'error');
            });
            
            httpSync.on('gameInitialized', function(data) {
                log('遊戲已初始化', 'success');
            });
            
            log(`同步初始化成功: 邀請ID ${invitationId}, 玩家ID ${playerId}`, 'success');
            updateSyncStatus(true);
        }
        
        // 初始化遊戲
        async function initGame() {
            if (!httpSync) {
                log('請先初始化同步', 'error');
                return;
            }
            
            const difficulty = document.getElementById('difficulty').value;
            const result = await httpSync.initGame(difficulty, 'fruit');
            
            if (result) {
                log(`遊戲初始化成功: 困難度 ${difficulty}`, 'success');
            } else {
                log('遊戲初始化失敗', 'error');
            }
        }
        
        // 翻牌
        async function flipCard() {
            if (!httpSync) {
                log('請先初始化同步', 'error');
                return;
            }
            
            const cardIndex = parseInt(document.getElementById('cardIndex').value);
            const cardSymbol = document.getElementById('cardSymbol').value;
            
            if (isNaN(cardIndex) || cardIndex < 0 || cardIndex > 23) {
                log('請輸入有效的卡片索引 (0-23)', 'error');
                return;
            }
            
            if (!cardSymbol) {
                log('請輸入卡片符號', 'error');
                return;
            }
            
            const result = await httpSync.flipCard(cardIndex, cardSymbol);
            
            if (result) {
                log(`翻牌成功: 索引 ${cardIndex}, 符號 ${cardSymbol}`, 'success');
            } else {
                log(`翻牌失敗: 索引 ${cardIndex}`, 'error');
            }
        }
        
        // 檢查配對
        async function checkMatch() {
            if (!httpSync) {
                log('請先初始化同步', 'error');
                return;
            }
            
            const card1Index = parseInt(prompt('請輸入第一張卡片索引:'));
            const card2Index = parseInt(prompt('請輸入第二張卡片索引:'));
            
            if (isNaN(card1Index) || isNaN(card2Index)) {
                log('請輸入有效的卡片索引', 'error');
                return;
            }
            
            const result = await httpSync.checkMatch(card1Index, card2Index);
            
            if (result) {
                log(`配對成功: 卡片 ${card1Index} 和 ${card2Index}`, 'success');
            } else {
                log(`配對失敗: 卡片 ${card1Index} 和 ${card2Index}`, 'error');
            }
        }
        
        // 獲取遊戲狀態
        async function getGameState() {
            if (!httpSync) {
                log('請先初始化同步', 'error');
                return;
            }
            
            const gameState = httpSync.getCurrentGameState();
            if (gameState) {
                log(`遊戲狀態: 回合 ${gameState.currentPlayer}, 配對數 ${gameState.matchedPairs}`, 'info');
                console.log('完整遊戲狀態:', gameState);
            } else {
                log('無法獲取遊戲狀態', 'error');
            }
        }
        
        // 停止同步
        function stopSync() {
            if (httpSync) {
                httpSync.stopPolling();
                httpSync = null;
                log('同步已停止', 'info');
                updateSyncStatus(false);
            }
        }
        
        // 更新同步狀態
        function updateSyncStatus(connected) {
            const statusIndicator = document.getElementById('syncStatus');
            const statusText = document.getElementById('syncStatusText');
            
            if (connected) {
                statusIndicator.className = 'status-indicator status-connected';
                statusText.textContent = '已連接';
            } else {
                statusIndicator.className = 'status-indicator status-disconnected';
                statusText.textContent = '未連接';
            }
        }
        
        // 更新最後同步時間
        function updateLastSyncTime() {
            const now = new Date();
            document.getElementById('lastSyncTime').textContent = now.toLocaleTimeString();
        }
        
        // 記錄日誌
        function log(message, type = 'info') {
            const logContainer = document.getElementById('logContainer');
            const logEntry = document.createElement('div');
            logEntry.className = `log-entry log-${type}`;
            logEntry.textContent = `[${new Date().toLocaleTimeString()}] ${message}`;
            logContainer.appendChild(logEntry);
            logContainer.scrollTop = logContainer.scrollHeight;
        }
        
        // 清除日誌
        function clearLog() {
            document.getElementById('logContainer').innerHTML = '';
        }
        
        // 頁面載入時初始化
        document.addEventListener('DOMContentLoaded', function() {
            log('HTTP 同步測試頁面已載入', 'info');
            updateSyncStatus(false);
        });
    </script>
</body>
</html>
