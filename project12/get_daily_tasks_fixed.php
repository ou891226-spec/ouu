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
// 支援累積型任務的進度計算
$tasks_sql = "
SELECT d.task_id, d.task_name, d.task_description, d.task_type, d.reward_points,
       mt.completed_date,
       mt.claimed_date,
       CASE 
           WHEN mt.claimed_date IS NOT NULL THEN 'claimed'
           WHEN mt.completed_date IS NOT NULL THEN 'completed'
           -- 動態判斷累積型任務是否已達成
           WHEN (
               -- 遊戲達人：完成10局遊戲（更寬鬆的匹配條件）
               (d.task_name = '遊戲達人' OR d.task_description LIKE '%完成10局%' OR d.task_description LIKE '%10局%' OR d.task_description LIKE '%完成%局遊戲%') AND
               (SELECT COUNT(*) FROM game_records WHERE member_id = mt.member_id AND DATE(play_date) = CURDATE()) >= 10
           ) THEN 'completed'
           WHEN (
               -- 分數收集者：獲得指定分數
               (d.task_description LIKE '%獲得1000分%') AND
               (SELECT COALESCE(SUM(score), 0) FROM game_records WHERE member_id = mt.member_id AND DATE(play_date) = CURDATE()) >= 1000
           ) THEN 'completed'
           WHEN (
               -- 全能玩家：完成3種不同類型遊戲
               (d.task_description LIKE '%三種不同類型%' OR d.task_description LIKE '%不同類型%') AND
               (SELECT COUNT(DISTINCT game_type) FROM game_records WHERE member_id = mt.member_id AND DATE(play_date) = CURDATE()) >= 3
           ) THEN 'completed'
           WHEN (
               -- 持久戰士：累積遊戲時間達到目標
               (d.task_description LIKE '%分鐘%' OR d.task_description LIKE '%遊玩時間%') AND
               (SELECT COALESCE(SUM(play_time), 0) FROM game_records WHERE member_id = mt.member_id AND DATE(play_date) = CURDATE() AND play_time IS NOT NULL) >= CASE 
                   WHEN d.task_description LIKE '%5分鐘%' THEN 300
                   WHEN d.task_description LIKE '%3分鐘%' THEN 180
                   WHEN d.task_description LIKE '%10分鐘%' THEN 600
                   ELSE 300
               END
           ) THEN 'completed'
           ELSE 'pending'
       END as status,
       CASE 
           -- 遊戲達人：完成10局遊戲（更寬鬆的匹配條件）
           WHEN d.task_name = '遊戲達人' OR d.task_description LIKE '%完成10局%' OR d.task_description LIKE '%10局%' OR d.task_description LIKE '%完成%局遊戲%' THEN (
               SELECT COUNT(*) FROM game_records 
               WHERE member_id = mt.member_id 
               AND DATE(play_date) = CURDATE()
           )
           -- 分數收集者：獲得指定分數（只計算今天的）
           WHEN d.task_description LIKE '%獲得%分%' OR d.task_description LIKE '%分數%' THEN (
               SELECT COALESCE(SUM(score), 0) FROM game_records 
               WHERE member_id = mt.member_id 
               AND DATE(play_date) = CURDATE()
           )
           -- 全能玩家：完成不同類型遊戲（只計算今天的）
           WHEN d.task_description LIKE '%三種不同類型%' OR d.task_description LIKE '%不同類型%' THEN (
               SELECT COUNT(DISTINCT game_type) FROM game_records 
               WHERE member_id = mt.member_id 
               AND DATE(play_date) = CURDATE()
           )
           -- 持久戰士：累積遊戲時間（更合理的累積模式）
           WHEN d.task_description LIKE '%分鐘%' OR d.task_description LIKE '%遊玩時間%' THEN (
               SELECT COALESCE(SUM(play_time), 0) FROM game_records 
               WHERE member_id = mt.member_id 
               AND DATE(play_date) = CURDATE() 
               AND play_time IS NOT NULL
           )
           -- 累積遊戲時間任務（只計算今天的）
           WHEN d.task_description LIKE '%累計%分鐘%' OR d.task_description LIKE '%總共%分鐘%' THEN (
               SELECT COALESCE(SUM(play_time), 0) FROM game_records 
               WHERE member_id = mt.member_id 
               AND DATE(play_date) = CURDATE() 
               AND play_time IS NOT NULL
           )
           -- 社交達人：添加好友
           WHEN d.task_description LIKE '%好友%' THEN (
               -- 暫時返回0，等待friends表實現
               0
           )
           -- 其他任務：完成狀態
           WHEN mt.claimed_date IS NOT NULL OR mt.completed_date IS NOT NULL THEN 1
           ELSE 0
       END as progress,
       CASE 
           -- 遊戲達人：10局遊戲
           WHEN d.task_name = '遊戲達人' OR d.task_description LIKE '%完成10局%' OR d.task_description LIKE '%10局%' OR d.task_description LIKE '%完成%局遊戲%' THEN 10
           -- 分數收集者：根據描述提取目標分數
           WHEN d.task_description LIKE '%獲得1000分%' THEN 1000
           WHEN d.task_description LIKE '%獲得500分%' THEN 500
           WHEN d.task_description LIKE '%獲得2000分%' THEN 2000
           WHEN d.task_description LIKE '%分數%' THEN 1000  -- 預設1000分
           -- 全能玩家：3種不同類型
           WHEN d.task_description LIKE '%三種不同類型%' OR d.task_description LIKE '%不同類型%' THEN 3
           -- 持久戰士：累積遊戲時間任務（根據描述中的分鐘數計算秒數）
           WHEN d.task_description LIKE '%5分鐘%' THEN 300
           WHEN d.task_description LIKE '%3分鐘%' THEN 180
           WHEN d.task_description LIKE '%10分鐘%' THEN 600
           WHEN d.task_description LIKE '%30分鐘%' THEN 1800
           WHEN d.task_description LIKE '%60分鐘%' THEN 3600
           WHEN d.task_description LIKE '%分鐘%' OR d.task_description LIKE '%遊玩時間%' THEN 300  -- 預設5分鐘
           -- 社交達人：5個好友
           WHEN d.task_description LIKE '%5個好友%' THEN 5
           WHEN d.task_description LIKE '%好友%' THEN 5  -- 預設5個
           -- 其他任務：1次完成
           ELSE 1
       END as required
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