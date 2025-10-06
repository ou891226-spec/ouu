<?php
require_once 'db.php';
session_start();

// 檢查用戶是否已登入
if (!isset($_SESSION['member_id']) || empty($_SESSION['member_id'])) {
    echo json_encode(['error' => '用戶未登入'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 獲取當前登入用戶的ID
$member_id = $_SESSION['member_id'];

// 從資料庫獲取用戶實際擁有的任務狀態（排除登入任務，只取前3個）
// 支援累積型任務的進度計算
$tasks_sql = "
SELECT d.task_id, d.task_name, d.task_description, d.task_type, d.reward_points,
       mt.completed_date,
       mt.claimed_date,
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
               -- 遊戲達人：完成10局遊戲（更寬鬆的匹配條件）
               (d.task_name = '遊戲達人' OR d.task_description LIKE '%完成10局%' OR d.task_description LIKE '%10局%' OR d.task_description LIKE '%完成%局遊戲%') AND
               (SELECT COUNT(*) FROM game_records WHERE member_id = mt.member_id AND DATE(play_date) = CURDATE()) >= 10
           ) THEN 'completed'
           WHEN (
               -- 遊戲愛好者：完成15局遊戲
               (d.task_name = '遊戲愛好者' OR d.task_description LIKE '%完成15局%' OR d.task_description LIKE '%15局%') AND
               (SELECT COUNT(*) FROM game_records WHERE member_id = mt.member_id AND DATE(play_date) = CURDATE()) >= 15
           ) THEN 'completed'
           WHEN (
               -- 分數效率：平均每局獲得50分以上
               (d.task_name = '分數效率' OR d.task_description LIKE '%平均每局獲得50分以上%') AND
               (SELECT COALESCE(AVG(score), 0) FROM game_records WHERE member_id = mt.member_id AND DATE(play_date) = CURDATE()) >= 50
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
               (d.task_description LIKE '%獲得1000分%' OR d.task_description LIKE '%總分達到1000分%') AND
               (SELECT COALESCE(SUM(score), 0) FROM game_records WHERE member_id = mt.member_id AND DATE(play_date) = CURDATE()) >= 1000
           ) THEN 'completed'
           WHEN (
               -- 分數挑戰者：累積獲得200分以上
               (d.task_name = '分數挑戰者' OR d.task_description LIKE '%累積獲得200分以上%' OR d.task_description LIKE '%單局獲得200分以上%') AND
               (SELECT COALESCE(SUM(score), 0) FROM game_records WHERE member_id = mt.member_id AND DATE(play_date) = CURDATE()) >= 200
           ) THEN 'completed'
           WHEN (
               -- 分數王者：總分達到5000分
               (d.task_name = '分數王者' OR d.task_description LIKE '%總分達到5000分%') AND
               (SELECT COALESCE(SUM(score), 0) FROM game_records WHERE member_id = mt.member_id AND DATE(play_date) = CURDATE()) >= 5000
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
               -- 平衡玩家：每種類型遊戲都完成至少1局
               (d.task_name = '平衡玩家' OR d.task_description LIKE '%每種類型遊戲都完成至少1局%') AND
               (SELECT COUNT(DISTINCT CASE 
                   WHEN game_type IN ('記憶力', '翻牌對對樂', '圖片線索問答', '追蹤犯人遊戲') THEN '記憶力'
                   WHEN game_type IN ('反應力', '接金蛋遊戲', '看字選色遊戲', '節奏遊戲') THEN '反應力'
                   WHEN game_type IN ('算術邏輯力', '2048', '算菜錢遊戲', '過河遊戲', '邏輯力') THEN '算術邏輯力'
                   ELSE game_type
               END) FROM game_records 
               WHERE member_id = mt.member_id 
               AND DATE(play_date) = CURDATE() 
               AND status = 'completed') >= 3
           ) THEN 'completed'
           WHEN (
               -- 全面發展：每種類型遊戲都完成至少3局
               (d.task_name = '全面發展' OR d.task_description LIKE '%每種類型遊戲都完成至少3局%') AND
               (SELECT COUNT(DISTINCT CASE 
                   WHEN game_type IN ('記憶力', '翻牌對對樂', '圖片線索問答', '追蹤犯人遊戲') THEN '記憶力'
                   WHEN game_type IN ('反應力', '接金蛋遊戲', '看字選色遊戲', '節奏遊戲') THEN '反應力'
                   WHEN game_type IN ('算術邏輯力', '2048', '算菜錢遊戲', '過河遊戲', '邏輯力') THEN '算術邏輯力'
                   ELSE game_type
               END) FROM game_records 
               WHERE member_id = mt.member_id 
               AND DATE(play_date) = CURDATE() 
               AND status = 'completed') >= 3 AND
               (SELECT COUNT(*) FROM (
                   SELECT CASE 
                       WHEN game_type IN ('記憶力', '翻牌對對樂', '圖片線索問答', '追蹤犯人遊戲') THEN '記憶力'
                       WHEN game_type IN ('反應力', '接金蛋遊戲', '看字選色遊戲', '節奏遊戲') THEN '反應力'
                       WHEN game_type IN ('算術邏輯力', '2048', '算菜錢遊戲', '過河遊戲', '邏輯力') THEN '算術邏輯力'
                       ELSE game_type 
                   END as game_category,
                   COUNT(*) as count
                   FROM game_records 
                   WHERE member_id = mt.member_id 
                   AND DATE(play_date) = CURDATE()
                   AND status = 'completed'
                   GROUP BY game_category
                   HAVING count >= 3
               ) as qualified_categories) >= 3
           ) THEN 'completed'
           WHEN (
               -- 持久戰士：累積遊戲時間達到目標
               (d.task_description LIKE '%分鐘%' OR d.task_description LIKE '%遊玩時間%') AND
               (SELECT COALESCE(SUM(play_time), 0) FROM game_records WHERE member_id = mt.member_id AND DATE(play_date) = CURDATE() AND play_time IS NOT NULL) >= CASE 
                   WHEN d.task_description LIKE '%5分鐘%' THEN 300
                   WHEN d.task_description LIKE '%3分鐘%' THEN 180
                   WHEN d.task_description LIKE '%10分鐘%' THEN 600
                   ELSE 300
               END
           ) THEN 'completed'
           WHEN (
               -- 線索專家：完成圖片線索問答遊戲
               (d.task_name = '線索專家' OR d.task_description LIKE '%圖片線索問答%') AND
               (SELECT COUNT(*) FROM game_records WHERE member_id = mt.member_id AND DATE(play_date) = CURDATE() AND game_type = '記憶力' AND game_id = 8) >= 1
           ) THEN 'completed'
           WHEN (
               -- 過河大師：完成過河遊戲
               (d.task_name = '過河大師' OR d.task_description LIKE '%過河遊戲%') AND
               (SELECT COUNT(*) FROM game_records WHERE member_id = mt.member_id AND DATE(play_date) = CURDATE() AND game_type = '算術邏輯力' AND game_id = 9) >= 1
           ) THEN 'completed'
           WHEN (
               -- 技藝達人：完成記憶力遊戲
               (d.task_name = '技藝達人' OR d.task_description LIKE '%記憶力遊戲%' OR d.task_description LIKE '%記憶遊戲%') AND
               (SELECT COUNT(*) FROM game_records WHERE member_id = mt.member_id AND DATE(play_date) = CURDATE() AND game_type = '記憶力') >= 3
           ) THEN 'completed'
           WHEN (
               -- 反應大師：完成反應力遊戲
               (d.task_name = '反應大師' OR d.task_description LIKE '%反應力遊戲%' OR d.task_description LIKE '%反應遊戲%' OR d.task_description LIKE '%節奏遊戲%') AND
               (SELECT COUNT(*) FROM game_records WHERE member_id = mt.member_id AND DATE(play_date) = CURDATE() AND game_type = '反應力') >= 1
           ) THEN 'completed'
           WHEN (
               -- 邏輯專家：完成邏輯遊戲
               (d.task_name = '邏輯專家' OR d.task_description LIKE '%邏輯遊戲%' OR d.task_description LIKE '%2048%') AND
               (SELECT COUNT(*) FROM game_records WHERE member_id = mt.member_id AND DATE(play_date) = CURDATE() AND (game_type = '算術邏輯力' OR game_type = '邏輯力')) >= 3
           ) THEN 'completed'
           WHEN (
               -- 手眼協調大師：完成手眼協調遊戲
               (d.task_name = '手眼協調大師' OR d.task_description LIKE '%手眼協調%' OR d.task_description LIKE '%接金蛋%') AND
               (SELECT COUNT(*) FROM game_records WHERE member_id = mt.member_id AND DATE(play_date) = CURDATE() AND game_type = '反應力') >= 1
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
               -- 遊戲之神：完成100局遊戲
               (d.task_name = '遊戲之神' OR d.task_description LIKE '%完成100局遊戲%') AND
               (SELECT COUNT(*) FROM game_records WHERE member_id = mt.member_id AND DATE(play_date) = CURDATE()) >= 100
           ) THEN 'completed'
           WHEN (
               -- 簡單專家：完成10局簡單難度遊戲
               (d.task_name = '簡單專家' OR d.task_description LIKE '%完成10局簡單難度遊戲%') AND
               (SELECT COUNT(*) FROM game_records WHERE member_id = mt.member_id AND DATE(play_date) = CURDATE() AND difficulty = 'easy') >= 10
           ) THEN 'completed'
           WHEN (
               -- 普通大師：完成10局普通難度遊戲
               (d.task_name = '普通大師' OR d.task_description LIKE '%完成10局普通難度遊戲%') AND
               (SELECT COUNT(*) FROM game_records WHERE member_id = mt.member_id AND DATE(play_date) = CURDATE() AND difficulty = 'normal') >= 10
           ) THEN 'completed'
           WHEN (
               -- 困難王者：完成5局困難難度遊戲
               (d.task_name = '困難王者' OR d.task_description LIKE '%完成5局困難難度遊戲%') AND
               (SELECT COUNT(*) FROM game_records WHERE member_id = mt.member_id AND DATE(play_date) = CURDATE() AND difficulty = 'hard') >= 5
           ) THEN 'completed'
           WHEN (
               -- 快速完成：5分鐘內完成3局遊戲
               (d.task_name = '快速完成' OR d.task_description LIKE '%5分鐘內完成3局遊戲%') AND
               (SELECT COUNT(*) FROM game_records WHERE member_id = mt.member_id AND DATE(play_date) = CURDATE() AND play_time <= 300) >= 3
           ) THEN 'completed'
           WHEN (
               -- 效率專家：10分鐘內完成5局遊戲
               (d.task_name = '效率專家' OR d.task_description LIKE '%10分鐘內完成5局遊戲%') AND
               (SELECT COUNT(*) FROM game_records WHERE member_id = mt.member_id AND DATE(play_date) = CURDATE() AND play_time <= 600) >= 5
           ) THEN 'completed'
           ELSE 'pending'
       END as status,
       CASE 
           -- 遊戲大師：完成25個關卡（計算所有遊戲關卡的完成次數）
           WHEN d.task_name = '遊戲大師' OR d.task_description LIKE '%完成25個關卡%' OR d.task_description LIKE '%25個關卡%' THEN (
               SELECT COUNT(*) FROM game_records 
               WHERE member_id = mt.member_id 
               AND DATE(play_date) = CURDATE()
           )
           -- 遊戲傳奇：完成50個關卡（計算所有遊戲關卡的完成次數）
           WHEN d.task_name = '遊戲傳奇' OR d.task_description LIKE '%完成50個關卡%' OR d.task_description LIKE '%50個關卡%' THEN (
               SELECT COUNT(*) FROM game_records 
               WHERE member_id = mt.member_id 
               AND DATE(play_date) = CURDATE()
           )
           -- 遊戲達人：完成10局遊戲（更寬鬆的匹配條件）
           WHEN d.task_name = '遊戲達人' OR d.task_description LIKE '%完成10局%' OR d.task_description LIKE '%10局%' OR d.task_description LIKE '%完成%局遊戲%' THEN (
               SELECT COUNT(*) FROM game_records 
               WHERE member_id = mt.member_id 
               AND DATE(play_date) = CURDATE()
           )
           -- 遊戲愛好者：完成15局遊戲
           WHEN d.task_name = '遊戲愛好者' OR d.task_description LIKE '%完成15局%' OR d.task_description LIKE '%15局%' THEN (
               SELECT COUNT(*) FROM game_records 
               WHERE member_id = mt.member_id 
               AND DATE(play_date) = CURDATE()
           )
           -- 分數效率：平均每局獲得50分以上（只計算今天的）
           WHEN d.task_name = '分數效率' OR d.task_description LIKE '%平均每局獲得50分以上%' THEN (
               SELECT COALESCE(AVG(score), 0) FROM game_records 
               WHERE member_id = mt.member_id 
               AND DATE(play_date) = CURDATE()
           )
           -- 績分高手：總分達到50分（只計算今天的）
           WHEN d.task_name = '績分高手' OR d.task_description LIKE '%總分達到50分%' THEN (
               SELECT COALESCE(SUM(score), 0) FROM game_records 
               WHERE member_id = mt.member_id 
               AND DATE(play_date) = CURDATE()
           )
           -- 進階者：總分達到500分（只計算今天的）
           WHEN d.task_name = '進階者' OR d.task_description LIKE '%總分達到500分%' THEN (
               SELECT COALESCE(SUM(score), 0) FROM game_records 
               WHERE member_id = mt.member_id 
               AND DATE(play_date) = CURDATE()
           )
           -- 千分大師：總分達到1000分（只計算今天的）
           WHEN d.task_name = '千分大師' OR d.task_description LIKE '%總分達到1000分%' THEN (
               SELECT COALESCE(SUM(score), 0) FROM game_records 
               WHERE member_id = mt.member_id 
               AND DATE(play_date) = CURDATE()
           )
           -- 分數王者：總分達到5000分（只計算今天的）
           WHEN d.task_name = '分數王者' OR d.task_description LIKE '%總分達到5000分%' THEN (
               SELECT COALESCE(SUM(score), 0) FROM game_records 
               WHERE member_id = mt.member_id 
               AND DATE(play_date) = CURDATE()
           )
           -- 分數挑戰者：累積獲得200分以上（只計算今天的）
           WHEN d.task_name = '分數挑戰者' OR d.task_description LIKE '%累積獲得200分以上%' OR d.task_description LIKE '%單局獲得200分以上%' THEN (
               SELECT COALESCE(SUM(score), 0) FROM game_records 
               WHERE member_id = mt.member_id 
               AND DATE(play_date) = CURDATE()
           )
           -- 分數收集者：獲得指定分數（只計算今天的）
           WHEN d.task_description LIKE '%獲得%分%' OR d.task_description LIKE '%分數%' THEN (
               SELECT COALESCE(SUM(score), 0) FROM game_records 
               WHERE member_id = mt.member_id 
               AND DATE(play_date) = CURDATE()
           )
           -- 全能玩家：完成三種不同類型遊戲（記憶力、反應力、算術邏輯力）
           WHEN d.task_name = '全能玩家' OR d.task_description LIKE '%三種不同類型%' OR d.task_description LIKE '%不同類型%' OR d.task_description LIKE '%所有類型%' THEN (
               SELECT COUNT(DISTINCT CASE 
                   WHEN game_type IN ('記憶力', '翻牌對對樂', '圖片線索問答', '追蹤犯人遊戲') THEN '記憶力'
                   WHEN game_type IN ('反應力', '接金蛋遊戲', '看字選色遊戲', '節奏遊戲') THEN '反應力'
                   WHEN game_type IN ('算術邏輯力', '2048', '算菜錢遊戲', '過河遊戲', '邏輯力') THEN '算術邏輯力'
                   ELSE game_type
               END) FROM game_records 
               WHERE member_id = mt.member_id 
               AND DATE(play_date) = CURDATE()
           )
           -- 平衡玩家：每種類型遊戲都完成至少1局
           WHEN d.task_name = '平衡玩家' OR d.task_description LIKE '%每種類型遊戲都完成至少1局%' THEN (
               SELECT COUNT(DISTINCT CASE 
                   WHEN game_type IN ('記憶力', '翻牌對對樂', '圖片線索問答', '追蹤犯人遊戲') THEN '記憶力'
                   WHEN game_type IN ('反應力', '接金蛋遊戲', '看字選色遊戲', '節奏遊戲') THEN '反應力'
                   WHEN game_type IN ('算術邏輯力', '2048', '算菜錢遊戲', '過河遊戲', '邏輯力') THEN '算術邏輯力'
                   ELSE game_type
               END) FROM game_records 
               WHERE member_id = mt.member_id 
               AND DATE(play_date) = CURDATE()
               AND status = 'completed'
               AND (
                   (CASE WHEN game_type IN ('記憶力', '翻牌對對樂', '圖片線索問答', '追蹤犯人遊戲') THEN '記憶力'
                         WHEN game_type IN ('反應力', '接金蛋遊戲', '看字選色遊戲', '節奏遊戲') THEN '反應力'
                         WHEN game_type IN ('算術邏輯力', '2048', '算菜錢遊戲', '過河遊戲', '邏輯力') THEN '算術邏輯力'
                         ELSE game_type END) IN (
                       SELECT game_category FROM (
                           SELECT CASE 
                               WHEN game_type IN ('記憶力', '翻牌對對樂', '圖片線索問答', '追蹤犯人遊戲') THEN '記憶力'
                               WHEN game_type IN ('反應力', '接金蛋遊戲', '看字選色遊戲', '節奏遊戲') THEN '反應力'
                               WHEN game_type IN ('算術邏輯力', '2048', '算菜錢遊戲', '過河遊戲', '邏輯力') THEN '算術邏輯力'
                               ELSE game_type 
                           END as game_category,
                           COUNT(*) as count
                           FROM game_records 
                           WHERE member_id = mt.member_id 
                           AND DATE(play_date) = CURDATE()
                           AND status = 'completed'
                           GROUP BY game_category
                           HAVING count >= 1
                       ) as qualified_categories
                   )
               )
           )
           -- 全面發展：每種類型遊戲都完成至少3局
           WHEN d.task_name = '全面發展' OR d.task_description LIKE '%每種類型遊戲都完成至少3局%' THEN (
               SELECT COUNT(DISTINCT CASE 
                   WHEN game_type IN ('記憶力', '翻牌對對樂', '圖片線索問答', '追蹤犯人遊戲') THEN '記憶力'
                   WHEN game_type IN ('反應力', '接金蛋遊戲', '看字選色遊戲', '節奏遊戲') THEN '反應力'
                   WHEN game_type IN ('算術邏輯力', '2048', '算菜錢遊戲', '過河遊戲', '邏輯力') THEN '算術邏輯力'
                   ELSE game_type
               END) FROM game_records 
               WHERE member_id = mt.member_id 
               AND DATE(play_date) = CURDATE()
               AND status = 'completed'
               AND (
                   (CASE WHEN game_type IN ('記憶力', '翻牌對對樂', '圖片線索問答', '追蹤犯人遊戲') THEN '記憶力'
                         WHEN game_type IN ('反應力', '接金蛋遊戲', '看字選色遊戲', '節奏遊戲') THEN '反應力'
                         WHEN game_type IN ('算術邏輯力', '2048', '算菜錢遊戲', '過河遊戲', '邏輯力') THEN '算術邏輯力'
                         ELSE game_type END) IN (
                       SELECT game_category FROM (
                           SELECT CASE 
                               WHEN game_type IN ('記憶力', '翻牌對對樂', '圖片線索問答', '追蹤犯人遊戲') THEN '記憶力'
                               WHEN game_type IN ('反應力', '接金蛋遊戲', '看字選色遊戲', '節奏遊戲') THEN '反應力'
                               WHEN game_type IN ('算術邏輯力', '2048', '算菜錢遊戲', '過河遊戲', '邏輯力') THEN '算術邏輯力'
                               ELSE game_type 
                           END as game_category,
                           COUNT(*) as count
                           FROM game_records 
                           WHERE member_id = mt.member_id 
                           AND DATE(play_date) = CURDATE()
                           AND status = 'completed'
                           GROUP BY game_category
                           HAVING count >= 3
                       ) as qualified_categories
                   )
               )
           )
           -- 持久戰士：累積遊戲時間（更合理的累積模式）
           WHEN d.task_description LIKE '%分鐘%' OR d.task_description LIKE '%遊玩時間%' THEN (
               SELECT COALESCE(SUM(play_time), 0) FROM game_records 
               WHERE member_id = mt.member_id 
               AND DATE(play_date) = CURDATE() 
               AND play_time IS NOT NULL
           )
           -- 累積遊戲時間任務（只計算今天的）
           WHEN d.task_description LIKE '%累計%分鐘%' OR d.task_description LIKE '%總共%分鐘%' THEN (
               SELECT COALESCE(SUM(play_time), 0) FROM game_records 
               WHERE member_id = mt.member_id 
               AND DATE(play_date) = CURDATE() 
               AND play_time IS NOT NULL
           )
           -- 社交達人：添加好友
           WHEN d.task_description LIKE '%好友%' THEN (
               SELECT COUNT(*) FROM friends WHERE member_id = mt.member_id
           )
           -- 線索專家：完成圖片線索問答遊戲
           WHEN d.task_name = '線索專家' OR d.task_description LIKE '%圖片線索問答%' THEN (
               SELECT COUNT(*) FROM game_records 
               WHERE member_id = mt.member_id 
               AND DATE(play_date) = CURDATE() 
               AND game_type = '記憶力' 
               AND game_id = 8
           )
           -- 過河大師：完成過河遊戲
           WHEN d.task_name = '過河大師' OR d.task_description LIKE '%過河遊戲%' THEN (
               SELECT COUNT(*) FROM game_records 
               WHERE member_id = mt.member_id 
               AND DATE(play_date) = CURDATE() 
               AND game_type = '算術邏輯力' 
               AND game_id = 9
           )
           -- 技藝達人：完成記憶力遊戲
           WHEN d.task_name = '技藝達人' OR d.task_description LIKE '%記憶力遊戲%' OR d.task_description LIKE '%記憶遊戲%' THEN (
               SELECT COUNT(*) FROM game_records 
               WHERE member_id = mt.member_id 
               AND DATE(play_date) = CURDATE() 
               AND game_type = '記憶力'
           )
           -- 反應大師：完成反應力遊戲
           WHEN d.task_name = '反應大師' OR d.task_description LIKE '%反應力遊戲%' OR d.task_description LIKE '%反應遊戲%' THEN (
               SELECT COUNT(*) FROM game_records 
               WHERE member_id = mt.member_id 
               AND DATE(play_date) = CURDATE() 
               AND game_type = '反應力'
           )
           -- 邏輯專家：完成邏輯遊戲
           WHEN d.task_name = '邏輯專家' OR d.task_description LIKE '%邏輯遊戲%' OR d.task_description LIKE '%2048%' THEN (
               SELECT COUNT(*) FROM game_records 
               WHERE member_id = mt.member_id 
               AND DATE(play_date) = CURDATE() 
               AND (game_type = '算術邏輯力' OR game_type = '邏輯力')
           )
           -- 手眼協調大師：完成手眼協調遊戲
           WHEN d.task_name = '手眼協調大師' OR d.task_description LIKE '%手眼協調%' OR d.task_description LIKE '%接金蛋%' THEN (
               SELECT COUNT(*) FROM game_records 
               WHERE member_id = mt.member_id 
               AND DATE(play_date) = CURDATE() 
               AND game_type = '反應力'
           )
           -- 追蹤專家：完成追蹤犯人遊戲
           WHEN d.task_name = '追蹤專家' OR d.task_description LIKE '%追蹤犯人%' OR d.task_description LIKE '%犯人遊戲%' THEN (
               SELECT COUNT(*) FROM game_records 
               WHERE member_id = mt.member_id 
               AND DATE(play_date) = CURDATE() 
               AND game_type = '記憶力'
           )
           -- 成就大師：獲得成就數量
           WHEN d.task_name = '成就大師' OR d.task_description LIKE '%獲得.*成就%' OR d.task_description LIKE '%成就大師%' THEN (
               SELECT COUNT(*) FROM member_achievements ma 
               JOIN achievements a ON ma.achievement_id = a.achievement_id 
               WHERE ma.member_id = mt.member_id 
               AND DATE(ma.earned_date) = CURDATE() 
               AND a.achievement_name != '每日登入'
           )
           -- 速度之王：30秒內完成遊戲的次數
           WHEN d.task_description LIKE '%30秒%' OR d.task_name = '速度之王' THEN (
               SELECT COUNT(*) FROM game_records 
               WHERE member_id = mt.member_id 
               AND DATE(play_date) = CURDATE() 
               AND play_time <= 30
           )
           -- 刷新最高分數：檢查是否有新的最高分數記錄
           WHEN d.task_name = '刷新最高分數' OR d.task_description LIKE '%最高分數%' THEN (
               SELECT COUNT(*) FROM game_records 
               WHERE member_id = mt.member_id 
               AND DATE(play_date) = CURDATE() 
               AND score > (
                   SELECT COALESCE(MAX(score), 0) FROM game_records 
                   WHERE member_id = mt.member_id 
                   AND DATE(play_date) < CURDATE()
               )
           )
           -- 遊戲之神：完成100局遊戲
           WHEN d.task_name = '遊戲之神' OR d.task_description LIKE '%完成100局遊戲%' THEN (
               SELECT COUNT(*) FROM game_records 
               WHERE member_id = mt.member_id 
               AND DATE(play_date) = CURDATE()
           )
           -- 其他任務：完成狀態
           WHEN mt.claimed_date IS NOT NULL OR mt.completed_date IS NOT NULL THEN 1
           ELSE 0
       END as progress,
       CASE 
           -- 遊戲大師：完成25個關卡（所有遊戲的關卡總數）
           WHEN d.task_name = '遊戲大師' OR d.task_description LIKE '%完成25個關卡%' OR d.task_description LIKE '%25個關卡%' THEN 25
           -- 遊戲傳奇：完成50個關卡（所有遊戲關卡的2倍）
           WHEN d.task_name = '遊戲傳奇' OR d.task_description LIKE '%完成50個關卡%' OR d.task_description LIKE '%50個關卡%' THEN 50
           -- 遊戲達人：完成10局遊戲
           WHEN d.task_name = '遊戲達人' OR d.task_description LIKE '%完成10局%' OR d.task_description LIKE '%10局%' OR d.task_description LIKE '%完成%局遊戲%' THEN 10
           -- 遊戲愛好者：完成15局遊戲
           WHEN d.task_name = '遊戲愛好者' OR d.task_description LIKE '%完成15局%' OR d.task_description LIKE '%15局%' THEN 15
           -- 分數效率：平均每局獲得50分以上
           WHEN d.task_name = '分數效率' OR d.task_description LIKE '%平均每局獲得50分以上%' THEN 50
           -- 績分高手：總分達到50分
           WHEN d.task_name = '績分高手' OR d.task_description LIKE '%總分達到50分%' THEN 50
           -- 進階者：總分達到500分
           WHEN d.task_name = '進階者' OR d.task_description LIKE '%總分達到500分%' THEN 500
           -- 分數挑戰者：累積獲得200分以上
           WHEN d.task_name = '分數挑戰者' OR d.task_description LIKE '%累積獲得200分以上%' OR d.task_description LIKE '%單局獲得200分以上%' THEN 200
           -- 分數收集者：根據描述提取目標分數
           WHEN d.task_description LIKE '%獲得1000分%' OR d.task_description LIKE '%總分達到1000分%' THEN 1000
           WHEN d.task_description LIKE '%獲得500分%' OR d.task_description LIKE '%總分達到500分%' THEN 500
           WHEN d.task_description LIKE '%獲得2000分%' OR d.task_description LIKE '%總分達到2000分%' THEN 2000
           WHEN d.task_description LIKE '%獲得5000分%' OR d.task_description LIKE '%總分達到5000分%' THEN 5000
           WHEN d.task_description LIKE '%獲得10000分%' OR d.task_description LIKE '%總分達到10000分%' THEN 10000
           WHEN d.task_description LIKE '%分數%' THEN 1000  -- 預設1000分
           -- 全能玩家：3種不同類型
           WHEN d.task_name = '全能玩家' OR d.task_description LIKE '%三種不同類型%' OR d.task_description LIKE '%不同類型%' OR d.task_description LIKE '%所有類型%' THEN 3
           -- 平衡玩家：每種類型遊戲都完成至少1局
           WHEN d.task_name = '平衡玩家' OR d.task_description LIKE '%每種類型遊戲都完成至少1局%' THEN 3
           -- 全面發展：每種類型遊戲都完成至少3局
           WHEN d.task_name = '全面發展' OR d.task_description LIKE '%每種類型遊戲都完成至少3局%' THEN 3
           -- 持久戰士：累積遊戲時間任務（根據描述中的分鐘數計算秒數）
           WHEN d.task_description LIKE '%5分鐘%' THEN 300
           WHEN d.task_description LIKE '%3分鐘%' THEN 180
           WHEN d.task_description LIKE '%10分鐘%' THEN 600
           WHEN d.task_description LIKE '%30分鐘%' THEN 1800
           WHEN d.task_description LIKE '%60分鐘%' THEN 3600
           WHEN d.task_description LIKE '%分鐘%' OR d.task_description LIKE '%遊玩時間%' THEN 300  -- 預設5分鐘
           -- 社交任務：根據描述提取好友數量
           WHEN d.task_description LIKE '%10位好友%' OR d.task_description LIKE '%10個好友%' THEN 10
           WHEN d.task_description LIKE '%5位好友%' OR d.task_description LIKE '%5個好友%' THEN 5
           WHEN d.task_description LIKE '%3位好友%' OR d.task_description LIKE '%3個好友%' THEN 3
           WHEN d.task_name = '社交大師' OR d.task_description LIKE '%添加3%' THEN 3
           WHEN d.task_description LIKE '%添加10%' THEN 10
           WHEN d.task_description LIKE '%好友%' THEN 3  -- 預設改為3個
           -- 技藝達人：完成記憶力遊戲（根據關卡數設定）
           WHEN d.task_name = '技藝達人' OR d.task_description LIKE '%記憶力遊戲%' OR d.task_description LIKE '%記憶遊戲%' THEN 3
           -- 反應大師：完成反應力遊戲（根據關卡數設定）
           WHEN d.task_name = '反應大師' OR d.task_description LIKE '%反應力遊戲%' OR d.task_description LIKE '%反應遊戲%' OR d.task_description LIKE '%節奏遊戲%' THEN 1
           -- 邏輯專家：完成邏輯遊戲（根據關卡數設定）
           WHEN d.task_name = '邏輯專家' OR d.task_description LIKE '%邏輯遊戲%' OR d.task_description LIKE '%2048%' THEN 3
           -- 手眼協調大師：完成手眼協調遊戲（根據關卡數設定）
           WHEN d.task_name = '手眼協調大師' OR d.task_description LIKE '%手眼協調%' OR d.task_description LIKE '%接金蛋%' THEN 1
           -- 追蹤專家：完成追蹤犯人遊戲（根據關卡數設定）
           WHEN d.task_name = '追蹤專家' OR d.task_description LIKE '%追蹤犯人%' OR d.task_description LIKE '%犯人遊戲%' THEN 3
           -- 成就大師：獲得3個成就
           WHEN d.task_name = '成就大師' OR d.task_description LIKE '%獲得.*成就%' OR d.task_description LIKE '%成就大師%' THEN 3
           -- 線索專家和過河大師：完成1次
           WHEN d.task_name = '線索專家' OR d.task_description LIKE '%圖片線索問答%' THEN 1
           WHEN d.task_name = '過河大師' OR d.task_description LIKE '%過河遊戲%' THEN 1
           -- 速度之王：30秒內完成1場遊戲
           WHEN d.task_description LIKE '%30秒%' OR d.task_name = '速度之王' THEN 1
           -- 刷新最高分數：完成1次
           WHEN d.task_name = '刷新最高分數' OR d.task_description LIKE '%最高分數%' THEN 1
           -- 遊戲之神：完成100局遊戲
           WHEN d.task_name = '遊戲之神' OR d.task_description LIKE '%完成100局遊戲%' THEN 100
           -- 簡單專家：完成10局簡單難度遊戲
           WHEN d.task_name = '簡單專家' OR d.task_description LIKE '%完成10局簡單難度遊戲%' THEN (
               SELECT COUNT(*) FROM game_records 
               WHERE member_id = mt.member_id 
               AND DATE(play_date) = CURDATE() 
               AND difficulty = 'easy'
           )
           -- 普通大師：完成10局普通難度遊戲
           WHEN d.task_name = '普通大師' OR d.task_description LIKE '%完成10局普通難度遊戲%' THEN (
               SELECT COUNT(*) FROM game_records 
               WHERE member_id = mt.member_id 
               AND DATE(play_date) = CURDATE() 
               AND difficulty = 'normal'
           )
           -- 困難王者：完成5局困難難度遊戲
           WHEN d.task_name = '困難王者' OR d.task_description LIKE '%完成5局困難難度遊戲%' THEN (
               SELECT COUNT(*) FROM game_records 
               WHERE member_id = mt.member_id 
               AND DATE(play_date) = CURDATE() 
               AND difficulty = 'hard'
           )
           -- 快速完成：5分鐘內完成3局遊戲
           WHEN d.task_name = '快速完成' OR d.task_description LIKE '%5分鐘內完成3局遊戲%' THEN (
               SELECT COUNT(*) FROM game_records 
               WHERE member_id = mt.member_id 
               AND DATE(play_date) = CURDATE() 
               AND play_time <= 300
           )
           -- 效率專家：10分鐘內完成5局遊戲
           WHEN d.task_name = '效率專家' OR d.task_description LIKE '%10分鐘內完成5局遊戲%' THEN (
               SELECT COUNT(*) FROM game_records 
               WHERE member_id = mt.member_id 
               AND DATE(play_date) = CURDATE() 
               AND play_time <= 600
           )
           -- 其他任務：1次完成
           ELSE 1
       END as required
FROM member_tasks mt
JOIN daily_tasks d ON mt.task_id = d.task_id
WHERE mt.member_id = ? AND d.is_active = 1
AND d.task_name != '登入網站一次'
ORDER BY mt.task_id
LIMIT 3
";
$stmt = $pdo->prepare($tasks_sql);
$stmt->execute([$member_id]);
$tasks = $stmt->fetchAll();

// 如果沒有任務，返回空陣列
if (empty($tasks)) {
    echo json_encode([], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode($tasks, JSON_UNESCAPED_UNICODE);
}
?> 