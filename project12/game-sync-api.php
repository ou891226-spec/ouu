<?php
session_start();

// 調試信息
error_log("API請求開始 - Session ID: " . session_id() . ", Member ID: " . ($_SESSION['member_id'] ?? '未設置'));

// 檢查用戶是否已登入
if (!isset($_SESSION['member_id']) || empty($_SESSION['member_id'])) {
    error_log("API請求 - 用戶未登入");
    // 如果是API請求，返回JSON錯誤而不是重定向
    if (isset($_SERVER['HTTP_CONTENT_TYPE']) && strpos($_SERVER['HTTP_CONTENT_TYPE'], 'application/json') !== false) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => '未登入']);
        exit;
    }
    // 否則重定向到登入頁面
    header('Location: login.php');
    exit;
}

// 設置用戶ID變數
$_SESSION['user_id'] = $_SESSION['member_id'];

error_log("API請求 - 用戶已登入，Member ID: " . $_SESSION['member_id']);

require_once 'db_connect.php';

header('Content-Type: application/json');

// 禁用錯誤輸出
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '只接受POST請求']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';

try {
    switch ($action) {
        case 'test_connection':
            // 測試連接
            error_log("API測試連接 - 用戶ID: " . $_SESSION['member_id']);
            echo json_encode([
                'success' => true, 
                'message' => 'API連接正常',
                'user_id' => $_SESSION['member_id'],
                'session_id' => session_id()
            ]);
            break;
            
        case 'update_game_state':
            // 更新遊戲狀態
            $invitationId = $data['invitation_id'];
            $gameState = $data['game_state'];
            
            error_log("更新遊戲狀態 - 邀請ID: $invitationId, 遊戲狀態: " . json_encode($gameState));
            
            // 檢查邀請是否存在
            $stmt = $pdo->prepare("SELECT * FROM game_invitations WHERE invitation_id = ? AND status = 'accepted'");
            $stmt->execute([$invitationId]);
            $invitation = $stmt->fetch();
            
            if (!$invitation) {
                error_log("邀請不存在或未接受 - 邀請ID: $invitationId");
                echo json_encode(['success' => false, 'message' => '邀請不存在或未接受']);
                exit;
            }
            
            // 更新遊戲狀態
            $stmt = $pdo->prepare("
                UPDATE game_invitations 
                SET game_state = ?, last_updated = NOW() 
                WHERE invitation_id = ?
            ");
            $result = $stmt->execute([json_encode($gameState), $invitationId]);
            
            if ($result) {
                error_log("遊戲狀態更新成功 - 邀請ID: $invitationId");
                echo json_encode(['success' => true]);
            } else {
                error_log("遊戲狀態更新失敗 - 邀請ID: $invitationId");
                echo json_encode(['success' => false, 'message' => '更新遊戲狀態失敗']);
            }
            break;
            
        case 'get_game_state':
            // 獲取遊戲狀態
            $invitationId = $data['invitation_id'];
            
            error_log("獲取遊戲狀態 - 邀請ID: $invitationId");
            
            $stmt = $pdo->prepare("SELECT game_state, game_end_state, status, last_updated FROM game_invitations WHERE invitation_id = ?");
            $stmt->execute([$invitationId]);
            $result = $stmt->fetch();
            
            if ($result) {
                $gameState = json_decode($result['game_state'], true) ?: [];
                $gameEndState = json_decode($result['game_end_state'], true) ?: [];
                
                error_log("遊戲狀態查詢結果 - 狀態: " . $result['status'] . ", 遊戲狀態: " . json_encode($gameState));
                
                // 檢查是否有玩家退出
                if ($result['status'] === 'quit') {
                    echo json_encode([
                        'success' => true, 
                        'game_state' => $gameState,
                        'last_updated' => $result['last_updated'],
                        'is_game_end' => false,
                        'player_quit' => true
                    ]);
                    exit;
                }
                
                // 如果有遊戲結束狀態，優先返回
                if (!empty($gameEndState)) {
                    echo json_encode([
                        'success' => true, 
                        'game_state' => $gameEndState,
                        'last_updated' => $result['last_updated'],
                        'is_game_end' => true
                    ]);
                } else {
                    echo json_encode([
                        'success' => true, 
                        'game_state' => $gameState,
                        'last_updated' => $result['last_updated'],
                        'is_game_end' => false
                    ]);
                }
            } else {
                error_log("找不到遊戲狀態 - 邀請ID: $invitationId");
                echo json_encode(['success' => false, 'message' => '找不到遊戲狀態']);
            }
            break;
            
        case 'update_game_end':
            // 更新遊戲結束狀態
            $invitationId = $data['invitation_id'];
            $playerId = $data['player_id'];
            $gameEndState = $data['game_end_state'];
            
            // 檢查邀請是否存在
            $stmt = $pdo->prepare("SELECT * FROM game_invitations WHERE invitation_id = ? AND status = 'accepted'");
            $stmt->execute([$invitationId]);
            $invitation = $stmt->fetch();
            
            if (!$invitation) {
                echo json_encode(['success' => false, 'message' => '邀請不存在或未接受']);
                exit;
            }
            
            // 更新遊戲結束狀態
            $stmt = $pdo->prepare("
                UPDATE game_invitations 
                SET game_end_state = ?, last_updated = NOW() 
                WHERE invitation_id = ?
            ");
            $stmt->execute([json_encode($gameEndState), $invitationId]);
            
            echo json_encode(['success' => true, 'message' => '遊戲結束狀態已更新']);
            break;
            
        case 'player_quit':
            // 玩家退出對戰
            $invitationId = $data['invitation_id'];
            $playerId = $data['player_id'];
            
            // 檢查邀請是否存在
            $stmt = $pdo->prepare("SELECT * FROM game_invitations WHERE invitation_id = ? AND status = 'accepted'");
            $stmt->execute([$invitationId]);
            $invitation = $stmt->fetch();
            
            if (!$invitation) {
                echo json_encode(['success' => false, 'message' => '邀請不存在或未接受']);
                exit;
            }
            
            // 更新邀請狀態為已退出
            $stmt = $pdo->prepare("
                UPDATE game_invitations 
                SET status = 'quit', last_updated = NOW() 
                WHERE invitation_id = ?
            ");
            $stmt->execute([$invitationId]);
            
            echo json_encode(['success' => true, 'message' => '玩家已退出對戰']);
            break;
            
        case 'test_connection':
            // 測試資料庫連接
            try {
                $stmt = $pdo->prepare("SELECT 1");
                $stmt->execute();
                echo json_encode(['success' => true, 'message' => '資料庫連接正常']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => '資料庫連接失敗: ' . $e->getMessage()]);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => '未知操作']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => '錯誤：' . $e->getMessage()]);
}
?> 