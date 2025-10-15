<?php
/**
 * Cron Job 清理腳本
 * 定期清理長時間停留在 'entered' 狀態的遊戲記錄
 * 
 * 建議執行頻率：每 10-15 分鐘執行一次
 * 
 * Cron Job 設定範例（每 15 分鐘執行一次）：
 * */15 * * * * php /path/to/your/project/cron_cleanup_game_entries.php >> /path/to/logs/cleanup.log 2>&1
 * 
 * 或者（每 10 分鐘執行一次）：
 * */10 * * * * php /path/to/your/project/cron_cleanup_game_entries.php >> /path/to/logs/cleanup.log 2>&1
 */

// 設定時區
date_default_timezone_set('Asia/Taipei');

// 記錄執行開始
$start_time = microtime(true);
$log_message = "[" . date('Y-m-d H:i:s') . "] Cron Job 開始執行\n";
echo $log_message;
error_log($log_message);

// 引入必要的文件
require_once __DIR__ . '/game_entry_tracker.php';

try {
    // 執行清理
    $timeout_minutes = 10; // 改為您希望的 10 分鐘
    
    echo "開始清理超過 {$timeout_minutes} 分鐘的 entered 記錄...\n";
    
    $cleaned_count = cleanupExpiredGameEntries($timeout_minutes);
    
    $end_time = microtime(true);
    $execution_time = round($end_time - $start_time, 2);
    
    // 記錄執行結果
    $result_message = "[" . date('Y-m-d H:i:s') . "] 清理完成：清理了 {$cleaned_count} 筆記錄，執行時間：{$execution_time} 秒\n";
    echo $result_message;
    error_log($result_message);
    
    // 輸出統計信息
    outputGameStats();
    
    echo str_repeat("-", 50) . "\n\n";
    
} catch (Exception $e) {
    $error_message = "[" . date('Y-m-d H:i:s') . "] 錯誤：" . $e->getMessage() . "\n";
    echo $error_message;
    error_log($error_message);
    exit(1);
}

/**
 * 輸出遊戲記錄統計信息
 */
function outputGameStats() {
    global $pdo;
    
    try {
        // 統計各狀態的記錄數
        $stmt = $pdo->query("
            SELECT 
                status,
                COUNT(*) as count
            FROM game_records
            WHERE play_date >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            GROUP BY status
        ");
        $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "\n過去 24 小時的遊戲記錄統計：\n";
        foreach ($stats as $stat) {
            echo "  {$stat['status']}: {$stat['count']} 筆\n";
        }
        
        // 統計當前仍在 entered 狀態的記錄
        $entered_stmt = $pdo->query("
            SELECT COUNT(*) as count
            FROM game_records
            WHERE status = 'entered'
        ");
        $entered_count = $entered_stmt->fetch(PDO::FETCH_ASSOC)['count'];
        echo "  目前仍在 entered 狀態: {$entered_count} 筆\n";
        
    } catch (Exception $e) {
        error_log("統計遊戲記錄失敗: " . $e->getMessage());
    }
}

exit(0);
?>

