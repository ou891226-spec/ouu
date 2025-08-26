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
        // 檢查配對並處理回合切換
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

            // 檢查兩張卡片是否都已翻開
            if (!isset($gameState['cards'][$card1Index]['flipped']) || !$gameState['cards'][$card1Index]['flipped'] ||
                !isset($gameState['cards'][$card2Index]['flipped']) || !$gameState['cards'][$card2Index]['flipped']) {
                echo json_encode(['success' => false, 'message' => '卡片未完全翻開']);
                exit;
            }

            // 檢查配對
            $card1Symbol = $gameState['cards'][$card1Index]['symbol'] ?? '';
            $card2Symbol = $gameState['cards'][$card2Index]['symbol'] ?? '';
            $isMatch = ($card1Symbol === $card2Symbol && $card1Symbol !== '');

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
                
                // 檢查遊戲是否結束
                $totalCards = count($gameState['cards']);
                $matchedCards = 0;
                foreach ($gameState['cards'] as $card) {
                    if (isset($card['matched']) && $card['matched']) {
                        $matchedCards += 2;
                    }
                }
                
                if ($matchedCards >= $totalCards) {
                    $gameState['gameEnded'] = true;
                    $gameState['winner'] = ($gameState['player1Score'] > $gameState['player2Score']) ? 1 : 
                                         (($gameState['player2Score'] > $gameState['player1Score']) ? 2 : 'tie');
                }
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
                    'message' => $isMatch ? '配對成功！' : '配對失敗，切換回合',
                    'game_ended' => $gameState['gameEnded'] ?? false,
                    'winner' => $gameState['winner'] ?? null
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => '更新遊戲狀態失敗']);
            }
            break;

        // 獲取配對狀態
        case 'get_match_status':
            $invitationId = $data['invitation_id'] ?? null;
            $playerId = $data['player_id'] ?? null;

            if (!$invitationId) {
                echo json_encode(['success' => false, 'message' => '邀請ID參數缺失']);
                exit;
            }

            $stmt = $pdo->prepare("SELECT game_state, from_user_id FROM game_invitations WHERE invitation_id = ?");
            $stmt->execute([$invitationId]);
            $result = $stmt->fetch();

            if ($result) {
                $gameState = json_decode($result['game_state'], true) ?: [];
                $isInviter = ($playerId == $result['from_user_id']);
                
                // 計算是否輪到當前玩家
                $currentPlayerNumber = $gameState['currentPlayer'] ?? 1;
                $isMyTurn = ($currentPlayerNumber === 1 && $isInviter) || ($currentPlayerNumber === 2 && !$isInviter);
                
                // 獲取已翻開但未配對的卡片
                $flippedCards = [];
                foreach ($gameState['cards'] as $index => $card) {
                    if (isset($card['flipped']) && $card['flipped'] && !isset($card['matched'])) {
                        $flippedCards[] = [
                            'index' => $index,
                            'symbol' => $card['symbol']
                        ];
                    }
                }
                
                echo json_encode([
                    'success' => true,
                    'game_state' => $gameState,
                    'is_my_turn' => $isMyTurn,
                    'current_player' => $currentPlayerNumber,
                    'flipped_cards' => $flippedCards,
                    'game_ended' => $gameState['gameEnded'] ?? false,
                    'winner' => $gameState['winner'] ?? null
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => '找不到遊戲狀態']);
            }
            break;

        // 重置配對狀態（用於測試）
        case 'reset_match_state':
            $invitationId = $data['invitation_id'] ?? null;
            $playerId = $data['player_id'] ?? null;

            if (!$invitationId || !$playerId) {
                echo json_encode(['success' => false, 'message' => '參數缺失']);
                exit;
            }

            // 獲取當前遊戲狀態
            $stmt = $pdo->prepare("SELECT game_state FROM game_invitations WHERE invitation_id = ?");
            $stmt->execute([$invitationId]);
            $result = $stmt->fetch();

            if ($result) {
                $gameState = json_decode($result['game_state'], true) ?: [];
                
                // 重置所有翻開但未配對的卡片
                foreach ($gameState['cards'] as $index => $card) {
                    if (isset($card['flipped']) && $card['flipped'] && !isset($card['matched'])) {
                        $gameState['cards'][$index]['flipped'] = false;
                    }
                }
                
                // 記錄重置動作
                $gameState['lastAction'] = 'reset_match_state';
                $gameState['lastActionBy'] = $playerId;
                $gameState['lastActionTime'] = time();

                // 更新資料庫
                $stmt = $pdo->prepare("UPDATE game_invitations SET game_state = ?, last_updated = NOW() WHERE invitation_id = ?");
                $result = $stmt->execute([json_encode($gameState), $invitationId]);

                if ($result) {
                    echo json_encode([
                        'success' => true,
                        'game_state' => $gameState,
                        'message' => '配對狀態已重置'
                    ]);
                } else {
                    echo json_encode(['success' => false, 'message' => '重置失敗']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => '找不到遊戲狀態']);
            }
            break;

        default:
            echo json_encode(['success' => false, 'message' => '未知操作']);
    }
} catch (Exception $e) {
    error_log("翻牌遊戲配對 API 錯誤: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => '伺服器錯誤']);
}
?>
