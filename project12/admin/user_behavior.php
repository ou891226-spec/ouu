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

// 過濾掉非遊戲行為，只顯示遊戲相關行為
        $where_conditions[] = "ubl.action_type IN ('game_exit', 'game_complete')";

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
try {
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
} catch (Exception $e) {
    $total_records = 0;
    $total_pages = 1;
}

// 檢查表是否存在
try {
    $table_check = $pdo->query("SHOW TABLES LIKE 'user_behavior_log'");
    if ($table_check->rowCount() == 0) {
        throw new Exception("user_behavior_log 表不存在，請先運行 fix_user_behavior_table.php");
    }
    
    // 檢查表結構
    $columns = $pdo->query("DESCRIBE user_behavior_log")->fetchAll(PDO::FETCH_COLUMN);
    $has_id = in_array('id', $columns);
    $has_log_id = in_array('log_id', $columns);
    
    // 檢查表結構中存在的字段
    $has_page_url = in_array('page_url', $columns);
    $has_game_type = in_array('game_type', $columns);
    
    // 根據表結構動態構建查詢
    if ($has_id) {
        $sql = "
            SELECT ubl.id, ubl.member_id, ubl.action_type, " . 
            ($has_page_url ? "ubl.page_url, " : "NULL as page_url, ") .
            ($has_game_type ? "ubl.game_type, " : "NULL as game_type, ") .
            "ubl.session_id, ubl.created_at, m.member_name 
            FROM user_behavior_log ubl 
            LEFT JOIN member m ON ubl.member_id = m.member_id 
            $where_clause
            ORDER BY ubl.created_at DESC 
            LIMIT $per_page OFFSET $offset
        ";
    } elseif ($has_log_id) {
        // 使用 log_id 作為主鍵
        $sql = "
            SELECT ubl.log_id as id, ubl.member_id, ubl.action_type, " . 
            ($has_page_url ? "ubl.page_url, " : "NULL as page_url, ") .
            ($has_game_type ? "ubl.game_type, " : "NULL as game_type, ") .
            "ubl.session_id, ubl.created_at, m.member_name 
            FROM user_behavior_log ubl 
            LEFT JOIN member m ON ubl.member_id = m.member_id 
            $where_clause
            ORDER BY ubl.created_at DESC 
            LIMIT $per_page OFFSET $offset
        ";
    } else {
        // 如果沒有主鍵字段，使用其他字段
$sql = "
            SELECT ubl.member_id, ubl.action_type, " . 
            ($has_page_url ? "ubl.page_url, " : "NULL as page_url, ") .
            ($has_game_type ? "ubl.game_type, " : "NULL as game_type, ") .
            "ubl.session_id, ubl.created_at, m.member_name 
    FROM user_behavior_log ubl 
    LEFT JOIN member m ON ubl.member_id = m.member_id 
    $where_clause
    ORDER BY ubl.created_at DESC 
    LIMIT $per_page OFFSET $offset
";
    }
    
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll();
    
} catch (Exception $e) {
    $error_message = $e->getMessage();
    $records = [];
}

// 定義遊戲類型到中文名稱的對應
$game_type_to_name = [
    '記憶力' => '翻牌對對樂',
    '算數邏輯力' => '算菜錢',
    '反應力' => '接金蛋',
    '策略思維' => '2048',
    '注意力' => '看字選色',
    '邏輯推理' => '圖片線索問答',
    '節奏感' => '節奏遊戲',
    '追蹤能力' => '追蹤犯人',
    '過河遊戲' => '過河遊戲'
];

// 獲取統計數據
try {
$stats_sql = "
    SELECT 
        COUNT(*) as total_actions,
        COUNT(DISTINCT ubl.member_id) as unique_users,
        COUNT(DISTINCT ubl.session_id) as unique_sessions,
        COUNT(CASE WHEN ubl.action_type = 'game_exit' THEN 1 END) as game_exits,
        COUNT(CASE WHEN ubl.action_type = 'game_complete' THEN 1 END) as game_completes
    FROM user_behavior_log ubl 
    LEFT JOIN member m ON ubl.member_id = m.member_id 
        WHERE ubl.action_type IN ('game_exit', 'game_complete')
    $where_clause
";
$stats_stmt = $pdo->prepare($stats_sql);
$stats_stmt->execute($params);
$stats = $stats_stmt->fetch();
} catch (Exception $e) {
    $stats = [
        'total_actions' => 0,
        'unique_users' => 0,
        'unique_sessions' => 0,
        'game_exits' => 0,
        'game_completes' => 0
    ];
}

// 獲取行為類型分布
try {
    // 為行為類型分布創建專門的過濾條件
    $action_types_where_conditions = [];
    $action_types_params = [];
    
    // 只過濾遊戲相關行為
    $action_types_where_conditions[] = "ubl.action_type IN ('game_exit', 'game_complete')";
    
    if ($date_filter) {
        switch ($date_filter) {
            case 'today':
                $action_types_where_conditions[] = "DATE(ubl.created_at) = CURDATE()";
                break;
            case 'week':
                $action_types_where_conditions[] = "ubl.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
                break;
            case 'month':
                $action_types_where_conditions[] = "ubl.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
                break;
        }
    }
    
    if ($user_search) {
        $action_types_where_conditions[] = "m.member_name LIKE ?";
        $action_types_params[] = "%$user_search%";
    }
    
    $action_types_where_clause = 'WHERE ' . implode(' AND ', $action_types_where_conditions);
    
    $action_types_sql = "
        SELECT action_type, COUNT(*) as count
        FROM user_behavior_log ubl 
        LEFT JOIN member m ON ubl.member_id = m.member_id 
        $action_types_where_clause
        GROUP BY action_type
        ORDER BY count DESC
    ";
    $action_types_stmt = $pdo->prepare($action_types_sql);
    $action_types_stmt->execute($action_types_params);
    $action_types = $action_types_stmt->fetchAll();
} catch (Exception $e) {
    $action_types = [];
}

// 1. 遊戲完成統計分析
try {
    // 為遊戲完成分析創建專門的過濾條件
    $game_complete_where_conditions = [];
    $game_complete_params = [];
    
    if ($date_filter) {
        switch ($date_filter) {
            case 'today':
                $game_complete_where_conditions[] = "DATE(ubl.created_at) = CURDATE()";
                break;
            case 'week':
                $game_complete_where_conditions[] = "ubl.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
                break;
            case 'month':
                $game_complete_where_conditions[] = "ubl.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
                break;
        }
    }
    
    if ($user_search) {
        $game_complete_where_conditions[] = "m.member_name LIKE ?";
        $game_complete_params[] = "%$user_search%";
    }
    
    $game_complete_where_clause = $game_complete_where_conditions ? 'AND ' . implode(' AND ', $game_complete_where_conditions) : '';
    
    $game_complete_sql = "
        SELECT 
            ubl.game_type,
            COUNT(*) as total_completes,
            COUNT(DISTINCT ubl.member_id) as unique_players
        FROM user_behavior_log ubl 
        LEFT JOIN member m ON ubl.member_id = m.member_id 
        WHERE ubl.action_type = 'game_complete' 
        AND ubl.game_type IS NOT NULL
        $game_complete_where_clause
        GROUP BY ubl.game_type 
        HAVING total_completes >= 1
        ORDER BY total_completes DESC
    ";
    
    $game_complete_stmt = $pdo->prepare($game_complete_sql);
    $game_complete_stmt->execute($game_complete_params);
    $game_complete_data = $game_complete_stmt->fetchAll();
} catch (Exception $e) {
    $game_complete_data = [];
}

// 2. 遊戲退出統計分析
try {
    // 為遊戲退出分析創建專門的過濾條件
    $game_exit_where_conditions = [];
    $game_exit_params = [];
    
    if ($date_filter) {
        switch ($date_filter) {
            case 'today':
                $game_exit_where_conditions[] = "DATE(ubl.created_at) = CURDATE()";
                break;
            case 'week':
                $game_exit_where_conditions[] = "ubl.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
                break;
            case 'month':
                $game_exit_where_conditions[] = "ubl.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
                break;
        }
    }
    
    if ($user_search) {
        $game_exit_where_conditions[] = "m.member_name LIKE ?";
        $game_exit_params[] = "%$user_search%";
    }
    
    $game_exit_where_clause = $game_exit_where_conditions ? 'AND ' . implode(' AND ', $game_exit_where_conditions) : '';
    
    $game_exit_sql = "
        SELECT 
            ubl.game_type,
            COUNT(*) as total_exits,
            COUNT(DISTINCT ubl.member_id) as unique_players,
            COUNT(CASE WHEN JSON_EXTRACT(ubl.additional_data, '$.play_time') <= 15 
                AND JSON_EXTRACT(ubl.additional_data, '$.play_time') > 0 
                THEN 1 END) as quick_exits_15s,
            COUNT(CASE WHEN JSON_EXTRACT(ubl.additional_data, '$.play_time') <= 30 
                AND JSON_EXTRACT(ubl.additional_data, '$.play_time') > 0 
                THEN 1 END) as quick_exits_30s
        FROM user_behavior_log ubl 
        LEFT JOIN member m ON ubl.member_id = m.member_id
        WHERE ubl.action_type = 'game_exit' 
        AND ubl.game_type IS NOT NULL
        $game_exit_where_clause
        GROUP BY ubl.game_type 
        HAVING total_exits >= 1
        ORDER BY total_exits DESC
        LIMIT 15
    ";
    
    $game_exit_stmt = $pdo->prepare($game_exit_sql);
    $game_exit_stmt->execute($game_exit_params);
    $game_exit_data = $game_exit_stmt->fetchAll();
} catch (Exception $e) {
    $game_exit_data = [];
}



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
        .action-game_complete { background: #28a745; }
        .action-game_exit { background: #dc3545; }
        .action-login { background: #17a2b8; }
        .action-logout { background: #6c757d; }
        .highlight { background: #fff3cd; }
        .warning { background: #f8d7da; }
        .success { background: #d1edff; }
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
                <h3><?php 
                    $total_games = $stats['game_exits'] + $stats['game_completes'];
                    echo $total_games > 0 ? round(($stats['game_exits'] / $total_games) * 100, 1) : 0; 
                ?>%</h3>
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
                    <?php if (empty($action_types)): ?>
                    <tr>
                        <td colspan="3" style="text-align: center; color: #666; padding: 20px;">
                            暫無行為類型數據
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($action_types as $type): ?>
                    <tr>
                        <td>
                            <span class="action-type action-<?php echo $type['action_type']; ?>">
                                <?php 
                                $labels = [
                                    'page_view' => '頁面瀏覽',

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
                        <td><?php echo $stats['total_actions'] > 0 ? round(($type['count'] / $stats['total_actions']) * 100, 1) : 0; ?>%</td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- 1. 遊戲完成統計分析 -->
        <div class="behavior-analysis">
            <h2>🎮 遊戲完成統計分析</h2>
            <p><em>分析各遊戲的完成次數和參與玩家數量</em></p>
            
            <?php if (empty($game_complete_data)): ?>
                <p style="color: #666; text-align: center; padding: 20px;">暫無遊戲完成數據</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>遊戲類型</th>
                            <th>完成次數</th>
                            <th>參與玩家數</th>
                            <th>平均完成率</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($game_complete_data as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($game_type_to_name[$row['game_type']] ?? $row['game_type']); ?></td>
                                <td><strong><?php echo number_format($row['total_completes']); ?></strong></td>
                                <td><?php echo number_format($row['unique_players']); ?></td>
                                <td><?php echo $row['unique_players'] > 0 ? round($row['total_completes'] / $row['unique_players'], 1) : 0; ?> 次/人</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <!-- 2. 遊戲退出統計分析 -->
        <div class="behavior-analysis">
            <h2>🔄 遊戲退出統計分析</h2>
            <p><em>分析各遊戲的退出次數和參與玩家數量</em></p>
            
            <?php if (empty($game_exit_data)): ?>
                <p style="color: #666; text-align: center; padding: 20px;">暫無遊戲退出數據</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>遊戲類型</th>
                            <th>退出次數</th>
                            <th>參與玩家數</th>
                            <th>平均退出率</th>
                            <th>≤15秒退出</th>
                            <th>快速退出率</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($game_exit_data as $row): ?>
                            <?php 
                            $quick_exit_rate_15s = $row['total_exits'] > 0 ? round(($row['quick_exits_15s'] / $row['total_exits']) * 100, 1) : 0;
                            $quick_exit_rate_30s = $row['total_exits'] > 0 ? round(($row['quick_exits_30s'] / $row['total_exits']) * 100, 1) : 0;
                            $row_class = $quick_exit_rate_15s > 30 ? 'warning' : ($quick_exit_rate_15s > 15 ? 'highlight' : 'success');
                            ?>
                            <tr class="<?php echo $row_class; ?>">
                                <td><?php echo htmlspecialchars($game_type_to_name[$row['game_type']] ?? $row['game_type']); ?></td>
                                <td><strong><?php echo number_format($row['total_exits']); ?></strong></td>
                                <td><?php echo number_format($row['unique_players']); ?></td>
                                <td><?php echo $row['unique_players'] > 0 ? round($row['total_exits'] / $row['unique_players'], 1) : 0; ?> 次/人</td>
                                <td><?php echo number_format($row['quick_exits_15s']); ?></td>
                                <td>
                                    <strong><?php echo $quick_exit_rate_15s; ?>%</strong>
                                    <?php if ($quick_exit_rate_15s > 30): ?>
                                        <span style="color: #dc3545;">⚠️ 需要優化</span>
                                    <?php elseif ($quick_exit_rate_15s > 15): ?>
                                        <span style="color: #ffc107;">⚠️ 建議改善</span>
                                    <?php else: ?>
                                        <span style="color: #28a745;">✅ 表現良好</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
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
                    <?php if (isset($error_message)): ?>
                    <tr>
                        <td colspan="4" style="text-align: center; color: red; padding: 20px;">
                            <strong>錯誤：</strong><?php echo htmlspecialchars($error_message); ?><br>
                            <a href="add_missing_fields.php" style="color: blue; text-decoration: underline;">點擊這裡添加缺失字段</a>
                        </td>
                    </tr>
                    <?php elseif (empty($records)): ?>
                    <tr>
                        <td colspan="4" style="text-align: center; color: #666; padding: 20px;">
                            暫無行為記錄數據
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php 
                    // 定義檔案路徑到遊戲名稱的對應
                    $path_to_game_name = [
                        '/project12/Vegetable-Cost.php' => '算菜錢',
                        '/project12/Memory-Game.php' => '翻牌對對樂',
                        '/project12/river.php' => '過河遊戲',
                        '/project12/2048ht.php' => '2048',
                        '/project12/rhythm_game.php' => '節奏遊戲',
                        '/project12/Catch-Egg Game.php' => '接金蛋',
                        '/project12/prisoner.php' => '追蹤犯人',
                        '/project12/text-color.php' => '看字選色',
                        '/project12/clue.php' => '圖片線索問答',
                        '/project12/vegetable_cost_2P.php' => '算菜錢(雙人)',
                        '/project12/Memory-Game-2P.php' => '翻牌對對樂(雙人)'
                    ];
                    
                    foreach ($records as $record): 
                        // 轉換為遊戲名稱，優先使用 game_type
                        $display_name = '';
                        if (isset($record['game_type']) && $record['game_type']) {
                            // 優先使用 game_type 對應的中文名稱
                            $display_name = $game_type_to_name[$record['game_type']] ?? $record['game_type'];
                        } elseif (isset($record['page_url']) && $record['page_url']) {
                            // 如果沒有 game_type，使用 page_url 對應
                            $display_name = $path_to_game_name[$record['page_url']] ?? $record['page_url'];
                        } else {
                            $display_name = '-';
                        }
                    ?>
                    <tr>
                        <td><?php echo isset($record['id']) ? $record['id'] : (isset($record['log_id']) ? $record['log_id'] : 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($record['member_name'] ?? '未登入用戶'); ?></td>
                        <td>
                            <span class="action-type action-<?php echo htmlspecialchars($record['action_type'] ?? ''); ?>">
                                <?php 
                                $labels = [
                                    'page_view' => '頁面瀏覽',                            
                                    'game_complete' => '遊戲完成',
                                    'game_exit' => '遊戲退出',
                                    'login' => '登入',
                                    'logout' => '登出'
                                ];
                                echo $labels[$record['action_type']] ?? $record['action_type'] ?? '未知';
                                ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($display_name); ?></td>
                        <td><?php echo isset($record['created_at']) ? date('m月d日 H:i', strtotime($record['created_at'])) : 'N/A'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
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