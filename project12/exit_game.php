<?php
/**
 * 遊戲退出API
 * 處理玩家主動退出遊戲
 */

session_start();
require_once 'db_connect.php';
require_once 'game_entry_tracker.php';

// 處理 AJAX 請求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');
    
    try {
        $record_id = $_POST['record_id'] ?? null;
        
        if (empty($record_id)) {
            // 嘗試從 session 獲取
            $record_id = $_SESSION['current_game_record_id'] ?? null;
        }
        
        if ($record_id) {
            // 標記為遊戲退出
            $result = markGameExit($record_id);
            
            if ($result) {
                // 清理 session
                unset($_SESSION['current_game_record_id']);
                unset($_SESSION['game_start_time']);
                unset($_SESSION['current_difficulty']);
                unset($_SESSION['current_game_type']);
                
                echo json_encode([
                    'success' => true,
                    'message' => '遊戲退出記錄成功'
                ]);
            } else {
                throw new Exception('標記遊戲退出失敗');
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
?>

