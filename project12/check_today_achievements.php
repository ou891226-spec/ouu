<?php
require_once 'db.php';

// 檢查用戶ID 8今天的成就狀態
$member_id = 8;

try {
    require_once 'check_and_grant_achievements.php';
    $today_status = getTodayAchievementStatus($member_id);
    
    echo "用戶 ID $member_id 今日成就狀態：\n";
    echo "==========================\n";
    echo "今日已獲得: " . $today_status['today_count'] . " 個成就\n";
    echo "還可獲得: " . $today_status['remaining'] . " 個成就\n";
    echo "是否可以獲得: " . ($today_status['can_earn'] ? '是' : '否') . "\n";
    
    // 檢查今日獲得的成就
    $today = date('Y-m-d');
    $sql = "SELECT a.achievement_name, a.icon, ma.earned_date
            FROM member_achievements ma
            JOIN achievements a ON ma.achievement_id = a.achievement_id
            WHERE ma.member_id = ? AND DATE(ma.earned_date) = ?
            ORDER BY ma.earned_date DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$member_id, $today]);
    $today_achievements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\n今日獲得的成就：\n";
    echo "==============\n";
    
    if (count($today_achievements) > 0) {
        foreach($today_achievements as $achievement) {
            echo "成就: " . $achievement['achievement_name'] . " (" . $achievement['icon'] . ")\n";
            echo "時間: " . $achievement['earned_date'] . "\n";
            echo "---\n";
        }
    } else {
        echo "今日尚未獲得成就\n";
    }
    
} catch (Exception $e) {
    echo "錯誤: " . $e->getMessage();
}
?> 