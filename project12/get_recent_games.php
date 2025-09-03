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
    // 獲取用戶最近遊玩的遊戲（按時間排序，最多3個）
    // 合併相同類型的遊戲記錄，避免重複顯示
    $recent_games_sql = "
        SELECT 
            CASE 
                WHEN gr.game_type IN ('記憶力', '翻牌對對樂') THEN '記憶力'
                WHEN gr.game_type IN ('邏輯力', '2048') THEN '2048'
                WHEN gr.game_type IN ('反應力', '節奏遊戲', '看字選色遊戲', '接金蛋遊戲') THEN gr.game_type
                WHEN gr.game_type IN ('算數邏輯力', '算術邏輯', '算菜錢遊戲') THEN '算數邏輯力'
                ELSE gr.game_type
            END as normalized_game_type,
            SUM(gr.score) as total_score,
            SUM(gr.play_time) as total_play_time,
            MAX(gr.play_date) as last_played,
            COUNT(*) as play_count
        FROM game_records gr
        WHERE gr.member_id = ?
        GROUP BY normalized_game_type
        ORDER BY MAX(gr.play_date) DESC
        LIMIT 3
    ";
    
    $recent_games_stmt = $pdo->prepare($recent_games_sql);
    $recent_games_stmt->execute([$member_id]);
    $recent_games = $recent_games_stmt->fetchAll();
    
    // 遊戲對應的圖片和連結
    $game_mappings = [
        '2048' => ['img' => 'img/game_20481.png?v=2', 'link' => '2048ht.php', 'title' => '2048'],
        '記憶力' => ['img' => 'img/card1.png?v=2', 'link' => 'Memory-Game.php', 'title' => '翻牌對對樂'],
        '追蹤犯人遊戲' => ['img' => 'img/prisoner1.png?v=2', 'link' => 'prisoner.php', 'title' => '追蹤犯人'],
        '節奏遊戲' => ['img' => 'img/rhythm1.png?v=2', 'link' => 'rhythm_game.php', 'title' => '節奏遊戲'],
        '反應力' => ['img' => 'img/egg1.png?v=2', 'link' => 'Catch-Egg Game.php', 'title' => '接金蛋'],
        '看字選色遊戲' => ['img' => 'img/text_color111.png?v=2', 'link' => 'text-color.php', 'title' => '看字選色'],
        '算數邏輯力' => ['img' => 'img/vegetable1.png?v=2', 'link' => 'Vegetable-Cost.php', 'title' => '算菜錢'],
        '圖片線索問答' => ['img' => 'img/clue11.png?v=2', 'link' => 'clue.php', 'title' => '圖片線索問答'],
        '過河遊戲' => ['img' => 'img/river1.png?v=2', 'link' => 'river.index.php', 'title' => '過河遊戲']
    ];
    
    $formatted_games = [];
    
    foreach ($recent_games as $game) {
        $game_type = $game['normalized_game_type'];
        
        // 查找對應的遊戲資訊
        $game_info = null;
        if (isset($game_mappings[$game_type])) {
            $game_info = $game_mappings[$game_type];
        }
        
        // 如果找不到對應，使用預設值
        if (!$game_info) {
            $game_info = [
                'img' => 'img/game_20481.png?v=2',
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
                'img' => 'img/egg1.png?v=2',
                'link' => 'Catch-Egg Game.php',
                'play_count' => 0,
                'last_played' => null,
                'avg_score' => 0
            ],
            [
                'game_type' => '節奏遊戲',
                'title' => '節奏遊戲',
                'img' => 'img/rhythm1.png?v=2',
                'link' => 'rhythm_game.php',
                'play_count' => 0,
                'last_played' => null,
                'avg_score' => 0
            ],
            [
                'game_type' => '看字選色遊戲',
                'title' => '看字選色',
                'img' => 'img/text_color111.png?v=2',
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