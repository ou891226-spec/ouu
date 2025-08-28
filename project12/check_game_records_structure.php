<?php
require_once 'db_connect.php';

try {
    echo "<h2>檢查 game_records 表結構</h2>";
    
    // 檢查表結構
    $stmt = $pdo->query("DESCRIBE game_records");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>表結構：</h3>";
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>欄位名</th><th>類型</th><th>NULL</th><th>KEY</th><th>DEFAULT</th><th>EXTRA</th></tr>";
    
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>{$column['Field']}</td>";
        echo "<td>{$column['Type']}</td>";
        echo "<td>{$column['Null']}</td>";
        echo "<td>{$column['Key']}</td>";
        echo "<td>{$column['Default']}</td>";
        echo "<td>{$column['Extra']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // 檢查最近的幾筆記錄
    echo "<h3>最近的記錄：</h3>";
    $stmt = $pdo->query("SELECT * FROM game_records ORDER BY play_date DESC LIMIT 5");
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($records)) {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr>";
        foreach (array_keys($records[0]) as $column) {
            echo "<th>$column</th>";
        }
        echo "</tr>";
        
        foreach ($records as $record) {
            echo "<tr>";
            foreach ($record as $value) {
                echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>目前沒有記錄</p>";
    }
    
    // 檢查總記錄數
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM game_records");
    $count = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p><strong>總記錄數：{$count['count']}</strong></p>";
    
} catch (Exception $e) {
    echo "❌ 錯誤: " . $e->getMessage();
}
?>

