<?php
session_start();
require_once '../db.php';

// 檢查管理員權限
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$admin_name = $_SESSION['admin_name'] ?? '管理員';

// 獲取遊戲統計數據
try {
    // 各遊戲的統計數據
    $game_stats = $pdo->query("
        SELECT 
            g.game_name,
            COUNT(gr.record_id) as play_count,
            AVG(gr.score) as avg_score,
            MAX(gr.score) as max_score
        FROM games g
        LEFT JOIN game_records gr ON g.game_id = gr.game_id
        GROUP BY g.game_id, g.game_name
        ORDER BY play_count DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    
            } catch (Exception $e) {
    $error = '獲取數據失敗：' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>遊戲管理 - 後台管理系統</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; }
        .header { background: white; padding: 20px; margin-bottom: 20px; border-radius: 5px; }
        .nav { background: white; padding: 15px; margin-bottom: 20px; border-radius: 5px; }
        .nav a { margin-right: 20px; text-decoration: none; color: #007bff; }
        .nav a:hover { text-decoration: underline; }
        .nav a.active { font-weight: bold; color: #0056b3; }
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
        .content { background: white; padding: 20px; border-radius: 5px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f8f9fa; font-weight: bold; }
        tr:hover { background-color: #f5f5f5; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: #f8f9fa; padding: 20px; border-radius: 5px; text-align: center; }
        .stat-card h3 { margin: 0; color: #007bff; font-size: 24px; }
        .stat-card p { margin: 5px 0 0 0; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>遊戲管理</h1>
            <p>歡迎，<?php echo htmlspecialchars($admin_name); ?></p>
            <a href="logout.php" class="logout">登出</a>
        </div>
        
        <div class="nav">
            <a href="game_records.php">📊 遊戲紀錄</a>
            <a href="user_behavior.php">👤 行為軌跡</a>
            <a href="question_management.php" class="active">🎯 遊戲管理</a>
            <a href="user_management.php">👥 用戶管理</a>
            <a href="ability_analysis.php">🧠 能力分析</a>
            <a href="test_results.php">📈 測試結果</a>
            <a href="custom_score_trend.php">📊 趨勢分析</a>
        </div>
        
        <div class="content">
            <h2>遊戲統計概覽</h2>
        
            <?php if (isset($error)): ?>
                <div style="color: red; margin-bottom: 20px;"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
            <div class="stats-grid">
            <div class="stat-card">
                    <h3><?php echo count($game_stats); ?></h3>
                    <p>總遊戲數量</p>
            </div>
            <div class="stat-card">
                    <h3><?php echo array_sum(array_column($game_stats, 'play_count')); ?></h3>
                    <p>總遊戲次數</p>
            </div>
            <div class="stat-card">
                    <h3><?php echo round(array_sum(array_column($game_stats, 'avg_score')) / count($game_stats), 1); ?></h3>
                <p>平均分數</p>
            </div>
        </div>
        
            <h3>各遊戲詳細統計</h3>
            <table>
                <thead>
                    <tr>
                        <th>遊戲名稱</th>
                        <th>遊戲次數</th>
                        <th>平均分數</th>
                        <th>最高分數</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($game_stats as $game): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($game['game_name']); ?></td>
                        <td><?php echo number_format($game['play_count']); ?></td>
                        <td><?php echo round($game['avg_score'], 1); ?></td>
                        <td><?php echo $game['max_score']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html> 
</body>

</html> 
