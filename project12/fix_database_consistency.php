<?php
require_once 'db.php';

echo "=== 檢查資料庫一致性 ===\n\n";

try {
    // 檢查資料庫結構
    echo "檢查資料庫欄位結構...\n";
    $sql = "DESCRIBE daily_tasks";
    $stmt = $pdo->query($sql);
    $columns = $stmt->fetchAll();
    
    echo "目前資料表欄位：\n";
    foreach ($columns as $column) {
        echo "- " . $column['Field'] . " (" . $column['Type'] . ")\n";
    }
    
    // 檢查是否有reward_achievement欄位
    $has_achievement = false;
    foreach ($columns as $column) {
        if ($column['Field'] === 'reward_achievement') {
            $has_achievement = true;
            break;
        }
    }
    
    if (!$has_achievement) {
        echo "\n⚠️ 缺少 reward_achievement 欄位，正在添加...\n";
        $add_sql = "ALTER TABLE daily_tasks ADD COLUMN reward_achievement VARCHAR(100) DEFAULT NULL AFTER reward_points";
        $pdo->exec($add_sql);
        echo "✓ 已添加 reward_achievement 欄位\n";
    } else {
        echo "\n✓ reward_achievement 欄位已存在\n";
    }
    
    // 檢查目前任務資料
    echo "\n檢查目前任務資料...\n";
    $sql = "SELECT task_id, task_name, task_description, task_type, reward_points, reward_achievement, is_active FROM daily_tasks ORDER BY task_id";
    $stmt = $pdo->query($sql);
    $tasks = $stmt->fetchAll();
    
    echo "目前任務資料：\n";
    foreach ($tasks as $task) {
        echo "ID: " . $task['task_id'] . 
             " | 名稱: " . $task['task_name'] . 
             " | 分數: " . $task['reward_points'] . 
             " | 成就: " . ($task['reward_achievement'] ?? 'NULL') . "\n";
    }
    
    // 更新所有任務為成就獎勵
    echo "\n更新任務為成就獎勵...\n";
    
    $achievement_mapping = [
        1 => '遊戲新手',
        2 => '對戰達人', 
        3 => '社交達人',
        47 => '全能玩家',
        48 => '突破自我',
        49 => '勝利王者',
        50 => '交友達人',
        51 => '陪伴達人',
        52 => '忠實玩家',
        53 => '記憶大師',
        54 => '反應達人',
        55 => '邏輯高手',
        56 => '堅持不懈',
        57 => '任務達人',
        58 => '邀請專家',
        59 => '高分玩家',
        60 => '遊戲探索者',
        61 => '對戰專家',
        62 => '配對高手',
        63 => '節奏大師',
        64 => '計算專家',
        65 => '2048高手',
        66 => '接蛋專家',
        67 => '追蹤專家',
        68 => '文字專家',
        69 => '分享達人',
        70 => '完美主義者'
    ];
    
    $update_sql = "UPDATE daily_tasks SET reward_points = 0, reward_achievement = ? WHERE task_id = ?";
    $update_stmt = $pdo->prepare($update_sql);
    
    $updated_count = 0;
    foreach ($achievement_mapping as $task_id => $achievement) {
        $update_stmt->execute([$achievement, $task_id]);
        echo "✓ 已更新任務 " . $task_id . " -> " . $achievement . "\n";
        $updated_count++;
    }
    
    echo "\n=== 更新完成 ===\n";
    echo "成功更新 " . $updated_count . " 個任務\n";
    
    // 顯示更新後的結果
    echo "\n更新後的任務：\n";
    $sql = "SELECT task_id, task_name, task_description, task_type, reward_points, reward_achievement, is_active FROM daily_tasks ORDER BY task_id";
    $stmt = $pdo->query($sql);
    $updated_tasks = $stmt->fetchAll();
    
    foreach ($updated_tasks as $task) {
        echo "ID: " . $task['task_id'] . 
             " | 名稱: " . $task['task_name'] . 
             " | 分數: " . $task['reward_points'] . 
             " | 成就: " . $task['reward_achievement'] . "\n";
    }
    
    echo "\n✅ 資料庫已同步完成！\n";
    
} catch (Exception $e) {
    echo "✗ 操作失敗: " . $e->getMessage() . "\n";
}
?> 