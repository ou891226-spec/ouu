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
    
    // 今日遊玩時間
    $today_playtime = $pdo->query("SELECT SUM(play_time) FROM game_records WHERE DATE(play_date) = CURDATE()")->fetchColumn() ?? 0;
    $hours = floor($today_playtime / 3600);
    $minutes = floor(($today_playtime % 3600) / 60);
    
    // 平均分數
    $avg_score = $pdo->query("SELECT AVG(score) FROM game_records WHERE score > 0")->fetchColumn() ?? 0;
    
    // 最近遊戲記錄
    $recent_games = $pdo->query("
        SELECT gr.*, m.member_name 
        FROM game_records gr 
        JOIN member m ON gr.member_id = m.member_id 
        ORDER BY gr.play_date DESC 
        LIMIT 10
    ")->fetchAll();
    
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
        .recent-games { background: white; padding: 20px; border-radius: 5px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; font-weight: bold; }
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
            <a href="question_management.php">題目管理</a>
            <a href="user_management.php">用戶管理</a>
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
                <h3><?php echo sprintf('%d日 %02d:%02d', 0, $hours, $minutes); ?></h3>
                <p>今日遊玩時間</p>
            </div>
            <div class="stat-card">
                <h3><?php echo round($avg_score); ?></h3>
                <p>平均分數</p>
            </div>
        </div>
        
        <div class="recent-games">
            <h2>最近遊戲記錄</h2>
            <table>
                <thead>
                    <tr>
                        <th>用戶</th>
                        <th>遊戲類型</th>
                        <th>分數</th>
                        <th>難度</th>
                        <th>時間</th>
                        <th>日期</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_games as $game): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($game['member_name']); ?></td>
                        <td><?php echo htmlspecialchars($game['game_type']); ?></td>
                        <td><?php echo $game['score']; ?></td>
                        <td><?php echo htmlspecialchars($game['difficulty'] ?? '一般'); ?></td>
                        <td><?php echo $game['play_time']; ?>秒</td>
                        <td><?php echo date('d日 H:i', strtotime($game['play_date'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html> 