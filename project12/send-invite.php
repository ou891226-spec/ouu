<?php
require_once "DB_open.php";
session_start();

if (!isset($_SESSION['member_id'])) {
    http_response_code(401);
    exit('未登入');
}

$sender_id = $_SESSION['member_id'];
$receiver_id = intval($_POST['friend_id'] ?? 0);

if ($receiver_id <= 0 || $receiver_id == $sender_id) {
    http_response_code(400);
    exit('參數錯誤');
}

// 檢查是否已經有邀請
$sql = "SELECT * FROM friend_requests WHERE sender_id = ? AND receiver_id = ? AND status = 'pending'";
$stmt = $link->prepare($sql);
$stmt->bind_param("ii", $sender_id, $receiver_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    exit('已送出邀請');
}

// 寫入邀請
$sql = "INSERT INTO friend_requests (sender_id, receiver_id, status, created_at) VALUES (?, ?, 'pending', NOW())";
$stmt = $link->prepare($sql);
$stmt->bind_param("ii", $sender_id, $receiver_id);
if ($stmt->execute()) {
    echo 'success';
} else {
    http_response_code(500);
    echo 'fail';
}
?>