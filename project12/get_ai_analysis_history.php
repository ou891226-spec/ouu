<?php
/**
 * 獲取AI分析歷史記錄
 */

// 使用輸出淨化工具
require_once 'output_cleaner.php';
initCleanOutput();

session_start();
require_once 'db_connect.php';

// 檢查是否登入
if (!isset($_SESSION['member_id'])) {
    outputCleanJson([
        'success' => false,
        'message' => '尚未登入'
    ]);
}

$member_id = $_SESSION['member_id'];

// 獲取查詢參數
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
$offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;

try {
    // 獲取總記錄數
    $count_sql = "SELECT COUNT(*) as total FROM ai_analysis_history WHERE member_id = ?";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute([$member_id]);
    $total_count = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // 獲取歷史記錄（按時間倒序）
    // 注意：LIMIT 和 OFFSET 必須是整數，不能用 PDO 參數綁定字符串
    $history_sql = "
        SELECT 
            id,
            analysis_type,
            player_type,
            description,
            suggestions,
            reaction_score,
            memory_score,
            logic_score,
            reaction_games,
            memory_games,
            logic_games,
            ai_enhanced,
            created_at,
            DATE_FORMAT(created_at, '%Y年%m月%d日 %H:%i:%s') as formatted_time
        FROM ai_analysis_history
        WHERE member_id = ?
        ORDER BY created_at DESC
        LIMIT $limit OFFSET $offset
    ";
    
    $stmt = $pdo->prepare($history_sql);
    $stmt->execute([$member_id]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 處理每條記錄
    foreach ($records as &$record) {
        // 將JSON字符串轉換為數組
        $record['suggestions'] = json_decode($record['suggestions'], true);
        // 轉換布爾值
        $record['ai_enhanced'] = (bool)$record['ai_enhanced'];
        
        // 計算時間差（多久之前）
        $created_time = strtotime($record['created_at']);
        $now = time();
        $diff = $now - $created_time;
        
        if ($diff < 60) {
            $record['time_ago'] = '剛剛';
        } elseif ($diff < 3600) {
            $record['time_ago'] = floor($diff / 60) . '分鐘前';
        } elseif ($diff < 86400) {
            $record['time_ago'] = floor($diff / 3600) . '小時前';
        } elseif ($diff < 2592000) {
            $record['time_ago'] = floor($diff / 86400) . '天前';
        } else {
            $record['time_ago'] = floor($diff / 2592000) . '個月前';
        }
    }
    
    outputCleanJson([
        'success' => true,
        'total_count' => $total_count,
        'records' => $records,
        'has_more' => ($offset + $limit) < $total_count
    ]);
    
} catch (PDOException $e) {
    error_log("獲取AI分析歷史失敗: " . $e->getMessage());
    outputCleanJson([
        'success' => false,
        'message' => '獲取歷史記錄失敗：' . $e->getMessage()
    ]);
}
?>

