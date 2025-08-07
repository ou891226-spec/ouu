// 簡化的同步優化
// 這個檔案包含基本的同步改進，可以安全地整合到現有遊戲中

// 優化同步頻率
function optimizeGameSync() {
    if (gameMode !== 'online' || !invitationId) return;
    
    console.log('開始優化遊戲同步');
    
    // 提高同步頻率到500ms
    if (gameSyncInterval) {
        clearInterval(gameSyncInterval);
    }
    
    gameSyncInterval = setInterval(() => {
        fetch('game-sync-api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            credentials: 'include',
            body: JSON.stringify({
                action: 'get_game_state',
                invitation_id: invitationId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.game_state) {
                // 簡化的同步邏輯
                updateGameFromSyncSimple(data.game_state);
            }
        })
        .catch(error => {
            console.error('同步錯誤:', error);
        });
    }, 500); // 500ms 同步頻率
}

// 簡化的同步更新函數
function updateGameFromSyncSimple(gameState) {
    console.log('簡化同步更新:', gameState.lastAction);
    
    // 檢查是否是我的動作
    const isMyAction = gameState.lastActionBy === getCurrentMemberId();
    if (isMyAction) {
        console.log('跳過自己的動作同步');
        return;
    }
    
    // 同步卡片狀態
    if (gameState.cards && Array.isArray(gameState.cards)) {
        gameState.cards.forEach((cardData, index) => {
            const card = cards[index];
            if (card) {
                // 同步翻牌狀態
                const shouldBeFlipped = cardData.flipped;
                const isCurrentlyFlipped = card.classList.contains('flipped');
                
                if (shouldBeFlipped && !isCurrentlyFlipped) {
                    card.classList.add('flipped');
                    console.log('同步翻開卡片:', index);
                    if (!flippedCards.includes(card)) {
                        flippedCards.push(card);
                    }
                } else if (!shouldBeFlipped && isCurrentlyFlipped) {
                    card.classList.remove('flipped');
                    console.log('同步蓋回卡片:', index);
                    const cardIndex = flippedCards.indexOf(card);
                    if (cardIndex > -1) {
                        flippedCards.splice(cardIndex, 1);
                    }
                }
                
                // 同步配對狀態
                const shouldBeMatched = cardData.matched;
                const isCurrentlyMatched = card.classList.contains('matched');
                
                if (shouldBeMatched && !isCurrentlyMatched) {
                    card.classList.add('matched');
                    console.log('同步配對卡片:', index);
                }
            }
        });
    }
    
    // 同步遊戲數據
    if (gameState.player1Score !== undefined) player1Score = gameState.player1Score;
    if (gameState.player2Score !== undefined) player2Score = gameState.player2Score;
    if (gameState.player1Pairs !== undefined) player1Pairs = gameState.player1Pairs;
    if (gameState.player2Pairs !== undefined) player2Pairs = gameState.player2Pairs;
    if (gameState.matchedPairs !== undefined) matchedPairs = gameState.matchedPairs;
    if (gameState.currentPlayer !== undefined) currentPlayer = gameState.currentPlayer;
    
    // 更新顯示
    updatePlayerDisplay();
    console.log('簡化同步完成');
}

// 優化翻牌同步
function optimizeFlipSync() {
    // 在翻牌時立即同步
    const originalFlipCard = flipCard;
    flipCard = function(card) {
        originalFlipCard.call(this, card);
        
        // 立即同步翻牌動作
        if (gameMode === 'online' && invitationId) {
            setTimeout(() => {
                syncGameState('flip_card');
            }, 100);
        }
    };
}

// 初始化優化
function initSyncOptimization() {
    console.log('初始化同步優化');
    optimizeGameSync();
    optimizeFlipSync();
}

// 導出函數供外部使用
window.initSyncOptimization = initSyncOptimization;
window.optimizeGameSync = optimizeGameSync;
window.updateGameFromSyncSimple = updateGameFromSyncSimple; 