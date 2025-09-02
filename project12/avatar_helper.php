<?php
// 頭像輔助函數

/**
 * 檢查頭像是否存在，如果不存在則生成預設頭像
 */
function getAvatarPath($member_id, $member_name, $custom_avatar = null) {
    // 預設頭像路徑
    $default_avatar_path = 'img/avatars/avatar_' . $member_id . '.png';
    $full_path = __DIR__ . '/' . $default_avatar_path;
    
    // 如果預設頭像不存在，生成它
    if (!file_exists($full_path)) {
        $generated_path = generateDefaultAvatar($member_id, $member_name);
        if ($generated_path) {
            return $generated_path;
        }
    }
    
    // 如果有自訂頭像且檔案存在，使用自訂頭像
    if ($custom_avatar && file_exists(__DIR__ . '/' . $custom_avatar)) {
        return $custom_avatar;
    }
    
    return $default_avatar_path;
}

/**
 * 生成預設頭像
 */
function generateDefaultAvatar($member_id, $member_name) {
    if (!function_exists('imagecreatetruecolor') || !function_exists('imagettftext')) {
        error_log("[avatar_helper.php] GD 或 FreeType 未安裝");
        return null;
    }
    
    $size = 100;
    $image = @imagecreatetruecolor($size, $size);
    if (!$image) {
        error_log("[avatar_helper.php] imagecreatetruecolor 失敗");
        return null;
    }
    
    imagesavealpha($image, true);
    $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
    imagefill($image, 0, 0, $transparent);
    
    // 使用藍色背景
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
        error_log("[avatar_helper.php] 字型檔案不存在: $font_path");
        imagedestroy($image);
        return null;
    }
    
    $textbox = @imagettfbbox($font_size, 0, $font_path, $first_char);
    if (!$textbox) {
        error_log("[avatar_helper.php] imagettfbbox 失敗");
        imagedestroy($image);
        return null;
    }
    
    $text_width = $textbox[2] - $textbox[0];
    $text_height = $textbox[1] - $textbox[7];
    $x = $center_x - $text_width / 2 - $textbox[0];
    $y = $center_y - $text_height / 2 - $textbox[7];
    
    if (@imagettftext($image, $font_size, 0, $x, $y, $text_color, $font_path, $first_char) === false) {
        error_log("[avatar_helper.php] imagettftext 失敗");
        imagedestroy($image);
        return null;
    }
    
    $upload_dir = __DIR__ . '/img/avatars/';
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            error_log("[avatar_helper.php] 無法建立資料夾: $upload_dir");
            imagedestroy($image);
            return null;
        }
    }
    
    if (!is_writable($upload_dir)) {
        error_log("[avatar_helper.php] 資料夾不可寫入: $upload_dir");
        imagedestroy($image);
        return null;
    }
    
    $file_name = 'avatar_' . $member_id . '.png';
    $file_path = $upload_dir . $file_name;
    
    if (!@imagepng($image, $file_path)) {
        error_log("[avatar_helper.php] 無法寫入圖片: $file_path");
        imagedestroy($image);
        return null;
    }
    
    imagedestroy($image);
    
    // 更新資料庫中的頭像路徑
    try {
        require_once 'db.php';
        $update_sql = "UPDATE `member` SET `avatar` = ? WHERE `member_id` = ?";
        $update_stmt = $pdo->prepare($update_sql);
        $update_stmt->execute(['img/avatars/' . $file_name, $member_id]);
    } catch (Exception $e) {
        error_log("[avatar_helper.php] 更新資料庫頭像路徑失敗: " . $e->getMessage());
    }
    
    return 'img/avatars/' . $file_name;
}
?>
