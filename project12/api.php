<?php
require_once 'db_connect2048.php';

header('Content-Type: application/json');

// 允許跨域請求
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// 處理 OPTIONS 請求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// 獲取請求方法
$method = $_SERVER['REQUEST_METHOD'];

// 根據請求方法處理
switch ($method) {
    case 'GET':
        // 如果是獲取分數，返回一個預設值
        echo json_encode([
            'success' => true,
            'score' => 0,
            'highScore' => 0
        ]);
        break;
        
    case 'POST':
        // 如果是提交分數，儲存到資料庫
        $data = json_decode(file_get_contents('php://input'), true);
        
        try {
            // 開始交易
            $pdo->beginTransaction();
            
            // 2048 遊戲的 game_id（假設為 7）
            $gameId = 7;
            
            // 插入遊戲記錄
            $stmt = $pdo->prepare("
                INSERT INTO game_records 
                (member_id, game_id, difficulty, score, play_date, play_time, game_type, is_single_player, opponent_id) 
                VALUES (:member_id, :game_id, :difficulty, :score, NOW(), :play_time, :game_type, :is_single_player, :opponent_id)
            ");
            
            $stmt->execute([
                'member_id' => $data['member_id'],
                'game_id' => $gameId,
                'difficulty' => $data['difficulty'],
                'score' => $data['score'],
                'play_time' => isset($data['play_time']) ? $data['play_time'] : null,
                'game_type' => '2048',
                'is_single_player' => 1,
                'opponent_id' => null
            ]);
            
            // 提交交易
            $pdo->commit();
            
            echo json_encode([
                'success' => true,
                'message' => '遊戲結果已儲存'
            ]);
        } catch (Exception $e) {
            // 如果發生錯誤，回滾交易
            $pdo->rollBack();
            echo json_encode([
                'success' => false,
                'message' => '儲存失敗：' . $e->getMessage()
            ]);
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Method not allowed'
        ]);
        break;
}
?> 