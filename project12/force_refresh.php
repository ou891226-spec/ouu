<!DOCTYPE html>
<html>
<head>
    <title>強制刷新成就</title>
    <meta charset="UTF-8">
</head>
<body>
    <h1>強制刷新成就顯示</h1>
    <p>正在清除快取並重新載入成就...</p>
    
    <script>
        // 清除所有快取
        if (typeof clearAchievementsCache === 'function') {
            clearAchievementsCache();
            console.log('成就快取已清除');
        }
        
        // 強制重新載入成就
        if (typeof loadUserAchievements === 'function') {
            // 延遲一下確保快取清除完成
            setTimeout(function() {
                loadUserAchievements();
                console.log('成就已重新載入');
            }, 100);
        }
        
        // 延遲後返回主頁
        setTimeout(function() {
            window.location.href = 'index.php';
        }, 2000);
    </script>
    
    <p>2秒後自動返回主頁...</p>
    <p><a href="index.php">立即返回主頁</a></p>
</body>
</html> 