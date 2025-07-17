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

// ====== 雙人房間邀請機制：取得 roomId（從網址參數） ======
function getRoomId() {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get('room') || 'default-room';
}
const roomId = getRoomId(); // 這個 roomId 會跟好友一樣
// ====== END ======

// === Socket.io 連線設定 ===
let socket = null;
const playerName = localStorage.getItem('member_id') || '玩家';
let isMyTurn = false;

// 檢查是否有 roomId，如果有才連線 socket.io
if (roomId && roomId !== 'default-room') {
    try {
        socket = io('http://localhost:3001'); // 若上線請改成你的伺服器IP
        
        // 加入房間
        socket.emit('joinRoom', { roomId, playerName });
        
        // Socket 事件監聽
        socket.on('connect', () => {
            console.log('已連線到遊戲伺服器');
        });
        
        socket.on('disconnect', () => {
            console.log('與遊戲伺服器斷線');
        });
        
        socket.on('gameAction', (data) => {
            console.log('收到遊戲動作:', data);
            // 處理其他玩家的動作
        });
        
    } catch (error) {
        console.log('Socket.io 連線失敗，使用本地模式:', error);
        socket = null;
    }
} else {
    console.log('本地模式：無 roomId，不連線 socket.io');
}

// 遊戲設置
const gameSettings = {
    easy: {
        gridSize: 4,
        timeLimit: 60,
        baseScore: 20
    },
    normal: {
        gridSize: 4,
        timeLimit: 120,
        baseScore: 50
    },
    hard: {
        gridSize: 8,
        timeLimit: 180,
        baseScore: 100
    }
};

// 使用從PHP傳來的資料更新設定
difficulties.forEach(diff => {
    if (gameSettings[diff.difficulty_level]) {
        gameSettings[diff.difficulty_level] = {
            ...gameSettings[diff.difficulty_level],
            gridSize: diff.color_count,
            timeLimit: diff.time_limit,
            baseScore: diff.score_multiplier
        };
    }
});

// 使用從PHP傳來的顏色設定
const themeColors = {};
colors.forEach(color => {
    if (!themeColors[color.difficulty_level]) {
        themeColors[color.difficulty_level] = {};
    }
    themeColors[color.difficulty_level][color.color_name] = color.color_code;
});

// 卡片符號
const symbols = {
    fruit: ['🍎', '🍐', '🍊', '🍋', '🍌', '🍉', '🍇', '🍓', '🍈', '🍒', '🍑', '🥭',
           '🍍', '🥥', '🥝', '🍅', '🍆', '🥑', '🥦', '🥬', '🥒', '🌶️', '🌽', '🥕'],
    animal: ['🐶', '🐱', '🐭', '🐹', '🐰', '🦊', '🐻', '🐼', '🐨', '🐯', '🦁', '🐮',
            '🐷', '🐸', '🐵', '🐔', '🐧', '🐦', '🦆', '🦅', '🦉', '🦇', '🐺', '🐗'],
    daily: ['⌚', '📱', '💻', '⌨️', '🖥️', '🖨️', '🖱️', '🖲️', '🕹️', '🗜️', '💽', '💾',
           '💿', '📀', '📼', '📷', '📹', '🎥', '📽️', '🎞️', '📞', '☎️', '📟', '📠'],
    vegetable: ['🥬', '🥦', '🥒', '🌶️', '🌽', '🥕', '🧄', '🧅', '🥔', '🍠', '🥐', '🥯',
               '🥖', '🥨', '🧀', '🥚', '🍳', '🧈', '🥞', '🧇', '🥓', '🥩', '🍗', '🍖']
};

// 顯示玩家設定視窗
function showPlayerSetupModal() {
    document.getElementById('player-setup-modal').classList.remove('hidden');
    document.getElementById('theme-modal').classList.add('hidden');
    document.getElementById('difficulty-modal').classList.add('hidden');
    document.getElementById('game-container').classList.add('hidden');
}

// 顯示遊戲說明彈窗
function showHelp() {
    document.getElementById('help-modal').classList.remove('hidden');
}

// 選擇主題
function selectTheme(theme) {
    currentTheme = theme;
    const themeData = themes.find(t => t.theme_name === theme);
    const themeStyle = JSON.parse(themeData.theme_style);
   
    // 更新卡片顏色
    document.documentElement.style.setProperty('--card-back-color', themeStyle.cardBack);
    document.documentElement.style.setProperty('--card-front-color', themeStyle.cardFront);
    document.documentElement.style.setProperty('--matched-color', themeStyle.matched);
    document.documentElement.style.setProperty('--background-color', themeStyle.background);
    document.documentElement.style.setProperty('--container-color', themeStyle.container);
   
    // 更新按鈕狀態
    document.querySelectorAll('.theme-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    document.querySelector(`.${theme}-theme`).classList.add('active');

    // 隱藏主題選擇，顯示難度選擇
    document.getElementById('theme-modal').classList.add('hidden');
    document.getElementById('difficulty-modal').classList.remove('hidden');
}

// 選擇難度
function selectDifficulty(difficulty) {
    currentDifficulty = difficulty;
    const settings = gameSettings[difficulty];
    gridSize = settings.gridSize;
    timeLeft = settings.timeLimit;
    document.getElementById('timer').textContent = timeLeft;
    document.getElementById('difficulty-modal').classList.add('hidden');
    document.getElementById('game-container').classList.remove('hidden');
    gameStartTimestamp = Date.now();
    initializeGame();
}

// 初始化遊戲
function initializeGame() {
    // 重置遊戲狀態
    cards = [];
    flippedCards = [];
    matchedPairs = 0;
    totalMoves = 0;
    canFlip = true;
    currentPlayer = 1;
    player1Score = 0;
    player2Score = 0;
    player1Pairs = 0;
    player2Pairs = 0;
    consecutiveMatches = 0;
    
    // 獲取玩家名稱
    player1Name = document.getElementById('player1-name').value || '玩家 1';
    player2Name = document.getElementById('player2-name').value || '玩家 2';
    
    // 更新顯示
    updatePlayerDisplay();
    document.getElementById('total-moves').textContent = '0';
    updateCurrentPlayer();
    
    // 如果是本地模式，設定為自己的回合
    if (!socket) {
        isMyTurn = true;
    }
   
    // 清空遊戲板
    const gameBoard = document.getElementById('game-board');
    gameBoard.innerHTML = '';
   
    // 設置網格
    let cols, rows;
    if (currentDifficulty === 'easy') {
        cols = 4;
        rows = 3;
    } else if (currentDifficulty === 'hard') {
        cols = 8;
        rows = 4;
    } else {
        cols = 4;
        rows = 4;
    }
    
    // 設定網格
    gameBoard.style.gridTemplateColumns = `repeat(${cols}, 1fr)`;
    gameBoard.style.gridTemplateRows = `repeat(${rows}, 1fr)`;
    
    // 創建卡片對
    const totalPairs = (cols * rows) / 2;
    const selectedSymbols = symbols[currentTheme].slice(0, totalPairs);
    const cardSymbols = [...selectedSymbols, ...selectedSymbols];
    shuffleArray(cardSymbols);

    // 清空卡片陣列
    cards = [];
    // 產生卡片
    cardSymbols.forEach((symbol, index) => {
        const card = createCard(symbol, index);
        cards.push(card);
        gameBoard.appendChild(card);
    });

    // 產生卡片後，直接用 JS 設定每張卡片為正方形
    let cardSize, fontSize;
    if (currentDifficulty === 'easy') {
        cardSize = '120px';
        fontSize = '2.8rem';
    } else if (currentDifficulty === 'hard') {
        cardSize = '50px';
        fontSize = '1.2rem';
    } else {
        cardSize = '80px';
        fontSize = '2rem';
    }
    const allCards = document.querySelectorAll('.card');
    allCards.forEach(card => {
        card.style.maxWidth = cardSize;
        card.style.width = cardSize;
        card.style.height = cardSize;
        card.style.paddingBottom = '0';
    });
    const allFronts = document.querySelectorAll('.card-front, .card-back');
    allFronts.forEach(face => {
        face.style.fontSize = fontSize;
    });

    // 設定 gameBoard 寬高
    gameBoard.style.width = `calc(${cardSize} * ${cols})`;
    gameBoard.style.height = `calc(${cardSize} * ${rows})`;
    gameBoard.style.marginLeft = 'auto';
    gameBoard.style.marginRight = 'auto';
    gameBoard.style.display = 'grid';
   
    setTimeout(adjustGameBoardSize, 0);
   
    // 開始計時
    startTimer();
   
    // 顯示控制按鈕
    document.getElementById('pauseBtn').classList.remove('hidden');
    document.getElementById('endBtn').classList.remove('hidden');
    document.getElementById('resetBtn').classList.remove('hidden');
    document.getElementById('resumeBtn').classList.add('hidden');
}

// 創建卡片
function createCard(symbol, index) {
    const card = document.createElement('div');
    card.className = 'card';
    card.innerHTML = `
        <div class="card-front">${symbol}</div>
        <div class="card-back"></div>
    `;
    card.dataset.symbol = symbol;
    card.dataset.index = index;
   
    card.addEventListener('click', () => flipCard(card));
    return card;
}

// 翻牌
function flipCard(card) {
    if (!canFlip || card.classList.contains('flipped') || flippedCards.length >= 2) return;
    
    // 如果是線上模式，檢查是否為自己的回合
    if (socket && !isMyTurn) {
        console.log('不是你的回合');
        return;
    }
    
    // 翻牌
    card.classList.add('flipped');
    flippedCards.push(card);
    
    // 如果是線上模式，發送翻牌事件給伺服器
    if (socket) {
        socket.emit('flipCard', { roomId, cardIndex: card.dataset.index });
    }
    
    // 檢查是否翻到兩張牌
    if (flippedCards.length === 2) {
        totalMoves++;
        document.getElementById('total-moves').textContent = totalMoves;
        
        // 延遲檢查配對，讓玩家看到第二張牌
        setTimeout(() => {
            checkMatchSync();
        }, 1000);
    }
}

// 同步配對檢查
function checkMatchSync() {
    const [card1, card2] = flippedCards;
    const match = card1.dataset.symbol === card2.dataset.symbol;
    
    if (match) {
        // 配對成功
        card1.classList.add('matched');
        card2.classList.add('matched');
        matchedPairs++;
        
        if (currentPlayer === 1) {
            player1Score += 10;
            player1Pairs++;
        } else {
            player2Score += 10;
            player2Pairs++;
        }
        
        // 如果是線上模式，廣播同步狀態
        if (socket) {
            socket.emit('syncGameState', {
                roomId,
                matchedPairs,
                totalMoves,
                player1Score,
                player2Score,
                player1Pairs,
                player2Pairs,
                currentPlayer
            });
        }
        
        // 檢查是否結束
        let totalPairs = (currentDifficulty === 'easy') ? 6 : (currentDifficulty === 'normal') ? 8 : 16;
        if (matchedPairs === totalPairs) {
            if (socket) {
                socket.emit('syncGameOver', { roomId, isWin: true });
            }
            showGameOver(true);
            return;
        }
        
        flippedCards = [];
        canFlip = true;
        // 配對成功可以再翻一次
        consecutiveMatches++;
        
    } else {
        // 配對失敗
        card1.classList.remove('flipped');
        card2.classList.remove('flipped');
        
        // 換人
        currentPlayer = currentPlayer === 1 ? 2 : 1;
        consecutiveMatches = 0;
        
        // 如果是線上模式，廣播同步狀態
        if (socket) {
            socket.emit('syncGameState', {
                roomId,
                matchedPairs,
                totalMoves,
                player1Score,
                player2Score,
                player1Pairs,
                player2Pairs,
                currentPlayer
            });
        }
        
        flippedCards = [];
        canFlip = true;
    }
    
    // 更新顯示
    updatePlayerDisplay();
    updateCurrentPlayer();
}

// 更新當前玩家顯示
function updateCurrentPlayer() {
    const player1Info = document.getElementById('player1-info');
    const player2Info = document.getElementById('player2-info');
    const gameBoard = document.getElementById('game-board');
    
    if (currentPlayer === 1) {
        player1Info.classList.add('active');
        player2Info.classList.remove('active');
        gameBoard.classList.remove('current-player-2');
        gameBoard.classList.add('current-player-1');
    } else {
        player1Info.classList.remove('active');
        player2Info.classList.add('active');
        gameBoard.classList.remove('current-player-1');
        gameBoard.classList.add('current-player-2');
    }
}

// 更新玩家顯示
function updatePlayerDisplay() {
    document.getElementById('player1-score').textContent = player1Score;
    document.getElementById('player2-score').textContent = player2Score;
    document.getElementById('player1-pairs').textContent = player1Pairs;
    document.getElementById('player2-pairs').textContent = player2Pairs;
    
    // 更新玩家名稱
    document.querySelector('#player1-info .player-name').textContent = player1Name;
    document.querySelector('#player2-info .player-name').textContent = player2Name;
}

// 開始計時
function startTimer() {
    clearInterval(gameTimer);
    gameTimer = setInterval(() => {
        timeLeft--;
        document.getElementById('timer').textContent = timeLeft;
       
        if (timeLeft <= 0) {
            clearInterval(gameTimer);
            canFlip = false;
            showGameOver(false);
        }
    }, 1000);
}

// 重置遊戲
function resetGame() {
    clearInterval(gameTimer);
    gamePaused = false;

    // 隱藏所有 modal
    document.querySelectorAll('.modal').forEach(m => m.classList.add('hidden'));
    document.getElementById('game-container').classList.add('hidden');
    document.getElementById('player-setup-modal').classList.remove('hidden');

    document.getElementById('game-board').innerHTML = '';
    document.getElementById('total-moves').textContent = '0';
    document.getElementById('timer').textContent = gameSettings[currentDifficulty].timeLimit;

    document.getElementById('pauseBtn').classList.add('hidden');
    document.getElementById('resumeBtn').classList.add('hidden');
    document.getElementById('endBtn').classList.add('hidden');
    document.getElementById('resetBtn').classList.add('hidden');
}

// 洗牌函數
function shuffleArray(array) {
    for (let i = array.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [array[i], array[j]] = [array[j], array[i]];
    }
    return array;
}

// 暫停遊戲
function pauseGame() {
    if (!gamePaused) {
        gamePaused = true;
        clearInterval(gameTimer);
        canFlip = false;
        document.getElementById('pauseBtn').classList.add('hidden');
        document.getElementById('resumeBtn').classList.remove('hidden');
    }
}

// 繼續遊戲
function resumeGame() {
    if (gamePaused) {
        gamePaused = false;
        canFlip = true;
        startTimer();
        document.getElementById('pauseBtn').classList.remove('hidden');
        document.getElementById('resumeBtn').classList.add('hidden');
    }
}

// 結束遊戲
function endGame() {
    clearInterval(gameTimer);
    canFlip = false;
    showGameOver(false);
}

// 綁定按鈕事件
document.getElementById('pauseBtn').onclick = pauseGame;
document.getElementById('resumeBtn').onclick = resumeGame;
document.getElementById('endBtn').onclick = endGame;
document.getElementById('resetBtn').onclick = resetGame;

// 儲存遊戲結果
async function saveGameResult(isWin, playTime) {
    try {
        const response = await fetch('Memory-Game-2P.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                player1_id: getCurrentMemberId(),
                player2_id: getCurrentMemberId() + 1, // 假設玩家2的ID
                difficulty: currentDifficulty,
                player1_score: player1Score,
                player2_score: player2Score,
                play_time: playTime
            })
        });
        const result = await response.json();
        if (!result.success) {
            console.error('儲存遊戲結果失敗:', result.message);
        }
    } catch (error) {
        console.error('儲存遊戲結果時發生錯誤:', error);
    }
}

// 獲取當前會員ID
function getCurrentMemberId() {
    return localStorage.getItem('member_id') || null;
}

// 顯示遊戲結束彈窗
function showGameOver(isWin) {
    gameEndTimestamp = Date.now();
    let playTime = 0;
    if (gameStartTimestamp && gameEndTimestamp) {
        playTime = Math.round((gameEndTimestamp - gameStartTimestamp) / 1000);
    }
    
    const gameOverModal = document.getElementById('game-over-modal');
    const gameOverTitle = document.getElementById('game-over-title');
    const resultMessage = document.getElementById('result-message');
    const winnerAnnouncement = document.getElementById('winner-announcement');

    // 獲取難度中文名稱
    const difficultyNames = {
        'easy': '簡單',
        'normal': '普通',
        'hard': '困難'
    };

    // 設置標題
    gameOverTitle.textContent = isWin ? '🎉 遊戲完成！' : '⏰ 時間到！';
   
    // 更新最終結果顯示
    document.querySelector('#player1-result .player-name').textContent = player1Name;
    document.querySelector('#player1-result .final-score').textContent = player1Score;
    document.querySelector('#player1-result .final-pairs').textContent = `${player1Pairs} 對`;
    
    document.querySelector('#player2-result .player-name').textContent = player2Name;
    document.querySelector('#player2-result .final-score').textContent = player2Score;
    document.querySelector('#player2-result .final-pairs').textContent = `${player2Pairs} 對`;

    // 判斷勝負
    let winnerText = '';
    if (player1Pairs > player2Pairs) {
        winnerText = `🏆 ${player1Name} 獲勝！`;
    } else if (player2Pairs > player1Pairs) {
        winnerText = `🏆 ${player2Name} 獲勝！`;
    } else {
        winnerText = '🤝 平手！';
    }
    
    winnerAnnouncement.textContent = winnerText;
    
    // 設置結果訊息
    if (isWin) {
        resultMessage.innerHTML = `難度 : ${difficultyNames[currentDifficulty]}<br><br>遊戲時間 : ${playTime}秒`;
    } else {
        resultMessage.innerHTML = `難度 : ${difficultyNames[currentDifficulty]}<br><br>未在時間內完成所有配對`;
    }

    // 儲存遊戲結果
    saveGameResult(isWin, playTime);

    // 立即顯示遊戲結束視窗
    gameOverModal.classList.remove('hidden');
}

// 重新開始遊戲
function replayGame() {
    // 重置遊戲狀態
    cards = [];
    flippedCards = [];
    matchedPairs = 0;
    totalMoves = 0;
    timeLeft = gameSettings[currentDifficulty].timeLimit;
    canFlip = true;
    gamePaused = false;
    currentPlayer = 1;
    player1Score = 0;
    player2Score = 0;
    player1Pairs = 0;
    player2Pairs = 0;
    consecutiveMatches = 0;
   
    // 清除計時器
    clearInterval(gameTimer);
   
    // 重置顯示
    document.getElementById('total-moves').textContent = '0';
    document.getElementById('timer').textContent = timeLeft;
    updatePlayerDisplay();
   
    // 清空遊戲板
    const gameBoard = document.getElementById('game-board');
    gameBoard.innerHTML = '';
   
    // 隱藏遊戲結束視窗
    const gameOverModal = document.getElementById('game-over-modal');
    gameOverModal.classList.add('hidden');
   
    // 顯示玩家設定視窗
    document.getElementById('player-setup-modal').classList.remove('hidden');
    document.getElementById('theme-modal').classList.add('hidden');
    document.getElementById('difficulty-modal').classList.add('hidden');
    document.getElementById('game-container').classList.add('hidden');
   
    // 重置控制按鈕狀態
    document.getElementById('pauseBtn').classList.add('hidden');
    document.getElementById('resumeBtn').classList.add('hidden');
    document.getElementById('endBtn').classList.add('hidden');
    document.getElementById('resetBtn').classList.add('hidden');
}

// 返回主選單
function returnToMain() {
    window.location.href = 'index.php';
}

// 頁面載入時初始化
window.onload = function() {
    document.getElementById('player-setup-modal').classList.remove('hidden');
    document.getElementById('theme-modal').classList.add('hidden');
    document.getElementById('difficulty-modal').classList.add('hidden');
    document.getElementById('game-container').classList.add('hidden');
};

// 全局函數
window.selectTheme = selectTheme;
window.selectDifficulty = selectDifficulty;
window.showHelp = showHelp;
window.replayGame = replayGame;
window.returnToMain = returnToMain;
window.resetGame = resetGame;
window.showPlayerSetupModal = showPlayerSetupModal;
window.showThemeModal = function() {
    document.getElementById('player-setup-modal').classList.add('hidden');
    document.getElementById('theme-modal').classList.remove('hidden');
    document.getElementById('difficulty-modal').classList.add('hidden');
};

function closeHelpModal() {
    document.getElementById('help-modal').classList.add('hidden');
}
window.closeHelpModal = closeHelpModal;

function adjustGameBoardSize() {
    const container = document.querySelector('.game-container');
    const board = document.getElementById('game-board');
    if (!container || !board) return;

    let cols, rows, calcCols, calcRows;
    if (board.classList.contains('hard-mode') || window.currentDifficulty === 'hard') {
        cols = 8; rows = 4; calcCols = 8; calcRows = 4;
    } else if (board.classList.contains('easy-mode') || window.currentDifficulty === 'easy') {
        cols = 4; rows = 3; calcCols = 4; calcRows = 4;
    } else {
        cols = 4; rows = 4; calcCols = 4; calcRows = 4;
    }
    const gap = 6;
    let maxCardSize;
    if (cols === 4 && rows === 3) {
        maxCardSize = 120;
    } else {
        maxCardSize = 90;
    }
    const containerWidth = container.clientWidth;
    const maxBoardWidth = Math.min(containerWidth, calcCols * maxCardSize + (calcCols - 1) * gap);
    const cardSize = Math.floor((maxBoardWidth - (calcCols - 1) * gap) / calcCols);

    board.style.width = (cardSize * cols + (cols - 1) * gap) + 'px';
    board.style.height = 'auto';

    document.querySelectorAll('.card').forEach(card => {
        card.style.width = card.style.height = cardSize + 'px';
        card.style.maxWidth = card.style.maxHeight = cardSize + 'px';
        card.style.paddingBottom = '0';
    });
    
    const fontSize = cardSize * 0.95;
    document.querySelectorAll('.card-front, .card-back').forEach(face => {
        face.style.fontSize = fontSize + 'px';
    });

    const boardWidth = board.offsetWidth;
    container.style.width = (boardWidth + 600) + 'px';
}

// 視窗縮放時自動調整
window.addEventListener('resize', adjustGameBoardSize);

// 監聽遊戲開始（可根據伺服器邏輯擴充）
socket.on('startGame', (gameState) => {
    // 這裡可以根據 gameState 初始化遊戲
    initializeGame(gameState);
});

// 監聽翻牌同步
socket.on('syncFlipCard', ({ cardIndex, playerId }) => {
    const card = document.querySelector(`.card[data-index='${cardIndex}']`);
    if (!card.classList.contains('flipped')) {
        card.classList.add('flipped');
        flippedCards.push(card);
        // 只在同步時檢查配對
        if (flippedCards.length === 2) {
            setTimeout(() => {
                checkMatchSync();
            }, 1000);
        }
    }
});

// 監聽配對、分數、回合同步
socket.on('syncGameState', (state) => {
    // state: { matchedPairs, totalMoves, player1Score, player2Score, player1Pairs, player2Pairs, currentPlayer }
    matchedPairs = state.matchedPairs;
    totalMoves = state.totalMoves;
    player1Score = state.player1Score;
    player2Score = state.player2Score;
    player1Pairs = state.player1Pairs;
    player2Pairs = state.player2Pairs;
    currentPlayer = state.currentPlayer;
    updatePlayerDisplay();
    updateCurrentPlayer();
});

// 監聽遊戲結束
socket.on('syncGameOver', (result) => {
    showGameOver(result.isWin);
}); 