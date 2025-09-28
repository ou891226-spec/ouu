<?php
/**
 * 遊戲結束API
 * 處理遊戲結束時的記錄更新
 */

session_start();
require_once 'db_connect.php';
require_once 'game_entry_tracker.php';

// 處理 AJAX 請求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');
    
    try {
        $score = intval($_POST['score'] ?? 0);
        $play_time = intval($_POST['play_time'] ?? 0);
        $member_id = $_POST['member_id'] ?? ($_SESSION['member_id'] ?? 1);
        $game_type = $_POST['game_type'] ?? ($_SESSION['current_game_type'] ?? '');
        $difficulty = $_POST['difficulty'] ?? ($_SESSION['current_difficulty'] ?? 'easy');
        
        if (empty($game_type)) {
            throw new Exception('遊戲類型不能為空');
        }
        
        // 根據分數門檻判斷成功/失敗
        $pass_score = getPassScore($difficulty);
        $status = ($score >= $pass_score) ? 'completed' : 'failed';
        
        // 更新遊戲記錄
        if (isset($_SESSION['current_game_record_id'])) {
            $result = updateGameRecord($_SESSION['current_game_record_id'], $score, $play_time, $status);
            
            if ($result) {
                // 清理 session
                unset($_SESSION['current_game_record_id']);
                unset($_SESSION['game_start_time']);
                unset($_SESSION['current_difficulty']);
                unset($_SESSION['current_game_type']);
                
                echo json_encode([
                    'success' => true,
                    'status' => $status,
                    'pass_score' => $pass_score,
                    'message' => '遊戲結束記錄成功'
                ]);
            } else {
                throw new Exception('更新遊戲記錄失敗');
            }
        } else {
            throw new Exception('找不到遊戲記錄ID');
        }
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => '無效的請求'
    ]);
}

/**
 * 獲取分數門檻
 */
function getPassScore($difficulty) {
    switch ($difficulty) {
        case 'easy': return 20;
        case 'normal': return 50;
        case 'hard': return 100;
        default: return 20;
    }
}
?>

