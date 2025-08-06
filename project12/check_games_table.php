<?php
require_once 'db.php';

try {
    $sql = "SELECT game_id, game_name, game_type FROM games ORDER BY game_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $games = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "遊戲表資料：\n";
    echo "==========\n";
    foreach($games as $game) {
        echo "ID: " . $game['game_id'] . " | 名稱: " . $game['game_name'] . " | 類型: " . $game['game_type'] . "\n";
    }
    
} catch (Exception $e) {
    echo "錯誤: " . $e->getMessage();
}
?> 