<?php
// 使用輸出淨化工具
require_once 'output_cleaner.php';
initCleanOutput();

session_start();
require_once 'db.php';
require_once 'admin/weighted_scoring_system.php';

$member_id = $_SESSION['member_id'] ?? null;

if (!$member_id) {
    outputCleanJson([
        'success' => false,
        'message' => '尚未登入'
    ]);
}

try {
    // 初始化加權分數系統
    $weighted_scoring = new WeightedScoringSystem($pdo);
    
    // 獲取所有基準時間設定
    $baseline_times = $weighted_scoring->getAllBaselineTimes();
    
    // 定義遊戲類型分類
    $game_categories = [
        'reaction' => ['反應力', '節奏遊戲', '看字選色遊戲', '接金蛋遊戲'],
        'memory' => ['記憶力', '翻牌對對樂', '追蹤犯人遊戲', '圖片線索問答'],
        'logic' => ['算術邏輯力', '2048', '算菜錢遊戲', '數字排排樂']
    ];
    
    // 初始化能力數據
    $ability_data = [
        'reaction' => ['total_plays' => 0, 'avg_time' => 0, 'baseline_time' => 0, 'efficiency' => 0],
        'memory' => ['total_plays' => 0, 'avg_time' => 0, 'baseline_time' => 0, 'efficiency' => 0],
        'logic' => ['total_plays' => 0, 'avg_time' => 0, 'baseline_time' => 0, 'efficiency' => 0]
    ];
    
    // 計算每個能力類別的數據
    foreach ($game_categories as $category => $game_types) {
        $total_plays = 0;
        $total_time = 0;
        $weighted_baseline = 0;
        $baseline_weight = 0;
        
        foreach ($game_types as $game_type) {
            // 獲取該遊戲類型的基準時間
            $baseline_time = $weighted_scoring->getBaselineTime($game_type);
            if ($baseline_time === null) continue;
            
            // 獲取用戶在該遊戲類型的記錄
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as play_count, AVG(play_time) as avg_time
                FROM game_records 
                WHERE member_id = ? 
                    AND game_type = ? 
                    AND play_time > 0 
                    AND play_time < 1800
                    AND status = 'completed'
            ");
            $stmt->execute([$member_id, $game_type]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && $result['play_count'] > 0) {
                $play_count = intval($result['play_count']);
                $avg_time = floatval($result['avg_time']);
                
                $total_plays += $play_count;
                $total_time += $avg_time * $play_count;
                
                // 加權計算基準時間
                $weighted_baseline += $baseline_time * $play_count;
                $baseline_weight += $play_count;
            }
        }
        
        if ($total_plays > 0) {
            $ability_data[$category]['total_plays'] = $total_plays;
            $ability_data[$category]['avg_time'] = $total_time / $total_plays;
            $ability_data[$category]['baseline_time'] = $baseline_weight > 0 ? $weighted_baseline / $baseline_weight : 0;
            
            // 計算加權分數 (與後台雷達圖使用相同公式)
            if ($ability_data[$category]['baseline_time'] > 0) {
                // 使用與後台相同的加權分數計算
                $base_score = 50; // 基礎分數
                $avg_time = $ability_data[$category]['avg_time'];
                $baseline_time = $ability_data[$category]['baseline_time'];
                
                // 時間加權係數 = 1 + (基準時間 - 實際時間) / 基準時間
                $time_weight = 1 + (($baseline_time - $avg_time) / $baseline_time);
                $time_weight = max(0.2, min(2.0, $time_weight)); // 限制範圍
                
                // 難度係數 (預設為1.0，因為前端沒有難度信息)
                $difficulty_multiplier = 1.0;
                
                // 計算加權分數
                $weighted_score = $base_score * $time_weight * $difficulty_multiplier;
                
                // 轉換為0-100的百分比
                $efficiency = min(100, max(0, $weighted_score));
                $ability_data[$category]['efficiency'] = $efficiency;
            }
        }
    }
    
    // 獲取用戶的基本分數（用於備用顯示）
    $score_sql = "SELECT reaction_score, memory_score, logic_score FROM member WHERE member_id = ?";
    $score_stmt = $pdo->prepare($score_sql);
    $score_stmt->execute([$member_id]);
    $score_result = $score_stmt->fetch();
    
    // 生成能力分析報告
    $analysis_report = generateBaselineAnalysisReport($ability_data);
    
    outputCleanJson([
        'success' => true,
        'data' => [
            'reaction' => round($ability_data['reaction']['efficiency'], 1),
            'memory' => round($ability_data['memory']['efficiency'], 1),
            'logic' => round($ability_data['logic']['efficiency'], 1),
            'baseline_data' => $ability_data,
            'report' => $analysis_report,
            'stats' => [
                'reaction_games' => $ability_data['reaction']['total_plays'],
                'memory_games' => $ability_data['memory']['total_plays'],
                'logic_games' => $ability_data['logic']['total_plays'],
                'reaction_avg' => round($ability_data['reaction']['avg_time'], 1),
                'memory_avg' => round($ability_data['memory']['avg_time'], 1),
                'logic_avg' => round($ability_data['logic']['avg_time'], 1)
            ],
            'backup_scores' => [
                'reaction' => $score_result['reaction_score'] ?? 0,
                'memory' => $score_result['memory_score'] ?? 0,
                'logic' => $score_result['logic_score'] ?? 0
            ]
        ]
    ]);
    
} catch (Exception $e) {
    outputCleanJson([
        'success' => false,
        'message' => '分析失敗：' . $e->getMessage()
    ]);
}

// 生成基於基準時間的分析報告
function generateBaselineAnalysisReport($ability_data) {
    $efficiencies = [
        'reaction' => $ability_data['reaction']['efficiency'],
        'memory' => $ability_data['memory']['efficiency'],
        'logic' => $ability_data['logic']['efficiency']
    ];
    
    $max_efficiency = max($efficiencies);
    $min_efficiency = min($efficiencies);
    $avg_efficiency = array_sum($efficiencies) / 3;
    
    // 計算有數據的能力數量
    $active_abilities = 0;
    foreach ($efficiencies as $efficiency) {
        if ($efficiency > 0) $active_abilities++;
    }
    
    $report = [];
    
    // 判斷玩家類型
    if ($active_abilities < 2) {
        if ($efficiencies['reaction'] > 0) {
            $report['type'] = '反應型玩家';
            $report['description'] = '您專精於反應力遊戲，建議嘗試其他類型的遊戲來拓展能力。';
        } elseif ($efficiencies['memory'] > 0) {
            $report['type'] = '記憶型玩家';
            $report['description'] = '您專精於記憶力遊戲，建議嘗試其他類型的遊戲來拓展能力。';
        } elseif ($efficiencies['logic'] > 0) {
            $report['type'] = '邏輯型玩家';
            $report['description'] = '您專精於邏輯力遊戲，建議嘗試其他類型的遊戲來拓展能力。';
        } else {
            $report['type'] = '新手玩家';
            $report['description'] = '您剛開始遊戲之旅，建議多嘗試各種類型的遊戲來發現自己的潛力。';
        }
    } elseif ($max_efficiency - $min_efficiency <= 20 && $active_abilities >= 2) {
        $report['type'] = '平衡型玩家';
        $report['description'] = '您的三個能力發展均衡，是個全能型玩家！';
    } elseif ($max_efficiency >= 80) {
        $report['type'] = '專精型玩家';
        $report['description'] = '您在某個領域表現特別突出，建議可以嘗試其他類型的遊戲來拓展能力。';
    } else {
        $report['type'] = '發展型玩家';
        $report['description'] = '您還有很大的進步空間，建議多練習各種類型的遊戲。';
    }
    
    // 能力建議
    $report['suggestions'] = [];
    
    if ($efficiencies['reaction'] < 50 && $ability_data['reaction']['total_plays'] > 0) {
        $report['suggestions'][] = '建議多練習節奏遊戲和接金蛋遊戲來提升反應力效率';
    }
    
    if ($efficiencies['memory'] < 50 && $ability_data['memory']['total_plays'] > 0) {
        $report['suggestions'][] = '建議多練習翻牌對對樂和追蹤犯人遊戲來提升記憶力效率';
    }
    
    if ($efficiencies['logic'] < 50 && $ability_data['logic']['total_plays'] > 0) {
        $report['suggestions'][] = '建議多練習2048和算菜錢遊戲來提升邏輯力效率';
    }
    
    return $report;
}

?>
