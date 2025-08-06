<?php
require_once 'db.php';
 
header('Content-Type: application/json');
 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // 添加除錯資訊
    error_log('收到追蹤犯人遊戲數據: ' . json_encode($data));
   
    try {
        // 開始交易
        $pdo->beginTransaction();
       
        $gameId = 6;
       
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
            'game_type' => '追蹤犯人遊戲', // 修正遊戲類型
            'is_single_player' => 1,
            'opponent_id' => null
        ]);
       
        // 更新會員總分數和記憶力分數
        $update_stmt = $pdo->prepare("UPDATE member SET total_score = total_score + :score, memory_score = memory_score + :score WHERE member_id = :member_id");
        $update_stmt->execute([
            'score' => $data['score'],
            'member_id' => $data['member_id']
        ]);
        
        // 檢查並授予成就
        require_once 'check_and_grant_achievements.php';
        checkAndGrantAchievements($data['member_id'], 'prisoner_game', $data['score'], isset($data['play_time']) ? $data['play_time'] : 0);
       
        // 提交交易
        $pdo->commit();
        
        error_log('追蹤犯人遊戲數據保存成功，分數: ' . $data['score']);
       
        echo json_encode(['success' => true, 'message' => '遊戲結果已儲存']);
    } catch (Exception $e) {
        // 如果發生錯誤，回滾交易
        $pdo->rollBack();
        error_log('追蹤犯人遊戲保存失敗: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => '儲存失敗：' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => '無效的請求方法']);
}
?>