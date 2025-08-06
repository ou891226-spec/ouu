<?php
require_once 'db.php';

echo "添加更多每日任務...\n";

// 檢查現有任務數量
$sql = "SELECT COUNT(*) as count FROM daily_tasks WHERE is_active = 1";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$result = $stmt->fetch();
$current_count = $result['count'];

echo "目前有 {$current_count} 個任務\n";

// 如果任務數量少於10個，則添加更多任務
if ($current_count < 10) {
    $tasks_to_add = [
        ['登入網站一次', '成功登入遊戲網站', 'login', 5],
        ['遊玩2048遊戲', '完成一局2048遊戲', 'game_2048', 15],
        ['遊玩記憶遊戲', '完成一局記憶遊戲', 'memory_game', 15],
        ['遊玩接金蛋遊戲', '完成一局接金蛋遊戲', 'catch_egg', 15],
        ['遊玩節奏遊戲', '完成一局節奏遊戲', 'rhythm_game', 15],
        ['遊玩追蹤犯人遊戲', '完成一局追蹤犯人遊戲', 'prisoner_game', 15],
        ['遊玩看字選色遊戲', '完成一局看字選色遊戲', 'text_color', 15],
        ['遊玩算菜錢遊戲', '完成一局算菜錢遊戲', 'vegetable_cost', 15],
        ['獲得100分', '在任意遊戲中獲得100分', 'score_100', 20],
        ['獲得500分', '在任意遊戲中獲得500分', 'score_500', 30],
        ['獲得1000分', '在任意遊戲中獲得1000分', 'score_1000', 50],
        ['連續登入3天', '連續登入遊戲網站3天', 'login_streak_3', 25],
        ['連續登入7天', '連續登入遊戲網站7天', 'login_streak_7', 60],
        ['完成所有每日任務', '在同一天完成所有3個每日任務', 'complete_all_daily', 100],
        ['邀請一位好友', '成功邀請一位新好友加入', 'invite_friend', 20],
        ['查看排行榜', '查看遊戲排行榜', 'view_ranking', 5],
        ['查看個人分析', '查看個人遊戲分析圖表', 'view_analysis', 10],
        ['查看歷史紀錄', '查看個人遊戲歷史紀錄', 'view_history', 10],
        ['查看相關報導', '查看遊戲相關報導', 'view_news', 5],
        ['查看關於我們', '查看關於我們的頁面', 'view_about', 5]
    ];
    
    $added_count = 0;
    foreach ($tasks_to_add as $task) {
        // 檢查任務是否已存在
        $check_sql = "SELECT COUNT(*) as count FROM daily_tasks WHERE task_name = ?";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute([$task[0]]);
        $exists = $check_stmt->fetch()['count'] > 0;
        
        if (!$exists) {
            $insert_sql = "INSERT INTO daily_tasks (task_name, task_description, task_type, reward_points, is_active) VALUES (?, ?, ?, ?, 1)";
            $insert_stmt = $pdo->prepare($insert_sql);
            $insert_stmt->execute($task);
            $added_count++;
            echo "已添加任務: {$task[0]}\n";
        }
    }
    
    echo "總共添加了 {$added_count} 個新任務\n";
} else {
    echo "任務數量已足夠，無需添加更多任務\n";
}

// 顯示所有任務
echo "\n所有可用任務：\n";
$sql = "SELECT task_id, task_name, task_description, task_type, reward_points FROM daily_tasks WHERE is_active = 1 ORDER BY task_id";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$all_tasks = $stmt->fetchAll();

foreach ($all_tasks as $task) {
    echo "- ID: {$task['task_id']}, 名稱: {$task['task_name']}, 描述: {$task['task_description']}, 類型: {$task['task_type']}, 獎勵: {$task['reward_points']}\n";
}

echo "\n完成！現在每日任務系統會隨機選擇3個不重複的任務。\n";
?> 