<?php
session_start();

// 檢查用戶是否已登入
if (!isset($_SESSION['member_id']) || empty($_SESSION['member_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => '未登入']);
    exit;
}

require_once 'db_connect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '只接受POST請求']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';

try {
    switch ($action) {
        // 玩家翻牌動作
        case 'flip_card':
            $invitationId = $data['invitation_id'] ?? null;
            $playerId = $data['player_id'] ?? null;
            $cardIndex = $data['card_index'] ?? null;
            $cardSymbol = $data['card_symbol'] ?? null;

            if (!$invitationId || !$playerId || $cardIndex === null) {
                echo json_encode(['success' => false, 'message' => '參數缺失']);
                exit;
            }

            // 獲取當前遊戲狀態
            $stmt = $pdo->prepare("SELECT game_state, from_user_id FROM game_invitations WHERE invitation_id = ? AND status = 'accepted'");
            $stmt->execute([$invitationId]);
            $invitation = $stmt->fetch();

            if (!$invitation) {
                echo json_encode(['success' => false, 'message' => '邀請不存在或未接受']);
                exit;
            }

            $gameState = json_decode($invitation['game_state'], true) ?: [];

            // 驗證是否輪到當前玩家
            $isInviter = ($playerId == $invitation['from_user_id']);
            $currentPlayerNumber = $gameState['currentPlayer'] ?? 1;
            $isMyTurn = ($currentPlayerNumber === 1 && $isInviter) || ($currentPlayerNumber === 2 && !$isInviter);

            if (!$isMyTurn) {
                echo json_encode(['success' => false, 'message' => '不是你的回合']);
                exit;
            }

            // 檢查卡片是否已經被配對
            if (isset($gameState['cards'][$cardIndex]['matched']) && $gameState['cards'][$cardIndex]['matched']) {
                echo json_encode(['success' => false, 'message' => '卡片已被配對']);
                exit;
            }

            // 更新卡片狀態
            $gameState['cards'][$cardIndex]['flipped'] = true;
            $gameState['cards'][$cardIndex]['symbol'] = $cardSymbol;
            
            // 記錄翻牌動作
            $gameState['lastAction'] = 'flip_card';
            $gameState['lastActionBy'] = $playerId;
            $gameState['lastFlippedCardIndex'] = $cardIndex;
            $gameState['lastActionTime'] = time();

            // 更新資料庫
            $stmt = $pdo->prepare("UPDATE game_invitations SET game_state = ?, last_updated = NOW() WHERE invitation_id = ?");
            $result = $stmt->execute([json_encode($gameState), $invitationId]);

            if ($result) {
                echo json_encode([
                    'success' => true, 
                    'message' => '翻牌成功',
                    'game_state' => $gameState
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => '更新遊戲狀態失敗']);
            }
            break;

        // 檢查配對並切換回合
        case 'check_match':
            $invitationId = $data['invitation_id'] ?? null;
            $playerId = $data['player_id'] ?? null;
            $card1Index = $data['card1_index'] ?? null;
            $card2Index = $data['card2_index'] ?? null;

            if (!$invitationId || !$playerId || $card1Index === null || $card2Index === null) {
                echo json_encode(['success' => false, 'message' => '參數缺失']);
                exit;
            }

            // 獲取當前遊戲狀態
            $stmt = $pdo->prepare("SELECT game_state, from_user_id FROM game_invitations WHERE invitation_id = ? AND status = 'accepted'");
            $stmt->execute([$invitationId]);
            $invitation = $stmt->fetch();

            if (!$invitation) {
                echo json_encode(['success' => false, 'message' => '邀請不存在或未接受']);
                exit;
            }

            $gameState = json_decode($invitation['game_state'], true) ?: [];

            // 檢查配對
            $card1Symbol = $gameState['cards'][$card1Index]['symbol'] ?? '';
            $card2Symbol = $gameState['cards'][$card2Index]['symbol'] ?? '';
            $isMatch = ($card1Symbol === $card2Symbol);

            if ($isMatch) {
                // 配對成功
                $gameState['cards'][$card1Index]['matched'] = true;
                $gameState['cards'][$card2Index]['matched'] = true;
                $gameState['matchedPairs'] = ($gameState['matchedPairs'] ?? 0) + 1;

                // 更新分數
                $isInviter = ($playerId == $invitation['from_user_id']);
                $currentPlayerNumber = $gameState['currentPlayer'] ?? 1;
                
                if ($currentPlayerNumber === 1) {
                    $gameState['player1Score'] = ($gameState['player1Score'] ?? 0) + 10;
                    $gameState['player1Pairs'] = ($gameState['player1Pairs'] ?? 0) + 1;
                } else {
                    $gameState['player2Score'] = ($gameState['player2Score'] ?? 0) + 10;
                    $gameState['player2Pairs'] = ($gameState['player2Pairs'] ?? 0) + 1;
                }
                
                // 配對成功，保持當前玩家回合
                $gameState['consecutiveMatches'] = ($gameState['consecutiveMatches'] ?? 0) + 1;
            } else {
                // 配對失敗
                $gameState['cards'][$card1Index]['flipped'] = false;
                $gameState['cards'][$card2Index]['flipped'] = false;
                $gameState['consecutiveMatches'] = 0;
                
                // 切換回合
                $gameState['currentPlayer'] = ($gameState['currentPlayer'] === 1) ? 2 : 1;
            }

            // 記錄配對結果
            $gameState['lastAction'] = 'check_match';
            $gameState['lastActionBy'] = $playerId;
            $gameState['lastMatchResult'] = $isMatch;
            $gameState['lastFlippedCardIndexes'] = [$card1Index, $card2Index];
            $gameState['lastActionTime'] = time();

            // 更新資料庫
            $stmt = $pdo->prepare("UPDATE game_invitations SET game_state = ?, last_updated = NOW() WHERE invitation_id = ?");
            $result = $stmt->execute([json_encode($gameState), $invitationId]);

            if ($result) {
                echo json_encode([
                    'success' => true,
                    'is_match' => $isMatch,
                    'game_state' => $gameState,
                    'message' => $isMatch ? '配對成功！' : '配對失敗，切換回合'
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => '更新遊戲狀態失敗']);
            }
            break;

        // 獲取遊戲狀態
        case 'get_game_state':
            $invitationId = $data['invitation_id'] ?? null;
            $playerId = $data['player_id'] ?? null;

            if (!$invitationId) {
                echo json_encode(['success' => false, 'message' => '邀請ID參數缺失']);
                exit;
            }

            $stmt = $pdo->prepare("SELECT game_state, from_user_id, last_updated FROM game_invitations WHERE invitation_id = ?");
            $stmt->execute([$invitationId]);
            $result = $stmt->fetch();

            if ($result) {
                $gameState = json_decode($result['game_state'], true) ?: [];
                $isInviter = ($playerId == $result['from_user_id']);
                
                // 計算是否輪到當前玩家
                $currentPlayerNumber = $gameState['currentPlayer'] ?? 1;
                $isMyTurn = ($currentPlayerNumber === 1 && $isInviter) || ($currentPlayerNumber === 2 && !$isInviter);
                
                echo json_encode([
                    'success' => true,
                    'game_state' => $gameState,
                    'is_my_turn' => $isMyTurn,
                    'current_player' => $currentPlayerNumber,
                    'last_updated' => $result['last_updated']
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => '找不到遊戲狀態']);
            }
            break;

        // 初始化遊戲
        case 'init_game':
            $invitationId = $data['invitation_id'] ?? null;
            $playerId = $data['player_id'] ?? null;
            $difficulty = $data['difficulty'] ?? 'easy';
            $theme = $data['theme'] ?? 'fruit';

            if (!$invitationId || !$playerId) {
                echo json_encode(['success' => false, 'message' => '參數缺失']);
                exit;
            }

            // 生成卡片
            $cardSymbols = [];
            switch ($difficulty) {
                case 'easy':
                    $symbols = ['🍎', '🍌', '🍊', '🍇', '🍓', '🍑', '🥝', '🍍'];
                    break;
                case 'medium':
                    $symbols = ['🐶', '🐱', '🐭', '🐹', '🐰', '🦊', '🐻', '🐼', '🐨', '🐯', '🦁', '🐮'];
                    break;
                case 'hard':
                    $symbols = ['⚽', '🏀', '🏈', '⚾', '🎾', '🏐', '🏉', '🎱', '🏓', '🏸', '🏒', '🏑'];
                    break;
                default:
                    $symbols = ['🍎', '🍌', '🍊', '🍇', '🍓', '🍑'];
            }

            // 創建配對卡片
            foreach ($symbols as $symbol) {
                $cardSymbols[] = $symbol;
                $cardSymbols[] = $symbol;
            }

            // 打亂卡片順序
            shuffle($cardSymbols);

            // 創建遊戲狀態
            $gameState = [
                'cards' => [],
                'currentPlayer' => 1,
                'player1Score' => 0,
                'player2Score' => 0,
                'player1Pairs' => 0,
                'player2Pairs' => 0,
                'matchedPairs' => 0,
                'consecutiveMatches' => 0,
                'difficulty' => $difficulty,
                'theme' => $theme,
                'gameStarted' => true,
                'lastAction' => 'init_game',
                'lastActionBy' => $playerId,
                'lastActionTime' => time()
            ];

            // 初始化卡片
            foreach ($cardSymbols as $index => $symbol) {
                $gameState['cards'][$index] = [
                    'symbol' => $symbol,
                    'flipped' => false,
                    'matched' => false
                ];
            }

            // 更新資料庫
            $stmt = $pdo->prepare("UPDATE game_invitations SET game_state = ?, last_updated = NOW() WHERE invitation_id = ?");
            $result = $stmt->execute([json_encode($gameState), $invitationId]);

            if ($result) {
                echo json_encode([
                    'success' => true,
                    'game_state' => $gameState,
                    'message' => '遊戲初始化成功'
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => '遊戲初始化失敗']);
            }
            break;

        // 玩家退出
        case 'player_quit':
            $invitationId = $data['invitation_id'] ?? null;
            $playerId = $data['player_id'] ?? null;

            if (!$invitationId || !$playerId) {
                echo json_encode(['success' => false, 'message' => '參數缺失']);
                exit;
            }

            $stmt = $pdo->prepare("UPDATE game_invitations SET status = 'quit', last_updated = NOW() WHERE invitation_id = ?");
            $result = $stmt->execute([$invitationId]);

            if ($result) {
                echo json_encode(['success' => true, 'message' => '玩家已退出']);
            } else {
                echo json_encode(['success' => false, 'message' => '退出失敗']);
            }
            break;

        default:
            echo json_encode(['success' => false, 'message' => '未知操作']);
    }
} catch (Exception $e) {
    error_log("翻牌遊戲同步 API 錯誤: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => '伺服器錯誤']);
}
?>

