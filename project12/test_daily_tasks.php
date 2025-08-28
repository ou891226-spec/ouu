<?php
session_start();
require_once 'db.php';

echo "<h2>每日任务测试</h2>";

// 检查Session
echo "<h3>Session状态：</h3>";
echo "<p>member_id: " . (isset($_SESSION['member_id']) ? $_SESSION['member_id'] : '未设置') . "</p>";
echo "<p>name: " . (isset($_SESSION['name']) ? $_SESSION['name'] : '未设置') . "</p>";

// 检查数据库连接
try {
    echo "<h3>数据库连接测试：</h3>";
    $test_sql = "SELECT COUNT(*) as count FROM member_tasks";
    $stmt = $pdo->prepare($test_sql);
    $stmt->execute();
    $result = $stmt->fetch();
    echo "<p>member_tasks表记录数: " . $result['count'] . "</p>";
    
    // 检查每日任务表
    $daily_sql = "SELECT COUNT(*) as count FROM daily_tasks WHERE is_active = 1";
    $stmt = $pdo->prepare($daily_sql);
    $stmt->execute();
    $result = $stmt->fetch();
    echo "<p>active daily_tasks记录数: " . $result['count'] . "</p>";
    
    // 如果有用户登录，检查用户任务
    if (isset($_SESSION['member_id'])) {
        echo "<h3>用户任务检查：</h3>";
        $user_sql = "SELECT COUNT(*) as count FROM member_tasks WHERE member_id = ?";
        $stmt = $pdo->prepare($user_sql);
        $stmt->execute([$_SESSION['member_id']]);
        $result = $stmt->fetch();
        echo "<p>用户任务记录数: " . $result['count'] . "</p>";
        
        // 检查具体的任务数据
        $tasks_sql = "
        SELECT d.task_id, d.task_name, d.task_description, d.task_type, d.reward_points,
               mt.completed_date, mt.claimed_date,
               CASE 
                   WHEN mt.claimed_date IS NOT NULL THEN 'claimed'
                   WHEN mt.completed_date IS NOT NULL THEN 'completed'
                   ELSE 'pending'
               END as status
        FROM member_tasks mt
        JOIN daily_tasks d ON mt.task_id = d.task_id
        WHERE mt.member_id = ? AND d.is_active = 1
        ORDER BY mt.task_id
        LIMIT 5
        ";
        $stmt = $pdo->prepare($tasks_sql);
        $stmt->execute([$_SESSION['member_id']]);
        $tasks = $stmt->fetchAll();
        
        echo "<h4>用户任务详情：</h4>";
        if ($tasks) {
            echo "<table border='1' style='border-collapse: collapse;'>";
            echo "<tr><th>任务ID</th><th>任务名称</th><th>状态</th><th>完成日期</th><th>领取日期</th></tr>";
            foreach ($tasks as $task) {
                echo "<tr>";
                echo "<td>" . $task['task_id'] . "</td>";
                echo "<td>" . $task['task_name'] . "</td>";
                echo "<td>" . $task['status'] . "</td>";
                echo "<td>" . ($task['completed_date'] ?: '未完成') . "</td>";
                echo "<td>" . ($task['claimed_date'] ?: '未领取') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p>没有找到用户任务</p>";
        }
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>数据库错误: " . $e->getMessage() . "</p>";
}

// 测试API调用
echo "<h3>API测试：</h3>";
echo "<p><a href='get_daily_tasks_fixed.php' target='_blank'>直接访问API</a></p>";

// 检查localStorage
echo "<h3>前端状态检查：</h3>";
echo "<script>";
echo "console.log('localStorage检查:');";
echo "console.log('missionLoadDate:', localStorage.getItem('missionLoadDate'));";
echo "console.log('missionLoadedToday:', localStorage.getItem('missionLoadedToday'));";
echo "console.log('missionShownToday:', localStorage.getItem('missionShownToday'));";
echo "console.log('autoShowMission:', localStorage.getItem('autoShowMission'));";
echo "</script>";
?>
