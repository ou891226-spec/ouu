<?php
require_once 'db_connect.php';

// 設置 JSON 響應頭
header('Content-Type: application/json; charset=utf-8');

// 添加調試日誌
error_log('get_high_score.php 被調用');

try {
    // 獲取 POST 數據
    $input = json_decode(file_get_contents('php://input'), true);
    error_log('收到的數據: ' . print_r($input, true));
    
    $game_id = $input['game_id'] ?? null;
    $member_id = $input['member_id'] ?? null;
    
    error_log("game_id: $game_id, member_id: $member_id");
    
    if (!$game_id || !$member_id) {
        error_log('缺少必要參數');
        echo json_encode([
            'success' => false,
            'message' => '缺少必要參數'
        ]);
        exit;
    }
    
    // 查詢該用戶在該遊戲中的最高分數
    $sql = "SELECT MAX(score) as high_score FROM game_records 
            WHERE member_id = ? AND game_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$member_id, $game_id]);
    $result = $stmt->fetch();
    
    $high_score = $result['high_score'] ?? 0;
    error_log("查詢到的最高分數: $high_score");
    
    echo json_encode([
        'success' => true,
        'high_score' => (int)$high_score
    ]);
    
} catch (Exception $e) {
    error_log('查詢失敗: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => '查詢失敗：' . $e->getMessage()
    ]);
}
?>
