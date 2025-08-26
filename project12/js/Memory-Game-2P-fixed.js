// 雙人模式遊戲變數
let cards = [];
let flippedCards = [];
let matchedPairs = 0;
let totalMoves = 0;
let timeLeft = 60;
let gameTimer;
let canFlip = true;
let currentDifficulty = 'easy';
let currentTheme = 'fruit';
let gridSize = 4;
let gamePaused = false;
let gameStartTimestamp = null;
let gameEndTimestamp = null;

// 雙人模式專用變數
let currentPlayer = 1; // 1 或 2
let player1Score = 0;
let player2Score = 0;
let player1Pairs = 0;
let player2Pairs = 0;
let player1Name = '玩家 1';
let player2Name = '玩家 2';
let consecutiveMatches = 0; // 連續配對次數

// WebSocket 相關變數
let currentInvitation = null;
let isInviter = false;
let roomId = null;
let wsClient = null;

// 從 URL 參數獲取邀請資訊
function getUrlParams() {
    const urlParams = new URLSearchParams(window.location.search);
    return {
        invitationId: urlParams.get('invitation_id'),
        roomId: urlParams.get('room_id')
    };
}

// 初始化 WebSocket 連接
function initializeWebSocket() {
    const userId = localStorage.getItem('member_id') || '1';
    
    // 檢查是否已經有 WebSocket 連接
    if (typeof window.gameWsClient !== 'undefined' && window.gameWsClient !== null) {
        wsClient = window.gameWsClient;
    } else {
        wsClient = new WebSocketClient();
        window.gameWsClient = wsClient;
    }
    
    wsClient.connect(userId).then(() => {
        console.log('WebSocket 連接成功');
        
        // 註冊事件處理器
        wsClient.on('invitation_created', handleInvitationCreated);
        wsClient.on('invitation_received', handleInvitationReceived);
        wsClient.on('invitation_accepted', handleInvitationAccepted);
        wsClient.on('invitation_rejected', handleInvitationRejected);
        wsClient.on('invitation_expired', handleInvitationExpired);
        wsClient.on('game_settings', handleGameSettings);
        wsClient.on('game_start', handleGameStart);
        wsClient.on('game_state_sync', handleGameStateSync);
        wsClient.on('player_disconnected', handlePlayerDisconnected);
        
        // 檢查是否有邀請參數
        const params = getUrlParams();
        if (params.invitationId && params.roomId) {
            // 被邀請者：接受邀請
            roomId = params.roomId;
            isInviter = false;
            
            // 發送邀請接收訊息
            wsClient.send({
                type: 'invitation_received',
                data: {
                    invitation_id: params.invitationId
                }
            });
        } else {
            // 邀請者：顯示邀請畫面
            showInvitationScreen();
        }
        
    }).catch(error => {
        console.error('WebSocket 連接失敗:', error);
        showNotification('無法連接到遊戲伺服器', 'error');
    });
}

// 事件處理器
function handleInvitationCreated(data) {
    console.log('邀請已創建:', data);
    currentInvitation = data;
    
    // 主邀請人：直接顯示等待畫面
    showWaitingModal();
}

function handleInvitationReceived(data) {
    console.log('收到邀請:', data);
    showInvitationPopup(data.invitation_id, data.inviter_name);
}

function handleInvitationAccepted(data) {
    console.log('邀請已接受:', data);
    roomId = data.room_id;
    isInviter = false;
    showWaitingModal();
}

function handleInvitationRejected(data) {
    console.log('邀請被拒絕:', data);
    showNotification('邀請被拒絕', 'error');
    showInvitationScreen();
}

function handleInvitationExpired(data) {
    console.log('邀請已過期:', data);
    showNotification('邀請已過期', 'error');
    showInvitationScreen();
}

function handleGameSettings(data) {
    console.log('收到遊戲設定:', data);
    startGameWithSettings(data);
}

function handleGameStart(data) {
    console.log('遊戲開始:', data);
    startGame();
}

function handleGameStateSync(data) {
    console.log('遊戲狀態同步:', data);
    // 同步遊戲狀態
    updateGameState(data);
}

function handlePlayerDisconnected(data) {
    console.log('玩家斷線:', data);
    showNotification('對手已斷線', 'error');
    endGame();
}

// 顯示邀請畫面
function showInvitationScreen() {
    document.getElementById('invitation-container').style.display = 'block';
    document.getElementById('waiting-container').style.display = 'none';
    document.getElementById('game-container').style.display = 'none';
    // 隱藏所有彈窗
    document.getElementById('player-setup-modal').classList.add('hidden');
    document.getElementById('theme-modal').classList.add('hidden');
    document.getElementById('difficulty-modal').classList.add('hidden');
    document.getElementById('help-modal').classList.add('hidden');
}

// 顯示說明視窗
function showHelp() {
    console.log('showHelp 函數被調用');
    const helpModal = document.getElementById('help-modal');
    if (helpModal) {
        helpModal.classList.remove('hidden');
        console.log('說明視窗已顯示');
    } else {
        console.error('找不到 help-modal 元素');
    }
}

// 關閉說明視窗
function closeHelpModal() {
    document.getElementById('help-modal').classList.add('hidden');
}

// 邀請好友函數
function inviteFriend(friendId, friendName) {
    console.log('inviteFriend 函數被調用:', friendId, friendName);
    const inviterName = localStorage.getItem('user_name') || '玩家';
    
    // 檢查 WebSocket 是否已連接
    if (!wsClient || !wsClient.isConnected) {
        console.log('WebSocket 未連接，嘗試重新連接');
        showNotification('正在連接伺服器，請稍候...', 'warning');
        // 嘗試重新連接
        initializeWebSocket();
        return;
    }
    
    console.log('WebSocket 已連接，發送邀請');
    
    // 發送邀請訊息
    wsClient.send({
        type: 'create_invitation',
        data: {
            inviter_id: localStorage.getItem('member_id') || '1',
            inviter_name: inviterName,
            invitee_id: friendId,
            invitee_name: friendName,
            game_type: 'memory'
        }
    });
    
    // 顯示通知
    showNotification('邀請已發送', 'success');
    
    // 顯示等待畫面
    showWaitingModal();
}

// 顯示通知函數
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.textContent = message;
    notification.style.backgroundColor = type === 'success' ? '#4CAF50' : 
                                       type === 'error' ? '#f44336' : 
                                       type === 'warning' ? '#ff9800' : '#2196F3';
    
    document.body.appendChild(notification);
    
    // 3秒後自動移除
    setTimeout(() => {
        notification.remove();
    }, 3000);
}

// 顯示邀請彈窗
function showInvitationPopup(invitationId, inviterName = '玩家') {
    // 移除已存在的邀請彈窗
    const existingPopup = document.querySelector('.invitation-popup');
    if (existingPopup) {
        existingPopup.remove();
    }
    
    const popup = document.createElement('div');
    popup.className = 'invitation-popup';
    popup.innerHTML = `
        <div class="invitation-popup-content">
            <h3>收到遊戲邀請</h3>
            <p>${inviterName} 邀請你進行翻牌對對樂</p>
            <div class="invitation-buttons">
                <button onclick="acceptInvitation('${invitationId}')" class="accept-btn">接受邀請</button>
                <button onclick="rejectInvitation('${invitationId}')" class="reject-btn">拒絕邀請</button>
            </div>
        </div>
    `;
    document.body.appendChild(popup);
}

// 接受邀請
function acceptInvitation(invitationId) {
    wsClient.send({
        type: 'accept_invitation',
        data: {
            invitation_id: invitationId,
            invitee_id: localStorage.getItem('member_id') || '1'
        }
    });
    
    // 移除彈窗
    const popup = document.querySelector('.invitation-popup');
    if (popup) {
        popup.remove();
    }
    
    showWaitingModal();
}

// 拒絕邀請
function rejectInvitation(invitationId) {
    wsClient.send({
        type: 'reject_invitation',
        data: {
            invitation_id: invitationId,
            invitee_id: localStorage.getItem('member_id') || '1'
        }
    });
    
    // 移除彈窗
    const popup = document.querySelector('.invitation-popup');
    if (popup) {
        popup.remove();
    }
    
    showInvitationScreen();
}

// 顯示等待畫面
function showWaitingModal() {
    document.getElementById('invitation-container').style.display = 'none';
    document.getElementById('waiting-container').style.display = 'block';
    document.getElementById('game-container').style.display = 'none';
}

// 取消邀請
function cancelInvitation() {
    // 重新啟用按鈕
    const buttons = document.querySelectorAll('.invite-battle-btn');
    buttons.forEach(btn => btn.disabled = false);
    
    // 重置等待訊息
    const waitingMessage = document.getElementById('waiting-message');
    if (waitingMessage) {
        waitingMessage.textContent = '邀請已發送，等待對方回應...';
    }
    
    if (wsClient) {
        wsClient.disconnect();
    }
    window.location.href = 'index.php';
}

// 設定遊戲設定
function setGameSettings(difficulty, theme) {
    wsClient.send({
        type: 'set_game_settings',
        data: {
            room_id: roomId,
            difficulty: difficulty,
            theme: theme
        }
    });
}

// 開始遊戲設定
function startGameWithSettings(settings) {
    currentDifficulty = settings.difficulty || 'easy';
    currentTheme = settings.theme || 'fruit';
    
    // 根據難度設定遊戲參數
    const gameSettings = {
        easy: { gridSize: 4, timeLimit: 60 },
        normal: { gridSize: 4, timeLimit: 120 },
        hard: { gridSize: 8, timeLimit: 180 }
    };
    
    const setting = gameSettings[currentDifficulty];
    gridSize = setting.gridSize;
    timeLeft = setting.timeLimit;
    
    // 初始化遊戲
    initializeGame();
    showGameScreen();
}

// 開始遊戲
function startGame() {
    if (gameTimer) {
        clearInterval(gameTimer);
    }
    
    gameStartTimestamp = Date.now();
    gameTimer = setInterval(() => {
        timeLeft--;
        document.getElementById('timer').textContent = timeLeft;
        
        if (timeLeft <= 0) {
            endGame();
        }
    }, 1000);
}

// 初始化遊戲
function initializeGame() {
    // 重置遊戲狀態
    cards = [];
    flippedCards = [];
    matchedPairs = 0;
    totalMoves = 0;
    currentPlayer = 1;
    player1Score = 0;
    player2Score = 0;
    player1Pairs = 0;
    player2Pairs = 0;
    canFlip = true;
    
    // 更新顯示
    updateDisplay();
    
    // 生成卡片
    generateCards();
}

// 生成卡片
function generateCards() {
    const gameBoard = document.getElementById('game-board');
    gameBoard.innerHTML = '';
    
    // 根據主題和難度生成卡片
    const cardPairs = Math.floor((gridSize * gridSize) / 2);
    const cardValues = getCardValues(currentTheme, cardPairs);
    
    // 創建卡片陣列
    cards = [];
    for (let i = 0; i < cardPairs; i++) {
        cards.push(cardValues[i], cardValues[i]);
    }
    
    // 洗牌
    shuffleArray(cards);
    
    // 創建卡片元素
    cards.forEach((value, index) => {
        const card = document.createElement('div');
        card.className = 'card';
        card.dataset.value = value;
        card.dataset.index = index;
        card.innerHTML = '<div class="card-back">?</div>';
        
        card.addEventListener('click', () => flipCard(card));
        gameBoard.appendChild(card);
    });
}

// 獲取卡片值
function getCardValues(theme, pairs) {
    const themes = {
        fruit: ['🍎', '🍌', '🍇', '🍊', '🍓', '🍑', '🥝', '🍍'],
        animal: ['🐶', '🐱', '🐰', '🐼', '🐨', '🦊', '🐯', '🦁'],
        daily: ['⌚', '📱', '💻', '🎧', '📷', '🎮', '📺', '🔋'],
        vegetable: ['🥬', '🥕', '🥦', '🍅', '🥒', '🌽', '🥔', '🧅']
    };
    
    return themes[theme] ? themes[theme].slice(0, pairs) : themes.fruit.slice(0, pairs);
}

// 洗牌函數
function shuffleArray(array) {
    for (let i = array.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [array[i], array[j]] = [array[j], array[i]];
    }
}

// 翻牌
function flipCard(card) {
    if (!canFlip || card.classList.contains('flipped') || card.classList.contains('matched')) {
        return;
    }
    
    card.classList.add('flipped');
    card.innerHTML = `<div class="card-front">${card.dataset.value}</div>`;
    flippedCards.push(card);
    
    if (flippedCards.length === 2) {
        canFlip = false;
        totalMoves++;
        
        const [card1, card2] = flippedCards;
        
        if (card1.dataset.value === card2.dataset.value) {
            // 配對成功
            setTimeout(() => {
                card1.classList.add('matched');
                card2.classList.add('matched');
                matchedPairs++;
                
                // 更新當前玩家分數
                if (currentPlayer === 1) {
                    player1Score += 10;
                    player1Pairs++;
                } else {
                    player2Score += 10;
                    player2Pairs++;
                }
                
                updateDisplay();
                flippedCards = [];
                canFlip = true;
                
                // 配對成功可以再翻一次
                consecutiveMatches++;
                if (consecutiveMatches >= 2) {
                    switchPlayer();
                }
                
                // 檢查遊戲是否結束
                if (matchedPairs === cards.length / 2) {
                    endGame();
                }
            }, 500);
        } else {
            // 配對失敗
            setTimeout(() => {
                card1.classList.remove('flipped');
                card2.classList.remove('flipped');
                card1.innerHTML = '<div class="card-back">?</div>';
                card2.innerHTML = '<div class="card-back">?</div>';
                
                flippedCards = [];
                canFlip = true;
                consecutiveMatches = 0;
                
                // 切換玩家
                switchPlayer();
            }, 1000);
        }
    }
}

// 切換玩家
function switchPlayer() {
    currentPlayer = currentPlayer === 1 ? 2 : 1;
    updateDisplay();
}

// 更新顯示
function updateDisplay() {
    document.getElementById('player1-score').textContent = player1Score;
    document.getElementById('player2-score').textContent = player2Score;
    document.getElementById('player1-pairs').textContent = player1Pairs;
    document.getElementById('player2-pairs').textContent = player2Pairs;
    document.getElementById('total-moves').textContent = totalMoves;
    document.getElementById('timer').textContent = timeLeft;
    
    // 更新當前玩家指示
    document.getElementById('player1-info').classList.toggle('active', currentPlayer === 1);
    document.getElementById('player2-info').classList.toggle('active', currentPlayer === 2);
}

// 顯示遊戲畫面
function showGameScreen() {
    document.getElementById('invitation-container').style.display = 'none';
    document.getElementById('waiting-container').style.display = 'none';
    document.getElementById('game-container').style.display = 'block';
}

// 結束遊戲
function endGame() {
    if (gameTimer) {
        clearInterval(gameTimer);
    }
    
    gameEndTimestamp = Date.now();
    const playTime = Math.floor((gameEndTimestamp - gameStartTimestamp) / 1000);
    
    // 確定獲勝者
    let winner;
    if (player1Pairs > player2Pairs) {
        winner = player1Name;
    } else if (player2Pairs > player1Pairs) {
        winner = player2Name;
    } else {
        winner = '平手';
    }
    
    // 保存遊戲結果
    saveGameResult(playTime, winner);
    
    // 顯示遊戲結果
    showGameResult(winner, playTime);
}

// 保存遊戲結果
async function saveGameResult(playTime, winner) {
    try {
        const response = await fetch('Memory-Game-2P.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                player1_id: window.phpMemberId,
                player2_id: 'opponent',
                difficulty: currentDifficulty,
                player1_score: player1Score,
                player2_score: player2Score,
                play_time: playTime,
                winner: winner
            })
        });
        
        const result = await response.json();
        if (result.success) {
            console.log('遊戲結果已保存');
        }
    } catch (error) {
        console.error('保存遊戲結果失敗:', error);
    }
}

// 顯示遊戲結果
function showGameResult(winner, playTime) {
    const resultModal = document.createElement('div');
    resultModal.className = 'result-modal';
    resultModal.innerHTML = `
        <div class="result-content">
            <h2>遊戲結束</h2>
            <p>獲勝者: ${winner}</p>
            <p>遊戲時間: ${playTime} 秒</p>
            <p>玩家 1 分數: ${player1Score}</p>
            <p>玩家 2 分數: ${player2Score}</p>
            <div class="result-buttons">
                <button onclick="location.reload()">再玩一次</button>
                <button onclick="window.location.href='index.php'">返回主頁</button>
            </div>
        </div>
    `;
    
    document.body.appendChild(resultModal);
}

// 更新遊戲狀態
function updateGameState(data) {
    // 同步遊戲狀態
    if (data.cards) {
        cards = data.cards;
    }
    if (data.currentPlayer) {
        currentPlayer = data.currentPlayer;
    }
    if (data.player1Score !== undefined) {
        player1Score = data.player1Score;
    }
    if (data.player2Score !== undefined) {
        player2Score = data.player2Score;
    }
    
    updateDisplay();
}

// 再玩一次
function replayGame() {
    location.reload();
}

// 返回主選單
function returnToMain() {
    window.location.href = 'index.php';
}

// 頁面載入時初始化
window.addEventListener('DOMContentLoaded', function() {
    console.log('DOM 載入完成');
    
    // 添加事件監聽器
    const helpBtn = document.getElementById('help-btn');
    console.log('找到說明按鈕:', helpBtn);
    if (helpBtn) {
        helpBtn.addEventListener('click', function() {
            console.log('說明按鈕被點擊');
            showHelp();
        });
    }
    
    // 為所有邀請按鈕添加事件監聽器
    const inviteButtons = document.querySelectorAll('.invite-battle-btn');
    console.log('找到邀請按鈕數量:', inviteButtons.length);
    inviteButtons.forEach((button, index) => {
        console.log(`為按鈕 ${index} 添加事件監聽器`);
        button.addEventListener('click', function() {
            console.log('邀請按鈕被點擊');
            const friendId = this.getAttribute('data-friend-id');
            const friendName = this.getAttribute('data-friend-name');
            console.log('好友資訊:', friendId, friendName);
            inviteFriend(friendId, friendName);
        });
    });
    
    // 確保所有函數都已定義
    if (typeof showInvitationScreen === 'function') {
        console.log('showInvitationScreen 函數已定義');
        // 先顯示邀請畫面
        showInvitationScreen();
    } else {
        console.error('showInvitationScreen 函數未定義');
    }
    
    if (typeof initializeWebSocket === 'function') {
        console.log('initializeWebSocket 函數已定義');
        // 初始化 WebSocket 連接
        initializeWebSocket();
    } else {
        console.error('initializeWebSocket 函數未定義');
    }
});

// 頁面卸載時清理
window.addEventListener('beforeunload', function() {
    if (wsClient) {
        wsClient.disconnect();
    }
});

// 將函數設為全局可用
window.showHelp = showHelp;
window.inviteFriend = inviteFriend;
window.acceptInvitation = acceptInvitation;
window.rejectInvitation = rejectInvitation;
window.cancelInvitation = cancelInvitation;
window.closeHelpModal = closeHelpModal;
