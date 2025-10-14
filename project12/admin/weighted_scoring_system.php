<?php
/**
 * 動態基準時間加權分數系統
 * 
 * 根據每款遊戲的基準時間動態調整分數
 * 公式：最終分數 = 基礎分 × 準確率 × (1 + (基準時間 - 實際時間) / 基準時間)
 */

class WeightedScoringSystem {
    private $pdo;
    private $baseline_times_cache = [];
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->loadBaselineTimes();
    }
    
    /**
     * 載入所有基準時間到快取
     */
    private function loadBaselineTimes() {
        try {
            $stmt = $this->pdo->query("
                SELECT game_type, baseline_time, avg_time, median_time, stage, 
                       baseline_last_updated, data_last_calculated, needs_update,
                       sample_count, min_sample_count
                FROM game_baseline_times 
                WHERE is_active = TRUE
            ");
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $this->baseline_times_cache[$row['game_type']] = $row;
            }
        } catch (Exception $e) {
            error_log("載入基準時間失敗: " . $e->getMessage());
        }
    }
    
    /**
     * 獲取遊戲的基準時間
     * 
     * @param string $game_type 遊戲類型
     * @return float|null 基準時間(秒)
     */
    public function getBaselineTime($game_type) {
        // 如果遊戲不存在，嘗試自動初始化
        if (!isset($this->baseline_times_cache[$game_type])) {
            $baseline_time = $this->initializeNewGameBaseline($game_type);
            if ($baseline_time === null) {
                return null;
            }
        }
        
        $data = $this->baseline_times_cache[$game_type];
        
        // 根據階段選擇使用哪個時間
        switch ($data['stage']) {
            case 'manual':
                return floatval($data['baseline_time']);
            case 'historical':
                return $data['avg_time'] ? floatval($data['avg_time']) : floatval($data['baseline_time']);
            case 'mature':
                return $data['median_time'] ? floatval($data['median_time']) : ($data['avg_time'] ?: floatval($data['baseline_time']));
            default:
                return floatval($data['baseline_time']);
        }
    }
    
    /**
     * 為新遊戲初始化基準時間
     * 
     * @param string $game_type 遊戲類型
     * @return float|null 初始化的基準時間
     */
    private function initializeNewGameBaseline($game_type) {
        try {
            // 先檢查歷史數據，計算平均時間作為初始基準
            $stmt = $this->pdo->prepare("
                SELECT AVG(play_time) as avg_time, COUNT(*) as count
                FROM game_records 
                WHERE game_type = ? 
                    AND play_time > 0 
                    AND play_time < 1800
                    AND status = 'completed'
            ");
            $stmt->execute([$game_type]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $baseline_time = null;
            $stage = 'manual';
            
            if ($result && $result['count'] >= 5) {
                // 如果有足夠的歷史數據，使用歷史平均作為基準
                $baseline_time = floatval($result['avg_time']);
                $stage = $result['count'] >= 20 ? 'historical' : 'manual';
            } else {
                // 如果沒有足夠數據，根據遊戲類型設定預設基準時間
                $baseline_time = $this->getDefaultBaselineTime($game_type);
            }
            
            if ($baseline_time === null) {
                return null;
            }
            
            // 插入到資料庫
            $stmt = $this->pdo->prepare("
                INSERT INTO game_baseline_times 
                (game_type, game_display_name, baseline_time, stage, baseline_last_updated) 
                VALUES (?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE 
                baseline_time = VALUES(baseline_time),
                stage = VALUES(stage),
                baseline_last_updated = NOW()
            ");
            
            $display_name = $this->getGameDisplayName($game_type);
            $stmt->execute([$game_type, $display_name, $baseline_time, $stage]);
            
            // 重新載入快取
            $this->loadBaselineTimes();
            
            error_log("自動初始化新遊戲基準時間: {$game_type} = {$baseline_time}秒 (階段: {$stage})");
            
            return $baseline_time;
            
        } catch (Exception $e) {
            error_log("初始化新遊戲基準時間失敗: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * 根據遊戲類型獲取預設基準時間
     */
    private function getDefaultBaselineTime($game_type) {
        // 預設基準時間映射表
        $default_times = [
            // 反應力遊戲（通常較短）
            '反應力' => 20.0,
            '接金蛋遊戲' => 30.0,
            '接金蛋' => 30.0,
            '節奏遊戲' => 120.0,
            '看字選色遊戲' => 45.0,
            
            // 記憶力遊戲（中等時間）
            '記憶力' => 45.0,
            '翻牌對對樂' => 60.0,
            '圖片線索問答' => 90.0,
            '追蹤犯人遊戲' => 180.0,
            
            // 邏輯思維遊戲（較長時間）
            '算術邏輯力' => 60.0,
            '算菜錢遊戲' => 70.0,
            '邏輯力' => 80.0,
            '2048' => 300.0,
            '過河遊戲' => 120.0
        ];
        
        // 直接匹配
        if (isset($default_times[$game_type])) {
            return $default_times[$game_type];
        }
        
        // 模糊匹配
        foreach ($default_times as $known_type => $time) {
            if (strpos($game_type, $known_type) !== false || strpos($known_type, $game_type) !== false) {
                return $time;
            }
        }
        
        // 根據遊戲名稱特徵推測
        if (strpos($game_type, '反應') !== false || strpos($game_type, '快速') !== false) {
            return 30.0; // 反應類遊戲預設30秒
        } elseif (strpos($game_type, '記憶') !== false || strpos($game_type, '對對樂') !== false) {
            return 60.0; // 記憶類遊戲預設60秒
        } elseif (strpos($game_type, '算') !== false || strpos($game_type, '邏輯') !== false) {
            return 90.0; // 邏輯類遊戲預設90秒
        }
        
        // 預設通用時間
        return 60.0;
    }
    
    /**
     * 獲取遊戲顯示名稱
     */
    private function getGameDisplayName($game_type) {
        // 可以根據需要自定義顯示名稱邏輯
        return $game_type;
    }
    
    /**
     * 計算加權分數（已簡化，移除準確率和防刷分係數）
     * 
     * @param string $game_type 遊戲類型
     * @param float $base_score 基礎分數
     * @param float $actual_time 實際完成時間(秒)
     * @param string $difficulty 難度級別
     * @param bool $enable_anti_spam 是否啟用防刷分（預設關閉）
     * @return array 包含詳細計算結果的陣列
     */
    public function calculateWeightedScore($game_type, $base_score, $actual_time, $difficulty = 'normal', $enable_anti_spam = false) {
        $baseline_time = $this->getBaselineTime($game_type);
        
        if ($baseline_time === null) {
            // 如果沒有基準時間，使用舊的固定分數系統
            return $this->getFallbackScore($difficulty);
        }
        
        // 防止除零錯誤
        if ($baseline_time <= 0) {
            $baseline_time = 60; // 預設1分鐘
        }
        
        // 計算時間加權係數
        // 公式：1 + (基準時間 - 實際時間) / 基準時間
        $time_weight = 1 + (($baseline_time - $actual_time) / $baseline_time);
        
        // 限制時間加權係數範圍 (0.2 到 2.0)
        $time_weight = max(0.2, min(2.0, $time_weight));
        
        // 根據難度調整分數
        $difficulty_multiplier = $this->getDifficultyMultiplier($difficulty);
        
        // 計算最終分數
        // 新公式：最終分數 = 基礎分 × 時間加權係數 × 難度係數
        $final_score = $base_score * $time_weight * $difficulty_multiplier;
        
        // 確保分數為正數且合理範圍
        $final_score = max(0, min(1000, $final_score));
        
        return [
            'game_type' => $game_type,
            'base_score' => round($base_score, 2),
            'baseline_time' => round($baseline_time, 2),
            'actual_time' => round($actual_time, 2),
            'time_weight' => round($time_weight, 4),
            'difficulty_multiplier' => $difficulty_multiplier,
            'final_score' => round($final_score, 2),
            'improvement_percentage' => round(($time_weight - 1) * 100, 1), // 相對於基準的改善百分比
            'anti_spam_enabled' => $enable_anti_spam,
            'anti_spam_penalty' => $enable_anti_spam ? 0 : null // 預設不啟用防刷分
        ];
    }
    
    /**
     * 獲取難度倍數
     */
    private function getDifficultyMultiplier($difficulty) {
        switch (strtolower($difficulty)) {
            case 'easy':
            case '簡單':
                return 0.5; 
            case 'normal':
            case '普通':
                return 1.0; 
            case 'hard':
            case '困難':
                return 1.5; 
            default:
                return 1.0; 
        }
    }
    
    /**
     * 當沒有基準時間時的後備評分系統
     */
    private function getFallbackScore($difficulty) {
        $base_scores = [
            'easy' => 20,
            '簡單' => 20,
            'normal' => 50,
            '普通' => 50,
            'hard' => 100,
            '困難' => 100
        ];
        
        $base_score = $base_scores[$difficulty] ?? 50;
        $difficulty_multiplier = $this->getDifficultyMultiplier($difficulty);
        $final_score = $base_score * $difficulty_multiplier;
        
        return [
            'game_type' => 'unknown',
            'base_score' => $base_score,
            'baseline_time' => null,
            'actual_time' => null,
            'time_weight' => 1.0,
            'difficulty_multiplier' => $difficulty_multiplier,
            'final_score' => round($final_score, 2),
            'improvement_percentage' => 0,
            'fallback' => true
        ];
    }
    
    /**
     * 儲存加權分數歷史
     */
    public function saveWeightedScoreHistory($member_id, $scoring_result, $difficulty) {
        if ($scoring_result['fallback'] ?? false) {
            return false; // 不儲存後備評分的歷史
        }
        
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO weighted_score_history 
                (member_id, game_type, base_score, accuracy_rate, baseline_time, 
                 actual_time, time_weight, final_score, difficulty) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            return $stmt->execute([
                $member_id,
                $scoring_result['game_type'],
                $scoring_result['base_score'],
                $scoring_result['accuracy_rate'],
                $scoring_result['baseline_time'],
                $scoring_result['actual_time'],
                $scoring_result['time_weight'],
                $scoring_result['final_score'],
                $difficulty
            ]);
        } catch (Exception $e) {
            error_log("儲存加權分數歷史失敗: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 更新遊戲基準時間 (管理員功能)
     */
    public function updateBaselineTime($game_type, $new_baseline_time, $stage = 'manual') {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE game_baseline_times 
                SET baseline_time = ?, stage = ?, last_updated = NOW() 
                WHERE game_type = ?
            ");
            
            $result = $stmt->execute([$new_baseline_time, $stage, $game_type]);
            
            // 更新快取
            if ($result) {
                $this->loadBaselineTimes();
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("更新基準時間失敗: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 獲取所有遊戲的基準時間設定
     */
    public function getAllBaselineTimes() {
        return $this->baseline_times_cache;
    }
    
    /**
     * 批量更新基準時間 (從歷史數據計算)
     */
    public function updateBaselineTimesFromHistory() {
        try {
            // 呼叫預存程序更新基準時間
            $this->pdo->exec("CALL UpdateGameBaselineTimes()");
            
            // 重新載入快取
            $this->loadBaselineTimes();
            
            return true;
        } catch (Exception $e) {
            error_log("從歷史數據更新基準時間失敗: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 獲取遊戲的統計資訊
     */
    public function getGameStatistics($game_type, $days = 30) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as total_plays,
                    AVG(play_time) as avg_time,
                    MIN(play_time) as min_time,
                    MAX(play_time) as max_time,
                    STDDEV(play_time) as std_dev
                FROM game_records 
                WHERE game_type = ? 
                    AND play_time > 0 
                    AND play_time < 1800
                    AND play_date >= DATE_SUB(NOW(), INTERVAL ? DAY)
                    AND status = 'completed'
            ");
            
            $stmt->execute([$game_type, $days]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("獲取遊戲統計失敗: " . $e->getMessage());
            return null;
        }
    }
}

/**
 * 防刷分機制（可選功能，預設關閉）
 * 
 * 注意：此功能預設不啟用，專注於挑戰自我的玩家不需要防刷分限制
 * 可通過設定參數啟用，適用於競爭性排行榜等場景
 */
class AntiSpamScoring {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * 檢查是否為刷分行為
     * 
     * @param int $member_id 用戶ID
     * @param string $game_type 遊戲類型
     * @param string $difficulty 難度
     * @return array 包含懲罰係數和原因的陣列
     */
    public function checkSpamBehavior($member_id, $game_type, $difficulty) {
        $penalty_factor = 1.0;
        $reasons = [];
        
        // 檢查1: 同一遊戲短時間內重複遊玩
        $recent_plays = $this->getRecentPlays($member_id, $game_type, $difficulty, 60); // 1小時內
        if ($recent_plays >= 10) {
            $penalty_factor *= 0.5; // 減少50%分數
            $reasons[] = "短時間內重複遊玩同一遊戲";
        } elseif ($recent_plays >= 5) {
            $penalty_factor *= 0.8; // 減少20%分數
            $reasons[] = "頻繁遊玩同一遊戲";
        }
        
        // 檢查2: 只玩簡單難度
        if ($difficulty === 'easy' || $difficulty === '簡單') {
            $easy_ratio = $this->getEasyGameRatio($member_id, 24); // 24小時內
            if ($easy_ratio > 0.8) {
                $penalty_factor *= 0.7; // 減少30%分數
                $reasons[] = "過度依賴簡單難度";
            }
        }
        
        // 檢查3: 遊戲時間過短 (可能是快速退出重刷)
        $avg_time = $this->getAveragePlayTime($member_id, $game_type, 24);
        $game_baseline = $this->getGameBaselineTime($game_type);
        if ($avg_time && $game_baseline && $avg_time < ($game_baseline * 0.3)) {
            $penalty_factor *= 0.6; // 減少40%分數
            $reasons[] = "遊戲時間異常短";
        }
        
        return [
            'penalty_factor' => max(0.1, $penalty_factor), // 最低保留10%分數
            'reasons' => $reasons,
            'is_spam' => $penalty_factor < 1.0
        ];
    }
    
    private function getRecentPlays($member_id, $game_type, $difficulty, $minutes) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as count
                FROM game_records 
                WHERE member_id = ? AND game_type = ? AND difficulty = ?
                    AND play_date >= DATE_SUB(NOW(), INTERVAL ? MINUTE)
            ");
            $stmt->execute([$member_id, $game_type, $difficulty, $minutes]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return intval($result['count']);
        } catch (Exception $e) {
            return 0;
        }
    }
    
    private function getEasyGameRatio($member_id, $hours) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    SUM(CASE WHEN difficulty IN ('easy', '簡單') THEN 1 ELSE 0 END) as easy_count,
                    COUNT(*) as total_count
                FROM game_records 
                WHERE member_id = ? 
                    AND play_date >= DATE_SUB(NOW(), INTERVAL ? HOUR)
            ");
            $stmt->execute([$member_id, $hours]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $total = intval($result['total_count']);
            return $total > 0 ? (intval($result['easy_count']) / $total) : 0;
        } catch (Exception $e) {
            return 0;
        }
    }
    
    private function getAveragePlayTime($member_id, $game_type, $hours) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT AVG(play_time) as avg_time
                FROM game_records 
                WHERE member_id = ? AND game_type = ?
                    AND play_date >= DATE_SUB(NOW(), INTERVAL ? HOUR)
                    AND play_time > 0
            ");
            $stmt->execute([$member_id, $game_type, $hours]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['avg_time'] ? floatval($result['avg_time']) : null;
        } catch (Exception $e) {
            return null;
        }
    }
    
    private function getGameBaselineTime($game_type) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT baseline_time FROM game_baseline_times 
                WHERE game_type = ? AND is_active = TRUE
            ");
            $stmt->execute([$game_type]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? floatval($result['baseline_time']) : null;
        } catch (Exception $e) {
            return null;
        }
    }
}
?>
