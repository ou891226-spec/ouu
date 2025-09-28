<?php
/**
 * 遊戲開始API
 * 處理遊戲開始時的進入追蹤
 */

session_start();
require_once 'db_connect.php';
require_once 'game_entry_tracker.php';

// 處理 AJAX 請求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');
    
    try {
        $member_id = $_POST['member_id'] ?? ($_SESSION['member_id'] ?? 1);
        $game_type = $_POST['game_type'] ?? '';
        $difficulty = $_POST['difficulty'] ?? 'easy';
        $game_id = $_POST['game_id'] ?? null;
        
        if (empty($game_type)) {
            throw new Exception('遊戲類型不能為空');
        }
        
        // 記錄遊戲進入
        $record_id = recordGameEntry($member_id, $game_type, $difficulty, $game_id);
        
        if ($record_id) {
            // 存儲在 session 中
            $_SESSION['current_game_record_id'] = $record_id;
            $_SESSION['game_start_time'] = time();
            $_SESSION['current_difficulty'] = $difficulty;
            $_SESSION['current_game_type'] = $game_type;
            
            echo json_encode([
                'success' => true,
                'record_id' => $record_id,
                'message' => '遊戲開始記錄成功'
            ]);
        } else {
            throw new Exception('記錄遊戲進入失敗');
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
?>

