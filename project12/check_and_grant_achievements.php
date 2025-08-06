<?php
require_once 'db.php';
session_start();

/**
 * 檢查並授予成就
 * @param int $member_id 會員ID
 * @param string $game_type 遊戲類型（可選）
 * @param int $score 分數（可選）
 * @param int $play_time 遊玩時間（可選）
 */
function checkAndGrantAchievements($member_id, $game_type = null, $score = 0, $play_time = 0) {
    global $pdo;
    
    try {
        // 0. 檢查每日成就限制
        if (!checkDailyAchievementLimit($member_id)) {
            return false; // 今日成就已達上限
        }
        
        // 1. 檢查遊戲類型成就
        if ($game_type) {
            $sql = "SELECT achievement_id, achievement_name, icon 
                    FROM achievements 
                    WHERE achievement_type = ? AND requirement_type = 'game_completion' 
                    AND is_active = 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$game_type]);
            $game_achievements = $stmt->fetchAll();
            
            foreach ($game_achievements as $achievement) {
                // 檢查每日限制
                if (!checkDailyAchievementLimit($member_id)) {
                    break; // 今日已達上限
                }
                
                if (grantAchievement($member_id, $achievement['achievement_id'], $achievement['achievement_name'], $achievement['icon'])) {
                    // 如果成功授予成就，更新每日計數
                    updateDailyAchievementCount($member_id);
                }
            }
        }
        
        // 2. 檢查總遊戲數量成就（每日限制內）
        if (checkDailyAchievementLimit($member_id)) {
            $sql = "SELECT COUNT(*) as total_games FROM game_records WHERE member_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$member_id]);
            $total_games = $stmt->fetch()['total_games'];
            
            $sql = "SELECT achievement_id, achievement_name, icon, requirement_value 
                    FROM achievements 
                    WHERE achievement_type = 'general' AND requirement_type = 'total_games' 
                    AND requirement_value <= ? AND is_active = 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$total_games]);
            $game_count_achievements = $stmt->fetchAll();
            
            foreach ($game_count_achievements as $achievement) {
                // 檢查每日限制
                if (!checkDailyAchievementLimit($member_id)) {
                    break; // 今日已達上限
                }
                
                if (grantAchievement($member_id, $achievement['achievement_id'], $achievement['achievement_name'], $achievement['icon'])) {
                    updateDailyAchievementCount($member_id);
                }
            }
        }
        
        // 3. 檢查總分成就（每日限制內）
        if (checkDailyAchievementLimit($member_id)) {
            $sql = "SELECT SUM(score) as total_score FROM game_records WHERE member_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$member_id]);
            $total_score = $stmt->fetch()['total_score'] ?? 0;
            
            $sql = "SELECT achievement_id, achievement_name, icon, requirement_value 
                    FROM achievements 
                    WHERE achievement_type = 'score' AND requirement_type = 'total_score' 
                    AND requirement_value <= ? AND is_active = 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$total_score]);
            $score_achievements = $stmt->fetchAll();
            
            foreach ($score_achievements as $achievement) {
                // 檢查每日限制
                if (!checkDailyAchievementLimit($member_id)) {
                    break; // 今日已達上限
                }
                
                if (grantAchievement($member_id, $achievement['achievement_id'], $achievement['achievement_name'], $achievement['icon'])) {
                    updateDailyAchievementCount($member_id);
                }
            }
        }
        
        // 4. 檢查特殊成就
        checkSpecialAchievements($member_id, $score, $play_time);
        
        return true;
        
    } catch (Exception $e) {
        error_log("成就檢查錯誤: " . $e->getMessage());
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
            
            // 記錄成就獲得
            echo "🎉 恭喜獲得成就：{$icon} {$achievement_name}\n";
            
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
        
        $sql = "SELECT achievement_count FROM daily_achievement_records 
                WHERE member_id = ? AND achievement_date = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$member_id, $today]);
        $record = $stmt->fetch();
        
        $count = $record ? $record['achievement_count'] : 0;
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