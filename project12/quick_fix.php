<?php
include 'db.php';

// 直接添加記憶大師成就
$member_id = 23;

// 檢查成就是否存在，不存在則創建
$stmt = $pdo->prepare("SELECT achievement_id FROM achievements WHERE achievement_name = ?");
$stmt->execute(['記憶大師']);
$achievement = $stmt->fetch();

if (!$achievement) {
    $stmt = $pdo->prepare("INSERT INTO achievements (achievement_name, achievement_description, achievement_type, requirement_type, requirement_value) VALUES (?, ?, 'daily', 'task', 1)");
    $stmt->execute(['記憶大師', '完成記憶力遊戲相關任務']);
    $achievement_id = $pdo->lastInsertId();
} else {
    $achievement_id = $achievement['achievement_id'];
}

// 檢查用戶是否已有此成就
$stmt = $pdo->prepare("SELECT COUNT(*) FROM member_achievements WHERE member_id = ? AND achievement_id = ?");
$stmt->execute([$member_id, $achievement_id]);

if ($stmt->fetchColumn() == 0) {
    // 添加成就
    $stmt = $pdo->prepare("INSERT INTO member_achievements (member_id, achievement_id, earned_date) VALUES (?, ?, NOW())");
    $stmt->execute([$member_id, $achievement_id]);
    echo "已為用戶 $member_id 添加記憶大師成就！";
} else {
    echo "用戶 $member_id 已經擁有記憶大師成就";
}
?> 