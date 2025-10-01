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
    // 獲取請求數據
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        throw new Exception('無效的 JSON 數據');
    }
    
    // 引入必要的文件
    $base_path = dirname(__DIR__);
    require_once $base_path . '/db_connect.php';
    
    if (!function_exists('processGameResult')) {
        require_once $base_path . '/game_entry_tracker.php';
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
