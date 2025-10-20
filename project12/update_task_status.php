<?php
// 禁用錯誤顯示，避免影響JSON響應
ini_set('display_errors', 0);
error_reporting(E_ALL);

// 設定台灣時區
date_default_timezone_set('Asia/Taipei');

require_once 'db.php';

// 啟動 session（如果尚未啟動）
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 設定回應標頭
header('Content-Type: application/json');

// 獲取用戶ID
$member_id = $_SESSION['member_id'] ?? null;

if (!$member_id) {
    echo json_encode(['success' => false, 'message' => '用戶未登入']);
    exit;
}

// 獲取 POST 資料
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => '無效的請求資料']);
    exit;
}

$game_id = $input['game_id'] ?? null;
$difficulty = $input['difficulty'] ?? null;
$score = $input['score'] ?? 0;
$play_time = $input['play_time'] ?? 0;

try {
    // 開始交易
    $pdo->beginTransaction();
    
    // 獲取用戶的所有待完成任務
    $tasks_sql = "
        SELECT mt.task_id, mt.member_id, d.task_name, d.task_description, d.task_type
        FROM member_tasks mt
        JOIN daily_tasks d ON mt.task_id = d.task_id
        WHERE mt.member_id = ? 
        AND mt.status = 'pending'
        AND (mt.completed_date IS NULL OR DATE(mt.completed_date) != CURDATE())
        AND d.is_active = 1
    ";
    
    $stmt = $pdo->prepare($tasks_sql);
    $stmt->execute([$member_id]);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $updated_tasks = [];
    
    foreach ($tasks as $task) {
        $task_id = $task['task_id'];
        $task_name = $task['task_name'];
        $task_description = $task['task_description'];
        $is_completed = false;
        
        // 根據任務類型檢查是否完成
        switch ($task_name) {
            case '遊戲達人':
                if (strpos($task_description, '10局') !== false) {
                    $count_sql = "SELECT COUNT(*) FROM game_records WHERE member_id = ? AND DATE(play_date) = CURDATE()";
                    $count_stmt = $pdo->prepare($count_sql);
                    $count_stmt->execute([$member_id]);
                    $game_count = $count_stmt->fetchColumn();
                    $is_completed = $game_count >= 10;
                }
                break;
                
            case '遊戲大師':
                if (strpos($task_description, '25個關卡') !== false) {
                    $count_sql = "SELECT COUNT(*) FROM game_records WHERE member_id = ? AND DATE(play_date) = CURDATE()";
                    $count_stmt = $pdo->prepare($count_sql);
                    $count_stmt->execute([$member_id]);
                    $game_count = $count_stmt->fetchColumn();
                    $is_completed = $game_count >= 25;
                }
                break;
                
            case '績分高手':
                if (strpos($task_description, '總分達到50分') !== false) {
                    $score_sql = "SELECT COALESCE(SUM(score), 0) FROM game_records WHERE member_id = ? AND DATE(play_date) = CURDATE()";
                    $score_stmt = $pdo->prepare($score_sql);
                    $score_stmt->execute([$member_id]);
                    $total_score = $score_stmt->fetchColumn();
                    $is_completed = $total_score >= 50;
                }
                break;
                
            case '進階者':
                if (strpos($task_description, '總分達到500分') !== false) {
                    $score_sql = "SELECT COALESCE(SUM(score), 0) FROM game_records WHERE member_id = ? AND DATE(play_date) = CURDATE()";
                    $score_stmt = $pdo->prepare($score_sql);
                    $score_stmt->execute([$member_id]);
                    $total_score = $score_stmt->fetchColumn();
                    $is_completed = $total_score >= 500;
                }
                break;
                
            case '持久戰士':
                if (strpos($task_description, '分鐘') !== false) {
                    $time_sql = "SELECT COALESCE(SUM(play_time), 0) FROM game_records WHERE member_id = ? AND DATE(play_date) = CURDATE()";
                    $time_stmt = $pdo->prepare($time_sql);
                    $time_stmt->execute([$member_id]);
                    $total_time = $time_stmt->fetchColumn();
                    $is_completed = $total_time >= 300; // 5分鐘 = 300秒
                }
                break;
                
            case '簡單專家':
                if (strpos($task_description, '簡單難度') !== false) {
                    $count_sql = "SELECT COUNT(*) FROM game_records WHERE member_id = ? AND DATE(play_date) = CURDATE() AND difficulty = 'easy'";
                    $count_stmt = $pdo->prepare($count_sql);
                    $count_stmt->execute([$member_id]);
                    $game_count = $count_stmt->fetchColumn();
                    $is_completed = $game_count >= 10;
                }
                break;
                
            case '普通大師':
                if (strpos($task_description, '普通難度') !== false) {
                    $count_sql = "SELECT COUNT(*) FROM game_records WHERE member_id = ? AND DATE(play_date) = CURDATE() AND difficulty = 'normal'";
                    $count_stmt = $pdo->prepare($count_sql);
                    $count_stmt->execute([$member_id]);
                    $game_count = $count_stmt->fetchColumn();
                    $is_completed = $game_count >= 10;
                }
                break;
                
            case '困難王者':
                if (strpos($task_description, '困難難度') !== false) {
                    $count_sql = "SELECT COUNT(*) FROM game_records WHERE member_id = ? AND DATE(play_date) = CURDATE() AND difficulty = 'hard'";
                    $count_stmt = $pdo->prepare($count_sql);
                    $count_stmt->execute([$member_id]);
                    $game_count = $count_stmt->fetchColumn();
                    $is_completed = $game_count >= 5;
                }
                break;
        }
        
        // 如果任務完成，更新狀態
        if ($is_completed) {
            $update_sql = "UPDATE member_tasks SET completed_date = NOW() WHERE task_id = ? AND member_id = ?";
            $update_stmt = $pdo->prepare($update_sql);
            $update_stmt->execute([$task_id, $member_id]);
            
            $updated_tasks[] = [
                'task_id' => $task_id,
                'task_name' => $task_name,
                'completed' => true
            ];
        }
    }
    
    // 提交交易
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => '任務狀態更新完成',
        'updated_tasks' => $updated_tasks
    ]);
    
} catch (Exception $e) {
    // 回滾交易
    $pdo->rollBack();
    
    echo json_encode([
        'success' => false,
        'message' => '更新任務狀態時發生錯誤：' . $e->getMessage()
    ]);
}
?>
