<?php
require_once 'db.php';

echo "=== 移除分享遊戲成績任務 ===\n\n";

try {
    // 找到要移除的任務
    $task_id = 69;
    
    echo "要移除的任務：\n";
    $sql = "SELECT task_id, task_name, task_description, task_type, reward_achievement FROM daily_tasks WHERE task_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$task_id]);
    $task = $stmt->fetch();
    
    if ($task) {
        echo "ID: " . $task['task_id'] . 
             " | 名稱: " . $task['task_name'] . 
             " | 描述: " . $task['task_description'] . 
             " | 成就: " . $task['reward_achievement'] . "\n";
        
        echo "\n確認要移除這個任務嗎？(y/n): ";
        $handle = fopen("php://stdin", "r");
        $line = fgets($handle);
        fclose($handle);
        
        if (trim($line) !== 'y') {
            echo "取消操作\n";
            exit;
        }
        
        // 移除任務
        $delete_sql = "DELETE FROM daily_tasks WHERE task_id = ?";
        $delete_stmt = $pdo->prepare($delete_sql);
        $delete_stmt->execute([$task_id]);
        
        echo "✓ 已移除任務 ID: " . $task_id . "\n";
        
        // 顯示剩餘的任務
        echo "\n剩餘的任務：\n";
        $sql = "SELECT task_id, task_name, task_description, task_type, reward_achievement, is_active FROM daily_tasks ORDER BY task_id";
        $stmt = $pdo->query($sql);
        $remaining_tasks = $stmt->fetchAll();
        
        foreach ($remaining_tasks as $task) {
            echo "ID: " . $task['task_id'] . 
                 " | 名稱: " . $task['task_name'] . 
                 " | 類型: " . $task['task_type'] . 
                 " | 成就: " . $task['reward_achievement'] .
                 " | 狀態: " . ($task['is_active'] ? '啟用' : '停用') . "\n";
        }
        
        echo "\n總共 " . count($remaining_tasks) . " 個任務\n";
        
    } else {
        echo "找不到任務 ID: " . $task_id . "\n";
    }
    
} catch (Exception $e) {
    echo "✗ 操作失敗: " . $e->getMessage() . "\n";
}
?> 