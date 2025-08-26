<?php
require_once 'db_connect.php';

echo "<h2>檢查 questions 表</h2>";

try {
    // 檢查表是否存在
    $stmt = $pdo->query("SHOW TABLES LIKE 'questions'");
    $tableExists = $stmt->rowCount() > 0;
    
    if ($tableExists) {
        echo "<p style='color: green;'>✓ questions 表存在</p>";
        
        // 檢查表結構
        echo "<h3>表結構：</h3>";
        $stmt = $pdo->query("DESCRIBE questions");
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>欄位名</th><th>類型</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        while ($row = $stmt->fetch()) {
            echo "<tr>";
            echo "<td>" . $row['Field'] . "</td>";
            echo "<td>" . $row['Type'] . "</td>";
            echo "<td>" . $row['Null'] . "</td>";
            echo "<td>" . $row['Key'] . "</td>";
            echo "<td>" . $row['Default'] . "</td>";
            echo "<td>" . $row['Extra'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // 檢查資料數量
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM questions");
        $count = $stmt->fetch()['total'];
        echo "<p>總題目數量: $count</p>";
        
        // 檢查各難度的題目數量
        $stmt = $pdo->query("SELECT difficulty, COUNT(*) as count FROM questions GROUP BY difficulty");
        echo "<h3>各難度題目數量：</h3>";
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>難度</th><th>數量</th></tr>";
        while ($row = $stmt->fetch()) {
            echo "<tr>";
            echo "<td>" . $row['difficulty'] . "</td>";
            echo "<td>" . $row['count'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // 顯示幾個範例題目
        $stmt = $pdo->query("SELECT * FROM questions LIMIT 3");
        echo "<h3>範例題目：</h3>";
        while ($row = $stmt->fetch()) {
            echo "<div style='border: 1px solid #ccc; margin: 10px 0; padding: 10px;'>";
            echo "<p><strong>題目ID:</strong> " . $row['question_id'] . "</p>";
            echo "<p><strong>難度:</strong> " . $row['difficulty'] . "</p>";
            echo "<p><strong>題目:</strong> " . $row['question_text'] . "</p>";
            echo "<p><strong>圖片路徑:</strong> " . $row['image_path'] . "</p>";
            echo "<p><strong>選項1:</strong> " . $row['option_1'] . "</p>";
            echo "<p><strong>選項2:</strong> " . $row['option_2'] . "</p>";
            echo "<p><strong>選項3:</strong> " . $row['option_3'] . "</p>";
            echo "<p><strong>選項4:</strong> " . $row['option_4'] . "</p>";
            echo "<p><strong>正確答案:</strong> " . $row['correct_answer_text'] . "</p>";
            echo "<p><strong>顯示時間:</strong> " . $row['display_time'] . "秒</p>";
            echo "</div>";
        }
        
    } else {
        echo "<p style='color: red;'>✗ questions 表不存在</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>錯誤: " . $e->getMessage() . "</p>";
}
?>
