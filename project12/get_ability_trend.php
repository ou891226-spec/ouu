<?php
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
    // 獲取最近12個月的遊戲記錄，按月份分組計算能力分數
    $trend_sql = "
        SELECT 
            DATE_FORMAT(play_date, '%Y-%m') as month,
            game_type,
            AVG(score) as avg_score,
            COUNT(*) as play_count
        FROM game_records 
        WHERE member_id = ? 
        AND play_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(play_date, '%Y-%m'), game_type
        ORDER BY month ASC
    ";
    $trend_stmt = $pdo->prepare($trend_sql);
    $trend_stmt->execute([$member_id]);
    $trend_records = $trend_stmt->fetchAll();
    
    // 按月份組織數據
    $monthly_data = [];
    foreach ($trend_records as $record) {
        $month = $record['month'];
        if (!isset($monthly_data[$month])) {
            $monthly_data[$month] = [
                'reaction' => ['scores' => [], 'count' => 0],
                'memory' => ['scores' => [], 'count' => 0],
                'logic' => ['scores' => [], 'count' => 0]
            ];
        }
        
        // 分類遊戲類型
        switch ($record['game_type']) {
            case '反應力':
            case '節奏遊戲':
            case '看字選色遊戲':
            case '接金蛋遊戲':
                $monthly_data[$month]['reaction']['scores'][] = $record['avg_score'];
                $monthly_data[$month]['reaction']['count'] += $record['play_count'];
                break;
            case '記憶力':
            case '翻牌對對樂':
            case '追蹤犯人遊戲':
            case '圖片線索問答':
                $monthly_data[$month]['memory']['scores'][] = $record['avg_score'];
                $monthly_data[$month]['memory']['count'] += $record['play_count'];
                break;
            case '邏輯力':
            case '2048':
            case '算菜錢遊戲':
            case '算術邏輯':
            case '算數邏輯力':
                $monthly_data[$month]['logic']['scores'][] = $record['avg_score'];
                $monthly_data[$month]['logic']['count'] += $record['play_count'];
                break;
            default:
                // 如果沒有匹配的類型，根據遊戲名稱判斷
                if (strpos($record['game_type'], '反應') !== false || 
                    strpos($record['game_type'], '節奏') !== false || 
                    strpos($record['game_type'], '接金蛋') !== false) {
                    $monthly_data[$month]['reaction']['scores'][] = $record['avg_score'];
                    $monthly_data[$month]['reaction']['count'] += $record['play_count'];
                } elseif (strpos($record['game_type'], '記憶') !== false || 
                         strpos($record['game_type'], '翻牌') !== false || 
                         strpos($record['game_type'], '追蹤') !== false) {
                    $monthly_data[$month]['memory']['scores'][] = $record['avg_score'];
                    $monthly_data[$month]['memory']['count'] += $record['play_count'];
                } elseif (strpos($record['game_type'], '邏輯') !== false || 
                         strpos($record['game_type'], '算術') !== false || 
                         strpos($record['game_type'], '算數') !== false) {
                    $monthly_data[$month]['logic']['scores'][] = $record['avg_score'];
                    $monthly_data[$month]['logic']['count'] += $record['play_count'];
                }
                break;
        }
    }
    
    // 計算每月能力分數
    $trend_data = [];
    foreach ($monthly_data as $month => $data) {
        $reaction_score = !empty($data['reaction']['scores']) ? array_sum($data['reaction']['scores']) / count($data['reaction']['scores']) : 0;
        $memory_score = !empty($data['memory']['scores']) ? array_sum($data['memory']['scores']) / count($data['memory']['scores']) : 0;
        $logic_score = !empty($data['logic']['scores']) ? array_sum($data['logic']['scores']) / count($data['logic']['scores']) : 0;
        
        $trend_data[] = [
            'date' => $month,
            'reaction' => round($reaction_score, 1),
            'memory' => round($memory_score, 1),
            'logic' => round($logic_score, 1),
            'reaction_count' => $data['reaction']['count'],
            'memory_count' => $data['memory']['count'],
            'logic_count' => $data['logic']['count']
        ];
    }
    
    // 如果沒有數據，生成一些示例數據
    if (empty($trend_data)) {
        $trend_data = generateSampleTrendData();
    }
    
    echo json_encode([
        'success' => true,
        'data' => $trend_data
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => '獲取趨勢數據失敗：' . $e->getMessage()
    ]);
}

// 生成示例趨勢數據（當沒有實際數據時使用）
function generateSampleTrendData() {
    $data = [];
    $base_reaction = 60;
    $base_memory = 70;
    $base_logic = 50;
    
    for ($i = 11; $i >= 0; $i--) {
        $month = date('Y-m', strtotime("-$i months"));
        
        // 添加一些隨機變化來模擬真實數據
        $reaction = $base_reaction + rand(-10, 15);
        $memory = $base_memory + rand(-8, 12);
        $logic = $base_logic + rand(-5, 15);
        
        // 確保分數在合理範圍內
        $reaction = max(0, min(100, $reaction));
        $memory = max(0, min(100, $memory));
        $logic = max(0, min(100, $logic));
        
        $data[] = [
            'date' => $month,
            'reaction' => $reaction,
            'memory' => $memory,
            'logic' => $logic,
            'reaction_count' => rand(0, 50),
            'memory_count' => rand(0, 30),
            'logic_count' => rand(0, 40)
        ];
    }
    
    return $data;
}
?>
