<?php
// 新的排行榜API
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 在輸出任何內容前啟動session
session_start();

require_once "DB_open.php";
header('Content-Type: application/json');

try {
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 5;
    $tab = isset($_GET['tab']) ? $_GET['tab'] : 'total';
    $my_member_id = isset($_SESSION['member_id']) ? $_SESSION['member_id'] : null;

    if (!$my_member_id) {
        echo json_encode([
            'rankings' => [],
            'my_ranking' => null,
            'error' => '請先登入'
        ]);
        exit;
    }

    $tabs = [
        'total' => 'total_score',
        'reaction' => 'reaction_score',
        'memory' => 'memory_score',
        'logic' => 'logic_score',
    ];
    $score_field = isset($tabs[$tab]) ? $tabs[$tab] : 'total_score';

    // 查詢排行榜
    $sql = "SELECT member_id, member_name, account, $score_field AS score, avatar FROM member ORDER BY $score_field DESC LIMIT ?, ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$offset, $limit]);
    $rankings = [];
    $rank = $offset + 1;
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $rankings[] = [
            'rank' => $rank++,
            'avatar' => !empty($row['avatar']) ? $row['avatar'] : null,
            'username' => $row['member_name'],
            'account' => $row['account'],
            'score' => intval($row['score']),
            'member_id' => $row['member_id'],
        ];
    }

    // 查自己的排名
    $my_ranking = null;
    $sql = "SELECT member_id, member_name, account, $score_field AS score, avatar FROM member WHERE member_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$my_member_id]);
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // 計算排名
        $count_sql = "SELECT COUNT(*) as count FROM member WHERE $score_field > ?";
        $count_stmt = $pdo->prepare($count_sql);
        $count_stmt->execute([$row['score']]);
        $count_row = $count_stmt->fetch(PDO::FETCH_ASSOC);
        $rank = $count_row['count'] + 1;
        
        $my_ranking = [
            'rank' => $rank,
            'avatar' => !empty($row['avatar']) ? $row['avatar'] : null,
            'username' => $row['member_name'],
            'account' => $row['account'],
            'score' => intval($row['score']),
            'member_id' => $row['member_id'],
        ];
    }

    echo json_encode([
        'rankings' => $rankings,
        'my_ranking' => $my_ranking,
        'debug' => [
            'session_member_id' => $my_member_id,
            'tab' => $tab,
            'score_field' => $score_field,
            'offset' => $offset,
            'limit' => $limit
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => '資料庫錯誤：' . $e->getMessage(),
        'rankings' => [],
        'my_ranking' => null,
        'debug' => [
            'error_message' => $e->getMessage(),
            'error_file' => $e->getFile(),
            'error_line' => $e->getLine()
        ]
    ]);
}
?> 