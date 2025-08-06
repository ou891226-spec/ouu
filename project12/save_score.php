<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['member_id'])) {
    echo json_encode(['success' => false, 'message' => '未登入']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $member_id = $_SESSION['member_id'];
    $score = isset($_POST['score']) ? intval($_POST['score']) : 0;
    $game_type = isset($_POST['game_type']) ? $_POST['game_type'] : '';
    
    if ($score < 0) {
        echo json_encode(['success' => false, 'message' => '分數不能為負數']);
        exit;
    }
    
    try {
        // 更新總分
        $sql = "UPDATE member SET total_score = total_score + ? WHERE member_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$score, $member_id]);
        
        // 根據遊戲類型更新對應的分數欄位
        $category_column = '';
        switch ($game_type) {
            case '記憶力':
            case '翻牌對對樂':
                $category_column = 'memory_score';
                break;
            case '節奏遊戲':
                $category_column = 'rhythm_score';
                break;
            case '2048':
                $category_column = 'game_2048_score';
                break;
            case '接金蛋遊戲':
                $category_column = 'catch_egg_score';
                break;
            case '追蹤犯人遊戲':
                $category_column = 'prisoner_score';
                break;
            case '看字選色遊戲':
                $category_column = 'text_color_score';
                break;
            case '算菜錢遊戲':
                $category_column = 'vegetable_cost_score';
                break;
        }
        
        if ($category_column) {
            $sql = "UPDATE member SET $category_column = $category_column + ? WHERE member_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$score, $member_id]);
        }
        
        echo json_encode(['success' => true, 'message' => '分數已儲存']);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => '儲存分數失敗: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => '無效的請求方法']);
}
?>





