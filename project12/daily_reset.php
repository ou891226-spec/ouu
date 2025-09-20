<?php
// 每日重置腳本 - 在台灣凌晨12點重置任務和成就
require_once 'db.php';

// 設定台灣時區
date_default_timezone_set('Asia/Taipei');

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
    // 注意：這裡不清除 member_achievements 表，只重置計數器
    // 由於 member 表沒有 daily_achievement_count 欄位，我們通過刪除今日成就記錄來重置
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
        
        // 檢查用戶是否為新手（沒有遊戲記錄）
        $game_count_sql = "SELECT COUNT(*) as game_count FROM game_records WHERE member_id = ?";
        $game_count_stmt = $pdo->prepare($game_count_sql);
        $game_count_stmt->execute([$user_id]);
        $game_count = $game_count_stmt->fetch()['game_count'];
        
        // 為用戶分配新的每日任務
        if ($game_count == 0) {
            // 新手用戶：包含新手任務
            $available_tasks_sql = "SELECT task_id FROM daily_tasks WHERE is_active = 1 ORDER BY RAND() LIMIT 3";
        } else {
            // 非新手用戶：排除新手任務
            $available_tasks_sql = "SELECT task_id FROM daily_tasks WHERE is_active = 1 AND task_name != '遊戲新手' ORDER BY RAND() LIMIT 3";
        }
        
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
    
    // 4. 重置每日遊戲統計（可選）
    // 如果需要重置每日遊戲統計，可以取消註釋下面的代碼
    /*
    $reset_stats_sql = "
        UPDATE member 
        SET daily_games_played = 0, daily_score_earned = 0 
        WHERE daily_games_played > 0 OR daily_score_earned > 0
    ";
    $stats_stmt = $pdo->prepare($reset_stats_sql);
    $stats_result = $stats_stmt->execute();
    
    if ($stats_result) {
        error_log("✓ 每日遊戲統計重置成功");
    } else {
        error_log("✗ 每日遊戲統計重置失敗");
    }
    */
    
    // 提交交易
    $pdo->commit();
    error_log("✓ 每日重置完成 - " . date('Y-m-d H:i:s'));
    
    // 移除 JSON 輸出，因為這個文件現在被包含在其他文件中
    // 如果需要單獨調用，可以通過參數控制是否輸出 JSON
    
} catch (Exception $e) {
    // 回滾交易
    $pdo->rollBack();
    error_log("✗ 每日重置失敗: " . $e->getMessage());
    
    // 移除 JSON 輸出，因為這個文件現在被包含在其他文件中
}
?> 