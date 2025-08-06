// 遊戲變數
let cards = [];
let flippedCards = [];
let matchedPairs = 0;
let moves = 0;
let timeLeft = 60;
let gameTimer;
let canFlip = true;
let currentDifficulty = 'easy';
let currentTheme = 'fruit';
let gridSize = 4;
let gamePaused = false;
let currentScore = 0;
let highScore = localStorage.getItem('highScore') || 0;
// 假設我們有一個計時器
let timer;
let timeLimit = 60; // 60秒的時間限制
let score = 0;
let difficulty = "普通"; // 假設難度是普通
let targetScore = 100; // 假設目標分數是100
let totalPairs = 0;
// === 新增：紀錄遊戲開始與結束時間 ===
let gameStartTimestamp = null;
let gameEndTimestamp = null;

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
        gridSize: 8,  // 8列
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
    fruit: ['papaya.png', 'starfruit.png', 'passionfruit.png', 'durian.png', 'mangosteen.png', '🍎', '🍐', '🍊', '🍋', '🍌', '🍉', '🍇',
           '🍓', '🍈', '🍒', '🍑', '🥭', '🍍', '🥥', '🥝', '🍋‍🟩', '🍏', '🥑', '🫐'],
    animal: ['🐶', '🐱', '🐭', '🐹', '🐰', '🦊', '🐻', '🐼', '🐨', '🐯', '🦁', '🐮',
            '🐷', '🐸', '🐵', '🐔', '🐧', '🐦', '🦆', '🦅', '🦉', '🦇', '🐺', '🐗'],
    daily: ['⌚', '📱', '🧯', '📺', '🖥️', '🖨️', '🖱️', '🖊️', '💉', '🪥', '🧢', '🩴',
           '💿', '👓', '💰', '📷', '💡', '🔑', '📽️', '✂️', '📞', '☎️', '🔨', '🧦'],
    vegetable: ['green_pepper.png', 'white_radish.png', 'red_pepper.png', 'scallion.png', 'napa_cabbage.png', '🥬', '🥦', '🥒', '🌶️', '🌽', '🥕', '🧄',
               '🧅', '🥔', '🍅', '🥩', '🎃', '🫛', '🫑', '🧀', '🥚', '🫚', '🍄‍🟫', '🍆']
};
 
// 顯示遊戲說明彈窗
function showHelp() {
    document.getElementById('help-modal').classList.remove('hidden');
}

// 處理返回按鈕
function handleBackButton() {
    window.location.href = 'index.php';
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
    // === 新增 ===
    gameStartTimestamp = Date.now();
    // ===========
    initializeGame();
}
 
// 初始化遊戲
function initializeGame() {
    // 重置遊戲狀態
    cards = [];
    flippedCards = [];
    matchedPairs = 0;
    moves = 0;
    canFlip = true;
    document.getElementById('moves').textContent = '0';
   
    // 清空遊戲板
    const gameBoard = document.getElementById('game-board');
    gameBoard.innerHTML = '';
   
    // 設置網格
    let cols, rows;
    if (currentDifficulty === 'easy') {
        cols = 4;
        rows = 3;
    } else if (currentDifficulty === 'hard') {
        cols = 8;  // 8列
        rows = 4;  // 4行
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
        cardSize = '50px';  // 改小一點，因為有8列
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
   
    // 新增:
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
    
    // 檢查是否為圖片檔案（包含.png副檔名）
    const isImage = symbol.includes('.png');
    
    if (isImage) {
        // 如果是圖片，使用img標籤，設定較小的尺寸
        console.log('創建圖片卡片:', symbol, '路徑:', `img/${symbol}`);
        card.innerHTML = `
            <div class="card-front">
                <img src="img/${symbol}" alt="${symbol}" style="width: 70%; height: 70%; object-fit: contain; margin: auto; display: block;" onerror="console.error('圖片載入失敗:', this.src)">
            </div>
            <div class="card-back"></div>
        `;
    } else {
        // 如果是emoji，直接顯示，設定較大的字體
        card.innerHTML = `
            <div class="card-front" style="font-size: 5.5rem; display: flex; align-items: center; justify-content: center;">${symbol}</div>
            <div class="card-back"></div>
        `;
    }
    
    card.dataset.symbol = symbol;
    card.dataset.index = index;
   
    card.addEventListener('click', () => flipCard(card));
    return card;
}
 
// 翻牌
function flipCard(card) {
    if (!canFlip || card.classList.contains('flipped') || flippedCards.length >= 2) return;
   
    card.classList.add('flipped');
    flippedCards.push(card);
   
    if (flippedCards.length === 2) {
        moves++;
        document.getElementById('moves').textContent = moves;
        canFlip = false;
       
        setTimeout(checkMatch, 1000);
    }
}
 
// 檢查配對
function checkMatch() {
    const [card1, card2] = flippedCards;
    const match = card1.dataset.symbol === card2.dataset.symbol;
   
    if (match) {
        card1.classList.add('matched');
        card2.classList.add('matched');
        matchedPairs++;

        // 計算總配對數
        let totalPairs;
        if (currentDifficulty === 'easy') {
            totalPairs = 6; // 4x3 網格，共6對
        } else if (currentDifficulty === 'normal') {
            totalPairs = 8; // 4x4 網格，共8對
        } else {
            totalPairs = 16; // 8x4 網格，共16對
        }

        // 檢查是否所有配對都完成
        if (matchedPairs === totalPairs) {
            clearInterval(gameTimer);
            canFlip = false;
            showGameOver(true);
            return;
        }
    } else {
        card1.classList.remove('flipped');
        card2.classList.remove('flipped');
    }
   
    flippedCards = [];
    canFlip = true;
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
    currentScore = 0;
    updateScoreDisplay();

    // 隱藏所有 modal
    document.querySelectorAll('.modal').forEach(m => m.classList.add('hidden'));
    document.getElementById('game-container').classList.add('hidden');
    document.getElementById('theme-modal').classList.remove('hidden');
    document.getElementById('difficulty-modal').classList.add('hidden');

    document.getElementById('game-board').innerHTML = '';
    document.getElementById('moves').textContent = '0';
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
 
// 更新分數顯示
function updateScoreDisplay() {
    if (document.getElementById('current-score')) {
        document.getElementById('current-score').textContent = currentScore;
    }
    if (document.getElementById('high-score')) {
        document.getElementById('high-score').textContent = highScore;
    }
}
 
// 計算分數
function calculateScore() {
    // 根據難度給予固定分數
    switch(currentDifficulty) {
        case 'easy':
            return 20;  // 簡單過關+20
        case 'normal':
            return 50;  // 普通+50
        case 'hard':
            return 100; // 困難+100
        default:
            return 0;
    }
}
 
// 儲存遊戲結果
async function saveGameResult(isWin, score, playTime) {
    try {
        const response = await fetch('Memory-Game.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                member_id: getCurrentMemberId(),
                difficulty: currentDifficulty,
                status: isWin ? 'completed' : 'failed',
                score: score,
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
    // 這裡需要實作獲取當前登入會員ID的邏輯
    // 可以從 session 或 localStorage 中獲取
    return localStorage.getItem('member_id') || null;
}
 
// 顯示遊戲結束彈窗
function showGameOver(isWin) {
    gameEndTimestamp = Date.now();
    let playTime = 0;
    if (gameStartTimestamp && gameEndTimestamp) {
        playTime = Math.round((gameEndTimestamp - gameStartTimestamp) / 1000); // 單位：秒
    }
    const gameOverModal = document.getElementById('game-over-modal');
    const gameOverTitle = document.getElementById('game-over-title');
    const resultMessage = document.getElementById('result-message');
 
    // 獲取難度中文名稱
    const difficultyNames = {
        'easy': '簡單',
        'normal': '普通',
        'hard': '困難'
    };
 
    // 設置標題
    gameOverTitle.textContent = isWin ? '🎉 恭喜破關！' : '⏰ 遊戲失敗';
   
    // 設置結果訊息
    let score = 0;
    if (isWin) {
        score = calculateScore();
        resultMessage.innerHTML = `難度 : ${difficultyNames[currentDifficulty]}<br><br>獲得分數 : ${score}<br><br>遊戲時間 : ${playTime}秒`;
    } else {
        resultMessage.innerHTML = `難度 : ${difficultyNames[currentDifficulty]}<br><br>未在時間內達成分數`;
    }
 
    // 儲存遊戲結果（帶分數與 play_time）
    saveGameResult(isWin, score, playTime);
 
    // 立即顯示遊戲結束視窗
    gameOverModal.classList.remove('hidden');
}
 
// 顯示主選單
function showMainMenu() {
    document.getElementById('game-container').classList.add('hidden');
    document.getElementById('theme-modal').classList.remove('hidden');
    document.getElementById('difficulty-modal').classList.add('hidden');
    document.querySelector('.modal')?.remove();
}
 
// 重新開始遊戲
function replayGame() {
    // 重置遊戲狀態
    cards = [];
    flippedCards = [];
    matchedPairs = 0;
    moves = 0;
    timeLeft = gameSettings[currentDifficulty].timeLimit;
    canFlip = true;
    gamePaused = false;
   
    // 清除計時器
    clearInterval(gameTimer);
   
    // 重置顯示
    document.getElementById('moves').textContent = '0';
    document.getElementById('timer').textContent = timeLeft;
   
    // 清空遊戲板
    const gameBoard = document.getElementById('game-board');
    gameBoard.innerHTML = '';
   
    // 隱藏遊戲結束視窗
    const gameOverModal = document.getElementById('game-over-modal');
    gameOverModal.classList.add('hidden');
   
    // 顯示主題選擇視窗
    document.getElementById('theme-modal').classList.remove('hidden');
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
    updateScoreDisplay();
    document.getElementById('theme-modal').classList.remove('hidden');
    document.getElementById('difficulty-modal').classList.add('hidden');
    document.getElementById('game-container').classList.add('hidden');
};

window.selectTheme = selectTheme;
window.selectDifficulty = selectDifficulty;
window.showHelp = showHelp;
window.replayGame = replayGame;
window.returnToMain = returnToMain;
window.resetGame = resetGame;

function closeHelpModal() {
    document.getElementById('help-modal').classList.add('hidden');
}
window.closeHelpModal = closeHelpModal;

function adjustGameBoardSize() {
    const container = document.querySelector('.game-container');
    const board = document.getElementById('game-board');
    if (!container || !board) return;

    // 讓簡單模式用 4x4 的寬度計算卡片大小，rows 設 3，cols 設 4
    let cols, rows, calcCols, calcRows;
    if (board.classList.contains('hard-mode') || window.currentDifficulty === 'hard') {
        cols = 8; rows = 4; calcCols = 8; calcRows = 4;
    } else if (board.classList.contains('easy-mode') || window.currentDifficulty === 'easy') {
        cols = 4; rows = 3; calcCols = 4; calcRows = 4;
    } else {
        cols = 4; rows = 4; calcCols = 4; calcRows = 4;
    }
    const gap = 6; // px
    let maxCardSize;
    if (cols === 4 && rows === 3) {
        maxCardSize = 120;
    } else {
        maxCardSize = 90;
    }
    const containerWidth = container.clientWidth;
    const maxBoardWidth = Math.min(containerWidth, calcCols * maxCardSize + (calcCols - 1) * gap);
    const cardSize = Math.floor((maxBoardWidth - (calcCols - 1) * gap) / calcCols);

    // 設定 .game-board 寬高
    board.style.width = (cardSize * cols + (cols - 1) * gap) + 'px';
    board.style.height = 'auto';

    // 設定每張卡片的寬高
    document.querySelectorAll('.card').forEach(card => {
        card.style.width = card.style.height = cardSize + 'px';
        card.style.maxWidth = card.style.maxHeight = cardSize + 'px';
        card.style.paddingBottom = '0';
    });
    // 圖示大小自動調整
    const fontSize = cardSize * 0.95;
    document.querySelectorAll('.card-front, .card-back').forEach(face => {
        face.style.fontSize = fontSize + 'px';
    });

    // 讓 .game-container 寬度自動比 .game-board 大 600px，永遠包住所有牌
    const boardWidth = board.offsetWidth;
    container.style.width = (boardWidth + 600) + 'px';
}

// 視窗縮放時自動調整
window.addEventListener('resize', adjustGameBoardSize);

function showThemeModal() {
    document.getElementById('difficulty-modal').classList.add('hidden');
    document.getElementById('theme-modal').classList.remove('hidden');
}
window.showThemeModal = showThemeModal;