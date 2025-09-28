<?php
require_once 'db_connect.php';
require_once 'game_entry_tracker.php';
 
header('Content-Type: application/json');
 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    error_log('收到追蹤犯人遊戲數據: ' . json_encode($data));
   
    try {
        $pdo->beginTransaction();
       
        $game_sql = "SELECT game_id, game_type FROM games WHERE game_name = '追蹤犯人遊戲'";
        $game_stmt = $pdo->prepare($game_sql);
        $game_stmt->execute();
        $game = $game_stmt->fetch();
        $gameId = $game ? $game['game_id'] : 6; 
        $gameType = $game ? $game['game_type'] : '記憶力';
       
        $difficulty = $data['difficulty'];
        $actual_score = $data['score'] ?? 0;
        $bonus_score = 0;

        if (($data['is_passed'] ?? false) === true) {
            if ($difficulty === 'easy') {
                $bonus_score = 20;
            } elseif ($difficulty === 'normal') {
                $bonus_score = 50;
            } elseif ($difficulty === 'hard') {
                $bonus_score = 100;
            }
        }
        
        // 使用新追蹤邏輯
        $record_id = recordGameEntry($data['member_id'], $gameType, $data['difficulty'], $gameId);
        if ($record_id) {
            $play_time = isset($data['play_time']) ? $data['play_time'] : 0;
            
            // 區分手動退出和遊戲失敗
            $isManualExit = isset($data['is_manual_exit']) && $data['is_manual_exit'] === true;
            if ($isManualExit) {
                // 手動退出遊戲
                $status = ($data['is_passed'] ?? false) ? 'completed' : 'exited';
            } else {
                // 正常遊戲結束
                $status = ($data['is_passed'] ?? false) ? 'completed' : 'failed';
            }
            
            updateGameRecord($record_id, $bonus_score, $play_time, $status);
        }
       
        $update_stmt = $pdo->prepare("UPDATE member SET total_score = total_score + :score WHERE member_id = :member_id");
        $update_stmt->execute([
            'score' => $bonus_score,
            'member_id' => $data['member_id']
        ]);
        
        require_once 'log_game_behavior.php';
        logGameBehavior(
            $data['member_id'], 
            $gameType, 
            isset($data['play_time']) ? $data['play_time'] : 0, 
            $actual_score, 
            $data['difficulty']
        );
        
        require_once 'check_and_grant_achievements.php';
        checkAndCompleteAllTasks($data['member_id'], '追蹤犯人遊戲');
        
        require_once 'check_and_grant_achievements.php';
        checkAndGrantAchievements($data['member_id'], 'prisoner_game', $actual_score, isset($data['play_time']) ? $data['play_time'] : 0);
       
        $pdo->commit();
        
        error_log('追蹤犯人遊戲數據保存成功，實際遊戲分數: ' . $actual_score . '，記錄的獎勵分數: ' . $bonus_score);
       
        echo json_encode(['success' => true, 'message' => '遊戲結果已儲存', 'task_completed' => !empty($completed_tasks)]);
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('追蹤犯人遊戲保存失敗: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => '儲存失敗：' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => '無效的請求方法']);
}
?>