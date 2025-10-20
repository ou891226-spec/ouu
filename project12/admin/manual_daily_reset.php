<?php
session_start();
require_once '../db.php';

// 檢查管理員權限
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$admin_name = $_SESSION['admin_name'] ?? '管理員';
$message = '';
$success = false;

// 處理手動重置請求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_reset'])) {
    try {
        // 執行每日重置
        include_once '../daily_reset.php';
        
        // 更新重置日期
        $today = date('Y-m-d');
        $update_sql = "
            INSERT INTO system_settings (setting_key, setting_value, last_reset_date) 
            VALUES ('daily_reset', 'completed', ?) 
            ON DUPLICATE KEY UPDATE 
            setting_value = 'completed', 
            last_reset_date = ?
        ";
        $update_stmt = $pdo->prepare($update_sql);
        $update_stmt->execute([$today, $today]);
        
        $success = true;
        $message = '✅ 每日重置執行成功！所有任務已更新。';
    } catch (Exception $e) {
        $message = '❌ 重置失敗：' . $e->getMessage();
    }
}

// 獲取上次重置時間
try {
    $check_sql = "SELECT last_reset_date, updated_at FROM system_settings WHERE setting_key = 'daily_reset'";
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->execute();
    $last_reset = $check_stmt->fetch();
} catch (Exception $e) {
    $last_reset = null;
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>手動重置每日任務 - 後台管理系統</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 0; 
            padding: 20px; 
            background: #f5f5f5; 
        }
        .container { 
            max-width: 800px; 
            margin: 0 auto; 
        }
        .header { 
            background: white; 
            padding: 20px; 
            margin-bottom: 20px; 
            border-radius: 5px; 
        }
        .nav { 
            background: white; 
            padding: 15px; 
            margin-bottom: 20px; 
            border-radius: 5px; 
        }
        .nav a { 
            margin-right: 20px; 
            text-decoration: none; 
            color: #007bff; 
        }
        .nav a:hover { 
            text-decoration: underline; 
        }
        .content { 
            background: white; 
            padding: 30px; 
            border-radius: 5px; 
        }
        .reset-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .reset-info h3 {
            margin-top: 0;
            color: #333;
        }
        .reset-button {
            background: #28a745;
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            font-weight: bold;
        }
        .reset-button:hover {
            background: #218838;
        }
        .message {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-weight: bold;
        }
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .warning-box {
            background: #fff3cd;
            border: 1px solid #ffc107;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #dee2e6;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            font-weight: bold;
            color: #666;
        }
        .info-value {
            color: #333;
        }
        .cron-box {
            background: #e7f3ff;
            border: 1px solid #b3d7ff;
            padding: 20px;
            border-radius: 5px;
            margin-top: 30px;
        }
        .cron-box h3 {
            margin-top: 0;
            color: #0056b3;
        }
        .cron-box code {
            background: #f8f9fa;
            padding: 10px;
            display: block;
            border-radius: 3px;
            margin: 10px 0;
            font-family: 'Courier New', monospace;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>手動重置每日任務</h1>
            <p>歡迎，<?php echo htmlspecialchars($admin_name); ?></p>
        </div>
        
        <div class="nav">
            <a href="index.php">📊 系統首頁</a>
            <a href="game_records.php">🎮 遊戲紀錄</a>
            <a href="user_management.php">👥 用戶管理</a>
            <a href="question_management.php">🎯 遊戲管理</a>
            <a href="logout.php">🚪 登出</a>
        </div>
        
        <div class="content">
            <?php if ($message): ?>
                <div class="message <?php echo $success ? 'success' : 'error'; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <div class="reset-info">
                <h3>📅 重置狀態資訊</h3>
                <?php if ($last_reset): ?>
                    <div class="info-row">
                        <span class="info-label">上次重置日期：</span>
                        <span class="info-value"><?php echo $last_reset['last_reset_date']; ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">上次執行時間：</span>
                        <span class="info-value"><?php echo $last_reset['updated_at']; ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">今天日期：</span>
                        <span class="info-value"><?php echo date('Y-m-d'); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">需要重置：</span>
                        <span class="info-value">
                            <?php echo ($last_reset['last_reset_date'] !== date('Y-m-d')) ? '✅ 是' : '❌ 否（今天已重置）'; ?>
                        </span>
                    </div>
                <?php else: ?>
                    <p style="color: #dc3545;">⚠️ 尚未進行過每日重置</p>
                <?php endif; ?>
            </div>
            
            <div class="warning-box">
                <strong>⚠️ 注意：</strong>
                <ul style="margin: 10px 0 0 0; padding-left: 20px;">
                    <li>執行重置會清空所有用戶的今日任務進度</li>
                    <li>會重新為每位用戶分配 3 個新的每日任務</li>
                    <li>建議在凌晨 12:00 或確認需要重置時才執行</li>
                </ul>
            </div>
            
            <form method="POST" onsubmit="return confirm('確定要執行每日重置嗎？\n\n這將會：\n1. 清空所有用戶的今日任務進度\n2. 重新分配新的每日任務\n3. 更新重置日期記錄');">
                <button type="submit" name="do_reset" value="1" class="reset-button">
                    🔄 立即執行每日重置
                </button>
            </form>
            
            <div class="cron-box">
                <h3>🕐 自動化設定（Windows 定時任務）</h3>
                <p>如果想要讓系統在每天凌晨 12:00 自動重置，請按照以下步驟設定：</p>
                
                <h4>方法 1：使用 Windows 工作排程器</h4>
                <ol>
                    <li>開啟「工作排程器」（Task Scheduler）</li>
                    <li>點選「建立基本工作」</li>
                    <li>設定名稱：「每日任務重置」</li>
                    <li>觸發程序：每天，00:00</li>
                    <li>動作：啟動程式</li>
                    <li>程式或指令碼：</li>
                </ol>
                <code>C:\xampp\php\php.exe</code>
                <p>引數：</p>
                <code>C:\xampp\htdocs\project12\check_daily_reset.php</code>
                
                <h4>方法 2：使用 PowerShell 定時任務</h4>
                <p>在 PowerShell（管理員）中執行：</p>
                <code>$action = New-ScheduledTaskAction -Execute "C:\xampp\php\php.exe" -Argument "C:\xampp\htdocs\project12\check_daily_reset.php"
$trigger = New-ScheduledTaskTrigger -Daily -At 00:00
Register-ScheduledTask -Action $action -Trigger $trigger -TaskName "DailyTaskReset" -Description "每日重置任務系統"</code>
            </div>
        </div>
    </div>
</body>
</html>

