<?php
require_once 'db.php';
session_start();

$member_id = $_SESSION['member_id'] ?? 0;

// 獲取今天的日期（用於任務隨機種子）
$today = date('Y-m-d');
$seed = strtotime($today);

// 使用今天的日期作為隨機種子，確保同一天顯示相同的任務
mt_srand($seed);

// 先獲取所有可用的任務，並計算進度
$sql = "
SELECT d.task_id, d.task_name, d.task_description, d.task_type, d.reward_points,
       mt.status,
       mt.completed_date,
       mt.claimed_date,
       CASE 
           WHEN mt.claimed_date IS NOT NULL THEN 'claimed'
           WHEN mt.completed_date IS NOT NULL THEN 'completed'
           -- 動態判斷累積型任務是否已達成
           WHEN (
               -- 分數效率：平均每局獲得50分以上
               (d.task_name = '分數效率' OR d.task_description LIKE '%平均每局獲得50分以上%') AND
               (SELECT COALESCE(AVG(score), 0) FROM game_records WHERE member_id = mt.member_id AND DATE(play_date) = CURDATE()) >= 50
           ) THEN 'completed'
           WHEN (
               -- 績分高手：總分達到50分
               (d.task_name = '績分高手' OR d.task_description LIKE '%總分達到50分%') AND
               (SELECT COALESCE(SUM(score), 0) FROM game_records WHERE member_id = mt.member_id AND DATE(play_date) = CURDATE()) >= 50
           ) THEN 'completed'
           WHEN (
               -- 進階者：總分達到500分
               (d.task_name = '進階者' OR d.task_description LIKE '%總分達到500分%') AND
               (SELECT COALESCE(SUM(score), 0) FROM game_records WHERE member_id = mt.member_id AND DATE(play_date) = CURDATE()) >= 500
           ) THEN 'completed'
           WHEN (
               -- 遊戲達人：完成10局遊戲
               (d.task_name = '遊戲達人' OR d.task_description LIKE '%完成10局%' OR d.task_description LIKE '%10局%') AND
               (SELECT COUNT(*) FROM game_records WHERE member_id = mt.member_id AND DATE(play_date) = CURDATE()) >= 10
           ) THEN 'completed'
           WHEN (
               -- 遊戲大師：完成25個關卡
               (d.task_name = '遊戲大師' OR d.task_description LIKE '%完成25個關卡%' OR d.task_description LIKE '%25個關卡%') AND
               (SELECT COUNT(*) FROM game_records WHERE member_id = mt.member_id AND DATE(play_date) = CURDATE()) >= 25
           ) THEN 'completed'
           WHEN (
               -- 遊戲傳奇：完成50個關卡
               (d.task_name = '遊戲傳奇' OR d.task_description LIKE '%完成50個關卡%' OR d.task_description LIKE '%50個關卡%') AND
               (SELECT COUNT(*) FROM game_records WHERE member_id = mt.member_id AND DATE(play_date) = CURDATE()) >= 50
           ) THEN 'completed'
           WHEN (
               -- 分數挑戰者：累積獲得200分以上
               (d.task_name = '分數挑戰者' OR d.task_description LIKE '%單局獲得200分以上%' OR d.task_description LIKE '%累積獲得200分以上%') AND
               (SELECT COALESCE(SUM(score), 0) FROM game_records WHERE member_id = mt.member_id AND DATE(play_date) = CURDATE()) >= 200
           ) THEN 'completed'
           WHEN (
               -- 遊戲狂熱者：一天內完成20局遊戲
               (d.task_name = '遊戲狂熱者' OR d.task_description LIKE '%一天內完成20局遊戲%') AND
               (SELECT COUNT(*) FROM game_records WHERE member_id = mt.member_id AND DATE(play_date) = CURDATE()) >= 20
           ) THEN 'completed'
           WHEN (
               -- 遊戲之神：完成100局遊戲
               (d.task_name = '遊戲之神' OR d.task_description LIKE '%完成100局遊戲%') AND
               (SELECT COUNT(*) FROM game_records WHERE member_id = mt.member_id AND DATE(play_date) = CURDATE()) >= 100
           ) THEN 'completed'
           ELSE 'pending'
       END as status,
       CASE 
           -- 分數效率：平均每局獲得50分以上
           WHEN d.task_name = '分數效率' OR d.task_description LIKE '%平均每局獲得50分以上%' THEN (
               SELECT COALESCE(AVG(score), 0) FROM game_records 
               WHERE member_id = mt.member_id 
               AND DATE(play_date) = CURDATE()
           )
           -- 績分高手：總分達到50分
           WHEN d.task_name = '績分高手' OR d.task_description LIKE '%總分達到50分%' THEN (
               SELECT COALESCE(SUM(score), 0) FROM game_records 
               WHERE member_id = mt.member_id 
               AND DATE(play_date) = CURDATE()
           )
           -- 進階者：總分達到500分
           WHEN d.task_name = '進階者' OR d.task_description LIKE '%總分達到500分%' THEN (
               SELECT COALESCE(SUM(score), 0) FROM game_records 
               WHERE member_id = mt.member_id 
               AND DATE(play_date) = CURDATE()
           )
           -- 遊戲達人：完成10局遊戲
           WHEN d.task_name = '遊戲達人' OR d.task_description LIKE '%完成10局%' OR d.task_description LIKE '%10局%' THEN (
               SELECT COUNT(*) FROM game_records 
               WHERE member_id = mt.member_id 
               AND DATE(play_date) = CURDATE()
           )
           -- 遊戲大師：完成25個關卡
           WHEN d.task_name = '遊戲大師' OR d.task_description LIKE '%完成25個關卡%' OR d.task_description LIKE '%25個關卡%' THEN (
               SELECT COUNT(*) FROM game_records 
               WHERE member_id = mt.member_id 
               AND DATE(play_date) = CURDATE()
           )
           -- 遊戲傳奇：完成50個關卡
           WHEN d.task_name = '遊戲傳奇' OR d.task_description LIKE '%完成50個關卡%' OR d.task_description LIKE '%50個關卡%' THEN (
               SELECT COUNT(*) FROM game_records 
               WHERE member_id = mt.member_id 
               AND DATE(play_date) = CURDATE()
           )
           -- 分數挑戰者：累積獲得200分以上
           WHEN d.task_name = '分數挑戰者' OR d.task_description LIKE '%單局獲得200分以上%' OR d.task_description LIKE '%累積獲得200分以上%' THEN (
               SELECT COALESCE(SUM(score), 0) FROM game_records 
               WHERE member_id = mt.member_id 
               AND DATE(play_date) = CURDATE()
           )
           -- 遊戲狂熱者：一天內完成20局遊戲
           WHEN d.task_name = '遊戲狂熱者' OR d.task_description LIKE '%一天內完成20局遊戲%' THEN (
               SELECT COUNT(*) FROM game_records 
               WHERE member_id = mt.member_id 
               AND DATE(play_date) = CURDATE()
           )
           -- 遊戲之神：完成100局遊戲
           WHEN d.task_name = '遊戲之神' OR d.task_description LIKE '%完成100局遊戲%' THEN (
               SELECT COUNT(*) FROM game_records 
               WHERE member_id = mt.member_id 
               AND DATE(play_date) = CURDATE()
           )
           ELSE 0
       END as progress,
       CASE 
           -- 分數效率：平均每局獲得50分以上
           WHEN d.task_name = '分數效率' OR d.task_description LIKE '%平均每局獲得50分以上%' THEN 50
           -- 績分高手：總分達到50分
           WHEN d.task_name = '績分高手' OR d.task_description LIKE '%總分達到50分%' THEN 50
           -- 進階者：總分達到500分
           WHEN d.task_name = '進階者' OR d.task_description LIKE '%總分達到500分%' THEN 500
           -- 遊戲達人：完成10局遊戲
           WHEN d.task_name = '遊戲達人' OR d.task_description LIKE '%完成10局%' OR d.task_description LIKE '%10局%' THEN 10
           -- 遊戲大師：完成25個關卡
           WHEN d.task_name = '遊戲大師' OR d.task_description LIKE '%完成25個關卡%' OR d.task_description LIKE '%25個關卡%' THEN 25
           -- 遊戲傳奇：完成50個關卡
           WHEN d.task_name = '遊戲傳奇' OR d.task_description LIKE '%完成50個關卡%' OR d.task_description LIKE '%50個關卡%' THEN 50
           -- 分數挑戰者：累積獲得200分以上
           WHEN d.task_name = '分數挑戰者' OR d.task_description LIKE '%單局獲得200分以上%' OR d.task_description LIKE '%累積獲得200分以上%' THEN 200
           -- 遊戲狂熱者：一天內完成20局遊戲
           WHEN d.task_name = '遊戲狂熱者' OR d.task_description LIKE '%一天內完成20局遊戲%' THEN 20
           -- 遊戲之神：完成100局遊戲
           WHEN d.task_name = '遊戲之神' OR d.task_description LIKE '%完成100局遊戲%' THEN 100
           ELSE 1
       END as required
FROM daily_tasks d
LEFT JOIN member_tasks mt ON d.task_id = mt.task_id AND mt.member_id = ?
WHERE d.is_active = 1
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$member_id]);
$all_tasks = $stmt->fetchAll();

// 隨機選擇3個任務，確保不重複
$selected_tasks = [];
$task_count = count($all_tasks);

if ($task_count > 0) {
    // 創建索引陣列
    $indices = range(0, $task_count - 1);
    
    // 隨機打亂索引
    for ($i = 0; $i < $task_count; $i++) {
        $j = mt_rand($i, $task_count - 1);
        $temp = $indices[$i];
        $indices[$i] = $indices[$j];
        $indices[$j] = $temp;
    }
    
    // 選擇前3個（確保不重複）
    $select_count = min(3, $task_count);
    for ($i = 0; $i < $select_count; $i++) {
        $selected_tasks[] = $all_tasks[$indices[$i]];
    }
    
    // 如果任務數量不足3個，則重複使用任務來湊足3個
    while (count($selected_tasks) < 3 && $task_count > 0) {
        // 再次隨機打亂索引
        for ($i = 0; $i < $task_count; $i++) {
            $j = mt_rand($i, $task_count - 1);
            $temp = $indices[$i];
            $indices[$i] = $indices[$j];
            $indices[$j] = $temp;
        }
        
        // 添加更多任務直到達到3個
        for ($i = 0; $i < $task_count && count($selected_tasks) < 3; $i++) {
            $selected_tasks[] = $all_tasks[$indices[$i]];
        }
    }
}

echo json_encode($selected_tasks);
?>
