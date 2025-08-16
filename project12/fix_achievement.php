<?php
include 'db.php';

// 檢查記憶大師成就是否存在
$stmt = $pdo->prepare("SELECT achievement_id FROM achievements WHERE achievement_name = ?");
$stmt->execute(['記憶大師']);
$achievement = $stmt->fetch();

if (!$achievement) {
    // 創建成就
    $stmt = $pdo->prepare("INSERT INTO achievements (achievement_name, achievement_description) VALUES (?, ?)");
    $stmt->execute(['記憶大師', '完成記憶力遊戲相關任務']);
    $achievement_id = $pdo->lastInsertId();
    echo "創建了記憶大師成就，ID: $achievement_id\n";
} else {
    $achievement_id = $achievement['achievement_id'];
    echo "記憶大師成就已存在，ID: $achievement_id\n";
}

// 檢查用戶是否已有此成就
$member_id = 23;
$stmt = $pdo->prepare("SELECT COUNT(*) FROM member_achievements WHERE member_id = ? AND achievement_id = ?");
$stmt->execute([$member_id, $achievement_id]);
$has_achievement = $stmt->fetchColumn() > 0;

if (!$has_achievement) {
    // 添加成就給用戶
    $stmt = $pdo->prepare("INSERT INTO member_achievements (member_id, achievement_id, earned_date) VALUES (?, ?, NOW())");
    $stmt->execute([$member_id, $achievement_id]);
    echo "已為用戶 $member_id 添加記憶大師成就！\n";
} else {
    echo "用戶 $member_id 已經擁有記憶大師成就\n";
}

echo "完成！請重新整理頁面查看成就。\n";
?> 