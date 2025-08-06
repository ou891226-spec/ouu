<?php
// 抑制錯誤輸出到瀏覽器
ini_set('display_errors', 0);
error_reporting(E_ALL);

session_start();

try {
    require_once 'db.php';
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => '資料庫連線失敗：' . $e->getMessage()
    ]);
    exit;
}

header('Content-Type: application/json');

// 添加除錯資訊
$debug_info = [
    'session_id' => session_id(),
    'member_id' => $_SESSION['member_id'] ?? 'null',
    'account' => $_SESSION['account'] ?? 'null',
    'name' => $_SESSION['name'] ?? 'null',
    'all_session' => $_SESSION
];

$member_id = $_SESSION['member_id'] ?? null;

if (!$member_id) {
    echo json_encode([
        'success' => false, 
        'message' => '尚未登入',
        'debug' => $debug_info
    ]);
    exit;
}

try {
    // 獲取指定的年份和月份，如果沒有指定則使用當前月份
    $year = $_GET['year'] ?? date('Y');
    $month = $_GET['month'] ?? date('m');
    $category = $_GET['category'] ?? 'all'; // 新增：類別篩選
    
    // 格式化月份為兩位數
    $month = str_pad($month, 2, '0', STR_PAD_LEFT);
    
    // 構建年月字串
    $year_month = $year . '-' . $month;
    $month_start = $year_month . '-01';
    $month_end = date('Y-m-t', strtotime($month_start)); // 該月最後一天
    
    // 根據類別設定 game_type 條件
    $category_condition = '';
    $category_params = [];
    
    switch ($category) {
        case 'reaction':
            $category_condition = "AND gr.game_type IN ('反應力', '節奏遊戲', '看字選色遊戲', '接金蛋遊戲')";
            break;
        case 'memory':
            $category_condition = "AND gr.game_type IN ('記憶力', '翻牌對對樂', '追蹤犯人遊戲', '圖片線索問答')";
            break;
        case 'logic':
            $category_condition = "AND gr.game_type IN ('算術邏輯', '2048', '算菜錢遊戲', '邏輯力')";
            break;
        default:
            $category_condition = '';
            break;
    }
    
    // 統計本月遊玩次數（從game_records表）
    $play_count_sql = "
        SELECT COUNT(*) as play_count 
        FROM game_records gr
        WHERE gr.member_id = ? 
        AND DATE(gr.play_date) BETWEEN ? AND ?
        $category_condition
    ";
    $play_count_stmt = $pdo->prepare($play_count_sql);
    $play_count_stmt->execute([$member_id, $month_start, $month_end]);
    $play_count_result = $play_count_stmt->fetch();
    $play_count = $play_count_result['play_count'];
    
    // 統計本月遊玩時間（從game_records表計算）
    $play_time_sql = "
        SELECT SUM(gr.play_time) as total_seconds 
        FROM game_records gr
        WHERE gr.member_id = ? 
        AND DATE(gr.play_date) BETWEEN ? AND ?
        $category_condition
    ";
    $play_time_stmt = $pdo->prepare($play_time_sql);
    $play_time_stmt->execute([$member_id, $month_start, $month_end]);
    $play_time_result = $play_time_stmt->fetch();
    $total_seconds = $play_time_result['total_seconds'] ?? 0;
    
    // 格式化時間
    $hours = floor($total_seconds / 3600);
    $minutes = floor(($total_seconds % 3600) / 60);
    $formatted_time = sprintf('%02d:%02d', $hours, $minutes);
    
            // 獲取本月每日遊玩記錄（按類別篩選）
        $daily_records_sql = "
            SELECT DATE(gr.play_date) as play_date, 
                   SUM(gr.play_time) as total_playtime_seconds,
                   COUNT(*) as game_count,
                   GROUP_CONCAT(DISTINCT gr.game_type ORDER BY gr.game_type SEPARATOR ', ') as game_types,
                   GROUP_CONCAT(DISTINCT gr.game_id ORDER BY gr.game_id SEPARATOR ', ') as game_ids,
                   GROUP_CONCAT(DISTINCT g.game_name ORDER BY g.game_name SEPARATOR ', ') as game_names
            FROM game_records gr
            LEFT JOIN games g ON gr.game_id = g.game_id
            WHERE gr.member_id = ? 
            AND DATE(gr.play_date) BETWEEN ? AND ?
            $category_condition
            GROUP BY DATE(gr.play_date)
            ORDER BY play_date DESC
        ";
    $daily_records_stmt = $pdo->prepare($daily_records_sql);
    $daily_records_stmt->execute([$member_id, $month_start, $month_end]);
    $daily_records = $daily_records_stmt->fetchAll();
    
    // 計算最佳表現遊戲（基於平均分數）
    $best_game_sql = "
        SELECT g.game_name, 
               AVG(gr.score) as avg_score,
               COUNT(*) as play_count,
               SUM(gr.score) as total_score
        FROM game_records gr
        LEFT JOIN games g ON gr.game_id = g.game_id
        WHERE gr.member_id = ? 
        AND DATE(gr.play_date) BETWEEN ? AND ?
        $category_condition
        AND gr.score > 0
        GROUP BY gr.game_id, g.game_name
        ORDER BY avg_score DESC, play_count DESC
        LIMIT 1
    ";
    $best_game_stmt = $pdo->prepare($best_game_sql);
    $best_game_stmt->execute([$member_id, $month_start, $month_end]);
    $best_game_result = $best_game_stmt->fetch();
    
    $best_game = '無數據';
    if ($best_game_result) {
        $best_game = $best_game_result['game_name'];
    }
    
    // 計算遊戲類型數量
    $game_types_sql = "
        SELECT COUNT(DISTINCT gr.game_type) as game_types_count
        FROM game_records gr
        WHERE gr.member_id = ? 
        AND DATE(gr.play_date) BETWEEN ? AND ?
    ";
    $game_types_stmt = $pdo->prepare($game_types_sql);
    $game_types_stmt->execute([$member_id, $month_start, $month_end]);
    $game_types_result = $game_types_stmt->fetch();
    $game_types_count = $game_types_result['game_types_count'] ?? 0;
    
    // 計算最高分數
    $best_score_sql = "
        SELECT MAX(gr.score) as best_score
        FROM game_records gr
        WHERE gr.member_id = ? 
        AND DATE(gr.play_date) BETWEEN ? AND ?
        AND gr.score > 0
    ";
    $best_score_stmt = $pdo->prepare($best_score_sql);
    $best_score_stmt->execute([$member_id, $month_start, $month_end]);
    $best_score_result = $best_score_stmt->fetch();
    $best_score = $best_score_result['best_score'] ?? 0;
    
    // 計算最常玩的遊戲
    $favorite_game_sql = "
        SELECT g.game_name, COUNT(*) as play_count
        FROM game_records gr
        LEFT JOIN games g ON gr.game_id = g.game_id
        WHERE gr.member_id = ? 
        AND DATE(gr.play_date) BETWEEN ? AND ?
        GROUP BY gr.game_id, g.game_name
        ORDER BY play_count DESC
        LIMIT 1
    ";
    $favorite_game_stmt = $pdo->prepare($favorite_game_sql);
    $favorite_game_stmt->execute([$member_id, $month_start, $month_end]);
    $favorite_game_result = $favorite_game_stmt->fetch();
    $favorite_game = $favorite_game_result ? $favorite_game_result['game_name'] : '無數據';
    
    // 計算遊戲風格（基於最常玩的遊戲類型）
    $game_style_sql = "
        SELECT gr.game_type, COUNT(*) as type_count
        FROM game_records gr
        WHERE gr.member_id = ? 
        AND DATE(gr.play_date) BETWEEN ? AND ?
        GROUP BY gr.game_type
        ORDER BY type_count DESC
        LIMIT 1
    ";
    $game_style_stmt = $pdo->prepare($game_style_sql);
    $game_style_stmt->execute([$member_id, $month_start, $month_end]);
    $game_style_result = $game_style_stmt->fetch();
    $game_style = '無數據';
    if ($game_style_result) {
        switch ($game_style_result['game_type']) {
            case '記憶力':
            case '翻牌對對樂':
            case '追蹤犯人遊戲':
                $game_style = '記憶型玩家';
                break;
            case '反應力':
            case '節奏遊戲':
            case '看字選色遊戲':
            case '接金蛋遊戲':
                $game_style = '反應型玩家';
                break;
            case '邏輯力':
            case '2048':
            case '算菜錢遊戲':
                $game_style = '邏輯型玩家';
                break;
            default:
                $game_style = '全能型玩家';
        }
    }
    
    // 計算成就數量（從成就表獲取）
    $achievement_count = 0;
    try {
        $achievement_count_sql = "
            SELECT COUNT(*) as achievement_count
            FROM user_achievements ua
            WHERE ua.member_id = ?
        ";
        $achievement_count_stmt = $pdo->prepare($achievement_count_sql);
        $achievement_count_stmt->execute([$member_id]);
        $achievement_count_result = $achievement_count_stmt->fetch();
        $achievement_count = $achievement_count_result['achievement_count'] ?? 0;
    } catch (Exception $e) {
        // 如果成就表不存在，設為0
        error_log("成就表查詢失敗：" . $e->getMessage());
        $achievement_count = 0;
    }
    
    // 計算登入天數（本月有遊戲記錄的天數）
    $login_days_sql = "
        SELECT COUNT(DISTINCT DATE(gr.play_date)) as login_days
        FROM game_records gr
        WHERE gr.member_id = ? 
        AND DATE(gr.play_date) BETWEEN ? AND ?
    ";
    $login_days_stmt = $pdo->prepare($login_days_sql);
    $login_days_stmt->execute([$member_id, $month_start, $month_end]);
    $login_days_result = $login_days_stmt->fetch();
    $login_days = $login_days_result['login_days'] ?? 0;
    
    // 計算進步幅度（基於本月與上月的平均分數比較）
    $improvement_rate = '0%';
    $current_month_avg_sql = "
        SELECT AVG(gr.score) as avg_score
        FROM game_records gr
        WHERE gr.member_id = ? 
        AND DATE(gr.play_date) BETWEEN ? AND ?
        AND gr.score > 0
    ";
    $current_month_stmt = $pdo->prepare($current_month_avg_sql);
    $current_month_stmt->execute([$member_id, $month_start, $month_end]);
    $current_month_result = $current_month_stmt->fetch();
    $current_month_avg = $current_month_result['avg_score'] ?? 0;
    
    if ($current_month_avg > 0) {
        // 計算上月的平均分數
        $last_month_start = date('Y-m-01', strtotime($month_start . ' -1 month'));
        $last_month_end = date('Y-m-t', strtotime($last_month_start));
        
        $last_month_stmt = $pdo->prepare($current_month_avg_sql);
        $last_month_stmt->execute([$member_id, $last_month_start, $last_month_end]);
        $last_month_result = $last_month_stmt->fetch();
        $last_month_avg = $last_month_result['avg_score'] ?? 0;
        
        if ($last_month_avg > 0) {
            $improvement = (($current_month_avg - $last_month_avg) / $last_month_avg) * 100;
            $improvement_rate = sprintf('%+.1f%%', $improvement);
        } else {
            $improvement_rate = '+100%';
        }
    }
    
    // 格式化每日記錄
    $formatted_records = [];
    foreach ($daily_records as $record) {
        $hours = floor($record['total_playtime_seconds'] / 3600);
        $minutes = floor(($record['total_playtime_seconds'] % 3600) / 60);
        
        // 處理遊戲類型顯示
        $game_types = $record['game_types'] ?? '';
        $game_types_array = array_filter(array_map('trim', explode(',', $game_types)));
        
        // 處理遊戲名稱
        $game_names = $record['game_names'] ?? '';
        $game_names_array = array_filter(array_map('trim', explode(',', $game_names)));
        
        $formatted_records[] = [
            'date' => $record['play_date'],
            'playtime' => sprintf('%02d:%02d', $hours, $minutes),
            'seconds' => $record['total_playtime_seconds'],
            'game_count' => $record['game_count'],
            'game_types' => $game_types_array,
            'game_names' => $game_names_array
        ];
    }
    
    echo json_encode([
        'success' => true,
        'year' => $year,
        'month' => $month,
        'year_month' => $year_month,
        'category' => $category,
        'play_count' => $play_count,
        'total_playtime' => $formatted_time,
        'total_seconds' => $total_seconds,
        'best_game' => $best_game,
        'game_types_count' => $game_types_count,
        'best_score' => $best_score,
        'login_streak' => $login_days,
        'favorite_game' => $favorite_game,
        'game_style' => $game_style,
        'achievement_count' => $achievement_count,
        'improvement_rate' => $improvement_rate,
        'daily_records' => $formatted_records
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => '獲取統計失敗：' . $e->getMessage(),
        'debug' => [
            'member_id' => $member_id,
            'month_start' => $month_start,
            'month_end' => $month_end,
            'category' => $category
        ]
    ]);
}
?> 