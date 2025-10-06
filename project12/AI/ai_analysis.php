<?php
/**
 * AI分析API - 調用Node.js微服務
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
    $user_data = getUserDataForAI($member_id);
    
    // 調用Node.js AI服務
    $ai_analysis = callAIService($user_data);
    
    outputCleanJson([
        'success' => true,
        'data' => $ai_analysis
    ]);
    
} catch (Exception $e) {
    // AI服務不可用時，返回錯誤而不是模板
    outputCleanJson([
        'success' => false,
        'message' => 'AI分析服務暫時不可用，請稍後再試：' . $e->getMessage()
    ]);
}

function getUserDataForAI($member_id) {
    global $pdo;
    
    // 獲取基本分數
    $score_sql = "SELECT reaction_score, memory_score, logic_score, total_score FROM member WHERE member_id = ?";
    $score_stmt = $pdo->prepare($score_sql);
    $score_stmt->execute([$member_id]);
    $scores = $score_stmt->fetch();
    
    // 獲取所有用戶的平均分數
    $avg_sql = "SELECT 
        AVG(reaction_score) as avg_reaction,
        AVG(memory_score) as avg_memory,
        AVG(logic_score) as avg_logic
        FROM member 
        WHERE reaction_score > 0 OR memory_score > 0 OR logic_score > 0";
    $avg_stmt = $pdo->query($avg_sql);
    $avg_result = $avg_stmt->fetch();
    
    // 獲取詳細遊戲統計
    $game_stats_sql = "
        SELECT 
            game_type,
            COUNT(*) as total_games,
            AVG(score) as avg_score,
            MAX(score) as max_score,
            SUM(play_time) as total_time
        FROM game_records 
        WHERE member_id = ? 
        GROUP BY game_type
    ";
    $game_stmt = $pdo->prepare($game_stats_sql);
    $game_stmt->execute([$member_id]);
    $game_stats = $game_stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_games = array_sum(array_column($game_stats, 'total_games'));
    
    // 獲取最近活躍度數據（最近30天的遊戲次數）
    $recent_activity_sql = "
        SELECT COUNT(*) as recent_games,
               AVG(score) as recent_avg_score
        FROM game_records 
        WHERE member_id = ? 
        AND play_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ";
    $recent_stmt = $pdo->prepare($recent_activity_sql);
    $recent_stmt->execute([$member_id]);
    $recent_activity = $recent_stmt->fetch();
    
    // 獲取最高分數數據
    $max_scores_sql = "
        SELECT 
            MAX(reaction_score) as max_reaction,
            MAX(memory_score) as max_memory,
            MAX(logic_score) as max_logic
        FROM member
        WHERE reaction_score > 0 OR memory_score > 0 OR logic_score > 0
    ";
    $max_stmt = $pdo->query($max_scores_sql);
    $max_scores = $max_stmt->fetch();
    
    // 計算能力等級
    $reaction_level = calculateSimpleLevel($scores['reaction_score'], $avg_result['avg_reaction']);
    $memory_level = calculateSimpleLevel($scores['memory_score'], $avg_result['avg_memory']);
    $logic_level = calculateSimpleLevel($scores['logic_score'], $avg_result['avg_logic']);
    
    return [
        'reaction_level' => $reaction_level,
        'memory_level' => $memory_level,
        'logic_level' => $logic_level,
        'reaction_score' => $scores['reaction_score'],
        'memory_score' => $scores['memory_score'],
        'logic_score' => $scores['logic_score'],
        'total_score' => $scores['total_score'],
        'total_games' => $total_games,
        'game_stats' => $game_stats,
        // 平均值數據
        'avg_reaction' => round($avg_result['avg_reaction'], 2),
        'avg_memory' => round($avg_result['avg_memory'], 2),
        'avg_logic' => round($avg_result['avg_logic'], 2),
        // 最高分數數據
        'max_reaction' => $max_scores['max_reaction'],
        'max_memory' => $max_scores['max_memory'],
        'max_logic' => $max_scores['max_logic'],
        // 最近活躍度數據
        'recent_games' => $recent_activity['recent_games'],
        'recent_avg_score' => round($recent_activity['recent_avg_score'], 2),
        // 動態計算的能力比例
        'ability_ratios' => [
            'reaction_to_avg' => round($scores['reaction_score'] / $avg_result['avg_reaction'], 2),
            'memory_to_avg' => round($scores['memory_score'] / $avg_result['avg_memory'], 2),
            'logic_to_avg' => round($scores['logic_score'] / $avg_result['avg_logic'], 2)
        ]
    ];
}

function calculateSimpleLevel($score, $avg_score) {
    if ($avg_score <= 0) return 1;
    
    $ratio = $score / $avg_score;
    if ($ratio >= 2.0) return 10;
    if ($ratio >= 1.5) return 8;
    if ($ratio >= 1.0) return 5;
    if ($ratio >= 0.5) return 3;
    return 1;
}

function getAIServiceURL() {
    // 檢測運行環境
    $is_local = (
        $_SERVER['SERVER_NAME'] === 'localhost' || 
        $_SERVER['SERVER_NAME'] === '127.0.0.1' ||
        strpos($_SERVER['SERVER_NAME'], 'localhost') !== false ||
        strpos($_SERVER['HTTP_HOST'], 'localhost') !== false ||
        strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false
    );
    
    if ($is_local) {
        // 本地環境：使用localhost
        return 'http://localhost:3001/analyze';
    } else {
        // Azure環境：使用環境變量或默認配置
        $ai_service_url = $_ENV['AI_SERVICE_URL'] ?? $_SERVER['AI_SERVICE_URL'] ?? 'https://smartfun-ai-service-fvanfkgnawayagha.eastasia-01.azurewebsites.net';
        
        if ($ai_service_url) {
            // 使用環境變量中配置的AI服務URL
            return rtrim($ai_service_url, '/') . '/analyze';
        } else {
            // 默認Azure配置：假設AI服務在同一App Service的不同端口
            $base_url = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'];
            $ai_port = $_ENV['AI_SERVICE_PORT'] ?? $_SERVER['AI_SERVICE_PORT'] ?? '3001';
            
            // 嘗試構建AI服務URL
            if (strpos($base_url, 'azurewebsites.net') !== false) {
                // Azure App Service環境
                return $base_url . ':' . $ai_port . '/analyze';
            } else {
                // 其他環境，嘗試localhost
                return 'http://localhost:' . $ai_port . '/analyze';
            }
        }
    }
}

function callAIService($user_data) {
    // 智能檢測環境並設置正確的AI服務URL
    $url = getAIServiceURL();
    
    $data = [
        'userData' => $user_data
    ];
    
    // 使用cURL替代file_get_contents
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen(json_encode($data))
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    // 檢查cURL錯誤
    if (curl_errno($ch)) {
        curl_close($ch);
        throw new Exception('cURL Error: ' . curl_error($ch));
    }
    
    curl_close($ch);
    
    // 檢查HTTP狀態碼
    if ($httpCode !== 200) {
        throw new Exception("AI服務HTTP錯誤: $httpCode");
    }
    
    if ($response === FALSE) {
        throw new Exception('AI服務連接失敗');
    }
    
    // 檢查回應是否為純JSON
    $result = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        // 記錄原始回應以便調試
        error_log('AI服務非JSON回應: ' . substr($response, 0, 200));
        throw new Exception('AI服務返回非JSON格式');
    }
    
    if (!$result || !isset($result['success']) || !$result['success']) {
        throw new Exception('AI分析失敗: ' . ($result['message'] ?? '未知錯誤'));
    }
    
    // 解析AI回應 - 現在是結構化的JSON格式
    $ai_analysis = $result['analysis'] ?? [];
    
    // 確保回應格式正確
    if (is_array($ai_analysis)) {
        return [
            'type' => $ai_analysis['type'] ?? 'AI智能分析玩家',
            'description' => $ai_analysis['description'] ?? 'AI分析完成，您的表現很棒！',
            'suggestions' => $ai_analysis['suggestions'] ?? ['繼續保持遊戲習慣', '嘗試不同類型的遊戲來提升各項能力'],
            'ai_enhanced' => true
        ];
    } else {
        // 備用格式（如果AI返回的不是結構化數據）
        return [
            'type' => 'AI智能分析玩家',
            'description' => $ai_analysis,
            'suggestions' => ['繼續保持遊戲習慣', '嘗試不同類型的遊戲來提升各項能力'],
            'ai_enhanced' => true
        ];
    }
}
?>
