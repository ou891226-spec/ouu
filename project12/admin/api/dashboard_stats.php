<?php
session_start();
require_once '../../db.php';

// 檢查管理員權限
if (!isset($_SESSION['member_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => '權限不足']);
    exit;
}

header('Content-Type: application/json');

try {
    // 獲取基本統計數據
    $stats = [];
    
    // 總用戶數
    $user_sql = "SELECT COUNT(*) as total FROM member";
    $user_stmt = $pdo->query($user_sql);
    $stats['total_users'] = $user_stmt->fetch()['total'];
    
    // 今日遊戲次數
    $today_games_sql = "SELECT COUNT(*) as total FROM game_records WHERE DATE(play_date) = CURDATE()";
    $today_games_stmt = $pdo->query($today_games_sql);
    $stats['today_games'] = $today_games_stmt->fetch()['total'];
    
    // 今日遊玩時間
    $today_playtime_sql = "SELECT SUM(play_time) as total FROM game_records WHERE DATE(play_date) = CURDATE()";
    $today_playtime_stmt = $pdo->query($today_playtime_sql);
    $total_seconds = $today_playtime_stmt->fetch()['total'] ?? 0;
    $hours = floor($total_seconds / 3600);
    $minutes = floor(($total_seconds % 3600) / 60);
    $stats['today_playtime'] = sprintf('%02d:%02d', $hours, $minutes);
    
    // 平均分數
    $avg_score_sql = "SELECT AVG(score) as avg FROM game_records WHERE score > 0";
    $avg_score_stmt = $pdo->query($avg_score_sql);
    $avg_score = $avg_score_stmt->fetch()['avg'] ?? 0;
    $stats['avg_score'] = round($avg_score, 0);
    
    // 獲取圖表數據
    $charts = [];
    
    // 最近7天遊戲趨勢
    $trend_sql = "
        SELECT DATE(play_date) as date, COUNT(*) as count
        FROM game_records 
        WHERE play_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE(play_date)
        ORDER BY date
    ";
    $trend_stmt = $pdo->query($trend_sql);
    $trend_data = $trend_stmt->fetchAll();
    
    $trend_labels = [];
    $trend_values = [];
    
    // 填充7天的數據
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $trend_labels[] = date('m/d', strtotime($date));
        
        $found = false;
        foreach ($trend_data as $row) {
            if ($row['date'] == $date) {
                $trend_values[] = $row['count'];
                $found = true;
                break;
            }
        }
        if (!$found) {
            $trend_values[] = 0;
        }
    }
    
    $charts['trend'] = [
        'labels' => $trend_labels,
        'data' => $trend_values
    ];
    
    // 遊戲類型分布
    $type_sql = "
        SELECT game_type, COUNT(*) as count
        FROM game_records 
        WHERE play_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY game_type
        ORDER BY count DESC
        LIMIT 5
    ";
    $type_stmt = $pdo->query($type_sql);
    $type_data = $type_stmt->fetchAll();
    
    $type_labels = [];
    $type_values = [];
    
    foreach ($type_data as $row) {
        $type_labels[] = $row['game_type'];
        $type_values[] = $row['count'];
    }
    
    $charts['types'] = [
        'labels' => $type_labels,
        'data' => $type_values
    ];
    
    // 獲取最新活動
    $activities = [];
    
    // 最新遊戲記錄
    $recent_games_sql = "
        SELECT gr.*, m.member_name
        FROM game_records gr
        JOIN member m ON gr.member_id = m.member_id
        ORDER BY gr.play_date DESC
        LIMIT 5
    ";
    $recent_games_stmt = $pdo->query($recent_games_sql);
    $recent_games = $recent_games_stmt->fetchAll();
    
    foreach ($recent_games as $game) {
        $activities[] = [
            'type' => 'game',
            'title' => $game['member_name'] . ' 完成了 ' . $game['game_type'] . ' 遊戲',
            'time' => date('m/d H:i', strtotime($game['play_date']))
        ];
    }
    
    // 最新註冊用戶
    $recent_users_sql = "
        SELECT member_name, created_at
        FROM member
        ORDER BY created_at DESC
        LIMIT 3
    ";
    $recent_users_stmt = $pdo->query($recent_users_sql);
    $recent_users = $recent_users_stmt->fetchAll();
    
    foreach ($recent_users as $user) {
        $activities[] = [
            'type' => 'user',
            'title' => $user['member_name'] . ' 註冊了帳號',
            'time' => date('m/d H:i', strtotime($user['created_at']))
        ];
    }
    
    // 按時間排序活動
    usort($activities, function($a, $b) {
        return strtotime($b['time']) - strtotime($a['time']);
    });
    
    // 只取前10個活動
    $activities = array_slice($activities, 0, 10);
    
    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'charts' => $charts,
        'activities' => $activities
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => '獲取數據失敗：' . $e->getMessage()
    ]);
}
?> 