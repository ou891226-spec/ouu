<?php
session_start();
require_once '../../db.php';

header('Content-Type: application/json');

// 只接受POST請求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => '只允許POST請求']);
    exit;
}

try {
    // 獲取POST數據
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('無效的JSON數據');
    }
    
    // 獲取用戶ID（如果已登入）
    $member_id = $_SESSION['member_id'] ?? null;
    
    // 基本驗證
    $required_fields = ['action_type', 'session_id', 'page_url', 'timestamp'];
    foreach ($required_fields as $field) {
        if (!isset($input[$field])) {
            throw new Exception("缺少必要欄位: $field");
        }
    }
    
    // 準備插入數據
    $insert_data = [
        'member_id' => $member_id,
        'action_type' => $input['action_type'],
        'page_url' => $input['page_url'],
        'session_id' => $input['session_id'],
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'additional_data' => json_encode($input)
    ];
    
    // 插入行為記錄
    $sql = "
        INSERT INTO user_behavior_log 
        (member_id, action_type, page_url, session_id, ip_address, user_agent, additional_data, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $insert_data['member_id'],
        $insert_data['action_type'],
        $insert_data['page_url'],
        $insert_data['session_id'],
        $insert_data['ip_address'],
        $insert_data['user_agent'],
        $insert_data['additional_data'],
        $input['timestamp']
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => '行為記錄已保存',
        'log_id' => $pdo->lastInsertId()
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => '記錄行為失敗：' . $e->getMessage()
    ]);
}
?> 