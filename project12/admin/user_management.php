<?php
session_start();
require_once '../db.php';

// 檢查管理員登入狀態
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$admin_name = $_SESSION['admin_name'] ?? '管理員';

// 處理用戶操作
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_status':
                $user_id = $_POST['user_id'];
                $new_status = $_POST['status'];
                $stmt = $pdo->prepare("UPDATE member SET status = ? WHERE id = ?");
                $stmt->execute([$new_status, $user_id]);
                break;
            case 'delete_user':
                $user_id = $_POST['user_id'];
                try {
                    $pdo->beginTransaction();
                    
                    // 刪除相關的用戶數據
                    $tables_to_clean = [
                        'user_behavior_log',
                        'user_online_status', 
                        'game_records',
                        'member_tasks',
                        'member_achievements',
                        'daily_playtime',
                        'friend_requests',
                        'friend_relationships'
                    ];
                    
                    foreach ($tables_to_clean as $table) {
                        $stmt = $pdo->prepare("DELETE FROM $table WHERE member_id = ?");
                        $stmt->execute([$user_id]);
                    }
                    
                    // 最後刪除用戶本身
                    $stmt = $pdo->prepare("DELETE FROM member WHERE member_id = ?");
                    $stmt->execute([$user_id]);
                    
                    $pdo->commit();
                    $delete_message = "用戶 ID $user_id 已成功刪除";
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $delete_message = "刪除用戶失敗: " . $e->getMessage();
                }
                break;
        }
    }
}

// 獲取篩選參數
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// 構建查詢條件
$where_conditions = [];
$params = [];

// 暫時移除狀態篩選，因為 member 表可能沒有 status 欄位
// if ($status_filter !== '') {
//     $where_conditions[] = "status = ?";
//     $params[] = $status_filter;
// }

if ($search !== '') {
    $where_conditions[] = "(account LIKE ? OR member_name LIKE ? OR name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// 獲取總用戶數
$count_sql = "SELECT COUNT(*) FROM member $where_clause";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_users = $count_stmt->fetchColumn();
$total_pages = ceil($total_users / $per_page);

// 獲取用戶列表
$sql = "SELECT m.*, 
        (SELECT session_id FROM user_behavior_log ubl WHERE ubl.member_id = m.member_id ORDER BY ubl.created_at DESC LIMIT 1) as latest_session_id,
        (SELECT created_at FROM user_behavior_log ubl WHERE ubl.member_id = m.member_id ORDER BY ubl.created_at DESC LIMIT 1) as last_activity_time,
        (SELECT COUNT(*) FROM user_online_status uos WHERE uos.member_id = m.member_id AND uos.is_online = 1 AND uos.last_activity >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)) as recent_activity_count
        FROM member m $where_clause ORDER BY m.member_id DESC LIMIT $per_page OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

// 獲取統計數據
$stats_sql = "SELECT 
    COUNT(*) as total_users,
            (SELECT COUNT(DISTINCT member_id) FROM user_online_status WHERE is_online = 1 AND last_activity >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)) as active_users,
    (SELECT COUNT(*) FROM member) as inactive_users,
    (SELECT COUNT(*) FROM member) as new_users_7d,
    (SELECT COUNT(DISTINCT session_id) FROM user_behavior_log) as total_sessions
FROM member";
$stats_stmt = $pdo->prepare($stats_sql);
$stats_stmt->execute();
$stats = $stats_stmt->fetch();
?>

<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>用戶管理 - 後台管理系統</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; }
        .header { background: white; padding: 20px; margin-bottom: 20px; border-radius: 5px; }
        .nav { background: white; padding: 15px; margin-bottom: 20px; border-radius: 5px; }
        .nav a { margin-right: 20px; text-decoration: none; color: #007bff; }
        .nav a:hover { text-decoration: underline; }
        .nav a.active { color: #0056b3; font-weight: bold; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 20px; }
        .stat-card { background: white; padding: 20px; border-radius: 5px; text-align: center; }
        .stat-card h3 { margin: 0; color: #007bff; font-size: 24px; }
        .stat-card p { margin: 5px 0 0 0; color: #666; }
        .filters { background: white; padding: 20px; margin-bottom: 20px; border-radius: 5px; }
        .filter-form { display: flex; gap: 15px; align-items: center; flex-wrap: wrap; }
        .filter-form input, .filter-form select { padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        .filter-form button { 
            background: #007bff; 
            color: white; 
            border: none; 
            padding: 8px 15px; 
            border-radius: 3px; 
            cursor: pointer; 
            font-size: 14px; 
            min-width: 80px; 
        }
        .filter-form button:hover { background: #0056b3; }
        .reset-btn { 
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
        .reset-btn:hover { 
            background: #5a6268; 
            text-decoration: none; 
        }
        .user-list { background: white; padding: 20px; border-radius: 5px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; font-weight: bold; }
        .status-active { color: #28a745; font-weight: bold; }
        .status-inactive { color: #dc3545; font-weight: bold; }
        .pagination { margin-top: 20px; text-align: center; }
        .pagination a { 
            display: inline-block; 
            padding: 8px 12px; 
            margin: 0 4px; 
            border: 1px solid #ddd; 
            text-decoration: none; 
            color: #007bff; 
            border-radius: 4px; 
        }
        .pagination a:hover { background: #007bff; color: white; }
        .pagination .current { background: #007bff; color: white; }
        .action-btn { 
            padding: 4px 8px; 
            border: none; 
            border-radius: 3px; 
            cursor: pointer; 
            font-size: 12px; 
        }
        .btn-activate { background: #28a745; color: white; }
        .btn-deactivate { background: #dc3545; color: white; }
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
            <a href="game_records.php">📊 遊戲紀錄</a>
            <a href="user_behavior.php">👤 行為軌跡</a>
            <a href="question_management.php">🎯 遊戲管理</a>
            <a href="user_management.php" class="active">👥 用戶管理</a>
            <a href="ability_analysis.php">🧠 能力分析</a>
            <a href="test_results.php">📈 測試結果</a>
            <a href="custom_score_trend.php">📊 趨勢分析</a>
        </div>
        
        <div class="stats">
            <div class="stat-card">
                <h3><?php echo number_format($stats['total_users']); ?></h3>
                <p>總用戶數</p>
            </div>
            <div class="stat-card">
                <h3><?php echo number_format($stats['active_users']); ?></h3>
                <p>活躍用戶 (目前線上)</p>
            </div>
            <div class="stat-card">
                <h3><?php echo number_format($stats['new_users_7d']); ?></h3>
                <p>近7天新用戶</p>
            </div>
            <div class="stat-card">
                <h3><?php echo number_format($stats['total_sessions']); ?></h3>
                <p>總會話數</p>
            </div>
        </div>
        
        <div class="filters">
            <form method="GET" class="filter-form">
                <input type="text" name="search" placeholder="搜尋帳號/姓名" value="<?php echo htmlspecialchars($search); ?>">
                <!-- 暫時隱藏狀態篩選，因為 member 表可能沒有 status 欄位 -->
                <!-- <select name="status">
                    <option value="">所有狀態</option>
                    <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>活躍</option>
                    <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>非活躍</option>
                </select> -->
                <button type="submit">篩選</button>
                <a href="user_management.php" class="reset-btn">重置</a>
            </form>
        </div>
        
        <div class="user-list">
            <h2>用戶列表</h2>
            
            <?php if (isset($delete_message)): ?>
            <div style="background: <?php echo strpos($delete_message, '成功') !== false ? '#d4edda' : '#f8d7da'; ?>; 
                        color: <?php echo strpos($delete_message, '成功') !== false ? '#155724' : '#721c24'; ?>; 
                        padding: 10px; 
                        border: 1px solid <?php echo strpos($delete_message, '成功') !== false ? '#c3e6cb' : '#f5c6cb'; ?>; 
                        border-radius: 4px; 
                        margin-bottom: 20px;">
                <?php echo htmlspecialchars($delete_message); ?>
            </div>
            <?php endif; ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>帳號</th>
                        <th>姓名</th>
                        <th>狀態</th>
                        <th>最後活動</th>
                        <th>會話ID</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo $user['member_id']; ?></td>
                        <td><?php echo htmlspecialchars($user['account']); ?></td>
                        <td><?php echo htmlspecialchars($user['member_name'] ?? $user['name'] ?? '-'); ?></td>
                        <td>
                            <?php if ($user['recent_activity_count'] > 0): ?>
                                <span class="status-active">活躍</span>
                            <?php else: ?>
                                <span class="status-inactive">非活躍</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo isset($user['last_activity_time']) && $user['last_activity_time'] ? date('m月d日 H:i', strtotime($user['last_activity_time'])) : '-'; ?></td>
                        <td>
                            <?php if (isset($user['latest_session_id']) && $user['latest_session_id']): ?>
                                <?php echo substr($user['latest_session_id'], 0, 20) . '...'; ?>
                            <?php else: ?>
                                <span style="color: #999; font-style: italic;">無活動記錄</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- 分頁 -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page-1; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>">上一頁</a>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
                    <a href="?page=<?php echo $i; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>" 
                       class="<?php echo $i === $page ? 'current' : ''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page+1; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>">下一頁</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <div style="margin-top: 20px; color: #6c757d; text-align: center;">
                顯示 <?php echo $offset + 1; ?> - <?php echo min($offset + $per_page, $total_users); ?> 共 <?php echo $total_users; ?> 個用戶
            </div>
        </div>
    </div>
</body>
</html> 