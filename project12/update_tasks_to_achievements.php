<?php
require_once 'db.php';

echo "=== 更新任務為成就獎勵 ===\n\n";

// 新的任務配置，獎勵改為成就而不是分數
$updated_tasks = [
    [
        'task_id' => 1,
        'task_name' => '成就任務',
        'task_description' => '遊玩任一普通關卡一次',
        'task_type' => 'Achievement',
        'reward_achievement' => '遊戲新手',
        'is_active' => 1
    ],
    [
        'task_id' => 2,
        'task_name' => '成就任務',
        'task_description' => '完成任意一場遊戲對戰',
        'task_type' => 'Achievement',
        'reward_achievement' => '對戰達人',
        'is_active' => 1
    ],
    [
        'task_id' => 3,
        'task_name' => '好友任務',
        'task_description' => '好友組隊遊戲一次',
        'task_type' => 'friend',
        'reward_achievement' => '社交達人',
        'is_active' => 1
    ],
    [
        'task_id' => 47,
        'task_name' => '成就任務',
        'task_description' => '完成三種不同類型遊戲',
        'task_type' => 'Achievement',
        'reward_achievement' => '全能玩家',
        'is_active' => 1
    ],
    [
        'task_id' => 48,
        'task_name' => '成就任務',
        'task_description' => '打破自己遊戲最高分',
        'task_type' => 'Achievement',
        'reward_achievement' => '突破自我',
        'is_active' => 1
    ],
    [
        'task_id' => 49,
        'task_name' => '好友任務',
        'task_description' => '與好友對戰並取得勝利',
        'task_type' => 'friend',
        'reward_achievement' => '勝利王者',
        'is_active' => 1
    ],
    [
        'task_id' => 50,
        'task_name' => '好友任務',
        'task_description' => '新增1位新好友',
        'task_type' => 'friend',
        'reward_achievement' => '交友達人',
        'is_active' => 1
    ],
    [
        'task_id' => 51,
        'task_name' => '好友任務',
        'task_description' => '與好友同時在線超過30分鐘',
        'task_type' => 'friend',
        'reward_achievement' => '陪伴達人',
        'is_active' => 1
    ],
    [
        'task_id' => 52,
        'task_name' => '登入網站一次',
        'task_description' => '今天登入網站一次即可完成任務',
        'task_type' => 'login',
        'reward_achievement' => '忠實玩家',
        'is_active' => 1
    ]
];

echo "要更新的任務（改為成就獎勵）：\n";
foreach ($updated_tasks as $task) {
    echo "- ID: " . $task['task_id'] . 
         ", 名稱: " . $task['task_name'] . 
         ", 成就: " . $task['reward_achievement'] . "\n";
}

echo "\n確認要更新這些任務嗎？(y/n): ";
$handle = fopen("php://stdin", "r");
$line = fgets($handle);
fclose($handle);

if (trim($line) !== 'y') {
    echo "取消操作\n";
    exit;
}

echo "\n開始更新任務...\n";

try {
    // 先檢查是否需要添加reward_achievement欄位
    $check_column_sql = "SHOW COLUMNS FROM daily_tasks LIKE 'reward_achievement'";
    $check_column_stmt = $pdo->query($check_column_sql);
    $column_exists = $check_column_stmt->fetch();
    
    if (!$column_exists) {
        // 添加reward_achievement欄位
        $add_column_sql = "ALTER TABLE daily_tasks ADD COLUMN reward_achievement VARCHAR(100) AFTER reward_points";
        $pdo->exec($add_column_sql);
        echo "✓ 已添加 reward_achievement 欄位\n";
    }
    
    // 更新每個任務
    $update_sql = "UPDATE daily_tasks SET task_name = ?, task_description = ?, task_type = ?, reward_achievement = ?, is_active = ? WHERE task_id = ?";
    $update_stmt = $pdo->prepare($update_sql);
    
    foreach ($updated_tasks as $task) {
        $update_stmt->execute([
            $task['task_name'],
            $task['task_description'],
            $task['task_type'],
            $task['reward_achievement'],
            $task['is_active'],
            $task['task_id']
        ]);
        echo "✓ 已更新任務: " . $task['task_name'] . " -> 成就: " . $task['reward_achievement'] . "\n";
    }
    
    echo "\n=== 更新完成 ===\n";
    echo "檢查更新後的任務：\n";
    
    $sql = "SELECT task_id, task_name, task_description, task_type, reward_achievement, is_active FROM daily_tasks ORDER BY task_id";
    $stmt = $pdo->query($sql);
    $updated_tasks_result = $stmt->fetchAll();
    
    foreach ($updated_tasks_result as $task) {
        echo "ID: " . $task['task_id'] . 
             " | 名稱: " . $task['task_name'] . 
             " | 類型: " . $task['task_type'] . 
             " | 成就: " . $task['reward_achievement'] .
             " | 狀態: " . ($task['is_active'] ? '啟用' : '停用') . "\n";
    }
    
    echo "\n總共 " . count($updated_tasks_result) . " 個任務\n";
    
} catch (Exception $e) {
    echo "✗ 更新失敗: " . $e->getMessage() . "\n";
}
?> 