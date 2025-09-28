<?php
/**
 * 標記遊戲退出API
 * 用於標記用戶退出遊戲（沒有後續動作）
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

if (!$record_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => '缺少記錄ID']);
    exit;
}

try {
    $result = markGameExit($record_id);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => '遊戲退出標記成功',
            'record_id' => $record_id
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => '標記遊戲退出失敗']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => '系統錯誤: ' . $e->getMessage()]);
}
?>

