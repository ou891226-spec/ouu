<?php
session_start();
require_once 'db.php';

header('Content-Type: application/json');

$member_id = $_SESSION['member_id'] ?? null;

if (!$member_id) {
    echo json_encode(['success' => false, 'message' => '尚未登入']);
    exit;
}

try {
    // 獲取所有有遊玩記錄的年份和月份
    $sql = "
        SELECT DISTINCT 
            YEAR(play_date) as year,
            MONTH(play_date) as month,
            DATE_FORMAT(play_date, '%Y-%m') as year_month
        FROM (
            SELECT play_date FROM game_records WHERE member_id = ?
            UNION
            SELECT play_date FROM daily_playtime_records WHERE member_id = ?
        ) combined_dates
        ORDER BY year DESC, month DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$member_id, $member_id]);
    $results = $stmt->fetchAll();
    
    // 格式化結果
    $available_months = [];
    foreach ($results as $row) {
        $available_months[] = [
            'year' => $row['year'],
            'month' => $row['month'],
            'year_month' => $row['year_month'],
            'display' => $row['year'] . '年' . $row['month'] . '月'
        ];
    }
    
    echo json_encode([
        'success' => true,
        'available_months' => $available_months
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => '獲取月份失敗：' . $e->getMessage()]);
}
?> 