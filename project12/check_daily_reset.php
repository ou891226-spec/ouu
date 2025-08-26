<?php
require_once 'db.php';

// 設定台灣時區
date_default_timezone_set('Asia/Taipei');

// 檢查是否需要執行每日重置
function checkAndExecuteDailyReset() {
    global $pdo;
    
    try {
        // 檢查最後重置時間
        $last_reset_sql = "
            SELECT MAX(created_at) as last_reset 
            FROM daily_reset_log 
            WHERE DATE(created_at) = CURDATE()
        ";
        $stmt = $pdo->prepare($last_reset_sql);
        $stmt->execute();
        $result = $stmt->fetch();
        
        $today = date('Y-m-d');
        $last_reset = $result['last_reset'] ?? null;
        
        // 如果今天還沒有重置過，執行重置
        if (!$last_reset || date('Y-m-d', strtotime($last_reset)) !== $today) {
            // 捕獲 daily_reset.php 的輸出，防止干擾 JSON
            ob_start();
            include 'daily_reset.php';
            $reset_output = ob_get_clean();
            
            // 記錄重置時間
            $log_sql = "INSERT INTO daily_reset_log (created_at) VALUES (NOW())";
            $log_stmt = $pdo->prepare($log_sql);
            $log_stmt->execute();
            
            return true;
        }
        
        return false;
        
    } catch (Exception $e) {
        error_log("檢查每日重置失敗: " . $e->getMessage());
        return false;
    }
}

// 創建重置日誌表（如果不存在）
function createResetLogTable() {
    global $pdo;
    
    $create_table_sql = "
        CREATE TABLE IF NOT EXISTS daily_reset_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            created_at DATETIME NOT NULL,
            INDEX idx_created_at (created_at)
        )
    ";
    
    try {
        $pdo->exec($create_table_sql);
    } catch (Exception $e) {
        error_log("創建重置日誌表失敗: " . $e->getMessage());
    }
}

// 執行檢查
createResetLogTable();
$reset_executed = checkAndExecuteDailyReset();

// 設置 JSON 響應頭
header('Content-Type: application/json; charset=utf-8');

if ($reset_executed) {
    echo json_encode([
        'success' => true,
        'message' => '每日重置已執行',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
} else {
    echo json_encode([
        'success' => true,
        'message' => '今日已重置過',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>
