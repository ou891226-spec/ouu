<?php
include 'db.php';

$member_id = 23;

echo "<h1>檢查任務狀態</h1>";

// 檢查記憶力遊戲相關任務
$stmt = $pdo->prepare("
    SELECT mt.task_id, mt.completed_date, mt.claimed_date, d.task_name, d.task_description, d.reward_achievement
    FROM member_tasks mt
    JOIN daily_tasks d ON mt.task_id = d.task_id
    WHERE mt.member_id = ? AND d.task_description LIKE '%記憶力遊戲%'
    ORDER BY mt.task_id DESC
");
$stmt->execute([$member_id]);
$tasks = $stmt->fetchAll();

echo "<h2>記憶力遊戲相關任務：</h2>";
if (empty($tasks)) {
    echo "<p>沒有找到記憶力遊戲相關任務</p>";
} else {
    foreach ($tasks as $task) {
        echo "<div style='border: 1px solid #ccc; padding: 10px; margin: 5px;'>";
        echo "<strong>任務ID:</strong> " . $task['task_id'] . "<br>";
        echo "<strong>任務名稱:</strong> " . $task['task_name'] . "<br>";
        echo "<strong>任務描述:</strong> " . $task['task_description'] . "<br>";
        echo "<strong>完成時間:</strong> " . ($task['completed_date'] ?: '未完成') . "<br>";
        echo "<strong>領取時間:</strong> " . ($task['claimed_date'] ?: '未領取') . "<br>";
        echo "<strong>獎勵成就:</strong> " . ($task['reward_achievement'] ?: '無') . "<br>";
        echo "</div>";
    }
}

// 檢查所有今天的任務
echo "<h2>今天的所有任務：</h2>";
$stmt = $pdo->prepare("
    SELECT mt.task_id, mt.completed_date, mt.claimed_date, d.task_name, d.task_description
    FROM member_tasks mt
    JOIN daily_tasks d ON mt.task_id = d.task_id
    WHERE mt.member_id = ? AND DATE(mt.completed_date) = CURDATE()
    ORDER BY mt.completed_date DESC
");
$stmt->execute([$member_id]);
$today_tasks = $stmt->fetchAll();

if (empty($today_tasks)) {
    echo "<p>今天沒有完成任何任務</p>";
} else {
    foreach ($today_tasks as $task) {
        echo "<div style='border: 1px solid #ccc; padding: 10px; margin: 5px;'>";
        echo "<strong>任務ID:</strong> " . $task['task_id'] . "<br>";
        echo "<strong>任務名稱:</strong> " . $task['task_name'] . "<br>";
        echo "<strong>任務描述:</strong> " . $task['task_description'] . "<br>";
        echo "<strong>完成時間:</strong> " . $task['completed_date'] . "<br>";
        echo "<strong>領取時間:</strong> " . ($task['claimed_date'] ?: '未領取') . "<br>";
        echo "</div>";
    }
}
?> 