<?php
// 獨立的每日重置腳本 - 可以單獨調用並返回 JSON
require_once 'db.php';

// 設定台灣時區
date_default_timezone_set('Asia/Taipei');

// 設置 JSON 響應頭
header('Content-Type: application/json; charset=utf-8');

// 記錄重置開始
error_log("開始執行每日重置 - " . date('Y-m-d H:i:s'));

try {
    // 開始交易
    $pdo->beginTransaction();
    
    // 1. 重置每日任務狀態
    $reset_tasks_sql = "
        UPDATE member_tasks 
        SET completed_date = NULL, claimed_date = NULL, status = 'pending'
        WHERE completed_date IS NOT NULL OR claimed_date IS NOT NULL
    ";
    $task_stmt = $pdo->prepare($reset_tasks_sql);
    $task_result = $task_stmt->execute();
    
    if ($task_result) {
        error_log("✓ 每日任務重置成功");
    } else {
        error_log("✗ 每日任務重置失敗");
    }
    
    // 2. 清空今日成就計數（但保留歷史成就記錄）
    $reset_achievement_count_sql = "
        DELETE FROM member_achievements 
        WHERE DATE(earned_date) = CURDATE()
    ";
    $achievement_stmt = $pdo->prepare($reset_achievement_count_sql);
    $achievement_result = $achievement_stmt->execute();
    
    if ($achievement_result) {
        error_log("✓ 成就計數重置成功");
    } else {
        error_log("✗ 成就計數重置失敗");
    }
    
    // 3. 為所有活躍用戶重新分配每日任務
    $active_users_sql = "SELECT DISTINCT member_id FROM member WHERE member_id > 0";
    $users_stmt = $pdo->query($active_users_sql);
    $users = $users_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $task_count = 0;
    foreach ($users as $user_id) {
        // 先刪除用戶現有的任務
        $delete_tasks_sql = "DELETE FROM member_tasks WHERE member_id = ?";
        $delete_stmt = $pdo->prepare($delete_tasks_sql);
        $delete_stmt->execute([$user_id]);
        
        // 為用戶分配新的每日任務（隨機選擇3個）
        $available_tasks_sql = "SELECT task_id FROM daily_tasks WHERE is_active = 1 ORDER BY RAND() LIMIT 3";
        $available_stmt = $pdo->query($available_tasks_sql);
        $available_tasks = $available_stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($available_tasks as $task_id) {
            $assign_tasks_sql = "
                INSERT INTO member_tasks (member_id, task_id, status, completed_date, claimed_date)
                VALUES (?, ?, 'pending', NULL, NULL)
            ";
            $assign_stmt = $pdo->prepare($assign_tasks_sql);
            $assign_result = $assign_stmt->execute([$user_id, $task_id]);
            
            if ($assign_result) {
                $task_count++;
            }
        }
    }
    
    error_log("✓ 為 $task_count 個用戶分配了新任務");
    
    // 4. 記錄重置時間
    $log_sql = "INSERT INTO daily_reset_log (created_at) VALUES (NOW())";
    $log_stmt = $pdo->prepare($log_sql);
    $log_stmt->execute();
    
    // 提交交易
    $pdo->commit();
    error_log("✓ 每日重置完成 - " . date('Y-m-d H:i:s'));
    
    // 返回成功訊息
    echo json_encode([
        'success' => true,
        'message' => '每日重置完成',
        'timestamp' => date('Y-m-d H:i:s'),
        'tasks_reset' => $task_result,
        'achievements_reset' => $achievement_result,
        'users_updated' => $task_count
    ]);
    
} catch (Exception $e) {
    // 回滾交易
    $pdo->rollBack();
    error_log("✗ 每日重置失敗: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => '重置失敗：' . $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>
