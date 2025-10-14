<?php
/**
 * 真實能力進步追蹤系統
 * 
 * 分析玩家的歷史表現，追蹤能力進步趨勢
 * 提供個人化的能力評估和建議
 */

class ProgressTrackingSystem {
    private $pdo;
    private $weighted_scoring;
    
    public function __construct($pdo, $weighted_scoring = null) {
        $this->pdo = $pdo;
        $this->weighted_scoring = $weighted_scoring;
    }
    
    /**
     * 獲取玩家的能力進步趨勢
     * 
     * @param int $member_id 用戶ID
     * @param int $days 分析天數
     * @return array 進步趨勢分析結果
     */
    public function getProgressTrend($member_id, $days = 30) {
        try {
            // 獲取歷史加權分數數據
            $weighted_data = $this->getWeightedScoreHistory($member_id, $days);
            
            // 獲取傳統分數數據作為對比
            $traditional_data = $this->getTraditionalScoreHistory($member_id, $days);
            
            // 計算各項能力的進步趨勢
            $progress_analysis = [
                'reaction' => $this->calculateAbilityProgress($weighted_data, 'reaction', $days),
                'memory' => $this->calculateAbilityProgress($weighted_data, 'memory', $days),
                'logic' => $this->calculateAbilityProgress($weighted_data, 'logic', $days),
                'overall' => $this->calculateOverallProgress($weighted_data, $days)
            ];
            
            // 計算改善率
            $improvement_rate = $this->calculateImprovementRate($weighted_data, $days);
            
            // 生成個人化建議
            $recommendations = $this->generateRecommendations($progress_analysis, $improvement_rate);
            
            return [
                'member_id' => $member_id,
                'analysis_period' => $days,
                'progress_analysis' => $progress_analysis,
                'improvement_rate' => $improvement_rate,
                'recommendations' => $recommendations,
                'data_points' => count($weighted_data),
                'analysis_date' => date('Y-m-d H:i:s')
            ];
            
        } catch (Exception $e) {
            error_log("獲取進步趨勢失敗: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * 獲取加權分數歷史數據
     */
    private function getWeightedScoreHistory($member_id, $days) {
        $stmt = $this->pdo->prepare("
            SELECT 
                wsh.*,
                CASE 
                    WHEN wsh.game_type IN ('反應力', '接金蛋遊戲', '接金蛋', '節奏遊戲', '看字選色遊戲') THEN 'reaction'
                    WHEN wsh.game_type IN ('記憶力', '翻牌對對樂', '圖片線索問答', '追蹤犯人遊戲') THEN 'memory'
                    WHEN wsh.game_type IN ('算術邏輯力', '算菜錢遊戲', '邏輯力', '2048', '過河遊戲') THEN 'logic'
                    ELSE 'other'
                END as ability_type,
                DATE(wsh.play_date) as play_day
            FROM weighted_score_history wsh 
            WHERE wsh.member_id = ? 
                AND wsh.play_date >= DATE_SUB(NOW(), INTERVAL ? DAY)
            ORDER BY wsh.play_date ASC
        ");
        $stmt->execute([$member_id, $days]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * 獲取傳統分數歷史數據
     */
    private function getTraditionalScoreHistory($member_id, $days) {
        $stmt = $this->pdo->prepare("
            SELECT 
                gr.*,
                CASE 
                    WHEN gr.game_type IN ('反應力', '接金蛋遊戲', '接金蛋', '節奏遊戲', '看字選色遊戲') THEN 'reaction'
                    WHEN gr.game_type IN ('記憶力', '翻牌對對樂', '圖片線索問答', '追蹤犯人遊戲') THEN 'memory'
                    WHEN gr.game_type IN ('算術邏輯力', '算菜錢遊戲', '邏輯力', '2048', '過河遊戲') THEN 'logic'
                    ELSE 'other'
                END as ability_type,
                DATE(gr.play_date) as play_day
            FROM game_records gr 
            WHERE gr.member_id = ? 
                AND gr.play_date >= DATE_SUB(NOW(), INTERVAL ? DAY)
                AND gr.status = 'completed'
            ORDER BY gr.play_date ASC
        ");
        $stmt->execute([$member_id, $days]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * 計算特定能力的進步趨勢
     */
    private function calculateAbilityProgress($data, $ability_type, $days) {
        $ability_data = array_filter($data, function($record) use ($ability_type) {
            return $record['ability_type'] === $ability_type;
        });
        
        if (empty($ability_data)) {
            return [
                'trend' => 'no_data',
                'improvement_percentage' => 0,
                'average_score' => 0,
                'best_score' => 0,
                'consistency' => 0,
                'play_count' => 0
            ];
        }
        
        // 按時間排序
        usort($ability_data, function($a, $b) {
            return strtotime($a['play_date']) - strtotime($b['play_date']);
        });
        
        $scores = array_column($ability_data, 'final_score');
        $play_count = count($scores);
        $average_score = array_sum($scores) / $play_count;
        $best_score = max($scores);
        
        // 計算一致性（標準差的倒數）
        $variance = 0;
        foreach ($scores as $score) {
            $variance += pow($score - $average_score, 2);
        }
        $std_dev = sqrt($variance / $play_count);
        $consistency = $std_dev > 0 ? min(100, 100 / $std_dev) : 100;
        
        // 計算趨勢
        $trend = $this->calculateTrendDirection($scores);
        
        // 計算改善百分比（最近25%與前面75%比較）
        $improvement_percentage = $this->calculateRecentImprovement($scores);
        
        return [
            'trend' => $trend,
            'improvement_percentage' => round($improvement_percentage, 1),
            'average_score' => round($average_score, 2),
            'best_score' => round($best_score, 2),
            'consistency' => round($consistency, 1),
            'play_count' => $play_count,
            'recent_average' => round($this->getRecentAverage($scores), 2),
            'early_average' => round($this->getEarlyAverage($scores), 2)
        ];
    }
    
    /**
     * 計算整體進步情況
     */
    private function calculateOverallProgress($data, $days) {
        if (empty($data)) {
            return [
                'overall_trend' => 'no_data',
                'total_games' => 0,
                'avg_improvement' => 0,
                'most_improved' => null,
                'needs_attention' => null
            ];
        }
        
        $all_scores = array_column($data, 'final_score');
        $total_games = count($all_scores);
        
        // 按能力類型分組計算
        $abilities = ['reaction', 'memory', 'logic'];
        $ability_improvements = [];
        
        foreach ($abilities as $ability) {
            $ability_progress = $this->calculateAbilityProgress($data, $ability, $days);
            if ($ability_progress['play_count'] > 0) {
                $ability_improvements[$ability] = $ability_progress['improvement_percentage'];
            }
        }
        
        $avg_improvement = empty($ability_improvements) ? 0 : array_sum($ability_improvements) / count($ability_improvements);
        
        // 找出最進步和需要關注的能力
        $most_improved = empty($ability_improvements) ? null : array_keys($ability_improvements, max($ability_improvements))[0];
        $needs_attention = empty($ability_improvements) ? null : array_keys($ability_improvements, min($ability_improvements))[0];
        
        // 整體趨勢
        $overall_trend = $avg_improvement > 5 ? 'improving' : ($avg_improvement < -5 ? 'declining' : 'stable');
        
        return [
            'overall_trend' => $overall_trend,
            'total_games' => $total_games,
            'avg_improvement' => round($avg_improvement, 1),
            'most_improved' => $most_improved,
            'needs_attention' => $needs_attention,
            'ability_scores' => $ability_improvements
        ];
    }
    
    /**
     * 計算改善率
     */
    private function calculateImprovementRate($data, $days) {
        $daily_averages = [];
        
        // 按日期分組計算每日平均分數
        foreach ($data as $record) {
            $day = $record['play_day'];
            if (!isset($daily_averages[$day])) {
                $daily_averages[$day] = ['scores' => [], 'count' => 0];
            }
            $daily_averages[$day]['scores'][] = $record['final_score'];
            $daily_averages[$day]['count']++;
        }
        
        // 計算每日平均分數
        $daily_avg_scores = [];
        foreach ($daily_averages as $day => $data) {
            $daily_avg_scores[$day] = array_sum($data['scores']) / $data['count'];
        }
        
        if (count($daily_avg_scores) < 2) {
            return [
                'daily_improvement' => 0,
                'weekly_improvement' => 0,
                'consistency_score' => 0
            ];
        }
        
        // 計算線性回歸斜率（日改善率）
        $days_array = array_keys($daily_avg_scores);
        $scores_array = array_values($daily_avg_scores);
        $daily_improvement = $this->calculateLinearRegressionSlope($days_array, $scores_array);
        
        return [
            'daily_improvement' => round($daily_improvement, 3),
            'weekly_improvement' => round($daily_improvement * 7, 2),
            'consistency_score' => round($this->calculateConsistencyScore($scores_array), 1)
        ];
    }
    
    /**
     * 生成個人化建議
     */
    private function generateRecommendations($progress_analysis, $improvement_rate) {
        $recommendations = [];
        
        // 基於整體趨勢的建議
        $overall = $progress_analysis['overall'];
        switch ($overall['overall_trend']) {
            case 'improving':
                $recommendations[] = [
                    'type' => 'positive',
                    'title' => '🎉 持續進步中！',
                    'message' => '您的整體表現正在穩步提升，請保持當前的練習節奏。',
                    'priority' => 'low'
                ];
                break;
            case 'declining':
                $recommendations[] = [
                    'type' => 'warning',
                    'title' => '⚠️ 需要加強練習',
                    'message' => '最近表現有所下滑，建議增加練習頻率或嘗試不同難度的遊戲。',
                    'priority' => 'high'
                ];
                break;
            case 'stable':
                $recommendations[] = [
                    'type' => 'info',
                    'title' => '📊 表現穩定',
                    'message' => '您的能力表現相對穩定，可以嘗試挑戰更高難度來突破瓶頸。',
                    'priority' => 'medium'
                ];
                break;
        }
        
        // 基於具體能力的建議
        foreach (['reaction', 'memory', 'logic'] as $ability) {
            $ability_data = $progress_analysis[$ability];
            $ability_names = [
                'reaction' => '反應力',
                'memory' => '記憶力', 
                'logic' => '邏輯思維'
            ];
            
            if ($ability_data['play_count'] == 0) {
                $recommendations[] = [
                    'type' => 'suggestion',
                    'title' => "🎮 嘗試{$ability_names[$ability]}遊戲",
                    'message' => "建議嘗試一些{$ability_names[$ability]}相關的遊戲來平衡發展。",
                    'priority' => 'medium'
                ];
            } elseif ($ability_data['improvement_percentage'] < -10) {
                $recommendations[] = [
                    'type' => 'warning',
                    'title' => "📉 {$ability_names[$ability]}需要加強",
                    'message' => "您的{$ability_names[$ability]}表現有所下降，建議多練習相關遊戲。",
                    'priority' => 'high'
                ];
            } elseif ($ability_data['consistency'] < 30) {
                $recommendations[] = [
                    'type' => 'tip',
                    'title' => "🎯 提升{$ability_names[$ability]}穩定性",
                    'message' => "您在{$ability_names[$ability]}方面表現不夠穩定，建議專注練習基礎技能。",
                    'priority' => 'medium'
                ];
            }
        }
        
        // 基於改善率的建議
        if ($improvement_rate['daily_improvement'] > 0.5) {
            $recommendations[] = [
                'type' => 'positive',
                'title' => '🚀 進步神速！',
                'message' => '您的學習曲線很陡峭，繼續保持這個勢頭！',
                'priority' => 'low'
            ];
        } elseif ($improvement_rate['consistency_score'] < 40) {
            $recommendations[] = [
                'type' => 'tip',
                'title' => '⚖️ 提升表現穩定性',
                'message' => '您的表現波動較大，建議建立固定的練習時間和節奏。',
                'priority' => 'medium'
            ];
        }
        
        // 按優先級排序
        usort($recommendations, function($a, $b) {
            $priority_order = ['high' => 3, 'medium' => 2, 'low' => 1];
            return $priority_order[$b['priority']] - $priority_order[$a['priority']];
        });
        
        return array_slice($recommendations, 0, 5); // 最多返回5個建議
    }
    
    /**
     * 計算趨勢方向
     */
    private function calculateTrendDirection($scores) {
        if (count($scores) < 3) return 'insufficient_data';
        
        $recent_avg = $this->getRecentAverage($scores);
        $early_avg = $this->getEarlyAverage($scores);
        
        $change_percentage = $early_avg > 0 ? (($recent_avg - $early_avg) / $early_avg) * 100 : 0;
        
        if ($change_percentage > 10) return 'strongly_improving';
        if ($change_percentage > 3) return 'improving';
        if ($change_percentage < -10) return 'strongly_declining';
        if ($change_percentage < -3) return 'declining';
        return 'stable';
    }
    
    /**
     * 計算最近改善情況
     */
    private function calculateRecentImprovement($scores) {
        if (count($scores) < 4) return 0;
        
        $recent_avg = $this->getRecentAverage($scores);
        $early_avg = $this->getEarlyAverage($scores);
        
        return $early_avg > 0 ? (($recent_avg - $early_avg) / $early_avg) * 100 : 0;
    }
    
    /**
     * 獲取最近25%數據的平均值
     */
    private function getRecentAverage($scores) {
        $count = count($scores);
        $recent_count = max(1, intval($count * 0.25));
        $recent_scores = array_slice($scores, -$recent_count);
        return array_sum($recent_scores) / count($recent_scores);
    }
    
    /**
     * 獲取前75%數據的平均值
     */
    private function getEarlyAverage($scores) {
        $count = count($scores);
        $early_count = max(1, intval($count * 0.75));
        $early_scores = array_slice($scores, 0, $early_count);
        return array_sum($early_scores) / count($early_scores);
    }
    
    /**
     * 計算線性回歸斜率
     */
    private function calculateLinearRegressionSlope($x_values, $y_values) {
        $n = count($x_values);
        if ($n < 2) return 0;
        
        // 將日期轉換為數值
        $x_numeric = [];
        $base_date = strtotime($x_values[0]);
        foreach ($x_values as $date) {
            $x_numeric[] = (strtotime($date) - $base_date) / 86400; // 轉換為天數
        }
        
        $sum_x = array_sum($x_numeric);
        $sum_y = array_sum($y_values);
        $sum_xy = 0;
        $sum_x2 = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $sum_xy += $x_numeric[$i] * $y_values[$i];
            $sum_x2 += $x_numeric[$i] * $x_numeric[$i];
        }
        
        $denominator = $n * $sum_x2 - $sum_x * $sum_x;
        if ($denominator == 0) return 0;
        
        return ($n * $sum_xy - $sum_x * $sum_y) / $denominator;
    }
    
    /**
     * 計算一致性分數
     */
    private function calculateConsistencyScore($scores) {
        $n = count($scores);
        if ($n < 2) return 100;
        
        $mean = array_sum($scores) / $n;
        $variance = 0;
        
        foreach ($scores as $score) {
            $variance += pow($score - $mean, 2);
        }
        
        $std_dev = sqrt($variance / $n);
        
        // 將標準差轉換為一致性分數 (0-100)
        return $mean > 0 ? max(0, 100 - ($std_dev / $mean) * 100) : 0;
    }
    
    /**
     * 獲取玩家的能力雷達圖數據
     * 使用各能力的加權分平均，而不是總分
     */
    public function getAbilityRadarData($member_id, $days = 30) {
        try {
            $abilities = ['reaction', 'memory', 'logic'];
            $radar_data = [];
            
            foreach ($abilities as $ability) {
                $ability_stats = $this->getAbilityWeightedAverages($member_id, $ability, $days);
                
                $radar_data[$ability] = [
                    'weighted_average' => $ability_stats['weighted_average'],
                    'traditional_average' => $ability_stats['traditional_average'],
                    'consistency' => $ability_stats['consistency'],
                    'improvement' => $ability_stats['improvement_percentage'],
                    'play_count' => $ability_stats['play_count']
                ];
            }
            
            return $radar_data;
        } catch (Exception $e) {
            error_log("獲取雷達圖數據失敗: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * 獲取特定能力的加權分數統計
     */
    private function getAbilityWeightedAverages($member_id, $ability_type, $days) {
        // 定義各能力對應的遊戲類型
        $game_types_map = [
            'reaction' => ['反應力', '接金蛋遊戲', '接金蛋', '節奏遊戲', '看字選色遊戲'],
            'memory' => ['記憶力', '翻牌對對樂', '圖片線索問答', '追蹤犯人遊戲'],
            'logic' => ['算術邏輯力', '算菜錢遊戲', '邏輯力', '2048', '過河遊戲']
        ];
        
        $game_types = $game_types_map[$ability_type] ?? [];
        
        if (empty($game_types)) {
            return [
                'weighted_average' => 0,
                'traditional_average' => 0,
                'consistency' => 0,
                'improvement_percentage' => 0,
                'play_count' => 0
            ];
        }
        
        $placeholders = implode(',', array_fill(0, count($game_types), '?'));
        
        // 獲取加權分數數據
        $weighted_stmt = $this->pdo->prepare("
            SELECT 
                final_score,
                play_date,
                time_weight,
                accuracy_rate
            FROM weighted_score_history 
            WHERE member_id = ? 
                AND game_type IN ($placeholders)
                AND play_date >= DATE_SUB(NOW(), INTERVAL ? DAY)
            ORDER BY play_date ASC
        ");
        
        $params = array_merge([$member_id], $game_types, [$days]);
        $weighted_stmt->execute($params);
        $weighted_scores = $weighted_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 獲取傳統分數數據作為對比
        $traditional_stmt = $this->pdo->prepare("
            SELECT 
                score,
                play_date
            FROM game_records 
            WHERE member_id = ? 
                AND game_type IN ($placeholders)
                AND play_date >= DATE_SUB(NOW(), INTERVAL ? DAY)
                AND status = 'completed'
            ORDER BY play_date ASC
        ");
        
        $traditional_stmt->execute($params);
        $traditional_scores = $traditional_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 計算統計數據
        $weighted_values = array_column($weighted_scores, 'final_score');
        $traditional_values = array_column($traditional_scores, 'score');
        
        $weighted_average = !empty($weighted_values) ? array_sum($weighted_values) / count($weighted_values) : 0;
        $traditional_average = !empty($traditional_values) ? array_sum($traditional_values) / count($traditional_values) : 0;
        
        // 計算一致性
        $consistency = $this->calculateConsistencyScore($weighted_values);
        
        // 計算改善百分比
        $improvement_percentage = $this->calculateRecentImprovement($weighted_values);
        
        return [
            'weighted_average' => round($weighted_average, 2),
            'traditional_average' => round($traditional_average, 2),
            'consistency' => round($consistency, 1),
            'improvement_percentage' => round($improvement_percentage, 1),
            'play_count' => count($weighted_values),
            'raw_data' => [
                'weighted_scores' => $weighted_values,
                'traditional_scores' => $traditional_values
            ]
        ];
    }
}
?>
