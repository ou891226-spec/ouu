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
    // 獲取用戶的三個能力分數
    $score_sql = "SELECT reaction_score, memory_score, logic_score FROM member WHERE member_id = ?";
    $score_stmt = $pdo->prepare($score_sql);
    $score_stmt->execute([$member_id]);
    $score_result = $score_stmt->fetch();
    
    if (!$score_result) {
        echo json_encode([
            'success' => false,
            'message' => '找不到用戶數據'
        ]);
        exit;
    }
    
    // 獲取所有用戶的平均分數作為基準
    $avg_sql = "SELECT 
        AVG(reaction_score) as avg_reaction,
        AVG(memory_score) as avg_memory,
        AVG(logic_score) as avg_logic,
        MAX(reaction_score) as max_reaction,
        MAX(memory_score) as max_memory,
        MAX(logic_score) as max_logic
        FROM member 
        WHERE reaction_score > 0 OR memory_score > 0 OR logic_score > 0";
    $avg_stmt = $pdo->query($avg_sql);
    $avg_result = $avg_stmt->fetch();
    
    // 計算能力強度（基於平均分數的百分比）
    $reaction_strength = 0;
    $memory_strength = 0;
    $logic_strength = 0;
    
    if ($avg_result['avg_reaction'] > 0) {
        $reaction_strength = min(100, ($score_result['reaction_score'] / $avg_result['avg_reaction']) * 50);
    }
    
    if ($avg_result['avg_memory'] > 0) {
        $memory_strength = min(100, ($score_result['memory_score'] / $avg_result['avg_memory']) * 50);
    }
    
    if ($avg_result['avg_logic'] > 0) {
        $logic_strength = min(100, ($score_result['logic_score'] / $avg_result['avg_logic']) * 50);
    }
    
    // 獲取詳細的遊戲記錄來計算綜合分析
    $game_records_sql = "
        SELECT 
            game_type, 
            COUNT(*) as play_count, 
            AVG(CASE 
                WHEN score > 1000 THEN 100 
                WHEN score > 100 THEN (score / 10)
                ELSE score 
            END) as avg_score, 
            MAX(CASE 
                WHEN score > 1000 THEN 100 
                WHEN score > 100 THEN (score / 10)
                ELSE score 
            END) as max_score
        FROM game_records 
        WHERE member_id = ? 
        GROUP BY game_type
    ";
    $game_records_stmt = $pdo->prepare($game_records_sql);
    $game_records_stmt->execute([$member_id]);
    $game_records = $game_records_stmt->fetchAll();
    
    // 分類遊戲記錄
    $reaction_games = [];
    $memory_games = [];
    $logic_games = [];
    
    foreach ($game_records as $record) {
        switch ($record['game_type']) {
            case '反應力':
            case '節奏遊戲':
            case '看字選色遊戲':
            case '接金蛋遊戲':
                $reaction_games[] = $record;
                break;
            case '記憶力':
            case '翻牌對對樂':
            case '追蹤犯人遊戲':
            case '圖片線索問答':
                $memory_games[] = $record;
                break;
            case '邏輯力':
            case '2048':
            case '算菜錢遊戲':
            case '算術邏輯力':
                $logic_games[] = $record;
                break;
        }
    }
    
    // 計算綜合能力分數（考慮遊戲次數和平均分數）
    $reaction_comprehensive = calculateComprehensiveScore($reaction_games);
    $memory_comprehensive = calculateComprehensiveScore($memory_games);
    $logic_comprehensive = calculateComprehensiveScore($logic_games);
    
    // 計算能力等級（1-10級）
    $reaction_level = calculateAbilityLevel($reaction_comprehensive, $avg_result['avg_reaction']);
    $memory_level = calculateAbilityLevel($memory_comprehensive, $avg_result['avg_memory']);
    $logic_level = calculateAbilityLevel($logic_comprehensive, $avg_result['avg_logic']);
    
    // 生成能力分析報告
    $analysis_report = generateAnalysisReport($reaction_level, $memory_level, $logic_level);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'reaction' => round($reaction_strength, 1),
            'memory' => round($memory_strength, 1),
            'logic' => round($logic_strength, 1),
            'levels' => [
                'reaction' => $reaction_level,
                'memory' => $memory_level,
                'logic' => $logic_level
            ],
            'comprehensive' => [
                'reaction' => $reaction_comprehensive,
                'memory' => $memory_comprehensive,
                'logic' => $logic_comprehensive
            ],
            'report' => $analysis_report,
            'stats' => [
                'reaction_games' => array_sum(array_column($reaction_games, 'play_count')),
                'memory_games' => array_sum(array_column($memory_games, 'play_count')),
                'logic_games' => array_sum(array_column($logic_games, 'play_count')),
                'reaction_avg' => calculateAverageScore($reaction_games),
                'memory_avg' => calculateAverageScore($memory_games),
                'logic_avg' => calculateAverageScore($logic_games)
            ]
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => '分析失敗：' . $e->getMessage()
    ]);
}

// 計算綜合能力分數
function calculateComprehensiveScore($games) {
    if (empty($games)) return 0;
    
    $total_score = 0;
    $total_plays = 0;
    $max_score = 0;
    
    foreach ($games as $game) {
        $total_score += $game['avg_score'] * $game['play_count'];
        $total_plays += $game['play_count'];
        $max_score = max($max_score, $game['max_score']);
    }
    
    if ($total_plays == 0) return 0;
    
    $avg_score = $total_score / $total_plays;
    
    // 綜合公式：平均分數 * 遊戲次數的對數 * 最高分數權重
    return $avg_score * log($total_plays + 1) * (1 + $max_score / 1000);
}

// 計算平均分數
function calculateAverageScore($games) {
    if (empty($games)) return 0;
    
    $total_score = 0;
    $total_plays = 0;
    
    foreach ($games as $game) {
        // 確保單個遊戲的平均分數不會超過100
        $avg_score = min(100, $game['avg_score']);
        $total_score += $avg_score * $game['play_count'];
        $total_plays += $game['play_count'];
    }
    
    if ($total_plays == 0) return 0;
    
    $final_avg = $total_score / $total_plays;
    
    // 確保最終平均分數不會超過100
    return round(min(100, $final_avg), 1);
}

// 計算能力等級
function calculateAbilityLevel($comprehensive_score, $avg_score) {
    if ($avg_score <= 0) return 1;
    
    $ratio = $comprehensive_score / $avg_score;
    
    if ($ratio >= 2.0) return 10;
    if ($ratio >= 1.8) return 9;
    if ($ratio >= 1.6) return 8;
    if ($ratio >= 1.4) return 7;
    if ($ratio >= 1.2) return 6;
    if ($ratio >= 1.0) return 5;
    if ($ratio >= 0.8) return 4;
    if ($ratio >= 0.6) return 3;
    if ($ratio >= 0.4) return 2;
    return 1;
}

// 生成分析報告
function generateAnalysisReport($reaction_level, $memory_level, $logic_level) {
    $levels = [$reaction_level, $memory_level, $logic_level];
    $max_level = max($levels);
    $min_level = min($levels);
    $avg_level = array_sum($levels) / 3;
    
    // 計算有數據的能力數量（等級大於1的能力）
    $active_abilities = 0;
    if ($reaction_level > 1) $active_abilities++;
    if ($memory_level > 1) $active_abilities++;
    if ($logic_level > 1) $active_abilities++;
    
    $report = [];
    
    // 判斷玩家類型（需要至少2個能力有數據才能判斷為平衡型）
    if ($active_abilities < 2) {
        // 新用戶或只玩過一種類型遊戲的用戶
        if ($reaction_level > 1) {
            $report['type'] = '反應型玩家';
            $report['description'] = '您專精於反應力遊戲，建議嘗試其他類型的遊戲來拓展能力。';
        } elseif ($memory_level > 1) {
            $report['type'] = '記憶型玩家';
            $report['description'] = '您專精於記憶力遊戲，建議嘗試其他類型的遊戲來拓展能力。';
        } elseif ($logic_level > 1) {
            $report['type'] = '邏輯型玩家';
            $report['description'] = '您專精於邏輯力遊戲，建議嘗試其他類型的遊戲來拓展能力。';
        } else {
            $report['type'] = '新手玩家';
            $report['description'] = '您剛開始遊戲之旅，建議多嘗試各種類型的遊戲來發現自己的潛力。';
        }
    } elseif ($max_level - $min_level <= 2 && $active_abilities >= 2) {
        $report['type'] = '平衡型玩家';
        $report['description'] = '您的三個能力發展均衡，是個全能型玩家！';
    } elseif ($max_level >= 8) {
        $report['type'] = '專精型玩家';
        $report['description'] = '您在某個領域表現特別突出，建議可以嘗試其他類型的遊戲來拓展能力。';
    } else {
        $report['type'] = '發展型玩家';
        $report['description'] = '您還有很大的進步空間，建議多練習各種類型的遊戲。';
    }
    
    // 能力建議
    $report['suggestions'] = [];
    
    if ($reaction_level < 5) {
        $report['suggestions'][] = '建議多玩節奏遊戲和接金蛋遊戲來提升反應力';
    }
    
    if ($memory_level < 5) {
        $report['suggestions'][] = '建議多玩翻牌對對樂和追蹤犯人遊戲來提升記憶力';
    }
    
    if ($logic_level < 5) {
        $report['suggestions'][] = '建議多玩2048和算菜錢遊戲來提升邏輯力';
    }
    
    return $report;
}
?> 