<?php
require_once 'db.php';

echo "=== 查找用戶1411131021 ===\n\n";

// 檢查members表結構
echo "=== members表結構 ===\n";
$structure_sql = "DESCRIBE members";
$structure_stmt = $pdo->query($structure_sql);
$structure = $structure_stmt->fetchAll();

foreach($structure as $column) {
    echo "欄位: {$column['Field']} | 類型: {$column['Type']} | 允許NULL: {$column['Null']}\n";
}

// 查找用戶1411131021
echo "\n=== 查找用戶記錄 ===\n";
$find_sql = "SELECT * FROM members WHERE member_id = '1411131021' OR username = '1411131021' OR account = '1411131021'";
$find_stmt = $pdo->query($find_sql);
$user = $find_stmt->fetch();

if($user) {
    echo "✅ 找到用戶記錄:\n";
    foreach($user as $key => $value) {
        echo "$key: $value\n";
    }
    
    $actual_member_id = $user['member_id'];
    echo "\n實際的member_id: $actual_member_id\n";
    
    // 檢查這個用戶的遊戲記錄
    echo "\n=== 檢查遊戲記錄 ===\n";
    $game_sql = "SELECT * FROM game_records WHERE member_id = ?";
    $game_stmt = $pdo->prepare($game_sql);
    $game_stmt->execute([$actual_member_id]);
    $game_records = $game_stmt->fetchAll();
    
    echo "game_records記錄數量: " . count($game_records) . "\n";
    
    if(!empty($game_records)) {
        echo "遊戲記錄:\n";
        foreach($game_records as $record) {
            echo "- 遊戲類型: {$record['game_type']} | 分數: {$record['score']} | 時間: {$record['play_time']}秒\n";
        }
    }
    
    // 檢查遊玩時間記錄
    echo "\n=== 檢查遊玩時間記錄 ===\n";
    $time_sql = "SELECT * FROM daily_playtime_records WHERE member_id = ?";
    $time_stmt = $pdo->prepare($time_sql);
    $time_stmt->execute([$actual_member_id]);
    $time_records = $time_stmt->fetchAll();
    
    echo "daily_playtime_records記錄數量: " . count($time_records) . "\n";
    
    if(!empty($time_records)) {
        $total_time = 0;
        foreach($time_records as $record) {
            $total_time += $record['play_time'];
            echo "- 日期: {$record['play_date']} | 時間: {$record['play_time']}秒\n";
        }
        $hours = floor($total_time / 3600);
        $minutes = floor(($total_time % 3600) / 60);
        $seconds = $total_time % 60;
        echo "總遊玩時間: {$hours}:{$minutes}:{$seconds}\n";
    }
    
    // 檢查成就
    echo "\n=== 檢查成就 ===\n";
    $achievement_sql = "SELECT ma.*, a.achievement_name, a.icon 
                        FROM member_achievements ma 
                        JOIN achievements a ON ma.achievement_id = a.achievement_id 
                        WHERE ma.member_id = ?";
    $achievement_stmt = $pdo->prepare($achievement_sql);
    $achievement_stmt->execute([$actual_member_id]);
    $achievements = $achievement_stmt->fetchAll();
    
    echo "成就數量: " . count($achievements) . "\n";
    foreach($achievements as $achievement) {
        echo "- {$achievement['achievement_name']} (獲得於: {$achievement['earned_date']})\n";
    }
    
} else {
    echo "❌ 找不到用戶1411131021\n";
    
    // 列出所有用戶
    echo "\n=== 所有用戶列表 ===\n";
    $all_sql = "SELECT member_id, username, account FROM members LIMIT 10";
    $all_stmt = $pdo->query($all_sql);
    $all_users = $all_stmt->fetchAll();
    
    foreach($all_users as $user) {
        echo "member_id: {$user['member_id']} | username: {$user['username']} | account: {$user['account']}\n";
    }
}
?>
