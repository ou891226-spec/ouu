<?php
session_start();
require_once 'db.php';

// 獲取當前會員ID
$member_id = $_SESSION['member_id'] ?? 8;

// 獲取當前分數
$stmt = $pdo->prepare("SELECT total_score FROM member WHERE member_id = ?");
$stmt->execute([$member_id]);
$result = $stmt->fetch();

$current_score = $result ? $result['total_score'] : 0;

// 模擬增加20分
$new_score = $current_score + 20;

// 更新分數
$update_stmt = $pdo->prepare("UPDATE member SET total_score = ? WHERE member_id = ?");
$update_stmt->execute([$new_score, $member_id]);

echo "測試分數更新：<br>";
echo "會員ID: $member_id<br>";
echo "原分數: $current_score<br>";
echo "新分數: $new_score<br>";
echo "更新完成！<br>";
echo "<a href='index.php'>返回主頁</a>";
?> 