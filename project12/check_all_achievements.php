<?php
require_once 'db.php';

try {
    // 檢查所有用戶的成就
    $sql = "SELECT ma.member_id, a.achievement_name, a.icon, ma.earned_date
            FROM member_achievements ma
            JOIN achievements a ON ma.achievement_id = a.achievement_id
            ORDER BY ma.earned_date DESC
            LIMIT 20";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $achievements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "所有用戶的成就記錄：\n";
    echo "==================\n";
    
    if (count($achievements) > 0) {
        foreach($achievements as $achievement) {
            echo "用戶ID: " . $achievement['member_id'] . "\n";
            echo "成就名稱: " . $achievement['achievement_name'] . "\n";
            echo "圖示: " . $achievement['icon'] . "\n";
            echo "獲得時間: " . $achievement['earned_date'] . "\n";
            echo "---\n";
        }
    } else {
        echo "目前沒有任何用戶獲得成就\n";
    }
    
    // 檢查成就表是否有資料
    echo "\n成就表檢查：\n";
    echo "==========\n";
    
    $check_sql = "SELECT COUNT(*) as count FROM achievements";
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->execute();
    $count = $check_stmt->fetch()['count'];
    echo "成就表中有 $count 個成就定義\n";
    
    $check_sql2 = "SELECT COUNT(*) as count FROM member_achievements";
    $check_stmt2 = $pdo->prepare($check_sql2);
    $check_stmt2->execute();
    $count2 = $check_stmt2->fetch()['count'];
    echo "用戶成就表中有 $count2 個成就記錄\n";
    
} catch (Exception $e) {
    echo "錯誤: " . $e->getMessage();
}
?> 