<?php
require_once 'db.php';
session_start();

echo "=== 檢查用戶全能玩家成就 ===\n\n";

// 假設用戶ID為1411131021（從圖片中看到的）
$member_id = 1411131021;

// 檢查用戶是否已獲得全能玩家成就
$sql = "SELECT ma.*, a.achievement_name, a.icon 
        FROM member_achievements ma 
        JOIN achievements a ON ma.achievement_id = a.achievement_id 
        WHERE ma.member_id = ? AND a.achievement_name = '全能玩家'";
$stmt = $pdo->prepare($sql);
$stmt->execute([$member_id]);
$result = $stmt->fetchAll();

if(empty($result)) {
    echo "❌ 用戶 $member_id 尚未獲得全能玩家成就\n\n";
    
    // 檢查用戶的遊戲記錄
    echo "=== 用戶遊戲記錄統計 ===\n";
    $game_sql = "SELECT game_type, COUNT(*) as count FROM game_records WHERE member_id = ? GROUP BY game_type";
    $game_stmt = $pdo->prepare($game_sql);
    $game_stmt->execute([$member_id]);
    $game_records = $game_stmt->fetchAll();
    
    echo "遊戲類型統計:\n";
    foreach($game_records as $record) {
        echo "- {$record['game_type']}: {$record['count']} 局\n";
    }
    
    // 檢查不同遊戲類型數量
    $type_sql = "SELECT COUNT(DISTINCT game_type) as game_types FROM game_records WHERE member_id = ?";
    $type_stmt = $pdo->prepare($type_sql);
    $type_stmt->execute([$member_id]);
    $type_count = $type_stmt->fetch()['game_types'];
    
    echo "\n總共遊戲類型數量: $type_count\n";
    echo "需要至少 7 種不同類型才能獲得全能玩家成就\n";
    
    if($type_count >= 7) {
        echo "✅ 用戶已滿足條件，應該可以獲得全能玩家成就\n";
    } else {
        echo "❌ 用戶尚未滿足條件\n";
    }
    
} else {
    echo "✅ 用戶 $member_id 已獲得全能玩家成就:\n";
    foreach($result as $achievement) {
        echo "獲得日期: {$achievement['earned_date']} | 成就: {$achievement['achievement_name']} | 圖標: {$achievement['icon']}\n";
    }
}

// 檢查用戶所有成就
echo "\n=== 用戶所有成就 ===\n";
$all_sql = "SELECT ma.*, a.achievement_name, a.icon 
            FROM member_achievements ma 
            JOIN achievements a ON ma.achievement_id = a.achievement_id 
            WHERE ma.member_id = ? 
            ORDER BY ma.earned_date DESC";
$all_stmt = $pdo->prepare($all_sql);
$all_stmt->execute([$member_id]);
$all_achievements = $all_stmt->fetchAll();

if(empty($all_achievements)) {
    echo "❌ 用戶沒有任何成就\n";
} else {
    echo "用戶成就列表:\n";
    foreach($all_achievements as $achievement) {
        echo "- {$achievement['achievement_name']} (獲得於: {$achievement['earned_date']})\n";
    }
}
?>
