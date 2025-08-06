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
        LIMIT 3
    ";
    
    $popular_games_stmt = $pdo->prepare($popular_games_sql);
    $popular_games_stmt->execute();
    $popular_games = $popular_games_stmt->fetchAll();
    
    // 遊戲對應的圖片和連結
    $game_mappings = [
        '2048' => ['img' => 'img/2048.png', 'link' => '2048ht.php', 'title' => '2048'],
        '記憶力' => ['img' => 'img/card.jpg', 'link' => 'Memory-Game.php', 'title' => '翻牌對對樂'],
        '追蹤犯人遊戲' => ['img' => 'img/prisoner.jpg', 'link' => 'prisoner.php', 'title' => '追蹤犯人'],
        '節奏遊戲' => ['img' => 'img/rhythm.jpg', 'link' => 'rhythm_game.php', 'title' => '節奏遊戲'],
        '反應力' => ['img' => 'img/egg.jpg', 'link' => 'Catch-Egg Game.php', 'title' => '接金蛋'],
        '看字選色遊戲' => ['img' => 'img/color.jpg', 'link' => 'text-color.php', 'title' => '看字選色'],
        '算數邏輯力' => ['img' => 'img/vegetable.jpg', 'link' => 'Vegetable-Cost.php', 'title' => '蔬菜成本'],
        '邏輯力' => ['img' => 'img/2048.png', 'link' => '2048ht.php', 'title' => '2048']
    ];
    
    $formatted_games = [];
    
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
                'game_type' => '追蹤犯人遊戲',
                'title' => '追蹤犯人',
                'img' => 'img/prisoner.jpg',
                'link' => 'prisoner.php',
                'total_plays' => 0,
                'unique_players' => 0,
                'avg_score' => 0,
                'last_played' => null
            ]
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $formatted_games
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => '獲取熱門遊戲失敗：' . $e->getMessage()
    ]);
}
?> 