<?php
// 添加錯誤報告
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "DB_open.php";
session_start();
header('Content-Type: application/json');

try {
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 5;
    $tab = isset($_GET['tab']) ? $_GET['tab'] : 'total';

    $tabs = [
        'total' => 'total_score',
        'reaction' => 'reaction_score',
        'memory' => 'memory_score',
        'logic' => 'logic_score',
    ];
    $score_field = isset($tabs[$tab]) ? $tabs[$tab] : 'total_score';

    // 先檢查資料庫中的實際數據
    $debug_sql = "SELECT member_id, member_name, account, total_score, reaction_score, memory_score, logic_score FROM member LIMIT 3";
    $debug_stmt = $pdo->prepare($debug_sql);
    $debug_stmt->execute();
    $debug_data = [];
    while ($row = $debug_stmt->fetch(PDO::FETCH_ASSOC)) {
        $debug_data[] = $row;
    }

    // 簡化查詢，避免複雜的SQL
    $sql = "SELECT member_id, member_name, account, $score_field AS score, avatar FROM member ORDER BY $score_field DESC LIMIT $offset, $limit";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $rankings = [];
    $rank = $offset + 1;
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $rankings[] = [
            'rank' => $rank++,
            'avatar' => !empty($row['avatar']) ? $row['avatar'] : null,
            'username' => $row['member_name'],
            'account' => $row['account'],
            'score' => intval($row['score']), // 確保是整數
            'member_id' => $row['member_id'],
        ];
    }

    // 查自己的排名
    $my_member_id = isset($_SESSION['member_id']) ? $_SESSION['member_id'] : null;
    $my_ranking = null;
    if ($my_member_id) {
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
                'score' => intval($row['score']), // 確保是整數
                'member_id' => $row['member_id'],
            ];
        }
    }

    echo json_encode([
        'rankings' => $rankings,
        'my_ranking' => $my_ranking,
        'debug' => [
            'session_member_id' => $my_member_id,
            'tab' => $tab,
            'score_field' => $score_field,
            'offset' => $offset,
            'limit' => $limit,
            'database_sample' => $debug_data, // 顯示資料庫中的實際數據
            'sql_query' => $sql
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
