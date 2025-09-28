<?php
/**
 * 更新遊戲記錄API
 * 用於更新用戶的遊戲記錄（從初始失敗記錄更新為實際遊戲結果）
 */

require_once 'db_connect.php';
require_once 'game_entry_tracker.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => '只允許POST請求']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => '無效的JSON數據']);
    exit;
}

$record_id = $data['record_id'] ?? null;
$score = $data['score'] ?? 0;
$play_time = $data['play_time'] ?? 0;
$status = $data['status'] ?? 'completed';

if (!$record_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => '缺少記錄ID']);
    exit;
}

try {
    $result = updateGameRecord($record_id, $score, $play_time, $status);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => '遊戲記錄更新成功',
            'record_id' => $record_id,
            'score' => $score,
            'play_time' => $play_time,
            'status' => $status
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => '更新遊戲記錄失敗']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => '系統錯誤: ' . $e->getMessage()]);
}
?>

