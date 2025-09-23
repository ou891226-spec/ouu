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

try {
    // 查詢待處理的交友邀請數量
    $sql = "SELECT COUNT(*) as count FROM friend_requests WHERE receiver_id = ? AND status = 'pending'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$my_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $count = intval($result['count']);
    
    echo json_encode([
        'success' => true,
        'count' => $count,
        'has_invitations' => $count > 0
    ]);
    
} catch (Exception $e) {
    error_log("獲取邀請數量錯誤: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => '獲取邀請數量失敗',
        'count' => 0,
        'has_invitations' => false
    ]);
}
?>




