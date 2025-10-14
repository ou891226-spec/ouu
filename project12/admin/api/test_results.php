<?php
// 關閉錯誤顯示，避免HTML輸出
error_reporting(0);
ini_set('display_errors', 0);

session_start();
require_once '../../db.php';

header('Content-Type: application/json');

// 僅允許已登入管理員
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => '未授權']);
    exit;
}

// 將遊戲類型映射到三項能力（放在try區塊外，方便函數使用）
$reactionTypes = ['反應力', '接金蛋遊戲', '接金蛋', '節奏遊戲', '看字選色遊戲'];
$memoryTypes = ['記憶力', '翻牌對對樂', '圖片線索問答', '追蹤犯人遊戲'];
$logicTypes = ['算術邏輯力', '算菜錢遊戲', '邏輯力', '2048', '過河遊戲'];

// 構建IN子句的問號佔位符（放在try區塊外，方便函數使用）
$inPlaceholders = function(array $arr) { return implode(',', array_fill(0, count($arr), '?')); };

/**
 * 輔助函數：計算特定日期範圍的能力值平均
 */
function getAbilityAverage(
    $pdo, 
    $memberId, 
    $abilityTypes, 
    $startDate = null, 
    $endDate = null, 
    $globalStats, 
    $abilityKey
) {
    global $inPlaceholders; // 引入全局的inPlaceholders函數
    
    // 檢查 globalStats 是否已準備好
    if (!isset($globalStats[$abilityKey])) {
        // 如果 globalStats 尚未計算，無法計算能力值
        return null;
    }

    $params = $abilityTypes;
    $dateWhere = '';

    if ($startDate && $endDate) {
        $dateWhere = "AND DATE(gr.play_date) BETWEEN ? AND ?";
        $params[] = $startDate;
        $params[] = $endDate;
    } else if ($startDate) {
        $dateWhere = "AND DATE(gr.play_date) >= ?";
        $params[] = $startDate;
    } else if ($endDate) {
        $dateWhere = "AND DATE(gr.play_date) <= ?";
        $params[] = $endDate;
    }

    $memberWhere = '';
    if ($memberId) {
        $memberWhere = 'AND gr.member_id = ?';
        $params[] = $memberId;
    }

    // 查詢該能力在指定日期範圍內的平均分數和時間
    $sql = "
        SELECT 
            AVG(gr.score) AS avg_score, 
            AVG(gr.play_time) AS avg_time
        FROM game_records gr
        WHERE gr.game_type IN (" . $inPlaceholders($abilityTypes) . ")
        $dateWhere
        $memberWhere
        AND gr.score IS NOT NULL
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || $row['avg_score'] === null) {
        return null;
    }

    $avgScore = floatval($row['avg_score']);
    $avgTime = $row['avg_time'] !== null ? floatval($row['avg_time']) : null;
    
    $global = $globalStats[$abilityKey];
    
    // 計算 Z-score
    $zScore = ($global['sd_score'] > 0) ? ($avgScore - $global['mean_score']) / $global['sd_score'] : 0;
    $abilityValue = 0;

    if ($avgTime !== null && $global['sd_time'] > 0) {
        $zTime = ($global['mean_time'] - $avgTime) / $global['sd_time'];
        // 能力值：(0.3 × Z_score + 0.7 × Z_time) × 10 + 50
        $abilityValue = (0.3 * $zScore + 0.7 * $zTime) * 10 + 50;
    } else {
        // 沒有時間資料，只使用分數 Z-score
        $abilityValue = $zScore * 10 + 50;
    }
    
    // 限制在 0-100 範圍內
    return max(0, min(100, round($abilityValue, 2)));
}


try {
    // 可選參數：指定用戶、時間範圍
    $memberId = isset($_GET['member_id']) && $_GET['member_id'] !== '' ? intval($_GET['member_id']) : null;
    $range = $_GET['range'] ?? '30d';

    // 時間範圍條件
    $dateWhere = '';
    $params = [];
    if ($range !== 'all') {
        switch ($range) {
            case '7d':
                $dateWhere = 'AND gr.play_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)';
                break;
            case '30d':
                $dateWhere = 'AND gr.play_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)';
                break;
            case '90d':
                $dateWhere = 'AND gr.play_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)';
                break;
        }
    }

    $memberWhere = '';
    if ($memberId) {
        $memberWhere = 'AND gr.member_id = ?';
        $params[] = $memberId;
    }

    $message = $memberId ? "用戶 #$memberId 的個人測試結果" : "全部用戶的測試結果";

// --- 修正 #1：提前計算全域統計 ($globalStats) ---
    $globalStats = [];
    foreach (['reaction', 'memory', 'logic'] as $ability) {
        $types = ${$ability . 'Types'};
        
        // 計算該能力的全域平均值和標準差
        $globalSql = "
            SELECT 
                AVG(gr.score) AS mean_score,
                STDDEV(gr.score) AS sd_score,
                AVG(CASE WHEN gr.play_time IS NOT NULL AND gr.play_time > 0 THEN gr.play_time END) AS mean_time,
                STDDEV(CASE WHEN gr.play_time IS NOT NULL AND gr.play_time > 0 THEN gr.play_time END) AS sd_time,
                COUNT(*) AS total_plays,
                COUNT(CASE WHEN gr.play_time IS NOT NULL AND gr.play_time > 0 THEN 1 END) AS time_plays
            FROM game_records gr
            WHERE gr.game_type IN (" . $inPlaceholders($types) . ")
            AND gr.score IS NOT NULL
        ";
        
        $globalStmt = $pdo->prepare($globalSql);
        $globalStmt->execute($types);
        $globalRow = $globalStmt->fetch(PDO::FETCH_ASSOC);
        
        $globalStats[$ability] = [
            // 如果查不到數據，給予預設值，避免除以零或 Null
            'mean_score' => $globalRow['mean_score'] ? round(floatval($globalRow['mean_score']), 2) : 50,
            'sd_score' => $globalRow['sd_score'] ? round(floatval($globalRow['sd_score']), 2) : 15,
            'mean_time' => $globalRow['mean_time'] ? round(floatval($globalRow['mean_time']), 2) : 30,
            'sd_time' => $globalRow['sd_time'] ? round(floatval($globalRow['sd_time']), 2) : 10,
            'total_plays' => intval($globalRow['total_plays']),
            'time_plays' => intval($globalRow['time_plays'])
        ];
    }
// ----------------------------------------------------


    // 取得按能力分組的平均分數與次數 (此區塊不需要修改)
    $sql = "
        SELECT grp.ability, AVG(grp.score) AS avg_score, COUNT(*) AS plays
        FROM (
            SELECT 
                CASE 
                    WHEN gr.game_type IN (" . $inPlaceholders($reactionTypes) . ") THEN 'reaction'
                    WHEN gr.game_type IN (" . $inPlaceholders($memoryTypes) . ") THEN 'memory'
                    WHEN gr.game_type IN (" . $inPlaceholders($logicTypes) . ") THEN 'logic'
                    ELSE 'other'
                END AS ability,
                gr.score
            FROM game_records gr
            WHERE gr.score IS NOT NULL
            $dateWhere
            $memberWhere
        ) AS grp
        WHERE grp.ability IN ('reaction','memory','logic')
        GROUP BY grp.ability
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge($reactionTypes, $memoryTypes, $logicTypes, $params));
    $abilityAverages = [
        'reaction' => ['avg_score' => 0, 'plays' => 0],
        'memory' => ['avg_score' => 0, 'plays' => 0],
        'logic' => ['avg_score' => 0, 'plays' => 0]
    ];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $abilityAverages[$row['ability']] = [
            'avg_score' => round(floatval($row['avg_score']), 2),
            'plays' => intval($row['plays'])
        ];
    }

    // 生成趨勢數據（近N天，每日三能力平均）
    $days = $range === '7d' ? 7 : ($range === '30d' ? 30 : ($range === '90d' ? 90 : 30));
    $trendLabels = [];
    $trendReaction = [];
    $trendMemory = [];
    $trendLogic = [];
    $trendDates = [];

    // 計算每日趨勢資料
    for ($i = $days - 1; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $trendLabels[] = date('m/d', strtotime($date));
        $trendDates[] = $date;

        // 查詢當日各能力的平均分數和時間，並計算 Z-score 能力值
        $trendSql = "
            SELECT ability, AVG(score) AS avg_score, AVG(play_time) AS avg_time, COUNT(*) AS plays FROM (
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
            
            // 計算 Z-score
            $global = $globalStats[$ability];
            $zScore = ($global['sd_score'] > 0) ? ($avgScore - $global['mean_score']) / $global['sd_score'] : 0;
            
            // 處理時間 Z-score：如果沒有時間資料，只使用分數
            if ($avgTime !== null && $global['sd_time'] > 0) {
                $zTime = ($global['mean_time'] - $avgTime) / $global['sd_time'];
                // 計算能力值：(0.3 × Z_score + 0.7 × Z_time) × 10 + 50
                $abilityValue = (0.3 * $zScore + 0.7 * $zTime) * 10 + 50;
            } else {
                // 如果沒有時間資料，只使用分數 Z-score
                $abilityValue = $zScore * 10 + 50;
            }
            
            // 限制在 0-100 範圍內
            $abilityValue = max(0, min(100, $abilityValue));
            
            $day[$ability] = round($abilityValue, 2);
        }
        
        $trendReaction[] = $day['reaction'];
        $trendMemory[] = $day['memory'];
        $trendLogic[] = $day['logic'];
    }

    // 計算統計摘要（基於 Z-score 能力值）
    $stats = [
        'reaction' => ['avg' => 0, 'max' => 0, 'min' => 0, 'count' => 0],
        'memory' => ['avg' => 0, 'max' => 0, 'min' => 0, 'count' => 0],
        'logic' => ['avg' => 0, 'max' => 0, 'min' => 0, 'count' => 0]
    ];

    // 計算各能力的統計資料（基於 Z-score 計算的能力值）
    foreach (['reaction', 'memory', 'logic'] as $ability) {
        $values = ${'trend' . ucfirst($ability)};
        $validValues = array_filter($values, function($v) { return $v !== null; });
        
        if (!empty($validValues)) {
            $stats[$ability]['avg'] = round(array_sum($validValues) / count($validValues), 1);
            $stats[$ability]['max'] = round(max($validValues), 1);
            $stats[$ability]['min'] = round(min($validValues), 1);
            $stats[$ability]['count'] = count($validValues);
        }
    }


// --- 修正 #2：計算提升率相關數據 ---
    $improvementStats = [];
    $today = date('Y-m-d');
    
    foreach (['reaction', 'memory', 'logic'] as $ability) {
        $types = ${$ability . 'Types'};
        
        // 1. 取得所有遊戲記錄中最**早**的一次平均能力值 (作為「第一次平均分」)
        // 找出最早的記錄日期
        $sqlFirstDate = "SELECT MIN(DATE(gr.play_date)) AS first_date FROM game_records gr WHERE gr.game_type IN (" . $inPlaceholders($types) . ")" . ($memberId ? ' AND gr.member_id = ?' : '');
        $paramsFirst = $types;
        if ($memberId) { $paramsFirst[] = $memberId; }
        
        $stmtFirst = $pdo->prepare($sqlFirstDate);
        $stmtFirst->execute($paramsFirst);
        $firstDate = $stmtFirst->fetchColumn();

        $firstAvg = null;
        if ($firstDate) {
            // 使用「最早日期」當天或最早的那幾筆記錄作為第一次平均分。
            // 這裡使用最早的那一天數據
            $firstAvg = getAbilityAverage($pdo, $memberId, $types, $firstDate, $firstDate, $globalStats, $ability);
        }
        
        // 2. 取得**最近N天內**的平均能力值 (作為「最近平均分」)
        $recentEndDate = $today;
        $recentStartDate = date('Y-m-d', strtotime("-7 days")); // 假設「最近」指近7天的平均
        
        $recentAvg = getAbilityAverage($pdo, $memberId, $types, $recentStartDate, $recentEndDate, $globalStats, $ability);
        
        // 3. 計算提升率
        $improvementRate = null;
        if ($firstAvg !== null && $recentAvg !== null) {
            if ($firstAvg > 0) {
                 // 提升率 = (最近平均分 - 第一次平均分) / 第一次平均分 * 100%
                $improvementRate = round(($recentAvg - $firstAvg) / $firstAvg * 100, 1);
            } else if ($firstAvg === 0 && $recentAvg > 0) {
                // 如果第一次是0，但最近有提升，給予一個極高的值來標示顯著進步 (如 1000% 或直接顯示 N/A 並在前端處理)
                 $improvementRate = 1000.0;
            } else {
                // 第一次是0，最近也是0
                $improvementRate = 0.0;
            }
        }

        $improvementStats[$ability] = [
            'first_avg' => $firstAvg !== null ? round($firstAvg, 1) : null,
            'recent_avg' => $recentAvg !== null ? round($recentAvg, 1) : null,
            'improvement_rate' => $improvementRate
        ];
    }
// ----------------------------------------------------


    // 依週彙整（每7天一組），X軸改為週區間標籤
    $weeklyLabels = [];
    $weeklyReaction = [];
    $weeklyMemory = [];
    $weeklyLogic = [];

    $total = count($trendDates);
    if ($total > 0) {
        for ($start = 0; $start < $total; $start += 7) {
            $end = min($start + 6, $total - 1);
            // 週標籤使用區間：MM/DD~MM/DD
            $startLabel = date('m/d', strtotime($trendDates[$start]));
            $endLabel = date('m/d', strtotime($trendDates[$end]));
            $weeklyLabels[] = $start === $end ? $startLabel : ($startLabel . '~' . $endLabel);

            // 取這一段的平均值
            $sliceReaction = array_slice($trendReaction, $start, $end - $start + 1);
            $sliceMemory = array_slice($trendMemory, $start, $end - $start + 1);
            $sliceLogic = array_slice($trendLogic, $start, $end - $start + 1);

            $avg = function(array $arr) {
                $valid = array_filter($arr, function($v){ return $v !== null; });
                $count = count($valid);
                if ($count === 0) return null;
                return round(array_sum($valid) / $count, 2);
            };

            // 週彙整使用 Z-score 計算的能力值
            $weeklyReaction[] = $avg($sliceReaction);
            $weeklyMemory[] = $avg($sliceMemory);
            $weeklyLogic[] = $avg($sliceLogic);
        }
    }

    // 整合所有統計數據到 $finalStats
    $finalStats = $stats;
    foreach (['reaction', 'memory', 'logic'] as $ability) {
        $finalStats[$ability]['first_avg'] = $improvementStats[$ability]['first_avg'];
        $finalStats[$ability]['recent_avg'] = $improvementStats[$ability]['recent_avg'];
        $finalStats[$ability]['improvement_rate'] = $improvementStats[$ability]['improvement_rate'];
    }

    // --- 修正 #3：修正 json_encode 語法錯誤 ---
    echo json_encode([
        'success' => true,
        'range' => $range,
        'filters' => ['member_id' => $memberId],
        'labels' => $weeklyLabels,
        'reaction' => $weeklyReaction,
        'memory' => $weeklyMemory,
        'logic' => $weeklyLogic,
        'stats' => $finalStats,
        'improvement_stats' => $improvementStats,
        'global_stats' => $globalStats,
        'message' => $message,
        'debug' => [] // debug 區塊保留為空陣列
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>