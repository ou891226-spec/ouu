<?php
/**
 * 檢查 member 表狀況
 */

require_once 'db.php';

echo "<h2>🔍 Member 表檢查</h2>\n";

try {
    // 檢查 member 表是否存在
    $stmt = $pdo->query("SHOW TABLES LIKE 'member'");
    $table_exists = $stmt->fetch();
    
    if ($table_exists) {
        echo "✅ member 表存在<br>\n";
        
        // 檢查表結構
        $stmt = $pdo->query("DESCRIBE member");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h3>📋 表結構:</h3>\n";
        echo "<table border='1' style='border-collapse: collapse;'>\n";
        echo "<tr><th>欄位名</th><th>類型</th><th>Null</th><th>預設值</th></tr>\n";
        
        foreach ($columns as $column) {
            echo "<tr>\n";
            echo "<td>{$column['Field']}</td>\n";
            echo "<td>{$column['Type']}</td>\n";
            echo "<td>{$column['Null']}</td>\n";
            echo "<td>{$column['Default']}</td>\n";
            echo "</tr>\n";
        }
        echo "</table>\n";
        
        // 檢查數據數量
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM member");
        $count = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<br>📊 用戶數量: " . $count['count'] . "<br>\n";
        
        if ($count['count'] > 0) {
            // 顯示前幾筆用戶資料
            $stmt = $pdo->query("SELECT member_id, name, email FROM member LIMIT 10");
            $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<h3>👥 用戶列表:</h3>\n";
            echo "<table border='1' style='border-collapse: collapse;'>\n";
            echo "<tr><th>ID</th><th>姓名</th><th>Email</th></tr>\n";
            
            foreach ($members as $member) {
                echo "<tr>\n";
                echo "<td>{$member['member_id']}</td>\n";
                echo "<td>" . htmlspecialchars($member['name'] ?? 'NULL') . "</td>\n";
                echo "<td>" . htmlspecialchars($member['email'] ?? 'NULL') . "</td>\n";
                echo "</tr>\n";
            }
            echo "</table>\n";
        } else {
            echo "<br>❌ 表中沒有用戶資料<br>\n";
        }
        
    } else {
        echo "❌ member 表不存在<br>\n";
        
        // 檢查是否有其他類似的表
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo "<h3>📋 現有表列表:</h3>\n";
        echo "<ul>\n";
        foreach ($tables as $table) {
            echo "<li>{$table}</li>\n";
        }
        echo "</ul>\n";
    }
    
} catch (Exception $e) {
    echo "<h3>❌ 檢查失敗</h3>\n";
    echo "錯誤訊息: " . $e->getMessage() . "<br>\n";
}
?>
