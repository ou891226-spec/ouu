// 翻牌遊戲 WebSocket 客戶端管理
class MemoryGameWebSocketClient {
    constructor() {
        this.ws = null;
        this.isConnected = false;
        this.autoReconnect = true;
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 3;
        this.eventHandlers = {};
        this.myId = null;
        this.gameId = null;
    }

    connect() {
        // 在 Azure 環境中直接使用輪詢模式
        if (window.location.hostname.includes('azurewebsites.net')) {
            console.log('檢測到 Azure 環境，直接使用輪詢模式');
            this.switchToPollingMode();
            return;
        }

        try {
            // 使用正確的 WebSocket URL
            const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
            const host = window.location.hostname;
            
            // 在 Azure 環境中使用不同的端口
            let port = 8080;
            if (window.location.hostname.includes('azurewebsites.net')) {
                port = 80; // Azure 通常使用端口 80
            }
            
            const wsUrl = `${protocol}//${host}:${port}`;
            console.log('嘗試連接到 WebSocket 伺服器:', wsUrl);
            this.ws = new WebSocket(wsUrl);
            
            this.ws.onopen = () => {
                console.log('WebSocket 連接成功');
                this.isConnected = true;
                this.reconnectAttempts = 0;
                this.triggerEvent('connected', {});
            };

            this.ws.onmessage = (event) => {
                try {
                    const data = JSON.parse(event.data);
                    console.log('收到 WebSocket 訊息:', data);
                    this.handleMessage(data);
                } catch (error) {
                    console.error('解析 WebSocket 訊息失敗:', error);
                }
            };

            this.ws.onclose = (event) => {
                console.log('WebSocket 連接關閉:', event.code);
                this.isConnected = false;
                
                // 如果連接失敗或重連次數已達上限，立即切換到輪詢模式
                if (event.code === 1006 || this.reconnectAttempts >= this.maxReconnectAttempts) {
                    console.log('WebSocket 連接失敗，立即切換到輪詢模式');
                    this.switchToPollingMode();
                } else {
                    // 自動重連
                    if (this.autoReconnect && this.reconnectAttempts < this.maxReconnectAttempts) {
                        this.scheduleReconnect();
                    }
                }
            };

            this.ws.onerror = (error) => {
                console.error('WebSocket 錯誤:', error);
            };
        } catch (error) {
            console.error('WebSocket 連接失敗:', error);
            this.switchToPollingMode();
        }
    }

    handleMessage(data) {
        switch (data.type) {
            case 'player_join':
                this.triggerEvent('playerJoined', data);
                break;
            case 'game_start':
                this.triggerEvent('gameStarted', data);
                break;
            case 'your_turn':
                this.triggerEvent('turnChanged', data);
                break;
            case 'card_flipped':
                this.triggerEvent('cardFlipped', data);
                break;
            case 'match_success':
                this.triggerEvent('matchSuccess', data);
                break;
            case 'match_fail':
                this.triggerEvent('matchFail', data);
                break;
            case 'game_over':
                this.triggerEvent('gameOver', data);
                break;
            case 'difficulty_synced':
                this.triggerEvent('difficultySynced', data);
                break;
            case 'player_leave':
            case 'error':
            case 'reset':
                this.triggerEvent('gameReset', data);
                break;
            default:
                console.log('未知的 WebSocket 訊息類型:', data.type);
        }
    }

    // 簡化的發送方法
    send(type, data = {}) {
        if (this.isConnected && this.ws) {
            const message = { type, ...data };
            console.log('發送 WebSocket 訊息:', message);
            this.ws.send(JSON.stringify(message));
        } else {
            console.warn('WebSocket 未連接，無法發送訊息');
        }
    }

    // 翻牌
    flipCard(gameId, playerId, cardIndex) {
        this.send('flip_card', {
            game_id: gameId,
            player_id: playerId,
            index: cardIndex
        });
    }

    // 檢查配對
    checkMatch(gameId, playerId, cardIndices) {
        this.send('check_match', {
            game_id: gameId,
            player_id: playerId,
            indices: cardIndices
        });
    }

    // 同步困難度
    syncDifficulty(gameId, playerId, difficulty) {
        this.send('sync_difficulty', {
            game_id: gameId,
            player_id: playerId,
            difficulty: difficulty
        });
    }

    // 加入遊戲
    joinGame(gameId, playerId) {
        this.send('join_game', {
            game_id: gameId,
            player_id: playerId
        });
    }

    // 事件處理
    on(event, handler) {
        if (!this.eventHandlers[event]) {
            this.eventHandlers[event] = [];
        }
        this.eventHandlers[event].push(handler);
    }

    triggerEvent(event, data) {
        if (this.eventHandlers[event]) {
            this.eventHandlers[event].forEach(handler => {
                try {
                    handler(data);
                } catch (error) {
                    console.error(`事件處理器錯誤 (${event}):`, error);
                }
            });
        }
    }

    // 切換到輪詢模式
    switchToPollingMode() {
        console.log('切換到輪詢模式');
        this.isConnected = false;
        this.autoReconnect = false;
        this.triggerEvent('pollingMode', { message: 'WebSocket 不可用，使用輪詢模式' });
        // 多次嘗試啟動優化輪詢
        this.attemptStartOptimizedSync();
    }

    // 嘗試啟動優化輪詢
    attemptStartOptimizedSync() {
        let attempts = 0;
        const maxAttempts = 10;
        const tryStart = () => {
            attempts++;
            console.log(`嘗試啟動優化輪詢機制 (${attempts}/${maxAttempts})`);
            if (window.optimizedSync && window.optimizedSync.start) {
                console.log('啟動優化輪詢機制成功');
                window.optimizedSync.start();
                return;
            }
            if (attempts < maxAttempts) {
                console.log(`優化輪詢機制不可用，${attempts * 200}ms 後重試`);
                setTimeout(tryStart, attempts * 200);
            } else {
                console.error('優化輪詢機制不可用，啟動基本輪詢');
                this.startBasicPolling();
            }
        };
        tryStart();
    }

    // 基本輪詢作為備用
    startBasicPolling() {
        console.log('啟動基本輪詢機制');
        this.pollGameState();
        this.pollInvitationStatus();
    }

    pollGameState() {
        if (!window.invitationId) return;
        
        setInterval(() => {
            fetch('game-sync-api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'get_game_state',
                    invitation_id: window.invitationId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.triggerEvent('gameStateUpdate', data.game_state);
                }
            })
            .catch(error => console.error('輪詢遊戲狀態失敗:', error));
        }, 1000);
    }

    pollInvitationStatus() {
        if (!window.invitationId) return;
        
        setInterval(() => {
            fetch('game-invitation-api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'check_invitation_status',
                    invitation_id: window.invitationId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.status === 'accepted') {
                    this.triggerEvent('invitationStatusUpdate', data);
                }
            })
            .catch(error => console.error('輪詢邀請狀態失敗:', error));
        }, 2000);
    }

    scheduleReconnect() {
        this.reconnectAttempts++;
        const delay = Math.min(1000 * Math.pow(2, this.reconnectAttempts), 10000);
        console.log(`${delay}ms 後嘗試重連 (${this.reconnectAttempts}/${this.maxReconnectAttempts})`);
        setTimeout(() => this.connect(), delay);
    }

    disconnect() {
        this.autoReconnect = false;
        if (this.ws) {
            this.ws.close();
        }
    }
}

// 全域翻牌遊戲 WebSocket 客戶端實例
window.memoryGameWebSocket = new MemoryGameWebSocketClient();

// 自動連接
document.addEventListener('DOMContentLoaded', () => {
    // 延遲連接，確保頁面完全載入
    setTimeout(() => {
        window.memoryGameWebSocket.connect();
    }, 1000);
});

// 頁面卸載時斷開連接
window.addEventListener('beforeunload', () => {
    if (window.memoryGameWebSocket) {
        window.memoryGameWebSocket.disconnect();
    }
});
