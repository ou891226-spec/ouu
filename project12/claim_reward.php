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
            -- 遊戲大師：完成25個關卡
            (d.task_name = '遊戲大師' OR d.task_description LIKE '%完成25個關卡%' OR d.task_description LIKE '%25個關卡%') AND
            (SELECT COUNT(*) FROM game_records WHERE member_id = mt.member_id AND DATE(play_date) = CURDATE()) >= 25
        ) THEN 'completed'
        WHEN (
            -- 遊戲傳奇：完成50個關卡
            (d.task_name = '遊戲傳奇' OR d.task_description LIKE '%完成50個關卡%' OR d.task_description LIKE '%50個關卡%') AND
            (SELECT COUNT(*) FROM game_records WHERE member_id = mt.member_id AND DATE(play_date) = CURDATE()) >= 50
        ) THEN 'completed'
        WHEN (
            -- 遊戲狂熱者：一天內完成20局遊戲
            (d.task_name = '遊戲狂熱者' OR d.task_description LIKE '%一天內完成20局遊戲%') AND
            (SELECT COUNT(*) FROM game_records WHERE member_id = mt.member_id AND DATE(play_date) = CURDATE()) >= 20
        ) THEN 'completed'
        WHEN (
            -- 遊戲達人：完成10局遊戲
            (d.task_name = '遊戲達人' OR d.task_description LIKE '%完成10局%' OR d.task_description LIKE '%10局%' OR d.task_description LIKE '%完成%局遊戲%') AND
            (SELECT COUNT(*) FROM game_records WHERE member_id = mt.member_id AND DATE(play_date) = CURDATE()) >= 10
        ) THEN 'completed'
        WHEN (
            -- 遊戲愛好者：完成15局遊戲
            (d.task_name = '遊戲愛好者' OR d.task_description LIKE '%完成15局%' OR d.task_description LIKE '%15局%') AND
            (SELECT COUNT(*) FROM game_records WHERE member_id = mt.member_id AND DATE(play_date) = CURDATE()) >= 15
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
            -- 分數挑戰者：累積獲得200分以上
            (d.task_name = '分數挑戰者' OR d.task_description LIKE '%單局獲得200分以上%' OR d.task_description LIKE '%累積獲得200分以上%') AND
            (SELECT COALESCE(SUM(score), 0) FROM game_records WHERE member_id = mt.member_id AND DATE(play_date) = CURDATE()) >= 200
        ) THEN 'completed'
        WHEN (
            -- 分數收集者：獲得指定分數
            (d.task_description LIKE '%獲得1000分%') AND
            (SELECT COALESCE(SUM(score), 0) FROM game_records WHERE member_id = mt.member_id AND DATE(play_date) = CURDATE()) >= 1000
        ) THEN 'completed'
        WHEN (
            -- 全能玩家：完成3種不同類型遊戲（記憶力、反應力、算術邏輯力）
            (d.task_name = '全能玩家' OR d.task_description LIKE '%三種不同類型%' OR d.task_description LIKE '%不同類型%' OR d.task_description LIKE '%所有類型%') AND
            (SELECT COUNT(DISTINCT CASE 
                WHEN game_type IN ('記憶力', '翻牌對對樂', '圖片線索問答', '追蹤犯人遊戲') THEN '記憶力'
                WHEN game_type IN ('反應力', '接金蛋遊戲', '看字選色遊戲', '節奏遊戲') THEN '反應力'
                WHEN game_type IN ('算術邏輯力', '2048', '算菜錢遊戲', '過河遊戲', '邏輯力') THEN '算術邏輯力'
                ELSE game_type
            END) FROM game_records WHERE member_id = mt.member_id AND DATE(play_date) = CURDATE()) >= 3
        ) THEN 'completed'
        WHEN (
            -- 技藝達人：完成記憶力遊戲
            (d.task_name = '技藝達人' OR d.task_description LIKE '%記憶力遊戲%' OR d.task_description LIKE '%記憶遊戲%') AND
            (SELECT COUNT(*) FROM game_records WHERE member_id = mt.member_id AND DATE(play_date) = CURDATE() AND game_type = '記憶力') >= 3
        ) THEN 'completed'
        WHEN (
            -- 反應大師：完成反應力遊戲
            (d.task_name = '反應大師' OR d.task_description LIKE '%反應力遊戲%' OR d.task_description LIKE '%反應遊戲%') AND
            (SELECT COUNT(*) FROM game_records WHERE member_id = mt.member_id AND DATE(play_date) = CURDATE() AND game_type = '反應力') >= 3
        ) THEN 'completed'
        WHEN (
            -- 邏輯專家：完成邏輯遊戲
            (d.task_name = '邏輯專家' OR d.task_description LIKE '%邏輯遊戲%' OR d.task_description LIKE '%2048%') AND
            (SELECT COUNT(*) FROM game_records WHERE member_id = mt.member_id AND DATE(play_date) = CURDATE() AND (game_type = '算術邏輯力' OR game_type = '邏輯力')) >= 3
        ) THEN 'completed'
        WHEN (
            -- 手眼協調大師：完成手眼協調遊戲
            (d.task_name = '手眼協調大師' OR d.task_description LIKE '%手眼協調%' OR d.task_description LIKE '%接金蛋%') AND
            (SELECT COUNT(*) FROM game_records WHERE member_id = mt.member_id AND DATE(play_date) = CURDATE() AND game_type = '反應力') >= 3
        ) THEN 'completed'
        WHEN (
            -- 追蹤專家：完成追蹤犯人遊戲
            (d.task_name = '追蹤專家' OR d.task_description LIKE '%追蹤犯人%' OR d.task_description LIKE '%犯人遊戲%') AND
            (SELECT COUNT(*) FROM game_records WHERE member_id = mt.member_id AND DATE(play_date) = CURDATE() AND game_type = '記憶力') >= 3
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
        WHEN (
            -- 成就大師：獲得3個成就
            (d.task_name = '成就大師' OR d.task_description LIKE '%獲得.*成就%' OR d.task_description LIKE '%成就大師%') AND
            (SELECT COUNT(*) FROM member_achievements ma 
             JOIN achievements a ON ma.achievement_id = a.achievement_id 
             WHERE ma.member_id = mt.member_id AND DATE(ma.earned_date) = CURDATE() 
             AND a.achievement_name != '每日登入') >= 3
        ) THEN 'completed'
        WHEN (
            -- 速度之王：30秒內完成遊戲
            (d.task_description LIKE '%30秒%' OR d.task_name = '速度之王') AND
            (SELECT COUNT(*) FROM game_records WHERE member_id = mt.member_id AND DATE(play_date) = CURDATE() AND play_time <= 30) >= 1
        ) THEN 'completed'
        WHEN (
            -- 刷新最高分數：檢查是否有新的最高分數記錄
            (d.task_name = '刷新最高分數' OR d.task_description LIKE '%最高分數%') AND
            (SELECT COUNT(*) FROM game_records 
             WHERE member_id = mt.member_id 
             AND DATE(play_date) = CURDATE() 
             AND score > (
                 SELECT COALESCE(MAX(score), 0) FROM game_records 
                 WHERE member_id = mt.member_id 
                 AND DATE(play_date) < CURDATE()
             )) >= 1
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
    
    // 直接授予對應的
    $achievement_mapping = [
        '遊戲狂熱者' => '遊戲狂熱者',
        '分數挑戰者' => '分數挑戰者', 
        '遊戲達人' => '遊戲達人',
        '遊戲大師' => '遊戲大師',
        '遊戲傳奇' => '遊戲傳奇',
        '績分高手' => '績分高手',
        '進階者' => '進階者'
    ];
    
    if (isset($achievement_mapping[$task_info['task_name']])) {
      $achievement_name = $achievement_mapping[$task_info['task_name']];
      
      // 確保成就存在
      $check_achievement_sql = "SELECT achievement_id FROM achievements WHERE achievement_name = ?";
      $stmt = $pdo->prepare($check_achievement_sql);
      $stmt->execute([$achievement_name]);
      $achievement = $stmt->fetch();
      
      if (!$achievement) {
        // 如果成就不存在，創建它
        $create_achievement_sql = "INSERT INTO achievements (achievement_name, achievement_description, achievement_icon) VALUES (?, ?, 'achievement.png')";
        $stmt = $pdo->prepare($create_achievement_sql);
        $stmt->execute([$achievement_name, $achievement_name . '成就']);
        
        // 重新查詢成就ID
        $stmt = $pdo->prepare($check_achievement_sql);
        $stmt->execute([$achievement_name]);
        $achievement = $stmt->fetch();
      }
      
      if ($achievement) {
        // 檢查是否已經獲得過這個成就
        $check_member_achievement_sql = "SELECT COUNT(*) FROM member_achievements WHERE member_id = ? AND achievement_id = ?";
        $stmt = $pdo->prepare($check_member_achievement_sql);
        $stmt->execute([$member_id, $achievement['achievement_id']]);
        $has_achievement = $stmt->fetchColumn() > 0;
        
        if (!$has_achievement) {
          // 添加成就
          $add_achievement_sql = "INSERT INTO member_achievements (member_id, achievement_id, earned_date) VALUES (?, ?, NOW())";
          $stmt = $pdo->prepare($add_achievement_sql);
          $stmt->execute([$member_id, $achievement['achievement_id']]);
          file_put_contents('debug.log', 'achievement_added: ' . $achievement_name . PHP_EOL, FILE_APPEND);
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