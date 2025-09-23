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
$where_conditions[] = "ubl.action_type IN ('game_exit', 'game_complete', 'game_failed')";

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

// 獲取統計數據
try {
$stats_sql = "
    SELECT 
        COUNT(*) as total_actions,
        COUNT(DISTINCT ubl.member_id) as unique_users,
            COUNT(CASE WHEN ubl.action_type = 'game_exit' THEN 1 END) as exit_count,
            ROUND(COUNT(CASE WHEN ubl.action_type = 'game_exit' THEN 1 END) * 100.0 / COUNT(*), 2) as exit_rate
    FROM user_behavior_log ubl 
    LEFT JOIN member m ON ubl.member_id = m.member_id 
    $where_clause
";
$stats_stmt = $pdo->prepare($stats_sql);
$stats_stmt->execute($params);
$stats = $stats_stmt->fetch();
} catch (Exception $e) {
    $stats = ['total_actions' => 0, 'unique_users' => 0, 'exit_count' => 0, 'exit_rate' => 0];
}

// 獲取行為類型分布
try {
    $action_types_where_conditions = [];
    $action_types_params = [];
    
    // 只過濾遊戲相關行為
    $action_types_where_conditions[] = "ubl.action_type IN ('game_exit', 'game_complete', 'game_failed')";
    
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

// 1. 遊戲退出統計分析
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
            CASE 
                WHEN ubl.game_type = '反應力' THEN '接金蛋、節奏遊戲、看字選色'
                WHEN ubl.game_type = '記憶力' THEN '翻牌對對樂、圖片線索、追蹤犯人'
                WHEN ubl.game_type = '算術邏輯力' THEN '算菜錢、過河遊戲、2048'
                ELSE ubl.game_type
            END as game_names,
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

// 2. 遊戲完成率分析
try {
    $completion_analysis_sql = "
        SELECT 
            gr.game_type,
            CASE 
                WHEN gr.game_type = '反應力' THEN '接金蛋、節奏遊戲、看字選色'
                WHEN gr.game_type = '記憶力' THEN '翻牌對對樂、圖片線索、追蹤犯人'
                WHEN gr.game_type = '算術邏輯力' THEN '算菜錢、過河遊戲、2048'
                ELSE gr.game_type
            END as game_names,
            COUNT(*) as total_attempts,
            COUNT(DISTINCT gr.member_id) as unique_players,
            AVG(gr.score) as avg_score,
            AVG(gr.play_time) as avg_playtime,
            COUNT(CASE WHEN gr.score > 0 THEN 1 END) as successful_attempts,
            ROUND(COUNT(CASE WHEN gr.score > 0 THEN 1 END) * 100.0 / COUNT(*), 2) as success_rate
        FROM game_records gr 
        JOIN member m ON gr.member_id = m.member_id 
        WHERE 1=1
        GROUP BY gr.game_type
        HAVING total_attempts >= 1
        ORDER BY success_rate DESC
    ";
    
    $completion_analysis_stmt = $pdo->prepare($completion_analysis_sql);
    $completion_analysis_stmt->execute();
    $completion_analysis = $completion_analysis_stmt->fetchAll();
} catch (Exception $e) {
    $completion_analysis = [];
}

// 3. 遊戲失敗統計分析
try {
    // 為遊戲失敗分析創建專門的過濾條件
    $game_failed_where_conditions = [];
    $game_failed_params = [];
    
    if ($date_filter) {
        switch ($date_filter) {
            case 'today':
                $game_failed_where_conditions[] = "DATE(ubl.created_at) = CURDATE()";
                break;
            case 'week':
                $game_failed_where_conditions[] = "ubl.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
                break;
            case 'month':
                $game_failed_where_conditions[] = "ubl.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
                break;
        }
    }
    
    if ($user_search) {
        $game_failed_where_conditions[] = "m.member_name LIKE ?";
        $game_failed_params[] = "%$user_search%";
    }
    
    $game_failed_where_clause = $game_failed_where_conditions ? 'AND ' . implode(' AND ', $game_failed_where_conditions) : '';
    
    $game_failed_sql = "
        SELECT 
            ubl.game_type,
            CASE 
                WHEN ubl.game_type = '反應力' THEN '接金蛋、節奏遊戲、看字選色'
                WHEN ubl.game_type = '記憶力' THEN '翻牌對對樂、圖片線索、追蹤犯人'
                WHEN ubl.game_type = '算術邏輯力' THEN '算菜錢、過河遊戲、2048'
                ELSE ubl.game_type
            END as game_names,
            COUNT(*) as total_failed,
            COUNT(DISTINCT ubl.member_id) as unique_players,
            COUNT(CASE WHEN JSON_EXTRACT(ubl.additional_data, '$.play_time') > 15 
                THEN 1 END) as long_play_failed
        FROM user_behavior_log ubl 
        LEFT JOIN member m ON ubl.member_id = m.member_id
        WHERE (ubl.action_type = 'game_failed' OR 
               (ubl.action_type = 'game_exit' AND JSON_EXTRACT(ubl.additional_data, '$.play_time') > 15))
        AND ubl.game_type IS NOT NULL
        $game_failed_where_clause
        GROUP BY ubl.game_type
        HAVING total_failed >= 1
        ORDER BY total_failed DESC
        LIMIT 15
    ";
    
    $game_failed_stmt = $pdo->prepare($game_failed_sql);
    $game_failed_stmt->execute($game_failed_params);
    $game_failed_data = $game_failed_stmt->fetchAll();
} catch (Exception $e) {
    $game_failed_data = [];
}

// 遊戲類型到名稱的映射
$game_type_to_name = [
    '反應力' => '反應力',
    '記憶力' => '記憶力', 
    '算術邏輯' => '算術邏輯',
    '算術邏輯力' => '算術邏輯力'
];

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
        .nav a.active { color: #0056b3; font-weight: bold; }
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
        .filters button:hover { background: #0056b3; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 20px; }
        .stat-card { background: white; padding: 20px; border-radius: 5px; text-align: center; }
        .stat-card h3 { margin: 0; color: #007bff; font-size: 24px; }
        .stat-card p { margin: 5px 0 0 0; color: #666; }
        .behavior-analysis { background: white; padding: 20px; margin-bottom: 20px; border-radius: 5px; }
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
        }
        .logout:hover { 
            background: #c82333; 
            text-decoration: none;
        }
        .action-type { padding: 4px 8px; border-radius: 3px; color: white; font-size: 12px; }
        .action-game_complete { background: #28a745; }
        .action-game_exit { background: #dc3545; }
        .action-game_failed { background: #ffc107; color: #000; }
        .warning { background-color: #fff3cd; }
        .highlight { background-color: #ffeaa7; }
        .success { background-color: #d4edda; }
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
            <a href="game_records.php">📊 遊戲紀錄</a>
            <a href="user_behavior.php" class="active">👤 行為軌跡</a>
            <a href="question_management.php">🎯 遊戲管理</a>
            <a href="user_management.php">👥 用戶管理</a>
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
                        <option value="game_failed" <?php echo $action_type_filter === 'game_failed' ? 'selected' : ''; ?>>遊戲失敗</option>
                    </select>
                </div>
                <div>
                    <label>日期範圍：</label>
                    <select name="date_filter">
                        <option value="">全部</option>
                        <option value="today" <?php echo $date_filter === 'today' ? 'selected' : ''; ?>>今天</option>
                        <option value="week" <?php echo $date_filter === 'week' ? 'selected' : ''; ?>>最近7天</option>
                        <option value="month" <?php echo $date_filter === 'month' ? 'selected' : ''; ?>>最近30天</option>
                    </select>
                </div>
                <button type="submit">篩選</button>
                <button type="button" onclick="window.location.href='user_behavior.php'">重置</button>
            </form>
        </div>
        
        <!-- 統計卡片 -->
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
                <h3><?php echo $stats['exit_rate']; ?>%</h3>
                <p>遊戲退出率</p>
            </div>
        </div>
        
        <!-- 行為類型分布 -->
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
                            <span class="action-type <?php 
                                switch ($type['action_type']) {
                                    case 'game_complete':
                                        echo 'action-game_complete';
                                        break;
                                    case 'game_exit':
                                        echo 'action-game_exit';
                                        break;
                                    case 'game_failed':
                                        echo 'action-game_failed';
                                        break;
                                    default:
                                        echo 'action-default';
                                }
                            ?>">
                                <?php 
                                switch ($type['action_type']) {
                                    case 'game_complete':
                                        echo '遊戲完成';
                                        break;
                                    case 'game_exit':
                                        echo '遊戲退出';
                                        break;
                                    case 'game_failed':
                                        echo '遊戲失敗';
                                        break;
                                    default:
                                        echo htmlspecialchars($type['action_type']);
                                }
                                ?>
                            </span>
                        </td>
                        <td><?php echo number_format($type['count']); ?></td>
                        <td><?php echo $stats['total_actions'] > 0 ? round(($type['count'] / $stats['total_actions']) * 100, 1) : 0; ?>%</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- 1. 遊戲退出統計分析 -->
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
                            <th>遊戲名稱</th>
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
                                <td><?php echo htmlspecialchars($row['game_names']); ?></td>
                                <td><strong><?php echo number_format($row['total_exits']); ?></strong></td>
                                <td><?php echo number_format($row['unique_players']); ?></td>
                                <td><?php echo $row['unique_players'] > 0 ? round($row['total_exits'] / $row['unique_players'], 1) : 0; ?> 次/人</td>
                                <td><?php echo number_format($row['quick_exits_15s']); ?></td>
                                <td>
                                    <?php echo $quick_exit_rate_15s; ?>%
                                    <?php if ($quick_exit_rate_15s > 30): ?>
                                        <span style="color: #dc3545;">⚠️ 需要關注</span>
                                    <?php elseif ($quick_exit_rate_15s > 15): ?>
                                        <span style="color: #ffc107;">⚠️ 注意</span>
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
        
        <!-- 2. 遊戲完成率分析 -->
        <div class="behavior-analysis">
            <h2>📈 遊戲完成率分析</h2>
            <p><em>分析各遊戲的成功率、用戶表現和參與情況</em></p>
            
            <?php if (empty($completion_analysis)): ?>
                <p style="color: #666; text-align: center; padding: 20px;">暫無遊戲完成率數據</p>
            <?php else: ?>
            <table>
                <thead>
                    <tr>
                            <th>遊戲類型</th>
                            <th>遊戲名稱</th>
                            <th>總嘗試次數</th>
                            <th>成功次數</th>
                            <th>參與玩家數</th>
                            <th>平均完成率</th>
                            <th>成功率</th>
                            <th>平均分數</th>
                            <th>平均遊玩時間</th>
                    </tr>
                </thead>
                <tbody>
                        <?php foreach ($completion_analysis as $analysis): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($game_type_to_name[$analysis['game_type']] ?? $analysis['game_type']); ?></td>
                            <td><?php echo htmlspecialchars($analysis['game_names']); ?></td>
                            <td><?php echo number_format($analysis['total_attempts']); ?></td>
                            <td><?php echo number_format($analysis['successful_attempts']); ?></td>
                            <td><?php echo number_format($analysis['unique_players']); ?></td>
                            <td><?php echo $analysis['unique_players'] > 0 ? round($analysis['successful_attempts'] / $analysis['unique_players'], 1) : 0; ?> 次/人</td>
                            <td>
                                <span style="color: <?php echo $analysis['success_rate'] >= 70 ? '#28a745' : ($analysis['success_rate'] >= 50 ? '#ffc107' : '#dc3545'); ?>; font-weight: bold;">
                                    <?php echo $analysis['success_rate']; ?>%
                            </span>
                        </td>
                            <td><?php echo round($analysis['avg_score']); ?></td>
                            <td><?php 
                                $avg_seconds = $analysis['avg_playtime'] ?? 0;
                                $avg_minutes = floor($avg_seconds / 60);
                                echo $avg_minutes > 0 ? $avg_minutes . '分' . ($avg_seconds % 60) . '秒' : $avg_seconds . '秒';
                            ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        
        <!-- 3. 遊戲失敗統計分析 -->
        <div class="behavior-analysis">
            <h2>❌ 遊戲失敗統計分析</h2>
            <p><em>分析各遊戲的長時間退出（失敗）次數和參與玩家數量</em></p>
            
            <?php if (empty($game_failed_data)): ?>
                <p style="color: #666; text-align: center; padding: 20px;">暫無遊戲失敗數據</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>遊戲類型</th>
                            <th>遊戲名稱</th>
                            <th>失敗次數</th>
                            <th>參與玩家數</th>
                            <th>平均失敗率</th>
                            <th>長時間失敗(>15秒)</th>
                            <th>長時間失敗率</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($game_failed_data as $row): ?>
                            <?php 
                            $long_failed_rate = $row['total_failed'] > 0 ? round(($row['long_play_failed'] / $row['total_failed']) * 100, 1) : 0;
                            $row_class = $long_failed_rate > 50 ? 'warning' : ($long_failed_rate > 30 ? 'highlight' : 'success');
                            ?>
                            <tr class="<?php echo $row_class; ?>">
                                <td><?php echo htmlspecialchars($game_type_to_name[$row['game_type']] ?? $row['game_type']); ?></td>
                                <td><?php echo htmlspecialchars($row['game_names']); ?></td>
                                <td><strong><?php echo number_format($row['total_failed']); ?></strong></td>
                                <td><?php echo number_format($row['unique_players']); ?></td>
                                <td><?php echo $row['unique_players'] > 0 ? round($row['total_failed'] / $row['unique_players'], 1) : 0; ?> 次/人</td>
                                <td><?php echo number_format($row['long_play_failed']); ?></td>
                                <td>
                                    <?php echo $long_failed_rate; ?>%
                                    <?php if ($long_failed_rate > 50): ?>
                                        <span style="color: #dc3545;">⚠️ 需要關注</span>
                                    <?php elseif ($long_failed_rate > 30): ?>
                                        <span style="color: #ffc107;">⚠️ 注意</span>
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
    </div>
</body>
</html> 