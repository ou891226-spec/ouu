// 翻牌遊戲客戶端整合系統
class MemoryGameClient {
    constructor() {
        this.syncSystem = null;
        this.invitationId = null;
        this.playerId = null;
        this.gameState = null;
        this.isMyTurn = false;
        this.flippedCards = [];
        this.matchCheckTimeout = null;
        this.eventHandlers = {};
        
        // 初始化
        this.init();
    }

    // 初始化客戶端
    init() {
        console.log('翻牌遊戲客戶端初始化');
        
        // 獲取當前用戶ID
        this.playerId = this.getCurrentMemberId();
        
        // 檢查URL參數中的邀請ID
        const urlParams = new URLSearchParams(window.location.search);
        const invitationParam = urlParams.get('invitation');
        
        if (invitationParam) {
            this.invitationId = invitationParam;
            this.startSync();
        }
        
        // 設置事件監聽器
        this.setupEventListeners();
    }

    // 開始同步
    startSync() {
        if (!this.invitationId || !this.playerId) {
            console.error('缺少必要參數，無法開始同步');
            return;
        }

        console.log('開始遊戲同步:', { invitationId: this.invitationId, playerId: this.playerId });
        
        // 初始化增強同步系統
        if (window.enhancedMemoryGameSync) {
            this.syncSystem = window.enhancedMemoryGameSync;
            this.syncSystem.init(this.invitationId, this.playerId);
            
            // 設置事件處理器
            this.syncSystem.on('gameStateUpdate', (data) => {
                this.handleGameStateUpdate(data);
            });
            
            this.syncSystem.on('cardFlipped', (data) => {
                this.handleOpponentCardFlipped(data);
            });
            
            this.syncSystem.on('matchSuccess', (data) => {
                this.handleMatchSuccess(data);
            });
            
            this.syncSystem.on('matchFail', (data) => {
                this.handleMatchFail(data);
            });
        } else {
            console.error('增強同步系統未載入');
        }
    }

    // 設置事件監聽器
    setupEventListeners() {
        // 監聽卡片點擊
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('card') && 
                !e.target.classList.contains('flipped') && 
                !e.target.classList.contains('matched')) {
                
                const cardIndex = parseInt(e.target.dataset.index);
                const cardSymbol = e.target.dataset.symbol;
                
                this.handleCardClick(cardIndex, cardSymbol);
            }
        });

        // 監聽頁面可見性變化
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden && this.syncSystem) {
                console.log('頁面重新可見，強制同步');
                this.syncSystem.forceSync();
            }
        });

        // 監聽頁面離開
        window.addEventListener('beforeunload', (e) => {
            if (this.invitationId && this.gameState) {
                this.quitGame();
            }
        });
    }

    // 處理卡片點擊
    async handleCardClick(cardIndex, cardSymbol) {
        if (!this.isMyTurn) {
            console.log('不是我的回合，無法翻牌');
            return;
        }

        console.log('玩家點擊卡片:', cardIndex, cardSymbol);
        
        // 立即在本地顯示翻牌效果
        this.flipCardLocally(cardIndex, cardSymbol);
        
        // 添加到已翻開卡片列表
        this.flippedCards.push({ index: cardIndex, symbol: cardSymbol });
        
        // 發送到伺服器
        if (this.syncSystem) {
            const success = await this.syncSystem.sendFlipAction(cardIndex, cardSymbol);
            
            if (!success) {
                // 如果發送失敗，恢復卡片狀態
                this.unflipCardLocally(cardIndex);
                this.flippedCards = this.flippedCards.filter(card => card.index !== cardIndex);
                console.error('翻牌動作發送失敗');
                return;
            }
        }
        
        // 檢查是否需要配對檢查
        if (this.flippedCards.length === 2) {
            this.checkMatch();
        }
    }

    // 本地翻牌效果
    flipCardLocally(cardIndex, cardSymbol) {
        const card = document.querySelector(`[data-index="${cardIndex}"]`);
        if (card) {
            card.classList.add('flipped');
            card.textContent = cardSymbol;
            card.style.transform = 'rotateY(180deg)';
            
            // 播放翻牌音效
            this.playSound('flip');
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

    // 檢查配對
    async checkMatch() {
        if (this.flippedCards.length !== 2) {
            return;
        }

        const card1 = this.flippedCards[0];
        const card2 = this.flippedCards[1];
        
        console.log('檢查配對:', card1, card2);
        
        // 清除之前的配對檢查計時器
        if (this.matchCheckTimeout) {
            clearTimeout(this.matchCheckTimeout);
        }
        
        // 延遲檢查，讓玩家看到兩張卡片
        this.matchCheckTimeout = setTimeout(async () => {
            try {
                const response = await fetch('memory-game-match-api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'check_match',
                        invitation_id: this.invitationId,
                        player_id: this.playerId,
                        card1_index: card1.index,
                        card2_index: card2.index
                    })
                });

                const data = await response.json();
                
                if (data.success) {
                    if (data.is_match) {
                        this.handleMatchSuccess(data);
                    } else {
                        this.handleMatchFail(data);
                    }
                } else {
                    console.error('配對檢查失敗:', data.message);
                }
            } catch (error) {
                console.error('配對檢查錯誤:', error);
            }
            
            // 清空已翻開卡片列表
            this.flippedCards = [];
        }, 1000); // 1秒後檢查配對
    }

    // 處理配對成功
    handleMatchSuccess(data) {
        console.log('配對成功!');
        
        const cardIndexes = data.game_state.lastFlippedCardIndexes;
        
        // 標記卡片為已配對
        cardIndexes.forEach(index => {
            const card = document.querySelector(`[data-index="${index}"]`);
            if (card) {
                card.classList.add('matched');
                card.style.transform = 'rotateY(180deg)';
            }
        });
        
        // 播放配對成功音效
        this.playSound('match_success');
        
        // 更新遊戲狀態
        this.updateGameState(data.game_state);
        
        // 配對成功，保持回合
        this.isMyTurn = true;
        this.updateTurnIndicator();
        
        // 檢查遊戲是否結束
        if (data.game_ended) {
            this.handleGameEnd(data.winner);
        }
        
        // 清空已翻開卡片列表
        this.flippedCards = [];
    }

    // 處理配對失敗
    handleMatchFail(data) {
        console.log('配對失敗');
        
        const cardIndexes = data.game_state.lastFlippedCardIndexes;
        
        // 2秒後蓋回卡片
        setTimeout(() => {
            cardIndexes.forEach(index => {
                this.unflipCardLocally(index);
            });
        }, 2000);
        
        // 播放配對失敗音效
        this.playSound('match_fail');
        
        // 更新遊戲狀態
        this.updateGameState(data.game_state);
        
        // 配對失敗，切換回合
        this.isMyTurn = false;
        this.updateTurnIndicator();
        
        // 清空已翻開卡片列表
        this.flippedCards = [];
    }

    // 處理對手翻牌
    handleOpponentCardFlipped(data) {
        console.log('對手翻牌:', data.cardIndex);
        
        // 顯示對手的翻牌動作
        this.flipCardLocally(data.cardIndex, data.cardSymbol);
        
        // 播放翻牌音效
        this.playSound('flip');
    }

    // 處理遊戲狀態更新
    handleGameStateUpdate(data) {
        console.log('遊戲狀態更新:', data);
        
        this.gameState = data.gameState;
        this.isMyTurn = data.isMyTurn;
        
        // 更新UI
        this.updateGameState(data.gameState);
        this.updateTurnIndicator();
    }

    // 更新遊戲狀態
    updateGameState(gameState) {
        if (!gameState) return;
        
        // 更新分數顯示
        this.updateScoreDisplay(gameState);
        
        // 更新卡片狀態
        this.updateCardStates(gameState.cards);
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
    }

    // 更新卡片狀態
    updateCardStates(cards) {
        if (!cards) return;
        
        Object.keys(cards).forEach(index => {
            const card = cards[index];
            const cardElement = document.querySelector(`[data-index="${index}"]`);
            
            if (cardElement) {
                if (card.matched) {
                    cardElement.classList.add('matched');
                    cardElement.classList.add('flipped');
                    cardElement.textContent = card.symbol;
                    cardElement.style.transform = 'rotateY(180deg)';
                } else if (card.flipped) {
                    cardElement.classList.add('flipped');
                    cardElement.textContent = card.symbol;
                    cardElement.style.transform = 'rotateY(180deg)';
                } else {
                    cardElement.classList.remove('flipped');
                    cardElement.textContent = '';
                    cardElement.style.transform = 'rotateY(0deg)';
                }
            }
        });
    }

    // 更新回合指示器
    updateTurnIndicator() {
        const player1Indicator = document.getElementById('player1-indicator');
        const player2Indicator = document.getElementById('player2-indicator');
        
        if (player1Indicator && player2Indicator) {
            if (this.isMyTurn) {
                // 高亮當前玩家
                if (this.gameState && this.gameState.currentPlayer === 1) {
                    player1Indicator.style.display = 'block';
                    player2Indicator.style.display = 'none';
                } else {
                    player1Indicator.style.display = 'none';
                    player2Indicator.style.display = 'block';
                }
            } else {
                // 隱藏指示器
                player1Indicator.style.display = 'none';
                player2Indicator.style.display = 'none';
            }
        }
    }

    // 處理遊戲結束
    handleGameEnd(winner) {
        console.log('遊戲結束，獲勝者:', winner);
        
        // 顯示遊戲結束視窗
        this.showGameOverModal(winner);
        
        // 停止同步
        if (this.syncSystem) {
            this.syncSystem.stopPolling();
        }
    }

    // 顯示遊戲結束視窗
    showGameOverModal(winner) {
        const modal = document.getElementById('game-over-modal');
        if (modal) {
            const title = document.getElementById('game-over-title');
            const announcement = document.getElementById('winner-announcement');
            
            if (winner === 'tie') {
                title.textContent = '平局！';
                announcement.textContent = '兩位玩家表現相當，遊戲平局！';
            } else {
                title.textContent = '遊戲結束';
                announcement.textContent = `玩家 ${winner} 獲勝！`;
            }
            
            modal.classList.remove('hidden');
        }
    }

    // 退出遊戲
    async quitGame() {
        if (this.syncSystem) {
            await this.syncSystem.playerQuit();
        }
        
        // 清理資源
        this.cleanup();
    }

    // 清理資源
    cleanup() {
        if (this.matchCheckTimeout) {
            clearTimeout(this.matchCheckTimeout);
        }
        
        if (this.syncSystem) {
            this.syncSystem.destroy();
        }
        
        console.log('遊戲客戶端已清理');
    }

    // 播放音效
    playSound(soundType) {
        // 這裡可以添加音效播放邏輯
        console.log('播放音效:', soundType);
    }

    // 獲取當前用戶ID
    getCurrentMemberId() {
        // 從隱藏輸入欄位或meta標籤獲取
        const hiddenInput = document.querySelector('input[name="member_id"]');
        if (hiddenInput) {
            return hiddenInput.value;
        }
        
        const metaTag = document.querySelector('meta[name="member_id"]');
        if (metaTag) {
            return metaTag.content;
        }
        
        // 從localStorage獲取
        return localStorage.getItem('member_id');
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
}

// 全域遊戲客戶端實例
window.memoryGameClient = new MemoryGameClient();

// 導出供其他模組使用
window.MemoryGameClient = MemoryGameClient;
