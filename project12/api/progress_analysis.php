<?php
/**
 * 個人進步分析API
 * 
 * 提供用戶的能力進步趨勢分析
 */

error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');

require_once '../db.php';
require_once '../admin/progress_tracking_system.php';
require_once '../admin/weighted_scoring_system.php';

// 簡單的身份驗證（根據您的系統調整）
session_start();
$member_id = $_SESSION['member_id'] ?? null;

// 如果是管理員查看其他用戶，允許傳入member_id
if (isset($_SESSION['admin_id']) && isset($_GET['member_id'])) {
    $member_id = intval($_GET['member_id']);
}

if (!$member_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => '請先登入']);
    exit;
}

try {
    $days = isset($_GET['days']) ? intval($_GET['days']) : 30;
    $days = max(7, min(365, $days)); // 限制在7-365天之間
    
    // 初始化系統
    $weighted_scoring = new WeightedScoringSystem($pdo);
    $progress_tracking = new ProgressTrackingSystem($pdo, $weighted_scoring);
    
    // 獲取進步趨勢分析
    $progress_analysis = $progress_tracking->getProgressTrend($member_id, $days);
    
    if (!$progress_analysis) {
        throw new Exception('無法獲取進步分析數據');
    }
    
    // 獲取雷達圖數據
    $radar_data = $progress_tracking->getAbilityRadarData($member_id, $days);
    
    // 獲取用戶基本資訊
    $stmt = $pdo->prepare("SELECT member_name, total_score FROM member WHERE member_id = ?");
    $stmt->execute([$member_id]);
    $user_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $result = [
        'success' => true,
        'user_info' => $user_info,
        'progress_analysis' => $progress_analysis,
        'radar_data' => $radar_data,
        'generated_at' => date('Y-m-d H:i:s')
    ];
    
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
