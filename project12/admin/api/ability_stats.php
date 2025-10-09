<?php
session_start();
require_once '../../db.php';

header('Content-Type: application/json');

// 僅允許已登入管理員
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => '未授權']);
    exit;
}

try {
    // 可選參數：指定用戶、時間範圍
    $memberId = isset($_GET['member_id']) && $_GET['member_id'] !== '' ? intval($_GET['member_id']) : null;
    $range = $_GET['range'] ?? '90d'; // 7d, 30d, 90d, all

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
            default:
                $dateWhere = 'AND gr.play_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)';
        }
    }

    $memberWhere = '';
    if ($memberId) {
        $memberWhere = 'AND gr.member_id = ?';
        $params[] = $memberId;
    }

    // 將遊戲類型映射到三項能力
    // reaction: 接金蛋/節奏/看字選色
    // memory: 翻牌對對樂/圖片線索問答/追蹤犯人
    // logic: 算菜錢/過河/2048
    $reactionTypes = ['反應力', '接金蛋遊戲', '接金蛋', '節奏遊戲', '看字選色遊戲'];
    $memoryTypes = ['記憶力', '翻牌對對樂', '圖片線索問答', '追蹤犯人遊戲'];
    $logicTypes = ['算術邏輯力', '算菜錢遊戲', '邏輯力', '2048', '過河遊戲'];

    // 構建IN子句
    $inPlaceholders = function(array $arr) { return implode(',', array_fill(0, count($arr), '?')); };

    // 注意：各 IN 子句需分別綁定對應的陣列，不能用合併後的陣列

    // 取得按能力分組的平均分數與次數
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

    // 取得每位用戶首次 vs 最近表現對比（用於趨勢/提升分析）
    $compareSql = "
        WITH filtered AS (
            SELECT gr.member_id, gr.game_type, gr.score, DATE(gr.play_date) AS d
            FROM game_records gr
            WHERE gr.score IS NOT NULL
            $dateWhere
            $memberWhere
        ), labeled AS (
            SELECT 
                member_id,
                CASE 
                    WHEN game_type IN (" . $inPlaceholders($reactionTypes) . ") THEN 'reaction'
                    WHEN game_type IN (" . $inPlaceholders($memoryTypes) . ") THEN 'memory'
                    WHEN game_type IN (" . $inPlaceholders($logicTypes) . ") THEN 'logic'
                    ELSE 'other'
                END AS ability,
                score,
                d,
                ROW_NUMBER() OVER(PARTITION BY member_id, 
                    CASE 
                        WHEN game_type IN (" . $inPlaceholders($reactionTypes) . ") THEN 'reaction'
                        WHEN game_type IN (" . $inPlaceholders($memoryTypes) . ") THEN 'memory'
                        WHEN game_type IN (" . $inPlaceholders($logicTypes) . ") THEN 'logic'
                        ELSE 'other'
                    END
                ORDER BY d ASC) AS rn_first,
                ROW_NUMBER() OVER(PARTITION BY member_id, 
                    CASE 
                        WHEN game_type IN (" . $inPlaceholders($reactionTypes) . ") THEN 'reaction'
                        WHEN game_type IN (" . $inPlaceholders($memoryTypes) . ") THEN 'memory'
                        WHEN game_type IN (" . $inPlaceholders($logicTypes) . ") THEN 'logic'
                        ELSE 'other'
                    END
                ORDER BY d DESC) AS rn_last
            FROM filtered
        )
        SELECT ability,
               AVG(CASE WHEN rn_first = 1 THEN score END) AS first_avg,
               AVG(CASE WHEN rn_last = 1 THEN score END) AS last_avg
        FROM labeled
        WHERE ability IN ('reaction','memory','logic')
        GROUP BY ability
    ";

    // 共有 3 組 CASE（能力映射、rn_first 分區、rn_last 分區），每組包含 3 個 IN 子句
    $compareParams = array_merge(
        $reactionTypes, $memoryTypes, $logicTypes,
        $reactionTypes, $memoryTypes, $logicTypes,
        $reactionTypes, $memoryTypes, $logicTypes
    );

    $stmt2 = $pdo->prepare($compareSql);
    $stmt2->execute(array_merge($compareParams, $params));

    $improvements = [
        'reaction' => ['first_avg' => null, 'last_avg' => null, 'delta' => null],
        'memory' => ['first_avg' => null, 'last_avg' => null, 'delta' => null],
        'logic' => ['first_avg' => null, 'last_avg' => null, 'delta' => null]
    ];
    while ($row = $stmt2->fetch(PDO::FETCH_ASSOC)) {
        $first = isset($row['first_avg']) ? floatval($row['first_avg']) : null;
        $last = isset($row['last_avg']) ? floatval($row['last_avg']) : null;
        $delta = ($first !== null && $last !== null) ? round($last - $first, 2) : null;
        $improvements[$row['ability']] = [
            'first_avg' => $first !== null ? round($first, 2) : null,
            'last_avg' => $last !== null ? round($last, 2) : null,
            'delta' => $delta
        ];
    }

    // 生成趨勢數據（近N天，每日三能力平均）
    $days = $range === '7d' ? 7 : ($range === '30d' ? 30 : 30);
    $trendLabels = [];
    $trendReaction = [];
    $trendMemory = [];
    $trendLogic = [];

    // 同時計算「正規化後的每日強度％」與其 7 日移動平均
    $trendStrengthReaction = [];
    $trendStrengthMemory = [];
    $trendStrengthLogic = [];
    $ma7StrengthReaction = [];
    $ma7StrengthMemory = [];
    $ma7StrengthLogic = [];

    $minSamplesPerDay = 3; // 少於門檻則視為無效樣本

    for ($i = $days - 1; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $trendLabels[] = date('m/d', strtotime($date));

        // 原始平均（不正規化）
        $trendSql = "
            SELECT ability, AVG(score) AS avg_score, COUNT(*) AS plays FROM (
                SELECT 
                    CASE 
                        WHEN gr.game_type IN (" . $inPlaceholders($reactionTypes) . ") THEN 'reaction'
                        WHEN gr.game_type IN (" . $inPlaceholders($memoryTypes) . ") THEN 'memory'
                        WHEN gr.game_type IN (" . $inPlaceholders($logicTypes) . ") THEN 'logic'
                        ELSE 'other'
                    END AS ability,
                    gr.score
                FROM game_records gr
                WHERE DATE(gr.play_date) = ?
                " . ($memberId ? 'AND gr.member_id = ?' : '') . "
            ) t
            WHERE ability IN ('reaction','memory','logic')
            GROUP BY ability
        ";

        $trendParams = array_merge($reactionTypes, $memoryTypes, $logicTypes, [$date]);
        if ($memberId) { $trendParams[] = $memberId; }

        $stmtT = $pdo->prepare($trendSql);
        $stmtT->execute($trendParams);
        $day = ['reaction' => null, 'memory' => null, 'logic' => null];
        $plays = ['reaction' => 0, 'memory' => 0, 'logic' => 0];
        while ($row = $stmtT->fetch(PDO::FETCH_ASSOC)) {
            $day[$row['ability']] = $row['avg_score'] !== null ? round(floatval($row['avg_score']), 2) : null;
            $plays[$row['ability']] = isset($row['plays']) ? intval($row['plays']) : 0;
        }
        $trendReaction[] = $day['reaction'];
        $trendMemory[] = $day['memory'];
        $trendLogic[] = $day['logic'];

        // 正規化日平均（沿用前台分析的簡化規則）：>1000 視為 100，>100 視為 /10，其餘原值；最後截到 0..100
        $normSql = "
            SELECT ability, AVG(norm_score) AS avg_norm, COUNT(*) AS plays FROM (
                SELECT 
                    CASE 
                        WHEN gr.game_type IN (" . $inPlaceholders($reactionTypes) . ") THEN 'reaction'
                        WHEN gr.game_type IN (" . $inPlaceholders($memoryTypes) . ") THEN 'memory'
                        WHEN gr.game_type IN (" . $inPlaceholders($logicTypes) . ") THEN 'logic'
                        ELSE 'other'
                    END AS ability,
                    CASE 
                        WHEN gr.score > 1000 THEN 100
                        WHEN gr.score > 100 THEN gr.score/10
                        ELSE gr.score
                    END AS norm_score
                FROM game_records gr
                WHERE DATE(gr.play_date) = ?
                " . ($memberId ? 'AND gr.member_id = ?' : '') . "
            ) s
            WHERE ability IN ('reaction','memory','logic')
            GROUP BY ability
        ";

        $stmtN = $pdo->prepare($normSql);
        $stmtN->execute($trendParams);
        $normDay = ['reaction' => null, 'memory' => null, 'logic' => null];
        $normPlays = ['reaction' => 0, 'memory' => 0, 'logic' => 0];
        while ($row = $stmtN->fetch(PDO::FETCH_ASSOC)) {
            $val = $row['avg_norm'] !== null ? floatval($row['avg_norm']) : null;
            $val = $val !== null ? max(0, min(100, $val)) : null;
            $normDay[$row['ability']] = $val !== null ? round($val, 2) : null;
            $normPlays[$row['ability']] = isset($row['plays']) ? intval($row['plays']) : 0;
        }
        $trendStrengthReaction[] = ($normDay['reaction'] !== null && $normPlays['reaction'] >= $minSamplesPerDay) ? $normDay['reaction'] : null;
        $trendStrengthMemory[]   = ($normDay['memory']   !== null && $normPlays['memory']   >= $minSamplesPerDay) ? $normDay['memory']   : null;
        $trendStrengthLogic[]    = ($normDay['logic']    !== null && $normPlays['logic']    >= $minSamplesPerDay) ? $normDay['logic']    : null;
    }

    // 計算 7 日移動平均（忽略 null；若視窗內皆為 null，則為 null）
    $computeMA = function(array $values, int $window) {
        $result = [];
        $n = count($values);
        for ($i = 0; $i < $n; $i++) {
            $sum = 0.0; $cnt = 0;
            for ($j = max(0, $i - $window + 1); $j <= $i; $j++) {
                if ($values[$j] !== null) { $sum += $values[$j]; $cnt++; }
            }
            $result[] = $cnt > 0 ? round($sum / $cnt, 2) : null;
        }
        return $result;
    };

    $ma7StrengthReaction = $computeMA($trendStrengthReaction, 7);
    $ma7StrengthMemory   = $computeMA($trendStrengthMemory, 7);
    $ma7StrengthLogic    = $computeMA($trendStrengthLogic, 7);

    // 可選：健康評估對照（若有）
    $health = null;
    try {
        $healthSql = "SELECT 
                AVG(memory_score) AS memory,
                AVG(reaction_score) AS reaction,
                AVG(logic_score) AS logic
            FROM health_assessments " . ($memberId ? 'WHERE member_id = ?' : '');
        $stmtH = $pdo->prepare($healthSql);
        if ($memberId) { $stmtH->execute([$memberId]); } else { $stmtH->execute(); }
        $healthRow = $stmtH->fetch(PDO::FETCH_ASSOC);
        if ($healthRow) {
            $health = [
                'reaction' => $healthRow['reaction'] !== null ? round(floatval($healthRow['reaction']), 2) : null,
                'memory' => $healthRow['memory'] !== null ? round(floatval($healthRow['memory']), 2) : null,
                'logic' => $healthRow['logic'] !== null ? round(floatval($healthRow['logic']), 2) : null,
            ];
        }
    } catch (Exception $e) {
        $health = null;
    }

    // 能力強度（0-100）計算：使用第95百分位數作為基準，更抗極端值
    $strength = [ 'reaction' => 0.0, 'memory' => 0.0, 'logic' => 0.0 ];
    
    // 計算第95百分位數的函數
    function percentile95($pdo, $column, $whereClause = '', $params = []) {
        try {
            // 先取得所有分數
            $sql = "SELECT {$column} FROM member WHERE {$column} > 0 {$whereClause} ORDER BY {$column}";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $scores = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (empty($scores)) return 0;
            
            $n = count($scores);
            $pos = 0.95 * ($n - 1);
            $lo = floor($pos);
            $hi = ceil($pos);
            
            if ($lo == $hi) {
                return floatval($scores[$lo]);
            }
            
            return floatval($scores[$lo]) + ($pos - $lo) * (floatval($scores[$hi]) - floatval($scores[$lo]));
        } catch (Exception $e) {
            return 0;
        }
    }
    
    try {
        // 計算各能力的第95百分位數
        $p95Reaction = percentile95($pdo, 'reaction_score');
        $p95Memory   = percentile95($pdo, 'memory_score');
        $p95Logic    = percentile95($pdo, 'logic_score');

        if ($memberId) {
            // 指定使用者：用該使用者的 member 分數做正規化
            $scoreStmt = $pdo->prepare("SELECT reaction_score, memory_score, logic_score FROM member WHERE member_id = ?");
            $scoreStmt->execute([$memberId]);
            $score = $scoreStmt->fetch(PDO::FETCH_ASSOC) ?: ['reaction_score' => 0, 'memory_score' => 0, 'logic_score' => 0];

            $strength['reaction'] = $p95Reaction > 0 ? min(100, max(0, floatval($score['reaction_score']) / $p95Reaction * 100)) : 0.0;
            $strength['memory']   = $p95Memory   > 0 ? min(100, max(0, floatval($score['memory_score'])   / $p95Memory   * 100)) : 0.0;
            $strength['logic']    = $p95Logic    > 0 ? min(100, max(0, floatval($score['logic_score'])    / $p95Logic    * 100)) : 0.0;
        } else {
            // 全部用戶：以所有有分數使用者的平均分數對第95百分位數做正規化
            $avgScoreSql = "SELECT 
                    AVG(reaction_score) AS avg_reaction,
                    AVG(memory_score)   AS avg_memory,
                    AVG(logic_score)    AS avg_logic
                FROM member
                WHERE reaction_score > 0 OR memory_score > 0 OR logic_score > 0";
            $avgScoreStmt = $pdo->query($avgScoreSql);
            $avgScoreRow = $avgScoreStmt->fetch(PDO::FETCH_ASSOC) ?: ['avg_reaction' => 0, 'avg_memory' => 0, 'avg_logic' => 0];

            $avgReaction = floatval($avgScoreRow['avg_reaction'] ?? 0);
            $avgMemory   = floatval($avgScoreRow['avg_memory'] ?? 0);
            $avgLogic    = floatval($avgScoreRow['avg_logic'] ?? 0);

            $strength['reaction'] = $p95Reaction > 0 ? min(100, max(0, $avgReaction / $p95Reaction * 100)) : 0.0;
            $strength['memory']   = $p95Memory   > 0 ? min(100, max(0, $avgMemory   / $p95Memory   * 100)) : 0.0;
            $strength['logic']    = $p95Logic    > 0 ? min(100, max(0, $avgLogic    / $p95Logic    * 100)) : 0.0;
        }
        // 四捨五入到小數點一位
        $strength['reaction'] = round($strength['reaction'], 1);
        $strength['memory']   = round($strength['memory'], 1);
        $strength['logic']    = round($strength['logic'], 1);
    } catch (Exception $e) {
        // 若失敗則維持 0 值
    }

    echo json_encode([
        'success' => true,
        'range' => $range,
        'filters' => [ 'member_id' => $memberId ],
        'summary' => $abilityAverages,
        'improvement' => $improvements,
        'trend' => [
            'labels' => $trendLabels,
            'reaction' => $trendReaction,
            'memory' => $trendMemory,
            'logic' => $trendLogic
        ],
        'trend_strength' => [
            'labels' => $trendLabels,
            'reaction' => $trendStrengthReaction,
            'memory' => $trendStrengthMemory,
            'logic' => $trendStrengthLogic
        ],
        'trend_strength_ma7' => [
            'labels' => $trendLabels,
            'reaction' => $ma7StrengthReaction,
            'memory' => $ma7StrengthMemory,
            'logic' => $ma7StrengthLogic
        ],
        'strength' => $strength,
        'health' => $health
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>


