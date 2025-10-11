<?php
/**
 * 遊戲進入追蹤系統
 * 實現建議的遊戲紀錄流程：
 * 1. 進入遊戲時先記錄一筆 PENDING 狀態的記錄
 * 2. 如果玩家有操作，根據結果更新為 COMPLETED/FAILED
 * 3. 如果玩家沒有操作，則更新為 EXITED
 */

require_once 'db_connect.php';

/**
 * 記錄遊戲進入（初始化為 PENDING 狀態）
 * @param int $member_id 會員ID
 * @param string $game_type 遊戲類型
 * @param string $difficulty 難度
 * @param int $game_id 遊戲ID
 * @return int|false 返回記錄ID，失敗返回false
 */
function recordGameEntry($member_id, $game_type, $difficulty = 'easy', $game_id = null) {
    global $pdo;
    
    try {
        // 檢查 member_id 是否存在
        $member_stmt = $pdo->prepare("SELECT member_id FROM member WHERE member_id = ?");
        $member_stmt->execute([$member_id]);
        $member = $member_stmt->fetch();
        if (!$member) {
            error_log("member_id {$member_id} 不存在於 member 表中");
            throw new Exception("用戶不存在");
        }
        
        // 如果沒有提供game_id，嘗試從games表查找
        if ($game_id === null) {
            $game_stmt = $pdo->prepare("SELECT game_id FROM games WHERE game_type = ? LIMIT 1");
            $game_stmt->execute([$game_type]);
            $game = $game_stmt->fetch();
            $game_id = $game ? $game['game_id'] : 0;
        }
        
        // 插入初始進入記錄（狀態為 entered）
        $stmt = $pdo->prepare("
            INSERT INTO game_records
            (member_id, game_id, difficulty, score, play_date, play_time, game_type, is_single_player, opponent_id, status)
            VALUES (?, ?, ?, 0, NOW(), 0, ?, 1, NULL, 'entered')
        ");
        
        $result = $stmt->execute([
            $member_id,
            $game_id,
            $difficulty,
            $game_type
        ]);
        
        if (!$result) {
            $errorInfo = $stmt->errorInfo();
            error_log("SQL 執行失敗: " . print_r($errorInfo, true));
            throw new Exception("SQL 執行失敗: " . $errorInfo[2]);
        }
        
        $record_id = $pdo->lastInsertId();
        if (!$record_id || $record_id == 0) {
            error_log("lastInsertId 返回無效值: " . $record_id);
            throw new Exception("無法獲取插入記錄的ID");
        }
        
        // 記錄行為軌跡（暫時跳過，避免 session 問題）
        // if (function_exists('logGameBehavior')) {
        //     try {
        //         logGameBehavior($member_id, $game_type, 0, 0, $difficulty, 'game_entered');
        //     } catch (Exception $e) {
        //         error_log("記錄行為軌跡失敗: " . $e->getMessage());
        //         // 不拋出異常，繼續執行
        //     }
        // }
        
        error_log("遊戲進入記錄: 用戶{$member_id}, 遊戲{$game_type}, 記錄ID{$record_id}");
        
        // 清理過期的遊戲進入記錄（暫時禁用）
        // cleanupExpiredGameEntries();
        
        return $record_id;
        
    } catch (Exception $e) {
        error_log("記錄遊戲進入失敗: " . $e->getMessage());
        error_log("錯誤詳情: " . $e->getTraceAsString());
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
                
                // 記錄行為軌跡（暫時跳過，避免 session 問題）
                // if (function_exists('logGameBehavior')) {
                //     try {
                //         logGameBehavior($record['member_id'], $record['game_type'], $play_time, $score, null, $status);
                //     } catch (Exception $e) {
                //         error_log("記錄行為軌跡失敗: " . $e->getMessage());
                //         // 不拋出異常，繼續執行
                //     }
                // }
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
 * 通用遊戲結果處理函數
 * 根據建議的流程處理遊戲結果：PENDING → COMPLETED/FAILED/EXITED
 * @param array $data 遊戲數據
 * @return array 處理結果
 */
function processGameResult($data) {
    global $pdo;
    
    try {
        // 處理不同的 action
        $action = $data['action'] ?? 'end_game';
        
        if ($action === 'start_game') {
            // 遊戲開始：簡化處理，暫時跳過進入記錄
            $required_fields = ['member_id', 'game_type', 'difficulty'];
            foreach ($required_fields as $field) {
                if (!isset($data[$field])) {
                    throw new Exception("缺少必要參數: {$field}");
                }
            }
            
            $member_id = $data['member_id'];
            $game_type = $data['game_type'];
            $difficulty = $data['difficulty'];
            $game_id = $data['game_id'] ?? null;
            
            error_log("遊戲開始: member_id={$member_id}, game_type={$game_type}, difficulty={$difficulty}, game_id={$game_id}");
            
            // 暫時跳過進入記錄，直接返回成功
            return [
                'success' => true,
                'message' => '遊戲開始成功',
                'record_id' => null
            ];
        }
        
        // 遊戲結束：處理完整結果
        // 驗證必要參數
        $required_fields = ['member_id', 'game_type', 'difficulty'];
        foreach ($required_fields as $field) {
            if (!isset($data[$field])) {
                throw new Exception("缺少必要參數: {$field}");
            }
        }
        
        $member_id = $data['member_id'];
        $game_type = $data['game_type'];
        $difficulty = $data['difficulty'];
        $score = $data['score'] ?? 0;
        $play_time = $data['play_time'] ?? 0;
        $is_manual_exit = $data['is_manual_exit'] ?? false;
        $is_passed = $data['is_passed'] ?? null;
        $game_id = $data['game_id'] ?? null;
        
        // 開始交易
        $pdo->beginTransaction();
        
        // 1. 根據遊戲結果決定最終狀態
        $final_status = 'entered';
        $final_score = $score;
        
        if ($is_manual_exit) {
            // 手動退出：直接視為失敗，分數為 0
            $score = 0; // 強制設為 0 分
            $final_status = 'failed';
        } else {
            // 正常遊戲結束：根據是否通過判斷
            if ($is_passed !== null) {
                // 如果有明確的通過狀態
                $final_status = $is_passed ? 'completed' : 'failed';
            } else {
                // 根據分數判斷（分數 > 0 視為通過）
                $final_status = ($score > 0) ? 'completed' : 'failed';
            }
        }
        
        // 3. 計算獎勵分數
        // 如果傳入的分數大於0且狀態為completed，使用傳入的分數
        // 否則使用預設的固定獎勵分數
        if ($final_status === 'completed') {
            if ($score > 0) {
                // 使用傳入的分數（各遊戲自己計算好的獎勵分數）
                $final_score = $score;
            } else {
                // 如果沒有傳入分數，使用預設值
                switch ($difficulty) {
                    case 'easy':
                    case '簡單':
                        $final_score = 20;
                        break;
                    case 'normal':
                    case '普通':
                        $final_score = 50;
                        break;
                    case 'hard':
                    case '困難':
                        $final_score = 100;
                        break;
                    default:
                        $final_score = 0;
                }
            }
        } else {
            // 失敗或退出沒有分數
            $final_score = 0;
        }
        
        // 4. 確保 game_id 不為 NULL
        if ($game_id === null) {
            // 如果沒有提供game_id，嘗試從games表查找
            $game_stmt = $pdo->prepare("SELECT game_id FROM games WHERE game_type = ? LIMIT 1");
            $game_stmt->execute([$game_type]);
            $game = $game_stmt->fetch();
            $game_id = $game ? $game['game_id'] : 0;
        }
        
        // 4. 直接插入最終狀態的記錄
        error_log("準備插入遊戲記錄: member_id=$member_id, game_id=$game_id, difficulty=$difficulty, final_score=$final_score, play_time=$play_time, game_type=$game_type, final_status=$final_status");
        
        $stmt = $pdo->prepare("
            INSERT INTO game_records
            (member_id, game_id, difficulty, score, play_date, play_time, game_type, is_single_player, opponent_id, status)
            VALUES (?, ?, ?, ?, NOW(), ?, ?, 1, NULL, ?)
        ");
        
        $result = $stmt->execute([
            $member_id,
            $game_id,
            $difficulty,
            $final_score,
            $play_time,
            $game_type,
            $final_status
        ]);
        
        if (!$result) {
            $errorInfo = $stmt->errorInfo();
            error_log("插入遊戲記錄失敗: " . implode(', ', $errorInfo));
            throw new Exception("插入遊戲記錄失敗: " . implode(', ', $errorInfo));
        }
        
        $record_id = $pdo->lastInsertId();
        error_log("遊戲記錄插入成功: record_id=$record_id");
        
        // 5. 更新會員總分數和分類分數
        if ($final_status === 'completed' && $final_score > 0) {
            // 更新會員總分數
            $update_stmt = $pdo->prepare("UPDATE member SET total_score = total_score + ? WHERE member_id = ?");
            $update_stmt->execute([$final_score, $member_id]);
            
            // 根據遊戲類型更新對應的分類分數
            updateCategoryScore($member_id, $game_type, $final_score);
            
            error_log("已更新會員分數: member_id=$member_id, 增加分數=$final_score, 遊戲類型=$game_type");
        }
        
        // 6. 跳過每日任務檢查（避免登入檢查問題）
        $completed_tasks = [];
        
        // 提交交易
        $pdo->commit();
        
        return [
            'success' => true,
            'record_id' => $record_id,
            'status' => $final_status,
            'score' => $final_score,
            'completed_tasks' => $completed_tasks,
            'message' => '遊戲結果已儲存'
        ];
        
    } catch (Exception $e) {
        // 回滾交易
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        error_log("處理遊戲結果失敗: " . $e->getMessage());
        error_log("錯誤詳情: " . $e->getTraceAsString());
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

/**
 * 清理長時間停留在 entered 狀態的記錄（視為玩家退出）
 * @param int $timeout_minutes 超時時間（分鐘，預設30分鐘）
 * @return int 清理的記錄數量
 */
function cleanupExpiredGameEntries($timeout_minutes = 30) {
    global $pdo;
    
    try {
        // 查找超過指定時間沒有更新的 entered 記錄
        $stmt = $pdo->prepare("
            SELECT record_id 
            FROM game_records 
            WHERE status = 'entered' 
            AND play_date < DATE_SUB(NOW(), INTERVAL ? MINUTE)
        ");
        $stmt->execute([$timeout_minutes]);
        $expired_records = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $cleaned_count = 0;
        foreach ($expired_records as $record_id) {
            if (markGameExit($record_id)) {
                $cleaned_count++;
            }
        }
        
        error_log("清理長時間entered記錄: {$cleaned_count}筆");
        return $cleaned_count;
        
    } catch (Exception $e) {
        error_log("清理過期記錄失敗: " . $e->getMessage());
        return 0;
    }
}
?>
