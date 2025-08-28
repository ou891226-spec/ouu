<?php
require_once 'db.php';
session_start();

// 檢查用戶是否已登入
if (!isset($_SESSION['member_id']) || empty($_SESSION['member_id'])) {
    echo json_encode(['error' => '用戶未登入'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 獲取當前登入用戶的ID
$member_id = $_SESSION['member_id'];

// 從資料庫獲取用戶實際擁有的任務狀態（排除登入任務，只取前3個）
$tasks_sql = "
SELECT d.task_id, d.task_name, d.task_description, d.task_type, d.reward_points,
       mt.completed_date,
       mt.claimed_date,
       CASE 
           WHEN mt.claimed_date IS NOT NULL THEN 'claimed'
           WHEN mt.completed_date IS NOT NULL THEN 'completed'
           ELSE 'pending'
       END as status,
       CASE 
           WHEN mt.claimed_date IS NOT NULL OR mt.completed_date IS NOT NULL THEN 1
           ELSE 0
       END as progress,
       1 as required
FROM member_tasks mt
JOIN daily_tasks d ON mt.task_id = d.task_id
WHERE mt.member_id = ? AND d.is_active = 1
AND d.task_name != '登入網站一次'
ORDER BY mt.task_id
LIMIT 3
";
$stmt = $pdo->prepare($tasks_sql);
$stmt->execute([$member_id]);
$tasks = $stmt->fetchAll();

// 如果沒有任務，返回空陣列
if (empty($tasks)) {
    echo json_encode([], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode($tasks, JSON_UNESCAPED_UNICODE);
}
?> 