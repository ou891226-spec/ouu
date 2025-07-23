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

try {
    // 檢查是否已經有邀請
    $sql = "SELECT * FROM friend_requests WHERE sender_id = ? AND receiver_id = ? AND status = 'pending'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$sender_id, $receiver_id]);
    $result = $stmt->fetch();
    
    if ($result) {
        exit('已送出邀請');
    }

    // 寫入邀請
    $sql = "INSERT INTO friend_requests (sender_id, receiver_id, status, created_at) VALUES (?, ?, 'pending', NOW())";
    $stmt = $pdo->prepare($sql);
    if ($stmt->execute([$sender_id, $receiver_id])) {
        echo 'success';
    } else {
        http_response_code(500);
        echo 'fail';
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo '資料庫錯誤：' . $e->getMessage();
}
?>
