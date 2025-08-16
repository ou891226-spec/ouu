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

    // 2. 為用戶分配新的每日任務
    $insert_sql = "INSERT INTO member_tasks (member_id, task_id, completed_date, claimed_date) VALUES (?, ?, NULL, NULL)";
    $stmt = $pdo->prepare($insert_sql);

    // 分配任務 53 (記憶力遊戲)
    $stmt->execute([$member_id, 53]);
    echo "<p>已分配任務 53: 完成一場記憶力遊戲</p>";

    // 分配任務 64 (蔬菜遊戲)
    $stmt->execute([$member_id, 64]);
    echo "<p>已分配任務 64: 在蔬菜遊戲中正確計算3次</p>";

    // 分配任務 47 (三種不同類型遊戲)
    $stmt->execute([$member_id, 47]);
    echo "<p>已分配任務 47: 完成三種不同類型遊戲</p>";

    $pdo->commit();
    echo "<p>任務刷新完成！</p>";

} catch (Exception $e) {
    $pdo->rollBack();
    echo "<p>任務刷新失敗: " . $e->getMessage() . "</p>";
    error_log("任務刷新失敗: " . $e->getMessage());
}
?> 