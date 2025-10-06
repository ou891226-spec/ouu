<?php
/**
 * 簡化的AI分析 - 無需外部API
 */

// 使用輸出淨化工具
require_once __DIR__ . '/../output_cleaner.php';
initCleanOutput();

session_start();
require_once __DIR__ . '/../db.php';

// 檢查登入狀態
if (!isset($_SESSION['member_id'])) {
    outputCleanJson(['success' => false, 'message' => '請先登入']);
}

$member_id = $_SESSION['member_id'];

try {
    // 獲取用戶數據
    $score_sql = "SELECT reaction_score, memory_score, logic_score, total_score FROM member WHERE member_id = ?";
    $score_stmt = $pdo->prepare($score_sql);
    $score_stmt->execute([$member_id]);
    $scores = $score_stmt->fetch();
    
    if (!$scores) {
        throw new Exception('找不到用戶數據');
    }
    
    // 獲取平均分數
    $avg_sql = "SELECT 
        AVG(reaction_score) as avg_reaction,
        AVG(memory_score) as avg_memory,
        AVG(logic_score) as avg_logic
        FROM member 
        WHERE reaction_score > 0 OR memory_score > 0 OR logic_score > 0";
    $avg_stmt = $pdo->query($avg_sql);
    $avg_result = $avg_stmt->fetch();
    
    $reaction = $scores['reaction_score'];
    $memory = $scores['memory_score'];
    $logic = $scores['logic_score'];
    $total = $scores['total_score'];
    
    $avg_reaction = $avg_result['avg_reaction'] ?: 1;
    $avg_memory = $avg_result['avg_memory'] ?: 1;
    $avg_logic = $avg_result['avg_logic'] ?: 1;
    
    // 智能分析
    $player_type = "穩健發展型玩家";
    $description = "根據您的遊戲表現分析，";
    $suggestions = "建議持續練習以提升認知能力。";
    
    if ($reaction > $avg_reaction * 1.2) {
        $player_type = "反應敏捷型玩家";
        $description .= "您具有出色的反應速度和手眼協調能力。";
        $suggestions = "建議多挑戰反應力類遊戲來維持優勢：看字選色遊戲、接金蛋遊戲、節奏遊戲。這些遊戲都能有效訓練您的快速反應能力。";
    } elseif ($memory > $avg_memory * 1.2) {
        $player_type = "記憶力優異型玩家";
        $description .= "您展現出優秀的記憶能力。";
        $suggestions = "建議多玩記憶力類遊戲來保持優勢：翻牌對對樂、追蹤犯人遊戲、線索遊戲。這些遊戲能進一步強化您的記憶和觀察能力。";
    } elseif ($logic > $avg_logic * 1.2) {
        $player_type = "邏輯思維型玩家";
        $description .= "您具有強大的邏輯思維能力。";
        $suggestions = "建議多挑戰算術邏輯類遊戲來發展優勢：算菜錢遊戲、2048遊戲、過河遊戲。這些遊戲需要策略思考和數學運算，能進一步提升您的邏輯能力。";
    } elseif ($reaction > $avg_reaction && $memory > $avg_memory && $logic > $avg_logic) {
        $player_type = "全能型玩家";
        $description .= "您在反應速度、記憶力和邏輯思維三個方面都表現出色。";
        $suggestions = "建議挑戰所有9款遊戲保持全面發展：反應力類(看字選色、接金蛋、節奏遊戲)、記憶力類(翻牌對對樂、追蹤犯人、線索遊戲)、算術邏輯類(算菜錢、2048、過河遊戲)。";
    }
    
    $description .= "建議持續練習以維持認知健康。";
    
    $ai_analysis = [
        'type' => $player_type,
        'description' => $description,
        'suggestions' => [$suggestions], // 轉換為數組格式
        'scores' => [
            'reaction' => $reaction,
            'memory' => $memory,
            'logic' => $logic,
            'total' => $total
        ],
        'analysis_type' => 'simple_analysis',
        'ai_enhanced' => false
    ];
    
    outputCleanJson([
        'success' => true,
        'data' => $ai_analysis
    ]);
    
} catch (Exception $e) {
    outputCleanJson([
        'success' => false,
        'message' => '分析失敗：' . $e->getMessage()
    ]);
}
?>
