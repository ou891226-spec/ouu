<?php
/**
 * 清理過期遊戲邀請腳本
 * 可以在 Azure 環境中設定為定期執行（例如每小時執行一次）
 */

require_once 'db_connect.php';

try {
    // 更新過期的邀請狀態
    $stmt = $pdo->prepare("
        UPDATE game_invitations 
        SET status = 'expired' 
        WHERE status = 'pending' AND expires_at < NOW()
    ");
    $stmt->execute();
    $expiredCount = $stmt->rowCount();
    
    // 刪除超過24小時的已處理邀請（已接受、拒絕、取消、過期）
    $stmt = $pdo->prepare("
        DELETE FROM game_invitations 
        WHERE status IN ('accepted', 'rejected', 'cancelled', 'expired') 
        AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    $stmt->execute();
    $deletedCount = $stmt->rowCount();
    
    // 記錄清理結果
    $logMessage = date('Y-m-d H:i:s') . " - 清理完成: 過期邀請 {$expiredCount} 個, 刪除舊記錄 {$deletedCount} 個\n";
    file_put_contents('logs/cleanup_invitations.log', $logMessage, FILE_APPEND | LOCK_EX);
    
    echo json_encode([
        'success' => true,
        'message' => "清理完成",
        'expired_count' => $expiredCount,
        'deleted_count' => $deletedCount
    ]);
    
} catch (Exception $e) {
    $errorMessage = date('Y-m-d H:i:s') . " - 清理錯誤: " . $e->getMessage() . "\n";
    file_put_contents('logs/cleanup_invitations.log', $errorMessage, FILE_APPEND | LOCK_EX);
    
    echo json_encode([
        'success' => false,
        'message' => '清理失敗: ' . $e->getMessage()
    ]);
}
?> 