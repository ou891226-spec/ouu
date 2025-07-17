<?php
require_once "DB_open.php";

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Function to generate a default avatar with the first character of the name
function generateDefaultAvatar($member_id, $member_name) {
    if (!function_exists('imagecreatetruecolor') || !function_exists('imagettftext')) {
        error_log("[register.php] GD 或 FreeType 未安裝");
        return null;
    }
    $size = 100;
    $image = @imagecreatetruecolor($size, $size);
    if (!$image) {
        error_log("[register.php] imagecreatetruecolor 失敗");
        return null;
    }
    imagesavealpha($image, true);
    $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
    imagefill($image, 0, 0, $transparent);
    $bg_color = imagecolorallocate($image, 3, 155, 229);
    $center_x = $size / 2;
    $center_y = $size / 2;
    $radius = $size / 2;
    imagefilledellipse($image, $center_x, $center_y, $radius * 2, $radius * 2, $bg_color);
    $text_color = imagecolorallocate($image, 255, 255, 255);
    $font_path = __DIR__ . '/fonts/msjhbd.ttc';
    $font_size = $size / 2.5;
    $first_char = mb_substr($member_name, 0, 1, 'UTF-8');
    if (!file_exists($font_path)) {
        error_log("[register.php] 字型檔案不存在: $font_path");
        imagedestroy($image);
        return null;
    }
    $textbox = @imagettfbbox($font_size, 0, $font_path, $first_char);
    if (!$textbox) {
        error_log("[register.php] imagettfbbox 失敗");
        imagedestroy($image);
        return null;
    }
    $text_width = $textbox[2] - $textbox[0];
    $text_height = $textbox[1] - $textbox[7];
    $x = $center_x - $text_width / 2 - $textbox[0];
    $y = $center_y - $text_height / 2 - $textbox[7];
    if (@imagettftext($image, $font_size, 0, $x, $y, $text_color, $font_path, $first_char) === false) {
        error_log("[register.php] imagettftext 失敗");
        imagedestroy($image);
        return null;
    }
    $upload_dir = __DIR__ . '/img/avatars/';
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            error_log("[register.php] 無法建立資料夾: $upload_dir");
            imagedestroy($image);
            return null;
        }
    }
    if (!is_writable($upload_dir)) {
        error_log("[register.php] 資料夾不可寫入: $upload_dir");
        imagedestroy($image);
        return null;
    }
    $file_name = 'avatar_' . $member_id . '.png';
    $file_path = $upload_dir . $file_name;
    if (!@imagepng($image, $file_path)) {
        error_log("[register.php] 無法寫入圖片: $file_path");
        imagedestroy($image);
        return null;
    }
    imagedestroy($image);
    // 回傳相對路徑
    return 'img/avatars/' . $file_name;
}

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
        // 產生預設頭像（加強容錯）
        $avatar_path = generateDefaultAvatar($new_member_id, $name);
        if ($avatar_path) {
            $update_avatar_sql = "UPDATE `member` SET `avatar` = ? WHERE `member_id` = ?";
            $update_avatar_stmt = $pdo->prepare($update_avatar_sql);
            $update_avatar_stmt->execute([$avatar_path, $new_member_id]);
        } else {
            error_log("[register.php] 頭像產生失敗，未更新 avatar 欄位");
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
