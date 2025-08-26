<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>2048 遊戲最終測試</title>
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
        .info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
    </style>
</head>
<body>
    <h1>🎮 2048 遊戲最終測試</h1>
    
    <div class="test-section">
        <h2>✅ 修復確認</h2>
        <div class="result info">
            <h3>已修復的問題：</h3>
            <ul>
                <li>✅ 遊戲達到目標分數時自動跳出「恭喜破關」彈窗</li>
                <li>✅ 最高分數在移動過程中實時更新</li>
                <li>✅ 勝利彈窗顯示正確的最高分數</li>
            </ul>
        </div>
    </div>
    
    <div class="test-section">
        <h2>🧪 測試步驟</h2>
        <div class="result info">
            <h3>請按照以下步驟測試：</h3>
            <ol>
                <li>點擊下方按鈕打開 2048 遊戲</li>
                <li>選擇「簡單」難度（目標分數：1500）</li>
                <li>玩到超過 1500 分</li>
                <li>觀察是否自動跳出「恭喜破關」彈窗</li>
                <li>檢查彈窗中的「最高分數」是否顯示正確</li>
            </ol>
        </div>
    </div>
    
    <div class="test-section">
        <h2>🎯 開始測試</h2>
        <button class="test-button" onclick="openGame()">🚀 打開 2048 遊戲</button>
        <button class="test-button" onclick="testHighScore()">📊 測試最高分數功能</button>
        <div id="testResult"></div>
    </div>
    
    <div class="test-section">
        <h2>📝 調試信息</h2>
        <div class="result info">
            <p>請打開瀏覽器的開發者工具（F12），查看控制台日誌：</p>
            <ul>
                <li>應該看到「移動過程中更新最高分數為: [分數]」</li>
                <li>應該看到「移動過程中達到目標分數，立即停止遊戲」</li>
                <li>應該看到「=== 顯示勝利彈窗 ===」</li>
                <li>應該看到「勝利時更新最高分數為: [分數]」</li>
            </ul>
        </div>
    </div>
    
    <script>
        async function testHighScore() {
            const resultDiv = document.getElementById('testResult');
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
                            <p>當前最高分數: ${data.high_score}</p>
                            <p>API 回應: ${JSON.stringify(data)}</p>
                        </div>
                    `;
                } else {
                    resultDiv.innerHTML = `
                        <div class="result error">
                            <h3>❌ 最高分數功能異常</h3>
                            <p>錯誤: ${data.message}</p>
                            <p>API 回應: ${JSON.stringify(data)}</p>
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
        
        function openGame() {
            window.open('2048ht.php', '_blank');
        }
        
        // 頁面載入時自動測試最高分數功能
        window.onload = function() {
            console.log('🎮 2048 遊戲測試頁面已載入');
            testHighScore();
        };
    </script>
</body>
</html>
