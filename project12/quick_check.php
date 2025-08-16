<?php
include 'db.php';

$member_id = 23;

echo "檢查用戶 $member_id 的成就...\n";

$stmt = $pdo->prepare("SELECT ma.member_id, a.achievement_name, ma.earned_date 
                       FROM member_achievements ma 
                       JOIN achievements a ON ma.achievement_id = a.achievement_id 
                       WHERE ma.member_id = ? 
                       ORDER BY ma.earned_date DESC");
$stmt->execute([$member_id]);

$achievements = $stmt->fetchAll();

if (empty($achievements)) {
    echo "用戶 $member_id 目前沒有任何成就\n";
} else {
    echo "用戶 $member_id 的成就:\n";
    foreach ($achievements as $achievement) {
        echo "- " . $achievement['achievement_name'] . " (獲得時間: " . $achievement['earned_date'] . ")\n";
    }
}

echo "\n檢查最近的任務領取記錄...\n";
$stmt = $pdo->prepare("SELECT mt.task_id, dt.task_name, dt.reward_achievement, mt.claimed_date 
                       FROM member_tasks mt 
                       JOIN daily_tasks dt ON mt.task_id = dt.task_id 
                       WHERE mt.member_id = ? AND mt.claimed_date IS NOT NULL 
                       ORDER BY mt.claimed_date DESC 
                       LIMIT 5");
$stmt->execute([$member_id]);

$recent_claims = $stmt->fetchAll();

if (empty($recent_claims)) {
    echo "沒有找到最近的任務領取記錄\n";
} else {
    echo "最近的任務領取記錄:\n";
    foreach ($recent_claims as $claim) {
        echo "- 任務: " . $claim['task_name'] . " (獎勵成就: " . ($claim['reward_achievement'] ?: '無') . ") - 領取時間: " . $claim['claimed_date'] . "\n";
    }
}
?> 