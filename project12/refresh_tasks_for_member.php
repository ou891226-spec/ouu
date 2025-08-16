<?php
include 'db.php';

$member_id = 23;

echo "<h1>刷新用戶 $member_id 的每日任務</h1>";

try {
    $pdo->beginTransaction();

    // 1. 刪除用戶現有的每日任務
    $delete_sql = "DELETE FROM member_tasks WHERE member_id = ?";
    $stmt = $pdo->prepare($delete_sql);
    $stmt->execute([$member_id]);
    echo "<p>已清除用戶 $member_id 的所有現有任務。</p>";

    // 2. 獲取要分配的任務ID
    $tasks_to_assign = [
        '完成一場記憶力遊戲',
        '在蔬菜遊戲中正確計算3次', 
        '完成三種不同類型遊戲'
    ];

    $new_task_ids = [];
    foreach ($tasks_to_assign as $description) {
        $find_task_sql = "SELECT task_id FROM daily_tasks WHERE task_description = ?";
        $stmt = $pdo->prepare($find_task_sql);
        $stmt->execute([$description]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($task) {
            $new_task_ids[] = $task['task_id'];
        } else {
            echo "<p>警告：找不到任務描述為 '{$description}' 的任務。</p>";
        }
    }

    if (empty($new_task_ids)) {
        echo "<p>沒有找到任何可分配的任務。請檢查 daily_tasks 表格。</p>";
        $pdo->rollBack();
        exit;
    }

    // 3. 為用戶分配新的每日任務
    $insert_sql = "INSERT INTO member_tasks (member_id, task_id, completed_date, claimed_date) VALUES (?, ?, NULL, NULL)";
    $stmt = $pdo->prepare($insert_sql);

    foreach ($new_task_ids as $task_id) {
        $stmt->execute([$member_id, $task_id]);
        echo "<p>已為用戶 $member_id 分配任務 ID: $task_id。</p>";
    }

    $pdo->commit();
    echo "<p>任務刷新完成！</p>";

} catch (Exception $e) {
    $pdo->rollBack();
    echo "<p>任務刷新失敗: " . $e->getMessage() . "</p>";
    error_log("任務刷新失敗: " . $e->getMessage());
}
?> 