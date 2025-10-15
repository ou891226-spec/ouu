<?php
/**
 * 保存AI分析結果到數據庫
 */

// 使用輸出淨化工具
require_once 'output_cleaner.php';
initCleanOutput();

session_start();
require_once 'db_connect.php';

// 檢查是否登入
if (!isset($_SESSION['member_id'])) {
    outputCleanJson([
        'success' => false,
        'message' => '尚未登入'
    ]);
}

$member_id = $_SESSION['member_id'];

// 獲取POST數據
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    outputCleanJson([
        'success' => false,
        'message' => '無效的請求數據'
    ]);
}

try {
    // 準備插入數據
    $player_type = $data['report']['type'] ?? '';
    $description = $data['report']['description'] ?? '';
    $suggestions = isset($data['report']['suggestions']) ? json_encode($data['report']['suggestions'], JSON_UNESCAPED_UNICODE) : '[]';
    $ai_enhanced = isset($data['report']['ai_enhanced']) && $data['report']['ai_enhanced'] ? 1 : 0;
    
    // 能力分數
    $reaction_score = $data['reaction'] ?? 0;
    $memory_score = $data['memory'] ?? 0;
    $logic_score = $data['logic'] ?? 0;
    
    // 遊戲次數
    $reaction_games = $data['stats']['reaction_games'] ?? 0;
    $memory_games = $data['stats']['memory_games'] ?? 0;
    $logic_games = $data['stats']['logic_games'] ?? 0;
    
    // 插入記錄
    $insert_sql = "
        INSERT INTO ai_analysis_history (
            member_id, 
            analysis_type,
            player_type, 
            description, 
            suggestions,
            reaction_score,
            memory_score,
            logic_score,
            reaction_games,
            memory_games,
            logic_games,
            ai_enhanced,
            created_at
        ) VALUES (
            :member_id,
            'ability_analysis',
            :player_type,
            :description,
            :suggestions,
            :reaction_score,
            :memory_score,
            :logic_score,
            :reaction_games,
            :memory_games,
            :logic_games,
            :ai_enhanced,
            NOW()
        )
    ";
    
    $stmt = $pdo->prepare($insert_sql);
    $stmt->execute([
        ':member_id' => $member_id,
        ':player_type' => $player_type,
        ':description' => $description,
        ':suggestions' => $suggestions,
        ':reaction_score' => $reaction_score,
        ':memory_score' => $memory_score,
        ':logic_score' => $logic_score,
        ':reaction_games' => $reaction_games,
        ':memory_games' => $memory_games,
        ':logic_games' => $logic_games,
        ':ai_enhanced' => $ai_enhanced
    ]);
    
    $analysis_id = $pdo->lastInsertId();
    
    outputCleanJson([
        'success' => true,
        'message' => 'AI分析已保存',
        'analysis_id' => $analysis_id,
        'saved_at' => date('Y-m-d H:i:s')
    ]);
    
} catch (PDOException $e) {
    error_log("保存AI分析失敗: " . $e->getMessage());
    outputCleanJson([
        'success' => false,
        'message' => '保存失敗：' . $e->getMessage()
    ]);
}
?>

