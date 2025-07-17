<?php
$filename = __DIR__ . '/myproject.sql';

if (file_exists($filename)) {
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="myproject.sql"');
    header('Content-Length: ' . filesize($filename));
    readfile($filename);
    exit;
} else {
    echo "檔案不存在：$filename";
}
?>
