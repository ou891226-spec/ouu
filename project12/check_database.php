<?php
/**
 * 檢查資料庫結構
 */

require_once 'db.php';

echo "<h2>🗄️ 資料庫結構檢查</h2>\n";

try {
    // 檢查 game_baseline_times 表是否存在
    $stmt = $pdo->query("SHOW TABLES LIKE 'game_baseline_times'");
    $table_exists = $stmt->fetch();
    
    if ($table_exists) {
        echo "✅ game_baseline_times 表存在<br>\n";
        
        // 檢查表結構
        $stmt = $pdo->query("DESCRIBE game_baseline_times");
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
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM game_baseline_times");
        $count = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<br>📊 當前記錄數量: " . $count['count'] . "<br>\n";
        
        if ($count['count'] > 0) {
            // 顯示前幾筆記錄
            $stmt = $pdo->query("SELECT game_type, baseline_time, stage FROM game_baseline_times LIMIT 5");
            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<h3>🎮 現有遊戲記錄:</h3>\n";
            echo "<table border='1' style='border-collapse: collapse;'>\n";
            echo "<tr><th>遊戲類型</th><th>基準時間</th><th>階段</th></tr>\n";
            
            foreach ($records as $record) {
                echo "<tr>\n";
                echo "<td>{$record['game_type']}</td>\n";
                echo "<td>{$record['baseline_time']}秒</td>\n";
                echo "<td>{$record['stage']}</td>\n";
                echo "</tr>\n";
            }
            echo "</table>\n";
        }
        
    } else {
        echo "❌ game_baseline_times 表不存在<br>\n";
        echo "請先執行 admin/database_weighted_scoring.sql 來建立表結構。<br>\n";
    }
    
    // 檢查 game_records 表
    $stmt = $pdo->query("SHOW TABLES LIKE 'game_records'");
    $game_records_exists = $stmt->fetch();
    
    if ($game_records_exists) {
        echo "<br>✅ game_records 表存在<br>\n";
        
        // 檢查遊戲記錄數量
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM game_records");
        $count = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "📊 遊戲記錄數量: " . $count['count'] . "<br>\n";
        
        if ($count['count'] > 0) {
            // 檢查有哪些遊戲類型
            $stmt = $pdo->query("SELECT game_type, COUNT(*) as count FROM game_records GROUP BY game_type LIMIT 10");
            $game_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<h3>🎯 遊戲類型統計:</h3>\n";
            echo "<table border='1' style='border-collapse: collapse;'>\n";
            echo "<tr><th>遊戲類型</th><th>記錄數量</th></tr>\n";
            
            foreach ($game_types as $game) {
                echo "<tr>\n";
                echo "<td>{$game['game_type']}</td>\n";
                echo "<td>{$game['count']}</td>\n";
                echo "</tr>\n";
            }
            echo "</table>\n";
        }
    } else {
        echo "<br>❌ game_records 表不存在<br>\n";
    }
    
} catch (Exception $e) {
    echo "<h3>❌ 檢查失敗</h3>\n";
    echo "錯誤訊息: " . $e->getMessage() . "<br>\n";
}
?>
