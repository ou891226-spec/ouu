<?php
// 確保沒有輸出緩衝
if (ob_get_level()) ob_end_clean();

session_start();
require_once 'db.php';

header('Content-Type: application/json');

// 抑制錯誤輸出
ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
    // 獲取所有用戶的遊戲統計（按遊玩次數排序）
    $popular_games_sql = "
        SELECT 
            gr.game_type,
            COUNT(*) as total_plays,
            COUNT(DISTINCT gr.member_id) as unique_players,
            AVG(gr.score) as avg_score,
            MAX(gr.play_date) as last_played
        FROM game_records gr
        GROUP BY gr.game_type
        ORDER BY total_plays DESC, unique_players DESC
    ";
    
    // 也獲取所有遊戲類型，包括沒有遊玩記錄的
    $all_games_sql = "
        SELECT DISTINCT game_type FROM game_records
        UNION
        SELECT '2048' as game_type
        UNION
        SELECT '記憶力' as game_type
        UNION
        SELECT '反應力' as game_type
        UNION
        SELECT '邏輯力' as game_type
        UNION
        SELECT '節奏遊戲' as game_type
        UNION
        SELECT '看字選色遊戲' as game_type
        UNION
        SELECT '算數邏輯力' as game_type
        UNION
        SELECT '追蹤犯人遊戲' as game_type
        UNION
        SELECT '接金蛋遊戲' as game_type
    ";
    
    $popular_games_stmt = $pdo->prepare($popular_games_sql);
    $popular_games_stmt->execute();
    $popular_games = $popular_games_stmt->fetchAll();
    
    $all_games_stmt = $pdo->prepare($all_games_sql);
    $all_games_stmt->execute();
    $all_games = $all_games_stmt->fetchAll();
    
    // 遊戲對應的圖片和連結
    $game_mappings = [
        '2048' => ['img' => 'img/2048.png', 'link' => '2048ht.php', 'title' => '2048'],
        '邏輯力' => ['img' => 'img/2048.png', 'link' => '2048ht.php', 'title' => '2048'],
        '算數邏輯力' => ['img' => 'img/2048.png', 'link' => '2048ht.php', 'title' => '2048'],
        '算菜錢遊戲' => ['img' => 'img/vegetable.jpg', 'link' => 'Vegetable-Cost.php', 'title' => '算菜錢'],
        '過河遊戲' => ['img' => 'img/2048.png', 'link' => '2048ht.php', 'title' => '過河遊戲'],
        '記憶力' => ['img' => 'img/card.jpg', 'link' => 'Memory-Game.php', 'title' => '翻牌對對樂'],
        '翻牌對對樂' => ['img' => 'img/card.jpg', 'link' => 'Memory-Game.php', 'title' => '翻牌對對樂'],
        '追蹤犯人遊戲' => ['img' => 'img/prisoner.jpg', 'link' => 'prisoner.php', 'title' => '追蹤犯人'],
        '圖片線索問答' => ['img' => 'img/card.jpg', 'link' => 'Memory-Game.php', 'title' => '圖片線索問答'],
        '反應力' => ['img' => 'img/egg.jpg', 'link' => 'Catch-Egg Game.php', 'title' => '接金蛋'],
        '接金蛋遊戲' => ['img' => 'img/egg.jpg', 'link' => 'Catch-Egg Game.php', 'title' => '接金蛋'],
        '接金蛋' => ['img' => 'img/egg.jpg', 'link' => 'Catch-Egg Game.php', 'title' => '接金蛋'],
        'Catch-Egg Game' => ['img' => 'img/egg.jpg', 'link' => 'Catch-Egg Game.php', 'title' => '接金蛋'],
        '節奏遊戲' => ['img' => 'img/rhythm.jpg', 'link' => 'rhythm_game.php', 'title' => '節奏遊戲'],
        '看字選色遊戲' => ['img' => 'img/color.jpg', 'link' => 'text-color.php', 'title' => '看字選色']
    ];
    
    $formatted_games = [];
    $used_titles = []; // 用來追蹤已經使用的遊戲標題
    
    foreach ($popular_games as $game) {
        $game_type = $game['game_type'];
        
        // 查找對應的遊戲資訊
        $game_info = null;
        foreach ($game_mappings as $key => $info) {
            if (strpos($game_type, $key) !== false || strpos($key, $game_type) !== false) {
                $game_info = $info;
                break;
            }
        }
        
        // 如果找不到對應，使用預設值
        if (!$game_info) {
            $game_info = [
                'img' => 'img/game_2048.png',
                'link' => 'game-category.php',
                'title' => $game_type
            ];
        }
        
        // 檢查是否已經有相同的遊戲標題，避免重複
        if (!in_array($game_info['title'], $used_titles)) {
            $used_titles[] = $game_info['title'];
            
            $formatted_games[] = [
                'game_type' => $game_type,
                'title' => $game_info['title'],
                'img' => $game_info['img'],
                'link' => $game_info['link'],
                'total_plays' => $game['total_plays'],
                'unique_players' => $game['unique_players'],
                'avg_score' => round($game['avg_score'], 0),
                'last_played' => $game['last_played']
            ];
            
            // 如果已經有3個遊戲，就停止添加（只顯示前3個作為熱門遊戲）
            if (count($formatted_games) >= 3) {
                break;
            }
        }
    }
    
    // 如果沒有遊戲記錄，返回預設遊戲
    if (empty($formatted_games)) {
        $formatted_games = [
            [
                'game_type' => '2048',
                'title' => '2048',
                'img' => 'img/2048.png',
                'link' => '2048ht.php',
                'total_plays' => 0,
                'unique_players' => 0,
                'avg_score' => 0,
                'last_played' => null
            ],
            [
                'game_type' => '記憶力',
                'title' => '翻牌對對樂',
                'img' => 'img/card.jpg',
                'link' => 'Memory-Game.php',
                'total_plays' => 0,
                'unique_players' => 0,
                'avg_score' => 0,
                'last_played' => null
            ],
            [
                'game_type' => '接金蛋遊戲',
                'title' => '接金蛋',
                'img' => 'img/egg.jpg',
                'link' => 'Catch-Egg Game.php',
                'total_plays' => 0,
                'unique_players' => 0,
                'avg_score' => 0,
                'last_played' => null
            ]
        ];
    }
    
    // 創建所有遊戲的完整統計
    $all_games_stats = [];
    
    foreach ($all_games as $game) {
        $game_type = $game['game_type'];
        
        // 查找對應的遊戲資訊
        $game_info = null;
        foreach ($game_mappings as $key => $info) {
            if (strpos($game_type, $key) !== false || strpos($key, $game_type) !== false) {
                $game_info = $info;
                break;
            }
        }
        
        // 如果找不到對應，使用預設值
        if (!$game_info) {
            $game_info = [
                'img' => 'img/game_2048.png',
                'link' => 'game-category.php',
                'title' => $game_type
            ];
        }
        
        // 查找遊玩統計
        $stats = null;
        foreach ($popular_games as $stat) {
            if ($stat['game_type'] === $game_type) {
                $stats = $stat;
                break;
            }
        }
        
        $all_games_stats[] = [
            'game_type' => $game_type,
            'title' => $game_info['title'],
            'img' => $game_info['img'],
            'link' => $game_info['link'],
            'total_plays' => $stats ? $stats['total_plays'] : 0,
            'unique_players' => $stats ? $stats['unique_players'] : 0,
            'avg_score' => $stats ? round($stats['avg_score'], 0) : 0,
            'last_played' => $stats ? $stats['last_played'] : null
        ];
    }
    
    // 按遊玩次數排序
    usort($all_games_stats, function($a, $b) {
        return $b['total_plays'] - $a['total_plays'];
    });
    
    echo json_encode([
        'success' => true,
        'data' => $formatted_games,
        'all_games' => $all_games_stats
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => '獲取熱門遊戲失敗：' . $e->getMessage()
    ]);
}
?> 