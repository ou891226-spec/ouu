<?php
include 'db.php';

$member_id = 23;

echo "<h1>手動觸發任務</h1>";

try {
    $pdo->beginTransaction();

    // 檢查並完成相關任務
    $task_check_sql = "
    SELECT mt.task_id, mt.completed_date, d.task_description
    FROM member_tasks mt
    JOIN daily_tasks d ON mt.task_id = d.task_id
    WHERE mt.member_id = ? AND mt.completed_date IS NULL AND (
        d.task_description LIKE '%遊玩任一普通關卡%' OR
        d.task_description LIKE '%完成任意一場遊戲%'
    )
    ";
    $task_stmt = $pdo->prepare($task_check_sql);
    $task_stmt->execute([$member_id]);
    $completed_tasks = $task_stmt->fetchAll();
    
    if (empty($completed_tasks)) {
        echo "<p>沒有找到可以完成的任務</p>";
    } else {
        foreach ($completed_tasks as $task) {
            // 完成任務
            $complete_task_sql = "UPDATE member_tasks SET completed_date = NOW() WHERE member_id = ? AND task_id = ?";
            $complete_stmt = $pdo->prepare($complete_task_sql);
            $complete_stmt->execute([$member_id, $task['task_id']]);
            echo "<p>✅ 已完成任務: {$task['task_description']}</p>";
        }
    }

    $pdo->commit();
    echo "<p>任務觸發完成！</p>";

} catch (Exception $e) {
    $pdo->rollBack();
    echo "<p>任務觸發失敗: " . $e->getMessage() . "</p>";
}
?> 