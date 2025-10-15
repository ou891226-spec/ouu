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
$game_type_filter = $_GET['game_type'] ?? ''; // 類型
$game_name_filter = $_GET['game_name'] ?? ''; // 遊戲名稱
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

if ($game_name_filter) {
    // 根據遊戲名稱篩選，需要結合game_type和game_id
    switch ($game_name_filter) {
        case '看字選色':
            $where_conditions[] = "gr.game_type = '反應力' AND gr.game_id = 1";
            break;
        case '接金蛋':
            $where_conditions[] = "gr.game_type = '反應力' AND gr.game_id = 2";
            break;
        case '算菜錢':
            $where_conditions[] = "gr.game_type = '算術邏輯力' AND gr.game_id = 3";
            break;
        case '2048':
            $where_conditions[] = "gr.game_type = '算術邏輯力' AND gr.game_id = 4";
            break;
        case '翻牌對對樂':
            $where_conditions[] = "gr.game_type = '記憶力' AND gr.game_id = 5";
            break;
        case '追蹤犯人':
            $where_conditions[] = "gr.game_type = '記憶力' AND gr.game_id = 6";
            break;
        case '節奏遊戲':
            $where_conditions[] = "gr.game_type = '反應力' AND gr.game_id = 7";
            break;
        case '線索遊戲':
            $where_conditions[] = "gr.game_type = '記憶力' AND gr.game_id = 8";
            break;
        case '數字排排樂':
            $where_conditions[] = "gr.game_type = '算術邏輯力' AND gr.game_id = 10";
            break;
    }
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

// 獲取遊戲記錄，並關聯行為軌跡數據
$sql = "
    SELECT 
        gr.*, 
        m.member_name, 
        g.game_name,
        ubl.action_type as behavior_type,
        ubl.created_at as behavior_time
    FROM game_records gr 
    JOIN member m ON gr.member_id = m.member_id 
    LEFT JOIN games g ON gr.game_id = g.game_id 
    LEFT JOIN (
        SELECT 
            ubl.member_id,
            ubl.game_type,
            ubl.action_type,
            ubl.created_at,
            ROW_NUMBER() OVER (
                PARTITION BY ubl.member_id, ubl.game_type, DATE(ubl.created_at) 
                ORDER BY ubl.created_at DESC
            ) as rn
        FROM user_behavior_log ubl 
        WHERE ubl.action_type IN ('game_complete', 'game_exit')
    ) ubl ON gr.member_id = ubl.member_id 
        AND ubl.game_type = gr.game_type 
        AND DATE(gr.play_date) = DATE(ubl.created_at)
        AND ubl.rn = 1
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
        SUM(CASE WHEN gr.play_time IS NOT NULL THEN gr.play_time ELSE 0 END) as total_playtime,
        AVG(gr.score) as avg_score,
        AVG(CASE WHEN gr.play_time IS NOT NULL THEN gr.play_time ELSE NULL END) as avg_playtime
    FROM game_records gr 
    JOIN member m ON gr.member_id = m.member_id 
    LEFT JOIN games g ON gr.game_id = g.game_id 
    $where_clause
";
$stats_stmt = $pdo->prepare($stats_sql);
$stats_stmt->execute($params);
$stats = $stats_stmt->fetch();

// 定義遊戲類型到中文名稱的對應
$game_type_to_name = [
    // 反應力相關
    '反應力' => '接金蛋',
    '接金蛋遊戲' => '接金蛋',
    '接金蛋' => '接金蛋',
    '節奏遊戲' => '節奏遊戲',
    '節奏感' => '節奏遊戲',
    '看字選色遊戲' => '看字選色',
    
    // 記憶力相關
    '記憶力' => '翻牌對對樂',
    '翻牌對對樂' => '翻牌對對樂',
    '圖片線索問答' => '圖片線索問答',
    '邏輯推理' => '圖片線索問答',
    '追蹤犯人遊戲' => '追蹤犯人',
    '追蹤能力' => '追蹤犯人',
    
    // 算術邏輯力相關
    '算術邏輯力' => '算菜錢',
    '算術邏輯力' => '數字排排樂',
    '算菜錢遊戲' => '算菜錢',
    '數字排排樂' => '數字排排樂',
    '邏輯力' => '2048',
    '2048' => '2048',
    
    // 雙人遊戲
    '算菜錢(雙人)' => '算菜錢(雙人)',
    '翻牌對對樂(雙人)' => '翻牌對對樂(雙人)'
];
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
        .action-type { padding: 4px 8px; border-radius: 3px; color: white; font-size: 12px; }
        .action-game_complete { background: #28a745; }
        .action-game_exit { background: #dc3545; }
        .action-game_failed { background: #ffc107; color: #000; }
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
            <a href="game_records.php" class="active">🎮 遊戲紀錄</a>
            <a href="user_behavior.php">👤 行為軌跡</a>
            <a href="question_management.php">🎯 遊戲管理</a>
            <a href="user_management.php">👥 用戶管理</a>
            <a href="ability_analysis.php">🧠 能力分析</a>
            <a href="ai_analysis_history.php">🤖 AI分析歷史</a>
            <a href="test_results.php">📈 測試結果</a>
            <a href="custom_score_trend.php">📊 趨勢分析</a>
            <a href="baseline_time_management.php">📊 雷達圖分析</a>
            <a href="delete_test_records.php" style="color: #dc3545;">🗑️ 刪除測試記錄</a>
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
                        <option value="反應力" <?php echo $game_type_filter === '反應力' ? 'selected' : ''; ?>>反應力</option>
                        <option value="記憶力" <?php echo $game_type_filter === '記憶力' ? 'selected' : ''; ?>>記憶力</option>
                        <option value="算術邏輯力" <?php echo $game_type_filter === '算術邏輯力' ? 'selected' : ''; ?>>算術邏輯力</option>
                    </select>
                </div>
                <div>
                    <label>遊戲名稱：</label>
                    <select name="game_name">
                        <option value="">全部</option>
                        <optgroup label="反應力">
                            <option value="接金蛋" <?php echo $game_name_filter === '接金蛋' ? 'selected' : ''; ?>>接金蛋</option>
                            <option value="節奏遊戲" <?php echo $game_name_filter === '節奏遊戲' ? 'selected' : ''; ?>>節奏遊戲</option>
                            <option value="看字選色" <?php echo $game_name_filter === '看字選色' ? 'selected' : ''; ?>>看字選色</option>
                        </optgroup>
                        <optgroup label="記憶力">
                            <option value="翻牌對對樂" <?php echo $game_name_filter === '翻牌對對樂' ? 'selected' : ''; ?>>翻牌對對樂</option>
                            <option value="圖片線索問答" <?php echo $game_name_filter === '圖片線索問答' ? 'selected' : ''; ?>>圖片線索問答</option>
                            <option value="追蹤犯人" <?php echo $game_name_filter === '追蹤犯人' ? 'selected' : ''; ?>>追蹤犯人</option>
                        </optgroup>
                        <optgroup label="算術邏輯力">
                            <option value="算菜錢" <?php echo $game_name_filter === '算菜錢' ? 'selected' : ''; ?>>算菜錢</option>
                            <option value="數字排排樂" <?php echo $game_name_filter === '數字排排樂' ? 'selected' : ''; ?>>數字排排樂</option>
                            <option value="2048" <?php echo $game_name_filter === '2048' ? 'selected' : ''; ?>>2048</option>
                        </optgroup>
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
                            <th>行為類型</th>
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
                        <td><?php 
                            // 統一算術邏輯相關的命名
                            $display_game_type = $record['game_type'];
                            if (in_array($record['game_type'], ['算術邏輯', '算術邏輯力', '算數邏輯力'])) {
                                $display_game_type = '算術邏輯力';
                            }
                            echo htmlspecialchars($display_game_type);
                        ?></td>
                        <td><?php 
                            // 特殊處理：根據game_id來判斷具體遊戲名稱
                            $game_name = '';
                            switch ($record['game_id']) {
                                case 1:
                                    $game_name = '看字選色';
                                    break;
                                case 2:
                                    $game_name = '接金蛋';
                                    break;
                                case 3:
                                    $game_name = '算菜錢';
                                    break;
                                case 4:
                                    $game_name = '2048';
                                    break;
                                case 5:
                                    $game_name = '翻牌對對樂';
                                    break;
                                case 6:
                                    $game_name = '追蹤犯人';
                                    break;
                                case 7:
                                    $game_name = '節奏遊戲';
                                    break;
                                case 8:
                                    $game_name = '圖片線索問答';
                                    break;
                                case 10:
                                    $game_name = '數字排排樂';
                                    break;
                                default:
                                    $game_name = $game_type_to_name[$record['game_type']] ?? $record['game_name'] ?? '-';
                                    break;
                            }
                            echo htmlspecialchars($game_name);
                        ?></td>
                        <td>
                            <?php 
                            // 根據遊戲記錄推斷行為類型
                            $behavior_type = '';
                            
                            // 根據遊戲記錄的狀態判斷行為類型
                            if ($record['status'] === 'completed') {
                                $behavior_type = 'game_complete';
                            } elseif ($record['status'] === 'exited') {
                                $behavior_type = 'game_exit';
                            } elseif ($record['status'] === 'failed') {
                                $behavior_type = 'game_failed';
                            } elseif ($record['behavior_type']) {
                                $behavior_type = $record['behavior_type'];
                            } else {
                                // 根據分數和遊玩時間推斷行為類型
                                if ($record['game_id'] == 4) {
                                    // 2048遊戲：分數0表示退出，分數>0表示完成
                                    if ($record['score'] > 0) {
                                        $behavior_type = 'game_complete';
                                    } else {
                                        $behavior_type = 'game_exit';
                                    }
                                } elseif (in_array($record['game_id'], [3, 10])) {
                                    // 算菜錢、數字排排樂：根據分數判斷（這些遊戲可能沒有準確的遊玩時間）
                                    if ($record['score'] > 0) {
                                        $behavior_type = 'game_complete';
                                    } else {
                                        $behavior_type = 'game_exit';
                                    }
                                } else {
                                    // 其他遊戲：根據分數和遊玩時間判斷
                                    if ($record['score'] > 0) {
                                        // 有分數表示過關，無論時間長短都是完成
                                        $behavior_type = 'game_complete';
                                    } elseif ($record['play_time'] <= 15 && $record['play_time'] > 0) {
                                        // 短時間退出
                                        $behavior_type = 'game_exit';
                                    } elseif ($record['play_time'] > 15) {
                                        // 玩了很久但沒得分，表示沒過關
                                        $behavior_type = 'game_exit';
                                    } elseif (!$record['play_time'] || $record['play_time'] == 0) {
                                        // 沒時間記錄，根據分數判斷
                                        $behavior_type = ($record['score'] > 0) ? 'game_complete' : 'game_exit';
                                    }
                                }
                            }
                            
                            if ($behavior_type): ?>
                                <span class="action-type action-<?php echo htmlspecialchars($behavior_type); ?>">
                                    <?php 
                                    $behavior_labels = [
                                        'game_complete' => '遊戲完成',
                                        'game_exit' => '遊戲退出',
                                        'game_failed' => '遊戲失敗'
                                    ];
                                    echo $behavior_labels[$behavior_type] ?? $behavior_type;
                                    ?>
                                </span>
                            <?php else: ?>
                                <span style="color: #999;">-</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $record['score']; ?></td>
                        <td><?php echo htmlspecialchars($record['difficulty'] ?? '一般'); ?></td>
                        <td><?php 
                            // 調試：檢查play_time的值
                            if (isset($record['play_time']) && $record['play_time'] !== null && $record['play_time'] !== '') {
                                echo $record['play_time'] . '秒';
                            } else {
                                echo '-';
                            }
                        ?></td>
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