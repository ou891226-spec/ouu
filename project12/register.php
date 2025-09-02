<?php
require_once "DB_open.php";
require_once "avatar_helper.php";

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST["name"];
    $id = $_POST["id"];
    $password = $_POST["password"];
    $confirm_password = $_POST["confirm_password"];
    if (empty($name) || empty($id) || empty($password) || empty($confirm_password)) {
        header("Location: registerForm.php?error=帳號欄位空白");
        exit();
    }
    if ($password != $confirm_password) {
        header("Location: registerForm.php?error=密碼輸入不相同");
        exit();
    }
    $check_query = "SELECT * FROM `member` WHERE account = ?";
    $check_stmt = $pdo->prepare($check_query);
    $check_stmt->execute([$id]);
    if ($check_stmt->fetch(PDO::FETCH_ASSOC)) {
        header("Location: registerForm.php?error=此帳號已存在，請重新選擇帳號");
        exit();
    }
    $query = "INSERT INTO `member` (member_name, account, password) VALUES (?, ?, ?)";
    $stmt = $pdo->prepare($query);
    if ($stmt->execute([$name, $id, $password])) {
        $new_member_id = $pdo->lastInsertId();
        
        // 產生預設頭像
        $avatar_path = generateDefaultAvatar($new_member_id, $name);
        if ($avatar_path) {
            // 強制更新資料庫的 avatar 欄位
            $update_sql = "UPDATE member SET avatar = ? WHERE member_id = ?";
            $update_stmt = $pdo->prepare($update_sql);
            if ($update_stmt->execute([$avatar_path, $new_member_id])) {
                error_log("[register.php] 頭像已成功更新到資料庫: $avatar_path");
            } else {
                error_log("[register.php] 頭像資料庫更新失敗");
            }
        } else {
            error_log("[register.php] 頭像產生失敗");
        }
        
        // 為新用戶分配每日任務
        try {
            // 獲取3個隨機的活躍任務
            $tasks_sql = "SELECT task_id FROM daily_tasks WHERE is_active = 1 ORDER BY RAND() LIMIT 3";
            $tasks_stmt = $pdo->query($tasks_sql);
            $available_tasks = $tasks_stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (!empty($available_tasks)) {
                // 為新用戶分配任務
                $assign_sql = "INSERT INTO member_tasks (member_id, task_id, status, completed_date, claimed_date) VALUES (?, ?, 'pending', NULL, NULL)";
                $assign_stmt = $pdo->prepare($assign_sql);
                
                foreach ($available_tasks as $task_id) {
                    $assign_stmt->execute([$new_member_id, $task_id]);
                }
                
                error_log("[register.php] 已為新用戶 $new_member_id 分配 " . count($available_tasks) . " 個任務");
            } else {
                error_log("[register.php] 沒有可用的任務可分配");
            }
        } catch (Exception $e) {
            error_log("[register.php] 分配任務失敗: " . $e->getMessage());
        }
        echo "姓名: " . htmlspecialchars($name) . "<br>";
        echo "帳號: " . htmlspecialchars($id) . "<br>";
        echo "密碼: " . htmlspecialchars($password) . "<br>"; 
        echo "註冊成功！<br><br>";
        echo "<a href='login.php' style='display: inline-block; padding: 10px 20px; background-color: #4CAF50; color: white; text-decoration: none; border-radius: 5px;'>返回登入頁面</a>";
    } else {
        header("Location: registerForm.php?error=註冊失敗，請稍後再試");
    }
} else {
    header("Location: registerForm.php"); 
    exit();
}
?>
