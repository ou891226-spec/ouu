<?php
/**
 * 遊戲進入追蹤系統
 * 實現新的遊戲退出判斷邏輯：
 * 1. 進入遊戲時先記錄一筆失敗記錄
 * 2. 如果用戶後續沒有動作，就算遊戲退出
 * 3. 如果用戶有後續動作，則更新為正常記錄
 */

require_once 'db_connect.php';

/**
 * 記錄遊戲進入（初始失敗記錄）
 * @param int $member_id 會員ID
 * @param string $game_type 遊戲類型
 * @param string $difficulty 難度
 * @param int $game_id 遊戲ID
 * @return int|false 返回記錄ID，失敗返回false
 */
function recordGameEntry($member_id, $game_type, $difficulty = 'easy', $game_id = null) {
    global $pdo;
    
    try {
        // 如果沒有提供game_id，嘗試從games表查找
        if ($game_id === null) {
            $game_stmt = $pdo->prepare("SELECT game_id FROM games WHERE game_type = ? LIMIT 1");
            $game_stmt->execute([$game_type]);
            $game = $game_stmt->fetch();
            $game_id = $game ? $game['game_id'] : 0;
        }
        
        // 插入初始進入記錄
        $stmt = $pdo->prepare("
            INSERT INTO game_records
            (member_id, game_id, difficulty, score, play_date, play_time, game_type, is_single_player, opponent_id, status)
            VALUES (?, ?, ?, 0, NOW(), 0, ?, 1, NULL, 'entered')
        ");
        
        $stmt->execute([
            $member_id,
            $game_id,
            $difficulty,
            $game_type
        ]);
        
        $record_id = $pdo->lastInsertId();
        
        // 記錄行為軌跡
        if (function_exists('logGameBehavior')) {
            logGameBehavior($member_id, $game_type, 0, 0, $difficulty, 'game_entered');
        }
        
        error_log("遊戲進入記錄: 用戶{$member_id}, 遊戲{$game_type}, 記錄ID{$record_id}");
        
        // 清理過期的遊戲進入記錄
        cleanupExpiredGameEntries();
        
        return $record_id;
        
    } catch (Exception $e) {
        error_log("記錄遊戲進入失敗: " . $e->getMessage());
        return false;
    }
}

/**
 * 更新遊戲記錄（用戶有實際遊戲行為時）
 * @param int $record_id 記錄ID
 * @param int $score 分數
 * @param int $play_time 遊戲時間
 * @param string $status 狀態 ('completed', 'failed')
 * @return bool 成功返回true
 */
function updateGameRecord($record_id, $score, $play_time, $status = 'completed') {
    global $pdo;
    
    try {
        // 更新遊戲記錄
        $stmt = $pdo->prepare("
            UPDATE game_records 
            SET score = ?, play_time = ?, status = ?, updated_at = NOW()
            WHERE record_id = ?
        ");
        
        $result = $stmt->execute([$score, $play_time, $status, $record_id]);
        
        if ($result) {
            // 獲取記錄詳情用於更新用戶分數
            $detail_stmt = $pdo->prepare("
                SELECT member_id, game_type, score 
                FROM game_records 
                WHERE record_id = ?
            ");
            $detail_stmt->execute([$record_id]);
            $record = $detail_stmt->fetch();
            
            if ($record) {
                // 更新會員總分數
                $update_stmt = $pdo->prepare("UPDATE member SET total_score = total_score + ? WHERE member_id = ?");
                $update_stmt->execute([$score, $record['member_id']]);
                
                // 根據遊戲類型更新對應的分類分數
                updateCategoryScore($record['member_id'], $record['game_type'], $score);
                
                // 記錄行為軌跡
                if (function_exists('logGameBehavior')) {
                    logGameBehavior($record['member_id'], $record['game_type'], $play_time, $score, null, $status);
                }
            }
            
            error_log("遊戲記錄更新: 記錄ID{$record_id}, 分數{$score}, 時間{$play_time}秒, 狀態{$status}");
        }
        
        return $result;
        
    } catch (Exception $e) {
        error_log("更新遊戲記錄失敗: " . $e->getMessage());
        return false;
    }
}

/**
 * 更新分類分數
 * @param int $member_id 會員ID
 * @param string $game_type 遊戲類型
 * @param int $score 分數
 */
function updateCategoryScore($member_id, $game_type, $score) {
    global $pdo;
    
    try {
        if ($game_type === '反應力' || $game_type === '節奏遊戲' || $game_type === '看字選色遊戲' || $game_type === '接金蛋遊戲') {
            $reaction_sql = "UPDATE member SET reaction_score = reaction_score + ? WHERE member_id = ?";
            $reaction_stmt = $pdo->prepare($reaction_sql);
            $reaction_stmt->execute([$score, $member_id]);
        } elseif ($game_type === '記憶力' || $game_type === '翻牌對對樂' || $game_type === '追蹤犯人遊戲' || $game_type === '圖片線索問答') {
            $memory_sql = "UPDATE member SET memory_score = memory_score + ? WHERE member_id = ?";
            $memory_stmt = $pdo->prepare($memory_sql);
            $memory_stmt->execute([$score, $member_id]);
        } elseif ($game_type === '算術邏輯力' || $game_type === '2048' || $game_type === '算菜錢遊戲' || $game_type === '過河遊戲') {
            $logic_sql = "UPDATE member SET logic_score = logic_score + ? WHERE member_id = ?";
            $logic_stmt = $pdo->prepare($logic_sql);
            $logic_stmt->execute([$score, $member_id]);
        }
    } catch (Exception $e) {
        error_log("更新分類分數失敗: " . $e->getMessage());
    }
}

/**
 * 標記遊戲退出（用戶沒有後續動作）
 * @param int $record_id 記錄ID
 * @return bool 成功返回true
 */
function markGameExit($record_id) {
    global $pdo;
    
    try {
        // 更新記錄狀態為退出
        $stmt = $pdo->prepare("
            UPDATE game_records 
            SET status = 'exited', updated_at = NOW()
            WHERE record_id = ?
        ");
        
        $result = $stmt->execute([$record_id]);
        
        if ($result) {
            // 獲取記錄詳情用於行為記錄
            $detail_stmt = $pdo->prepare("
                SELECT member_id, game_type, play_time 
                FROM game_records 
                WHERE record_id = ?
            ");
            $detail_stmt->execute([$record_id]);
            $record = $detail_stmt->fetch();
            
            if ($record && function_exists('logGameBehavior')) {
                logGameBehavior($record['member_id'], $record['game_type'], $record['play_time'], 0, null, 'game_exit');
            }
            
            error_log("遊戲退出標記: 記錄ID{$record_id}");
        }
        
        return $result;
        
    } catch (Exception $e) {
        error_log("標記遊戲退出失敗: " . $e->getMessage());
        return false;
    }
}

/**
 * 清理過期的遊戲進入記錄（超過一定時間沒有更新的記錄）
 * @param int $timeout_minutes 超時時間（分鐘）
 * @return int 清理的記錄數量
 */
function cleanupExpiredGameEntries($timeout_minutes = 30) {
    global $pdo;
    
    try {
        // 查找超過指定時間沒有更新的進入記錄
        $stmt = $pdo->prepare("
            SELECT record_id 
            FROM game_records 
            WHERE status = 'entered' 
            AND created_at < DATE_SUB(NOW(), INTERVAL ? MINUTE)
        ");
        $stmt->execute([$timeout_minutes]);
        $expired_records = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $cleaned_count = 0;
        foreach ($expired_records as $record_id) {
            if (markGameExit($record_id)) {
                $cleaned_count++;
            }
        }
        
        error_log("清理過期遊戲進入記錄: {$cleaned_count}筆");
        return $cleaned_count;
        
    } catch (Exception $e) {
        error_log("清理過期記錄失敗: " . $e->getMessage());
        return 0;
    }
}
?>
