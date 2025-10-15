<?php
/**
 * 每日能力分析批次處理腳本
 * 此腳本應該通過 Cron Job 每日執行
 * 
 * 功能：
 * 1. 從 weighted_scores 表計算每日各用戶的能力分數
 * 2. 檢查是否達到 10 筆門檻
 * 3. 更新 ability_analysis 表
 */

require_once 'db_connect.php';

echo "=== 每日能力分析批次處理 ===" . PHP_EOL;
echo "執行時間: " . date('Y-m-d H:i:s') . PHP_EOL . PHP_EOL;

try {
    // 每日批次分析 SQL 邏輯
    $dailyAnalysisSQL = "
    INSERT INTO ability_analysis (
        member_id, analysis_date, reaction_score, memory_score, logic_score,
        reaction_plays, memory_plays, logic_plays, total_plays, is_valid_analysis
    )
    SELECT 
        ws.member_id,
        CURDATE() AS analysis_date,
        -- 計算各能力的平均分數 (AVG + CASE WHEN)
        AVG(CASE WHEN gam.ability_category = 'reaction' THEN ws.final_score END) AS reaction_score,
        AVG(CASE WHEN gam.ability_category = 'memory' THEN ws.final_score END) AS memory_score,
        AVG(CASE WHEN gam.ability_category = 'logic' THEN ws.final_score END) AS logic_score,
        -- 計算各能力的遊玩筆數 (SUM + 隱式轉換)
        SUM(gam.ability_category = 'reaction') AS reaction_plays,
        SUM(gam.ability_category = 'memory') AS memory_plays,
        SUM(gam.ability_category = 'logic') AS logic_plays,
        COUNT(*) AS total_plays,
        -- 檢查所有三力是否都達到 10 筆的當日遊玩門檻
        CASE 
            WHEN SUM(gam.ability_category = 'reaction') >= 10
              AND SUM(gam.ability_category = 'memory') >= 10
              AND SUM(gam.ability_category = 'logic') >= 10
            THEN TRUE
            ELSE FALSE
        END AS is_valid_analysis
    FROM weighted_scores ws
    JOIN game_ability_map gam ON ws.game_type = gam.game_type COLLATE utf8mb4_unicode_ci
    WHERE DATE(ws.play_date) = CURDATE() -- 只處理當日數據
    GROUP BY ws.member_id
    ON DUPLICATE KEY UPDATE
        reaction_score = VALUES(reaction_score),
        memory_score = VALUES(memory_score),
        logic_score = VALUES(logic_score),
        reaction_plays = VALUES(reaction_plays),
        memory_plays = VALUES(memory_plays),
        logic_plays = VALUES(logic_plays),
        total_plays = VALUES(total_plays),
        is_valid_analysis = VALUES(is_valid_analysis),
        updated_at = CURRENT_TIMESTAMP
    ";
    
    echo "執行每日分析..." . PHP_EOL;
    $stmt = $pdo->prepare($dailyAnalysisSQL);
    $result = $stmt->execute();
    
    if ($result) {
        $affectedRows = $stmt->rowCount();
        echo "✅ 成功處理 $affectedRows 筆用戶記錄" . PHP_EOL;
    } else {
        echo "❌ 執行失敗" . PHP_EOL;
    }
    
    // 顯示今日分析結果統計
    echo PHP_EOL . "=== 今日分析結果統計 ===" . PHP_EOL;
    
    $statsSQL = "
    SELECT 
        COUNT(*) as total_users,
        SUM(CASE WHEN is_valid_analysis = TRUE THEN 1 ELSE 0 END) as valid_analysis_users,
        SUM(CASE WHEN is_valid_analysis = FALSE THEN 1 ELSE 0 END) as invalid_analysis_users,
        AVG(reaction_score) as avg_reaction_score,
        AVG(memory_score) as avg_memory_score,
        AVG(logic_score) as avg_logic_score
    FROM ability_analysis 
    WHERE analysis_date = CURDATE()
    ";
    
    $stmt = $pdo->query($statsSQL);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($stats) {
        echo "總用戶數: " . $stats['total_users'] . PHP_EOL;
        echo "有效分析用戶: " . $stats['valid_analysis_users'] . " (三力都達到10筆門檻)" . PHP_EOL;
        echo "無效分析用戶: " . $stats['invalid_analysis_users'] . " (未達到門檻)" . PHP_EOL;
        echo "平均反應力分數: " . round($stats['avg_reaction_score'], 2) . PHP_EOL;
        echo "平均記憶力分數: " . round($stats['avg_memory_score'], 2) . PHP_EOL;
        echo "平均邏輯力分數: " . round($stats['avg_logic_score'], 2) . PHP_EOL;
    }
    
    // 顯示今日詳細記錄
    echo PHP_EOL . "=== 今日詳細記錄 ===" . PHP_EOL;
    
    $detailSQL = "
    SELECT 
        aa.member_id,
        m.member_name,
        aa.reaction_score,
        aa.memory_score,
        aa.logic_score,
        aa.reaction_plays,
        aa.memory_plays,
        aa.logic_plays,
        aa.total_plays,
        aa.is_valid_analysis
    FROM ability_analysis aa
    JOIN member m ON aa.member_id = m.member_id
    WHERE aa.analysis_date = CURDATE()
    ORDER BY aa.total_plays DESC
    LIMIT 10
    ";
    
    $stmt = $pdo->query($detailSQL);
    $details = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($details) {
        echo "前10名用戶今日表現:" . PHP_EOL;
        foreach($details as $detail) {
            $validText = $detail['is_valid_analysis'] ? '✅' : '❌';
            echo sprintf(
                "  %s %s: 反應力=%.1f(%d) 記憶力=%.1f(%d) 邏輯力=%.1f(%d) 總次數=%d %s",
                $validText,
                $detail['member_name'],
                $detail['reaction_score'],
                $detail['reaction_plays'],
                $detail['memory_score'],
                $detail['memory_plays'],
                $detail['logic_score'],
                $detail['logic_plays'],
                $detail['total_plays'],
                $detail['is_valid_analysis'] ? '(有效分析)' : '(無效分析)'
            ) . PHP_EOL;
        }
    }
    
    echo PHP_EOL . "每日能力分析批次處理完成！" . PHP_EOL;
    
} catch (Exception $e) {
    echo "執行失敗: " . $e->getMessage() . PHP_EOL;
    echo "錯誤詳情: " . $e->getTraceAsString() . PHP_EOL;
}
?>

