<?php
// 避免任何輸出緩衝問題
ob_start();

require_once 'check_login.php';
require_once 'db_connect.php';

// 清除任何可能的輸出
ob_clean();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept, Authorization');
header('Access-Control-Allow-Credentials: true');

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
            
        case 'get_friends':
            handleGetFriends($pdo, $input);
            break;
            
        case 'update_invitation_settings':
            handleUpdateInvitationSettings($pdo, $input);
            break;
            
        case 'update_invitation_status':
            handleUpdateInvitationStatus($pdo, $input);
            break;
            
        case 'find_users':
            handleFindUsers($pdo, $input);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => '無效的操作']);
    }
} catch (Exception $e) {
    error_log("遊戲邀請 API 錯誤: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => '伺服器錯誤，請稍後再試']);
}

function handleSendInvitation($pdo, $input) {
    // 支持指定發送者ID（用於測試），否則使用當前登入用戶
    $fromUserId = $input['from_user_id'] ?? $_SESSION['member_id'];
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
    
    // 檢查是否已有待處理的邀請（只檢查未過期的）
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM game_invitations 
                          WHERE from_user_id = ? AND to_user_id = ? AND status = 'pending' 
                          AND expires_at > NOW()");
    $stmt->execute([$fromUserId, $toUserId]);
    if ($stmt->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => '已有待處理的邀請']);
        return;
    }
    
    // 清理過期的邀請
    $stmt = $pdo->prepare("UPDATE game_invitations SET status = 'expired' 
                          WHERE from_user_id = ? AND to_user_id = ? AND status = 'pending' 
                          AND expires_at <= NOW()");
    $stmt->execute([$fromUserId, $toUserId]);
    
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
    
    // 解析遊戲設定
    $gameSettings = null;
    if ($invitation['game_settings']) {
        $gameSettings = json_decode($invitation['game_settings'], true);
    }
    
    // 構建響應
    $response = [
        'success' => true,
        'status' => $invitation['status'],
        'invitation' => array_merge($invitation, ['game_settings' => $gameSettings])
    ];
    
    // 如果有遊戲設定，直接返回
    if ($gameSettings) {
        $response['game_settings'] = $gameSettings;
    }
    
    echo json_encode($response);
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
    
    // 獲取更新後的完整邀請信息
    $stmt = $pdo->prepare("
        SELECT gi.*, 
               m1.member_name as from_user_name,
               m2.member_name as to_user_name
        FROM game_invitations gi
        JOIN member m1 ON gi.from_user_id = m1.member_id
        JOIN member m2 ON gi.to_user_id = m2.member_id
        WHERE gi.invitation_id = ?
    ");
    $stmt->execute([$invitationId]);
    $updatedInvitation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'message' => '邀請已接受',
        'invitation' => $updatedInvitation
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
    
    // 獲取待處理的邀請（包括 quit 狀態的邀請，用於調試）
    $stmt = $pdo->prepare("
        SELECT gi.*, 
               m.member_name as from_user_name,
               m.avatar as from_user_avatar
        FROM game_invitations gi
        JOIN member m ON gi.from_user_id = m.member_id
        WHERE gi.to_user_id = ? AND (gi.status = 'pending' OR gi.status = 'accepted' OR gi.status = 'quit')
        ORDER BY gi.created_at DESC
    ");
    $stmt->execute([$currentUserId]);
    $invitations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'invitations' => $invitations
    ]);
}

function handleGetFriends($pdo, $input) {
    $currentUserId = $_SESSION['member_id'];
    
    // 獲取好友列表
    $stmt = $pdo->prepare("
        SELECT m.member_id as id, m.member_name as name, m.avatar
        FROM friends f
        JOIN member m ON f.friend_id = m.member_id
        WHERE f.member_id = ?
        ORDER BY m.member_name
    ");
    $stmt->execute([$currentUserId]);
    $friends = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'friends' => $friends
    ]);
}

function handleUpdateInvitationSettings($pdo, $input) {
    $invitationId = $input['invitation_id'] ?? 0;
    $gameSettings = $input['game_settings'] ?? [];
    
    if (!$invitationId) {
        echo json_encode(['success' => false, 'message' => '缺少邀請ID']);
        return;
    }
    
    if (empty($gameSettings)) {
        echo json_encode(['success' => false, 'message' => '缺少遊戲設定']);
        return;
    }
    
    // 檢查邀請是否存在且屬於當前用戶
    $stmt = $pdo->prepare("SELECT * FROM game_invitations WHERE invitation_id = ? AND (from_user_id = ? OR to_user_id = ?)");
    $stmt->execute([$invitationId, $_SESSION['member_id'], $_SESSION['member_id']]);
    $invitation = $stmt->fetch();
    
    if (!$invitation) {
        echo json_encode(['success' => false, 'message' => '邀請不存在或無權限']);
        return;
    }
    
    // 更新遊戲設定
    $gameSettingsJson = json_encode($gameSettings);
    $stmt = $pdo->prepare("UPDATE game_invitations SET game_settings = ?, last_updated = NOW() WHERE invitation_id = ?");
    $stmt->execute([$gameSettingsJson, $invitationId]);
    
    echo json_encode(['success' => true, 'message' => '遊戲設定已更新']);
}

function handleUpdateInvitationStatus($pdo, $input) {
    $invitationId = $input['invitation_id'] ?? 0;
    $status = $input['status'] ?? '';
    
    if (!$invitationId || !$status) {
        echo json_encode(['success' => false, 'message' => '缺少必要參數']);
        return;
    }
    
    // 檢查邀請是否存在且屬於當前用戶
    $stmt = $pdo->prepare("SELECT * FROM game_invitations WHERE invitation_id = ? AND (from_user_id = ? OR to_user_id = ?)");
    $stmt->execute([$invitationId, $_SESSION['member_id'], $_SESSION['member_id']]);
    $invitation = $stmt->fetch();
    
    if (!$invitation) {
        echo json_encode(['success' => false, 'message' => '邀請不存在或無權限']);
        return;
    }
    
    // 更新邀請狀態
    $stmt = $pdo->prepare("UPDATE game_invitations SET status = ?, last_updated = NOW() WHERE invitation_id = ?");
    $stmt->execute([$status, $invitationId]);
    
    echo json_encode(['success' => true, 'message' => '邀請狀態已更新']);
}

function handleFindUsers($pdo, $input) {
    $senderName = $input['sender_name'] ?? '';
    $receiverName = $input['receiver_name'] ?? '';
    
    if (!$senderName || !$receiverName) {
        echo json_encode(['success' => false, 'message' => '請提供發送者和接收者用戶名']);
        return;
    }
    
    // 查找發送者
    $stmt = $pdo->prepare("SELECT member_id, member_name FROM member WHERE member_name = ?");
    $stmt->execute([$senderName]);
    $sender = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // 查找接收者
    $stmt = $pdo->prepare("SELECT member_id, member_name FROM member WHERE member_name = ?");
    $stmt->execute([$receiverName]);
    $receiver = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$sender) {
        echo json_encode(['success' => false, 'message' => '找不到發送者用戶: ' . $senderName]);
        return;
    }
    
    if (!$receiver) {
        echo json_encode(['success' => false, 'message' => '找不到接收者用戶: ' . $receiverName]);
        return;
    }
    
    // 檢查是否為好友關係
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM friends WHERE member_id = ? AND friend_id = ?");
    $stmt->execute([$sender['member_id'], $receiver['member_id']]);
    $isFriend = $stmt->fetchColumn() > 0;
    
    echo json_encode([
        'success' => true,
        'sender_id' => $sender['member_id'],
        'receiver_id' => $receiver['member_id'],
        'sender_name' => $sender['member_name'],
        'receiver_name' => $receiver['member_name'],
        'is_friend' => $isFriend
    ]);
}
?> 