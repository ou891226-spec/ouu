<?php
session_start();
header('Content-Type: application/json');
require_once 'db.php';

// 添加除錯資訊
$debug_info = [
    'session_id' => session_id(),
    'member_id' => $_SESSION['member_id'] ?? 'null',
    'account' => $_SESSION['account'] ?? 'null',
    'name' => $_SESSION['name'] ?? 'null',
    'all_session' => $_SESSION
];

$member_id = $_SESSION['member_id'] ?? null;

if (!$member_id) {
    echo json_encode([
        'success' => false, 
        'message' => '尚未登入',
        'debug' => $debug_info
    ]);
    exit;
}

try {
    $score = 0;
    $stmt = $pdo->prepare("SELECT total_score FROM member WHERE member_id = ?");
    $stmt->execute([$member_id]);
    $result = $stmt->fetch();
    
    if ($result) {
        $score = $result['total_score'];
    }
    
    echo json_encode([
        'success' => true, 
        'score' => $score,
        'debug' => $debug_info
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => '資料庫錯誤：' . $e->getMessage(),
        'debug' => $debug_info
    ]);
}
?>
