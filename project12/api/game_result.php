<?php
/**
 * 遊戲結果 API 端點
 * 統一處理所有遊戲的結果保存
 */

// 清除所有輸出緩衝
while (ob_get_level()) {
    ob_end_clean();
}

// 禁用錯誤顯示
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// 處理 OPTIONS 請求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// 只允許 POST 請求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => '只允許 POST 請求']);
    exit();
}

try {
    // === START: 修復 sendBeacon 接收邏輯 ===
    $data = null;
    
    // 優先處理來自 sendBeacon (FormData) 的數據
    // sendBeacon 使用 FormData 時，數據會放在 $_POST['data'] 中
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['data'])) {
        $data = json_decode($_POST['data'], true);
        error_log("收到 sendBeacon FormData: " . print_r($data, true));
    }
    
    // 其次處理來自普通 AJAX (JSON) 的數據
    if (!$data) {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        error_log("收到 AJAX JSON: " . print_r($data, true));
    }
    
    if (!$data) {
        // 如果兩種方式都沒成功，則拋出錯誤
        throw new Exception('無法解析請求數據，請求可能為空或格式錯誤。');
    }
    // === END: 修復 sendBeacon 接收邏輯 ===
    
    // 引入必要的文件
    $base_path = dirname(__DIR__);
    require_once $base_path . '/db_connect.php';
    
    if (!function_exists('processGameResult')) {
        require_once $base_path . '/game_entry_tracker.php';
    }
    
    // 檢查是否為重複的強制退出記錄
    if (isset($data['is_manual_exit']) && $data['is_manual_exit'] === true) {
        // 檢查最近3秒內是否已有相同用戶的強制退出記錄
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count FROM game_records 
            WHERE member_id = ? AND status = 'failed' 
            AND play_date >= DATE_SUB(NOW(), INTERVAL 3 SECOND)
        ");
        $stmt->execute([$data['member_id']]);
        $duplicate_count = $stmt->fetch()['count'];
        
        if ($duplicate_count > 0) {
            // 發現重複記錄，直接返回成功但不寫入數據庫
            error_log('檢測到重複的強制退出記錄，跳過寫入: member_id=' . $data['member_id']);
            echo json_encode([
                'success' => true,
                'message' => '重複記錄已跳過',
                'duplicate' => true
            ]);
            exit();
        }
    }
    
    // 使用通用處理函數
    $result = processGameResult($data);
    
    error_log('API處理結果: ' . json_encode($result));
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log('API處理錯誤: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => '處理失敗: ' . $e->getMessage()
    ]);
}
?>
