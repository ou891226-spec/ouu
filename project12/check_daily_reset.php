<?php
/**
 * 檢查每日重置狀態
 * 檢查是否需要執行每日重置，並返回相應的狀態
 */

// 使用輸出淨化工具
require_once 'output_cleaner.php';
initCleanOutput();

session_start();
require_once 'db.php';

// 設定台灣時區
date_default_timezone_set('Asia/Taipei');

try {
    // 檢查今日是否已經執行過重置
    $check_sql = "SELECT last_reset_date FROM system_settings WHERE setting_key = 'daily_reset'";
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->execute();
    $last_reset = $check_stmt->fetch();
    
    $today = date('Y-m-d');
    $needs_reset = false;
    
    if (!$last_reset || $last_reset['last_reset_date'] !== $today) {
        // 需要執行重置
        $needs_reset = true;
        
        // 執行每日重置
        include_once 'daily_reset.php';
        
        // 更新重置日期
        $update_sql = "
            INSERT INTO system_settings (setting_key, setting_value, last_reset_date) 
            VALUES ('daily_reset', 'completed', ?) 
            ON DUPLICATE KEY UPDATE 
            setting_value = 'completed', 
            last_reset_date = ?
        ";
        $update_stmt = $pdo->prepare($update_sql);
        $update_stmt->execute([$today, $today]);
        
        outputCleanJson([
            'success' => true,
            'message' => '每日重置已執行',
            'reset_date' => $today
        ]);
    } else {
        // 今日已執行過重置
        outputCleanJson([
            'success' => true,
            'message' => '今日重置已完成',
            'reset_date' => $today
        ]);
    }
    
} catch (Exception $e) {
    // 如果 system_settings 表不存在，創建它
    if (strpos($e->getMessage(), "doesn't exist") !== false) {
        try {
            $create_table_sql = "
                CREATE TABLE IF NOT EXISTS system_settings (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    setting_key VARCHAR(100) UNIQUE NOT NULL,
                    setting_value TEXT,
                    last_reset_date DATE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )
            ";
            $pdo->exec($create_table_sql);
            
            // 重新執行重置檢查
            $today = date('Y-m-d');
            include_once 'daily_reset.php';
            
            $insert_sql = "INSERT INTO system_settings (setting_key, setting_value, last_reset_date) VALUES ('daily_reset', 'completed', ?)";
            $insert_stmt = $pdo->prepare($insert_sql);
            $insert_stmt->execute([$today]);
            
            outputCleanJson([
                'success' => true,
                'message' => '每日重置已執行（首次）',
                'reset_date' => $today
            ]);
            
        } catch (Exception $e2) {
            outputCleanJson([
                'success' => false,
                'message' => '系統初始化失敗：' . $e2->getMessage()
            ]);
        }
    } else {
        outputCleanJson([
            'success' => false,
            'message' => '檢查每日重置失敗：' . $e->getMessage()
        ]);
    }
}
?>
