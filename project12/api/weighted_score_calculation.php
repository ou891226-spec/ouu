<?php
/**
 * 加權分數計算API
 * 
 * 接收遊戲結果並計算動態基準時間加權分數
 * 
 * POST 參數:
 * - member_id: 用戶ID
 * - game_type: 遊戲類型
 * - base_score: 基礎分數 (遊戲表現分數)
 * - actual_time: 實際完成時間(秒)
 * - difficulty: 難度 (easy/normal/hard)
 * - accuracy_rate: 準確率 (0-1，可選，預設1.0)
 * - is_manual_exit: 是否手動退出 (可選，預設false)
 * - session_token: 會話驗證 (可選)
 */

error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');

// 檢查請求方法
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => '僅支援POST請求']);
    exit;
}

require_once '../db.php';
require_once '../admin/weighted_scoring_system.php';

// 驗證必要參數
$required_params = ['member_id', 'game_type', 'base_score', 'actual_time', 'difficulty'];
$missing_params = [];

foreach ($required_params as $param) {
    if (!isset($_POST[$param]) || $_POST[$param] === '') {
        $missing_params[] = $param;
    }
}

if (!empty($missing_params)) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => '缺少必要參數: ' . implode(', ', $missing_params)
    ]);
    exit;
}

try {
    // 獲取參數
    $member_id = intval($_POST['member_id']);
    $game_type = trim($_POST['game_type']);
    $base_score = floatval($_POST['base_score']);
    $actual_time = floatval($_POST['actual_time']);
    $difficulty = trim($_POST['difficulty']);
    $accuracy_rate = isset($_POST['accuracy_rate']) ? floatval($_POST['accuracy_rate']) : 1.0;
    $is_manual_exit = isset($_POST['is_manual_exit']) ? filter_var($_POST['is_manual_exit'], FILTER_VALIDATE_BOOLEAN) : false;
    
    // 基本驗證
    if ($member_id <= 0) {
        throw new Exception('無效的用戶ID');
    }
    
    if ($actual_time < 0) {
        throw new Exception('遊戲時間不能為負數');
    }
    
    if ($accuracy_rate < 0 || $accuracy_rate > 1) {
        throw new Exception('準確率必須在0-1之間');
    }
    
    // 檢查用戶是否存在
    $stmt = $pdo->prepare("SELECT member_id FROM member WHERE member_id = ?");
    $stmt->execute([$member_id]);
    if (!$stmt->fetch()) {
        throw new Exception('用戶不存在');
    }
    
    // 初始化加權分數系統
    $weighted_scoring = new WeightedScoringSystem($pdo);
    
    // 如果是手動退出，直接給0分
    if ($is_manual_exit) {
        $result = [
            'success' => true,
            'message' => '手動退出，無分數',
            'scoring_result' => [
                'game_type' => $game_type,
                'base_score' => 0,
                'accuracy_rate' => 0,
                'baseline_time' => null,
                'actual_time' => $actual_time,
                'time_weight' => 0,
                'difficulty_multiplier' => 0,
                'final_score' => 0,
                'improvement_percentage' => 0,
                'manual_exit' => true
            ]
        ];
        
        echo json_encode($result);
        exit;
    }
    
    // 計算加權分數
    $scoring_result = $weighted_scoring->calculateWeightedScore(
        $game_type, 
        $base_score, 
        $actual_time, 
        $difficulty,
        false // 不啟用防刷分
    );
    
    // 儲存加權分數歷史
    $weighted_scoring->saveWeightedScoreHistory($member_id, $scoring_result, $difficulty);
    
    // 更新用戶總分 (這裡需要根據您的現有系統邏輯調整)
    $update_total_score = updateMemberTotalScore($pdo, $member_id, $scoring_result['final_score'], $game_type);
    
    // 記錄到遊戲紀錄表 (保持與現有系統的兼容性)
    $record_game = recordGameResult($pdo, $member_id, $game_type, $scoring_result['final_score'], $actual_time, $difficulty);
    
    // 回傳結果
    $result = [
        'success' => true,
        'message' => '分數計算完成',
        'scoring_result' => $scoring_result,
        'database_updated' => $update_total_score && $record_game,
        'formula' => '最終分數 = 基礎分 × 時間加權係數 × 難度係數'
    ];
    
    echo json_encode($result);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage(),
        'error_code' => 'CALCULATION_ERROR'
    ]);
}

/**
 * 更新用戶總分
 */
function updateMemberTotalScore($pdo, $member_id, $score_to_add, $game_type) {
    try {
        // 判斷遊戲類型並更新對應的能力分數
        $ability_field = getAbilityField($game_type);
        
        if ($ability_field) {
            $stmt = $pdo->prepare("
                UPDATE member 
                SET $ability_field = $ability_field + ?, 
                    total_score = total_score + ? 
                WHERE member_id = ?
            ");
            return $stmt->execute([$score_to_add, $score_to_add, $member_id]);
        } else {
            // 如果無法分類，只更新總分
            $stmt = $pdo->prepare("
                UPDATE member 
                SET total_score = total_score + ? 
                WHERE member_id = ?
            ");
            return $stmt->execute([$score_to_add, $member_id]);
        }
    } catch (Exception $e) {
        error_log("更新用戶總分失敗: " . $e->getMessage());
        return false;
    }
}

/**
 * 根據遊戲類型獲取對應的能力欄位
 */
function getAbilityField($game_type) {
    $reaction_types = ['反應力', '接金蛋遊戲', '接金蛋', '節奏遊戲', '看字選色遊戲'];
    $memory_types = ['記憶力', '翻牌對對樂', '圖片線索問答', '追蹤犯人遊戲'];
    $logic_types = ['算術邏輯力', '算菜錢遊戲', '邏輯力', '2048', '過河遊戲'];
    
    if (in_array($game_type, $reaction_types)) {
        return 'reaction_score';
    } elseif (in_array($game_type, $memory_types)) {
        return 'memory_score';
    } elseif (in_array($game_type, $logic_types)) {
        return 'logic_score';
    }
    
    return null;
}

/**
 * 記錄遊戲結果到遊戲紀錄表
 */
function recordGameResult($pdo, $member_id, $game_type, $score, $play_time, $difficulty) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO game_records 
            (member_id, game_type, score, play_time, difficulty, status, play_date) 
            VALUES (?, ?, ?, ?, ?, 'completed', NOW())
        ");
        return $stmt->execute([$member_id, $game_type, $score, $play_time, $difficulty]);
    } catch (Exception $e) {
        error_log("記錄遊戲結果失敗: " . $e->getMessage());
        return false;
    }
}
?>
