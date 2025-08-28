<?php
session_start();
require_once 'db_connect.php';

$member_id = $_SESSION['member_id'] ?? 8;
$game_id = $_POST['game_id'] ?? 0;
$score = $_POST['score'] ?? 0;
$play_time = $_POST['play_time'] ?? 0;
$difficulty = $_POST['difficulty'] ?? 'N/A';
$game_type = $_POST['game_type'] ?? '瀏覽時間';
$is_single_player = 0;

// 保存遊戲記錄
$sql = "INSERT INTO game_records 
  (member_id, game_id, difficulty, score, play_date, play_time, game_type, is_single_player, opponent_id)
  VALUES (?, ?, ?, ?, NOW(), ?, ?, ?, ?)";
$stmt = $pdo->prepare($sql);
$stmt->execute([$member_id, $game_id, $difficulty, $score, $play_time, $game_type, $is_single_player, null]);

// 更新會員總分數
$update_sql = "UPDATE member SET total_score = total_score + ? WHERE member_id = ?";
$update_stmt = $pdo->prepare($update_sql);
$update_stmt->execute([$score, $member_id]);

// 根據遊戲類型更新對應的分類分數
if ($game_type === '反應力' || $game_type === '節奏遊戲' || $game_type === '看字選色遊戲' || $game_type === '接金蛋遊戲') {
    $reaction_sql = "UPDATE member SET reaction_score = reaction_score + ? WHERE member_id = ?";
    $reaction_stmt = $pdo->prepare($reaction_sql);
    $reaction_stmt->execute([$score, $member_id]);
} elseif ($game_type === '記憶力' || $game_type === '翻牌對對樂' || $game_type === '追蹤犯人遊戲' || $game_type === '圖片線索問答') {
    $memory_sql = "UPDATE member SET memory_score = memory_score + ? WHERE member_id = ?";
    $memory_stmt = $pdo->prepare($memory_sql);
    $memory_stmt->execute([$score, $member_id]);
} elseif ($game_type === '算術邏輯' || $game_type === '2048' || $game_type === '算菜錢遊戲') {
    $logic_sql = "UPDATE member SET logic_score = logic_score + ? WHERE member_id = ?";
    $logic_stmt = $pdo->prepare($logic_sql);
    $logic_stmt->execute([$score, $member_id]);
}

// 檢查並授予成就
require_once 'check_and_grant_achievements.php';

// 根據遊戲類型映射到成就系統的遊戲類型
$achievement_game_type = null;
switch ($game_type) {
    case '記憶力':
    case '翻牌對對樂':
        $achievement_game_type = 'memory_game';
        break;
    case '節奏遊戲':
        $achievement_game_type = 'rhythm_game';
        break;
    case '2048':
        $achievement_game_type = 'game_2048';
        break;
    case '接金蛋遊戲':
        $achievement_game_type = 'catch_egg';
        break;
    case '追蹤犯人遊戲':
        $achievement_game_type = 'prisoner_game';
        break;
    case '看字選色遊戲':
        $achievement_game_type = 'text_color';
        break;
    case '算菜錢遊戲':
        $achievement_game_type = 'vegetable_cost';
        break;
}

// 檢查並授予成就
if ($achievement_game_type) {
    checkAndGrantAchievements($member_id, $achievement_game_type, $score, $play_time);
}

echo "✅ 分數已記錄並更新總分";
?>
