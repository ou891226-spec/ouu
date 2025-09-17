<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['member_id'])) {
    echo json_encode(['success' => false, 'message' => '未登入']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['avatar'])) {
    $member_id = $_SESSION['member_id'];
    $file = $_FILES['avatar'];
    
    // 檢查檔案類型
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/tiff', 'image/tif', 'image/webp'];
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'tiff', 'tif', 'webp'];
    
    // 檢查檔案是否上傳成功
    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => '檔案上傳錯誤: ' . $file['error']]);
        exit;
    }
    
    // 檢查 MIME 類型和副檔名（更寬鬆的驗證）
    $is_valid_type = in_array($file['type'], $allowed_types) || in_array($file_extension, $allowed_extensions);
    
    // 額外檢查：使用 getimagesize 驗證是否為有效圖片
    $image_info = @getimagesize($file['tmp_name']);
    $is_valid_image = $image_info !== false;
    
    if (!$is_valid_type || !$is_valid_image) {
        echo json_encode([
            'success' => false, 
            'message' => '只允許上傳 JPG、PNG 或 GIF 格式的圖片',
            'debug' => [
                'file_type' => $file['type'],
                'file_extension' => $file_extension,
                'is_valid_type' => $is_valid_type,
                'is_valid_image' => $is_valid_image,
                'image_info' => $image_info
            ]
        ]);
        exit;
    }
    
    // 檢查檔案大小（限制為 5MB）
    if ($file['size'] > 5 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => '檔案大小不能超過 5MB']);
        exit;
    }
    
    // 創建上傳目錄
    $upload_dir = 'img/avatars/';
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            echo json_encode(['success' => false, 'message' => '無法創建上傳目錄']);
            exit;
        }
    }
    
    // 檢查目錄是否可寫入
    if (!is_writable($upload_dir)) {
        echo json_encode(['success' => false, 'message' => '上傳目錄不可寫入']);
        exit;
    }
    
    // 生成檔案名稱（統一使用 jpg 格式）
    $file_name = 'avatar_' . $member_id . '_' . time() . '.jpg';
    $file_path = $upload_dir . $file_name;
    
    // 處理圖片格式轉換
    $source_path = $file['tmp_name'];
    $target_path = $file_path;
    
    // 根據原始格式進行轉換
    $image_type = $image_info[2]; // IMAGETYPE_XXX 常數
    
    // 創建圖片資源
    switch ($image_type) {
        case IMAGETYPE_JPEG:
            $source_image = imagecreatefromjpeg($source_path);
            break;
        case IMAGETYPE_PNG:
            $source_image = imagecreatefrompng($source_path);
            break;
        case IMAGETYPE_GIF:
            $source_image = imagecreatefromgif($source_path);
            break;
        case IMAGETYPE_WEBP:
            $source_image = imagecreatefromwebp($source_path);
            break;
        case IMAGETYPE_TIFF_II:
        case IMAGETYPE_TIFF_MM:
            // 檢查是否有 TIFF 支援
            if (function_exists('imagecreatefromtiff')) {
                $source_image = imagecreatefromtiff($source_path);
            } elseif (extension_loaded('imagick')) {
                // 使用 ImageMagick 處理 TIFF
                try {
                    $imagick = new Imagick($source_path);
                    $imagick->setImageFormat('jpeg');
                    $imagick->writeImage($target_path);
                    $imagick->destroy();
                    
                    // 直接更新資料庫，跳過 GD 處理
                    $sql = "UPDATE member SET avatar = ? WHERE member_id = ?";
                    $stmt = $pdo->prepare($sql);
                    
                    if ($stmt->execute([$file_path, $member_id])) {
                        $_SESSION['avatar_url'] = $file_path;
                        echo json_encode(['success' => true, 'avatar_url' => $file_path]);
                    } else {
                        echo json_encode(['success' => false, 'message' => '更新資料庫失敗']);
                    }
                    exit;
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => 'TIFF 檔案處理失敗，請轉換為 JPG、PNG 或 GIF 後再上傳']);
                    exit;
                }
            } else {
                echo json_encode([
                    'success' => false, 
                    'message' => '不支援 TIFF 格式。請將圖片轉換為 JPG、PNG 或 GIF 格式後再上傳。',
                    'suggestion' => '您可以使用線上工具如 convertio.co 或 iloveimg.com 來轉換圖片格式。'
                ]);
                exit;
            }
            break;
        default:
            echo json_encode(['success' => false, 'message' => '不支援的圖片格式']);
            exit;
    }
    
    if (!$source_image) {
        echo json_encode(['success' => false, 'message' => '無法讀取圖片檔案']);
        exit;
    }
    
    // 獲取原始尺寸
    $width = imagesx($source_image);
    $height = imagesy($source_image);
    
    // 設定最大尺寸（例如 300x300）
    $max_size = 300;
    
    // 計算新尺寸
    if ($width > $height) {
        $new_width = $max_size;
        $new_height = floor($height * $max_size / $width);
    } else {
        $new_height = $max_size;
        $new_width = floor($width * $max_size / $height);
    }
    
    // 創建新圖片
    $new_image = imagecreatetruecolor($new_width, $new_height);
    
    // 保持透明度（如果是 PNG 或 GIF）
    if ($image_type == IMAGETYPE_PNG || $image_type == IMAGETYPE_GIF) {
        imagealphablending($new_image, false);
        imagesavealpha($new_image, true);
        $transparent = imagecolorallocatealpha($new_image, 255, 255, 255, 127);
        imagefilledrectangle($new_image, 0, 0, $new_width, $new_height, $transparent);
    }
    
    // 調整圖片大小
    imagecopyresampled($new_image, $source_image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
    
    // 儲存為 JPEG
    $quality = 90;
    if (imagejpeg($new_image, $target_path, $quality)) {
        // 清理記憶體
        imagedestroy($source_image);
        imagedestroy($new_image);
        
        // 更新資料庫中的頭像路徑
        $sql = "UPDATE member SET avatar = ? WHERE member_id = ?";
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute([$file_path, $member_id])) {
            // 更新 session 中的頭像路徑
            $_SESSION['avatar_url'] = $file_path;
            
            // 回傳成功狀態和新的頭像路徑
            echo json_encode(['success' => true, 'avatar_url' => $file_path]);
        } else {
            echo json_encode(['success' => false, 'message' => '更新資料庫失敗']);
        }
    } else {
        // 清理記憶體
        imagedestroy($source_image);
        imagedestroy($new_image);
        echo json_encode(['success' => false, 'message' => '檔案上傳失敗']);
    }
} else {
    echo json_encode(['success' => false, 'message' => '無效的請求']);
}
?>





