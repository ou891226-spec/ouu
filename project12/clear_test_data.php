<?php
session_start();
require_once 'db_connect.php';

// 檢查是否為管理員（可選的安全檢查）
$is_admin = isset($_SESSION['admin_id']) || isset($_GET['admin_key']) && $_GET['admin_key'] === 'clear2024';

if (!$is_admin) {
    echo "<h2>⚠️ 安全警告</h2>";
    echo "<p>此工具會清空所有測試資料，請確認您有權限執行此操作。</p>";
    echo "<p>如需執行，請在URL後加上：<code>?admin_key=clear2024</code></p>";
    echo "<p>例如：<code>clear_test_data.php?admin_key=clear2024</code></p>";
    exit;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // 要清空的表列表
        $tables_to_clear = [
            'game_records' => '遊戲紀錄',
            'member_achievements' => '會員成就',
            'member_tasks' => '會員任務',
            'daily_playtime_records' => '每日遊玩時間',
            'game_high_scores' => '遊戲最高分',
            'game_invitations' => '遊戲邀請',
            'user_behavior_log' => '用戶行為記錄',
            'friend_requests' => '好友請求',
            'two_player_game_records' => '雙人遊戲紀錄'
        ];
        
        $cleared_tables = [];
        
        // 檢查是否要清空所有表
        if (isset($_POST['clear_all'])) {
            foreach ($tables_to_clear as $table => $description) {
                // 檢查表是否存在
                $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
                if ($stmt->rowCount() > 0) {
                    // 獲取清空前的記錄數
                    $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
                    $before_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                    
                    // 清空表
                    $pdo->exec("TRUNCATE TABLE $table");
                    
                    // 獲取清空後的記錄數
                    $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
                    $after_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                    
                    $cleared_tables[] = [
                        'table' => $table,
                        'description' => $description,
                        'before' => $before_count,
                        'after' => $after_count
                    ];
                }
            }
            $message = "✅ 所有資料清空完成！";
        } else {
            // 清空單個表
            foreach ($_POST as $key => $value) {
                if (strpos($key, 'clear_') === 0) {
                    $table = substr($key, 6); // 移除 'clear_' 前綴
                    if (isset($tables_to_clear[$table])) {
                        $description = $tables_to_clear[$table];
                        
                        // 檢查表是否存在
                        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
                        if ($stmt->rowCount() > 0) {
                            // 獲取清空前的記錄數
                            $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
                            $before_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                            
                            // 清空表
                            $pdo->exec("TRUNCATE TABLE $table");
                            
                            // 獲取清空後的記錄數
                            $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
                            $after_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                            
                            $cleared_tables[] = [
                                'table' => $table,
                                'description' => $description,
                                'before' => $before_count,
                                'after' => $after_count
                            ];
                        }
                    }
                }
            }
            if (!empty($cleared_tables)) {
                $message = "✅ 選定的資料表清空完成！";
            }
        }
        
    } catch (Exception $e) {
        $error = "❌ 清空失敗：" . $e->getMessage();
    }
}

// 獲取當前各表的記錄數
$current_counts = [];
$tables_to_check = [
    'game_records' => '遊戲紀錄',
    'member_achievements' => '會員成就',
    'member_tasks' => '會員任務',
    'daily_playtime_records' => '每日遊玩時間',
    'game_high_scores' => '遊戲最高分',
    'game_invitations' => '遊戲邀請',
    'user_behavior_log' => '用戶行為記錄',
    'friend_requests' => '好友請求',
    'two_player_game_records' => '雙人遊戲紀錄'
];

foreach ($tables_to_check as $table => $description) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        $current_counts[$table] = [
            'description' => $description,
            'count' => $count
        ];
    } catch (Exception $e) {
        $current_counts[$table] = [
            'description' => $description,
            'count' => '表不存在'
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>清空測試資料工具</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            text-align: center;
            margin-bottom: 30px;
        }
        .warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        .count-high {
            color: #dc3545;
            font-weight: bold;
        }
        .count-zero {
            color: #28a745;
            font-weight: bold;
        }
        .clear-button {
            background: #dc3545;
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin: 20px 0;
        }
        .clear-button:hover {
            background: #c82333;
        }
        .clear-button:disabled {
            background: #6c757d;
            cursor: not-allowed;
        }
        .summary {
            background: #e9ecef;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .links {
            margin-top: 30px;
            text-align: center;
        }
        .links a {
            display: inline-block;
            margin: 0 10px;
            padding: 10px 20px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }
        .links a:hover {
            background: #0056b3;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧹 清空測試資料工具</h1>
        
        <?php if ($message): ?>
            <div class="success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="warning">
            <strong>⚠️ 重要提醒：</strong>
            <ul>
                <li>此工具會清空所有測試資料，操作不可逆轉</li>
                <li>請確認您要清空的是測試環境的資料</li>
                <li>清空後所有遊戲紀錄、任務進度、成就等都會消失</li>
            </ul>
        </div>
        
        <h2>📊 當前資料狀況</h2>
        <form method="POST">
        <table>
                    <thead>
            <tr>
                <th>資料表</th>
                <th>描述</th>
                <th>記錄數</th>
                <th>操作</th>
            </tr>
        </thead>
                    <tbody>
            <?php foreach ($current_counts as $table => $info): ?>
            <tr>
                <td><code><?php echo $table; ?></code></td>
                <td><?php echo $info['description']; ?></td>
                <td class="<?php echo $info['count'] > 0 ? 'count-high' : 'count-zero'; ?>">
                    <?php echo $info['count']; ?>
                </td>
                <td>
                    <?php if ($info['count'] > 0): ?>
                    <button type="submit" name="clear_<?php echo $table; ?>" value="1" 
                            style="background: #dc3545; color: white; border: none; padding: 5px 10px; border-radius: 3px; cursor: pointer; font-size: 12px;"
                            onclick="return confirm('確定要清空 <?php echo $info['description']; ?> 嗎？')">
                        🗑️ 清空
                    </button>
                    <?php else: ?>
                    <span style="color: #6c757d; font-size: 12px;">已清空</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        </table>
       </form>
        
        <?php if (isset($cleared_tables) && !empty($cleared_tables)): ?>
        <h2>✅ 清空結果</h2>
        <table>
            <thead>
                <tr>
                    <th>資料表</th>
                    <th>描述</th>
                    <th>清空前</th>
                    <th>清空後</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cleared_tables as $table): ?>
                <tr>
                    <td><code><?php echo $table['table']; ?></code></td>
                    <td><?php echo $table['description']; ?></td>
                    <td><?php echo $table['before']; ?></td>
                    <td class="count-zero"><?php echo $table['after']; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
        
        <?php
        $total_records = array_sum(array_map(function($info) {
            return is_numeric($info['count']) ? $info['count'] : 0;
        }, $current_counts));
        ?>
        
        <div class="summary">
            <strong>總計：</strong> <?php echo $total_records; ?> 筆記錄
            <?php if ($total_records > 0): ?>
                <br><span class="count-high">⚠️ 發現測試資料，建議清空</span>
            <?php else: ?>
                <br><span class="count-zero">✅ 資料庫已清空</span>
            <?php endif; ?>
        </div>
        
        <?php if ($total_records > 0): ?>
        <form method="POST" onsubmit="return confirm('確定要清空所有測試資料嗎？此操作不可逆轉！');">
            <button type="submit" name="clear_all" class="clear-button">
                🗑️ 清空所有測試資料
            </button>
        </form>
        <?php endif; ?>
        
        <div class="links">
            <a href="admin/index.php">後台首頁</a>
            <a href="admin/game_records.php">遊戲紀錄</a>
            <a href="admin/user_behavior.php">行為軌跡</a>
            <a href="index.php">前台首頁</a>
        </div>
    </div>
    
    <script>
        // 自動刷新頁面以顯示最新數據
        if (window.location.search.includes('refresh')) {
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        }
    </script>
</body>
</html>
