<?php
session_start();
require_once 'db_connect.php';
require_once 'game_entry_tracker.php';

$member_id = $_SESSION['member_id'] ?? 8;
$game_id = $_POST['game_id'] ?? 0;
$score = $_POST['score'] ?? 0;
$play_time = $_POST['play_time'] ?? 0;
$difficulty = $_POST['difficulty'] ?? 'N/A';
$game_type = $_POST['game_type'] ?? '瀏覽時間';
$is_single_player = 0;
$record_id = $_POST['record_id'] ?? null; // 新增：支援更新模式

// 檢查是否為更新模式
if ($record_id && $record_id > 0) {
    // 更新現有記錄
    $update_result = updateGameRecord($record_id, $score, $play_time, $score > 0 ? 'completed' : 'failed');
    if (!$update_result) {
        error_log("更新遊戲記錄失敗: record_id={$record_id}");
    }
} else {
    // 傳統模式：直接插入新記錄
    $sql = "INSERT INTO game_records 
      (member_id, game_id, difficulty, score, play_date, play_time, game_type, is_single_player, opponent_id, status)
      VALUES (?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$member_id, $game_id, $difficulty, $score, $play_time, $game_type, $is_single_player, null, 'completed']);
}

// 只有在非更新模式下才更新分數（避免重複計算）
if (!$record_id || $record_id <= 0) {
    // 更新會員總分數
    $update_sql = "UPDATE member SET total_score = total_score + ? WHERE member_id = ?";
    $update_stmt = $pdo->prepare($update_sql);
    $update_stmt->execute([$score, $member_id]);

    // 根據遊戲類型更新對應的分類分數
    updateCategoryScore($member_id, $game_type, $score);
}

// 記錄遊戲行為軌跡
require_once 'log_game_behavior.php';
logGameBehavior($member_id, $game_type, $play_time, $score, $difficulty);

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
