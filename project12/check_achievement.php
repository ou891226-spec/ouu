<?php
require_once 'db.php';

echo "=== 檢查全能玩家成就 ===\n\n";

// 檢查全能玩家成就是否存在
$sql = "SELECT * FROM achievements WHERE achievement_name = '全能玩家'";
$stmt = $pdo->query($sql);
$result = $stmt->fetchAll();

if(empty($result)) {
    echo "❌ 全能玩家成就不存在於資料庫中\n\n";
    
    // 檢查所有成就
    echo "=== 所有成就列表 ===\n";
    $all_sql = "SELECT achievement_id, achievement_name, icon FROM achievements ORDER BY achievement_id";
    $all_stmt = $pdo->query($all_sql);
    $all_achievements = $all_stmt->fetchAll();
    
    foreach($all_achievements as $achievement) {
        echo "ID: {$achievement['achievement_id']} | 名稱: {$achievement['achievement_name']} | 圖標: {$achievement['icon']}\n";
    }
    
    echo "\n=== 需要添加全能玩家成就 ===\n";
    
} else {
    echo "✅ 全能玩家成就存在:\n";
    foreach($result as $achievement) {
        echo "ID: {$achievement['achievement_id']} | 名稱: {$achievement['achievement_name']} | 圖標: {$achievement['icon']}\n";
    }
}

// 檢查任務表中的全能玩家任務
echo "\n=== 檢查全能玩家任務 ===\n";
$task_sql = "SELECT * FROM daily_tasks WHERE reward_achievement = '全能玩家' OR task_name = '全能玩家'";
$task_stmt = $pdo->query($task_sql);
$task_result = $task_stmt->fetchAll();

if(empty($task_result)) {
    echo "❌ 沒有找到全能玩家相關任務\n";
} else {
    echo "✅ 找到全能玩家相關任務:\n";
    foreach($task_result as $task) {
        echo "任務ID: {$task['task_id']} | 名稱: {$task['task_name']} | 描述: {$task['task_description']} | 獎勵成就: {$task['reward_achievement']}\n";
    }
}
?>
