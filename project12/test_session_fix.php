<?php
/**
 * 測試 Session 數據更新修復
 */

session_start();
require_once 'db_connect.php';

echo "<h2>Session 數據測試</h2>";

// 模擬用戶登入
$_SESSION['member_id'] = 1;

// 模擬答案處理
echo "<h3>模擬答案處理：</h3>";

// 初始化 session
$_SESSION['clue_total'] = 0;
$_SESSION['clue_correct'] = 0;

echo "<p>初始狀態 - 總題數: " . $_SESSION['clue_total'] . ", 答對數: " . $_SESSION['clue_correct'] . "</p>";

// 模擬3題答案處理
$answers = [
    ['user' => '黃色', 'correct' => '黃色', 'result' => '正確'],
    ['user' => '紅色', 'correct' => '藍色', 'result' => '錯誤'],
    ['user' => '綠色', 'correct' => '綠色', 'result' => '正確']
];

foreach ($answers as $i => $answer) {
    $_SESSION['clue_total']++;
    if ($answer['user'] === $answer['correct']) {
        $_SESSION['clue_correct']++;
    }
    
    echo "<p>第" . ($i+1) . "題 - 用戶答案: {$answer['user']}, 正確答案: {$answer['correct']}, 結果: {$answer['result']}</p>";
    echo "<p>當前狀態 - 總題數: " . $_SESSION['clue_total'] . ", 答對數: " . $_SESSION['clue_correct'] . "</p>";
}

// 檢查過關條件
$pass = $_SESSION['clue_correct'] >= 3;
echo "<h3>過關檢查：</h3>";
echo "<p>答對數: " . $_SESSION['clue_correct'] . " >= 3 ? " . ($pass ? '是' : '否') . "</p>";
echo "<p>過關狀態: " . ($pass ? '過關' : '未過關') . "</p>";

// 模擬遊戲結果保存
if ($pass) {
    echo "<h3>模擬遊戲結果保存：</h3>";
    
    $gameData = [
        'member_id' => $_SESSION['member_id'],
        'game_type' => '記憶力',
        'difficulty' => 'easy',
        'score' => 20,
        'play_time' => 52,
        'is_manual_exit' => false,
        'is_passed' => true,
        'game_id' => 8
    ];
    
    echo "<p>遊戲數據:</p>";
    echo "<pre>" . json_encode($gameData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
    
    // 測試 processGameResult
    require_once 'game_entry_tracker.php';
    
    try {
        $result = processGameResult($gameData);
        echo "<p>保存結果:</p>";
        echo "<pre>" . json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
        
        if ($result && $result['success']) {
            echo "<p style='color: green;'>✅ 測試成功！記錄已保存</p>";
        } else {
            echo "<p style='color: red;'>❌ 測試失敗</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ 錯誤: " . $e->getMessage() . "</p>";
    }
}

echo "<hr>";
echo "<p><a href='clue.php'>返回線索遊戲測試</a></p>";
?>
