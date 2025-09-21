<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';

try {
    echo "正在檢查並修正初學者成就...\n";
    
    // 1. 查詢所有包含"初學者"的成就
    $sql = "SELECT * FROM achievements WHERE achievement_name LIKE '%初學者%'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $achievements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($achievements)) {
        echo "找到包含'初學者'的成就：\n";
        foreach ($achievements as $achievement) {
            echo "ID: {$achievement['achievement_id']}, 名稱: {$achievement['achievement_name']}\n";
            
            // 更新成就名稱從"初學者"改為"遊戲新手"
            $new_name = str_replace('初學者', '遊戲新手', $achievement['achievement_name']);
            $update_sql = "UPDATE achievements SET achievement_name = ? WHERE achievement_id = ?";
            $update_stmt = $pdo->prepare($update_sql);
            $update_stmt->execute([$new_name, $achievement['achievement_id']]);
            
            echo "已更新成就 ID {$achievement['achievement_id']} 的名稱從 '{$achievement['achievement_name']}' 改為 '{$new_name}'\n";
        }
    } else {
        echo "未找到包含'初學者'的成就\n";
    }
    
    // 2. 檢查用戶23的成就情況
    echo "\n檢查用戶23的成就：\n";
    $user_sql = "
        SELECT ma.*, a.achievement_name, a.achievement_description 
        FROM member_achievements ma 
        JOIN achievements a ON ma.achievement_id = a.achievement_id 
        WHERE ma.member_id = 23
        ORDER BY ma.earned_date DESC
        LIMIT 10
    ";
    $user_stmt = $pdo->prepare($user_sql);
    $user_stmt->execute();
    $user_achievements = $user_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($user_achievements)) {
        foreach ($user_achievements as $ua) {
            echo "成就: {$ua['achievement_name']} (獲得時間: {$ua['earned_date']})\n";
        }
    } else {
        echo "用戶23沒有任何成就\n";
    }
    
    echo "\n修正完成！\n";
    
} catch (Exception $e) {
    echo "錯誤: " . $e->getMessage() . "\n";
}
?>


