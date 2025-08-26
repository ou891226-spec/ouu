<?php
// 如果沒有已經建立的數據庫連接，則使用默認連接
if (!isset($pdo)) {
    require_once 'db.php';
}

// 只在會話未啟動時啟動會話
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * 檢查並授予成就（已停用自動授予）
 * @param int $member_id 會員ID
 * @param string $game_type 遊戲類型（可選）
 * @param int $score 分數（可選）
 * @param int $play_time 遊玩時間（可選）
 */
function checkAndGrantAchievements($member_id, $game_type = null, $score = 0, $play_time = 0) {
    global $pdo;
    
    try {
        // 現在成就只能通過完成每日任務獲得，不再自動授予
        // 只檢查並完成相關的每日任務（必須有明確的遊戲類型）
        if ($game_type) {
            checkAndCompleteAllTasks($member_id, $game_type);
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log("任務檢查錯誤: " . $e->getMessage());
        return false;
    }
}

/**
 * 授予成就
 */
function grantAchievement($member_id, $achievement_id, $achievement_name, $icon) {
    global $pdo;
    
    try {
        // 檢查是否已經獲得此成就
        $sql = "SELECT COUNT(*) as count FROM member_achievements 
                WHERE member_id = ? AND achievement_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$member_id, $achievement_id]);
        $exists = $stmt->fetch()['count'] > 0;
        
        if (!$exists) {
            // 授予成就
            $sql = "INSERT INTO member_achievements (member_id, achievement_id) VALUES (?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$member_id, $achievement_id]);
            
                         // 記錄成就獲得（只在非API請求時輸出）
             if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
                 echo "🎉 恭喜獲得成就：{$icon} {$achievement_name}\n";
             }
            
            return true;
        }
        
        return false;
        
    } catch (Exception $e) {
        error_log("授予成就錯誤: " . $e->getMessage());
        return false;
    }
}

/**
 * 檢查特殊成就
 */
function checkSpecialAchievements($member_id, $score, $play_time) {
    global $pdo;
    
    try {
        // 檢查完美主義者（假設滿分是1000）
        if ($score >= 1000) {
            $sql = "SELECT achievement_id, achievement_name, icon FROM achievements 
                    WHERE achievement_type = 'special' AND requirement_type = 'perfect_score'";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $achievement = $stmt->fetch();
            
            if ($achievement) {
                grantAchievement($member_id, $achievement['achievement_id'], $achievement['achievement_name'], $achievement['icon']);
            }
        }
        
        // 檢查持久戰士（遊玩時間超過5分鐘）
        if ($play_time >= 300) {
            $sql = "SELECT achievement_id, achievement_name, icon FROM achievements 
                    WHERE achievement_type = 'special' AND requirement_type = 'long_playtime'";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $achievement = $stmt->fetch();
            
            if ($achievement) {
                grantAchievement($member_id, $achievement['achievement_id'], $achievement['achievement_name'], $achievement['icon']);
            }
        }
        
        // 檢查全能玩家（完成所有類型的遊戲）
        $sql = "SELECT COUNT(DISTINCT game_type) as game_types FROM game_records WHERE member_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$member_id]);
        $game_types = $stmt->fetch()['game_types'];
        
        if ($game_types >= 7) {
            $sql = "SELECT achievement_id, achievement_name, icon FROM achievements 
                    WHERE achievement_type = 'special' AND requirement_type = 'game_types'";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $achievement = $stmt->fetch();
            
            if ($achievement) {
                grantAchievement($member_id, $achievement['achievement_id'], $achievement['achievement_name'], $achievement['icon']);
            }
        }
        
    } catch (Exception $e) {
        error_log("特殊成就檢查錯誤: " . $e->getMessage());
    }
}

/**
 * 檢查每日成就限制
 */
function checkDailyAchievementLimit($member_id) {
    global $pdo;
    
    try {
        $today = date('Y-m-d');
        
        // 檢查今日是否已達上限
        $sql = "SELECT achievement_count FROM daily_achievement_records 
                WHERE member_id = ? AND achievement_date = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$member_id, $today]);
        $record = $stmt->fetch();
        
        if ($record) {
            // 如果今日已獲得3個成就，則不能再獲得
            if ($record['achievement_count'] >= 3) {
                return false;
            }
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log("檢查每日成就限制錯誤: " . $e->getMessage());
        return true; // 出錯時允許獲得成就
    }
}

/**
 * 更新每日成就計數
 */
function updateDailyAchievementCount($member_id) {
    global $pdo;
    
    try {
        $today = date('Y-m-d');
        
        // 檢查是否已有今日記錄
        $sql = "SELECT record_id, achievement_count FROM daily_achievement_records 
                WHERE member_id = ? AND achievement_date = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$member_id, $today]);
        $record = $stmt->fetch();
        
        if ($record) {
            // 更新現有記錄
            $sql = "UPDATE daily_achievement_records 
                    SET achievement_count = achievement_count + 1 
                    WHERE record_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$record['record_id']]);
        } else {
            // 創建新記錄
            $sql = "INSERT INTO daily_achievement_records 
                    (member_id, achievement_date, achievement_count, last_reset_date) 
                    VALUES (?, ?, 1, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$member_id, $today, $today]);
        }
        
    } catch (Exception $e) {
        error_log("更新每日成就計數錯誤: " . $e->getMessage());
    }
}

/**
 * 獲取用戶成就
 */
function getUserAchievements($member_id) {
    global $pdo;
    
    try {
        $sql = "SELECT a.achievement_name, a.achievement_description, a.icon, ma.earned_date
                FROM member_achievements ma
                JOIN achievements a ON ma.achievement_id = a.achievement_id
                WHERE ma.member_id = ?
                ORDER BY ma.earned_date DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$member_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log("獲取用戶成就錯誤: " . $e->getMessage());
        return [];
    }
}

/**
 * 獲取今日成就狀態
 */
function getTodayAchievementStatus($member_id) {
    global $pdo;
    
    try {
        $today = date('Y-m-d');
        
        // 計算今天通過完成每日任務獲得的成就數量
        $sql = "SELECT COUNT(*) as count FROM member_achievements ma 
                JOIN achievements a ON ma.achievement_id = a.achievement_id 
                JOIN daily_tasks d ON a.achievement_name = d.reward_achievement
                JOIN member_tasks mt ON d.task_id = mt.task_id
                WHERE ma.member_id = ? AND DATE(ma.earned_date) = ?
                AND mt.claimed_date IS NOT NULL
                AND a.achievement_name != '每日登入'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$member_id, $today]);
        $count = $stmt->fetchColumn();
        
        $remaining = 3 - $count;
        
        return [
            'today_count' => $count,
            'remaining' => $remaining,
            'can_earn' => $remaining > 0
        ];
        
    } catch (Exception $e) {
        error_log("獲取今日成就狀態錯誤: " . $e->getMessage());
        return [
            'today_count' => 0,
            'remaining' => 3,
            'can_earn' => true
        ];
    }
}

// 新增：檢查並完成複雜任務（如完成三種不同類型遊戲）
function checkAndCompleteComplexTasks($member_id) {
    global $pdo;
    
    try {
        // 檢查任務47：完成三種不同類型遊戲
        $task_47_sql = "
        SELECT mt.task_id, mt.completed_date, d.task_description
        FROM member_tasks mt
        JOIN daily_tasks d ON mt.task_id = d.task_id
        WHERE mt.member_id = ? AND d.task_id = 47 AND mt.completed_date IS NULL
        ";
        $task_stmt = $pdo->prepare($task_47_sql);
        $task_stmt->execute([$member_id]);
        $task_47 = $task_stmt->fetch();
        
        if ($task_47) {
            // 檢查今天玩過的不同遊戲類型
            $today = date('Y-m-d');
            $game_types_sql = "
            SELECT DISTINCT game_type
            FROM game_records 
            WHERE member_id = ? AND DATE(play_date) = ?
            ";
            $game_stmt = $pdo->prepare($game_types_sql);
            $game_stmt->execute([$member_id, $today]);
            $game_types = $game_stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // 如果玩了3種或以上不同類型的遊戲，完成任務
            if (count($game_types) >= 3) {
                $complete_sql = "UPDATE member_tasks SET completed_date = NOW() WHERE member_id = ? AND task_id = 47";
                $complete_stmt = $pdo->prepare($complete_sql);
                $complete_stmt->execute([$member_id]);
                
                // 記錄到日誌
                error_log("用戶 $member_id 完成任務47：完成三種不同類型遊戲。遊戲類型：" . implode(", ", $game_types));
            }
        }
        
        return true;
    } catch (Exception $e) {
        error_log("檢查複雜任務時發生錯誤：" . $e->getMessage());
        return false;
    }
}

// 新增：檢查並完成所有相關任務
function checkAndCompleteAllTasks($member_id, $game_type = null) {
    global $pdo;
    
    try {
        // 檢查一般任務（基於遊戲類型）
        if ($game_type) {
            // 根據遊戲類型映射到任務描述中的關鍵字
            $game_type_mapping = [
                '記憶力' => ['記憶力遊戲', '記憶力'],
                '算數邏輯力' => ['算數邏輯力', '算術邏輯', '算菜錢遊戲', '蔬菜遊戲'],
                '邏輯力' => ['邏輯力', '2048遊戲'],
                '反應力' => ['反應力', '反應力遊戲'],
                '節奏遊戲' => ['節奏遊戲', '節奏'],
                '接金蛋遊戲' => ['接金蛋遊戲', '接金蛋', '接蛋遊戲'],
                '看字選色遊戲' => ['看字選色遊戲', '看字選色'],
                '追蹤犯人遊戲' => ['追蹤犯人遊戲', '追蹤犯人', '犯人遊戲']
            ];
            
            $search_terms = $game_type_mapping[$game_type] ?? [$game_type];
            $placeholders = str_repeat('?,', count($search_terms) - 1) . '?';
            
            $task_check_sql = "
            SELECT mt.task_id, mt.completed_date, d.task_description, d.reward_achievement
            FROM member_tasks mt
            JOIN daily_tasks d ON mt.task_id = d.task_id
            WHERE mt.member_id = ? AND mt.completed_date IS NULL 
            AND d.task_name != '登入網站一次' AND (
                d.task_description LIKE '%遊玩任一普通關卡%' OR
                d.task_description LIKE '%完成任意一場遊戲%' OR
                d.task_description LIKE '%普通關卡%' OR
                d.task_description LIKE '%遊戲對戰%'
            ";
            
            // 添加遊戲類型特定的搜索條件
            foreach ($search_terms as $term) {
                $task_check_sql .= " OR d.task_description LIKE '%$term%'";
            }
            $task_check_sql .= ")";
            
            $task_stmt = $pdo->prepare($task_check_sql);
            $task_stmt->execute([$member_id]);
            $completed_tasks = $task_stmt->fetchAll();
            
            foreach ($completed_tasks as $task) {
                // 完成任務（但不自動授予成就）
                $complete_task_sql = "UPDATE member_tasks SET completed_date = NOW() WHERE member_id = ? AND task_id = ?";
                $complete_stmt = $pdo->prepare($complete_task_sql);
                $complete_stmt->execute([$member_id, $task['task_id']]);
                
                error_log("用戶 $member_id 完成任務 {$task['task_id']}：{$task['task_description']}");
                
                // 記錄可領取的成就（不自動授予）
                if ($task['reward_achievement']) {
                    error_log("用戶 $member_id 可領取成就：{$task['reward_achievement']}");
                }
            }
        }
        
        // 檢查複雜任務
        checkAndCompleteComplexTasks($member_id);
        
        return true;
    } catch (Exception $e) {
        error_log("檢查任務時發生錯誤：" . $e->getMessage());
        return false;
    }
}

// 如果作為 API 調用
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $member_id = $_SESSION['member_id'] ?? 0;
    
    if (!$member_id) {
        echo json_encode(['success' => false, 'message' => '未登入']);
        exit;
    }
    
    switch ($_POST['action']) {
        case 'check_achievements':
            $game_type = $_POST['game_type'] ?? null;
            $score = $_POST['score'] ?? 0;
            $play_time = $_POST['play_time'] ?? 0;
            
            $result = checkAndGrantAchievements($member_id, $game_type, $score, $play_time);
            echo json_encode(['success' => $result]);
            break;
            
        case 'get_achievements':
            $achievements = getUserAchievements($member_id);
            echo json_encode(['success' => true, 'achievements' => $achievements]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => '無效的操作']);
    }
}
?> 