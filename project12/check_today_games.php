<?php
require_once 'db.php';

echo "<h1>檢查會員23今天的遊戲記錄</h1>";

try {
    $member_id = 23;
    $today = date('Y-m-d');
    
    // 檢查今天玩過的所有遊戲
    $games_sql = "
        SELECT game_type, COUNT(*) as play_count
        FROM game_records 
        WHERE member_id = ? AND DATE(play_date) = ?
        GROUP BY game_type
        ORDER BY play_count DESC
    ";
    $games_stmt = $pdo->prepare($games_sql);
    $games_stmt->execute([$member_id, $today]);
    $games = $games_stmt->fetchAll();
    
    echo "<h2>今天玩過的遊戲類型：</h2>";
    if ($games) {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>遊戲類型</th><th>遊戲次數</th></tr>";
        foreach ($games as $game) {
            echo "<tr>";
            echo "<td>" . $game['game_type'] . "</td>";
            echo "<td>" . $game['play_count'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        echo "<h3>總結：</h3>";
        echo "<p>今天總共玩了 " . count($games) . " 種不同類型的遊戲</p>";
        echo "<ul>";
        foreach ($games as $game) {
            echo "<li>" . $game['game_type'] . " - " . $game['play_count'] . " 次</li>";
        }
        echo "</ul>";
        
        // 檢查任務47的狀態
        $task_47_sql = "
            SELECT mt.task_id, mt.completed_date, d.task_description
            FROM member_tasks mt
            JOIN daily_tasks d ON mt.task_id = d.task_id
            WHERE mt.member_id = ? AND d.task_id = 47
        ";
        $task_47_stmt = $pdo->prepare($task_47_sql);
        $task_47_stmt->execute([$member_id]);
        $task_47 = $task_47_stmt->fetch();
        
        echo "<h3>任務47狀態：</h3>";
        if ($task_47) {
            echo "<p>任務描述：" . $task_47['task_description'] . "</p>";
            echo "<p>完成時間：" . ($task_47['completed_date'] ?? '未完成') . "</p>";
        } else {
            echo "<p>沒有找到任務47</p>";
        }
        
        // 檢查記憶力遊戲任務
        $memory_task_sql = "
            SELECT mt.task_id, mt.completed_date, d.task_description
            FROM member_tasks mt
            JOIN daily_tasks d ON mt.task_id = d.task_id
            WHERE mt.member_id = ? AND d.task_description LIKE '%記憶力遊戲%'
        ";
        $memory_task_stmt = $pdo->prepare($memory_task_sql);
        $memory_task_stmt->execute([$member_id]);
        $memory_tasks = $memory_task_stmt->fetchAll();
        
        echo "<h3>記憶力遊戲任務狀態：</h3>";
        if ($memory_tasks) {
            foreach ($memory_tasks as $task) {
                echo "<p>任務ID：" . $task['task_id'] . "</p>";
                echo "<p>任務描述：" . $task['task_description'] . "</p>";
                echo "<p>完成時間：" . ($task['completed_date'] ?? '未完成') . "</p>";
            }
        } else {
            echo "<p>沒有找到記憶力遊戲相關任務</p>";
        }
        
    } else {
        echo "<p>今天沒有遊戲記錄</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>錯誤：" . $e->getMessage() . "</p>";
}

echo "<h2>檢查完成</h2>";
echo "<p><a href='index.php'>返回主頁</a></p>";
?> 