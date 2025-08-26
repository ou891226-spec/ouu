// 增強版翻牌遊戲同步系統
class EnhancedMemoryGameSync {
    constructor() {
        this.syncInterval = null;
        this.lastSyncTime = 0;
        this.syncDebounceDelay = 300;
        this.pollingInterval = 1000;
        this.isPolling = false;
        this.lastGameState = null;
        this.invitationId = null;
        this.playerId = null;
        this.eventHandlers = {};
        this.pendingActions = [];
        this.isProcessingAction = false;
        this.retryCount = 0;
        this.maxRetries = 3;
    }

    // 初始化同步
    init(invitationId, playerId) {
        this.invitationId = invitationId;
        this.playerId = playerId;
        console.log('增強同步系統初始化:', { invitationId, playerId });
        
        // 開始輪詢
        this.startPolling();
        
        // 設置事件監聽器
        this.setupEventListeners();
    }

    // 設置事件監聽器
    setupEventListeners() {
        // 監聽卡片點擊事件
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('card') && !e.target.classList.contains('flipped') && !e.target.classList.contains('matched')) {
                const cardIndex = parseInt(e.target.dataset.index);
                const cardSymbol = e.target.dataset.symbol;
                
                // 檢查是否輪到我的回合
                if (this.isMyTurn()) {
                    this.handleCardClick(cardIndex, cardSymbol);
                } else {
                    console.log('不是我的回合，無法翻牌');
                }
            }
        });

        // 監聽頁面可見性變化
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                console.log('📱 頁面重新可見，立即同步遊戲狀態');
                this.forceSync();
            }
        });
    }

    // 處理卡片點擊
    async handleCardClick(cardIndex, cardSymbol) {
        console.log('玩家點擊卡片:', cardIndex, cardSymbol);
        
        // 立即在本地顯示翻牌效果
        this.flipCardLocally(cardIndex, cardSymbol);
        
        // 發送到伺服器
        const success = await this.sendFlipAction(cardIndex, cardSymbol);
        
        if (!success) {
            // 如果發送失敗，恢復卡片狀態
            this.unflipCardLocally(cardIndex);
            console.error('翻牌動作發送失敗');
        }
    }

    // 本地翻牌效果
    flipCardLocally(cardIndex, cardSymbol) {
        const card = document.querySelector(`[data-index="${cardIndex}"]`);
        if (card) {
            card.classList.add('flipped');
            card.textContent = cardSymbol;
            card.style.transform = 'rotateY(180deg)';
        }
    }

    // 本地蓋回卡片
    unflipCardLocally(cardIndex) {
        const card = document.querySelector(`[data-index="${cardIndex}"]`);
        if (card) {
            card.classList.remove('flipped');
            card.textContent = '';
            card.style.transform = 'rotateY(0deg)';
        }
    }

    // 發送翻牌動作
    async sendFlipAction(cardIndex, cardSymbol) {
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
                console.log('翻牌動作發送成功:', cardIndex);
                this.lastGameState = data.game_state;
                this.retryCount = 0;
                return true;
            } else {
                console.error('翻牌動作發送失敗:', data.message);
                return false;
            }
        } catch (error) {
            console.error('翻牌動作請求錯誤:', error);
            return false;
        }
    }

    // 開始輪詢
    startPolling() {
        if (this.isPolling) {
            console.log('輪詢已啟動，跳過重複啟動');
            return;
        }

        this.isPolling = true;
        console.log('開始增強輪詢同步');

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
        console.log('停止增強輪詢同步');
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

        // 檢查是否有新的動作（且不是自己發起的）
        if (lastActionTime > this.lastSyncTime && lastActionBy !== this.playerId) {
            console.log('檢測到對手新動作:', { lastAction, lastActionBy, lastActionTime });
            
            switch (lastAction) {
                case 'flip_card':
                    this.handleOpponentCardFlipped(gameState);
                    break;
                case 'check_match':
                    this.handleOpponentMatchResult(gameState);
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

    // 處理對手翻牌
    handleOpponentCardFlipped(gameState) {
        const lastFlippedIndex = gameState.lastFlippedCardIndex;
        if (lastFlippedIndex !== undefined) {
            console.log('對手翻牌:', lastFlippedIndex);
            
            // 顯示對手的翻牌動作
            const card = document.querySelector(`[data-index="${lastFlippedIndex}"]`);
            if (card && !card.classList.contains('flipped')) {
                const cardSymbol = gameState.cards[lastFlippedIndex].symbol;
                
                // 添加翻牌動畫
                card.classList.add('flipped');
                card.textContent = cardSymbol;
                card.style.transform = 'rotateY(180deg)';
                
                // 播放翻牌音效（如果有的話）
                this.playFlipSound();
            }
            
            this.triggerEvent('cardFlipped', {
                cardIndex: lastFlippedIndex,
                cardSymbol: gameState.cards[lastFlippedIndex].symbol
            });
        }
    }

    // 處理對手配對結果
    handleOpponentMatchResult(gameState) {
        const isMatch = gameState.lastMatchResult;
        const cardIndexes = gameState.lastFlippedCardIndexes;
        
        console.log('對手配對結果:', { isMatch, cardIndexes });
        
        if (isMatch) {
            // 配對成功
            cardIndexes.forEach(index => {
                const card = document.querySelector(`[data-index="${index}"]`);
                if (card) {
                    card.classList.add('matched');
                    card.style.transform = 'rotateY(180deg)';
                }
            });
            
            // 播放配對成功音效
            this.playMatchSound(true);
            
            this.triggerEvent('matchSuccess', {
                cardIndexes: cardIndexes,
                gameState: gameState
            });
        } else {
            // 配對失敗，2秒後蓋回卡片
            setTimeout(() => {
                cardIndexes.forEach(index => {
                    const card = document.querySelector(`[data-index="${index}"]`);
                    if (card) {
                        card.classList.remove('flipped');
                        card.textContent = '';
                        card.style.transform = 'rotateY(0deg)';
                    }
                });
            }, 2000);
            
            // 播放配對失敗音效
            this.playMatchSound(false);
            
            this.triggerEvent('matchFail', {
                cardIndexes: cardIndexes,
                gameState: gameState
            });
        }
        
        // 更新分數顯示
        this.updateScoreDisplay(gameState);
    }

    // 處理遊戲初始化
    handleGameInitialized(gameState) {
        console.log('🎮 遊戲已初始化');
        this.triggerEvent('gameInitialized', { gameState: gameState });
    }

    // 更新分數顯示
    updateScoreDisplay(gameState) {
        // 更新玩家1分數
        const player1ScoreElement = document.getElementById('player1-score');
        if (player1ScoreElement) {
            player1ScoreElement.textContent = gameState.player1Score || 0;
        }
        
        const player1PairsElement = document.getElementById('player1-pairs');
        if (player1PairsElement) {
            player1PairsElement.textContent = gameState.player1Pairs || 0;
        }
        
        // 更新玩家2分數
        const player2ScoreElement = document.getElementById('player2-score');
        if (player2ScoreElement) {
            player2ScoreElement.textContent = gameState.player2Score || 0;
        }
        
        const player2PairsElement = document.getElementById('player2-pairs');
        if (player2PairsElement) {
            player2PairsElement.textContent = gameState.player2Pairs || 0;
        }
        
        // 更新總配對次數
        const totalMovesElement = document.getElementById('total-moves');
        if (totalMovesElement) {
            totalMovesElement.textContent = gameState.matchedPairs || 0;
        }
        
        // 更新當前玩家指示器
        this.updateCurrentPlayerIndicator(gameState.currentPlayer);
    }

    // 更新當前玩家指示器
    updateCurrentPlayerIndicator(currentPlayer) {
        const player1Indicator = document.getElementById('player1-indicator');
        const player2Indicator = document.getElementById('player2-indicator');
        
        if (player1Indicator && player2Indicator) {
            if (currentPlayer === 1) {
                player1Indicator.style.display = 'block';
                player2Indicator.style.display = 'none';
            } else {
                player1Indicator.style.display = 'none';
                player2Indicator.style.display = 'block';
            }
        }
    }

    // 播放翻牌音效
    playFlipSound() {
        // 這裡可以添加翻牌音效
        console.log('🔊 播放翻牌音效');
    }

    // 播放配對音效
    playMatchSound(isSuccess) {
        if (isSuccess) {
            console.log('播放配對成功音效');
        } else {
            console.log('播放配對失敗音效');
        }
    }

    // 強制同步
    forceSync() {
        console.log('強制同步遊戲狀態');
        this.pollGameState();
    }

    // 檢查是否輪到我的回合
    isMyTurn() {
        if (!this.lastGameState) return false;
        
        const currentPlayer = this.lastGameState.currentPlayer;
        const isInviter = this.playerId == window.invitationData?.from_user_id;
        
        return (currentPlayer === 1 && isInviter) || (currentPlayer === 2 && !isInviter);
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

    // 清理資源
    destroy() {
        this.stopPolling();
        this.eventHandlers = {};
        console.log('增強同步系統已清理');
    }
}

// 全域增強同步實例
window.enhancedMemoryGameSync = new EnhancedMemoryGameSync();

// 導出供其他模組使用
window.EnhancedMemoryGameSync = EnhancedMemoryGameSync;
