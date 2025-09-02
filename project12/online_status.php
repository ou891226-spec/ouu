<?php
session_start();
require_once 'db.php';

// 處理離線信號（來自 sendBeacon）
$input = file_get_contents('php://input');
if ($input) {
    $data = json_decode($input, true);
    if ($data && isset($data['action']) && $data['action'] === 'offline') {
        // 標記當前會話為離線
        if (isset($_SESSION['member_id'])) {
            $member_id = $_SESSION['member_id'];
            $session_id = session_id();
            
            try {
                $offline_sql = "UPDATE user_online_status SET is_online = 0 WHERE member_id = ? AND session_id = ?";
                $offline_stmt = $pdo->prepare($offline_sql);
                $offline_stmt->execute([$member_id, $session_id]);
                exit('offline');
            } catch (Exception $e) {
                exit('error');
            }
        }
        exit('no_session');
    }
}

// 檢查用戶是否已登入
if (!isset($_SESSION['member_id'])) {
    http_response_code(401);
    exit('未登入');
}

$member_id = $_SESSION['member_id'];
$session_id = session_id();
$current_time = date('Y-m-d H:i:s');

try {
    // 檢查是否已有線上記錄
    $check_sql = "SELECT * FROM user_online_status WHERE member_id = ? AND session_id = ?";
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->execute([$member_id, $session_id]);
    $existing = $check_stmt->fetch();

    if ($existing) {
        // 更新現有記錄
        $update_sql = "UPDATE user_online_status SET last_activity = ?, is_online = 1 WHERE member_id = ? AND session_id = ?";
        $update_stmt = $pdo->prepare($update_sql);
        $update_stmt->execute([$current_time, $member_id, $session_id]);
    } else {
        // 插入新記錄
        $insert_sql = "INSERT INTO user_online_status (member_id, session_id, last_activity, is_online) VALUES (?, ?, ?, 1)";
        $insert_stmt = $pdo->prepare($insert_sql);
        $insert_stmt->execute([$member_id, $session_id, $current_time]);
    }

    // 清理超過5分鐘沒有活動的記錄
    $cleanup_sql = "UPDATE user_online_status SET is_online = 0 WHERE last_activity < DATE_SUB(NOW(), INTERVAL 5 MINUTE)";
    $pdo->exec($cleanup_sql);

    echo json_encode(['status' => 'success']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
