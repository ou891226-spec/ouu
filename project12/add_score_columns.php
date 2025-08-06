<?php
require_once 'db.php';

try {
    // 檢查並添加缺失的分類分數欄位
    $columns_to_add = [
        'reaction_score' => 'INT DEFAULT 0',
        'memory_score' => 'INT DEFAULT 0', 
        'logic_score' => 'INT DEFAULT 0'
    ];
    
    foreach ($columns_to_add as $column_name => $column_definition) {
        // 檢查欄位是否存在
        $check_sql = "SHOW COLUMNS FROM member LIKE '$column_name'";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute();
        
        if ($check_stmt->rowCount() == 0) {
            // 欄位不存在，添加它
            $add_sql = "ALTER TABLE member ADD COLUMN $column_name $column_definition";
            $add_stmt = $pdo->prepare($add_sql);
            $add_stmt->execute();
            echo "✅ 已添加欄位：$column_name\n";
        } else {
            echo "ℹ️ 欄位已存在：$column_name\n";
        }
    }
    
    echo "\n✅ 分類分數欄位檢查完成！\n";
    
} catch (Exception $e) {
    echo "錯誤：" . $e->getMessage();
}
?> 