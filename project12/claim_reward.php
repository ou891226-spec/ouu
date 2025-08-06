<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';
session_start();

// Debug log
file_put_contents('debug.log', 'member_id=' . ($_SESSION['member_id'] ?? 'null') . ', task_id=' . ($_POST['task_id'] ?? 'null') . PHP_EOL, FILE_APPEND);

$member_id = $_SESSION['member_id'] ?? 0;
$task_id = $_POST['task_id'] ?? 0;

if (!$member_id || !$task_id) {
  echo json_encode(['success' => false, 'message' => '參數錯誤']);
  exit;
}

try {
  // 先檢查是否有這個任務記錄
  $check_stmt = $pdo->prepare("SELECT status FROM member_tasks WHERE member_id = ? AND task_id = ?");
  $check_stmt->execute([$member_id, $task_id]);
  $task_record = $check_stmt->fetch();
  
  if (!$task_record) {
    // 如果沒有記錄，先插入一個 completed 記錄
    $insert_stmt = $pdo->prepare("INSERT INTO member_tasks (member_id, task_id, status, completed_date) VALUES (?, ?, 'completed', NOW())");
    $insert_stmt->execute([$member_id, $task_id]);
  }
  
  // 更新為已領取（移除 claimed_date）
  $stmt = $pdo->prepare("UPDATE member_tasks SET status = 'claimed' WHERE member_id = ? AND task_id = ? AND status = 'completed'");
  $stmt->execute([$member_id, $task_id]);
  
  if ($stmt->rowCount() > 0) {
    echo json_encode(['success' => true]);
  } else {
    echo json_encode(['success' => false, 'message' => '無法領取，可能已領取過或尚未完成']);
  }
} catch (Exception $e) {
  file_put_contents('debug.log', 'Error: ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
  echo json_encode(['success' => false, 'message' => '資料庫錯誤：' . $e->getMessage()]);
} 