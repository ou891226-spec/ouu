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
        // 檢查特殊成就（如完美主義者、持久戰士等）
        if ($game_type && $score > 0) {
            // 將遊戲類型映射到中文遊戲類型
            $chinese_game_type = null;
            switch ($game_type) {
                case 'memory_game':
                    $chinese_game_type = '記憶力';
                    break;
                case 'game_2048':
                    $chinese_game_type = '算術邏輯力';
                    break;
                case 'catch_egg':
                    $chinese_game_type = '反應力';
                    break;
                case 'text_color':
                    $chinese_game_type = '反應力';
                    break;
                case 'vegetable_cost':
                    $chinese_game_type = '算術邏輯力';
                    break;
                case 'rhythm_game':
                    $chinese_game_type = '反應力';
                    break;
                case 'prisoner_game':
                    $chinese_game_type = '記憶力';
                    break;
                case 'river_game':
                    $chinese_game_type = '算術邏輯力';
                    break;
                case 'memory_2p':
                    $chinese_game_type = '記憶力';
                    break;
                case 'vegetable_2p':
                    $chinese_game_type = '算術邏輯力';
                    break;
                default:
                    $chinese_game_type = $game_type;
                    break;
            }
            
            checkSpecialAchievements($member_id, $score, $play_time, $chinese_game_type);
        }
        
        // 檢查並完成相關的每日任務（必須有明確的遊戲類型）
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
                 // echo "🎉 恭喜獲得成就：{$icon} {$achievement_name}\n";
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
function checkSpecialAchievements($member_id, $score, $play_time, $game_type = null) {
    global $pdo;
    
    try {
        // 檢查完美主義者 - 根據不同遊戲類型判斷滿分
        $isPerfectScore = false;
        
        if ($game_type) {
            switch ($game_type) {
                case '記憶力':
                    // 記憶力遊戲：完成就是滿分（翻牌對對樂、圖片線索、追蹤犯人）
                    $isPerfectScore = ($score > 0);
                    break;
                case '反應力':
                    // 反應力遊戲：需要達到一定分數（接金蛋、看字選色、節奏遊戲）
                    // 不同遊戲有不同標準，但統一設為300分以上
                    $isPerfectScore = ($score >= 300);
                    break;
                case '算術邏輯力':
                    // 算術邏輯力遊戲：需要更細緻的判斷
                    // 2048遊戲需要達到2048分，算菜錢和數字排排樂完成即可
                    if ($score >= 2048) {
                        // 如果分數很高，肯定是完美
                        $isPerfectScore = true;
                    } else if ($score > 0 && $score < 2048) {
                        // 分數較低但大於0，可能是算菜錢或數字排排樂
                        $isPerfectScore = true;
                    } else {
                        $isPerfectScore = false;
                    }
                    break;
                default:
                    // 其他遊戲：使用舊的通用標準
                    $isPerfectScore = ($score >= 1000);
                    break;
            }
        } else {
            // 沒有遊戲類型資訊時，使用舊的通用標準
            $isPerfectScore = ($score >= 1000);
        }
        
        if ($isPerfectScore) {
            $sql = "SELECT achievement_id, achievement_name, icon FROM achievements 
                    WHERE achievement_name = '完美主義者'";
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
                    WHERE achievement_name = '持久戰士'";
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
                    WHERE achievement_name = '全能玩家'";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $achievement = $stmt->fetch();
            
            if ($achievement) {
                grantAchievement($member_id, $achievement['achievement_id'], $achievement['achievement_name'], $achievement['icon']);
            }
        }
        
        // 檢查遊戲達人（累計遊玩100局遊戲）
        $total_games_sql = "SELECT COUNT(*) as total_games FROM game_records WHERE member_id = ?";
        $total_games_stmt = $pdo->prepare($total_games_sql);
        $total_games_stmt->execute([$member_id]);
        $total_games = $total_games_stmt->fetch()['total_games'];
        
        if ($total_games >= 100) {
            $sql = "SELECT achievement_id, achievement_name, icon FROM achievements 
                    WHERE achievement_name = '遊戲達人'";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $achievement = $stmt->fetch();
            
            if ($achievement) {
                grantAchievement($member_id, $achievement['achievement_id'], $achievement['achievement_name'], $achievement['icon']);
            }
        }
        
        // 檢查連勝王者（連續獲得5次高分）
        // 這裡定義高分為分數 >= 500
        if ($score >= 500) {
            $recent_high_scores_sql = "SELECT COUNT(*) as high_score_count 
                                     FROM game_records 
                                     WHERE member_id = ? 
                                     AND score >= 500 
                                     AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                                     ORDER BY created_at DESC 
                                     LIMIT 10";
            $high_scores_stmt = $pdo->prepare($recent_high_scores_sql);
            $high_scores_stmt->execute([$member_id]);
            $high_score_count = $high_scores_stmt->fetch()['high_score_count'];
            
            if ($high_score_count >= 5) {
                $sql = "SELECT achievement_id, achievement_name, icon FROM achievements 
                        WHERE achievement_name = '連勝王者'";
                $stmt = $pdo->prepare($sql);
                $stmt->execute();
                $achievement = $stmt->fetch();
                
                if ($achievement) {
                    grantAchievement($member_id, $achievement['achievement_id'], $achievement['achievement_name'], $achievement['icon']);
                }
            }
        }
        
        // 檢查連續登入成就（需要額外的登入記錄表來追蹤）
        checkLoginStreakAchievements($member_id);
        
    } catch (Exception $e) {
        error_log("特殊成就檢查錯誤: " . $e->getMessage());
    }
}

/**
 * 檢查連續登入相關成就
 */
function checkLoginStreakAchievements($member_id) {
    global $pdo;
    
    try {
        // 檢查連續登入天數
        $login_streak_sql = "
            SELECT COUNT(DISTINCT DATE(created_at)) as consecutive_days
            FROM user_behavior_log 
            WHERE member_id = ? 
            AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            AND DATE(created_at) IN (
                SELECT DATE(created_at) 
                FROM user_behavior_log 
                WHERE member_id = ? 
                GROUP BY DATE(created_at)
                ORDER BY DATE(created_at) DESC
                LIMIT 30
            )
        ";
        
        $streak_stmt = $pdo->prepare($login_streak_sql);
        $streak_stmt->execute([$member_id, $member_id]);
        $streak_days = $streak_stmt->fetch()['consecutive_days'];
        
        // 檢查連續登入3天成就
        if ($streak_days >= 3) {
            $sql = "SELECT achievement_id, achievement_name, icon FROM achievements 
                    WHERE achievement_name = '忠實玩家'";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $achievement = $stmt->fetch();
            
            if ($achievement) {
                grantAchievement($member_id, $achievement['achievement_id'], $achievement['achievement_name'], $achievement['icon']);
            }
        }
        
        // 檢查連續登入7天成就
        if ($streak_days >= 7) {
            $sql = "SELECT achievement_id, achievement_name, icon FROM achievements 
                    WHERE achievement_name = '超級粉絲'";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $achievement = $stmt->fetch();
            
            if ($achievement) {
                grantAchievement($member_id, $achievement['achievement_id'], $achievement['achievement_name'], $achievement['icon']);
            }
        }
        
        // 檢查連續登入30天成就
        if ($streak_days >= 30) {
            $sql = "SELECT achievement_id, achievement_name, icon FROM achievements 
                    WHERE achievement_name = '資深玩家'";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $achievement = $stmt->fetch();
            
            if ($achievement) {
                grantAchievement($member_id, $achievement['achievement_id'], $achievement['achievement_name'], $achievement['icon']);
            }
        }
        
    } catch (Exception $e) {
        error_log("檢查連續登入成就錯誤: " . $e->getMessage());
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
        $sql = "SELECT a.achievement_name, 
                       a.achievement_name as achievement_description,
                       COALESCE(a.icon, '🏆') as icon, 
                       ma.earned_date
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
        
        // 計算今天獲得的所有成就數量（排除每日登入）
        $sql = "SELECT COUNT(*) as count FROM member_achievements ma 
                JOIN achievements a ON ma.achievement_id = a.achievement_id 
                WHERE ma.member_id = ? AND DATE(ma.earned_date) = ?
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
function checkAndCompleteAllTasks($member_id, $game_type = null, $play_time = 0) {
    global $pdo;
    
    try {
        // 檢查一般任務（基於遊戲類型）
        if ($game_type) {
            // 根據遊戲類型映射到任務描述中的關鍵字
            $game_type_mapping = [
                '記憶力' => ['記憶力遊戲', '記憶力', '記憶遊戲', '翻牌對對樂', '翻牌', '對對樂', '記憶', '技藝達人', '圖片線索問答'],
                '算術邏輯力' => ['算術邏輯力', '算菜錢遊戲', '蔬菜遊戲', '算菜錢', '算術', '數學'],
                '邏輯力' => ['邏輯力', '2048遊戲', '2048', '邏輯', '數字'],
                '反應力' => ['反應力', '反應力遊戲', '接金蛋遊戲', '接金蛋', '接蛋遊戲', '接蛋', '反應', '接金蛋專家'],
                '節奏遊戲' => ['節奏遊戲', '節奏', '音樂', '節拍', '節奏達人'],
                '接金蛋遊戲' => ['接金蛋遊戲', '接金蛋', '接蛋遊戲', '接蛋', '接金蛋專家'],
                '看字選色遊戲' => ['看字選色遊戲', '看字選色', '選色', '顏色', '看字選色專家'],
                '追蹤犯人遊戲' => ['追蹤犯人遊戲', '追蹤犯人', '犯人遊戲', '犯人', '追蹤', '追蹤專家'],
                '數字排排樂' => ['數字排排樂', '拼圖', '排排樂', '拼圖專家']
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
            
            // 記錄調試信息
            error_log("檢查用戶 $member_id 的任務，遊戲類型: $game_type");
            error_log("搜索關鍵字: " . implode(', ', $search_terms));
            error_log("找到 " . count($completed_tasks) . " 個可完成的任務");
            
            foreach ($completed_tasks as $task) {
                // 完成任務（但不自動授予成就）
                $complete_task_sql = "UPDATE member_tasks SET completed_date = NOW() WHERE member_id = ? AND task_id = ?";
                $complete_stmt = $pdo->prepare($complete_task_sql);
                $complete_stmt->execute([$member_id, $task['task_id']]);
                
                error_log("✓ 用戶 $member_id 完成任務 {$task['task_id']}：{$task['task_description']}");
                
                // 記錄可領取的成就（不自動授予）
                if ($task['reward_achievement']) {
                    error_log("✓ 用戶 $member_id 可領取成就：{$task['reward_achievement']}");
                }
            }
        }
        
        // 檢查基於遊戲時間的任務
        if ($play_time > 0) {
            checkTimeBasedTasks($member_id, $play_time);
        }
        
        // 檢查複雜任務
        checkAndCompleteComplexTasks($member_id);
        
        return true;
    } catch (Exception $e) {
        error_log("檢查任務時發生錯誤：" . $e->getMessage());
        return false;
    }
}

// 新增：檢查基於遊戲時間的任務
function checkTimeBasedTasks($member_id, $play_time) {
    global $pdo;
    
    try {
        // 檢查30秒內完成遊戲的任務
        if ($play_time <= 30) {
            $speed_task_sql = "
            SELECT mt.task_id, mt.completed_date, d.task_description, d.reward_achievement
            FROM member_tasks mt
            JOIN daily_tasks d ON mt.task_id = d.task_id
            WHERE mt.member_id = ? AND mt.completed_date IS NULL 
            AND d.task_description LIKE '%30秒%'
            ";
            
            $speed_stmt = $pdo->prepare($speed_task_sql);
            $speed_stmt->execute([$member_id]);
            $speed_tasks = $speed_stmt->fetchAll();
            
            foreach ($speed_tasks as $task) {
                // 完成任務
                $complete_task_sql = "UPDATE member_tasks SET completed_date = NOW() WHERE member_id = ? AND task_id = ?";
                $complete_stmt = $pdo->prepare($complete_task_sql);
                $complete_stmt->execute([$member_id, $task['task_id']]);
                
                error_log("用戶 $member_id 完成速度任務 {$task['task_id']}：{$task['task_description']} (遊戲時間: {$play_time}秒)");
                
                // 記錄可領取的成就
                if ($task['reward_achievement']) {
                    error_log("用戶 $member_id 可領取速度成就：{$task['reward_achievement']}");
                }
            }
        }
        
        return true;
    } catch (Exception $e) {
        error_log("檢查時間任務時發生錯誤：" . $e->getMessage());
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