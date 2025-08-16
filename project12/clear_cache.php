<!DOCTYPE html>
<html>
<head>
    <title>清除快取</title>
</head>
<body>
    <h1>清除成就快取</h1>
    <p>正在清除快取並重新載入成就...</p>
    
    <script>
        // 清除成就快取
        if (typeof clearAchievementsCache === 'function') {
            clearAchievementsCache();
        }
        
        // 強制重新載入成就
        if (typeof loadUserAchievements === 'function') {
            loadUserAchievements();
        }
        
        // 延遲後返回主頁
        setTimeout(function() {
            window.location.href = 'index.php';
        }, 2000);
    </script>
    
    <p>2秒後自動返回主頁...</p>
</body>
</html> 