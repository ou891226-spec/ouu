<?php
require_once 'db.php';

echo "<h1>修復新手任務分配</h1>";
echo "<p>移除非新手用戶的「遊戲新手」任務...</p>";

try {
    $pdo->beginTransaction();
    
    // 找到所有擁有「遊戲新手」任務的用戶
    $find_beginner_tasks_sql = "
        SELECT DISTINCT mt.member_id 
        FROM member_tasks mt 
        JOIN daily_tasks dt ON mt.task_id = dt.task_id 
        WHERE dt.task_name = '遊戲新手'
    ";
    $stmt = $pdo->query($find_beginner_tasks_sql);
    $users_with_beginner_task = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<p>找到 " . count($users_with_beginner_task) . " 個用戶擁有「遊戲新手」任務</p>";
    
    $removed_count = 0;
    $kept_count = 0;
    
    foreach ($users_with_beginner_task as $user_id) {
        // 檢查用戶是否有遊戲記錄
        $game_count_sql = "SELECT COUNT(*) as game_count FROM game_records WHERE member_id = ?";
        $game_stmt = $pdo->prepare($game_count_sql);
        $game_stmt->execute([$user_id]);
        $game_count = $game_stmt->fetch()['game_count'];
        
        if ($game_count > 0) {
            // 非新手用戶，移除新手任務
            $remove_task_sql = "
                DELETE mt FROM member_tasks mt 
                JOIN daily_tasks dt ON mt.task_id = dt.task_id 
                WHERE mt.member_id = ? AND dt.task_name = '遊戲新手'
            ";
            $remove_stmt = $pdo->prepare($remove_task_sql);
            $remove_stmt->execute([$user_id]);
            
            // 為該用戶隨機分配一個其他任務來替代
            $replacement_task_sql = "
                SELECT task_id FROM daily_tasks 
                WHERE is_active = 1 AND task_name != '遊戲新手' 
                AND task_id NOT IN (
                    SELECT task_id FROM member_tasks WHERE member_id = ?
                )
                ORDER BY RAND() LIMIT 1
            ";
            $replacement_stmt = $pdo->prepare($replacement_task_sql);
            $replacement_stmt->execute([$user_id]);
            $replacement_task = $replacement_stmt->fetch();
            
            if ($replacement_task) {
                $add_task_sql = "
                    INSERT INTO member_tasks (member_id, task_id, status, completed_date, claimed_date) 
                    VALUES (?, ?, 'pending', NULL, NULL)
                ";
                $add_stmt = $pdo->prepare($add_task_sql);
                $add_stmt->execute([$user_id, $replacement_task['task_id']]);
                
                echo "<p>用戶 $user_id：移除新手任務，替換為任務 ID " . $replacement_task['task_id'] . "</p>";
            } else {
                echo "<p>用戶 $user_id：移除新手任務，但沒有找到替代任務</p>";
            }
            
            $removed_count++;
        } else {
            // 新手用戶，保留新手任務
            echo "<p>用戶 $user_id：保留新手任務（遊戲次數：$game_count）</p>";
            $kept_count++;
        }
    }
    
    $pdo->commit();
    
    echo "<h2>修復完成！</h2>";
    echo "<p>移除了 $removed_count 個非新手用戶的新手任務</p>";
    echo "<p>保留了 $kept_count 個新手用戶的新手任務</p>";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "<p style='color: red;'>修復失敗：" . $e->getMessage() . "</p>";
    error_log("修復新手任務失敗：" . $e->getMessage());
}
?>

