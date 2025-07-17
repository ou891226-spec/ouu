<?php
session_start();
require 'db.php'; // 資料庫連線

$member_id = $_SESSION['user_id'] ?? 8;
$game_id = $_POST['game_id'] ?? 0;
$score = $_POST['score'] ?? 0;
$play_time = $_POST['play_time'] ?? 0;
$difficulty = $_POST['difficulty'] ?? 'N/A';
$game_type = $_POST['game_type'] ?? '瀏覽時間';
$is_single_player = 0;

$sql = "INSERT INTO game_records 
  (member_id, game_id, score, difficulty, play_date, play_time, game_type, is_single_player)
  VALUES (?, ?, ?, ?, NOW(), ?, ?, ?)";
$stmt = $pdo->prepare($sql);
$stmt->execute([$member_id, $game_id, $score, $difficulty, $play_time, $game_type, $is_single_player]);

echo "✅ 總時間已記錄";
