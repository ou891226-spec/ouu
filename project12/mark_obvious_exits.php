<?php
require_once 'db_connect.php';

echo "手動標記明顯的退出記錄...\n\n";

try {
    // 查找明顯的退出記錄：play_time = 0 且 score = 0 的記錄
    $stmt = $pdo->query("
        SELECT record_id, member_id, game_type, score, play_time, play_date, status
        FROM game_records 
        WHERE play_time = 0 AND score = 0 AND status IN ('completed', 'failed')
        ORDER BY play_date DESC
        LIMIT 50
    ");
    $potential_exits = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "找到 " . count($potential_exits) . " 筆可能的退出記錄：\n\n";
    
    $marked_count = 0;
    foreach ($potential_exits as $record) {
        echo "記錄ID: {$record['record_id']} | 會員: {$record['member_id']} | 遊戲: {$record['game_type']} | 分數: {$record['score']} | 時間: {$record['play_time']}秒 | 狀態: {$record['status']} | 日期: {$record['play_date']}\n";
        
        // 標記為退出
        $update_stmt = $pdo->prepare("UPDATE game_records SET status = 'exited', updated_at = NOW() WHERE record_id = ?");
        $result = $update_stmt->execute([$record['record_id']]);
        
        if ($result) {
            $marked_count++;
            echo "  ✅ 已標記為退出\n";
        } else {
            echo "  ❌ 標記失敗\n";
        }
    }
    
    echo "\n總共標記了 {$marked_count} 筆記錄為退出\n";
    
    // 檢查更新後的統計
    echo "\n更新後的統計：\n";
    $stmt = $pdo->query("
        SELECT 
            status,
            COUNT(*) as count,
            ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM game_records), 2) as percentage
        FROM game_records 
        GROUP BY status 
        ORDER BY count DESC
    ");
    $status_counts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($status_counts as $status) {
        echo "- {$status['status']}: {$status['count']} 筆 ({$status['percentage']}%)\n";
    }
    
    // 檢查各遊戲的退出統計
    echo "\n各遊戲的退出統計：\n";
    $stmt = $pdo->query("
        SELECT 
            game_type,
            COUNT(CASE WHEN status = 'exited' THEN 1 END) as exits,
            COUNT(*) as total,
            ROUND(COUNT(CASE WHEN status = 'exited' THEN 1 END) * 100.0 / COUNT(*), 2) as exit_rate
        FROM game_records 
        GROUP BY game_type 
        HAVING exits > 0
        ORDER BY exit_rate DESC
    ");
    $game_exits = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($game_exits as $game) {
        echo "- {$game['game_type']}: {$game['exits']} 筆退出 ({$game['exit_rate']}%)\n";
    }
    
} catch (Exception $e) {
    echo "錯誤: " . $e->getMessage() . "\n";
}
?>

