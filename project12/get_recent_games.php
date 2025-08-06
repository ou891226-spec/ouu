<?php
// 確保沒有輸出緩衝
if (ob_get_level()) ob_end_clean();

session_start();
require_once 'db.php';

header('Content-Type: application/json');

// 抑制錯誤輸出
ini_set('display_errors', 0);
error_reporting(E_ALL);

$member_id = $_SESSION['member_id'] ?? null;

if (!$member_id) {
    echo json_encode([
        'success' => false,
        'message' => '尚未登入'
    ]);
    exit;
}

try {
    // 獲取用戶最近遊玩的遊戲（按時間排序，最多6個）
    $recent_games_sql = "
        SELECT 
            gr.game_type,
            SUM(gr.score) as total_score,
            SUM(gr.play_time) as total_play_time,
            MAX(gr.play_date) as last_played,
            COUNT(*) as play_count
        FROM game_records gr
        WHERE gr.member_id = ?
        GROUP BY gr.game_type
        ORDER BY MAX(gr.play_date) DESC
        LIMIT 6
    ";
    
    $recent_games_stmt = $pdo->prepare($recent_games_sql);
    $recent_games_stmt->execute([$member_id]);
    $recent_games = $recent_games_stmt->fetchAll();
    
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
    
    foreach ($recent_games as $game) {
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
            'play_count' => $game['play_count'],
            'last_played' => $game['last_played'],
            'avg_score' => round($game['total_score'] / $game['play_count'], 0)
        ];
    }
    
    // 如果沒有遊戲記錄，返回預設遊戲
    if (empty($formatted_games)) {
        $formatted_games = [
            [
                'game_type' => '接金蛋遊戲',
                'title' => '接金蛋',
                'img' => 'img/egg.jpg',
                'link' => 'Catch-Egg Game.php',
                'play_count' => 0,
                'last_played' => null,
                'avg_score' => 0
            ],
            [
                'game_type' => '節奏遊戲',
                'title' => '節奏遊戲',
                'img' => 'img/rhythm.jpg',
                'link' => 'rhythm_game.php',
                'play_count' => 0,
                'last_played' => null,
                'avg_score' => 0
            ],
            [
                'game_type' => '看字選色遊戲',
                'title' => '看字選色',
                'img' => 'img/color.jpg',
                'link' => 'text-color.php',
                'play_count' => 0,
                'last_played' => null,
                'avg_score' => 0
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
        'message' => '獲取最近遊戲失敗：' . $e->getMessage()
    ]);
}
?> 