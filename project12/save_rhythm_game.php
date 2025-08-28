<?php
require_once 'db_connect.php';
 
header('Content-Type: application/json');
 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
   
    try {
        // 開始交易
        $pdo->beginTransaction();
       
        // 獲取節奏遊戲的game_id和game_type
        $game_sql = "SELECT game_id, game_type FROM games WHERE game_name = '節奏遊戲'";
        $game_stmt = $pdo->prepare($game_sql);
        $game_stmt->execute();
        $game = $game_stmt->fetch();
        $gameId = $game ? $game['game_id'] : 7; // 如果找不到，使用預設值
        $gameType = $game ? $game['game_type'] : '反應力'; // 從資料庫獲取正確的遊戲類型
        
        // 根據難度和是否過關設定獎勵分數
        $difficulty = $data['difficulty'];
        $is_passed = $data['is_passed'] ?? false; // 從前端接收是否過關
        $final_score = 0;
        
        if ($is_passed) {
            // 只有過關才給予獎勵分數
            if ($difficulty === 'easy') {
                $final_score = 20;
            } elseif ($difficulty === 'normal') {
                $final_score = 50;
            } elseif ($difficulty === 'hard') {
                $final_score = 100;
            }
        }
        // 如果沒過關，final_score 保持為 0
       
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
            'game_type' => $gameType, // 使用資料庫中的正確遊戲類型
            'is_single_player' => 1,
            'opponent_id' => null
        ]);
       
        // 更新會員總分數和反應力分數（使用前端計算好的分數）
        $update_stmt = $pdo->prepare("UPDATE member SET total_score = total_score + :score, reaction_score = reaction_score + :score WHERE member_id = :member_id");
        $update_stmt->execute([
            'score' => $final_score, // 使用前端計算好的分數
            'member_id' => $data['member_id']
        ]);
        
        // 檢查並完成所有相關任務
        require_once 'check_and_grant_achievements.php';
        checkAndCompleteAllTasks($data['member_id'], '節奏遊戲');
       
        // 提交交易
        $pdo->commit();
       
        echo json_encode(['success' => true, 'message' => '遊戲結果已儲存', 'task_completed' => !empty($completed_tasks)]);
    } catch (Exception $e) {
        // 如果發生錯誤，回滾交易
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => '儲存失敗：' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => '無效的請求方法']);
}
?>