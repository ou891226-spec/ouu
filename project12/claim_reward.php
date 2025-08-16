<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';
session_start();

// 強制使用會員ID 23進行測試
$member_id = $_SESSION['member_id'] ?? 23;
$task_id = $_POST['task_id'] ?? 0;

// Debug log
file_put_contents('debug.log', 'member_id=' . $member_id . ', task_id=' . $task_id . PHP_EOL, FILE_APPEND);

if (!$task_id) {
  echo json_encode(['success' => false, 'message' => '參數錯誤']);
  exit;
}

try {
  // 檢查任務是否已完成但未領取
  $check_stmt = $pdo->prepare("SELECT completed_date, claimed_date FROM member_tasks WHERE member_id = ? AND task_id = ?");
  $check_stmt->execute([$member_id, $task_id]);
  $task_record = $check_stmt->fetch();
  
  file_put_contents('debug.log', 'task_record: ' . json_encode($task_record) . PHP_EOL, FILE_APPEND);
  
  if (!$task_record) {
    echo json_encode(['success' => false, 'message' => '找不到任務記錄']);
    exit;
  }
  
  if (!$task_record['completed_date']) {
    echo json_encode(['success' => false, 'message' => '任務尚未完成']);
    exit;
  }
  
  if ($task_record['claimed_date']) {
    echo json_encode(['success' => false, 'message' => '獎勵已領取過']);
    exit;
  }
  
  // 更新為已領取
  $stmt = $pdo->prepare("UPDATE member_tasks SET claimed_date = NOW() WHERE member_id = ? AND task_id = ?");
  $stmt->execute([$member_id, $task_id]);
  
  $updated_rows = $stmt->rowCount();
  file_put_contents('debug.log', 'updated_rows: ' . $updated_rows . PHP_EOL, FILE_APPEND);
  
  if ($updated_rows > 0) {
    // 獲取任務對應的成就
    $achievement_sql = "SELECT reward_achievement FROM daily_tasks WHERE task_id = ?";
    $stmt = $pdo->prepare($achievement_sql);
    $stmt->execute([$task_id]);
    $task_info = $stmt->fetch();
    
    if ($task_info && $task_info['reward_achievement']) {
      // 查找對應的成就ID
      $find_achievement_sql = "SELECT achievement_id FROM achievements WHERE achievement_name = ?";
      $stmt = $pdo->prepare($find_achievement_sql);
      $stmt->execute([$task_info['reward_achievement']]);
      $achievement = $stmt->fetch();
      
      if ($achievement) {
        // 檢查是否已經獲得過這個成就
        $check_achievement_sql = "SELECT COUNT(*) FROM member_achievements WHERE member_id = ? AND achievement_id = ?";
        $stmt = $pdo->prepare($check_achievement_sql);
        $stmt->execute([$member_id, $achievement['achievement_id']]);
        $has_achievement = $stmt->fetchColumn() > 0;
        
        if (!$has_achievement) {
          // 添加成就
          $add_achievement_sql = "INSERT INTO member_achievements (member_id, achievement_id, earned_date) VALUES (?, ?, NOW())";
          $stmt = $pdo->prepare($add_achievement_sql);
          $stmt->execute([$member_id, $achievement['achievement_id']]);
          file_put_contents('debug.log', 'achievement_added: ' . $task_info['reward_achievement'] . PHP_EOL, FILE_APPEND);
        }
      }
    }
    
    echo json_encode(['success' => true, 'message' => '獎勵領取成功']);
  } else {
    echo json_encode(['success' => false, 'message' => '領取失敗']);
  }
} catch (Exception $e) {
  file_put_contents('debug.log', 'Error: ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
  echo json_encode(['success' => false, 'message' => '資料庫錯誤：' . $e->getMessage()]);
} 