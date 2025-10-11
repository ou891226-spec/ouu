<?php
// 關閉錯誤顯示，避免HTML輸出
error_reporting(0);
ini_set('display_errors', 0);

session_start();
require_once '../../db.php';

header('Content-Type: application/json');

// 僅允許已登入管理員
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => '未授權']);
    exit;
}

try {
    // 可選參數：指定用戶、時間範圍
    $memberId = isset($_GET['member_id']) && $_GET['member_id'] !== '' ? intval($_GET['member_id']) : null;
    $range = $_GET['range'] ?? '30d';

    // 生成模擬數據用於測試
    $days = $range === '7d' ? 7 : ($range === '30d' ? 30 : 30);
    $trendLabels = [];
    $trendReaction = [];
    $trendMemory = [];
    $trendLogic = [];
    // 保存原始日期（YYYY-MM-DD）以便週彙整
    $trendDates = [];

    // 根據是否選擇特定用戶生成不同的數據
    if ($memberId) {
        // 個人數據：更穩定的趨勢，模擬個人能力發展
        $baseReaction = 45 + ($memberId % 20); // 基於用戶ID的基礎值
        $baseMemory = 50 + ($memberId % 15);
        $baseLogic = 40 + ($memberId % 25);
        
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $trendLabels[] = date('m/d', strtotime($date));
            $trendDates[] = $date;
            
            // 個人數據：較小的波動，模擬個人進步
            $trendReaction[] = round($baseReaction + rand(-8, 8) + sin($i * 0.1) * 5, 2);
            $trendMemory[] = round($baseMemory + rand(-6, 6) + cos($i * 0.15) * 4, 2);
            $trendLogic[] = round($baseLogic + rand(-10, 10) + sin($i * 0.12) * 6, 2);
        }
        
        $stats = [
            'reaction' => ['avg' => $baseReaction + 2, 'max' => $baseReaction + 15, 'min' => $baseReaction - 10, 'count' => $days],
            'memory' => ['avg' => $baseMemory + 1, 'max' => $baseMemory + 12, 'min' => $baseMemory - 8, 'count' => $days],
            'logic' => ['avg' => $baseLogic + 3, 'max' => $baseLogic + 18, 'min' => $baseLogic - 12, 'count' => $days]
        ];
        
        $message = "用戶 #$memberId 的個人測試結果";
    } else {
        // 全部用戶數據：更大的波動，模擬整體趨勢
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $trendLabels[] = date('m/d', strtotime($date));
            $trendDates[] = $date;
            
            $trendReaction[] = round(50 + rand(-20, 20) + sin($i * 0.2) * 10, 2);
            $trendMemory[] = round(50 + rand(-15, 15) + cos($i * 0.3) * 8, 2);
            $trendLogic[] = round(50 + rand(-25, 25) + sin($i * 0.25) * 12, 2);
        }
        
        $stats = [
            'reaction' => ['avg' => 52.3, 'max' => 78.5, 'min' => 28.1, 'count' => $days],
            'memory' => ['avg' => 48.7, 'max' => 72.3, 'min' => 25.4, 'count' => $days],
            'logic' => ['avg' => 51.2, 'max' => 85.6, 'min' => 18.9, 'count' => $days]
        ];
        
        $message = "全部用戶的測試結果";
    }

    $globalStats = [
        'reaction' => ['mean_score' => 50, 'sd_score' => 15, 'mean_time' => 30, 'sd_time' => 10],
        'memory' => ['mean_score' => 45, 'sd_score' => 12, 'mean_time' => 25, 'sd_time' => 8],
        'logic' => ['mean_score' => 55, 'sd_score' => 18, 'mean_time' => 35, 'sd_time' => 12]
    ];

    // 依週彙整（每7天一組），X軸改為週區間標籤
    // 將最舊 -> 最新的每日資料分段為週
    $weeklyLabels = [];
    $weeklyReaction = [];
    $weeklyMemory = [];
    $weeklyLogic = [];

    $total = count($trendDates);
    if ($total > 0) {
        for ($start = 0; $start < $total; $start += 7) {
            $end = min($start + 6, $total - 1);
            // 週標籤使用區間：MM/DD~MM/DD
            $startLabel = date('m/d', strtotime($trendDates[$start]));
            $endLabel = date('m/d', strtotime($trendDates[$end]));
            $weeklyLabels[] = $start === $end ? $startLabel : ($startLabel . '~' . $endLabel);

            // 取這一段的平均值
            $sliceReaction = array_slice($trendReaction, $start, $end - $start + 1);
            $sliceMemory = array_slice($trendMemory, $start, $end - $start + 1);
            $sliceLogic = array_slice($trendLogic, $start, $end - $start + 1);

            $avg = function(array $arr) {
                $valid = array_filter($arr, function($v){ return $v !== null; });
                $count = count($valid);
                if ($count === 0) return null;
                return round(array_sum($valid) / $count, 2);
            };

            $weeklyReaction[] = $avg($sliceReaction);
            $weeklyMemory[] = $avg($sliceMemory);
            $weeklyLogic[] = $avg($sliceLogic);
        }
    }

    echo json_encode([
        'success' => true,
        'range' => $range,
        'filters' => ['member_id' => $memberId],
        'labels' => $weeklyLabels,
        'reaction' => $weeklyReaction,
        'memory' => $weeklyMemory,
        'logic' => $weeklyLogic,
        'stats' => $stats,
        'global_stats' => $globalStats,
        'message' => $message
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>