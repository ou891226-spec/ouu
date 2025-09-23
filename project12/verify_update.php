<?php
require_once 'db.php';

echo "=== 驗證資料庫更新結果 ===\n";

// 檢查社交大師任務狀態
$sql1 = "SELECT task_id, task_name, is_active FROM daily_tasks WHERE task_name = '社交大師'";
$stmt1 = $pdo->query($sql1);
$social_task = $stmt1->fetch();

if ($social_task) {
    echo "社交大師任務: ID {$social_task['task_id']}, 啟用狀態: " . ($social_task['is_active'] ? '是' : '否') . "\n";
} else {
    echo "沒有找到社交大師任務\n";
}

// 檢查刷新最高分數任務
$sql2 = "SELECT task_id, task_name, task_description, is_active FROM daily_tasks WHERE task_name = '刷新最高分數'";
$stmt2 = $pdo->query($sql2);
$high_score_task = $stmt2->fetch();

if ($high_score_task) {
    echo "刷新最高分數任務: ID {$high_score_task['task_id']}, 啟用狀態: " . ($high_score_task['is_active'] ? '是' : '否') . "\n";
    echo "描述: {$high_score_task['task_description']}\n";
} else {
    echo "沒有找到刷新最高分數任務\n";
}

// 檢查用戶任務分配
$sql3 = "SELECT COUNT(*) as count FROM member_tasks mt JOIN daily_tasks d ON mt.task_id = d.task_id WHERE d.task_name = '社交大師'";
$stmt3 = $pdo->query($sql3);
$social_count = $stmt3->fetch()['count'];
echo "用戶的社交大師任務分配: {$social_count} 個\n";

$sql4 = "SELECT COUNT(*) as count FROM member_tasks mt JOIN daily_tasks d ON mt.task_id = d.task_id WHERE d.task_name = '刷新最高分數'";
$stmt4 = $pdo->query($sql4);
$high_score_count = $stmt4->fetch()['count'];
echo "用戶的刷新最高分數任務分配: {$high_score_count} 個\n";

echo "=== 驗證完成 ===\n";
?>
