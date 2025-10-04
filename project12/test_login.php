<?php
session_start();

echo "Session資訊:\n";
echo "member_id: " . ($_SESSION['member_id'] ?? 'null') . "\n";
echo "account: " . ($_SESSION['account'] ?? 'null') . "\n";
echo "name: " . ($_SESSION['name'] ?? 'null') . "\n";
echo "session_id: " . session_id() . "\n";

// 檢查get_score.php的邏輯
require_once 'db_connect.php';

$member_id = $_SESSION['member_id'] ?? null;

if (!$member_id) {
    echo "\n❌ 未登入 - 這就是問題所在！\n";
    echo "請先登入系統，然後再玩遊戲。\n";
} else {
    echo "\n✅ 已登入\n";
    
    // 檢查分數
    $stmt = $pdo->prepare("SELECT total_score FROM member WHERE member_id = ?");
    $stmt->execute([$member_id]);
    $result = $stmt->fetch();
    
    if ($result) {
        echo "資料庫中的總分: {$result['total_score']}\n";
    }
}
?>
