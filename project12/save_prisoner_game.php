<?php
require_once 'db_connect.php';
 
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
        
        $stmt = $pdo->prepare("
            INSERT INTO game_records
            (member_id, game_id, difficulty, score, play_date, play_time, game_type, is_single_player, opponent_id)
            VALUES (:member_id, :game_id, :difficulty, :score, NOW(), :play_time, :game_type, :is_single_player, :opponent_id)
        ");
        $stmt->execute([
            'member_id' => $data['member_id'],
            'game_id' => $gameId,
            'difficulty' => $data['difficulty'],
            'score' => $bonus_score,  // 修正：記錄標準獎勵分數而不是實際遊戲分數
            'play_time' => isset($data['play_time']) ? $data['play_time'] : null,
            'game_type' => $gameType,
            'is_single_player' => 1,
            'opponent_id' => null
        ]);
       
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