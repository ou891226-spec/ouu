<?php
include 'db.php';

$member_id = 23;

echo "<h1>檢查任務觸發問題</h1>";

// 檢查用戶當前的任務
$stmt = $pdo->prepare("
    SELECT mt.task_id, mt.completed_date, d.task_description
    FROM member_tasks mt
    JOIN daily_tasks d ON mt.task_id = d.task_id
    WHERE mt.member_id = ? AND mt.completed_date IS NULL
");
$stmt->execute([$member_id]);
$pending_tasks = $stmt->fetchAll();

echo "<h2>用戶的未完成任務：</h2>";
foreach ($pending_tasks as $task) {
    echo "<p>任務ID: {$task['task_id']} - 描述: {$task['task_description']}</p>";
}

// 測試任務匹配
echo "<h2>測試任務匹配：</h2>";
$test_descriptions = [
    '遊玩任一普通關卡一次',
    '完成任意一場遊戲對戰',
    '好友組隊遊戲一次'
];

foreach ($test_descriptions as $desc) {
    echo "<p>測試描述: '$desc'</p>";
    
    // 測試各種匹配模式
    $patterns = [
        '%遊玩任一普通關卡%',
        '%完成任意一場遊戲%',
        '%記憶力遊戲%'
    ];
    
    foreach ($patterns as $pattern) {
        if (strpos($desc, str_replace('%', '', $pattern)) !== false) {
            echo "<p style='color: green;'>✅ 匹配模式: $pattern</p>";
        } else {
            echo "<p style='color: red;'>❌ 不匹配模式: $pattern</p>";
        }
    }
    echo "<hr>";
}
?> 