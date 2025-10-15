<?php
// 禁用錯誤顯示，避免影響JSON響應
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL); // 記錄但不顯示

// 清除任何之前的輸出
if (ob_get_level()) ob_end_clean();

require_once 'db.php';

// 只在session未啟動時啟動session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

$member_id = $_SESSION['member_id'] ?? null;

if (!$member_id) {
    echo json_encode(['success' => false, 'message' => '未登入']);
    exit;
}

try {
    // 檢查今日成就限制
    $today = date('Y-m-d');
    $sql = "SELECT COUNT(*) as count FROM member_achievements ma 
            JOIN achievements a ON ma.achievement_id = a.achievement_id 
            WHERE ma.member_id = ? AND DATE(ma.earned_date) = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$member_id, $today]);
    $today_achievements = $stmt->fetchColumn();
    
    if ($today_achievements >= 3) {
        echo json_encode(['success' => false, 'message' => '今日成就已達上限']);
        exit;
    }
    
    // 獲取已完成但未領取成就的任務
    $sql = "
    SELECT mt.task_id, d.task_name, d.task_description, d.reward_achievement
    FROM member_tasks mt
    JOIN daily_tasks d ON mt.task_id = d.task_id
    WHERE mt.member_id = ? 
    AND mt.completed_date IS NOT NULL 
    AND mt.claimed_date IS NULL
    AND d.reward_achievement IS NOT NULL
    ORDER BY mt.completed_date ASC
    LIMIT 1
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$member_id]);
    $task = $stmt->fetch();
    
    if (!$task) {
        echo json_encode(['success' => false, 'message' => '沒有可領取的成就']);
        exit;
    }
    
    // 查找對應的成就
    $achievement_sql = "SELECT achievement_id, achievement_name, icon FROM achievements WHERE achievement_name = ?";
    $achievement_stmt = $pdo->prepare($achievement_sql);
    $achievement_stmt->execute([$task['reward_achievement']]);
    $achievement = $achievement_stmt->fetch();
    
    if (!$achievement) {
        echo json_encode(['success' => false, 'message' => '找不到對應的成就']);
        exit;
    }
    
    // 檢查是否已經獲得此成就
    $check_sql = "SELECT COUNT(*) FROM member_achievements WHERE member_id = ? AND achievement_id = ?";
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->execute([$member_id, $achievement['achievement_id']]);
    $already_earned = $check_stmt->fetchColumn() > 0;
    
    if ($already_earned) {
        // 標記任務為已領取
        $update_sql = "UPDATE member_tasks SET claimed_date = NOW() WHERE member_id = ? AND task_id = ?";
        $update_stmt = $pdo->prepare($update_sql);
        $update_stmt->execute([$member_id, $task['task_id']]);
        
        echo json_encode([
            'success' => true, 
            'message' => '成就已領取',
            'achievements' => [[
                'icon' => $achievement['icon'],
                'achievement_name' => $achievement['achievement_name'],
                'task_description' => $task['task_description']
            ]]
        ]);
    } else {
        // 授予成就並標記任務為已領取
        $pdo->beginTransaction();
        
        try {
            // 授予成就
            $grant_sql = "INSERT INTO member_achievements (member_id, achievement_id, achievement_name) VALUES (?, ?, ?)";
            $grant_stmt = $pdo->prepare($grant_sql);
            $grant_stmt->execute([$member_id, $achievement['achievement_id'], $achievement['achievement_name']]);
            
            // 標記任務為已領取
            $update_sql = "UPDATE member_tasks SET claimed_date = NOW() WHERE member_id = ? AND task_id = ?";
            $update_stmt = $pdo->prepare($update_sql);
            $update_stmt->execute([$member_id, $task['task_id']]);
            
            $pdo->commit();
            
            echo json_encode([
                'success' => true, 
                'message' => '成就領取成功',
                'achievements' => [[
                    'icon' => $achievement['icon'],
                    'achievement_name' => $achievement['achievement_name'],
                    'task_description' => $task['task_description']
                ]]
            ]);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => '領取失敗：' . $e->getMessage()]);
}
?>
