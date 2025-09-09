<?php
/**
 * 遊戲行為軌跡記錄函數
 * 自動判斷短時間遊戲記錄並記錄為遊戲退出行為
 */

function logGameBehavior($member_id, $game_type, $play_time, $score, $difficulty = null) {
    global $pdo;
    
    try {
        // 確保 session 已啟動
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        // 判斷是否為短時間退出（≤15秒）
        $is_quick_exit = ($play_time <= 15 && $play_time > 0);
        
        // 判斷是否為正常完成（有分數且時間合理）
        $is_complete = ($score > 0 && $play_time > 15);
        
        // 生成session_id（如果不存在）
        if (!isset($_SESSION['behavior_session_id'])) {
            $_SESSION['behavior_session_id'] = 'session_' . time() . '_' . rand(1000, 9999);
        }
        $session_id = $_SESSION['behavior_session_id'];
        
        // 準備行為記錄數據
        $action_type = '';
        $additional_data = [
            'game_type' => $game_type,
            'play_time' => $play_time,
            'score' => $score,
            'difficulty' => $difficulty,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        // 根據遊戲情況決定行為類型
        if ($is_quick_exit) {
            $action_type = 'game_exit';
            $additional_data['exit_reason'] = 'quick_exit';
            $additional_data['exit_point'] = 'early_game';
        } elseif ($is_complete) {
            $action_type = 'game_complete';
            $additional_data['completion_status'] = 'success';
        } else {
            // 其他情況（失敗但玩了較長時間）
            $action_type = 'game_complete';
            $additional_data['completion_status'] = 'failed';
        }
        
        // 插入行為記錄
        $behavior_sql = "
            INSERT INTO user_behavior_log 
            (member_id, action_type, page_url, session_id, ip_address, user_agent, additional_data, game_type, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ";
        
        $stmt = $pdo->prepare($behavior_sql);
        $stmt->execute([
            $member_id,
            $action_type,
            $_SERVER['REQUEST_URI'] ?? '',
            $session_id,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            json_encode($additional_data),
            $game_type
        ]);
        
        // 記錄日誌（除錯用）
        error_log("遊戲行為記錄: 用戶{$member_id}, 遊戲{$game_type}, 時間{$play_time}秒, 行為{$action_type}");
        
        return true;
        
    } catch (Exception $e) {
        error_log("記錄遊戲行為失敗: " . $e->getMessage());
        return false;
    }
}

/**
 * 批量分析現有遊戲記錄並補充行為軌跡
 * 用於一次性處理歷史數據
 */
function analyzeExistingGameRecords() {
    global $pdo;
    
    try {
        // 查詢所有有遊戲時間的記錄，但還沒有對應行為軌跡的
        $sql = "
            SELECT gr.*, m.member_name
            FROM game_records gr
            LEFT JOIN member m ON gr.member_id = m.member_id
            WHERE gr.play_time IS NOT NULL 
            AND gr.play_time > 0
            AND NOT EXISTS (
                SELECT 1 FROM user_behavior_log ubl 
                WHERE ubl.member_id = gr.member_id 
                AND ubl.game_type = gr.game_type
                AND DATE(ubl.created_at) = DATE(gr.play_date)
                AND ubl.action_type IN ('game_exit', 'game_complete')
            )
            ORDER BY gr.play_date DESC
            LIMIT 500
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $records = $stmt->fetchAll();
        
        $processed = 0;
        $quick_exits = 0;
        $completions = 0;
        
        foreach ($records as $record) {
            // 模擬session環境
            $_SESSION['behavior_session_id'] = 'batch_' . $record['record_id'];
            
            // 記錄行為軌跡
            if (logGameBehavior(
                $record['member_id'], 
                $record['game_type'], 
                $record['play_time'], 
                $record['score'], 
                $record['difficulty']
            )) {
                $processed++;
                
                if ($record['play_time'] <= 15) {
                    $quick_exits++;
                } else {
                    $completions++;
                }
            }
        }
        
        return [
            'total_processed' => $processed,
            'quick_exits' => $quick_exits,
            'completions' => $completions,
            'total_records' => count($records)
        ];
        
    } catch (Exception $e) {
        error_log("批量分析遊戲記錄失敗: " . $e->getMessage());
        return false;
    }
}
?>
