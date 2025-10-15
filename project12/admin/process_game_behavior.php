<?php
session_start();
require_once '../db.php';
require_once '../log_game_behavior.php';

// 檢查管理員權限
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$admin_name = $_SESSION['admin_name'] ?? '管理員';

// 處理批量處理請求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_records'])) {
    try {
        $result = analyzeExistingGameRecords();
        
        if ($result) {
            $success_message = sprintf(
                "批量處理完成！<br>
                - 總共處理：%d 筆記錄<br>
                - 遊戲退出：%d 筆 (無後續動作)<br>
                - 遊戲完成：%d 筆 (有實際遊戲行為)<br>
                - 找到記錄：%d 筆",
                $result['total_processed'],
                $result['exit_records'], 
                $result['completions'],
                $result['total_records']
            );
        } else {
            $error_message = "批量處理失敗，請檢查系統日誌";
        }
        
    } catch (Exception $e) {
        $error_message = "處理失敗：" . $e->getMessage();
    }
}

// 獲取統計數據
try {
    $stats_sql = "
        SELECT 
            COUNT(*) as total_game_records,
            COUNT(CASE WHEN status = 'exited' THEN 1 END) as exit_records,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_records,
            COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_records,
            COUNT(CASE WHEN status = 'entered' THEN 1 END) as entered_records,
            COUNT(CASE WHEN play_time IS NULL THEN 1 END) as null_time_records
        FROM game_records
        WHERE play_time IS NOT NULL
    ";
    $stats_stmt = $pdo->prepare($stats_sql);
    $stats_stmt->execute();
    $game_stats = $stats_stmt->fetch();
    
    // 獲取行為軌跡統計
    $behavior_sql = "
        SELECT 
            COUNT(*) as total_behaviors,
            COUNT(CASE WHEN action_type = 'game_exit' THEN 1 END) as exit_behaviors,
            COUNT(CASE WHEN action_type = 'game_complete' THEN 1 END) as complete_behaviors
        FROM user_behavior_log
        WHERE action_type IN ('game_exit', 'game_complete')
    ";
    $behavior_stmt = $pdo->prepare($behavior_sql);
    $behavior_stmt->execute();
    $behavior_stats = $behavior_stmt->fetch();
    
    // 檢查未處理的記錄
    $unprocessed_sql = "
        SELECT COUNT(*) as unprocessed_count
        FROM game_records gr
        WHERE gr.play_time IS NOT NULL 
        AND gr.play_time > 0
        AND NOT EXISTS (
            SELECT 1 FROM user_behavior_log ubl 
            WHERE ubl.member_id = gr.member_id 
            AND ubl.game_type = gr.game_type
            AND DATE(ubl.created_at) = DATE(gr.play_date)
            AND ubl.action_type IN ('game_exit', 'game_complete')
        )
    ";
    $unprocessed_stmt = $pdo->prepare($unprocessed_sql);
    $unprocessed_stmt->execute();
    $unprocessed_stats = $unprocessed_stmt->fetch();
    
} catch (Exception $e) {
    $game_stats = ['total_game_records' => 0, 'exit_records' => 0, 'completed_records' => 0, 'failed_records' => 0, 'entered_records' => 0, 'null_time_records' => 0];
    $behavior_stats = ['total_behaviors' => 0, 'exit_behaviors' => 0, 'complete_behaviors' => 0];
    $unprocessed_stats = ['unprocessed_count' => 0];
}
?>

<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>處理遊戲行為軌跡 - 管理後台</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Microsoft JhengHei', Arial, sans-serif; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .header { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header h1 { color: #333; }
        .nav { background: white; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .nav a { text-decoration: none; color: #007bff; margin-right: 20px; padding: 8px 16px; border-radius: 4px; }
        .nav a:hover { background: #007bff; color: white; }
        .card { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 20px; }
        .stat-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px; text-align: center; }
        .stat-card.game { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .stat-card.behavior { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        .stat-card.pending { background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%); color: #333; }
        .stat-number { font-size: 28px; font-weight: bold; margin-bottom: 8px; }
        .stat-label { opacity: 0.9; font-size: 14px; }
        .btn { padding: 12px 24px; border: none; border-radius: 6px; cursor: pointer; text-decoration: none; display: inline-block; font-size: 16px; font-weight: 500; }
        .btn-primary { background: #007bff; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-warning { background: #ffc107; color: #333; }
        .btn:hover { opacity: 0.9; transform: translateY(-1px); }
        .alert { padding: 15px; border-radius: 6px; margin-bottom: 20px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .info-box { background: #e7f3ff; border: 1px solid #b3d9ff; padding: 15px; border-radius: 6px; margin: 15px 0; }
        .warning { background: #fff3cd; color: #856404; padding: 15px; border-radius: 6px; margin: 15px 0; border: 1px solid #ffeaa7; }
        .logout { float: right; background: #dc3545; color: white; padding: 8px 16px; text-decoration: none; border-radius: 5px; }
        .process-section { border: 2px solid #007bff; border-radius: 8px; padding: 20px; margin: 20px 0; }
        .breakdown { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 15px; }
        .breakdown-item { background: #f8f9fa; padding: 12px; border-radius: 4px; text-align: center; }
        .breakdown-number { font-size: 20px; font-weight: bold; color: #007bff; }
        .breakdown-label { font-size: 12px; color: #666; margin-top: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>處理遊戲行為軌跡</h1>
            <p>歡迎，<?php echo htmlspecialchars($admin_name); ?></p>
            <a href="logout.php" class="logout">登出</a>
        </div>
        
        <div class="nav">
            <a href="game_records.php">🎮 遊戲紀錄</a>
            <a href="user_behavior.php">👤 行為軌跡</a>
            <a href="question_management.php">🎯 遊戲管理</a>
            <a href="user_management.php">👥 用戶管理</a>
            <a href="ability_analysis.php">🧠 能力分析</a>
            <a href="ai_analysis_history.php">🤖 AI分析歷史</a>
            <a href="test_results.php">📈 測試結果</a>
            <a href="custom_score_trend.php">📊 趨勢分析</a>
            <a href="baseline_time_management.php">📊 雷達圖分析</a>
            <a href="delete_test_records.php" style="color: #dc3545;">🗑️ 刪除測試記錄</a>
            <a href="process_game_behavior.php" style="background: #007bff; color: white;">⚙️ 處理行為軌跡</a>
        </div>

        <?php if (isset($success_message)): ?>
        <div class="alert alert-success">
            <?php echo $success_message; ?>
        </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
        <div class="alert alert-error">
            <?php echo htmlspecialchars($error_message); ?>
        </div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card game">
                <div class="stat-number"><?php echo number_format($game_stats['total_game_records']); ?></div>
                <div class="stat-label">遊戲記錄總數</div>
                <div class="breakdown">
                    <div class="breakdown-item">
                        <div class="breakdown-number"><?php echo number_format($game_stats['exit_records']); ?></div>
                        <div class="breakdown-label">退出記錄</div>
                    </div>
                    <div class="breakdown-item">
                        <div class="breakdown-number"><?php echo number_format($game_stats['completed_records']); ?></div>
                        <div class="breakdown-label">完成記錄</div>
                    </div>
                </div>
            </div>
            
            <div class="stat-card behavior">
                <div class="stat-number"><?php echo number_format($behavior_stats['total_behaviors']); ?></div>
                <div class="stat-label">行為軌跡記錄</div>
                <div class="breakdown">
                    <div class="breakdown-item">
                        <div class="breakdown-number"><?php echo number_format($behavior_stats['exit_behaviors']); ?></div>
                        <div class="breakdown-label">遊戲退出</div>
                    </div>
                    <div class="breakdown-item">
                        <div class="breakdown-number"><?php echo number_format($behavior_stats['complete_behaviors']); ?></div>
                        <div class="breakdown-label">遊戲完成</div>
                    </div>
                </div>
            </div>
            
            <div class="stat-card pending">
                <div class="stat-number"><?php echo number_format($unprocessed_stats['unprocessed_count']); ?></div>
                <div class="stat-label">待處理記錄</div>
            </div>
        </div>

        <div class="info-box">
            <h3>📊 行為軌跡分析說明</h3>
            <p><strong>遊戲退出</strong>：用戶進入遊戲後沒有後續動作，或遊戲時間為0且分數為0</p>
            <p><strong>遊戲完成</strong>：用戶有實際遊戲行為且獲得分數</p>
            <p><strong>遊戲失敗</strong>：用戶有實際遊戲行為但沒有獲得分數</p>
            <p><strong>遊戲進入</strong>：用戶剛進入遊戲，等待後續動作</p>
            <p>這些數據用於分析用戶行為模式，了解遊戲的吸引力和用戶體驗問題。</p>
        </div>

        <?php if ($unprocessed_stats['unprocessed_count'] > 0): ?>
        <div class="process-section">
            <h2>🔄 批量處理歷史記錄</h2>
            <p>發現 <strong><?php echo number_format($unprocessed_stats['unprocessed_count']); ?></strong> 筆遊戲記錄尚未轉換為行為軌跡記錄。</p>
            
            <div class="warning">
                ⚠️ 此操作會分析現有的遊戲記錄，並為每筆記錄創建對應的行為軌跡記錄。
                <br>• 無後續動作的記錄 → 標記為「遊戲退出」
                <br>• 有實際遊戲行為且獲得分數 → 標記為「遊戲完成」
                <br>• 有實際遊戲行為但沒有獲得分數 → 標記為「遊戲失敗」
            </div>
            
            <form method="POST" onsubmit="return confirm('確定要處理這些記錄嗎？此操作可能需要一些時間。');">
                <button type="submit" name="process_records" class="btn btn-primary">
                    🚀 開始批量處理 (<?php echo number_format($unprocessed_stats['unprocessed_count']); ?> 筆)
                </button>
            </form>
        </div>
        <?php else: ?>
        <div class="card">
            <h2>✅ 處理完成</h2>
            <p>所有遊戲記錄都已經有對應的行為軌跡記錄了！</p>
            <a href="user_behavior.php" class="btn btn-success">查看行為軌跡分析</a>
        </div>
        <?php endif; ?>

        <div class="card">
            <h2>📈 下一步操作</h2>
            <p>處理完成後，你可以：</p>
            <ul style="margin: 15px 0; padding-left: 20px;">
                <li><a href="user_behavior.php">查看完整的行為軌跡分析</a></li>
                <li><a href="delete_test_records.php">清理測試記錄</a></li>
                <li><a href="game_records.php">查看遊戲記錄</a></li>
            </ul>
        </div>
    </div>
</body>
</html>
