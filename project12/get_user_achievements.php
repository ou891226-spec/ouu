<?php
// 關閉錯誤顯示，避免影響JSON輸出
ini_set('display_errors', 0);
error_reporting(0);

require_once 'db.php';
session_start();

// 設定台灣時區
date_default_timezone_set('Asia/Taipei');

header('Content-Type: application/json');

$member_id = $_SESSION['member_id'] ?? null;

if (!$member_id) {
    echo json_encode(['success' => false, 'message' => '未登入']);
    exit;
}

try {
    // 獲取用戶今天獲得的成就稱號（根據台灣時間）
    $today = date('Y-m-d'); // 使用台灣時間
    $sql = "SELECT a.achievement_name, 
                   a.achievement_name as achievement_description,
                   CONCAT('img/', COALESCE(a.icon, 'Achievement.png')) as icon, 
                   ma.earned_date
            FROM member_achievements ma
            JOIN achievements a ON ma.achievement_id = a.achievement_id
            WHERE ma.member_id = ? AND DATE(ma.earned_date) = ?
            AND a.achievement_name IS NOT NULL
            AND a.achievement_name != '每日登入'
            ORDER BY ma.earned_date DESC
            LIMIT 3";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$member_id, $today]);
    $achievements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 調試：記錄查詢結果
    error_log("成就查詢結果 - 用戶ID: $member_id, 日期: $today, 成就數量: " . count($achievements));
    if (!empty($achievements)) {
        error_log("成就詳情: " . json_encode($achievements));
    }
    
    // 添加調試輸出
    if (php_sapi_name() === 'cli') {
        echo "調試信息 - 用戶ID: $member_id, 日期: $today, 成就數量: " . count($achievements) . "\n";
        if (!empty($achievements)) {
            echo "成就詳情: " . json_encode($achievements, JSON_UNESCAPED_UNICODE) . "\n";
        }
    }
    
    // 獲取用戶的遊戲統計資料
    $stats_sql = "SELECT 
                    COUNT(*) as total_games,
                    SUM(score) as total_score,
                    SUM(play_time) as total_playtime
                  FROM game_records 
                  WHERE member_id = ?";
    
    $stats_stmt = $pdo->prepare($stats_sql);
    $stats_stmt->execute([$member_id]);
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    
    // 獲取今日成就狀態
    require_once 'check_and_grant_achievements.php';
    $today_status = getTodayAchievementStatus($member_id);
    
    // 顯示用戶獲得的成就稱號
    $all_achievements = $achievements;
    
    echo json_encode([
        'success' => true,
        'achievements' => $all_achievements,
        'stats' => $stats,
        'today_status' => $today_status
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => '資料庫錯誤：' . $e->getMessage()]);
}
?> 