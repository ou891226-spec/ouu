<?php
session_start();
require_once '../db.php';

// 檢查管理員權限
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$admin_name = $_SESSION['admin_name'] ?? '管理員';

// 獲取基本統計數據
try {
    // 總用戶數
    $user_count = $pdo->query("SELECT COUNT(*) FROM member")->fetchColumn();
    
    // 今日遊戲次數
    $today_games = $pdo->query("SELECT COUNT(*) FROM game_records WHERE DATE(play_date) = CURDATE()")->fetchColumn();
    
    // 平均分數
    $avg_score = $pdo->query("SELECT AVG(score) FROM game_records WHERE score > 0")->fetchColumn() ?? 0;
    
} catch (Exception $e) {
    $error = '獲取數據失敗：' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>後台管理系統</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; }
        .header { background: white; padding: 20px; margin-bottom: 20px; border-radius: 5px; }
        .nav { background: white; padding: 15px; margin-bottom: 20px; border-radius: 5px; }
        .nav a { margin-right: 20px; text-decoration: none; color: #007bff; }
        .nav a:hover { text-decoration: underline; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 20px; }
        .stat-card { background: white; padding: 20px; border-radius: 5px; text-align: center; }
        .stat-card h3 { margin: 0; color: #007bff; font-size: 24px; }
        .stat-card p { margin: 5px 0 0 0; color: #666; }
        .logout { 
            float: right; 
            background: #dc3545; 
            color: white; 
            padding: 8px 16px; 
            text-decoration: none; 
            border-radius: 5px; 
            font-weight: bold;
            transition: background-color 0.3s ease;
            margin-top: -85px;
        }
        .logout:hover { 
            background: #c82333; 
            text-decoration: none;
        }
        .welcome-section {
            background: white;
            padding: 30px;
            border-radius: 5px;
            text-align: center;
            margin-bottom: 20px;
        }
        .welcome-section h2 {
            color: #333;
            margin-bottom: 15px;
        }
        .welcome-section p {
            color: #666;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>樂齡智趣網後台管理</h1>
            <p>歡迎，<?php echo htmlspecialchars($admin_name); ?></p>
            <a href="logout.php" class="logout">登出</a>
        </div>
        
        <div class="nav">
            <a href="index.php">首頁</a>
            <a href="game_records.php">遊戲紀錄</a>
            <a href="user_behavior.php">行為軌跡</a>
            <a href="question_management.php">遊戲管理</a>
            <a href="user_management.php">用戶管理</a>
        </div>
        
        <div class="welcome-section">
            <h2>系統概覽</h2>
            <p>歡迎使用樂齡智趣網後台管理系統。您可以在此查看系統統計數據，並使用上方導航欄管理各個功能模組。</p>
        </div>
        
        <div class="stats">
            <div class="stat-card">
                <h3><?php echo number_format($user_count); ?></h3>
                <p>總用戶數</p>
            </div>
            <div class="stat-card">
                <h3><?php echo number_format($today_games); ?></h3>
                <p>今日遊戲次數</p>
            </div>
            <div class="stat-card">
                <h3><?php echo round($avg_score); ?></h3>
                <p>平均分數</p>
            </div>
        </div>
    </div>
</body>
</html> 