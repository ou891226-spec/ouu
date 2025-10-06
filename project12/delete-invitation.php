<?php
require_once 'db.php';
session_start();

header('Content-Type: application/json');

// 檢查用戶是否已登入
if (!isset($_SESSION['member_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => '未登入']);
    exit;
}

$my_id = $_SESSION['member_id'];
$request_id = intval($_POST['request_id'] ?? 0);

if ($request_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '參數錯誤']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // 檢查是否確實為該用戶送出的邀請
    $check_sql = "SELECT COUNT(*) as count FROM friend_requests WHERE request_id = ? AND sender_id = ?";
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->execute([$request_id, $my_id]);
    $result = $check_stmt->fetch();
    
    if ($result['count'] == 0) {
        throw new Exception('該邀請不存在或您無權限刪除');
    }
    
    // 刪除邀請
    $delete_sql = "DELETE FROM friend_requests WHERE request_id = ? AND sender_id = ?";
    $delete_stmt = $pdo->prepare($delete_sql);
    $delete_stmt->execute([$request_id, $my_id]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => '已成功取消邀請'
    ]);
    
} catch (Exception $e) {
    $pdo->rollback();
    error_log("刪除邀請錯誤: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => '取消邀請失敗：' . $e->getMessage()
    ]);
}
?>
