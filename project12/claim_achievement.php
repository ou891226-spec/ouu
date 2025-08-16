<?php
require_once 'db.php';
session_start();

header('Content-Type: application/json');

$member_id = $_SESSION['member_id'] ?? 0;

if (!$member_id) {
    echo json_encode(['success' => false, 'message' => '未登入']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';
    
    if ($action === 'claim_achievement') {
        try {
            $pdo->beginTransaction();
            
            // 查找已完成但未領取獎勵的任務
            $completed_tasks_sql = "
                SELECT mt.task_id, d.task_description, d.reward_achievement
                FROM member_tasks mt
                JOIN daily_tasks d ON mt.task_id = d.task_id
                WHERE mt.member_id = ? AND mt.completed_date IS NOT NULL AND mt.claimed_date IS NULL
                ORDER BY mt.completed_date DESC
            ";
            $completed_tasks_stmt = $pdo->prepare($completed_tasks_sql);
            $completed_tasks_stmt->execute([$member_id]);
            $completed_tasks = $completed_tasks_stmt->fetchAll();
            
            $claimed_achievements = [];
            
            foreach ($completed_tasks as $task) {
                if ($task['reward_achievement']) {
                    // 查找成就ID
                    $achievement_sql = "SELECT achievement_id, achievement_name, icon FROM achievements WHERE achievement_name = ?";
                    $achievement_stmt = $pdo->prepare($achievement_sql);
                    $achievement_stmt->execute([$task['reward_achievement']]);
                    $achievement = $achievement_stmt->fetch();
                    
                    if ($achievement) {
                        // 檢查是否已經獲得此成就
                        $check_sql = "SELECT COUNT(*) as count FROM member_achievements WHERE member_id = ? AND achievement_id = ?";
                        $check_stmt = $pdo->prepare($check_sql);
                        $check_stmt->execute([$member_id, $achievement['achievement_id']]);
                        $exists = $check_stmt->fetchColumn() > 0;
                        
                        if (!$exists) {
                            // 授予成就
                            $grant_sql = "INSERT INTO member_achievements (member_id, achievement_id, earned_date) VALUES (?, ?, NOW())";
                            $grant_stmt = $pdo->prepare($grant_sql);
                            $grant_stmt->execute([$member_id, $achievement['achievement_id']]);
                            
                            $claimed_achievements[] = [
                                'achievement_name' => $achievement['achievement_name'],
                                'icon' => $achievement['icon'],
                                'task_description' => $task['task_description']
                            ];
                        }
                        
                        // 標記任務為已領取
                        $claim_sql = "UPDATE member_tasks SET claimed_date = NOW() WHERE member_id = ? AND task_id = ?";
                        $claim_stmt = $pdo->prepare($claim_sql);
                        $claim_stmt->execute([$member_id, $task['task_id']]);
                    }
                }
            }
            
            $pdo->commit();
            
            if (!empty($claimed_achievements)) {
                echo json_encode([
                    'success' => true,
                    'message' => '成就領取成功',
                    'achievements' => $claimed_achievements
                ]);
            } else {
                echo json_encode([
                    'success' => true,
                    'message' => '沒有可領取的成就',
                    'achievements' => []
                ]);
            }
            
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => '領取失敗：' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => '無效的操作']);
    }
} else {
    echo json_encode(['success' => false, 'message' => '無效的請求方法']);
}
?>
