<?php
require_once 'db.php';

echo "=== 檢查遊玩時間記錄 ===\n\n";

$user_id = 1411131021;

// 檢查daily_playtime_records表
echo "=== daily_playtime_records表 ===\n";
$sql = "SELECT * FROM daily_playtime_records WHERE member_id = ? ORDER BY play_date DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$playtime_records = $stmt->fetchAll();

if(empty($playtime_records)) {
    echo "❌ 用戶 $user_id 在daily_playtime_records表中沒有記錄\n";
} else {
    echo "✅ 找到用戶 $user_id 的遊玩時間記錄:\n";
    foreach($playtime_records as $record) {
        echo "日期: {$record['play_date']} | 遊玩時間: {$record['play_time']}秒\n";
    }
}

// 檢查所有用戶的遊玩時間記錄
echo "\n=== 所有用戶遊玩時間統計 ===\n";
$all_sql = "SELECT member_id, SUM(play_time) as total_time, COUNT(*) as days 
            FROM daily_playtime_records 
            GROUP BY member_id 
            ORDER BY total_time DESC 
            LIMIT 10";
$all_stmt = $pdo->query($all_sql);
$all_results = $all_stmt->fetchAll();

foreach($all_results as $result) {
    $hours = floor($result['total_time'] / 3600);
    $minutes = floor(($result['total_time'] % 3600) / 60);
    $seconds = $result['total_time'] % 60;
    echo "用戶ID: {$result['member_id']} | 總時間: {$hours}:{$minutes}:{$seconds} | 天數: {$result['days']}\n";
}

// 檢查用戶是否在其他表中
echo "\n=== 檢查用戶是否存在於其他表 ===\n";
$tables = ['members', 'users', 'accounts'];
foreach($tables as $table) {
    try {
        $check_sql = "SELECT COUNT(*) as count FROM $table WHERE member_id = ? OR id = ?";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute([$user_id, $user_id]);
        $count = $check_stmt->fetch()['count'];
        echo "$table 表中用戶記錄數量: $count\n";
    } catch (Exception $e) {
        echo "$table 表查詢失敗: " . $e->getMessage() . "\n";
    }
}

// 檢查member_id的格式
echo "\n=== 檢查member_id格式 ===\n";
$format_sql = "SELECT DISTINCT member_id FROM daily_playtime_records ORDER BY member_id LIMIT 10";
$format_stmt = $pdo->query($format_sql);
$format_results = $format_stmt->fetchAll();

echo "daily_playtime_records表中的member_id格式:\n";
foreach($format_results as $result) {
    echo "- {$result['member_id']} (類型: " . gettype($result['member_id']) . ")\n";
}
?>
