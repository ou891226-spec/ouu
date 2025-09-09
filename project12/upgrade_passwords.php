<?php
/**
 * 密碼升級腳本
 * 將現有的明文密碼升級為加鹽雜湊密碼
 * 
 * 注意：此腳本只需要執行一次！
 */

require_once 'db_connect.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h2>密碼升級腳本</h2>";
echo "<p>正在升級現有的明文密碼為安全的加鹽雜湊密碼...</p>";

try {
    // 查找所有需要升級的密碼（長度 <= 20 字元的通常是明文密碼）
    $sql = "SELECT member_id, account, password FROM member WHERE LENGTH(password) <= 20";
    $stmt = $pdo->query($sql);
    $users_to_upgrade = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $total_users = count($users_to_upgrade);
    echo "<p>找到 {$total_users} 個需要升級的帳戶</p>";
    
    if ($total_users == 0) {
        echo "<p style='color: green;'>✅ 所有密碼都已經是加鹽雜湊格式，無需升級！</p>";
        exit();
    }
    
    $upgraded_count = 0;
    $failed_count = 0;
    
    // 開始事務
    $pdo->beginTransaction();
    
    foreach ($users_to_upgrade as $user) {
        try {
            // 將明文密碼轉換為加鹽雜湊
            $hashed_password = password_hash($user['password'], PASSWORD_DEFAULT);
            
            // 更新資料庫
            $update_sql = "UPDATE member SET password = ? WHERE member_id = ?";
            $update_stmt = $pdo->prepare($update_sql);
            
            if ($update_stmt->execute([$hashed_password, $user['member_id']])) {
                $upgraded_count++;
                echo "<p>✅ 帳戶 '{$user['account']}' 密碼升級成功</p>";
            } else {
                $failed_count++;
                echo "<p style='color: red;'>❌ 帳戶 '{$user['account']}' 密碼升級失敗</p>";
            }
            
        } catch (Exception $e) {
            $failed_count++;
            echo "<p style='color: red;'>❌ 帳戶 '{$user['account']}' 升級時發生錯誤: " . $e->getMessage() . "</p>";
        }
    }
    
    if ($failed_count == 0) {
        // 全部成功，提交事務
        $pdo->commit();
        echo "<h3 style='color: green;'>🎉 密碼升級完成！</h3>";
        echo "<p>✅ 成功升級 {$upgraded_count} 個帳戶的密碼</p>";
        echo "<p>✅ 所有密碼現在都使用安全的加鹽雜湊處理</p>";
        
        // 建議刪除此腳本
        echo "<div style='background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; margin: 20px 0; border-radius: 8px;'>";
        echo "<h4>⚠️ 重要提醒</h4>";
        echo "<p>密碼升級已完成，為了安全起見，請立即刪除此升級腳本文件：</p>";
        echo "<code>upgrade_passwords.php</code>";
        echo "</div>";
        
    } else {
        // 有失敗，回滾事務
        $pdo->rollback();
        echo "<h3 style='color: red;'>❌ 升級過程中發生錯誤</h3>";
        echo "<p>成功: {$upgraded_count} 個，失敗: {$failed_count} 個</p>";
        echo "<p>所有變更已回滾，請檢查錯誤訊息後重新執行</p>";
    }
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollback();
    }
    echo "<p style='color: red;'>❌ 升級過程中發生嚴重錯誤: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<h3>密碼安全說明</h3>";
echo "<ul>";
echo "<li>✅ 使用 PHP 的 <code>password_hash()</code> 函數</li>";
echo "<li>✅ 自動生成隨機鹽值</li>";
echo "<li>✅ 使用 bcrypt 演算法（預設）</li>";
echo "<li>✅ 每個密碼都有唯一的鹽值</li>";
echo "<li>✅ 登入時使用 <code>password_verify()</code> 驗證</li>";
echo "</ul>";

echo "<h3>升級後的系統特性</h3>";
echo "<ul>";
echo "<li>🔒 新註冊的帳戶自動使用加鹽密碼</li>";
echo "<li>🔒 修改密碼時自動使用加鹽密碼</li>";
echo "<li>🔒 忘記密碼重設時自動使用加鹽密碼</li>";
echo "<li>🔄 支援新舊密碼格式混合驗證</li>";
echo "</ul>";
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>密碼升級腳本</title>
    <style>
        body {
            font-family: 'Microsoft JhengHei', Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            line-height: 1.6;
        }
        h2 { color: #333; }
        h3 { color: #666; }
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
        }
        ul {
            background: #f9f9f9;
            padding: 15px 30px;
            border-left: 4px solid #4CAF50;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div style="text-align: center; margin-top: 30px;">
        <a href="index.php" style="background: #4CAF50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">
            返回首頁
        </a>
    </div>
</body>
</html>
