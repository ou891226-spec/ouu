<?php
require_once 'db.php';

// 檢查用戶ID 123的成就
$member_id = 123;

try {
    $sql = "SELECT a.achievement_name, a.achievement_description, a.icon, ma.earned_date
            FROM member_achievements ma
            JOIN achievements a ON ma.achievement_id = a.achievement_id
            WHERE ma.member_id = ?
            ORDER BY ma.earned_date DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$member_id]);
    $achievements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "用戶 ID $member_id 的成就：\n";
    echo "========================\n";
    
    if (count($achievements) > 0) {
        foreach($achievements as $achievement) {
            echo "成就名稱: " . $achievement['achievement_name'] . "\n";
            echo "圖示: " . $achievement['icon'] . "\n";
            echo "描述: " . $achievement['achievement_description'] . "\n";
            echo "獲得時間: " . $achievement['earned_date'] . "\n";
            echo "---\n";
        }
    } else {
        echo "該用戶尚未獲得任何成就\n";
    }
    
} catch (Exception $e) {
    echo "錯誤: " . $e->getMessage();
}
?> 