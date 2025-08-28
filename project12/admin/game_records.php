<?php
session_start();
require_once '../db.php';

// 檢查管理員權限
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$admin_name = $_SESSION['admin_name'] ?? '管理員';

// 處理篩選參數
$game_type_filter = $_GET['game_type'] ?? '';
$date_filter = $_GET['date_filter'] ?? '';
$selected_month = $_GET['selected_month'] ?? '';
$selected_year = $_GET['selected_year'] ?? '';
$search = $_GET['search'] ?? '';

// 構建查詢條件
$where_conditions = [];
$params = [];

if ($game_type_filter) {
    $where_conditions[] = "gr.game_type = ?";
    $params[] = $game_type_filter;
}

if ($date_filter) {
    switch ($date_filter) {
        case 'today':
            $where_conditions[] = "DATE(gr.play_date) = CURDATE()";
            break;
        case 'week':
            $where_conditions[] = "gr.play_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
            break;
        case 'month':
            $where_conditions[] = "gr.play_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
            break;
        case 'specific_month':
            if ($selected_month && $selected_year) {
                $where_conditions[] = "YEAR(gr.play_date) = ? AND MONTH(gr.play_date) = ?";
                $params[] = $selected_year;
                $params[] = $selected_month;
            }
            break;
    }
}

if ($search) {
    $where_conditions[] = "(m.member_name LIKE ? OR gr.game_type LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// 分頁
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 50;
$offset = ($page - 1) * $per_page;

// 獲取總記錄數
$count_sql = "
    SELECT COUNT(*) 
    FROM game_records gr 
    JOIN member m ON gr.member_id = m.member_id 
    LEFT JOIN games g ON gr.game_id = g.game_id 
    $where_clause
";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_records = $count_stmt->fetchColumn();
$total_pages = ceil($total_records / $per_page);

// 獲取遊戲記錄
$sql = "
    SELECT gr.*, m.member_name, g.game_name 
    FROM game_records gr 
    JOIN member m ON gr.member_id = m.member_id 
    LEFT JOIN games g ON gr.game_id = g.game_id 
    $where_clause
    ORDER BY gr.play_date DESC 
    LIMIT $per_page OFFSET $offset
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll();

// 獲取統計數據
$stats_sql = "
    SELECT 
        COUNT(*) as total_records,
        COUNT(DISTINCT gr.member_id) as unique_users,
        SUM(gr.play_time) as total_playtime,
        AVG(gr.score) as avg_score,
        AVG(gr.play_time) as avg_playtime
    FROM game_records gr 
    JOIN member m ON gr.member_id = m.member_id 
    LEFT JOIN games g ON gr.game_id = g.game_id 
    $where_clause
";
$stats_stmt = $pdo->prepare($stats_sql);
$stats_stmt->execute($params);
$stats = $stats_stmt->fetch();
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>遊戲紀錄管理</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; }
        .header { background: white; padding: 20px; margin-bottom: 20px; border-radius: 5px; }
        .nav { background: white; padding: 15px; margin-bottom: 20px; border-radius: 5px; }
        .nav a { margin-right: 20px; text-decoration: none; color: #007bff; }
        .nav a:hover { text-decoration: underline; }
        .filters { background: white; padding: 20px; margin-bottom: 20px; border-radius: 5px; }
        .filters form { display: flex; gap: 15px; align-items: end; flex-wrap: wrap; }
        .filters select, .filters input { padding: 8px; border: 1px solid #ddd; border-radius: 3px; }
        .filters button { 
            padding: 8px 15px; 
            background: #007bff; 
            color: white; 
            border: none; 
            border-radius: 3px; 
            cursor: pointer;
            font-size: 14px;
            min-width: 80px;
        }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 20px; }
        .stat-card { background: white; padding: 20px; border-radius: 5px; text-align: center; }
        .stat-card h3 { margin: 0; color: #007bff; font-size: 24px; }
        .stat-card p { margin: 5px 0 0 0; color: #666; }
        .records { background: white; padding: 20px; border-radius: 5px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; font-weight: bold; }
        .pagination { margin-top: 20px; text-align: center; }
        .pagination a { margin: 0 5px; padding: 8px 12px; text-decoration: none; background: #007bff; color: white; border-radius: 3px; }
        .pagination a:hover { background: #0056b3; }
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
        .export { 
            margin-left: 10px; 
            padding: 8px 15px; 
            background: #6c757d; 
            color: white; 
            text-decoration: none; 
            border-radius: 3px; 
            border: none; 
            cursor: pointer;
            transition: background-color 0.3s ease;
            font-size: 14px;
            min-width: 60px;
            display: inline-block;
        }
        .export:hover { 
            background: #5a6268; 
            text-decoration: none;
        }
        #month_year_select {
            margin-top: 10px;
        }
        #month_year_select select {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 3px;
            margin-right: 10px;
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const dateFilter = document.getElementById('date_filter');
            const monthYearSelect = document.getElementById('month_year_select');
            
            dateFilter.addEventListener('change', function() {
                if (this.value === 'specific_month') {
                    monthYearSelect.style.display = 'block';
                } else {
                    monthYearSelect.style.display = 'none';
                }
            });
        });
    </script>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>遊戲紀錄管理</h1>
            <p>歡迎，<?php echo htmlspecialchars($admin_name); ?></p>
            <a href="logout.php" class="logout">登出</a>
        </div>
        
        <div class="nav">
            <a href="game_records.php">遊戲紀錄</a>
            <a href="user_behavior.php">行為軌跡</a>
            <a href="question_management.php">遊戲管理</a>
            <a href="user_management.php">用戶管理</a>
        </div>
        
        <div class="filters">
            <form method="GET">
                <div>
                    <label>搜尋：</label>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="用戶名或遊戲類型">
                </div>
                <div>
                    <label>遊戲類型：</label>
                    <select name="game_type">
                        <option value="">全部</option>
                        <option value="記憶力" <?php echo $game_type_filter === '記憶力' ? 'selected' : ''; ?>>記憶力</option>
                        <option value="反應力" <?php echo $game_type_filter === '反應力' ? 'selected' : ''; ?>>反應力</option>
                        <option value="邏輯力" <?php echo $game_type_filter === '邏輯力' ? 'selected' : ''; ?>>邏輯力</option>
                    </select>
                </div>
                <div>
                    <label>日期範圍：</label>
                    <select name="date_filter" id="date_filter">
                        <option value="">全部</option>
                        <option value="today" <?php echo $date_filter === 'today' ? 'selected' : ''; ?>>今天</option>
                        <option value="week" <?php echo $date_filter === 'week' ? 'selected' : ''; ?>>本週</option>
                        <option value="month" <?php echo $date_filter === 'month' ? 'selected' : ''; ?>>本月</option>
                        <option value="specific_month" <?php echo $date_filter === 'specific_month' ? 'selected' : ''; ?>>指定月份</option>
                    </select>
                </div>
                <div id="month_year_select" style="display: <?php echo $date_filter === 'specific_month' ? 'block' : 'none'; ?>;">
                    <label>選擇月份：</label>
                    <select name="selected_month" style="margin-right: 10px;">
                        <option value="">選擇月份</option>
                        <?php for ($i = 1; $i <= 12; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo $selected_month == $i ? 'selected' : ''; ?>><?php echo $i; ?>月</option>
                        <?php endfor; ?>
                    </select>
                    <select name="selected_year">
                        <option value="">選擇年份</option>
                        <?php for ($i = date('Y'); $i >= date('Y') - 5; $i--): ?>
                            <option value="<?php echo $i; ?>" <?php echo $selected_year == $i ? 'selected' : ''; ?>><?php echo $i; ?>年</option>
                        <?php endfor; ?>
                    </select>
                </div>
                <button type="submit">篩選</button>
                <a href="game_records.php" class="export">重置</a>
            </form>
        </div>
        
        <div class="stats">
            <div class="stat-card">
                <h3><?php echo number_format($stats['total_records']); ?></h3>
                <p>總記錄數</p>
            </div>
            <div class="stat-card">
                <h3><?php echo number_format($stats['unique_users']); ?></h3>
                <p>活躍用戶</p>
            </div>
            <div class="stat-card">
                <h3><?php 
                    $avg_seconds = $stats['avg_playtime'] ?? 0;
                    $avg_days = floor($avg_seconds / 86400);
                    $avg_hours = floor(($avg_seconds % 86400) / 3600);
                    $avg_minutes = floor(($avg_seconds % 3600) / 60);
                    
                    if ($avg_days > 0) {
                        echo sprintf('%d日%d時%d分', $avg_days, $avg_hours, $avg_minutes);
                    } elseif ($avg_hours > 0) {
                        echo sprintf('%d時%d分', $avg_hours, $avg_minutes);
                    } else {
                        echo sprintf('%d分', $avg_minutes);
                    }
                ?></h3>
                <p>平均遊玩時間</p>
            </div>
            <div class="stat-card">
                <h3><?php echo round($stats['avg_score']); ?></h3>
                <p>平均分數</p>
            </div>
        </div>
        
        <div class="records">
            <h2>遊戲紀錄列表 (共 <?php echo number_format($total_records); ?> 筆)</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>用戶</th>
                        <th>遊戲類型</th>
                        <th>遊戲名稱</th>
                        <th>分數</th>
                        <th>難度</th>
                        <th>遊玩時間</th>
                        <th>日期</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($records as $record): ?>
                    <tr>
                        <td><?php echo $record['record_id'] ?? $record['id'] ?? '-'; ?></td>
                        <td><?php echo htmlspecialchars($record['member_name']); ?></td>
                        <td><?php echo htmlspecialchars($record['game_type']); ?></td>
                        <td><?php echo htmlspecialchars($record['game_name'] ?? '-'); ?></td>
                        <td><?php echo $record['score']; ?></td>
                        <td><?php echo htmlspecialchars($record['difficulty'] ?? '一般'); ?></td>
                        <td><?php echo $record['play_time']; ?>秒</td>
                        <td><?php echo date('m月d日 H:i', strtotime($record['play_date'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page-1; ?>&game_type=<?php echo urlencode($game_type_filter); ?>&date_filter=<?php echo urlencode($date_filter); ?>&selected_month=<?php echo urlencode($selected_month); ?>&selected_year=<?php echo urlencode($selected_year); ?>&search=<?php echo urlencode($search); ?>">上一頁</a>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
                    <a href="?page=<?php echo $i; ?>&game_type=<?php echo urlencode($game_type_filter); ?>&date_filter=<?php echo urlencode($date_filter); ?>&selected_month=<?php echo urlencode($selected_month); ?>&selected_year=<?php echo urlencode($selected_year); ?>&search=<?php echo urlencode($search); ?>" <?php echo $i === $page ? 'style="background: #0056b3;"' : ''; ?>><?php echo $i; ?></a>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page+1; ?>&game_type=<?php echo urlencode($game_type_filter); ?>&date_filter=<?php echo urlencode($date_filter); ?>&selected_month=<?php echo urlencode($selected_month); ?>&selected_year=<?php echo urlencode($selected_year); ?>&search=<?php echo urlencode($search); ?>">下一頁</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html> 