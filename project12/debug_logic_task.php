<?php
session_start();
require_once 'db_connect.php';

// 检查登录状态
if (!isset($_SESSION['member_id'])) {
    die('请先登录');
}

$member_id = $_SESSION['member_id'];

echo "<h2>邏輯專家任务诊断工具</h2>";
echo "<p>用户 ID: " . $member_id . "</p>";
echo "<hr>";

// 1. 检查今天的所有游戏记录
echo "<h3>1. 今天的所有游戏记录：</h3>";
$stmt = $pdo->prepare("
    SELECT record_id, game_id, game_type, difficulty, score, status, play_date, play_time
    FROM game_records 
    WHERE member_id = ? AND DATE(play_date) = CURDATE()
    ORDER BY play_date DESC
");
$stmt->execute([$member_id]);
$all_records = $stmt->fetchAll();

if (empty($all_records)) {
    echo "<p style='color:red;'>❌ 今天没有任何游戏记录！</p>";
} else {
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>记录ID</th><th>游戏ID</th><th>游戏类型</th><th>难度</th><th>分数</th><th>状态</th><th>时间</th></tr>";
    foreach ($all_records as $record) {
        echo "<tr>";
        echo "<td>" . $record['record_id'] . "</td>";
        echo "<td>" . $record['game_id'] . "</td>";
        echo "<td>" . htmlspecialchars($record['game_type']) . "</td>";
        echo "<td>" . htmlspecialchars($record['difficulty']) . "</td>";
        echo "<td>" . $record['score'] . "</td>";
        echo "<td>" . htmlspecialchars($record['status']) . "</td>";
        echo "<td>" . $record['play_date'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<hr>";

// 2. 检查逻辑游戏记录（符合任务条件的）
echo "<h3>2. 符合邏輯專家任务条件的记录：</h3>";
$stmt = $pdo->prepare("
    SELECT record_id, game_id, game_type, difficulty, score, status, play_date
    FROM game_records 
    WHERE member_id = ? 
    AND DATE(play_date) = CURDATE() 
    AND (game_type = '算術邏輯力' OR game_type = '邏輯力')
    ORDER BY play_date DESC
");
$stmt->execute([$member_id]);
$logic_records = $stmt->fetchAll();

if (empty($logic_records)) {
    echo "<p style='color:red;'>没有符合条件的逻辑游戏记录！</p>";
    echo "<p><strong>可能原因：</strong></p>";
    echo "<ul>";
    echo "<li>游戏类型不是算術邏輯力或邏輯力</li>";
    echo "<li>游戏记录没有保存成功</li>";
    echo "<li>日期不对（不是今天）</li>";
    echo "</ul>";
} else {
    echo "<p style='color:green;'>找到 " . count($logic_records) . " 条逻辑游戏记录</p>";
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>记录ID</th><th>游戏ID</th><th>游戏类型</th><th>难度</th><th>分数</th><th>状态</th><th>时间</th></tr>";
    foreach ($logic_records as $record) {
        echo "<tr>";
        echo "<td>" . $record['record_id'] . "</td>";
        echo "<td>" . $record['game_id'] . "</td>";
        echo "<td>" . htmlspecialchars($record['game_type']) . "</td>";
        echo "<td>" . htmlspecialchars($record['difficulty']) . "</td>";
        echo "<td>" . $record['score'] . "</td>";
        echo "<td>" . htmlspecialchars($record['status']) . "</td>";
        echo "<td>" . $record['play_date'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    if (count($logic_records) >= 3) {
        echo "<p style='color:green; font-weight:bold;'>已经完成了 " . count($logic_records) . " 局逻辑游戏，应该可以完成任务！</p>";
    } else {
        echo "<p style='color:orange; font-weight:bold;'>只完成了 " . count($logic_records) . " 局，还需要 " . (3 - count($logic_records)) . " 局</p>";
    }
}

echo "<hr>";

// 3. 检查任务状态
echo "<h3>3. 邏輯專家任务状态：</h3>";
$stmt = $pdo->prepare("
    SELECT 
        mt.task_id, 
        dt.task_name, 
        dt.task_description,
        mt.completed_date,
        mt.claimed_date,
        (SELECT COUNT(*) FROM game_records 
         WHERE member_id = mt.member_id 
         AND DATE(play_date) = CURDATE() 
         AND (game_type = '算術邏輯力' OR game_type = '邏輯力')) as logic_game_count
    FROM member_tasks mt
    JOIN daily_tasks dt ON mt.task_id = dt.task_id
    WHERE mt.member_id = ? 
    AND (dt.task_name = '邏輯專家' OR dt.task_description LIKE '%邏輯遊戲%')
    LIMIT 1
");
$stmt->execute([$member_id]);
$task = $stmt->fetch();

if (!$task) {
    echo "<p style='color:red;'>找不到邏輯專家任务！可能这不是你今天的任务之一。</p>";
} else {
    echo "<p><strong>任务名称：</strong>" . htmlspecialchars($task['task_name']) . "</p>";
    echo "<p><strong>任务描述：</strong>" . htmlspecialchars($task['task_description']) . "</p>";
    echo "<p><strong>逻辑游戏完成数：</strong>" . $task['logic_game_count'] . " / 3</p>";
    echo "<p><strong>完成时间：</strong>" . ($task['completed_date'] ? $task['completed_date'] : '未完成') . "</p>";
    echo "<p><strong>领取时间：</strong>" . ($task['claimed_date'] ? $task['claimed_date'] : '未领取') . "</p>";
    
    if ($task['logic_game_count'] >= 3 && !$task['completed_date']) {
        echo "<p style='color:orange; font-weight:bold;'>已完成3局，但任务状态未更新！可能需要刷新页面或重新登录。</p>";
    } elseif ($task['logic_game_count'] >= 3 && $task['completed_date']) {
        echo "<p style='color:green; font-weight:bold;'>任务已完成！</p>";
    } else {
        echo "<p style='color:blue;'>还需要完成 " . (3 - $task['logic_game_count']) . " 局逻辑游戏</p>";
    }
}

echo "<hr>";

// 4. 显示游戏类型映射信息
echo "<h3>4. 逻辑游戏类型映射：</h3>";
echo "<p>以下游戏算作逻辑游戏（game_type需要是这些值之一）：</p>";
echo "<ul>";
echo "<li>game_type = 算術邏輯力 （包括：2048、算菜钱、数字排排乐）</li>";
echo "<li>game_type = 邏輯力 （2048的另一种记录方式）</li>";
echo "</ul>";

echo "<hr>";
echo "<p><a href='mission.php'>返回任务页面</a></p>";
?>
