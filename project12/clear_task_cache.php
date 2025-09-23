<!DOCTYPE html>
<html>
<head>
    <title>清除任務快取</title>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: 'Microsoft JhengHei', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
            text-align: center;
            max-width: 500px;
        }
        h1 {
            color: #333;
            margin-bottom: 20px;
        }
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #4CAF50;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 10px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔄 清除任務快取</h1>
        <p>正在清除快取並重新載入任務...</p>
        <div style="margin: 20px 0;">
            <div class="loading"></div>
            <span>處理中...</span>
        </div>
        <p style="color: #666; font-size: 14px;">請稍候，系統正在更新任務資料</p>
    </div>
    
    <script>
        // 清除所有任務相關的快取
        function clearAllTaskCache() {
            console.log("開始清除任務快取...");
            
            // 清除localStorage中的所有任務相關資料
            const keysToRemove = [
                'daily_tasks_cache',
                'missionLoadDate',
                'missionLoadedToday',
                'missionShownToday',
                'autoShowMission'
            ];
            
            keysToRemove.forEach(key => {
                localStorage.removeItem(key);
                console.log(`已清除: ${key}`);
            });
            
            // 清除sessionStorage
            sessionStorage.clear();
            console.log("已清除sessionStorage");
            
            // 重置全域變數
            if (typeof window !== 'undefined') {
                window.tasksLoaded = false;
                window.missionLoaded = false;
            }
            
            console.log("快取清除完成");
        }
        
        // 立即執行清除
        clearAllTaskCache();
        
        // 2秒後重新導向到首頁
        setTimeout(function() {
            console.log("重新導向到首頁...");
            window.location.href = 'index.php?cache_cleared=' + Date.now();
        }, 2000);
    </script>
</body>
</html> 