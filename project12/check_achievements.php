<?php
require_once 'db.php';

try {
    $stmt = $pdo->prepare('SELECT achievement_name, icon FROM achievements ORDER BY achievement_id');
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "成就圖示檢查：\n";
    echo "================\n";
    foreach($results as $row) {
        echo $row['achievement_name'] . ': ' . $row['icon'] . "\n";
    }
    
} catch (Exception $e) {
    echo "錯誤: " . $e->getMessage();
}
?> 