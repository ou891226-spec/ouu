<?php
include 'db.php';

echo "<h1>檢查 daily_tasks 表格</h1>";

// 檢查所有任務
$stmt = $pdo->query("SELECT task_id, task_name, task_description, is_active FROM daily_tasks ORDER BY task_id");
$tasks = $stmt->fetchAll();

echo "<h2>所有任務：</h2>";
if (empty($tasks)) {
    echo "<p>daily_tasks 表格是空的</p>";
} else {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>名稱</th><th>描述</th><th>狀態</th></tr>";
    foreach ($tasks as $task) {
        echo "<tr>";
        echo "<td>" . $task['task_id'] . "</td>";
        echo "<td>" . $task['task_name'] . "</td>";
        echo "<td>" . $task['task_description'] . "</td>";
        echo "<td>" . ($task['is_active'] ? '啟用' : '停用') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// 檢查特定任務
echo "<h2>檢查特定任務：</h2>";
$specific_tasks = [
    '完成一場記憶力遊戲',
    '在蔬菜遊戲中正確計算3次',
    '完成三種不同類型遊戲'
];

foreach ($specific_tasks as $description) {
    $stmt = $pdo->prepare("SELECT task_id, task_name, task_description FROM daily_tasks WHERE task_description = ?");
    $stmt->execute([$description]);
    $task = $stmt->fetch();
    
    if ($task) {
        echo "<p>✅ 找到任務：{$description} (ID: {$task['task_id']})</p>";
    } else {
        echo "<p>❌ 找不到任務：{$description}</p>";
    }
}
?> 