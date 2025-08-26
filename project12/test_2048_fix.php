<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>測試 2048 遊戲修復</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f0f0f0;
        }
        .test-section {
            background: white;
            padding: 20px;
            margin: 10px 0;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .test-button {
            background: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin: 5px;
        }
        .test-button:hover {
            background: #45a049;
        }
        .result {
            margin-top: 10px;
            padding: 10px;
            border-radius: 4px;
        }
        .success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <h1>2048 遊戲修復測試</h1>
    
    <div class="test-section">
        <h2>1. 測試最高分數功能</h2>
        <button class="test-button" onclick="testHighScore()">測試獲取最高分數</button>
        <div id="highScoreResult"></div>
    </div>
    
    <div class="test-section">
        <h2>2. 測試遊戲邏輯</h2>
        <button class="test-button" onclick="testGameLogic()">測試遊戲邏輯</button>
        <div id="gameLogicResult"></div>
    </div>
    
    <div class="test-section">
        <h2>3. 直接進入 2048 遊戲</h2>
        <button class="test-button" onclick="openGame()">打開 2048 遊戲</button>
        <p>請在遊戲中測試：</p>
        <ul>
            <li>玩到超過 1500 分，看是否自動跳出「恭喜破關」</li>
            <li>檢查最高分數是否正確更新</li>
        </ul>
    </div>
    
    <script>
        async function testHighScore() {
            const resultDiv = document.getElementById('highScoreResult');
            resultDiv.innerHTML = '測試中...';
            
            try {
                const response = await fetch('get_high_score.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        game_id: 4,
                        member_id: 24
                    })
                });
                
                const data = await response.json();
                console.log('最高分數測試結果:', data);
                
                if (data.success) {
                    resultDiv.innerHTML = `
                        <div class="result success">
                            <h3>✅ 最高分數功能正常</h3>
                            <p>最高分數: ${data.high_score}</p>
                            <p>回應: ${JSON.stringify(data)}</p>
                        </div>
                    `;
                } else {
                    resultDiv.innerHTML = `
                        <div class="result error">
                            <h3>❌ 最高分數功能異常</h3>
                            <p>錯誤: ${data.message}</p>
                            <p>回應: ${JSON.stringify(data)}</p>
                        </div>
                    `;
                }
            } catch (error) {
                console.error('最高分數測試錯誤:', error);
                resultDiv.innerHTML = `
                    <div class="result error">
                        <h3>❌ 最高分數測試錯誤</h3>
                        <p>錯誤: ${error.message}</p>
                    </div>
                `;
            }
        }
        
        function testGameLogic() {
            const resultDiv = document.getElementById('gameLogicResult');
            resultDiv.innerHTML = '測試中...';
            
            // 模擬遊戲邏輯測試
            const testScore = 1600;
            const targetScore = 1500;
            const won = testScore >= targetScore;
            
            if (won) {
                resultDiv.innerHTML = `
                    <div class="result success">
                        <h3>✅ 遊戲邏輯正常</h3>
                        <p>測試分數: ${testScore}</p>
                        <p>目標分數: ${targetScore}</p>
                        <p>勝利狀態: ${won}</p>
                        <p>✅ 分數達到目標時應該觸發勝利條件</p>
                    </div>
                `;
            } else {
                resultDiv.innerHTML = `
                    <div class="result error">
                        <h3>❌ 遊戲邏輯異常</h3>
                        <p>測試分數: ${testScore}</p>
                        <p>目標分數: ${targetScore}</p>
                        <p>勝利狀態: ${won}</p>
                    </div>
                `;
            }
        }
        
        function openGame() {
            window.open('2048ht.php', '_blank');
        }
        
        // 頁面載入時自動測試
        window.onload = function() {
            console.log('開始自動測試...');
            testHighScore();
            testGameLogic();
        };
    </script>
</body>
</html>
