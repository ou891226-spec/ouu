<?php
require_once 'db.php';

echo "=== 查找用戶1411131021 ===\n\n";

// 在member表中查找用戶
$find_sql = "SELECT * FROM member WHERE account = '1411131021'";
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
        $game_types = [];
        foreach($game_records as $record) {
            $game_types[] = $record['game_type'];
            echo "- 遊戲類型: {$record['game_type']} | 分數: {$record['score']} | 時間: {$record['play_time']}秒\n";
        }
        
        $unique_types = array_unique($game_types);
        echo "\n不同遊戲類型數量: " . count($unique_types) . "\n";
        echo "遊戲類型: " . implode(', ', $unique_types) . "\n";
        
        // 檢查是否滿足全能玩家條件（7種不同類型）
        if(count($unique_types) >= 7) {
            echo "✅ 用戶已滿足全能玩家條件（需要7種不同類型）\n";
        } else {
            echo "❌ 用戶尚未滿足全能玩家條件（需要7種不同類型）\n";
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
    $has_all_round = false;
    foreach($achievements as $achievement) {
        echo "- {$achievement['achievement_name']} (獲得於: {$achievement['earned_date']})\n";
        if($achievement['achievement_name'] == '全能玩家') {
            $has_all_round = true;
        }
    }
    
    if(!$has_all_round) {
        echo "\n❌ 用戶尚未獲得全能玩家成就\n";
        
        // 檢查是否應該獲得這個成就
        if(!empty($game_records)) {
            $unique_types = array_unique(array_column($game_records, 'game_type'));
            if(count($unique_types) >= 7) {
                echo "✅ 用戶應該獲得全能玩家成就，但尚未獲得\n";
                echo "需要手動觸發成就檢查\n";
            }
        }
    } else {
        echo "\n✅ 用戶已獲得全能玩家成就\n";
    }
    
} else {
    echo "❌ 找不到用戶1411131021\n";
    
    // 列出所有用戶
    echo "\n=== 所有用戶列表 ===\n";
    $all_sql = "SELECT member_id, account, member_name FROM member LIMIT 10";
    $all_stmt = $pdo->query($all_sql);
    $all_users = $all_stmt->fetchAll();
    
    foreach($all_users as $user) {
        echo "member_id: {$user['member_id']} | account: {$user['account']} | name: {$user['member_name']}\n";
    }
}
?>
