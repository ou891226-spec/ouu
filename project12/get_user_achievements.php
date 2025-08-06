<?php
require_once 'db.php';
session_start();

header('Content-Type: application/json');

$member_id = $_SESSION['member_id'] ?? 0;

if (!$member_id) {
    echo json_encode(['success' => false, 'message' => '未登入']);
    exit;
}

try {
    // 獲取用戶已獲得的成就稱號
    $sql = "SELECT a.achievement_name, a.achievement_description, a.icon, ma.earned_date
            FROM member_achievements ma
            JOIN achievements a ON ma.achievement_id = a.achievement_id
            WHERE ma.member_id = ?
            ORDER BY ma.earned_date DESC
            LIMIT 10";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$member_id]);
    $achievements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
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