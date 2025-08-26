<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>測試最高分數功能</title>
</head>
<body>
    <h1>測試最高分數功能</h1>
    <button onclick="testHighScore()">測試獲取最高分數</button>
    <div id="result"></div>
    
    <script>
        async function testHighScore() {
            const resultDiv = document.getElementById('result');
            resultDiv.innerHTML = '測試中...';
            
            try {
                const response = await fetch('get_high_score.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        game_id: 4,
                        member_id: 24 // 使用截圖中顯示的會員ID
                    })
                });
                
                const data = await response.json();
                console.log('測試結果:', data);
                
                if (data.success) {
                    resultDiv.innerHTML = `
                        <h3>測試成功</h3>
                        <p>最高分數: ${data.high_score}</p>
                        <p>回應: ${JSON.stringify(data)}</p>
                    `;
                } else {
                    resultDiv.innerHTML = `
                        <h3>測試失敗</h3>
                        <p>錯誤: ${data.message}</p>
                        <p>回應: ${JSON.stringify(data)}</p>
                    `;
                }
            } catch (error) {
                console.error('測試錯誤:', error);
                resultDiv.innerHTML = `
                    <h3>測試錯誤</h3>
                    <p>錯誤: ${error.message}</p>
                `;
            }
        }
        
        // 頁面載入時自動測試
        window.onload = function() {
            console.log('頁面載入完成，開始測試...');
            testHighScore();
        };
    </script>
</body>
</html>
