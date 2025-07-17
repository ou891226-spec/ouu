<?php
// 測試雙人記憶遊戲系統
require_once 'db_connect_memory_game.php';
session_start();

echo "<h1>雙人記憶遊戲系統測試</h1>";

// 檢查資料庫連接
try {
    $pdo->query("SELECT 1");
    echo "<h2>✅ 資料庫連接成功</h2>";
} catch (Exception $e) {
    echo "<h2>❌ 資料庫連接失敗: " . $e->getMessage() . "</h2>";
    exit;
}

// 檢查必要的表格
$tables = [
    'memory_game_themes' => '記憶遊戲主題',
    'memory_game_difficulty_settings' => '記憶遊戲難度設定',
    'memory_game_colors' => '記憶遊戲顏色',
    'game_invitations' => '遊戲邀請',
    'game_rooms' => '遊戲房間',
    'friends' => '好友關係',
    'member' => '會員資料',
    'game_records' => '遊戲記錄'
];

echo "<h3>資料庫表格檢查：</h3>";
foreach ($tables as $table => $description) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
        $count = $stmt->fetchColumn();
        echo "<p>✅ $description ($table): $count 筆資料</p>";
    } catch (Exception $e) {
        echo "<p>❌ $description ($table): " . $e->getMessage() . "</p>";
    }
}

// 檢查會員登入狀態
if (isset($_SESSION['member_id'])) {
    echo "<h3>✅ 會員已登入 (ID: " . $_SESSION['member_id'] . ")</h3>";
    
    // 檢查好友數量
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM friends WHERE member_id = ?");
        $stmt->execute([$_SESSION['member_id']]);
        $friendCount = $stmt->fetchColumn();
        echo "<p>好友數量: $friendCount</p>";
    } catch (Exception $e) {
        echo "<p>❌ 檢查好友數量失敗: " . $e->getMessage() . "</p>";
    }
    
    // 檢查待處理邀請
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM game_invitations WHERE invitee_id = ? AND status = 'pending'");
        $stmt->execute([$_SESSION['member_id']]);
        $inviteCount = $stmt->fetchColumn();
        echo "<p>待處理遊戲邀請: $inviteCount</p>";
    } catch (Exception $e) {
        echo "<p>❌ 檢查邀請數量失敗: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<h3>❌ 會員未登入</h3>";
    echo "<p><a href='login.php'>前往登入</a></p>";
}

echo "<hr>";
echo "<h3>測試連結：</h3>";
echo "<p><a href='Memory-Game-2P.php' target='_blank'>雙人記憶遊戲</a></p>";
echo "<p><a href='friend.php' target='_blank'>好友管理</a></p>";
echo "<p><a href='add-friend.php' target='_blank'>添加好友</a></p>";
echo "<p><a href='invitation-friend.php' target='_blank'>交友邀請</a></p>";

echo "<hr>";
echo "<h3>系統說明：</h3>";
echo "<ul>";
echo "<li>雙人記憶遊戲需要先成為好友才能邀請</li>";
echo "<li>遊戲邀請會儲存在 game_invitations 表格中</li>";
echo "<li>接受邀請後可以開始遊戲</li>";
echo "<li>遊戲結果會儲存在 game_records 表格中</li>";
echo "</ul>";
?> 