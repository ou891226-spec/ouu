<?php
/**
 * 測試加權分數系統
 */

require_once 'db.php';
require_once 'admin/weighted_scoring_system.php';

echo "<h2>🎯 加權分數系統測試</h2>\n";

try {
    // 初始化加權分數系統
    $weighted_scoring = new WeightedScoringSystem($pdo);
    
    echo "<h3>📊 測試1: 獲取基準時間</h3>\n";
    
    // 測試幾個遊戲的基準時間
    $test_games = ['算菜錢遊戲', '接金蛋遊戲', '翻牌對對樂', '2048'];
    
    foreach ($test_games as $game_type) {
        $baseline_time = $weighted_scoring->getBaselineTime($game_type);
        echo "🎮 <strong>{$game_type}</strong>: " . ($baseline_time ? $baseline_time . "秒" : "未設定") . "<br>\n";
    }
    
    echo "<h3>🧮 測試2: 分數計算範例</h3>\n";
    
    // 測試分數計算
    $test_cases = [
        [
            'game_type' => '算菜錢遊戲',
            'base_score' => 80,
            'actual_time' => 60, // 比基準快10秒
            'accuracy_rate' => 0.9,
            'difficulty' => 'normal'
        ],
        [
            'game_type' => '接金蛋遊戲',
            'base_score' => 100,
            'actual_time' => 25, // 比基準快5秒
            'accuracy_rate' => 1.0,
            'difficulty' => 'hard'
        ],
        [
            'game_type' => '翻牌對對樂',
            'base_score' => 60,
            'actual_time' => 70, // 比基準慢10秒
            'accuracy_rate' => 0.8,
            'difficulty' => 'easy'
        ]
    ];
    
    foreach ($test_cases as $i => $case) {
        echo "<h4>範例 " . ($i + 1) . ": {$case['game_type']}</h4>\n";
        echo "輸入參數:<br>\n";
        echo "- 基礎分數: {$case['base_score']}<br>\n";
        echo "- 實際時間: {$case['actual_time']}秒<br>\n";
        echo "- 準確率: " . ($case['accuracy_rate'] * 100) . "%<br>\n";
        echo "- 難度: {$case['difficulty']}<br>\n";
        
        $result = $weighted_scoring->calculateWeightedScore(
            $case['game_type'],
            $case['base_score'],
            $case['actual_time'],
            $case['accuracy_rate'],
            $case['difficulty']
        );
        
        echo "<strong>計算結果:</strong><br>\n";
        echo "- 基準時間: {$result['baseline_time']}秒<br>\n";
        echo "- 時間加權係數: {$result['time_weight']}<br>\n";
        echo "- 難度係數: {$result['difficulty_multiplier']}<br>\n";
        echo "- 改善百分比: {$result['improvement_percentage']}%<br>\n";
        echo "- <strong>最終分數: {$result['final_score']}</strong><br>\n";
        echo "<hr>\n";
    }
    
    echo "<h3>📈 測試3: 系統統計</h3>\n";
    
    // 獲取所有基準時間設定
    $all_baselines = $weighted_scoring->getAllBaselineTimes();
    echo "已設定的遊戲數量: " . count($all_baselines) . "<br>\n";
    
    if (!empty($all_baselines)) {
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
        echo "<tr><th>遊戲類型</th><th>基準時間</th><th>階段</th></tr>\n";
        
        foreach ($all_baselines as $game_type => $data) {
            echo "<tr>\n";
            echo "<td>{$game_type}</td>\n";
            echo "<td>{$data['baseline_time']}秒</td>\n";
            echo "<td>{$data['stage']}</td>\n";
            echo "</tr>\n";
        }
        
        echo "</table>\n";
    }
    
    echo "<h3>✅ 測試完成</h3>\n";
    echo "系統運行正常！可以開始使用加權分數功能了。\n";
    
} catch (Exception $e) {
    echo "<h3>❌ 測試失敗</h3>\n";
    echo "錯誤訊息: " . $e->getMessage() . "<br>\n";
    echo "請檢查資料庫連接和表結構。\n";
}
?>
