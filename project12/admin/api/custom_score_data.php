<?php
// custom_score_data.php
error_reporting(0);
ini_set('display_errors', 0);

session_start();
require_once '../../db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => '未授權']);
    exit;
}

// 將遊戲類型映射到三項能力
$reactionTypes = ['反應力', '接金蛋遊戲', '接金蛋', '節奏遊戲', '看字選色遊戲'];
$memoryTypes = ['記憶力', '翻牌對對樂', '圖片線索問答', '追蹤犯人遊戲'];
$logicTypes = ['算術邏輯力', '算菜錢遊戲', '邏輯力', '2048', '過河遊戲'];
$inPlaceholders = function(array $arr) { return implode(',', array_fill(0, count($arr), '?')); };

/**
 * 輔助函數：套用自訂公式 V = score + Pt
 * @param float $score 該能力當日平均分數
 * @param float|null $time 該能力當日平均時間（弧度）
 * @return float
 */
function calculateCustomV($score, $time) {
    if ($time === null || $time <= 0) {
        // 如果沒有時間數據，假設 Pt = 0
        return round(floatval($score), 2);
    }
    
    $Pt = 0;
    $time_rad = floatval($time); // 假設 time 直接是弧度值
    
    if ($time_rad < 90) {
        // time < 90: Pt = cos(time)
        $Pt = cos($time_rad);
    } else {
        // time >= 90: Pt = sin(time - 90)
        $Pt = sin($time_rad - 90);
    }
    
    $V = $score + $Pt;
    // 限制分數在合理範圍內（例如 0-100），但由於公式較為自訂，這裡只做四捨五入
    return round($V, 2);
}


try {
    $memberId = isset($_GET['member_id']) && $_GET['member_id'] !== '' ? intval($_GET['member_id']) : null;
    $range = $_GET['range'] ?? '30d';

    $dateWhere = '';
    $params = [];
    if ($range !== 'all') {
        switch ($range) {
            case '7d': $dateWhere = 'AND gr.play_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)'; break;
            case '30d': $dateWhere = 'AND gr.play_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)'; break;
            case '90d': $dateWhere = 'AND gr.play_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)'; break;
        }
    }

    $memberWhere = '';
    if ($memberId) {
        $memberWhere = 'AND gr.member_id = ?';
        $params[] = $memberId;
    }

    // 確定要查詢的天數範圍
    $days = $range === '7d' ? 7 : ($range === '30d' ? 30 : ($range === '90d' ? 90 : 30));
    $trendDates = [];
    $dailyVReaction = [];
    $dailyVMemory = [];
    $dailyVLogic = [];
    
    // 計算每日 V 值趨勢資料
    for ($i = $days - 1; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $trendDates[] = $date;

        $trendSql = "
            SELECT ability, AVG(score) AS avg_score, AVG(play_time) AS avg_time FROM (
                SELECT 
                    CASE 
                        WHEN gr.game_type IN (" . $inPlaceholders($reactionTypes) . ") THEN 'reaction'
                        WHEN gr.game_type IN (" . $inPlaceholders($memoryTypes) . ") THEN 'memory'
                        WHEN gr.game_type IN (" . $inPlaceholders($logicTypes) . ") THEN 'logic'
                        ELSE 'other'
                    END AS ability,
                    gr.score,
                    gr.play_time
                FROM game_records gr
                WHERE DATE(gr.play_date) = ?
                " . ($memberId ? 'AND gr.member_id = ?' : '') . "
                AND gr.score IS NOT NULL
            ) t
            WHERE ability IN ('reaction','memory','logic')
            GROUP BY ability
        ";

        $trendParams = array_merge($reactionTypes, $memoryTypes, $logicTypes, [$date]);
        if ($memberId) { $trendParams[] = $memberId; }

        $stmtT = $pdo->prepare($trendSql);
        $stmtT->execute($trendParams);
        $day = ['reaction' => null, 'memory' => null, 'logic' => null];
        
        while ($row = $stmtT->fetch(PDO::FETCH_ASSOC)) {
            $ability = $row['ability'];
            $avgScore = floatval($row['avg_score']);
            $avgTime = $row['avg_time'] !== null ? floatval($row['avg_time']) : null;
            
            // 【核心計算】：套用自訂公式
            $V_value = calculateCustomV($avgScore, $avgTime);
            $day[$ability] = $V_value;
        }
        
        $dailyVReaction[] = $day['reaction'];
        $dailyVMemory[] = $day['memory'];
        $dailyVLogic[] = $day['logic'];
    }

    // --- 週彙整 (沿用 test_results.php 的邏輯) ---
    $weeklyLabels = [];
    $weeklyVReaction = [];
    $weeklyVMemory = [];
    $weeklyVLogic = [];
    
    $total = count($trendDates);
    if ($total > 0) {
        for ($start = 0; $start < $total; $start += 7) {
            $end = min($start + 6, $total - 1);
            $startLabel = date('m/d', strtotime($trendDates[$start]));
            $endLabel = date('m/d', strtotime($trendDates[$end]));
            $weeklyLabels[] = $start === $end ? $startLabel : ($startLabel . '~' . $endLabel);

            $sliceReaction = array_slice($dailyVReaction, $start, $end - $start + 1);
            $sliceMemory = array_slice($dailyVMemory, $start, $end - $start + 1);
            $sliceLogic = array_slice($dailyVLogic, $start, $end - $start + 1);

            $avg = function(array $arr) {
                $valid = array_filter($arr, function($v){ return $v !== null; });
                $count = count($valid);
                if ($count === 0) return null;
                return round(array_sum($valid) / $count, 2);
            };

            $weeklyVReaction[] = $avg($sliceReaction);
            $weeklyVMemory[] = $avg($sliceMemory);
            $weeklyVLogic[] = $avg($sliceLogic);
        }
    }
    // ------------------------------------------------

    echo json_encode([
        'success' => true,
        'range' => $range,
        'labels' => $weeklyLabels,
        'reaction' => $weeklyVReaction,
        'memory' => $weeklyVMemory,
        'logic' => $weeklyVLogic,
        'message' => '自訂分數趨勢數據',
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
// 檔案結束，無 ?>