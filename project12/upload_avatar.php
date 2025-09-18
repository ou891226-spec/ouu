<?php
// 設定 PHP 上傳限制
ini_set('upload_max_filesize', '20M');
ini_set('post_max_size', '20M');
ini_set('max_execution_time', 120);
ini_set('memory_limit', '512M');
ini_set('max_input_time', 120);
ini_set('display_errors', 1);

// 設定 JSON 回應標頭
header('Content-Type: application/json');

session_start();

// 檢查是否登入
if (!isset($_SESSION['member_id'])) {
    echo json_encode(['success' => false, 'message' => '未登入']);
    exit;
}

// 檢查是否為 POST 請求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '只接受 POST 請求']);
    exit;
}

// 檢查是否有檔案上傳
if (!isset($_FILES['avatar'])) {
    echo json_encode(['success' => false, 'message' => '沒有檔案上傳']);
    exit;
}

$member_id = $_SESSION['member_id'];
$file = $_FILES['avatar'];

// 檢查檔案上傳錯誤
if ($file['error'] !== UPLOAD_ERR_OK) {
    $error_messages = [
        UPLOAD_ERR_INI_SIZE => '檔案大小超過伺服器限制',
        UPLOAD_ERR_FORM_SIZE => '檔案大小超過表單限制',
        UPLOAD_ERR_PARTIAL => '檔案只上傳了一部分',
        UPLOAD_ERR_NO_FILE => '沒有選擇檔案',
        UPLOAD_ERR_NO_TMP_DIR => '缺少臨時資料夾',
        UPLOAD_ERR_CANT_WRITE => '檔案寫入失敗',
        UPLOAD_ERR_EXTENSION => '檔案上傳被擴展功能阻止'
    ];
    
    $error_message = isset($error_messages[$file['error']]) ? 
        $error_messages[$file['error']] : '檔案上傳錯誤: ' . $file['error'];
        
    echo json_encode(['success' => false, 'message' => $error_message]);
    exit;
}

// 檢查檔案大小
if ($file['size'] > 20 * 1024 * 1024) {
    $file_size_mb = round($file['size'] / (1024 * 1024), 2);
    echo json_encode([
        'success' => false, 
        'message' => '⚠️ 檔案太大！',
        'suggestion' => "您的檔案大小為 {$file_size_mb}MB，請選擇小於 20MB 的圖片。",
        'max_size' => '20MB',
        'current_size' => $file_size_mb . 'MB'
    ]);
    exit;
}

if ($file['size'] == 0) {
    echo json_encode([
        'success' => false, 
        'message' => '⚠️ 檔案讀取失敗！',
        'suggestion' => '請重新選擇圖片檔案，或嘗試使用其他圖片。',
        'tip' => '如果問題持續發生，請嘗試重新拍照或選擇其他圖片。'
    ]);
    exit;
}

// 檢查檔案類型
$allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
$file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];

$is_valid_type = in_array($file['type'], $allowed_types) || in_array($file_extension, $allowed_extensions);

if (!$is_valid_type) {
    echo json_encode([
        'success' => false, 
        'message' => '⚠️ 檔案格式不支援！',
        'suggestion' => '請選擇 JPG、PNG 或 GIF 格式的圖片檔案。',
        'allowed_formats' => 'JPG、PNG、GIF'
    ]);
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

// 生成檔案名稱
$file_name = 'avatar_' . $member_id . '_' . time() . '.jpg';
$file_path = $upload_dir . $file_name;

// 移動檔案
if (move_uploaded_file($file['tmp_name'], $file_path)) {
    // 更新資料庫
    try {
        require_once 'db.php';
        $sql = "UPDATE member SET avatar = ? WHERE member_id = ?";
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute([$file_path, $member_id])) {
            $_SESSION['avatar_url'] = $file_path;
            echo json_encode(['success' => true, 'avatar_url' => $file_path]);
        } else {
            echo json_encode(['success' => false, 'message' => '更新資料庫失敗']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => '資料庫錯誤: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => '檔案移動失敗']);
}
?>