<?php
require_once 'db.php';

echo "開始初始化每日遊玩時間數據...\n";

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
    echo "✅ 表格創建成功\n";
    
    // 獲取所有會員
    $member_sql = "SELECT member_id FROM member";
    $member_stmt = $pdo->query($member_sql);
    $members = $member_stmt->fetchAll();
    
    echo "找到 " . count($members) . " 個會員\n";
    
    // 為每個會員創建過去7天的示例數據
    for ($i = 0; $i < 7; $i++) {
        $date = date('Y-m-d', strtotime("-$i days"));
        
        foreach ($members as $member) {
            $member_id = $member['member_id'];
            
            // 隨機生成遊玩時間（30分鐘到3小時）
            $playtime_seconds = rand(1800, 10800);
            
            // 檢查是否已有記錄
            $check_sql = "SELECT id FROM daily_playtime_records WHERE member_id = ? AND play_date = ?";
            $check_stmt = $pdo->prepare($check_sql);
            $check_stmt->execute([$member_id, $date]);
            
            if ($check_stmt->rowCount() == 0) {
                // 插入新記錄
                $insert_sql = "INSERT INTO daily_playtime_records (member_id, play_date, total_playtime_seconds) VALUES (?, ?, ?)";
                $insert_stmt = $pdo->prepare($insert_sql);
                $insert_stmt->execute([$member_id, $date, $playtime_seconds]);
                
                $hours = floor($playtime_seconds / 3600);
                $minutes = floor(($playtime_seconds % 3600) / 60);
                echo "✅ 會員 {$member_id} 在 {$date} 遊玩了 {$hours}小時{$minutes}分鐘\n";
            } else {
                echo "ℹ️ 會員 {$member_id} 在 {$date} 已有記錄\n";
            }
        }
    }
    
    echo "\n✅ 每日遊玩時間數據初始化完成！\n";
    
    // 顯示統計
    $stats_sql = "SELECT COUNT(*) as total_records FROM daily_playtime_records";
    $stats_stmt = $pdo->query($stats_sql);
    $stats = $stats_stmt->fetch();
    echo "總記錄數：{$stats['total_records']}\n";
    
} catch (Exception $e) {
    echo "錯誤：" . $e->getMessage() . "\n";
}
?> 