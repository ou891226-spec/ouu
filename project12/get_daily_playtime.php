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
    // 獲取最近30天的遊玩時間紀錄
    $sql = "SELECT play_date, total_playtime_seconds 
            FROM daily_playtime_records 
            WHERE member_id = ? 
            ORDER BY play_date DESC 
            LIMIT 30";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$member_id]);
    $records = $stmt->fetchAll();
    
    // 格式化時間顯示
    $formatted_records = [];
    foreach ($records as $record) {
        $hours = floor($record['total_playtime_seconds'] / 3600);
        $minutes = floor(($record['total_playtime_seconds'] % 3600) / 60);
        $seconds = $record['total_playtime_seconds'] % 60;
        
        $formatted_records[] = [
            'date' => $record['play_date'],
            'playtime' => sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds),
            'seconds' => $record['total_playtime_seconds']
        ];
    }
    
    echo json_encode([
        'success' => true, 
        'records' => $formatted_records
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => '獲取記錄失敗：' . $e->getMessage()]);
}
?> 