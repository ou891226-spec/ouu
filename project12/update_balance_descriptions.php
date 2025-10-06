<?php
require_once 'db.php';

// 更新平衡任務的描述，讓它們更準確
$tasks = [
    '平衡玩家' => '每種類型遊戲都成功破關至少1局',
    '全面發展' => '每種類型遊戲都成功破關至少3局'
];

foreach($tasks as $task_name => $new_description) {
    $stmt = $pdo->prepare("UPDATE daily_tasks SET task_description = ? WHERE task_name = ?");
    $stmt->execute([$new_description, $task_name]);
    echo "已更新: $task_name\n";
}

echo "\n更新完成！檢查結果：\n";

// 檢查更新結果
$stmt = $pdo->query("SELECT task_name, task_description FROM daily_tasks WHERE task_name IN ('平衡玩家', '全面發展') ORDER BY task_name");
while($row = $stmt->fetch()) {
    echo $row['task_name'] . ': ' . $row['task_description'] . PHP_EOL;
}
?>
