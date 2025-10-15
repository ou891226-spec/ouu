<?php
// 設定 PHP 執行時間和記憶體限制，避免 502 錯誤
ini_set('max_execution_time', 30); // 30 秒執行時間限制
ini_set('memory_limit', '128M'); // 128MB 記憶體限制
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once "DB_open.php";
require_once __DIR__ . "/avatar_helper.php";

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
    // 檢查帳號是否已存在
    try {
        $check_query = "SELECT * FROM `member` WHERE account = ?";
        $check_stmt = $pdo->prepare($check_query);
        $check_stmt->execute([$id]);
        if ($check_stmt->fetch(PDO::FETCH_ASSOC)) {
            header("Location: registerForm.php?error=" . urlencode("此帳號已註冊過，請重新選擇帳號"));
            exit();
        }
    } catch (Exception $e) {
        error_log("檢查帳號重複時發生錯誤: " . $e->getMessage());
        header("Location: registerForm.php?error=系統錯誤，請稍後再試");
        exit();
    }
    // 使用 password_hash() 進行安全的密碼加鹽處理
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // 插入新用戶資料
    try {
        $query = "INSERT INTO `member` (member_name, account, password) VALUES (?, ?, ?)";
        $stmt = $pdo->prepare($query);
        if ($stmt->execute([$name, $id, $hashed_password])) {
        $new_member_id = $pdo->lastInsertId();
        
        // 產生預設頭像（非阻塞操作）
        try {
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
        } catch (Exception $e) {
            error_log("[register.php] 頭像產生過程發生錯誤: " . $e->getMessage());
            // 頭像產生失敗不影響註冊流程
        }
        
        // 為新用戶分配每日任務
        try {
            // 新用戶分配完整的每日任務（3個）
            $tasks_sql = "
                SELECT task_id FROM daily_tasks 
                WHERE is_active = 1 
                AND task_name != '遊戲新手' 
                AND task_name NOT LIKE '%忠實玩家%'
                ORDER BY RAND() 
                LIMIT 3
            ";
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
            // 重定向到美觀的註冊成功頁面
            $success_url = "register_success.php?name=" . urlencode($name) . "&account=" . urlencode($id);
            header("Location: $success_url");
            exit();
        } else {
            header("Location: registerForm.php?error=註冊失敗，請稍後再試");
            exit();
        }
    } catch (Exception $e) {
        error_log("註冊用戶時發生錯誤: " . $e->getMessage());
        header("Location: registerForm.php?error=系統錯誤，請稍後再試");
        exit();
    }
} else {
    header("Location: registerForm.php"); 
    exit();
}
?>
