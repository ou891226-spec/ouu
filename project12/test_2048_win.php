<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>測試 2048 遊戲勝利彈窗</title>
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
    <h1>🏆 2048 遊戲勝利彈窗測試</h1>
    
    <div class="test-section">
        <h2>❓ 問題描述</h2>
        <div class="result info">
            <h3>您遇到的問題：</h3>
            <ul>
                <li>遊戲達到目標分數（1500）時沒有自動跳出「恭喜破關」彈窗</li>
                <li>雖然遊戲狀態顯示已勝利，但彈窗沒有出現</li>
                <li>需要手動點擊「結束遊戲」按鈕才能看到彈窗</li>
            </ul>
        </div>
    </div>
    
    <div class="test-section">
        <h2>🔧 修復內容</h2>
        <div class="result success">
            <h3>已修復的問題：</h3>
            <ul>
                <li>✅ 在所有移動方法中添加了勝利檢查</li>
                <li>✅ 達到目標分數時立即調用 showWinModal()</li>
                <li>✅ 修復了 showWinModal() 方法中的元素檢查</li>
                <li>✅ 確保勝利彈窗能正確顯示</li>
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
                <li>檢查彈窗中的分數是否正確</li>
            </ol>
        </div>
    </div>
    
    <div class="test-section">
        <h2>🎯 開始測試</h2>
        <button class="test-button" onclick="openGame()">🚀 打開 2048 遊戲</button>
        <button class="test-button" onclick="checkWinModal()">🔍 檢查勝利彈窗</button>
        <div id="testResult"></div>
    </div>
    
    <div class="test-section">
        <h2>📝 調試信息</h2>
        <div class="result info">
            <p>請打開瀏覽器的開發者工具（F12），查看控制台日誌：</p>
            <ul>
                <li>應該看到「移動過程中達到目標分數，立即停止遊戲」</li>
                <li>應該看到「=== 顯示勝利彈窗 ===」</li>
                <li>應該看到「勝利彈窗已設置為顯示」</li>
                <li>如果看到「找不到勝利彈窗元素」，說明 HTML 結構有問題</li>
            </ul>
        </div>
    </div>
    
    <script>
        function openGame() {
            window.open('2048ht.php', '_blank');
        }
        
        function checkWinModal() {
            const resultDiv = document.getElementById('testResult');
            resultDiv.innerHTML = '檢查中...';
            
            // 嘗試檢查勝利彈窗元素
            try {
                const winModal = document.getElementById('win-modal');
                const winDifficulty = document.getElementById('win-difficulty');
                const winGameScore = document.getElementById('win-game-score');
                const winRewardScore = document.getElementById('win-reward-score');
                const winBestScore = document.getElementById('win-best-score');
                
                let result = '';
                result += `<h3>勝利彈窗元素檢查結果：</h3>`;
                result += `<p>勝利彈窗元素: ${winModal ? '✅ 找到' : '❌ 未找到'}</p>`;
                result += `<p>難度標籤: ${winDifficulty ? '✅ 找到' : '❌ 未找到'}</p>`;
                result += `<p>遊戲分數標籤: ${winGameScore ? '✅ 找到' : '❌ 未找到'}</p>`;
                result += `<p>獎勵分數標籤: ${winRewardScore ? '✅ 找到' : '❌ 未找到'}</p>`;
                result += `<p>最高分數標籤: ${winBestScore ? '✅ 找到' : '❌ 未找到'}</p>`;
                
                if (winModal && winDifficulty && winGameScore && winRewardScore && winBestScore) {
                    result += `<p>✅ 勝利彈窗 HTML 結構完整</p>`;
                } else {
                    result += `<p>❌ 勝利彈窗 HTML 結構不完整</p>`;
                }
                
                resultDiv.innerHTML = `<div class="result ${winModal ? 'success' : 'error'}">${result}</div>`;
            } catch (error) {
                resultDiv.innerHTML = `<div class="result error"><h3>❌ 檢查失敗</h3><p>錯誤: ${error.message}</p></div>`;
            }
        }
        
        // 頁面載入時自動檢查
        window.onload = function() {
            console.log('🏆 2048 遊戲勝利彈窗測試頁面已載入');
            checkWinModal();
        };
    </script>
</body>
</html>
