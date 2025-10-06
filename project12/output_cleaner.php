<?php
/**
 * PHP輸出淨化工具
 * 確保JSON輸出絕對純淨，無任何多餘字符
 */

/**
 * 初始化純淨輸出環境
 * 必須在所有其他輸出之前調用
 */
function initCleanOutput() {
    // 啟動輸出緩衝
    ob_start();
    
    // 禁用錯誤顯示
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(E_ALL);
    
    // 設置JSON頭部
    header('Content-Type: application/json; charset=utf-8');
}

/**
 * 清理輸出緩衝並確保純淨輸出
 * 在輸出JSON之前調用
 */
function cleanOutput() {
    // 清理所有輸出緩衝
    while (ob_get_level() > 1) {
        ob_end_clean();
    }
    
    // 清理當前緩衝
    if (ob_get_level()) {
        ob_end_clean();
    }
}

/**
 * 安全輸出JSON
 * 自動清理輸出緩衝並輸出JSON
 */
function outputCleanJson($data) {
    cleanOutput();
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
?>
