<?php
session_start();

// 清除後台管理員session
unset($_SESSION['admin_id']);
unset($_SESSION['admin_name']);
unset($_SESSION['admin_role']);

// 重定向到登入頁面
header('Location: login.php');
exit;
?> 