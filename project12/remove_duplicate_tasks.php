<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';

try {
    echo "正在檢查重複的追蹤犯人遊戲任務...\n";
    
    // 查詢所有與追蹤犯人相關的任務
    $sql = "SELECT * FROM daily_tasks WHERE task_description LIKE '%追蹤犯人%' OR task_name LIKE '%追蹤%' ORDER BY task_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($tasks)) {
        echo "找到追蹤相關的任務：\n";
        foreach ($tasks as $task) {
            echo "ID: {$task['task_id']}, 名稱: {$task['task_name']}, 描述: {$task['task_description']}\n";
        }
        
        // 找出重複的任務並刪除
        $tracking_tasks = [];
        foreach ($tasks as $task) {
            if (strpos($task['task_description'], '追蹤犯人') !== false) {
                $tracking_tasks[] = $task;
            }
        }
        
        if (count($tracking_tasks) > 1) {
            echo "\n發現重複的追蹤犯人任務，保留第一個，刪除其他：\n";
            
            // 保留第一個任務，刪除其他
            for ($i = 1; $i < count($tracking_tasks); $i++) {
                $task_to_delete = $tracking_tasks[$i];
                echo "刪除任務 ID {$task_to_delete['task_id']}: {$task_to_delete['task_name']} - {$task_to_delete['task_description']}\n";
                
                // 先刪除相關的用戶任務記錄
                $delete_member_tasks = "DELETE FROM member_tasks WHERE task_id = ?";
                $delete_stmt1 = $pdo->prepare($delete_member_tasks);
                $delete_stmt1->execute([$task_to_delete['task_id']]);
                
                // 再刪除任務本身
                $delete_task = "DELETE FROM daily_tasks WHERE task_id = ?";
                $delete_stmt2 = $pdo->prepare($delete_task);
                $delete_stmt2->execute([$task_to_delete['task_id']]);
            }
        }
    }
    
    // 同樣檢查其他可能重複的任務
    echo "\n檢查其他可能重複的任務...\n";
    
    // 檢查記憶力遊戲相關任務
    $memory_sql = "SELECT * FROM daily_tasks WHERE task_description LIKE '%翻牌對對樂%' OR task_description LIKE '%記憶%' ORDER BY task_id";
    $memory_stmt = $pdo->prepare($memory_sql);
    $memory_stmt->execute();
    $memory_tasks = $memory_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($memory_tasks)) {
        echo "記憶力相關任務：\n";
        foreach ($memory_tasks as $task) {
            echo "ID: {$task['task_id']}, 名稱: {$task['task_name']}, 描述: {$task['task_description']}\n";
        }
    }
    
    // 檢查算菜錢遊戲相關任務
    $math_sql = "SELECT * FROM daily_tasks WHERE task_description LIKE '%算菜錢%' OR task_description LIKE '%蔬菜%' ORDER BY task_id";
    $math_stmt = $pdo->prepare($math_sql);
    $math_stmt->execute();
    $math_tasks = $math_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($math_tasks)) {
        echo "\n算菜錢相關任務：\n";
        foreach ($math_tasks as $task) {
            echo "ID: {$task['task_id']}, 名稱: {$task['task_name']}, 描述: {$task['task_description']}\n";
        }
    }
    
    echo "\n檢查完成！\n";
    
} catch (Exception $e) {
    echo "錯誤: " . $e->getMessage() . "\n";
}
?>



