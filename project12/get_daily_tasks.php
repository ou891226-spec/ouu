<?php
require_once 'db.php';
session_start();

$member_id = $_SESSION['member_id'] ?? 0;

// 獲取今天的日期（用於任務隨機種子）
$today = date('Y-m-d');
$seed = strtotime($today);

// 使用今天的日期作為隨機種子，確保同一天顯示相同的任務
mt_srand($seed);

// 先獲取所有可用的任務
$sql = "
SELECT d.task_id, d.task_name, d.task_description, d.task_type, d.reward_points,
       mt.status,
       mt.completed_date
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
