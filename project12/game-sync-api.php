<?php
session_start();

// 調試信息
error_log("API請求開始 - Session ID: " . session_id() . ", Member ID: " . ($_SESSION['member_id'] ?? '未設置'));

// 檢查用戶是否已登入
if (!isset($_SESSION['member_id']) || empty($_SESSION['member_id'])) {
    error_log("API請求 - 用戶未登入");
    if (isset($_SERVER['HTTP_CONTENT_TYPE']) && strpos($_SERVER['HTTP_CONTENT_TYPE'], 'application/json') !== false) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => '未登入']);
        exit;
    }
    header('Location: login.php');
    exit;
}

// 設置用戶ID變數
$_SESSION['user_id'] = $_SESSION['member_id'];

error_log("API請求 - 用戶已登入，Member ID: " . $_SESSION['member_id']);

require_once 'db_connect.php'; // 確保 db_connect.php 包含 $pdo 物件

header('Content-Type: application/json');

// 禁用錯誤輸出 (在開發階段建議開啟，部署時再關閉)
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
            error_log("API測試連接 - 用戶ID: " . $_SESSION['member_id']);
            echo json_encode([
                'success' => true, 
                'message' => 'API連接正常',
                'user_id' => $_SESSION['member_id'],
                'session_id' => session_id()
            ]);
            break;
            



        // --- 翻牌遊戲困難度同步功能 ---------------------------------------------------------------
        case 'sync_difficulty':
            $invitationId = $data['invitation_id'] ?? null;
            $playerId = $data['player_id'] ?? null;
            $difficulty = $data['difficulty'] ?? null;

            if (!$invitationId || !$playerId || !$difficulty) {
                error_log("sync_difficulty 參數缺失 - 邀請ID: $invitationId, 玩家ID: $playerId, 困難度: $difficulty");
                echo json_encode(['success' => false, 'message' => '參數缺失']);
                exit;
            }

            // 獲取當前遊戲狀態
            $stmt = $pdo->prepare("SELECT game_state, from_user_id, to_user_id FROM game_invitations WHERE invitation_id = ? AND status = 'accepted'");
            $stmt->execute([$invitationId]);
            $invitation = $stmt->fetch();

            if (!$invitation) {
                error_log("sync_difficulty 邀請不存在或未接受 - 邀請ID: $invitationId");
                echo json_encode(['success' => false, 'message' => '邀請不存在或未接受']);
                exit;
            }

            $gameState = json_decode($invitation['game_state'], true) ?: [];
            
            // 更新困難度設定
            $gameState['difficulty'] = $difficulty;
            $gameState['lastAction'] = 'sync_difficulty';
            $gameState['lastActionBy'] = $playerId;

            // 將更新後的新狀態存回資料庫
            $stmt = $pdo->prepare("UPDATE game_invitations SET game_state = ?, last_updated = NOW() WHERE invitation_id = ?");
            $result = $stmt->execute([json_encode($gameState), $invitationId]);

            if ($result) {
                error_log("翻牌遊戲困難度同步成功 - 邀請ID: $invitationId, 困難度: $difficulty");
                echo json_encode(['success' => true]);
            } else {
                error_log("翻牌遊戲困難度同步失敗 - 邀請ID: $invitationId");
                echo json_encode(['success' => false, 'message' => '更新遊戲狀態失敗']);
            }
            break;

        // --- 算菜錢遊戲題目同步功能 ---------------------------------------------------------------
        case 'sync_questions':
            $invitationId = $data['invitation_id'] ?? null;
            $playerId = $data['player_id'] ?? null;
            $questions = $data['questions'] ?? null;
            $totalQuestions = $data['total_questions'] ?? 0;
            $currentDifficulty = $data['current_difficulty'] ?? null;

            if (!$invitationId || !$playerId) {
                error_log("sync_questions 參數缺失 - 邀請ID: $invitationId, 玩家ID: $playerId");
                echo json_encode(['success' => false, 'message' => '參數缺失']);
                exit;
            }

            // 獲取當前遊戲狀態
            $stmt = $pdo->prepare("SELECT game_state, from_user_id, to_user_id FROM game_invitations WHERE invitation_id = ? AND status = 'accepted'");
            $stmt->execute([$invitationId]);
            $invitation = $stmt->fetch();

            if (!$invitation) {
                error_log("sync_questions 邀請不存在或未接受 - 邀請ID: $invitationId");
                echo json_encode(['success' => false, 'message' => '邀請不存在或未接受']);
                exit;
            }

            $gameState = json_decode($invitation['game_state'], true) ?: [];
            
            // 更新題目數據
            $gameState['questions'] = $questions;
            $gameState['total_questions'] = $totalQuestions;
            $gameState['current_difficulty'] = $currentDifficulty;
            $gameState['lastAction'] = 'sync_questions';
            $gameState['lastActionBy'] = $playerId;

            // 將更新後的新狀態存回資料庫
            $stmt = $pdo->prepare("UPDATE game_invitations SET game_state = ?, last_updated = NOW() WHERE invitation_id = ?");
            $result = $stmt->execute([json_encode($gameState), $invitationId]);

            if ($result) {
                error_log("算菜錢遊戲題目同步成功 - 邀請ID: $invitationId, 題目數量: $totalQuestions");
                echo json_encode(['success' => true]);
            } else {
                error_log("算菜錢遊戲題目同步失敗 - 邀請ID: $invitationId");
                echo json_encode(['success' => false, 'message' => '更新遊戲狀態失敗']);
            }
            break;



        // --- 算菜錢遊戲同步功能 ------------------------------------------------------------
        case 'sync_answer':
            $invitationId = $data['invitation_id'] ?? null;
            $playerId = $data['player_id'] ?? null;
            $selectedAnswer = $data['selected_answer'] ?? null;
            $correctAnswer = $data['correct_answer'] ?? null;
            $isCorrect = $data['is_correct'] ?? false;
            $currentQuestion = $data['current_question'] ?? 0;
            $player1Score = $data['player1_score'] ?? 0;
            $player2Score = $data['player2_score'] ?? 0;
            $player1Correct = $data['player1_correct'] ?? 0;
            $player2Correct = $data['player2_correct'] ?? 0;
            $totalQuestions = $data['total_questions'] ?? 0;
            $currentQuestionData = $data['current_question_data'] ?? null; // 新增：當前題目數據

            if (!$invitationId || $playerId === null) {
                echo json_encode(['success' => false, 'message' => '參數缺失']);
                exit;
            }

            // 獲取當前遊戲狀態
            $stmt = $pdo->prepare("SELECT game_state, from_user_id, to_user_id FROM game_invitations WHERE invitation_id = ? AND status = 'accepted'");
            $stmt->execute([$invitationId]);
            $invitation = $stmt->fetch();

            if (!$invitation) {
                echo json_encode(['success' => false, 'message' => '邀請不存在或未接受']);
                exit;
            }

            $gameState = json_decode($invitation['game_state'], true) ?: [];
            
            // 更新遊戲狀態
            $gameState['current_question'] = $currentQuestion;
            $gameState['player1_score'] = $player1Score;
            $gameState['player2_score'] = $player2Score;
            $gameState['player1_correct'] = $player1Correct;
            $gameState['player2_correct'] = $player2Correct;
            $gameState['total_questions'] = $totalQuestions;
            
            // 同步當前題目數據（如果提供）
            if ($currentQuestionData) {
                $gameState['current_question_data'] = $currentQuestionData;
            }
            
            // 切換玩家（1 或 2）
            $isInviter = ($playerId == $invitation['from_user_id']);
            $currentPlayer = $gameState['current_player'] ?? 1;
            $gameState['current_player'] = ($currentPlayer === 1) ? 2 : 1;
            
            // 直接切換到下一題，不需要等待雙方都答完
            $gameState['current_question'] = $currentQuestion + 1;
            $gameState['last_action'] = 'next_question';
            $gameState['last_action_by'] = $playerId;
            
            echo json_encode(['success' => true, 'message' => '答案已同步，題目已切換']);
            
            // 記錄答案
            $gameState['last_answer'] = [
                'player_id' => $playerId,
                'selected_answer' => $selectedAnswer,
                'correct_answer' => $correctAnswer,
                'is_correct' => $isCorrect,
                'timestamp' => time()
            ];

            // 更新資料庫
            $stmt = $pdo->prepare("UPDATE game_invitations SET game_state = ?, last_updated = NOW() WHERE invitation_id = ?");
            $stmt->execute([json_encode($gameState), $invitationId]);
            break;
            
        case 'get_game_state':
            $invitationId = $data['invitation_id'] ?? null;
            $playerId = $data['player_id'] ?? null;

            if (!$invitationId) {
                echo json_encode(['success' => false, 'message' => '邀請ID參數缺失']);
                exit;
            }

            error_log("獲取遊戲狀態 - 邀請ID: $invitationId, 玩家ID: " . ($playerId ?? '未提供'));

            // 根據是否有 player_id 參數決定查詢方式
            if ($playerId !== null) {
                // 算菜錢遊戲專用查詢
                $stmt = $pdo->prepare("SELECT game_state FROM game_invitations WHERE invitation_id = ? AND status = 'accepted'");
                $stmt->execute([$invitationId]);
                $invitation = $stmt->fetch();

                if (!$invitation) {
                    echo json_encode(['success' => false, 'message' => '邀請不存在或未接受']);
                    exit;
                }

                $gameState = json_decode($invitation['game_state'], true) ?: [];
                
                echo json_encode([
                    'success' => true,
                    'game_state' => $gameState
                ]);
            } else {
                // 通用查詢（翻牌遊戲等）
                $stmt = $pdo->prepare("SELECT game_state, game_end_state, status, last_updated, from_user_id FROM game_invitations WHERE invitation_id = ?");
                $stmt->execute([$invitationId]);
                $result = $stmt->fetch();
                
                if ($result) {
                    $gameState = json_decode($result['game_state'], true) ?: [];
                    $gameEndState = json_decode($result['game_end_state'], true) ?: [];
                    
                    error_log("遊戲狀態查詢結果 - 狀態: " . $result['status'] . ", 遊戲狀態: " . json_encode($gameState));
                    
                    if ($result['status'] === 'quit') {
                        echo json_encode([
                            'success' => true, 
                            'game_state' => $gameState,
                            'last_updated' => $result['last_updated'],
                            'is_game_end' => false,
                            'player_quit' => true,
                            'current_user_id' => $_SESSION['member_id'],
                            'from_user_id' => $result['from_user_id']
                        ]);
                        exit;
                    }
                    
                    if (!empty($gameEndState)) {
                        echo json_encode([
                            'success' => true, 
                            'game_state' => $gameEndState,
                            'last_updated' => $result['last_updated'],
                            'is_game_end' => true,
                            'current_user_id' => $_SESSION['member_id'],
                            'from_user_id' => $result['from_user_id']
                        ]);
                    } else {
                        echo json_encode([
                            'success' => true, 
                            'game_state' => $gameState,
                            'last_updated' => $result['last_updated'],
                            'is_game_end' => false,
                            'current_user_id' => $_SESSION['member_id'],
                            'from_user_id' => $result['from_user_id']
                        ]);
                    }
                } else {
                    error_log("找不到遊戲狀態 - 邀請ID: $invitationId");
                    echo json_encode(['success' => false, 'message' => '找不到遊戲狀態']);
                }
            }
            break;
            



            
        // --- 新增的動作：處理單張卡片翻開 ---
        case 'flip_card_immediate':
        case 'flip_card':
            $invitationId = $data['invitation_id'] ?? null;
            $playerId = $data['player_id'] ?? null;
            $cardIndex = $data['card_index'] ?? null;

            if (!$invitationId || $cardIndex === null || !$playerId) {
                error_log("flip_card 參數缺失 - 邀請ID: $invitationId, 卡片索引: $cardIndex, 玩家ID: $playerId");
                echo json_encode(['success' => false, 'message' => '參數缺失']);
                exit;
            }

            // 獲取當前遊戲狀態
            $stmt = $pdo->prepare("SELECT game_state, from_user_id FROM game_invitations WHERE invitation_id = ? AND status = 'accepted'");
            $stmt->execute([$invitationId]);
            $invitation = $stmt->fetch();

            if (!$invitation) {
                error_log("flip_card 邀請不存在或未接受 - 邀請ID: $invitationId");
                echo json_encode(['success' => false, 'message' => '邀請不存在或未接受']);
                exit;
            }

            $gameState = json_decode($invitation['game_state'], true) ?: [];

            // 驗證是否輪到當前玩家
            $isInviter = ($playerId == $invitation['from_user_id']);
            $currentPlayerNumber = $gameState['currentPlayer'] ?? 1; // 預設玩家1
            $isMyTurn = ($currentPlayerNumber === 1 && $isInviter) || ($currentPlayerNumber === 2 && !$isInviter);

            if (!$isMyTurn) {
                error_log("flip_card 不是你的回合 - 玩家ID: $playerId, 當前玩家: $currentPlayerNumber");
                echo json_encode(['success' => false, 'message' => '不是你的回合']);
                exit;
            }

            // 檢查卡片是否存在
            if (!isset($gameState['cards'][$cardIndex])) {
                error_log("flip_card 卡片不存在 - 索引: $cardIndex");
                echo json_encode(['success' => false, 'message' => '卡片不存在']);
                exit;
            }
            
            // 檢查卡片是否已經被配對（已配對的卡片不能再翻）
            if ($gameState['cards'][$cardIndex]['matched']) {
                error_log("flip_card 卡片已被配對 - 索引: $cardIndex");
                echo json_encode(['success' => false, 'message' => '卡片已被配對']);
                exit;
            }
            
            // 如果卡片已經被翻開，允許重複翻牌（可能是同步延遲）
            if ($gameState['cards'][$cardIndex]['flipped']) {
                error_log("flip_card 卡片已被翻開，但允許重複操作 - 索引: $cardIndex");
                // 不返回錯誤，繼續執行
            }

            // 更新卡片狀態
            $gameState['cards'][$cardIndex]['flipped'] = true;
            
            // 將翻開的卡片索引加入臨時列表，用於後續配對檢查
            // 注意：這裡假設前端會追蹤 flippedCards，並在第二次翻牌時發送 check_match_and_turn_switch
            // 伺服器端也可以維護這個列表，但會增加複雜度，目前先讓前端發送兩張牌的資訊
            
            // 更新 lastAction 資訊，讓對手知道發生了什麼
            $gameState['lastAction'] = 'flip_card';
            $gameState['lastActionBy'] = $playerId;
            $gameState['lastFlippedCardIndex'] = $cardIndex; // 記錄最後翻開的卡片

            // 將更新後的新狀態存回資料庫
            $stmt = $pdo->prepare("UPDATE game_invitations SET game_state = ?, last_updated = NOW() WHERE invitation_id = ?");
            $result = $stmt->execute([json_encode($gameState), $invitationId]);

            if ($result) {
                error_log("單張卡片翻開狀態更新成功 - 邀請ID: $invitationId, 卡片索引: $cardIndex");
                echo json_encode(['success' => true]);
            } else {
                error_log("單張卡片翻開狀態更新失敗 - 邀請ID: $invitationId, 卡片索引: $cardIndex");
                echo json_encode(['success' => false, 'message' => '更新卡片狀態失敗']);
            }
            break;

        // --- 新增的動作：檢查配對並切換回合 ---
        case 'check_match_and_turn_switch':
            $invitationId = $data['invitation_id'] ?? null;
            $playerId = $data['player_id'] ?? null;
            $flippedCardIndexes = $data['flipped_card_indexes'] ?? []; // 預期收到兩張牌的索引

            if (!$invitationId || count($flippedCardIndexes) !== 2 || !$playerId) {
                error_log("check_match_and_turn_switch 參數缺失或數量不對 - 邀請ID: $invitationId, 翻牌數: " . count($flippedCardIndexes));
                echo json_encode(['success' => false, 'message' => '參數缺失或翻牌數量不正確']);
                exit;
            }

            $card1Index = $flippedCardIndexes[0];
            $card2Index = $flippedCardIndexes[1];

            // 獲取當前遊戲狀態
            $stmt = $pdo->prepare("SELECT game_state, from_user_id FROM game_invitations WHERE invitation_id = ? AND status = 'accepted'");
            $stmt->execute([$invitationId]);
            $invitation = $stmt->fetch();

            if (!$invitation) {
                error_log("check_match_and_turn_switch 邀請不存在或未接受 - 邀請ID: $invitationId");
                echo json_encode(['success' => false, 'message' => '邀請不存在或未接受']);
                exit;
            }

            $gameState = json_decode($invitation['game_state'], true) ?: [];

            // 驗證是否輪到當前玩家 (再次檢查，確保安全)
            $isInviter = ($playerId == $invitation['from_user_id']);
            $currentPlayerNumber = $gameState['currentPlayer'] ?? 1;
            $isMyTurn = ($currentPlayerNumber === 1 && $isInviter) || ($currentPlayerNumber === 2 && !$isInviter);

            if (!$isMyTurn) {
                error_log("check_match_and_turn_switch 不是你的回合 - 玩家ID: $playerId, 當前玩家: $currentPlayerNumber");
                echo json_encode(['success' => false, 'message' => '不是你的回合']);
                exit;
            }

            // 確保卡片存在 (放寬檢查，允許卡片未翻開的情況)
            if (!isset($gameState['cards'][$card1Index]) || !isset($gameState['cards'][$card2Index])) {
                error_log("check_match_and_turn_switch 卡片不存在 - 索引1: $card1Index, 索引2: $card2Index");
                echo json_encode(['success' => false, 'message' => '卡片不存在']);
                exit;
            }
            
            // 如果卡片未翻開，先翻開它們
            if (!$gameState['cards'][$card1Index]['flipped']) {
                $gameState['cards'][$card1Index]['flipped'] = true;
            }
            if (!$gameState['cards'][$card2Index]['flipped']) {
                $gameState['cards'][$card2Index]['flipped'] = true;
            }

            $card1Symbol = $gameState['cards'][$card1Index]['symbol'];
            $card2Symbol = $gameState['cards'][$card2Index]['symbol'];
            $isMatch = ($card1Symbol === $card2Symbol);

            if ($isMatch) {
                // 配對成功
                $gameState['cards'][$card1Index]['matched'] = true;
                $gameState['cards'][$card2Index]['matched'] = true;
                $gameState['matchedPairs'] = ($gameState['matchedPairs'] ?? 0) + 1;

                if ($currentPlayerNumber === 1) {
                    $gameState['player1Score'] = ($gameState['player1Score'] ?? 0) + 10;
                    $gameState['player1Pairs'] = ($gameState['player1Pairs'] ?? 0) + 1;
                } else {
                    $gameState['player2Score'] = ($gameState['player2Score'] ?? 0) + 10;
                    $gameState['player2Pairs'] = ($gameState['player2Pairs'] ?? 0) + 1;
                }
                $gameState['consecutiveMatches'] = ($gameState['consecutiveMatches'] ?? 0) + 1;
                // 配對成功，回合不切換
            } else {
                // 配對失敗
                $gameState['cards'][$card1Index]['flipped'] = false; // 翻回背面
                $gameState['cards'][$card2Index]['flipped'] = false; // 翻回背面
                $gameState['consecutiveMatches'] = 0; // 連續配對歸零
                
                // 切換回合
                $gameState['currentPlayer'] = ($currentPlayerNumber === 1) ? 2 : 1;
            }

            // 更新 lastAction 資訊
            $gameState['lastAction'] = 'check_match_and_turn_switch';
            $gameState['lastActionBy'] = $playerId;
            $gameState['lastMatchResult'] = $isMatch;
            $gameState['lastFlippedCardIndexes'] = [$card1Index, $card2Index]; // 記錄這次操作的兩張卡片

            // 將更新後的新狀態存回資料庫
            $stmt = $pdo->prepare("UPDATE game_invitations SET game_state = ?, last_updated = NOW() WHERE invitation_id = ?");
            $result = $stmt->execute([json_encode($gameState), $invitationId]);

            if ($result) {
                error_log("配對檢查及回合切換成功 - 邀請ID: $invitationId, 是否配對: " . ($isMatch ? '是' : '否') . ", 下一回合玩家: " . $gameState['currentPlayer']);
                echo json_encode(['success' => true, 'is_match' => $isMatch, 'game_state' => $gameState]); // 回傳完整狀態給發起者
            } else {
                error_log("配對檢查及回合切換失敗 - 邀請ID: $invitationId");
                echo json_encode(['success' => false, 'message' => '更新遊戲狀態失敗']);
            }
            break;

        // --- 現有的 update_game_state 應該被淘汰或修改 ---
        // 建議移除此 case，或僅用於遊戲初始化等少數情況，不應用於頻繁的遊戲中操作
        case 'update_game_state':
            // 由於我們新增了更精細的 'flip_card' 和 'check_match_and_turn_switch'，
            // 這個 'update_game_state' 應該被重新考慮。
            // 如果它被用於遊戲中頻繁的狀態更新，會導致上述的競態條件問題。
            // 建議：將其改為僅用於遊戲初始化或特殊情況，或者完全移除。
            // 目前暫時保留，但請注意其潛在問題。
            $invitationId = $data['invitation_id'];
            $gameState = $data['game_state'];
            
            error_log("更新遊戲狀態 (通用) - 邀請ID: $invitationId, 遊戲狀態: " . json_encode($gameState));
            
            $stmt = $pdo->prepare("SELECT * FROM game_invitations WHERE invitation_id = ? AND status = 'accepted'");
            $stmt->execute([$invitationId]);
            $invitation = $stmt->fetch();
            
            if (!$invitation) {
                error_log("邀請不存在或未接受 - 邀請ID: $invitationId");
                echo json_encode(['success' => false, 'message' => '邀請不存在或未接受']);
                exit;
            }
            
            $stmt = $pdo->prepare("
                UPDATE game_invitations 
                SET game_state = ?, last_updated = NOW() 
                WHERE invitation_id = ?
            ");
            $result = $stmt->execute([json_encode($gameState), $invitationId]);
            
            if ($result) {
                error_log("遊戲狀態更新成功 (通用) - 邀請ID: $invitationId");
                echo json_encode(['success' => true]);
            } else {
                error_log("遊戲狀態更新失敗 (通用) - 邀請ID: $invitationId");
                echo json_encode(['success' => false, 'message' => '更新遊戲狀態失敗']);
            }
            break;
            
        // 移除重複的 get_game_state case
            
        case 'update_game_end':
            $invitationId = $data['invitation_id'];
            $playerId = $data['player_id'];
            $gameEndState = $data['game_end_state'];
            
            $stmt = $pdo->prepare("SELECT * FROM game_invitations WHERE invitation_id = ? AND status = 'accepted'");
            $stmt->execute([$invitationId]);
            $invitation = $stmt->fetch();
            
            if (!$invitation) {
                echo json_encode(['success' => false, 'message' => '邀請不存在或未接受']);
                exit;
            }
            
            $stmt = $pdo->prepare("
                UPDATE game_invitations 
                SET game_end_state = ?, last_updated = NOW() 
                WHERE invitation_id = ?
            ");
            $stmt->execute([json_encode($gameEndState), $invitationId]);
            
            echo json_encode(['success' => true, 'message' => '遊戲結束狀態已更新']);
            break;
            
        case 'player_quit':
            $invitationId = $data['invitation_id'];
            $playerId = $data['player_id'];
            
            $stmt = $pdo->prepare("SELECT * FROM game_invitations WHERE invitation_id = ? AND status = 'accepted'");
            $stmt->execute([$invitationId]);
            $invitation = $stmt->fetch();
            
            if (!$invitation) {
                echo json_encode(['success' => false, 'message' => '邀請不存在或未接受']);
                exit;
            }
            
            $stmt = $pdo->prepare("
                UPDATE game_invitations 
                SET status = 'quit', last_updated = NOW() 
                WHERE invitation_id = ?
            ");
            $stmt->execute([$invitationId]);
            
            echo json_encode(['success' => true, 'message' => '玩家已退出對戰']);
            break;
            
        // 移除重複的 test_connection case
            
        default:
            echo json_encode(['success' => false, 'message' => '未知操作']);
    }
} catch (Exception $e) {
    error_log("API錯誤: " . $e->getMessage() . " - 堆疊: " . $e->getTraceAsString());
    // 確保回應是有效的 JSON
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => '錯誤：' . $e->getMessage()]);
}
?>