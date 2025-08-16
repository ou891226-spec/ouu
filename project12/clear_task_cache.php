<!DOCTYPE html>
<html>
<head>
    <title>清除任務快取</title>
    <meta charset="UTF-8">
</head>
<body>
    <h1>清除任務快取</h1>
    <p>正在清除快取並重新載入任務...</p>
    <script>
        // 清除任務快取
        if (typeof window !== 'undefined') {
            window.tasksLoaded = false;
        }
        
        // 清除localStorage中的快取
        if (typeof localStorage !== 'undefined') {
            localStorage.removeItem('daily_tasks_cache');
        }
        
        // 強制重新載入頁面
        setTimeout(function() {
            window.location.href = 'index.php';
        }, 2000);
    </script>
    <p>2秒後自動返回主頁...</p>
</body>
</html> 