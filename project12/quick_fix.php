<?php
session_start();
require_once 'db.php';

echo "<h1>🔧 快速修復</h1>";

if (!isset($_SESSION['member_id'])) {
    echo "<p>請先登入</p>";
    exit;
}

$member_id = $_SESSION['member_id'];

try {
    // 檢查是否有記錄
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM game_records WHERE member_id = ?");
    $stmt->execute([$member_id]);
    $game_count = $stmt->fetch()['count'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM daily_playtime_records WHERE member_id = ?");
    $stmt->execute([$member_id]);
    $playtime_count = $stmt->fetch()['count'];
    
    if ($game_count == 0 && $playtime_count == 0) {
        echo "<h2>自動生成測試數據</h2>";
        
        // 生成一些遊戲記錄
        $games = [
            ['game_id' => 1, 'game_type' => '反應力'],
            ['game_id' => 2, 'game_type' => '記憶力'],
            ['game_id' => 3, 'game_type' => '邏輯力'],
            ['game_id' => 4, 'game_type' => '2048'],
            ['game_id' => 5, 'game_type' => '節奏遊戲']
        ];
        
        // 為最近3個月生成數據
        for ($month_offset = 0; $month_offset < 3; $month_offset++) {
            $date = date('Y-m', strtotime("-$month_offset months"));
            $year = date('Y', strtotime("-$month_offset months"));
            $month = date('m', strtotime("-$month_offset months"));
            
            echo "<p>生成 $year 年 $month 月數據...</p>";
            
            // 生成每日遊玩時間
            $days_in_month = date('t', strtotime("$date-01"));
            for ($day = 1; $day <= $days_in_month; $day++) {
                $play_date = sprintf('%s-%02d', $date, $day);
                
                // 跳過未來日期
                if ($play_date > date('Y-m-d')) continue;
                
                $playtime_seconds = rand(1800, 14400); // 30分鐘到4小時
                
                $insert_sql = "INSERT IGNORE INTO daily_playtime_records (member_id, play_date, total_playtime_seconds) VALUES (?, ?, ?)";
                $insert_stmt = $pdo->prepare($insert_sql);
                $insert_stmt->execute([$member_id, $play_date, $playtime_seconds]);
            }
            
            // 生成遊戲記錄
            for ($i = 0; $i < 5; $i++) {
                $random_day = rand(1, $days_in_month);
                $play_date = sprintf('%s-%02d', $date, $random_day);
                $random_game = $games[array_rand($games)];
                $random_score = rand(50, 500);
                $random_time = rand(300, 1800);
                
                $insert_sql = "INSERT IGNORE INTO game_records (member_id, game_id, score, difficulty, play_date, play_time, game_type, is_single_player) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $insert_stmt = $pdo->prepare($insert_sql);
                $insert_stmt->execute([
                    $member_id,
                    $random_game['game_id'],
                    $random_score,
                    'normal',
                    $play_date,
                    $random_time,
                    $random_game['game_type'],
                    1
                ]);
            }
        }
        
        echo "<p style='color: green;'>✅ 測試數據生成完成！</p>";
    } else {
        echo "<p>已有數據，無需生成</p>";
    }
    
    // 顯示當前數據統計
    echo "<h2>當前數據統計</h2>";
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM game_records WHERE member_id = ?");
    $stmt->execute([$member_id]);
    $game_count = $stmt->fetch()['count'];
    echo "<p>遊戲記錄：$game_count 筆</p>";
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM daily_playtime_records WHERE member_id = ?");
    $stmt->execute([$member_id]);
    $playtime_count = $stmt->fetch()['count'];
    echo "<p>遊玩時間記錄：$playtime_count 筆</p>";
    
    echo "<h2>下一步</h2>";
    echo "<p><a href='history.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>查看歷史紀錄</a></p>";
    echo "<p><a href='check_database.php'>檢查資料庫</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ 錯誤：" . $e->getMessage() . "</p>";
}
?> 