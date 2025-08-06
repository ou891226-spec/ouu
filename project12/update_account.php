<?php
require_once 'check_login.php';
require_once 'db.php';

$member_id = $_SESSION['member_id'];
$new_name = isset($_POST['name']) ? trim($_POST['name']) : '';
$new_password = isset($_POST['password']) ? trim($_POST['password']) : '';

if (!$member_id || !$new_name) {
    echo "<script>alert('資料不完整'); window.history.back();</script>";
    exit;
}

try {
    // 檢查名字是否重複（排除自己）
    $sql_check = "SELECT member_id FROM member WHERE name = ? AND member_id != ?";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute([$new_name, $member_id]);
    if ($stmt_check->fetch()) {
        echo "<script>alert('此名字已被使用，請換一個'); window.history.back();</script>";
        exit;
    }

    // 根據是否有輸入新密碼來決定更新方式
    if (!empty($new_password)) {
        // 更新名字與密碼
        $sql = "UPDATE member SET name = ?, password = ? WHERE member_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$new_name, $new_password, $member_id]);
    } else {
        // 只更新名字
        $sql = "UPDATE member SET name = ? WHERE member_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$new_name, $member_id]);
    }

    if ($stmt->rowCount() > 0) {
        $_SESSION['name'] = $new_name;
        echo "<script>alert('更新成功'); window.location.href='index.php';</script>";
    } else {
        echo "<script>alert('更新失敗或資料未變更'); window.location.href='index.php';</script>";
    }
} catch (Exception $e) {
    echo "<script>alert('更新失敗：資料庫錯誤'); window.history.back();</script>";
    error_log("update_account.php 錯誤: " . $e->getMessage());
}
?> 