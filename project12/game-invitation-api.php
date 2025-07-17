<?php
require_once 'check_login.php';
require_once 'db_connect.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// 處理 OPTIONS 請求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// 檢查是否已登入
if (!isset($_SESSION['member_id'])) {
    echo json_encode(['success' => false, 'message' => '請先登入']);
    exit();
}

// 獲取 POST 資料
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

try {
    switch ($action) {
        case 'send_invitation':
            handleSendInvitation($pdo, $input);
            break;
            
        case 'check_invitation':
            handleCheckInvitation($pdo, $input);
            break;
            
        case 'accept_invitation':
            handleAcceptInvitation($pdo, $input);
            break;
            
        case 'reject_invitation':
            handleRejectInvitation($pdo, $input);
            break;
            
        case 'cancel_invitation':
            handleCancelInvitation($pdo, $input);
            break;
            
        case 'get_pending_invitations':
            handleGetPendingInvitations($pdo);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => '無效的操作']);
    }
} catch (Exception $e) {
    error_log("遊戲邀請 API 錯誤: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => '伺服器錯誤，請稍後再試']);
}

function handleSendInvitation($pdo, $input) {
    $fromUserId = $_SESSION['member_id'];
    $toUserId = $input['to_user_id'] ?? 0;
    $gameType = $input['game_type'] ?? 'memory_game_2p';
    
    if (!$toUserId) {
        echo json_encode(['success' => false, 'message' => '缺少接收者ID']);
        return;
    }
    
    // 檢查是否已經是好友
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM friends WHERE member_id = ? AND friend_id = ?");
    $stmt->execute([$fromUserId, $toUserId]);
    if (!$stmt->fetchColumn()) {
        echo json_encode(['success' => false, 'message' => '只能邀請好友進行遊戲']);
        return;
    }
    
    // 檢查是否已有待處理的邀請
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM game_invitations 
                          WHERE from_user_id = ? AND to_user_id = ? AND status = 'pending'");
    $stmt->execute([$fromUserId, $toUserId]);
    if ($stmt->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => '已有待處理的邀請']);
        return;
    }
    
    // 生成邀請ID
    $invitationId = 'invite_' . time() . '_' . bin2hex(random_bytes(8));
    
    // 插入邀請記錄
    $stmt = $pdo->prepare("INSERT INTO game_invitations 
                          (invitation_id, from_user_id, to_user_id, game_type, status, created_at, expires_at) 
                          VALUES (?, ?, ?, ?, 'pending', NOW(), DATE_ADD(NOW(), INTERVAL 5 MINUTE))");
    $stmt->execute([$invitationId, $fromUserId, $toUserId, $gameType]);
    
    echo json_encode([
        'success' => true, 
        'message' => '邀請已發送',
        'invitation_id' => $invitationId
    ]);
}

function handleCheckInvitation($pdo, $input) {
    $invitationId = $input['invitation_id'] ?? '';
    $currentUserId = $_SESSION['member_id'];
    
    if (!$invitationId) {
        echo json_encode(['success' => false, 'message' => '缺少邀請ID']);
        return;
    }
    
    // 獲取邀請資訊
    $stmt = $pdo->prepare("
        SELECT gi.*, 
               m1.member_name as from_user_name,
               m2.member_name as to_user_name
        FROM game_invitations gi
        JOIN member m1 ON gi.from_user_id = m1.member_id
        JOIN member m2 ON gi.to_user_id = m2.member_id
        WHERE gi.invitation_id = ? AND (gi.from_user_id = ? OR gi.to_user_id = ?)
    ");
    $stmt->execute([$invitationId, $currentUserId, $currentUserId]);
    $invitation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$invitation) {
        echo json_encode(['success' => false, 'message' => '邀請不存在']);
        return;
    }
    
    // 檢查是否過期
    if (strtotime($invitation['expires_at']) < time()) {
        // 更新狀態為過期
        $stmt = $pdo->prepare("UPDATE game_invitations SET status = 'expired' WHERE invitation_id = ?");
        $stmt->execute([$invitationId]);
        $invitation['status'] = 'expired';
    }
    
    echo json_encode([
        'success' => true,
        'status' => $invitation['status'],
        'invitation' => $invitation
    ]);
}

function handleAcceptInvitation($pdo, $input) {
    $invitationId = $input['invitation_id'] ?? '';
    $currentUserId = $_SESSION['member_id'];
    
    if (!$invitationId) {
        echo json_encode(['success' => false, 'message' => '缺少邀請ID']);
        return;
    }
    
    // 檢查邀請是否存在且屬於當前用戶
    $stmt = $pdo->prepare("SELECT * FROM game_invitations WHERE invitation_id = ? AND to_user_id = ? AND status = 'pending'");
    $stmt->execute([$invitationId, $currentUserId]);
    $invitation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$invitation) {
        echo json_encode(['success' => false, 'message' => '邀請不存在或已處理']);
        return;
    }
    
    // 更新邀請狀態
    $stmt = $pdo->prepare("UPDATE game_invitations SET status = 'accepted', accepted_at = NOW() WHERE invitation_id = ?");
    $stmt->execute([$invitationId]);
    
    echo json_encode([
        'success' => true,
        'message' => '邀請已接受',
        'invitation' => $invitation
    ]);
}

function handleRejectInvitation($pdo, $input) {
    $invitationId = $input['invitation_id'] ?? '';
    $currentUserId = $_SESSION['member_id'];
    
    if (!$invitationId) {
        echo json_encode(['success' => false, 'message' => '缺少邀請ID']);
        return;
    }
    
    // 更新邀請狀態
    $stmt = $pdo->prepare("UPDATE game_invitations SET status = 'rejected', rejected_at = NOW() WHERE invitation_id = ? AND to_user_id = ?");
    $stmt->execute([$invitationId, $currentUserId]);
    
    echo json_encode([
        'success' => true,
        'message' => '邀請已拒絕'
    ]);
}

function handleCancelInvitation($pdo, $input) {
    $invitationId = $input['invitation_id'] ?? '';
    $currentUserId = $_SESSION['member_id'];
    
    if (!$invitationId) {
        echo json_encode(['success' => false, 'message' => '缺少邀請ID']);
        return;
    }
    
    // 更新邀請狀態
    $stmt = $pdo->prepare("UPDATE game_invitations SET status = 'cancelled', cancelled_at = NOW() WHERE invitation_id = ? AND from_user_id = ?");
    $stmt->execute([$invitationId, $currentUserId]);
    
    echo json_encode([
        'success' => true,
        'message' => '邀請已取消'
    ]);
}

function handleGetPendingInvitations($pdo) {
    $currentUserId = $_SESSION['member_id'];
    
    // 獲取待處理的邀請
    $stmt = $pdo->prepare("
        SELECT gi.*, 
               m.member_name as from_user_name,
               m.avatar as from_user_avatar
        FROM game_invitations gi
        JOIN member m ON gi.from_user_id = m.member_id
        WHERE gi.to_user_id = ? AND gi.status = 'pending'
        ORDER BY gi.created_at DESC
    ");
    $stmt->execute([$currentUserId]);
    $invitations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'invitations' => $invitations
    ]);
}
?> 