<?php
require_once 'db.php';

try {
    echo "開始同步分類分數...\n";
    
    // 先重置所有分類分數為0
    $reset_sql = "UPDATE member SET reaction_score = 0, memory_score = 0, logic_score = 0";
    $reset_stmt = $pdo->prepare($reset_sql);
    $reset_stmt->execute();
    echo "✅ 已重置所有分類分數\n";
    
    // 根據遊戲記錄更新分類分數
    $update_sql = "
        UPDATE member m 
        SET 
            reaction_score = (
                SELECT COALESCE(SUM(score), 0) 
                FROM game_records gr 
                WHERE gr.member_id = m.member_id 
                AND gr.game_type IN ('反應力', '節奏遊戲', '看字選色遊戲', '接金蛋遊戲')
            ),
            memory_score = (
                SELECT COALESCE(SUM(score), 0) 
                FROM game_records gr 
                WHERE gr.member_id = m.member_id 
                AND gr.game_type IN ('記憶力', '翻牌對對樂', '追蹤犯人遊戲', '圖片線索問答')
            ),
            logic_score = (
                SELECT COALESCE(SUM(score), 0) 
                FROM game_records gr 
                WHERE gr.member_id = m.member_id 
                AND gr.game_type IN ('算術邏輯', '2048', '算菜錢遊戲', '邏輯力')
            )
    ";
    
    $update_stmt = $pdo->prepare($update_sql);
    $update_stmt->execute();
    
    echo "✅ 已同步所有遊戲記錄到分類分數\n";
    
    // 顯示同步結果
    $result_sql = "SELECT member_id, member_name, total_score, reaction_score, memory_score, logic_score FROM member ORDER BY total_score DESC LIMIT 10";
    $result_stmt = $pdo->prepare($result_sql);
    $result_stmt->execute();
    $results = $result_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\n同步結果（前10名）：\n";
    echo "ID\t姓名\t\t總分\t反應力\t記憶力\t邏輯力\n";
    echo "----------------------------------------\n";
    foreach ($results as $row) {
        printf("%d\t%s\t%d\t%d\t%d\t%d\n", 
            $row['member_id'], 
            $row['member_name'], 
            $row['total_score'], 
            $row['reaction_score'], 
            $row['memory_score'], 
            $row['logic_score']
        );
    }
    
    echo "\n✅ 分類分數同步完成！\n";
    
} catch (Exception $e) {
    echo "錯誤：" . $e->getMessage() . "\n";
}
?> 