<?php
session_start();
header('Content-Type: application/json');
require_once 'DB_open.php';

$member_id = $_SESSION['member_id'] ?? null;

if (!$member_id) {
    echo json_encode(['success' => false, 'message' => '尚未登入']);
    exit;
}

$score = 0;
$stmt = $pdo->prepare("SELECT total_score FROM member WHERE member_id = ?");
$stmt->execute([$member_id]);
if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $score = $row['total_score'];
}
echo json_encode(['success' => true, 'score' => $score]);
?>
