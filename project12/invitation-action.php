<?php
require_once "DB_open.php";
session_start();
if (!isset($_SESSION['member_id'])) {
    http_response_code(401);
    exit('未登入');
}
$my_id = $_SESSION['member_id'];
$action = $_POST['action'] ?? '';
$request_id = intval($_POST['request_id'] ?? 0);
if (!$request_id || !in_array($action, ['accept','reject'])) {
    http_response_code(400); exit('參數錯誤');
}
// 查詢邀請資訊
$sql = "SELECT * FROM friend_requests WHERE request_id = ? AND receiver_id = ? AND status = 'pending'";
$stmt = $pdo->prepare($sql);
$stmt->execute([$request_id, $my_id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    http_response_code(404); exit('邀請不存在');
}
$sender_id = $row['sender_id'];
if ($action === 'accept') {
    // 更新邀請狀態
    $sql = "UPDATE friend_requests SET status = 'accepted' WHERE request_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$request_id]);
    // 新增雙向好友
    $sql = "INSERT IGNORE INTO friends (member_id, friend_id, created_at) VALUES (?, ?, NOW()), (?, ?, NOW())";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$my_id, $sender_id, $sender_id, $my_id]);
    echo 'accepted';
} else if ($action === 'reject') {
    $sql = "UPDATE friend_requests SET status = 'rejected' WHERE request_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$request_id]);
    echo 'rejected';
} 