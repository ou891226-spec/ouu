<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');

// 檢查用戶是否已登入
if (!isset($_SESSION['member_id'])) {
    echo json_encode(['success' => false, 'message' => '未登入']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';

// 調試：記錄接收到的數據
error_log("force_sync_fix.php 收到請求: " . json_encode($data));

try {
    switch ($action) {
        case 'force_sync_game_state':
            $invitationId = $data['invitation_id'] ?? null;
            $playerId = $_SESSION['member_id'];
            
            if (!$invitationId) {
                echo json_encode(['success' => false, 'message' => '邀請ID缺失']);
                exit;
            }
            
            // 獲取邀請信息
            $stmt = $pdo->prepare("SELECT * FROM game_invitations WHERE invitation_id = ? AND status = 'accepted'");
            $stmt->execute([$invitationId]);
            $invitation = $stmt->fetch();
            
            if (!$invitation) {
                echo json_encode(['success' => false, 'message' => '邀請不存在或未接受']);
                exit;
            }
            
            $gameState = json_decode($invitation['game_state'], true) ?: [];
            
            // 強制同步遊戲狀態
            $syncState = [
                'invitation_id' => $invitationId,
                'from_user_id' => $invitation['from_user_id'],
                'to_user_id' => $invitation['to_user_id'],
                'difficulty' => $gameState['difficulty'] ?? 'normal',
                'theme' => $gameState['theme'] ?? 'fruit',
                'currentPlayer' => $gameState['currentPlayer'] ?? 1,
                'player1Score' => $gameState['player1Score'] ?? 0,
                'player2Score' => $gameState['player2Score'] ?? 0,
                'player1Pairs' => $gameState['player1Pairs'] ?? 0,
                'player2Pairs' => $gameState['player2Pairs'] ?? 0,
                'matchedPairs' => $gameState['matchedPairs'] ?? 0,
                'cards' => $gameState['cards'] ?? [],
                'lastAction' => $gameState['lastAction'] ?? '',
                'lastActionBy' => $gameState['lastActionBy'] ?? '',
                'lastFlippedCardIndex' => $gameState['lastFlippedCardIndex'] ?? null,
                'lastFlippedCardIndexes' => $gameState['lastFlippedCardIndexes'] ?? [],
                'lastMatchResult' => $gameState['lastMatchResult'] ?? false,
                'gameStarted' => $gameState['gameStarted'] ?? false,
                'last_updated' => $invitation['last_updated']
            ];
            
            echo json_encode([
                'success' => true,
                'game_state' => $syncState,
                'message' => '強制同步成功'
            ]);
            break;
            
        case 'reset_game_state':
            $invitationId = $data['invitation_id'] ?? null;
            $difficulty = $data['difficulty'] ?? 'normal';
            $theme = $data['theme'] ?? 'fruit';
            
            if (!$invitationId) {
                echo json_encode(['success' => false, 'message' => '邀請ID缺失']);
                exit;
            }
            
            // 根據困難度生成卡片
            $cards = [];
            $symbols = [];
            
            // 根據主題選擇符號
            switch ($theme) {
                case 'fruit':
                    $symbols = ['🍎', '🍌', '🍊', '🍇', '🍓', '🍑', '🥝', '🥭', '🍍', '🥥', '🍈', '🍉', '🍊', '🍋', '🍌', '🍍'];
                    break;
                case 'vegetable':
                    $symbols = ['🥕', '🥦', '🥬', '🥒', '🍅', '🌽', '🥔', '🧅', '🧄', '🥜', '🌶️', '🥑', '🍆', '🥬', '🥒', '🥕'];
                    break;
                case 'animal':
                    $symbols = ['🐶', '🐱', '🐭', '🐹', '🐰', '🦊', '🐻', '🐼', '🐨', '🐯', '🦁', '🐮', '🐷', '🐸', '🐵', '🐔'];
                    break;
                default:
                    $symbols = ['🍎', '🍌', '🍊', '🍇', '🍓', '🍑', '🥝', '🥭', '🍍', '🥥', '🍈', '🍉', '🍊', '🍋', '🍌', '🍍'];
            }
            
            // 根據困難度決定卡片數量
            $totalCards = 0;
            switch ($difficulty) {
                case 'easy':
                    $totalCards = 12; // 4x3
                    break;
                case 'normal':
                    $totalCards = 16; // 4x4
                    break;
                case 'hard':
                    $totalCards = 32; // 8x4
                    break;
                default:
                    $totalCards = 16;
            }
            
            // 生成卡片對
            $cardSymbols = [];
            for ($i = 0; $i < $totalCards / 2; $i++) {
                $symbol = $symbols[$i % count($symbols)];
                $cardSymbols[] = $symbol;
                $cardSymbols[] = $symbol;
            }
            
            // 打亂卡片順序
            shuffle($cardSymbols);
            
            // 創建卡片陣列
            for ($i = 0; $i < $totalCards; $i++) {
                $cards[] = [
                    'index' => $i,
                    'symbol' => $cardSymbols[$i],
                    'flipped' => false,
                    'matched' => false
                ];
            }
            
            // 創建新的遊戲狀態
            $newGameState = [
                'difficulty' => $difficulty,
                'theme' => $theme,
                'currentPlayer' => 1,
                'player1Score' => 0,
                'player2Score' => 0,
                'player1Pairs' => 0,
                'player2Pairs' => 0,
                'matchedPairs' => 0,
                'cards' => $cards,
                'lastAction' => 'game_reset',
                'lastActionBy' => $_SESSION['member_id'],
                'lastFlippedCardIndex' => null,
                'lastFlippedCardIndexes' => [],
                'lastMatchResult' => false,
                'gameStarted' => true
            ];
            
            // 更新資料庫
            $stmt = $pdo->prepare("UPDATE game_invitations SET game_state = ?, last_updated = NOW() WHERE invitation_id = ?");
            $result = $stmt->execute([json_encode($newGameState), $invitationId]);
            
            if ($result) {
                echo json_encode([
                    'success' => true,
                    'game_state' => $newGameState,
                    'message' => '遊戲狀態重置成功'
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => '重置失敗']);
            }
            break;
            
        default:
            error_log("force_sync_fix.php 未知操作: " . $action);
            echo json_encode(['success' => false, 'message' => '未知操作: ' . $action]);
    }
} catch (Exception $e) {
    error_log("強制同步錯誤: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => '伺服器錯誤: ' . $e->getMessage()]);
}
?>
