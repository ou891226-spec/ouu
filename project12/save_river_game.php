<?php
require_once 'db_connect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // 添加除錯資訊
    error_log('收到過河遊戲數據: ' . json_encode($data));
   
    try {
        // 開始交易
        $pdo->beginTransaction();
       
        $gameId = 9; // 過河遊戲的 game_id
        
        // 使用前端傳來的分數，前端已經根據是否過關計算好了
        $final_score = $data['score'];
        
        $stmt = $pdo->prepare("
            INSERT INTO game_records
            (member_id, game_id, difficulty, score, play_date, play_time, game_type, is_single_player, opponent_id)
            VALUES (:member_id, :game_id, :difficulty, :score, NOW(), :play_time, :game_type, :is_single_player, :opponent_id)
        ");
        $stmt->execute([
            'member_id' => $data['member_id'],
            'game_id' => $gameId,
            'difficulty' => $data['difficulty'],
            'score' => $final_score, // 使用前端計算好的分數
            'play_time' => isset($data['play_time']) ? $data['play_time'] : null,
            'game_type' => '過河遊戲',
            'is_single_player' => 1,
            'opponent_id' => null
        ]);
       
        // 更新會員總分數（使用前端計算好的分數）
        $update_stmt = $pdo->prepare("UPDATE member SET total_score = total_score + :score WHERE member_id = :member_id");
        $update_stmt->execute([
            'score' => $final_score, // 使用前端計算好的分數
            'member_id' => $data['member_id']
        ]);
        
        // 記錄遊戲行為軌跡
        require_once 'log_game_behavior.php';
        logGameBehavior(
            $data['member_id'], 
            '過河遊戲', 
            isset($data['play_time']) ? $data['play_time'] : 0, 
            $final_score, 
            $data['difficulty']
        );
        
        // 檢查並完成所有相關任務
        require_once 'check_and_grant_achievements.php';
        checkAndCompleteAllTasks($data['member_id'], '過河遊戲');
        
        // 檢查並授予成就
        require_once 'check_and_grant_achievements.php';
        checkAndGrantAchievements($data['member_id'], 'river_game', $data['score'], isset($data['play_time']) ? $data['play_time'] : 0);
       
        // 提交交易
        $pdo->commit();
        
        error_log('過河遊戲數據保存成功，分數: ' . $data['score']);
       
        echo json_encode(['success' => true, 'message' => '遊戲結果已儲存', 'task_completed' => !empty($completed_tasks)]);
    } catch (Exception $e) {
        // 如果發生錯誤，回滾交易
        $pdo->rollBack();
        error_log('過河遊戲保存失敗: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => '儲存失敗：' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => '無效的請求方法']);
}
?>
