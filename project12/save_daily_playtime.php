<?php
session_start();
require_once 'db.php';

header('Content-Type: application/json');

$member_id = $_SESSION['member_id'] ?? null;
$play_time_seconds = $_POST['play_time'] ?? 0;

if (!$member_id) {
    echo json_encode(['success' => false, 'message' => '尚未登入']);
    exit;
}

try {
    // 先創建表格（如果不存在）
    $create_table_sql = "CREATE TABLE IF NOT EXISTS daily_playtime_records (
        id INT AUTO_INCREMENT PRIMARY KEY,
        member_id INT NOT NULL,
        play_date DATE NOT NULL,
        total_playtime_seconds INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_member_date (member_id, play_date),
        FOREIGN KEY (member_id) REFERENCES member(member_id) ON DELETE CASCADE
    )";
    $pdo->exec($create_table_sql);
    
    // 獲取今天的日期
    $today = date('Y-m-d');
    
    // 檢查是否已有今天的記錄
    $check_sql = "SELECT id, total_playtime_seconds FROM daily_playtime_records 
                  WHERE member_id = ? AND play_date = ?";
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->execute([$member_id, $today]);
    $existing_record = $check_stmt->fetch();
    
    if ($existing_record) {
        // 更新現有記錄（累加新增的時間）
        $update_sql = "UPDATE daily_playtime_records 
                       SET total_playtime_seconds = total_playtime_seconds + ? 
                       WHERE member_id = ? AND play_date = ?";
        $update_stmt = $pdo->prepare($update_sql);
        $update_stmt->execute([$play_time_seconds, $member_id, $today]);
    } else {
        // 創建新記錄
        $insert_sql = "INSERT INTO daily_playtime_records 
                       (member_id, play_date, total_playtime_seconds) 
                       VALUES (?, ?, ?)";
        $insert_stmt = $pdo->prepare($insert_sql);
        $insert_stmt->execute([$member_id, $today, $play_time_seconds]);
    }
    
    echo json_encode(['success' => true, 'message' => '每日遊玩時間已記錄']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => '記錄失敗：' . $e->getMessage()]);
}
?> 