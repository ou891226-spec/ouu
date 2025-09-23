<?php
require_once 'db.php';

echo "=== 檢查遊戲記錄問題 ===\n\n";

// 檢查所有用戶的遊戲記錄
$sql = "SELECT member_id, COUNT(*) as game_count, SUM(play_time) as total_time 
        FROM game_records 
        GROUP BY member_id 
        ORDER BY game_count DESC 
        LIMIT 10";
$stmt = $pdo->query($sql);
$results = $stmt->fetchAll();

echo "=== 前10名用戶遊戲記錄 ===\n";
foreach($results as $result) {
    echo "用戶ID: {$result['member_id']} | 遊戲次數: {$result['game_count']} | 總遊玩時間: {$result['total_time']}秒\n";
}

// 檢查特定用戶1411131021的詳細記錄
echo "\n=== 用戶1411131021詳細檢查 ===\n";
$user_id = 1411131021;

// 檢查game_records表
$user_sql = "SELECT * FROM game_records WHERE member_id = ?";
$user_stmt = $pdo->prepare($user_sql);
$user_stmt->execute([$user_id]);
$user_records = $user_stmt->fetchAll();

echo "game_records表中的記錄數量: " . count($user_records) . "\n";

// 檢查其他可能的遊戲記錄表
$tables = ['game_sessions', 'play_sessions', 'user_games', 'game_logs'];
foreach($tables as $table) {
    try {
        $check_sql = "SELECT COUNT(*) as count FROM $table WHERE member_id = ?";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute([$user_id]);
        $count = $check_stmt->fetch()['count'];
        echo "$table 表中的記錄數量: $count\n";
    } catch (Exception $e) {
        echo "$table 表不存在或查詢失敗\n";
    }
}

// 檢查所有表結構
echo "\n=== 檢查資料庫表結構 ===\n";
$tables_sql = "SHOW TABLES";
$tables_stmt = $pdo->query($tables_sql);
$all_tables = $tables_stmt->fetchAll(PDO::FETCH_COLUMN);

foreach($all_tables as $table) {
    if(strpos($table, 'game') !== false || strpos($table, 'play') !== false || strpos($table, 'record') !== false) {
        echo "找到相關表: $table\n";
    }
}

// 檢查成就觸發邏輯
echo "\n=== 檢查成就觸發邏輯 ===\n";
$achievement_sql = "SELECT * FROM achievements WHERE achievement_name = '全能玩家'";
$achievement_stmt = $pdo->query($achievement_sql);
$achievement = $achievement_stmt->fetch();

if($achievement) {
    echo "全能玩家成就ID: {$achievement['achievement_id']}\n";
    echo "成就名稱: {$achievement['achievement_name']}\n";
    echo "圖標: {$achievement['icon']}\n";
}
?>
