<?php
require_once 'db.php';
session_start();

echo "<h1>修復用戶 Session 頭像資訊</h1>";

if (!isset($_SESSION['member_id'])) {
    echo "<p>❌ 請先登入</p>";
    exit;
}

$current_user_id = $_SESSION['member_id'];

echo "<h2>當前用戶資訊</h2>";
echo "<p>會員ID: $current_user_id</p>";
echo "<p>姓名: " . ($_SESSION['name'] ?? '未知') . "</p>";
echo "<p>帳號: " . ($_SESSION['account'] ?? '未知') . "</p>";

echo "<h2>修復當前用戶的 Session</h2>";

try {
    // 從資料庫獲取最新的用戶資訊
    $sql = "SELECT member_name, avatar FROM member WHERE member_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$current_user_id]);
    $result = $stmt->fetch();
    
    if ($result) {
        echo "<p>資料庫中的姓名: " . $result['member_name'] . "</p>";
        echo "<p>資料庫中的頭像: " . ($result['avatar'] ?? 'NULL') . "</p>";
        
        // 更新 Session
        $_SESSION['name'] = $result['member_name'];
        $_SESSION['avatar_url'] = $result['avatar'];
        
        echo "<p>✅ Session 已更新</p>";
        echo "<p>SESSION['name']: " . $_SESSION['name'] . "</p>";
        echo "<p>SESSION['avatar_url']: " . $_SESSION['avatar_url'] . "</p>";
        
        // 檢查頭像檔案
        if ($result['avatar']) {
            $full_path = __DIR__ . '/' . $result['avatar'];
            if (file_exists($full_path)) {
                echo "<p>✅ 頭像檔案存在</p>";
                echo "<p>檔案大小: " . filesize($full_path) . " bytes</p>";
                
                // 顯示頭像預覽
                echo "<h3>頭像預覽</h3>";
                echo "<img src='" . htmlspecialchars($result['avatar']) . "' style='width: 100px; height: 100px; border: 2px solid #ccc;'>";
            } else {
                echo "<p>❌ 頭像檔案不存在: $full_path</p>";
            }
        }
    } else {
        echo "<p>❌ 找不到用戶資料</p>";
    }
    
} catch (Exception $e) {
    echo "<p>❌ 修復失敗: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<h2>測試修復結果</h2>";
echo "<p>現在請：</p>";
echo "<ol>";
echo "<li>重新整理主頁面 (index.php)</li>";
echo "<li>檢查頭像是否顯示正確的藍色圓形頭像</li>";
echo "<li>如果還是舊頭像，請清除瀏覽器快取後再試</li>";
echo "</ol>";

echo "<p><a href='index.php'>返回主頁面</a></p>";
echo "<p><a href='debug_avatar.php'>重新檢查頭像狀態</a></p>";
?>
