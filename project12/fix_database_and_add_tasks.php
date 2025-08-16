<?php
require_once 'db.php';

echo "=== 修復資料庫並添加任務 ===\n\n";

try {
    // 檢查並修復資料庫欄位
    echo "檢查資料庫欄位...\n";
    
    // 檢查reward_points欄位是否有預設值
    $check_sql = "SHOW COLUMNS FROM daily_tasks LIKE 'reward_points'";
    $check_stmt = $pdo->query($check_sql);
    $reward_points_column = $check_stmt->fetch();
    
    if ($reward_points_column) {
        echo "✓ reward_points 欄位存在\n";
        
        // 如果reward_points欄位沒有預設值，添加預設值
        if ($reward_points_column['Default'] === null) {
            $alter_sql = "ALTER TABLE daily_tasks MODIFY COLUMN reward_points INT DEFAULT 0";
            $pdo->exec($alter_sql);
            echo "✓ 已為 reward_points 欄位添加預設值 0\n";
        }
    }
    
    // 檢查reward_achievement欄位
    $check_achievement_sql = "SHOW COLUMNS FROM daily_tasks LIKE 'reward_achievement'";
    $check_achievement_stmt = $pdo->query($check_achievement_sql);
    $reward_achievement_column = $check_achievement_stmt->fetch();
    
    if (!$reward_achievement_column) {
        $add_achievement_sql = "ALTER TABLE daily_tasks ADD COLUMN reward_achievement VARCHAR(100) DEFAULT NULL AFTER reward_points";
        $pdo->exec($add_achievement_sql);
        echo "✓ 已添加 reward_achievement 欄位\n";
    } else {
        echo "✓ reward_achievement 欄位已存在\n";
    }
    
    echo "\n資料庫修復完成！\n\n";
    
    // 新增的任務配置
    $new_tasks = [
        [
            'task_id' => 53,
            'task_name' => '成就任務',
            'task_description' => '完成一場記憶力遊戲',
            'task_type' => 'Achievement',
            'reward_points' => 0,
            'reward_achievement' => '記憶大師',
            'is_active' => 1
        ],
        [
            'task_id' => 54,
            'task_name' => '成就任務',
            'task_description' => '完成一場反應力遊戲',
            'task_type' => 'Achievement',
            'reward_points' => 0,
            'reward_achievement' => '反應達人',
            'is_active' => 1
        ],
        [
            'task_id' => 55,
            'task_name' => '成就任務',
            'task_description' => '完成一場邏輯遊戲',
            'task_type' => 'Achievement',
            'reward_points' => 0,
            'reward_achievement' => '邏輯高手',
            'is_active' => 1
        ],
        [
            'task_id' => 56,
            'task_name' => '成就任務',
            'task_description' => '連續登入3天',
            'task_type' => 'login',
            'reward_points' => 0,
            'reward_achievement' => '堅持不懈',
            'is_active' => 1
        ],
        [
            'task_id' => 57,
            'task_name' => '成就任務',
            'task_description' => '完成5個任務',
            'task_type' => 'Achievement',
            'reward_points' => 0,
            'reward_achievement' => '任務達人',
            'is_active' => 1
        ],
        [
            'task_id' => 58,
            'task_name' => '好友任務',
            'task_description' => '邀請好友一起遊戲',
            'task_type' => 'friend',
            'reward_points' => 0,
            'reward_achievement' => '邀請專家',
            'is_active' => 1
        ],
        [
            'task_id' => 59,
            'task_name' => '成就任務',
            'task_description' => '在任一遊戲中獲得100分以上',
            'task_type' => 'Achievement',
            'reward_points' => 0,
            'reward_achievement' => '高分玩家',
            'is_active' => 1
        ],
        [
            'task_id' => 60,
            'task_name' => '成就任務',
            'task_description' => '遊玩3種不同遊戲',
            'task_type' => 'Achievement',
            'reward_points' => 0,
            'reward_achievement' => '遊戲探索者',
            'is_active' => 1
        ],
        [
            'task_id' => 61,
            'task_name' => '成就任務',
            'task_description' => '完成一場2人對戰遊戲',
            'task_type' => 'Achievement',
            'reward_points' => 0,
            'reward_achievement' => '對戰專家',
            'is_active' => 1
        ],
        [
            'task_id' => 62,
            'task_name' => '成就任務',
            'task_description' => '在記憶力遊戲中配對成功10次',
            'task_type' => 'Achievement',
            'reward_points' => 0,
            'reward_achievement' => '配對高手',
            'is_active' => 1
        ],
        [
            'task_id' => 63,
            'task_name' => '成就任務',
            'task_description' => '在節奏遊戲中連續擊中5次',
            'task_type' => 'Achievement',
            'reward_points' => 0,
            'reward_achievement' => '節奏大師',
            'is_active' => 1
        ],
        [
            'task_id' => 64,
            'task_name' => '成就任務',
            'task_description' => '在蔬菜遊戲中正確計算3次',
            'task_type' => 'Achievement',
            'reward_points' => 0,
            'reward_achievement' => '計算專家',
            'is_active' => 1
        ],
        [
            'task_id' => 65,
            'task_name' => '成就任務',
            'task_description' => '在2048遊戲中達到512分',
            'task_type' => 'Achievement',
            'reward_points' => 0,
            'reward_achievement' => '2048高手',
            'is_active' => 1
        ],
        [
            'task_id' => 66,
            'task_name' => '成就任務',
            'task_description' => '在接蛋遊戲中接到10個蛋',
            'task_type' => 'Achievement',
            'reward_points' => 0,
            'reward_achievement' => '接蛋專家',
            'is_active' => 1
        ],
        [
            'task_id' => 67,
            'task_name' => '成就任務',
            'task_description' => '在犯人遊戲中成功追蹤3次',
            'task_type' => 'Achievement',
            'reward_points' => 0,
            'reward_achievement' => '追蹤專家',
            'is_active' => 1
        ],
        [
            'task_id' => 68,
            'task_name' => '成就任務',
            'task_description' => '在文字顏色遊戲中正確回答5題',
            'task_type' => 'Achievement',
            'reward_points' => 0,
            'reward_achievement' => '文字專家',
            'is_active' => 1
        ],
        [
            'task_id' => 69,
            'task_name' => '好友任務',
            'task_description' => '與好友分享遊戲成績',
            'task_type' => 'friend',
            'reward_points' => 0,
            'reward_achievement' => '分享達人',
            'is_active' => 1
        ],
        [
            'task_id' => 70,
            'task_name' => '成就任務',
            'task_description' => '完成所有今日任務',
            'task_type' => 'Achievement',
            'reward_points' => 0,
            'reward_achievement' => '完美主義者',
            'is_active' => 1
        ]
    ];
    
    echo "要添加的新任務：\n";
    foreach ($new_tasks as $task) {
        echo "- ID: " . $task['task_id'] . 
             ", 名稱: " . $task['task_name'] . 
             ", 描述: " . $task['task_description'] . 
             ", 成就: " . $task['reward_achievement'] . "\n";
    }
    
    echo "\n確認要添加這些任務嗎？(y/n): ";
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    fclose($handle);
    
    if (trim($line) !== 'y') {
        echo "取消操作\n";
        exit;
    }
    
    echo "\n開始添加任務...\n";
    
    // 檢查是否已存在這些任務ID
    $existing_ids = [];
    foreach ($new_tasks as $task) {
        $check_sql = "SELECT task_id FROM daily_tasks WHERE task_id = ?";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute([$task['task_id']]);
        if ($check_stmt->fetch()) {
            $existing_ids[] = $task['task_id'];
        }
    }
    
    if (!empty($existing_ids)) {
        echo "⚠️ 以下任務ID已存在，將跳過: " . implode(', ', $existing_ids) . "\n";
        $new_tasks = array_filter($new_tasks, function($task) use ($existing_ids) {
            return !in_array($task['task_id'], $existing_ids);
        });
    }
    
    // 添加新任務
    $insert_sql = "INSERT INTO daily_tasks (task_id, task_name, task_description, task_type, reward_points, reward_achievement, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $insert_stmt = $pdo->prepare($insert_sql);
    
    $added_count = 0;
    foreach ($new_tasks as $task) {
        $insert_stmt->execute([
            $task['task_id'],
            $task['task_name'],
            $task['task_description'],
            $task['task_type'],
            $task['reward_points'],
            $task['reward_achievement'],
            $task['is_active']
        ]);
        echo "✓ 已添加任務: " . $task['task_name'] . " -> 成就: " . $task['reward_achievement'] . "\n";
        $added_count++;
    }
    
    echo "\n=== 添加完成 ===\n";
    echo "成功添加 " . $added_count . " 個新任務\n";
    
    // 顯示所有任務
    echo "\n檢查所有任務：\n";
    $sql = "SELECT task_id, task_name, task_description, task_type, reward_achievement, is_active FROM daily_tasks ORDER BY task_id";
    $stmt = $pdo->query($sql);
    $all_tasks = $stmt->fetchAll();
    
    foreach ($all_tasks as $task) {
        echo "ID: " . $task['task_id'] . 
             " | 名稱: " . $task['task_name'] . 
             " | 類型: " . $task['task_type'] . 
             " | 成就: " . $task['reward_achievement'] .
             " | 狀態: " . ($task['is_active'] ? '啟用' : '停用') . "\n";
    }
    
    echo "\n總共 " . count($all_tasks) . " 個任務\n";
    
} catch (Exception $e) {
    echo "✗ 操作失敗: " . $e->getMessage() . "\n";
}
?> 