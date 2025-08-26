<?php
require_once 'db.php';
session_start();

// 強制使用會員ID 23（當前登入的用戶）
$member_id = 23;

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

// 已經在SQL中限制為3個任務，不需要再次切片

echo json_encode($tasks, JSON_UNESCAPED_UNICODE);
?> 