<?php
require_once 'db_connect.php';
require_once 'game_entry_tracker.php';
 
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
       
        // 使用新追蹤邏輯
        $record_id = recordGameEntry($data['member_id'], $gameType, $data['difficulty'], $gameId);
        if ($record_id) {
            $play_time = isset($data['play_time']) ? $data['play_time'] : 0;
            
            // 區分手動退出和遊戲失敗
            $isManualExit = isset($data['is_manual_exit']) && $data['is_manual_exit'] === true;
            if ($isManualExit) {
                // 手動退出遊戲
                $status = ($final_score > 0) ? 'completed' : 'exited';
            } else {
                // 正常遊戲結束（時間到或達到目標）
                $status = ($final_score > 0) ? 'completed' : 'failed';
            }
            
            updateGameRecord($record_id, $final_score, $play_time, $status);
        }
       
        // 更新會員總分數和反應力分數（使用前端計算好的分數）
        $update_stmt = $pdo->prepare("UPDATE member SET total_score = total_score + :score, reaction_score = reaction_score + :score WHERE member_id = :member_id");
        $update_stmt->execute([
            'score' => $final_score, // 使用前端計算好的分數
            'member_id' => $data['member_id']
        ]);
        
        // 記錄遊戲行為軌跡
        require_once 'log_game_behavior.php';
        logGameBehavior(
            $data['member_id'], 
            '反應力', 
            isset($data['play_time']) ? $data['play_time'] : 0, 
            $final_score, 
            $data['difficulty']
        );
        
        // 檢查並完成所有相關任務
        require_once 'check_and_grant_achievements.php';
        checkAndGrantAchievements($data['member_id'], 'rhythm_game', $data['score'], isset($data['play_time']) ? $data['play_time'] : 0);
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