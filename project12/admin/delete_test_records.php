<?php
session_start();
require_once '../db.php';

// 檢查管理員權限
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$admin_name = $_SESSION['admin_name'] ?? '管理員';

// 處理刪除請求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $message = '';
        
        // 處理遊戲記錄刪除
        if (isset($_POST['delete_game_records'])) {
            $delete_conditions = [];
            $params = [];
            
            // 選項1：刪除今天的記錄
            if (isset($_POST['delete_today'])) {
                $delete_conditions[] = "DATE(play_date) = CURDATE()";
            }
            
            // 選項2：刪除特定用戶的記錄
            if (isset($_POST['delete_user']) && !empty($_POST['user_name'])) {
                $delete_conditions[] = "member_id IN (SELECT member_id FROM member WHERE member_name = ?)";
                $params[] = $_POST['user_name'];
            }
            
            // 選項3：刪除特定時間範圍的記錄
            if (isset($_POST['delete_range']) && !empty($_POST['start_date']) && !empty($_POST['end_date'])) {
                $delete_conditions[] = "DATE(play_date) BETWEEN ? AND ?";
                $params[] = $_POST['start_date'];
                $params[] = $_POST['end_date'];
            }
            
            // 選項4：刪除分數為0的測試記錄
            if (isset($_POST['delete_zero_score'])) {
                $delete_conditions[] = "score = 0";
            }
            
            if (!empty($delete_conditions)) {
                $where_clause = "WHERE " . implode(' AND ', $delete_conditions);
                
                // 先查詢要刪除的記錄數量
                $count_sql = "SELECT COUNT(*) FROM game_records $where_clause";
                $count_stmt = $pdo->prepare($count_sql);
                $count_stmt->execute($params);
                $delete_count = $count_stmt->fetchColumn();
                
                if ($delete_count > 0) {
                    // 執行刪除
                    $delete_sql = "DELETE FROM game_records $where_clause";
                    $delete_stmt = $pdo->prepare($delete_sql);
                    $delete_stmt->execute($params);
                    
                    $success_message = "成功刪除 {$delete_count} 筆遊戲記錄";
                } else {
                    $error_message = "沒有找到符合條件的遊戲記錄";
                }
            } else {
                $error_message = "請選擇至少一個刪除條件";
            }
        }
        
        // 處理用戶管理操作
        if (isset($_POST['user_management'])) {
            // 刪除用戶及其所有相關記錄
            if (isset($_POST['delete_user_completely']) && !empty($_POST['delete_user_name'])) {
                $user_name = $_POST['delete_user_name'];
                
                // 獲取用戶ID
                $user_stmt = $pdo->prepare("SELECT member_id FROM member WHERE member_name = ?");
                $user_stmt->execute([$user_name]);
                $user = $user_stmt->fetch();
                
                if ($user) {
                    $member_id = $user['member_id'];
                    
                    // 開始事務
                    $pdo->beginTransaction();
                    
                    try {
                        // 刪除遊戲記錄
                        $delete_games = $pdo->prepare("DELETE FROM game_records WHERE member_id = ?");
                        $delete_games->execute([$member_id]);
                        $deleted_games = $delete_games->rowCount();
                        
                        // 刪除行為軌跡記錄
                        $delete_behaviors = $pdo->prepare("DELETE FROM user_behavior_log WHERE member_id = ?");
                        $delete_behaviors->execute([$member_id]);
                        $deleted_behaviors = $delete_behaviors->rowCount();
                        
                        // 刪除用戶
                        $delete_user = $pdo->prepare("DELETE FROM member WHERE member_id = ?");
                        $delete_user->execute([$member_id]);
                        
                        $pdo->commit();
                        $success_message = "成功刪除用戶 {$user_name} 及其所有相關記錄<br>- 遊戲記錄：{$deleted_games} 筆<br>- 行為軌跡：{$deleted_behaviors} 筆";
                        
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        $error_message = "刪除用戶失敗：" . $e->getMessage();
                    }
                } else {
                    $error_message = "找不到用戶：{$user_name}";
                }
            }
            
            // 清空用戶總分數
            if (isset($_POST['reset_user_scores']) && !empty($_POST['reset_scores_user_name'])) {
                $user_name = $_POST['reset_scores_user_name'];
                
                $reset_stmt = $pdo->prepare("
                    UPDATE member 
                    SET total_score = 0, reaction_score = 0, memory_score = 0, logic_score = 0 
                    WHERE member_name = ?
                ");
                $result = $reset_stmt->execute([$user_name]);
                $affected_rows = $reset_stmt->rowCount();
                
                if ($affected_rows > 0) {
                    $success_message = (isset($success_message) ? $success_message . "<br>" : "") . "成功清空用戶 {$user_name} 的所有分數";
                } else {
                    $error_message = (isset($error_message) ? $error_message . "<br>" : "") . "找不到用戶或分數已為零：{$user_name}";
                }
            }
            
            // 清空所有用戶分數
            if (isset($_POST['reset_all_scores'])) {
                $reset_all_stmt = $pdo->prepare("
                    UPDATE member 
                    SET total_score = 0, reaction_score = 0, memory_score = 0, logic_score = 0
                ");
                $result = $reset_all_stmt->execute();
                $affected_rows = $reset_all_stmt->rowCount();
                
                $success_message = (isset($success_message) ? $success_message . "<br>" : "") . "成功清空所有用戶分數 ({$affected_rows} 位用戶)";
            }
        }
        
        // 處理行為軌跡記錄刪除
        if (isset($_POST['delete_behavior_logs'])) {
            $behavior_conditions = [];
            $behavior_params = [];
            
            // 選項1：刪除今天的行為記錄
            if (isset($_POST['delete_behavior_today'])) {
                $behavior_conditions[] = "DATE(created_at) = CURDATE()";
            }
            
            // 選項2：刪除特定用戶的行為記錄
            if (isset($_POST['delete_behavior_user']) && !empty($_POST['behavior_user_name'])) {
                $behavior_conditions[] = "member_id IN (SELECT member_id FROM member WHERE member_name = ?)";
                $behavior_params[] = $_POST['behavior_user_name'];
            }
            
            // 選項3：刪除特定時間範圍的行為記錄
            if (isset($_POST['delete_behavior_range']) && !empty($_POST['behavior_start_date']) && !empty($_POST['behavior_end_date'])) {
                $behavior_conditions[] = "DATE(created_at) BETWEEN ? AND ?";
                $behavior_params[] = $_POST['behavior_start_date'];
                $behavior_params[] = $_POST['behavior_end_date'];
            }
            
            // 選項4：刪除遊戲退出記錄
            if (isset($_POST['delete_game_exits'])) {
                $behavior_conditions[] = "action_type = 'game_exit'";
            }
            
            // 選項5：刪除所有遊戲相關行為記錄
            if (isset($_POST['delete_all_game_behaviors'])) {
                $behavior_conditions[] = "action_type IN ('game_exit', 'game_complete')";
            }
            
            if (!empty($behavior_conditions)) {
                $behavior_where_clause = "WHERE " . implode(' AND ', $behavior_conditions);
                
                // 先查詢要刪除的行為記錄數量
                $behavior_count_sql = "SELECT COUNT(*) FROM user_behavior_log $behavior_where_clause";
                $behavior_count_stmt = $pdo->prepare($behavior_count_sql);
                $behavior_count_stmt->execute($behavior_params);
                $behavior_delete_count = $behavior_count_stmt->fetchColumn();
                
                if ($behavior_delete_count > 0) {
                    // 執行刪除
                    $behavior_delete_sql = "DELETE FROM user_behavior_log $behavior_where_clause";
                    $behavior_delete_stmt = $pdo->prepare($behavior_delete_sql);
                    $behavior_delete_stmt->execute($behavior_params);
                    
                    $success_message = (isset($success_message) ? $success_message . "<br>" : "") . "成功刪除 {$behavior_delete_count} 筆行為軌跡記錄";
                } else {
                    $error_message = (isset($error_message) ? $error_message . "<br>" : "") . "沒有找到符合條件的行為軌跡記錄";
                }
            } else {
                $error_message = (isset($error_message) ? $error_message . "<br>" : "") . "請選擇至少一個行為軌跡刪除條件";
            }
        }
        
    } catch (Exception $e) {
        $error_message = "刪除失敗：" . $e->getMessage();
    }
}

// 獲取統計數據
try {
    $stats_sql = "
        SELECT 
            COUNT(*) as total_records,
            COUNT(CASE WHEN DATE(play_date) = CURDATE() THEN 1 END) as today_records,
            COUNT(CASE WHEN score = 0 THEN 1 END) as zero_score_records
        FROM game_records
    ";
    $stats_stmt = $pdo->prepare($stats_sql);
    $stats_stmt->execute();
    $stats = $stats_stmt->fetch();
    
    // 獲取行為軌跡統計數據
    $behavior_stats_sql = "
        SELECT 
            COUNT(*) as total_behavior_records,
            COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as today_behavior_records,
            COUNT(CASE WHEN action_type = 'game_exit' THEN 1 END) as exit_records,
            COUNT(CASE WHEN action_type = 'game_complete' THEN 1 END) as complete_records
        FROM user_behavior_log
        WHERE action_type IN ('game_exit', 'game_complete')
    ";
    $behavior_stats_stmt = $pdo->prepare($behavior_stats_sql);
    $behavior_stats_stmt->execute();
    $behavior_stats = $behavior_stats_stmt->fetch();
    
    // 獲取用戶統計數據
    $user_stats_sql = "
        SELECT 
            COUNT(*) as total_users,
            COUNT(CASE WHEN total_score > 0 THEN 1 END) as users_with_scores,
            SUM(total_score) as total_all_scores
        FROM member
    ";
    $user_stats_stmt = $pdo->prepare($user_stats_sql);
    $user_stats_stmt->execute();
    $user_stats = $user_stats_stmt->fetch();
} catch (Exception $e) {
    $stats = ['total_records' => 0, 'today_records' => 0, 'zero_score_records' => 0];
    $behavior_stats = ['total_behavior_records' => 0, 'today_behavior_records' => 0, 'exit_records' => 0, 'complete_records' => 0];
    $user_stats = ['total_users' => 0, 'users_with_scores' => 0, 'total_all_scores' => 0];
}

// 獲取用戶列表
try {
    $users_sql = "
        SELECT DISTINCT m.member_name, COUNT(gr.record_id) as record_count
        FROM member m
        LEFT JOIN game_records gr ON m.member_id = gr.member_id
        GROUP BY m.member_id, m.member_name
        HAVING record_count > 0
        ORDER BY record_count DESC
    ";
    $users_stmt = $pdo->prepare($users_sql);
    $users_stmt->execute();
    $users = $users_stmt->fetchAll();
} catch (Exception $e) {
    $users = [];
}
?>

<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>刪除測試記錄 - 管理後台</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Microsoft JhengHei', Arial, sans-serif; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .header { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header h1 { color: #333; }
        .nav { background: white; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .nav a { text-decoration: none; color: #007bff; margin-right: 20px; padding: 8px 16px; border-radius: 4px; }
        .nav a:hover { background: #007bff; color: white; }
        .card { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 20px; }
        .stat-item { background: #f8f9fa; padding: 15px; border-radius: 6px; text-align: center; }
        .stat-number { font-size: 24px; font-weight: bold; color: #007bff; }
        .stat-label { color: #666; margin-top: 5px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input, .form-group select { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        .checkbox-group { display: flex; flex-wrap: wrap; gap: 15px; }
        .checkbox-item { display: flex; align-items: center; }
        .checkbox-item input { width: auto; margin-right: 8px; }
        .btn { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-primary { background: #007bff; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        .btn:hover { opacity: 0.9; }
        .alert { padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .warning { background: #fff3cd; color: #856404; padding: 15px; border-radius: 4px; margin: 15px 0; border: 1px solid #ffeaa7; }
        .logout { float: right; background: #dc3545; color: white; padding: 8px 16px; text-decoration: none; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>刪除測試記錄</h1>
            <p>歡迎，<?php echo htmlspecialchars($admin_name); ?></p>
            <a href="logout.php" class="logout">登出</a>
        </div>
        
        <div class="nav">
            <a href="game_records.php">遊戲紀錄</a>
            <a href="user_behavior.php">行為軌跡</a>
            <a href="question_management.php">遊戲管理</a>
            <a href="user_management.php">用戶管理</a>
            <a href="ability_analysis.php">能力分析</a>
            <a href="delete_test_records.php" style="background: #007bff; color: white;">刪除測試記錄</a>
        </div>

        <?php if (isset($success_message)): ?>
        <div class="alert alert-success">
            <?php echo htmlspecialchars($success_message); ?>
        </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
        <div class="alert alert-error">
            <?php echo htmlspecialchars($error_message); ?>
        </div>
        <?php endif; ?>

        <div class="card">
            <h2>📊 遊戲記錄統計</h2>
            <div class="stats">
                <div class="stat-item">
                    <div class="stat-number"><?php echo number_format($stats['total_records']); ?></div>
                    <div class="stat-label">總記錄數</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo number_format($stats['today_records']); ?></div>
                    <div class="stat-label">今日記錄</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo number_format($stats['zero_score_records']); ?></div>
                    <div class="stat-label">零分記錄</div>
                </div>
            </div>
        </div>

        <div class="card">
            <h2>🔄 行為軌跡統計</h2>
            <div class="stats">
                <div class="stat-item">
                    <div class="stat-number"><?php echo number_format($behavior_stats['total_behavior_records']); ?></div>
                    <div class="stat-label">總行為記錄</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo number_format($behavior_stats['today_behavior_records']); ?></div>
                    <div class="stat-label">今日行為記錄</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo number_format($behavior_stats['exit_records']); ?></div>
                    <div class="stat-label">遊戲退出記錄</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo number_format($behavior_stats['complete_records']); ?></div>
                    <div class="stat-label">遊戲完成記錄</div>
                </div>
            </div>
        </div>

        <div class="card">
            <h2>👥 用戶統計</h2>
            <div class="stats">
                <div class="stat-item">
                    <div class="stat-number"><?php echo number_format($user_stats['total_users']); ?></div>
                    <div class="stat-label">總用戶數</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo number_format($user_stats['users_with_scores']); ?></div>
                    <div class="stat-label">有分數用戶</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo number_format($user_stats['total_all_scores']); ?></div>
                    <div class="stat-label">總分數</div>
                </div>
            </div>
        </div>

        <div class="card">
            <h2>🗑️ 刪除遊戲記錄</h2>
            <div class="warning">
                ⚠️ 警告：此操作無法復原，請謹慎操作！建議先備份資料庫。
            </div>
            
            <form method="POST" onsubmit="return confirm('確定要刪除選定的遊戲記錄嗎？此操作無法復原！');">
                <div class="form-group">
                    <label>選擇刪除條件：</label>
                    <div class="checkbox-group">
                        <div class="checkbox-item">
                            <input type="checkbox" id="delete_today" name="delete_today">
                            <label for="delete_today">刪除今天的記錄 (<?php echo $stats['today_records']; ?> 筆)</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" id="delete_zero_score" name="delete_zero_score">
                            <label for="delete_zero_score">刪除零分記錄 (<?php echo $stats['zero_score_records']; ?> 筆)</label>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <div class="checkbox-item">
                        <input type="checkbox" id="delete_user" name="delete_user">
                        <label for="delete_user">刪除特定用戶的記錄：</label>
                    </div>
                    <select name="user_name" style="margin-top: 10px;">
                        <option value="">選擇用戶</option>
                        <?php foreach ($users as $user): ?>
                        <option value="<?php echo htmlspecialchars($user['member_name']); ?>">
                            <?php echo htmlspecialchars($user['member_name']); ?> (<?php echo $user['record_count']; ?> 筆記錄)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <div class="checkbox-item">
                        <input type="checkbox" id="delete_range" name="delete_range">
                        <label for="delete_range">刪除日期範圍內的記錄：</label>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 10px;">
                        <input type="date" name="start_date" placeholder="開始日期">
                        <input type="date" name="end_date" placeholder="結束日期">
                    </div>
                </div>

                <button type="submit" name="delete_game_records" class="btn btn-danger">
                    🗑️ 刪除選定的遊戲記錄
                </button>
                <a href="game_records.php" class="btn btn-primary" style="margin-left: 10px;">
                    📊 查看遊戲記錄
                </a>
            </form>
        </div>

        <div class="card">
            <h2>🔄 刪除行為軌跡記錄</h2>
            <div class="warning">
                ⚠️ 警告：此操作無法復原，請謹慎操作！建議先備份資料庫。
            </div>
            
            <form method="POST" onsubmit="return confirm('確定要刪除選定的行為軌跡記錄嗎？此操作無法復原！');">
                <div class="form-group">
                    <label>選擇刪除條件：</label>
                    <div class="checkbox-group">
                        <div class="checkbox-item">
                            <input type="checkbox" id="delete_behavior_today" name="delete_behavior_today">
                            <label for="delete_behavior_today">刪除今天的行為記錄 (<?php echo $behavior_stats['today_behavior_records']; ?> 筆)</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" id="delete_game_exits" name="delete_game_exits">
                            <label for="delete_game_exits">刪除遊戲退出記錄 (<?php echo $behavior_stats['exit_records']; ?> 筆)</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" id="delete_all_game_behaviors" name="delete_all_game_behaviors">
                            <label for="delete_all_game_behaviors">刪除所有遊戲行為記錄 (<?php echo $behavior_stats['total_behavior_records']; ?> 筆)</label>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <div class="checkbox-item">
                        <input type="checkbox" id="delete_behavior_user" name="delete_behavior_user">
                        <label for="delete_behavior_user">刪除特定用戶的行為記錄：</label>
                    </div>
                    <select name="behavior_user_name" style="margin-top: 10px;">
                        <option value="">選擇用戶</option>
                        <?php foreach ($users as $user): ?>
                        <option value="<?php echo htmlspecialchars($user['member_name']); ?>">
                            <?php echo htmlspecialchars($user['member_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <div class="checkbox-item">
                        <input type="checkbox" id="delete_behavior_range" name="delete_behavior_range">
                        <label for="delete_behavior_range">刪除日期範圍內的行為記錄：</label>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 10px;">
                        <input type="date" name="behavior_start_date" placeholder="開始日期">
                        <input type="date" name="behavior_end_date" placeholder="結束日期">
                    </div>
                </div>

                <button type="submit" name="delete_behavior_logs" class="btn btn-danger">
                    🔄 刪除選定的行為軌跡記錄
                </button>
                <a href="user_behavior.php" class="btn btn-primary" style="margin-left: 10px;">
                    📊 查看行為軌跡
                </a>
            </form>
        </div>

        <div class="card">
            <h2>👥 用戶管理</h2>
            <div class="warning">
                ⚠️ 危險操作：刪除用戶會永久移除該用戶的所有數據！請謹慎操作！
            </div>
            
            <form method="POST" onsubmit="return confirm('確定要執行選定的用戶管理操作嗎？此操作無法復原！');">
                <div class="form-group">
                    <label>選擇操作：</label>
                    <div class="checkbox-group">
                        <div class="checkbox-item">
                            <input type="checkbox" id="delete_user_completely" name="delete_user_completely">
                            <label for="delete_user_completely">完全刪除用戶（包含所有記錄）</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" id="reset_user_scores" name="reset_user_scores">
                            <label for="reset_user_scores">清空特定用戶分數</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" id="reset_all_scores" name="reset_all_scores">
                            <label for="reset_all_scores">清空所有用戶分數 (<?php echo $user_stats['users_with_scores']; ?> 位用戶)</label>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="delete_user_name">要刪除的用戶：</label>
                    <select name="delete_user_name" id="delete_user_name">
                        <option value="">選擇要刪除的用戶</option>
                        <?php foreach ($users as $user): ?>
                        <option value="<?php echo htmlspecialchars($user['member_name']); ?>">
                            <?php echo htmlspecialchars($user['member_name']); ?> (<?php echo $user['record_count']; ?> 筆記錄)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="reset_scores_user_name">要清空分數的用戶：</label>
                    <select name="reset_scores_user_name" id="reset_scores_user_name">
                        <option value="">選擇要清空分數的用戶</option>
                        <?php foreach ($users as $user): ?>
                        <option value="<?php echo htmlspecialchars($user['member_name']); ?>">
                            <?php echo htmlspecialchars($user['member_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" name="user_management" class="btn btn-danger">
                    👥 執行用戶管理操作
                </button>
                <a href="user_management.php" class="btn btn-primary" style="margin-left: 10px;">
                    📊 查看用戶管理
                </a>
            </form>
        </div>
    </div>

    <script>
        // 遊戲記錄表單交互
        document.querySelector('select[name="user_name"]').addEventListener('change', function() {
            if (this.value) {
                document.getElementById('delete_user').checked = true;
            }
        });

        document.querySelector('input[name="start_date"]').addEventListener('change', function() {
            if (this.value) {
                document.getElementById('delete_range').checked = true;
            }
        });
        
        document.querySelector('input[name="end_date"]').addEventListener('change', function() {
            if (this.value) {
                document.getElementById('delete_range').checked = true;
            }
        });

        // 行為軌跡記錄表單交互
        document.querySelector('select[name="behavior_user_name"]').addEventListener('change', function() {
            if (this.value) {
                document.getElementById('delete_behavior_user').checked = true;
            }
        });

        document.querySelector('input[name="behavior_start_date"]').addEventListener('change', function() {
            if (this.value) {
                document.getElementById('delete_behavior_range').checked = true;
            }
        });
        
        document.querySelector('input[name="behavior_end_date"]').addEventListener('change', function() {
            if (this.value) {
                document.getElementById('delete_behavior_range').checked = true;
            }
        });

        // 用戶管理表單交互
        document.querySelector('select[name="delete_user_name"]').addEventListener('change', function() {
            if (this.value) {
                document.getElementById('delete_user_completely').checked = true;
            }
        });

        document.querySelector('select[name="reset_scores_user_name"]').addEventListener('change', function() {
            if (this.value) {
                document.getElementById('reset_user_scores').checked = true;
            }
        });

        // 當選擇清空所有用戶分數時，顯示額外確認
        document.getElementById('reset_all_scores').addEventListener('change', function() {
            if (this.checked) {
                if (!confirm('確定要清空所有用戶的分數嗎？這將影響 <?php echo $user_stats['users_with_scores']; ?> 位用戶！')) {
                    this.checked = false;
                }
            }
        });

        // 當選擇刪除用戶時，顯示額外確認
        document.getElementById('delete_user_completely').addEventListener('change', function() {
            if (this.checked) {
                const selectedUser = document.querySelector('select[name="delete_user_name"]').value;
                if (selectedUser && !confirm(`確定要完全刪除用戶 "${selectedUser}" 嗎？這將刪除該用戶的所有數據！`)) {
                    this.checked = false;
                    document.querySelector('select[name="delete_user_name"]').value = '';
                }
            }
        });
    </script>
</body>
</html>
