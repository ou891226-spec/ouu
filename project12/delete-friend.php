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
$friend_id = intval($_POST['friend_id'] ?? 0);

if ($friend_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '參數錯誤']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // 檢查是否確實為好友關係
    $check_sql = "SELECT COUNT(*) as count FROM friends WHERE member_id = ? AND friend_id = ?";
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->execute([$my_id, $friend_id]);
    $result = $check_stmt->fetch();
    
    if ($result['count'] == 0) {
        throw new Exception('該用戶不在您的好友列表中');
    }
    
    // 刪除雙向好友關係
    $delete_sql = "DELETE FROM friends WHERE (member_id = ? AND friend_id = ?) OR (member_id = ? AND friend_id = ?)";
    $delete_stmt = $pdo->prepare($delete_sql);
    $delete_stmt->execute([$my_id, $friend_id, $friend_id, $my_id]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => '已成功移除好友'
    ]);
    
} catch (Exception $e) {
    $pdo->rollback();
    error_log("刪除好友錯誤: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => '刪除好友失敗：' . $e->getMessage()
    ]);
}
?>



