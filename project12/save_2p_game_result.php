<?php
// 設置響應頭為JSON
header('Content-Type: application/json');

// 禁用錯誤輸出到響應
ini_set('display_errors', 0);
error_reporting(0);

// 清理輸出緩衝區（如果存在）
if (ob_get_level()) {
    ob_clean();
}

try {
    // 檢查請求方法
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('只允許POST請求');
    }
    
    // 獲取JSON數據
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        throw new Exception('無效的JSON數據');
    }
    
    // 驗證必要字段
    $required_fields = ['player1_id', 'player2_id', 'player1_name', 'player2_name', 'player1_score', 'player2_score'];
    foreach ($required_fields as $field) {
        if (!isset($data[$field])) {
            throw new Exception("缺少必要字段: $field");
        }
    }
    
    // 連接到數據庫
    require_once 'db_connect.php';
    
    // 準備SQL語句
    $sql = "INSERT INTO two_player_game_records (
        player1_id, player2_id, player1_name, player2_name, difficulty, 
        player1_score, player2_score, play_time, game_mode, theme, 
        winner_id, total_moves, game_status, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    
    $stmt = $pdo->prepare($sql);
    
    if (!$stmt) {
        throw new Exception('SQL準備失敗');
    }
    
    // 計算獲勝者ID
    $winner_id = null;
    if ($data['player1_score'] > $data['player2_score']) {
        $winner_id = $data['player1_id'];
    } elseif ($data['player2_score'] > $data['player1_score']) {
        $winner_id = $data['player2_id'];
    }
    
    // 執行插入
    $result = $stmt->execute([
        $data['player1_id'],
        $data['player2_id'],
        $data['player1_name'],
        $data['player2_name'],
        $data['difficulty'] ?? 'easy',
        $data['player1_score'],
        $data['player2_score'],
        $data['play_time'] ?? 0,
        $data['game_mode'] ?? '2p',
        $data['theme'] ?? 'fruit',
        $winner_id,
        $data['total_moves'] ?? 0,
        $data['game_status'] ?? 'completed'
    ]);
    
    if (!$result) {
        throw new Exception('保存失敗');
    }
    
    // 更新玩家1的總分數
    $update_player1_sql = "UPDATE member SET total_score = total_score + ? WHERE member_id = ?";
    $update_player1_stmt = $pdo->prepare($update_player1_sql);
    $update_player1_stmt->execute([$data['player1_score'], $data['player1_id']]);
    
    // 更新玩家2的總分數
    $update_player2_sql = "UPDATE member SET total_score = total_score + ? WHERE member_id = ?";
    $update_player2_stmt = $pdo->prepare($update_player2_sql);
    $update_player2_stmt->execute([$data['player2_score'], $data['player2_id']]);
    
    // 檢查並授予成就（雙人遊戲）
    require_once 'check_and_grant_achievements.php';
    
    // 為玩家1檢查成就
    if ($data['player1_score'] > 0) {
        checkAndGrantAchievements($data['player1_id'], 'memory_game', $data['player1_score'], isset($data['play_time']) ? $data['play_time'] : 0);
    }
    
    // 為玩家2檢查成就
    if ($data['player2_score'] > 0) {
        checkAndGrantAchievements($data['player2_id'], 'memory_game', $data['player2_score'], isset($data['play_time']) ? $data['play_time'] : 0);
    }
    
    // 返回成功響應
    echo json_encode([
        'success' => true,
        'message' => '雙人遊戲記錄已保存',
        'result_id' => $pdo->lastInsertId(),
        'winner_id' => $winner_id
    ]);
    
} catch (Exception $e) {
    // 返回錯誤響應
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} catch (Error $e) {
    // 返回系統錯誤響應
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => '系統錯誤: ' . $e->getMessage()
    ]);
} finally {
    // 關閉數據庫連接
    if (isset($pdo)) {
        $pdo = null;
    }
}
?> 