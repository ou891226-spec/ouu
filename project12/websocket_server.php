<?php
require_once 'vendor/autoload.php';
require_once 'db_connect.php';

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class MemoryGameWebSocket implements MessageComponentInterface {
    protected $clients;
    protected $gameRooms;
    protected $userConnections;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
        $this->gameRooms = [];
        $this->userConnections = [];
        echo "翻牌遊戲 WebSocket 伺服器已啟動\n";
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "新連接: {$conn->resourceId}\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);
        echo "收到訊息: " . $msg . "\n";

        if (!$data) {
            return;
        }

        $action = $data['action'] ?? '';
        $userId = $data['user_id'] ?? null;
        $invitationId = $data['invitation_id'] ?? null;

        // 儲存用戶連接
        if ($userId) {
            $this->userConnections[$userId] = $from;
        }

        switch ($action) {
            case 'join_game':
                $this->handleJoinGame($from, $data);
                break;
            case 'sync_difficulty':
                $this->handleSyncDifficulty($from, $data);
                break;
            case 'flip_card':
                $this->handleFlipCard($from, $data);
                break;
            case 'check_match':
                $this->handleCheckMatch($from, $data);
                break;
            case 'turn_switch':
                $this->handleTurnSwitch($from, $data);
                break;
            case 'game_start':
                $this->handleGameStart($from, $data);
                break;
            case 'game_end':
                $this->handleGameEnd($from, $data);
                break;
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        echo "連接關閉: {$conn->resourceId}\n";

        // 清理用戶連接
        foreach ($this->userConnections as $userId => $connection) {
            if ($connection === $conn) {
                unset($this->userConnections[$userId]);
                break;
            }
        }
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "錯誤: {$e->getMessage()}\n";
        $conn->close();
    }

    protected function handleJoinGame($from, $data) {
        $invitationId = $data['invitation_id'];
        $userId = $data['user_id'];

        if (!isset($this->gameRooms[$invitationId])) {
            $this->gameRooms[$invitationId] = [];
        }

        $this->gameRooms[$invitationId][$userId] = $from;

        // 通知房間內的其他玩家
        $this->broadcastToRoom($invitationId, [
            'action' => 'player_joined',
            'user_id' => $userId,
            'invitation_id' => $invitationId
        ], $from);

        echo "玩家 {$userId} 加入翻牌遊戲房間 {$invitationId}\n";
    }

    protected function handleSyncDifficulty($from, $data) {
        $invitationId = $data['invitation_id'];
        $difficulty = $data['difficulty'];
        $userId = $data['user_id'];

        // 更新資料庫中的遊戲設定
        $this->updateGameSettings($invitationId, $difficulty, $userId);

        // 廣播給房間內的所有玩家
        $this->broadcastToRoom($invitationId, [
            'action' => 'difficulty_synced',
            'difficulty' => $difficulty,
            'user_id' => $userId,
            'invitation_id' => $invitationId
        ]);

        echo "困難度同步: {$difficulty} (邀請ID: {$invitationId})\n";
    }

    protected function handleFlipCard($from, $data) {
        $invitationId = $data['invitation_id'];
        $cardIndex = $data['card_index'];
        $userId = $data['user_id'];

        // 廣播給房間內的其他玩家
        $this->broadcastToRoom($invitationId, [
            'action' => 'card_flipped',
            'card_index' => $cardIndex,
            'user_id' => $userId,
            'invitation_id' => $invitationId
        ], $from);

        echo "卡片翻轉: 卡片 {$cardIndex} (用戶: {$userId})\n";
    }

    protected function handleCheckMatch($from, $data) {
        $invitationId = $data['invitation_id'];
        $card1Index = $data['card1_index'];
        $card2Index = $data['card2_index'];
        $isMatch = $data['is_match'];
        $userId = $data['user_id'];

        // 廣播給房間內的所有玩家
        $this->broadcastToRoom($invitationId, [
            'action' => 'match_result',
            'card1_index' => $card1Index,
            'card2_index' => $card2Index,
            'is_match' => $isMatch,
            'user_id' => $userId,
            'invitation_id' => $invitationId
        ]);

        echo "配對結果: " . ($isMatch ? '成功' : '失敗') . " (用戶: {$userId})\n";
    }

    protected function handleTurnSwitch($from, $data) {
        $invitationId = $data['invitation_id'];
        $nextPlayer = $data['next_player'];
        $userId = $data['user_id'];

        // 廣播給房間內的所有玩家
        $this->broadcastToRoom($invitationId, [
            'action' => 'turn_switched',
            'next_player' => $nextPlayer,
            'user_id' => $userId,
            'invitation_id' => $invitationId
        ]);

        echo "回合切換: 玩家 {$nextPlayer} (用戶: {$userId})\n";
    }

    protected function handleGameStart($from, $data) {
        $invitationId = $data['invitation_id'];
        $userId = $data['user_id'];

        // 廣播給房間內的所有玩家
        $this->broadcastToRoom($invitationId, [
            'action' => 'game_started',
            'user_id' => $userId,
            'invitation_id' => $invitationId
        ]);

        echo "翻牌遊戲開始: 邀請ID {$invitationId}\n";
    }

    protected function handleGameEnd($from, $data) {
        $invitationId = $data['invitation_id'];
        $winner = $data['winner'];
        $userId = $data['user_id'];

        // 廣播給房間內的所有玩家
        $this->broadcastToRoom($invitationId, [
            'action' => 'game_ended',
            'winner' => $winner,
            'user_id' => $userId,
            'invitation_id' => $invitationId
        ]);

        // 清理房間
        unset($this->gameRooms[$invitationId]);

        echo "翻牌遊戲結束: 獲勝者 {$winner} (邀請ID: {$invitationId})\n";
    }

    protected function broadcastToRoom($invitationId, $message, $exclude = null) {
        if (!isset($this->gameRooms[$invitationId])) {
            return;
        }

        $messageJson = json_encode($message);
        foreach ($this->gameRooms[$invitationId] as $userId => $connection) {
            if ($connection !== $exclude) {
                $connection->send($messageJson);
            }
        }
    }

    protected function updateGameSettings($invitationId, $difficulty, $userId) {
        global $conn;
        
        try {
            $gameSettings = json_encode([
                'difficulty' => $difficulty,
                'updated_by' => $userId,
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            $stmt = $conn->prepare("UPDATE game_invitations SET game_settings = ?, last_updated = NOW() WHERE invitation_id = ?");
            $stmt->bind_param("ss", $gameSettings, $invitationId);
            $stmt->execute();
            
            echo "遊戲設定已更新: 困難度 {$difficulty}\n";
        } catch (Exception $e) {
            echo "更新遊戲設定失敗: " . $e->getMessage() . "\n";
        }
    }
}

// 啟動 WebSocket 伺服器
$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new MemoryGameWebSocket()
        )
    ),
    8080
);

echo "翻牌遊戲 WebSocket 伺服器正在監聽端口 8080...\n";
$server->run();
?>
