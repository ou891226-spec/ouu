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
            gr.game_type,
            g.game_name as game_names,
            COUNT(CASE WHEN gr.status = 'exited' THEN 1 END) as game_exits,
            ROUND(
                COUNT(CASE WHEN gr.status = 'exited' THEN 1 END) * 100.0 / COUNT(*), 2
            ) as exit_rate
        FROM game_records gr
        LEFT JOIN games g ON gr.game_id = g.game_id
        WHERE gr.game_type IS NOT NULL
        $game_exit_where_clause
        GROUP BY gr.game_id, gr.game_type, g.game_name
        HAVING (game_exits >= 1 OR COUNT(*) >= 1)
        ORDER BY gr.game_type, exit_rate DESC
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
            g.game_name as game_names,
            COUNT(CASE WHEN (
                (gr.difficulty = 'easy' AND gr.score = 20) OR
                (gr.difficulty = 'normal' AND gr.score = 50) OR
                (gr.difficulty = 'hard' AND gr.score = 100)
            ) THEN 1 END) as successful_attempts,
            ROUND(
                COUNT(CASE WHEN (
                    (gr.difficulty = 'easy' AND gr.score = 20) OR
                    (gr.difficulty = 'normal' AND gr.score = 50) OR
                    (gr.difficulty = 'hard' AND gr.score = 100)
                ) THEN 1 END) * 100.0 / 
                COUNT(CASE WHEN (
                    (gr.difficulty = 'easy' AND gr.score = 20) OR
                    (gr.difficulty = 'normal' AND gr.score = 50) OR
                    (gr.difficulty = 'hard' AND gr.score = 100) OR
                    (gr.difficulty = 'easy' AND gr.score < 20) OR
                    (gr.difficulty = 'normal' AND gr.score < 50) OR
                    (gr.difficulty = 'hard' AND gr.score < 100)
                ) THEN 1 END), 2
            ) as success_rate,
            CONCAT(
                ROUND(COUNT(CASE WHEN gr.difficulty = 'easy' THEN 1 END) * 100.0 / COUNT(*), 1), '% 簡單, ',
                ROUND(COUNT(CASE WHEN gr.difficulty = 'normal' THEN 1 END) * 100.0 / COUNT(*), 1), '% 普通, ',
                ROUND(COUNT(CASE WHEN gr.difficulty = 'hard' THEN 1 END) * 100.0 / COUNT(*), 1), '% 困難'
            ) as difficulty_distribution,
            ROUND(
                COUNT(DISTINCT CASE WHEN gr2.record_id IS NOT NULL THEN gr.member_id END) * 100.0 / 
                COUNT(DISTINCT gr.member_id), 2
            ) as retry_rate
        FROM game_records gr 
        LEFT JOIN games g ON gr.game_id = g.game_id
        LEFT JOIN game_records gr2 ON (
            gr.member_id = gr2.member_id 
            AND gr.game_id = gr2.game_id 
            AND gr2.play_date > gr.play_date 
            AND (
                (gr2.difficulty = 'easy' AND gr2.score = 20) OR
                (gr2.difficulty = 'normal' AND gr2.score = 50) OR
                (gr2.difficulty = 'hard' AND gr2.score = 100) OR
                (gr2.difficulty = 'easy' AND gr2.score < 20) OR
                (gr2.difficulty = 'normal' AND gr2.score < 50) OR
                (gr2.difficulty = 'hard' AND gr2.score < 100)
            )
            AND gr2.status != 'entered'
        )
        JOIN member m ON gr.member_id = m.member_id 
        WHERE (
            (gr.difficulty = 'easy' AND gr.score = 20) OR
            (gr.difficulty = 'normal' AND gr.score = 50) OR
            (gr.difficulty = 'hard' AND gr.score = 100) OR
            (gr.difficulty = 'easy' AND gr.score < 20) OR
            (gr.difficulty = 'normal' AND gr.score < 50) OR
            (gr.difficulty = 'hard' AND gr.score < 100)
        )
        AND gr.status != 'entered'
        GROUP BY gr.game_id, gr.game_type, g.game_name
        HAVING successful_attempts >= 1
        ORDER BY gr.game_type, success_rate DESC
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
            failed.game_type,
            failed.game_names,
            failed.total_failed,
            failed.failure_rate,
            failed.difficulty_distribution,
            COALESCE(retry.retry_rate, 0) as retry_rate
        FROM (
            SELECT 
                gr.game_type,
                g.game_name as game_names,
                COUNT(*) as total_failed,
                ROUND(COUNT(*) * 100.0 / (
                    SELECT COUNT(*) 
                    FROM game_records gr2 
                    WHERE gr2.game_type = gr.game_type 
                    AND gr2.status != 'entered'
                ), 2) as failure_rate,
                CONCAT(
                    ROUND(COUNT(CASE WHEN gr.difficulty = 'easy' THEN 1 END) * 100.0 / COUNT(*), 1), '% 簡單, ',
                    ROUND(COUNT(CASE WHEN gr.difficulty = 'normal' THEN 1 END) * 100.0 / COUNT(*), 1), '% 普通, ',
                    ROUND(COUNT(CASE WHEN gr.difficulty = 'hard' THEN 1 END) * 100.0 / COUNT(*), 1), '% 困難'
                ) as difficulty_distribution
            FROM game_records gr
            LEFT JOIN games g ON gr.game_id = g.game_id
            WHERE (
                (gr.difficulty = 'easy' AND gr.score < 20) OR
                (gr.difficulty = 'normal' AND gr.score < 50) OR
                (gr.difficulty = 'hard' AND gr.score < 100)
            )
            AND gr.status != 'entered'
            $game_failed_where_clause
            GROUP BY gr.game_type, g.game_name
            HAVING total_failed >= 1
        ) failed
        LEFT JOIN (
            SELECT 
                gr.game_type,
                ROUND(
                    COUNT(DISTINCT CASE WHEN gr2.record_id IS NOT NULL THEN gr.member_id END) * 100.0 / 
                    COUNT(DISTINCT gr.member_id), 2
                ) as retry_rate
            FROM game_records gr
            LEFT JOIN game_records gr2 ON (
                gr.member_id = gr2.member_id 
                AND gr.game_type = gr2.game_type 
                AND gr2.play_date > gr.play_date 
                AND gr2.status != 'entered'
            )
            WHERE gr.status != 'entered'
            $game_failed_where_clause
            GROUP BY gr.game_type
        ) retry ON failed.game_type = retry.game_type
        ORDER BY failed.game_type, failed.total_failed DESC
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
        
        /* 遊戲類型背景色 */
        .reaction { background-color: #e3f2fd; } /* 反應力 - 淺藍色 */
        .logic { background-color: #f3e5f5; } /* 算術邏輯力 - 淺紫色 */
        .memory { background-color: #e8f5e8; } /* 記憶力 - 淺綠色 */
        .default { background-color: #fafafa; } /* 預設 - 淺灰色 */
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
            <p><em>分析各遊戲的退出率、完成率和平均遊玩時間</em></p>
            
            <?php if (empty($game_exit_data)): ?>
                <p style="color: #666; text-align: center; padding: 20px;">暫無遊戲退出數據</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>遊戲類型</th>
                            <th>遊戲名稱</th>
                            <th>退出次數</th>
                            <th>退出率</th>
                        </tr>
                    </thead>
                    <tbody>
                            <?php 
                        $current_type = '';
                        $type_colors = [
                            '反應力' => 'reaction',
                            '算術邏輯力' => 'logic', 
                            '記憶力' => 'memory'
                        ];
                        foreach ($game_exit_data as $row): 
                            // 根據遊戲類型設定背景色
                            $type_class = $type_colors[$row['game_type']] ?? 'default';
                        ?>
                            <tr class="<?php echo $type_class; ?>">
                                <td><?php echo htmlspecialchars($game_type_to_name[$row['game_type']] ?? $row['game_type']); ?></td>
                                <td><?php echo htmlspecialchars($row['game_names']); ?></td>
                                <td><?php echo number_format($row['game_exits']); ?></td>
                                <td>
                                    <?php echo $row['exit_rate']; ?>%
                                    <?php if ($row['exit_rate'] > 30): ?>
                                        <span style="color: #dc3545;">⚠️ 高退出率</span>
                                        <small style="color: #666;">(>30%)</small>
                                    <?php elseif ($row['exit_rate'] > 15): ?>
                                        <span style="color: #ffc107;">⚠️ 需注意</span>
                                        <small style="color: #666;">(15-30%)</small>
                                    <?php elseif ($row['exit_rate'] <= 10): ?>
                                        <span style="color: #28a745;">✅ 表現優秀</span>
                                        <small style="color: #666;">(≤10%)</small>
                                    <?php else: ?>
                                        <span style="color: #28a745;">✅ 正常</span>
                                        <small style="color: #666;">(10-15%)</small>
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
                            <th>成功次數</th>
                            <th>成功率</th>
                            <th>難度分布</th>
                            <th>重試率</th>
                    </tr>
                </thead>
                <tbody>
                        <?php 
                        $type_colors = [
                            '反應力' => 'reaction',
                            '算術邏輯力' => 'logic', 
                            '記憶力' => 'memory'
                        ];
                        foreach ($completion_analysis as $analysis): 
                            $type_class = $type_colors[$analysis['game_type']] ?? 'default';
                        ?>
                        <tr class="<?php echo $type_class; ?>">
                            <td><?php echo htmlspecialchars($game_type_to_name[$analysis['game_type']] ?? $analysis['game_type']); ?></td>
                            <td><?php echo htmlspecialchars($analysis['game_names']); ?></td>
                            <td><strong><?php echo number_format($analysis['successful_attempts']); ?></strong></td>
                            <td>
                                    <?php echo $analysis['success_rate']; ?>%
                                <?php if ($analysis['success_rate'] >= 70): ?>
                                    <span style="color: #28a745;">✅ 表現優秀</span>
                                    <small style="color: #666;">(≥70%)</small>
                                <?php elseif ($analysis['success_rate'] >= 50): ?>
                                    <span style="color: #ffc107;">⚠️ 需要改進</span>
                                    <small style="color: #666;">(50-69%)</small>
                                <?php else: ?>
                                    <span style="color: #dc3545;">⚠️ 需要關注</span>
                                    <small style="color: #666;">(<50%)</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <small style="color: #666; font-size: 12px;">
                                    <?php echo htmlspecialchars($analysis['difficulty_distribution']); ?>
                                </small>
                            </td>
                            <td>
                                <?php 
                                $retry_rate = $analysis['retry_rate'] ?? 0;
                                if ($retry_rate >= 70): ?>
                                    <span style="color: #28a745; font-weight: bold;"><?php echo $retry_rate; ?>%</span>
                                    <small style="color: #666;">高重試</small>
                                <?php elseif ($retry_rate >= 40): ?>
                                    <span style="color: #ffc107; font-weight: bold;"><?php echo $retry_rate; ?>%</span>
                                    <small style="color: #666;">中等重試</small>
                                <?php else: ?>
                                    <span style="color: #dc3545; font-weight: bold;"><?php echo $retry_rate; ?>%</span>
                                    <small style="color: #666;">低重試</small>
                                <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        
        <!-- 3. 遊戲失敗統計分析 -->
        <div class="behavior-analysis">
            <h2>❌ 遊戲失敗統計分析</h2>
            <p><em>分析各遊戲的失敗次數和參與玩家數量</em></p>
            
            <?php if (empty($game_failed_data)): ?>
                <p style="color: #666; text-align: center; padding: 20px;">暫無遊戲失敗數據</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>遊戲類型</th>
                            <th>遊戲名稱</th>
                            <th>失敗次數</th>
                            <th>失敗率</th>
                            <th>難度分布</th>
                            <th>重試率</th>
                        </tr>
                    </thead>
                    <tbody>
                            <?php 
                        $type_colors = [
                            '反應力' => 'reaction',
                            '算術邏輯力' => 'logic', 
                            '記憶力' => 'memory'
                        ];
                        foreach ($game_failed_data as $row): 
                            $type_class = $type_colors[$row['game_type']] ?? 'default';
                        ?>
                            <tr class="<?php echo $type_class; ?>">
                                <td><?php echo htmlspecialchars($game_type_to_name[$row['game_type']] ?? $row['game_type']); ?></td>
                                <td><?php echo htmlspecialchars($row['game_names']); ?></td>
                                <td><strong><?php echo number_format($row['total_failed']); ?></strong></td>
                                <td>
                                    <?php echo $row['failure_rate']; ?>%
                                    <?php if ($row['failure_rate'] > 70): ?>
                                        <span style="color: #dc3545;">⚠️ 需要關注</span>
                                        <small style="color: #666;">(>70%)</small>
                                    <?php elseif ($row['failure_rate'] > 50): ?>
                                        <span style="color: #ffc107;">⚠️ 注意</span>
                                        <small style="color: #666;">(50-70%)</small>
                                    <?php elseif ($row['failure_rate'] > 30): ?>
                                        <span style="color: #ffc107;">⚠️ 需注意</span>
                                        <small style="color: #666;">(30-50%)</small>
                                    <?php else: ?>
                                        <span style="color: #28a745;">✅ 表現良好</span>
                                        <small style="color: #666;">(<30%)</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small style="color: #666; font-size: 12px;">
                                        <?php echo htmlspecialchars($row['difficulty_distribution']); ?>
                                    </small>
                                </td>
                                <td>
                                    <?php 
                                    $retry_rate = $row['retry_rate'] ?? 0;
                                    if ($retry_rate >= 70): ?>
                                        <span style="color: #28a745; font-weight: bold;"><?php echo $retry_rate; ?>%</span>
                                        <small style="color: #666;">高重試</small>
                                    <?php elseif ($retry_rate >= 40): ?>
                                        <span style="color: #ffc107; font-weight: bold;"><?php echo $retry_rate; ?>%</span>
                                        <small style="color: #666;">中等重試</small>
                                    <?php else: ?>
                                        <span style="color: #dc3545; font-weight: bold;"><?php echo $retry_rate; ?>%</span>
                                        <small style="color: #666;">低重試</small>
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