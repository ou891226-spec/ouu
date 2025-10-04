<?php
require_once 'db.php';

echo "Testing get_daily_tasks_fixed.php directly...\n\n";

// 模擬一個會話
session_start();
$_SESSION['member_id'] = 1; // 使用測試用戶ID

// 直接包含並執行 get_daily_tasks_fixed.php
ob_start();
include 'get_daily_tasks_fixed.php';
$output = ob_get_clean();

echo "Raw output from get_daily_tasks_fixed.php:\n";
echo $output . "\n\n";

// 解析JSON輸出
$tasks = json_decode($output, true);

if ($tasks && is_array($tasks)) {
    echo "Parsed tasks:\n";
    foreach ($tasks as $task) {
        if ($task['task_name'] === '遊戲之神') {
            echo "Found 遊戲之神 task:\n";
            echo "  Task ID: " . $task['task_id'] . "\n";
            echo "  Task Name: " . $task['task_name'] . "\n";
            echo "  Task Description: " . $task['task_description'] . "\n";
            echo "  Progress: " . $task['progress'] . "\n";
            echo "  Required: " . $task['required'] . "\n";
            echo "  Status: " . $task['status'] . "\n";
            echo "  Progress Display: " . $task['progress'] . "/" . $task['required'] . "\n";
            break;
        }
    }
} else {
    echo "No tasks found or invalid JSON output.\n";
    echo "Error: " . json_last_error_msg() . "\n";
}
?>


