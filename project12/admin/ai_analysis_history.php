<?php
session_start();
require_once '../db_connect.php';

// 檢查管理員權限
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$admin_name = $_SESSION['admin_name'] ?? '管理員';

// 獲取查詢參數
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$member_filter = isset($_GET['member_id']) ? intval($_GET['member_id']) : 0;

try {
    // 構建查詢條件
    $where_conditions = [];
    $params = [];
    
    if ($search) {
        $where_conditions[] = "(m.member_name LIKE ? OR aah.player_type LIKE ? OR aah.description LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    if ($member_filter) {
        $where_conditions[] = "aah.member_id = ?";
        $params[] = $member_filter;
    }
    
    $where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // 獲取總記錄數
    $count_sql = "
        SELECT COUNT(*) 
        FROM ai_analysis_history aah
        JOIN member m ON aah.member_id = m.member_id
        $where_clause
    ";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetchColumn();
    $total_pages = ceil($total_records / $limit);
    
    // 獲取歷史記錄
    $history_sql = "
        SELECT 
            aah.*,
            m.member_name,
            m.account
        FROM ai_analysis_history aah
        JOIN member m ON aah.member_id = m.member_id
        $where_clause
        ORDER BY aah.created_at DESC
        LIMIT $limit OFFSET $offset
    ";
    $history_stmt = $pdo->prepare($history_sql);
    $history_stmt->execute($params);
    $records = $history_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 獲取所有用戶列表（用於篩選）
    $users_sql = "SELECT member_id, member_name, account FROM member ORDER BY member_name";
    $users_stmt = $pdo->query($users_sql);
    $users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 統計數據
    $stats_sql = "
        SELECT 
            COUNT(*) as total_analyses,
            COUNT(DISTINCT member_id) as unique_users,
            AVG(reaction_score) as avg_reaction,
            AVG(memory_score) as avg_memory,
            AVG(logic_score) as avg_logic
        FROM ai_analysis_history
    ";
    $stats_stmt = $pdo->query($stats_sql);
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error = '獲取數據失敗：' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI分析歷史記錄 - 後台管理</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; }
        .header { background: white; padding: 20px; margin-bottom: 20px; border-radius: 5px; }
        .nav { background: white; padding: 15px; margin-bottom: 20px; border-radius: 5px; }
        .nav a { margin-right: 20px; text-decoration: none; color: #007bff; }
        .nav a:hover { text-decoration: underline; }
        .nav a.active { color: #0056b3; font-weight: bold; }
        .logout { float: right; background: #dc3545; color: white; padding: 8px 16px; text-decoration: none; border-radius: 5px; font-weight: bold; transition: background-color 0.3s ease; margin-top: -85px; }
        .logout:hover { background: #c82333; text-decoration: none; }
        
        .search-filters {
            background: white;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        
        .filter-row {
            display: flex;
            gap: 15px;
            align-items: end;
            flex-wrap: wrap;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .filter-group label {
            font-weight: bold;
            color: #333;
            font-size: 14px;
        }
        
        .filter-group input,
        .filter-group select {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 3px;
            font-size: 14px;
        }
        
        .search-btn {
            padding: 8px 15px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-size: 14px;
            min-width: 80px;
        }
        
        .search-btn:hover {
            background: #0056b3;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 5px;
            text-align: center;
        }
        
        .stat-card h3 {
            margin: 0 0 10px 0;
            color: #007bff;
            font-size: 24px;
        }
        
        .stat-card p {
            margin: 0;
            color: #666;
            font-size: 14px;
        }
        
        .history-table {
            background: white;
            border-radius: 5px;
            overflow: hidden;
        }
        
        .table-header {
            background: #f8f9fa;
            padding: 15px 20px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .table-header h3 {
            margin: 0;
            color: #333;
        }
        
        .table-content {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }
        
        th {
            background: #f8f9fa;
            font-weight: bold;
            color: #333;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        .player-type {
            font-weight: bold;
            color: #007bff;
        }
        
        .scores {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .score-item {
            background: #e9ecef;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .score-reaction { background: #ffe6e6; color: #d63384; }
        .score-memory { background: #e6f3ff; color: #0d6efd; }
        .score-logic { background: #e6ffe6; color: #198754; }
        
        .description {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            padding: 20px;
            background: white;
            border-top: 1px solid #dee2e6;
        }
        
        .pagination a,
        .pagination span {
            padding: 8px 12px;
            border: 1px solid #dee2e6;
            border-radius: 3px;
            text-decoration: none;
            color: #007bff;
        }
        
        .pagination .current {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }
        
        .pagination a:hover {
            background: #e9ecef;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        
        .ai-enhanced {
            background: #007bff;
            color: white;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🤖 AI分析歷史記錄</h1>
            <p>歡迎，<?php echo htmlspecialchars($admin_name); ?></p>
            <a href="logout.php" class="logout">登出</a>
        </div>
        
        <div class="nav">
            <a href="game_records.php">🎮 遊戲紀錄</a>
            <a href="user_behavior.php">👤 行為軌跡</a>
            <a href="question_management.php">🎯 遊戲管理</a>
            <a href="user_management.php">👥 用戶管理</a>
            <a href="ability_analysis.php">🧠 能力分析</a>
            <a href="ai_analysis_history.php" class="active">🤖 AI分析歷史</a>
            <a href="test_results.php">📈 測試結果</a>
            <a href="custom_score_trend.php">📊 趨勢分析</a>
            <a href="baseline_time_management.php">📊 雷達圖分析</a>
        </div>
        
        <!-- 統計數據 -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php echo number_format($stats['total_analyses'] ?? 0); ?></h3>
                <p>🔢 總分析次數</p>
            </div>
            <div class="stat-card">
                <h3><?php echo number_format($stats['unique_users'] ?? 0); ?></h3>
                <p>👥 使用用戶數</p>
            </div>
            <div class="stat-card">
                <h3><?php echo number_format($stats['avg_reaction'] ?? 0, 1); ?></h3>
                <p>⚡ 平均反應力</p>
            </div>
            <div class="stat-card">
                <h3><?php echo number_format($stats['avg_memory'] ?? 0, 1); ?></h3>
                <p>🧠 平均記憶力</p>
            </div>
            <div class="stat-card">
                <h3><?php echo number_format($stats['avg_logic'] ?? 0, 1); ?></h3>
                <p>💡 平均邏輯力</p>
            </div>
        </div>
        
        <!-- 搜尋和篩選 -->
        <div class="search-filters">
            <form method="GET" class="filter-row">
                <div class="filter-group">
                    <label>🔍 搜尋</label>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="用戶名稱、玩家類型或描述">
                </div>
                <div class="filter-group">
                    <label>👤 用戶篩選</label>
                    <select name="member_id">
                        <option value="0">全部用戶</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['member_id']; ?>" <?php echo $member_filter == $user['member_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['member_name'] . ' (' . $user['account'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>&nbsp;</label>
                    <button type="submit" class="search-btn">🔍 搜尋</button>
                </div>
            </form>
        </div>
        
        <!-- 歷史記錄表格 -->
        <div class="history-table">
            <div class="table-header">
                <h3>📋 AI分析歷史記錄 (共 <?php echo number_format($total_records); ?> 筆)</h3>
            </div>
            <div class="table-content">
                <?php if (empty($records)): ?>
                    <div class="no-data">
                        <p>😔 沒有找到符合條件的記錄</p>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>⏰ 時間</th>
                                <th>👤 用戶</th>
                                <th>🎭 玩家類型</th>
                                <th>📊 能力分數</th>
                                <th>📝 分析說明</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($records as $record): ?>
                                <tr>
                                    <td>
                                        <div style="font-size: 14px; font-weight: 600;">
                                            <?php echo date('Y/m/d', strtotime($record['created_at'])); ?>
                                        </div>
                                        <div style="font-size: 12px; color: #666;">
                                            <?php echo date('H:i:s', strtotime($record['created_at'])); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="font-weight: 600;"><?php echo htmlspecialchars($record['member_name']); ?></div>
                                        <div style="font-size: 12px; color: #666;"><?php echo htmlspecialchars($record['account']); ?></div>
                                    </td>
                                    <td>
                                        <span class="player-type"><?php echo htmlspecialchars($record['player_type']); ?></span>
                                    </td>
                                    <td>
                                        <div class="scores">
                                            <span class="score-item score-reaction">⚡ 反應: <?php echo number_format($record['reaction_score'], 1); ?></span>
                                            <span class="score-item score-memory">🧠 記憶: <?php echo number_format($record['memory_score'], 1); ?></span>
                                            <span class="score-item score-logic">💡 邏輯: <?php echo number_format($record['logic_score'], 1); ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="description" title="<?php echo htmlspecialchars($record['description']); ?>">
                                            <?php echo htmlspecialchars($record['description']); ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            
            <!-- 分頁 -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&member_id=<?php echo $member_filter; ?>">⬅️ 上一頁</a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="current"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&member_id=<?php echo $member_filter; ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&member_id=<?php echo $member_filter; ?>">➡️ 下一頁</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
