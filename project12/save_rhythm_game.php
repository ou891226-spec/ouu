<?php
require_once 'rhythm_game_db.php';
 
header('Content-Type: application/json');
 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
   
    try {
        // 開始交易
        $pdo->beginTransaction();
       
        $gameId = 7;
       
        $stmt = $pdo->prepare("
            INSERT INTO game_records
            (member_id, game_id, difficulty, score, play_date, play_time, game_type, is_single_player, opponent_id)
            VALUES (:member_id, :game_id, :difficulty, :score, NOW(), :play_time, :game_type, :is_single_player, :opponent_id)
        ");
        $stmt->execute([
            'member_id' => $data['member_id'],
            'game_id' => $gameId,
            'difficulty' => $data['difficulty'],
            'score' => $data['score'],
            'play_time' => isset($data['play_time']) ? $data['play_time'] : null,
            'game_type' => '反應力', // 或 'Catch Egg'
            'is_single_player' => 1,
            'opponent_id' => null
        ]);
       
        // 提交交易
        $pdo->commit();
       
        echo json_encode(['success' => true, 'message' => '遊戲結果已儲存']);
    } catch (Exception $e) {
        // 如果發生錯誤，回滾交易
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => '儲存失敗：' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => '無效的請求方法']);
}
?>