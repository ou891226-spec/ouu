<?php
require_once 'db.php';

echo "<h2>強制離線</h2>";

try {
    // 將所有用戶標記為離線
    $cleanup_sql = "UPDATE user_online_status SET is_online = 0";
    $pdo->exec($cleanup_sql);
    
    echo "<p style='color: green;'>✅ 所有用戶已強制離線</p>";
    
    // 顯示當前狀態
    $stmt = $pdo->query("SELECT COUNT(*) as total, SUM(is_online) as online FROM user_online_status");
    $result = $stmt->fetch();
    
    echo "<p>總記錄數: {$result['total']}</p>";
    echo "<p>線上用戶數: {$result['online']}</p>";
    
    // 顯示最近的記錄
    $stmt = $pdo->query("SELECT uos.*, m.account, m.member_name FROM user_online_status uos 
                        LEFT JOIN member m ON uos.member_id = m.member_id 
                        ORDER BY uos.last_activity DESC LIMIT 5");
    $records = $stmt->fetchAll();
    
    if (count($records) > 0) {
        echo "<h3>最近記錄:</h3>";
        echo "<table border='1'>";
        echo "<tr><th>用戶</th><th>會話ID</th><th>最後活動</th><th>線上狀態</th></tr>";
        foreach ($records as $record) {
            $status = $record['is_online'] ? '線上' : '離線';
            $status_color = $record['is_online'] ? 'green' : 'red';
            echo "<tr>";
            echo "<td>{$record['account']} ({$record['member_name']})</td>";
            echo "<td>" . substr($record['session_id'], 0, 20) . "...</td>";
            echo "<td>{$record['last_activity']}</td>";
            echo "<td style='color: $status_color;'>$status</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ 錯誤: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='admin/user_management.php'>查看用戶管理頁面</a></p>";
echo "<p><small>這個腳本會立即將所有用戶標記為離線，用於測試和修正狀態。</small></p>";
?>
