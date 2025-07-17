<?php
require_once "DB_open.php";
session_start();
header('Content-Type: application/json');

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

// 用 ROW_NUMBER() 查全體排名
$sql = "SELECT * FROM (
    SELECT member_id, member_name, account, $score_field AS score, avatar,
           ROW_NUMBER() OVER (ORDER BY $score_field DESC) AS rank
    FROM member
) t
ORDER BY rank ASC
LIMIT :offset, :limit";
$stmt = $pdo->prepare($sql);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->execute();
$rankings = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $rankings[] = [
        'rank' => intval($row['rank']),
        'avatar' => !empty($row['avatar']) ? $row['avatar'] : null,
        'username' => $row['member_name'],
        'account' => $row['account'],
        'score' => $row['score'],
        'member_id' => $row['member_id'],
    ];
}

// 查自己的全體排名
$my_member_id = isset($_SESSION['member_id']) ? $_SESSION['member_id'] : null;
$my_ranking = null;
if ($my_member_id) {
    $sql = "SELECT * FROM (
        SELECT member_id, member_name, account, $score_field AS score, avatar,
               ROW_NUMBER() OVER (ORDER BY $score_field DESC) AS rank
        FROM member
    ) t WHERE member_id = :my_member_id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':my_member_id', $my_member_id, PDO::PARAM_INT);
    $stmt->execute();
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $my_ranking = [
            'rank' => intval($row['rank']),
            'avatar' => !empty($row['avatar']) ? $row['avatar'] : null,
            'username' => $row['member_name'],
            'account' => $row['account'],
            'score' => $row['score'],
            'member_id' => $row['member_id'],
        ];
    }
}

echo json_encode([
    'rankings' => $rankings,
    'my_ranking' => $my_ranking,
    'debug' => [
        'session_member_id' => $my_member_id,
        'my_ranking_raw' => $my_ranking
    ]
]); 