// 翻牌遊戲 HTTP 同步客戶端
class MemoryGameHttpSync {
    constructor() {
        this.syncInterval = null;
        this.lastSyncTime = 0;
        this.syncDebounceDelay = 300; // 300ms 防抖延遲
        this.pollingInterval = 1000; // 1秒輪詢間隔
        this.isPolling = false;
        this.lastGameState = null;
        this.invitationId = null;
        this.playerId = null;
        this.eventHandlers = {};
    }

    // 初始化同步
    init(invitationId, playerId) {
        this.invitationId = invitationId;
        this.playerId = playerId;
        console.log('HTTP 同步初始化:', { invitationId, playerId });
        
        // 開始輪詢
        this.startPolling();
    }

    // 開始輪詢
    startPolling() {
        if (this.isPolling) {
            console.log('輪詢已啟動，跳過重複啟動');
            return;
        }

        this.isPolling = true;
        console.log('開始 HTTP 輪詢同步');

        this.syncInterval = setInterval(() => {
            this.pollGameState();
        }, this.pollingInterval);
    }

    // 停止輪詢
    stopPolling() {
        if (this.syncInterval) {
            clearInterval(this.syncInterval);
            this.syncInterval = null;
        }
        this.isPolling = false;
        console.log('停止 HTTP 輪詢同步');
    }

    // 輪詢遊戲狀態
    async pollGameState() {
        if (!this.invitationId || !this.playerId) {
            console.warn('缺少必要參數，無法輪詢遊戲狀態');
            return;
        }

        try {
            const response = await fetch('memory-game-sync-api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'get_game_state',
                    invitation_id: this.invitationId,
                    player_id: this.playerId
                })
            });

            const data = await response.json();
            
            if (data.success) {
                this.handleGameStateUpdate(data);
            } else {
                console.error('輪詢遊戲狀態失敗:', data.message);
            }
        } catch (error) {
            console.error('輪詢遊戲狀態錯誤:', error);
        }
    }

    // 處理遊戲狀態更新
    handleGameStateUpdate(data) {
        const gameState = data.game_state;
        const lastAction = gameState.lastAction;
        const lastActionBy = gameState.lastActionBy;
        const lastActionTime = gameState.lastActionTime;

        // 檢查是否有新的動作
        if (lastActionTime > this.lastSyncTime && lastActionBy !== this.playerId) {
            console.log('檢測到新動作:', { lastAction, lastActionBy, lastActionTime });
            
            switch (lastAction) {
                case 'flip_card':
                    this.handleCardFlipped(gameState);
                    break;
                case 'check_match':
                    this.handleMatchResult(gameState);
                    break;
                case 'init_game':
                    this.handleGameInitialized(gameState);
                    break;
            }
            
            this.lastSyncTime = lastActionTime;
        }

        // 更新本地遊戲狀態
        this.lastGameState = gameState;
        
        // 觸發狀態更新事件
        this.triggerEvent('gameStateUpdate', {
            gameState: gameState,
            isMyTurn: data.is_my_turn,
            currentPlayer: data.current_player
        });
    }

    // 處理卡片翻轉
    handleCardFlipped(gameState) {
        const lastFlippedIndex = gameState.lastFlippedCardIndex;
        if (lastFlippedIndex !== undefined) {
            console.log('對手翻牌:', lastFlippedIndex);
            this.triggerEvent('cardFlipped', {
                cardIndex: lastFlippedIndex,
                cardSymbol: gameState.cards[lastFlippedIndex].symbol
            });
        }
    }

    // 處理配對結果
    handleMatchResult(gameState) {
        const isMatch = gameState.lastMatchResult;
        const cardIndexes = gameState.lastFlippedCardIndexes;
        
        console.log('配對結果:', { isMatch, cardIndexes });
        
        if (isMatch) {
            // 配對成功
            this.triggerEvent('matchSuccess', {
                cardIndexes: cardIndexes,
                gameState: gameState
            });
        } else {
            // 配對失敗
            this.triggerEvent('matchFail', {
                cardIndexes: cardIndexes,
                gameState: gameState
            });
        }
    }

    // 處理遊戲初始化
    handleGameInitialized(gameState) {
        console.log('遊戲已初始化');
        this.triggerEvent('gameInitialized', { gameState: gameState });
    }

    // 發送翻牌動作
    async flipCard(cardIndex, cardSymbol) {
        if (!this.invitationId || !this.playerId) {
            console.error('缺少必要參數，無法發送翻牌動作');
            return false;
        }

        try {
            const response = await fetch('memory-game-sync-api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'flip_card',
                    invitation_id: this.invitationId,
                    player_id: this.playerId,
                    card_index: cardIndex,
                    card_symbol: cardSymbol
                })
            });

            const data = await response.json();
            
            if (data.success) {
                console.log('翻牌成功:', cardIndex);
                this.lastGameState = data.game_state;
                return true;
            } else {
                console.error('翻牌失敗:', data.message);
                return false;
            }
        } catch (error) {
            console.error('翻牌請求錯誤:', error);
            return false;
        }
    }

    // 發送配對檢查
    async checkMatch(card1Index, card2Index) {
        if (!this.invitationId || !this.playerId) {
            console.error('缺少必要參數，無法發送配對檢查');
            return false;
        }

        try {
            const response = await fetch('memory-game-sync-api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'check_match',
                    invitation_id: this.invitationId,
                    player_id: this.playerId,
                    card1_index: card1Index,
                    card2_index: card2Index
                })
            });

            const data = await response.json();
            
            if (data.success) {
                console.log('配對檢查成功:', data.is_match);
                this.lastGameState = data.game_state;
                return data.is_match;
            } else {
                console.error('配對檢查失敗:', data.message);
                return false;
            }
        } catch (error) {
            console.error('配對檢查請求錯誤:', error);
            return false;
        }
    }

    // 初始化遊戲
    async initGame(difficulty = 'easy', theme = 'fruit') {
        if (!this.invitationId || !this.playerId) {
            console.error('缺少必要參數，無法初始化遊戲');
            return false;
        }

        try {
            const response = await fetch('memory-game-sync-api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'init_game',
                    invitation_id: this.invitationId,
                    player_id: this.playerId,
                    difficulty: difficulty,
                    theme: theme
                })
            });

            const data = await response.json();
            
            if (data.success) {
                console.log('遊戲初始化成功');
                this.lastGameState = data.game_state;
                return data.game_state;
            } else {
                console.error('遊戲初始化失敗:', data.message);
                return false;
            }
        } catch (error) {
            console.error('遊戲初始化請求錯誤:', error);
            return false;
        }
    }

    // 玩家退出
    async playerQuit() {
        if (!this.invitationId || !this.playerId) {
            console.error('缺少必要參數，無法退出遊戲');
            return false;
        }

        try {
            const response = await fetch('memory-game-sync-api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'player_quit',
                    invitation_id: this.invitationId,
                    player_id: this.playerId
                })
            });

            const data = await response.json();
            
            if (data.success) {
                console.log('玩家退出成功');
                this.stopPolling();
                return true;
            } else {
                console.error('玩家退出失敗:', data.message);
                return false;
            }
        } catch (error) {
            console.error('玩家退出請求錯誤:', error);
            return false;
        }
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

    // 獲取當前遊戲狀態
    getCurrentGameState() {
        return this.lastGameState;
    }

    // 檢查是否輪到我的回合
    isMyTurn() {
        if (!this.lastGameState) return false;
        
        const currentPlayer = this.lastGameState.currentPlayer;
        const isInviter = this.playerId == window.invitationData?.from_user_id;
        
        return (currentPlayer === 1 && isInviter) || (currentPlayer === 2 && !isInviter);
    }
}

// 全域 HTTP 同步實例
window.memoryGameHttpSync = new MemoryGameHttpSync();

