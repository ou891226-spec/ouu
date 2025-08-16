<?php
require_once 'db.php';
session_start();

$member_id = $_SESSION['member_id'] ?? 0;

if ($member_id == 0) {
    echo "請先登入系統";
    exit;
}

echo "=== 您的每日任務 ===\n";
echo "用戶ID: " . $member_id . "\n\n";

// 獲取今天的任務
$today = date('Y-m-d');
$seed = strtotime($today);
mt_srand($seed);

// 獲取所有可用的任務
$sql = "SELECT d.task_id, d.task_name, d.task_description, d.task_type, d.reward_points,
       mt.status, mt.completed_date
FROM daily_tasks d
LEFT JOIN member_tasks mt ON d.task_id = mt.task_id AND mt.member_id = ? AND DATE(mt.completed_date) = ?
WHERE d.is_active = 1
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$member_id, $today]);
$all_tasks = $stmt->fetchAll();

// 隨機選擇3個任務
$selected_tasks = [];
$task_count = count($all_tasks);

if ($task_count > 0) {
    $indices = range(0, $task_count - 1);
    
    // 隨機打亂索引
    for ($i = 0; $i < $task_count; $i++) {
        $j = mt_rand($i, $task_count - 1);
        $temp = $indices[$i];
        $indices[$i] = $indices[$j];
        $indices[$j] = $temp;
    }
    
    // 選擇前3個
    $select_count = min(3, $task_count);
    for ($i = 0; $i < $select_count; $i++) {
        $selected_tasks[] = $all_tasks[$indices[$i]];
    }
}

echo "今天(" . $today . ")的每日任務：\n";
echo "================================\n";

foreach ($selected_tasks as $index => $task) {
    $status = $task['status'] ?? 'pending';
    $isCompleted = $status === 'completed' || $status === 'claimed';
    $isClaimed = $status === 'claimed';
    
    echo ($index + 1) . ". " . $task['task_name'] . "\n";
    echo "   描述: " . $task['task_description'] . "\n";
    echo "   類型: " . $task['task_type'] . "\n";
    echo "   獎勵: " . $task['reward_points'] . "分\n";
    
    if ($isClaimed) {
        echo "   狀態: ✅ 已領取獎勵\n";
    } elseif ($isCompleted) {
        echo "   狀態: ✅ 已完成 (可領取獎勵)\n";
    } else {
        echo "   狀態: ⏳ 進行中\n";
    }
    echo "\n";
}

echo "=== 任務完成記錄 ===\n";
$sql = "SELECT mt.task_id, mt.status, mt.completed_date, d.task_name 
        FROM member_tasks mt 
        JOIN daily_tasks d ON mt.task_id = d.task_id 
        WHERE mt.member_id = ? 
        ORDER BY mt.completed_date DESC 
        LIMIT 5";
$stmt = $pdo->prepare($sql);
$stmt->execute([$member_id]);
$recent_tasks = $stmt->fetchAll();

if ($recent_tasks) {
    echo "最近完成的任務：\n";
    foreach ($recent_tasks as $task) {
        echo "- " . $task['task_name'] . " (" . $task['completed_date'] . ")\n";
    }
} else {
    echo "您還沒有完成過任何任務\n";
}

echo "\n=== 任務統計 ===\n";
$sql = "SELECT COUNT(*) as total_completed FROM member_tasks WHERE member_id = ? AND status IN ('completed', 'claimed')";
$stmt = $pdo->prepare($sql);
$stmt->execute([$member_id]);
$total_completed = $stmt->fetch()['total_completed'];

echo "總共完成任務: " . $total_completed . "個\n";

// 檢查今天的完成情況
$sql = "SELECT COUNT(*) as today_completed FROM member_tasks WHERE member_id = ? AND DATE(completed_date) = ? AND status IN ('completed', 'claimed')";
$stmt = $pdo->prepare($sql);
$stmt->execute([$member_id, $today]);
$today_completed = $stmt->fetch()['today_completed'];

echo "今天完成任務: " . $today_completed . "/3個\n";
?> 