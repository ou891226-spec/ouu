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
  // 檢查任務是否已完成但未領取（支援動態完成狀態判斷）
  $check_stmt = $pdo->prepare("
    SELECT 
      mt.completed_date, 
      mt.claimed_date,
      d.task_name,
      d.task_description,
      CASE 
        WHEN mt.claimed_date IS NOT NULL THEN 'claimed'
        WHEN mt.completed_date IS NOT NULL THEN 'completed'
        -- 動態判斷累積型任務是否已達成
        WHEN (
            -- 遊戲達人：完成10局遊戲
            (d.task_name = '遊戲達人' OR d.task_description LIKE '%完成10局%' OR d.task_description LIKE '%10局%' OR d.task_description LIKE '%完成%局遊戲%') AND
            (SELECT COUNT(*) FROM game_records WHERE member_id = mt.member_id AND DATE(play_date) = CURDATE()) >= 10
        ) THEN 'completed'
        WHEN (
            -- 持久戰士：累積遊戲時間達到目標
            (d.task_description LIKE '%分鐘%' OR d.task_description LIKE '%遊玩時間%') AND
            (SELECT COALESCE(SUM(play_time), 0) FROM game_records WHERE member_id = mt.member_id AND DATE(play_date) = CURDATE() AND play_time IS NOT NULL) >= 300
        ) THEN 'completed'
        WHEN (
            -- 績分高手：總分達到50分
            (d.task_name = '績分高手' OR d.task_description LIKE '%總分達到50分%') AND
            (SELECT COALESCE(SUM(score), 0) FROM game_records WHERE member_id = mt.member_id AND DATE(play_date) = CURDATE()) >= 50
        ) THEN 'completed'
        WHEN (
            -- 進階者：總分達到500分
            (d.task_name = '進階者' OR d.task_description LIKE '%總分達到500分%') AND
            (SELECT COALESCE(SUM(score), 0) FROM game_records WHERE member_id = mt.member_id AND DATE(play_date) = CURDATE()) >= 500
        ) THEN 'completed'
        WHEN (
            -- 分數收集者：獲得指定分數
            (d.task_description LIKE '%獲得1000分%') AND
            (SELECT COALESCE(SUM(score), 0) FROM game_records WHERE member_id = mt.member_id AND DATE(play_date) = CURDATE()) >= 1000
        ) THEN 'completed'
        WHEN (
            -- 全能玩家：完成3種不同類型遊戲
            (d.task_description LIKE '%三種不同類型%' OR d.task_description LIKE '%不同類型%') AND
            (SELECT COUNT(DISTINCT game_type) FROM game_records WHERE member_id = mt.member_id AND DATE(play_date) = CURDATE()) >= 3
        ) THEN 'completed'
        WHEN (
            -- 社交任務：添加好友
            d.task_description LIKE '%好友%' AND
            (SELECT COUNT(*) FROM friends WHERE member_id = mt.member_id) >= CASE 
                WHEN d.task_description LIKE '%10位好友%' OR d.task_description LIKE '%10個好友%' THEN 10
                WHEN d.task_description LIKE '%5位好友%' OR d.task_description LIKE '%5個好友%' THEN 5
                WHEN d.task_description LIKE '%3位好友%' OR d.task_description LIKE '%3個好友%' THEN 3
                WHEN d.task_name = '社交大師' OR d.task_description LIKE '%添加3%' THEN 3
                WHEN d.task_description LIKE '%添加10%' THEN 10
                ELSE 3
            END
        ) THEN 'completed'
        ELSE 'pending'
      END as dynamic_status
    FROM member_tasks mt
    JOIN daily_tasks d ON mt.task_id = d.task_id
    WHERE mt.member_id = ? AND mt.task_id = ?
  ");
  $check_stmt->execute([$member_id, $task_id]);
  $task_record = $check_stmt->fetch();
  
  file_put_contents('debug.log', 'task_record: ' . json_encode($task_record) . PHP_EOL, FILE_APPEND);
  
  if (!$task_record) {
    echo json_encode(['success' => false, 'message' => '找不到任務記錄']);
    exit;
  }
  
  // 檢查是否已完成（支援動態狀態）
  $is_completed = $task_record['completed_date'] || $task_record['dynamic_status'] === 'completed';
  if (!$is_completed) {
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