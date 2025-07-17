<?php
require_once 'db.php';

echo "檢查任務資料：\n";

$sql = "SELECT * FROM daily_tasks WHERE is_active = 1";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$tasks = $stmt->fetchAll();

if (empty($tasks)) {
    echo "沒有找到任務資料，需要插入測試任務\n";
    
    // 插入測試任務
    $insert_sql = "INSERT INTO daily_tasks (task_name, task_description, task_type, reward_points, is_active) VALUES 
                   ('遊玩任一普通關卡一次', '完成任意遊戲的普通難度關卡', 'achievement', 10, 1)";
    $pdo->exec($insert_sql);
    
    echo "已插入測試任務\n";
} else {
    echo "找到 " . count($tasks) . " 個任務：\n";
    foreach ($tasks as $task) {
        echo "- ID: {$task['task_id']}, 名稱: {$task['task_name']}, 描述: {$task['task_description']}, 類型: {$task['task_type']}, 獎勵: {$task['reward_points']}\n";
    }
}

echo "\n檢查member_tasks表結構：\n";
$sql = "DESCRIBE member_tasks";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$columns = $stmt->fetchAll();

foreach ($columns as $column) {
    echo "- {$column['Field']} ({$column['Type']})\n";
}

echo "\n檢查會員任務狀態：\n";
$member_id = 8; // 假設會員ID為8
$sql = "SELECT mt.*, d.task_name, d.task_type 
        FROM member_tasks mt 
        JOIN daily_tasks d ON mt.task_id = d.task_id 
        WHERE mt.member_id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$member_id]);
$member_tasks = $stmt->fetchAll();

if (empty($member_tasks)) {
    echo "會員 {$member_id} 沒有任務記錄\n";
} else {
    foreach ($member_tasks as $task) {
        echo "- 任務: {$task['task_name']}, 狀態: {$task['status']}, 完成時間: {$task['completed_date']}\n";
    }
}
?> 