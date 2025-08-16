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
$action_type_filter = $_GET['action_type'] ?? '';
$date_filter = $_GET['date_filter'] ?? '';
$user_search = $_GET['user_search'] ?? '';

// 構建查詢條件
$where_conditions = [];
$params = [];

if ($action_type_filter) {
    $where_conditions[] = "ubl.action_type = ?";
    $params[] = $action_type_filter;
}

if ($date_filter) {
    switch ($date_filter) {
        case 'today':
            $where_conditions[] = "DATE(ubl.created_at) = CURDATE()";
            break;
        case 'week':
            $where_conditions[] = "ubl.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
            break;
        case 'month':
            $where_conditions[] = "ubl.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
            break;
    }
}

if ($user_search) {
    $where_conditions[] = "m.member_name LIKE ?";
    $params[] = "%$user_search%";
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// 分頁
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 50;
$offset = ($page - 1) * $per_page;

// 獲取總記錄數
$count_sql = "
    SELECT COUNT(*) 
    FROM user_behavior_log ubl 
    LEFT JOIN member m ON ubl.member_id = m.member_id 
    $where_clause
";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_records = $count_stmt->fetchColumn();
$total_pages = ceil($total_records / $per_page);

// 獲取行為記錄
$sql = "
    SELECT ubl.*, m.member_name 
    FROM user_behavior_log ubl 
    LEFT JOIN member m ON ubl.member_id = m.member_id 
    $where_clause
    ORDER BY ubl.created_at DESC 
    LIMIT $per_page OFFSET $offset
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll();

// 獲取統計數據
$stats_sql = "
    SELECT 
        COUNT(*) as total_actions,
        COUNT(DISTINCT ubl.member_id) as unique_users,
        COUNT(DISTINCT ubl.session_id) as unique_sessions,
        COUNT(CASE WHEN ubl.action_type = 'game_exit' THEN 1 END) as game_exits,
        COUNT(CASE WHEN ubl.action_type = 'game_complete' THEN 1 END) as game_completes
    FROM user_behavior_log ubl 
    LEFT JOIN member m ON ubl.member_id = m.member_id 
    $where_clause
";
$stats_stmt = $pdo->prepare($stats_sql);
$stats_stmt->execute($params);
$stats = $stats_stmt->fetch();

// 獲取行為類型分布
$action_types_sql = "
    SELECT action_type, COUNT(*) as count
    FROM user_behavior_log ubl 
    LEFT JOIN member m ON ubl.member_id = m.member_id 
    $where_clause
    GROUP BY action_type
    ORDER BY count DESC
";
$action_types_stmt = $pdo->prepare($action_types_sql);
$action_types_stmt->execute($params);
$action_types = $action_types_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>用戶行為軌跡分析</title>
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
        .behavior-analysis { background: white; padding: 20px; margin-bottom: 20px; border-radius: 5px; }
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
        .action-type { padding: 4px 8px; border-radius: 3px; color: white; font-size: 12px; }
        .action-page_view { background: #28a745; }
        .action-game_start { background: #007bff; }
        .action-game_complete { background: #28a745; }
        .action-game_exit { background: #dc3545; }
        .action-login { background: #17a2b8; }
        .action-logout { background: #6c757d; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>用戶行為軌跡分析</h1>
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
        
        <div class="filters">
            <form method="GET">
                <div>
                    <label>搜尋用戶：</label>
                    <input type="text" name="user_search" value="<?php echo htmlspecialchars($user_search); ?>" placeholder="用戶名">
                </div>
                <div>
                    <label>行為類型：</label>
                    <select name="action_type">
                        <option value="">全部</option>
                        <option value="page_view" <?php echo $action_type_filter === 'page_view' ? 'selected' : ''; ?>>頁面瀏覽</option>
                        <option value="game_start" <?php echo $action_type_filter === 'game_start' ? 'selected' : ''; ?>>遊戲開始</option>
                        <option value="game_complete" <?php echo $action_type_filter === 'game_complete' ? 'selected' : ''; ?>>遊戲完成</option>
                        <option value="game_exit" <?php echo $action_type_filter === 'game_exit' ? 'selected' : ''; ?>>遊戲退出</option>
                    </select>
                </div>
                <div>
                    <label>日期範圍：</label>
                    <select name="date_filter">
                        <option value="">全部</option>
                        <option value="today" <?php echo $date_filter === 'today' ? 'selected' : ''; ?>>今天</option>
                        <option value="week" <?php echo $date_filter === 'week' ? 'selected' : ''; ?>>本週</option>
                        <option value="month" <?php echo $date_filter === 'month' ? 'selected' : ''; ?>>本月</option>
                    </select>
                </div>
                <button type="submit">篩選</button>
                <a href="user_behavior.php" class="export">重置</a>
            </form>
        </div>
        
        <div class="stats">
            <div class="stat-card">
                <h3><?php echo number_format($stats['total_actions']); ?></h3>
                <p>總行為數</p>
            </div>
            <div class="stat-card">
                <h3><?php echo number_format($stats['unique_users']); ?></h3>
                <p>活躍用戶</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $stats['game_exits'] > 0 ? round(($stats['game_exits'] / ($stats['game_exits'] + $stats['game_completes'])) * 100, 1) : 0; ?>%</h3>
                <p>遊戲退出率</p>
            </div>
        </div>
        
        <div class="behavior-analysis">
            <h2>行為類型分布</h2>
            <table>
                <thead>
                    <tr>
                        <th>行為類型</th>
                        <th>次數</th>
                        <th>百分比</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($action_types as $type): ?>
                    <tr>
                        <td>
                            <span class="action-type action-<?php echo $type['action_type']; ?>">
                                <?php 
                                $labels = [
                                    'page_view' => '頁面瀏覽',
                                    'game_start' => '遊戲開始',
                                    'game_complete' => '遊戲完成',
                                    'game_exit' => '遊戲退出',
                                    'login' => '登入',
                                    'logout' => '登出'
                                ];
                                echo $labels[$type['action_type']] ?? $type['action_type'];
                                ?>
                            </span>
                        </td>
                        <td><?php echo number_format($type['count']); ?></td>
                        <td><?php echo round(($type['count'] / $stats['total_actions']) * 100, 1); ?>%</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="records">
            <h2>行為記錄列表 (共 <?php echo number_format($total_records); ?> 筆)</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>用戶</th>
                        <th>行為類型</th>
                        <th>頁面/遊戲</th>
                        <th>時間</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($records as $record): ?>
                    <tr>
                        <td><?php echo $record['id']; ?></td>
                        <td><?php echo htmlspecialchars($record['member_name'] ?? '未登入用戶'); ?></td>
                        <td>
                            <span class="action-type action-<?php echo $record['action_type']; ?>">
                                <?php 
                                $labels = [
                                    'page_view' => '頁面瀏覽',
                                    'game_start' => '遊戲開始',
                                    'game_complete' => '遊戲完成',
                                    'game_exit' => '遊戲退出',
                                    'login' => '登入',
                                    'logout' => '登出'
                                ];
                                echo $labels[$record['action_type']] ?? $record['action_type'];
                                ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($record['page_url'] ?? $record['game_type'] ?? '-'); ?></td>
                        <td><?php echo date('m月d日 H:i', strtotime($record['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page-1; ?>&action_type=<?php echo urlencode($action_type_filter); ?>&date_filter=<?php echo urlencode($date_filter); ?>&user_search=<?php echo urlencode($user_search); ?>">上一頁</a>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
                    <a href="?page=<?php echo $i; ?>&action_type=<?php echo urlencode($action_type_filter); ?>&date_filter=<?php echo urlencode($date_filter); ?>&user_search=<?php echo urlencode($user_search); ?>" <?php echo $i === $page ? 'style="background: #0056b3;"' : ''; ?>><?php echo $i; ?></a>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page+1; ?>&action_type=<?php echo urlencode($action_type_filter); ?>&date_filter=<?php echo urlencode($date_filter); ?>&user_search=<?php echo urlencode($user_search); ?>">下一頁</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html> 