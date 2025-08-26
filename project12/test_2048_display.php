<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>測試 2048 遊戲數字顯示</title>
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
    <h1>🔢 2048 遊戲數字顯示測試</h1>
    
    <div class="test-section">
        <h2>❓ 問題描述</h2>
        <div class="result info">
            <h3>您遇到的問題：</h3>
            <ul>
                <li>遊戲板上的數字沒有顯示出來</li>
                <li>控制台顯示已經添加了數字，但遊戲板上看不到</li>
                <li>遊戲板顯示為空白</li>
            </ul>
        </div>
    </div>
    
    <div class="test-section">
        <h2>🔧 修復內容</h2>
        <div class="result success">
            <h3>已修復的問題：</h3>
            <ul>
                <li>✅ 修復了 updateDisplay() 方法中的 HTML 元素查找方式</li>
                <li>✅ 從使用 `tile-${i}-${j}` ID 改為使用 `.cell` 類名</li>
                <li>✅ 正確使用 `data-row` 和 `data-col` 屬性來定位格子</li>
                <li>✅ 確保數字能正確顯示在遊戲板上</li>
            </ul>
        </div>
    </div>
    
    <div class="test-section">
        <h2>🧪 測試步驟</h2>
        <div class="result info">
            <h3>請按照以下步驟測試：</h3>
            <ol>
                <li>點擊下方按鈕打開 2048 遊戲</li>
                <li>選擇任意難度開始遊戲</li>
                <li>檢查遊戲板上是否顯示了數字（應該是兩個 "2"）</li>
                <li>如果數字顯示正常，說明修復成功</li>
            </ol>
        </div>
    </div>
    
    <div class="test-section">
        <h2>🎯 開始測試</h2>
        <button class="test-button" onclick="openGame()">🚀 打開 2048 遊戲</button>
        <button class="test-button" onclick="checkElements()">🔍 檢查 HTML 元素</button>
        <div id="testResult"></div>
    </div>
    
    <div class="test-section">
        <h2>📝 調試信息</h2>
        <div class="result info">
            <p>請打開瀏覽器的開發者工具（F12），查看控制台日誌：</p>
            <ul>
                <li>應該看到「找到 16 個格子元素」</li>
                <li>應該看到「更新格子 (x, y): 值 = 2」</li>
                <li>如果看到「找到 0 個格子元素」，說明 HTML 結構有問題</li>
            </ul>
        </div>
    </div>
    
    <script>
        function openGame() {
            window.open('2048ht.php', '_blank');
        }
        
        function checkElements() {
            const resultDiv = document.getElementById('testResult');
            resultDiv.innerHTML = '檢查中...';
            
            // 嘗試檢查元素
            try {
                const cells = document.querySelectorAll('.cell');
                const board = document.getElementById('board');
                
                let result = '';
                result += `<h3>HTML 元素檢查結果：</h3>`;
                result += `<p>遊戲板元素: ${board ? '✅ 找到' : '❌ 未找到'}</p>`;
                result += `<p>格子元素數量: ${cells.length}</p>`;
                
                if (cells.length > 0) {
                    result += `<p>✅ 格子元素結構正常</p>`;
                    result += `<p>第一個格子的 data-row: ${cells[0].dataset.row}</p>`;
                    result += `<p>第一個格子的 data-col: ${cells[0].dataset.col}</p>`;
                } else {
                    result += `<p>❌ 沒有找到格子元素，可能是 HTML 結構問題</p>`;
                }
                
                resultDiv.innerHTML = `<div class="result ${cells.length > 0 ? 'success' : 'error'}">${result}</div>`;
            } catch (error) {
                resultDiv.innerHTML = `<div class="result error"><h3>❌ 檢查失敗</h3><p>錯誤: ${error.message}</p></div>`;
            }
        }
        
        // 頁面載入時自動檢查
        window.onload = function() {
            console.log('🔢 2048 遊戲數字顯示測試頁面已載入');
            checkElements();
        };
    </script>
</body>
</html>
