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
let timer;
let timeLimit = 60;
let score = 0;
let difficulty = "普通";
let targetScore = 100;
let totalPairs = 0;
let gameStartTimestamp = null;
let gameEndTimestamp = null;

// 音效變數
let flipSound = null;
let bingoSound = null;

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
    // 初始化視頻播放邏輯
    initVideoPlayback();
    // 嘗試啟用音頻（需要用戶互動）
    enableAudio();
}

// 啟用音頻功能
function enableAudio() {
    const video = document.getElementById('current-video');
    // 創建用戶互動事件來啟用音頻
    const enableSound = () => {
        video.muted = false;
        video.volume = 1.0;
        console.log('音頻已啟用');
        // 移除事件監聽器，避免重複執行
        document.removeEventListener('click', enableSound);
        document.removeEventListener('touchstart', enableSound);
    };
    
    // 添加點擊和觸摸事件監聽器
    document.addEventListener('click', enableSound);
    document.addEventListener('touchstart', enableSound);
}

// 初始化視頻連續播放
function initVideoPlayback() {
    const video = document.getElementById('current-video');
    
    // 清除之前的事件監聽器
    video.removeEventListener('ended', handleVideoEnd);
    
    // 設置第一個視頻
    video.src = 'gd/card1.mp4';
    video.setAttribute('data-current-video', 'card1');
    
    // 顯示「上一步」和「下一步」按鈕
    document.getElementById('prev-step-button').style.display = 'none';
    document.getElementById('next-step-button').style.display = 'block';
    
    updateInstructionUI('card1');
    
    // 添加視頻結束事件監聽器
    video.addEventListener('ended', handleVideoEnd);
    
    // 添加播放事件監聽器來隱藏音頻提示
    video.addEventListener('play', function() {
        const audioNotice = document.getElementById('audio-notice');
        if (audioNotice && !video.muted) {
            audioNotice.style.display = 'none';
        }
    });
    
    // 強制加載視頻並確保聲音開啟
    video.load();
    video.muted = false;
    video.volume = 1.0;
}

// 處理視頻結束事件 (這裡不需要自動跳轉，用戶手動切換)
function handleVideoEnd() {
    const video = document.getElementById('current-video');
    console.log('視頻播放結束');
}

// 前往下一步
function goToNextStep() {
    const video = document.getElementById('current-video');
    const currentStep = video.getAttribute('data-current-video');
    
    if (currentStep === 'card1') {
        video.src = 'gd/card2.mp4';
        video.setAttribute('data-current-video', 'card2');
        updateInstructionUI('card2');
    }
    
    video.load();
    video.muted = false;
    video.volume = 1.0;
    video.play();
}

// 回到上一步
function goToPrevStep() {
    const video = document.getElementById('current-video');
    const currentStep = video.getAttribute('data-current-video');
    
    if (currentStep === 'card2') {
        video.src = 'gd/card1.mp4';
        video.setAttribute('data-current-video', 'card1');
        updateInstructionUI('card1');
    }
    
    video.load();
    video.muted = false;
    video.volume = 1.0;
    video.play();
}

// 更新說明文字和按鈕顯示
function updateInstructionUI(videoName) {
    const instructionText = document.getElementById('instruction-text');
    const stepIndicator = document.getElementById('step-indicator');
    const prevStepButton = document.getElementById('prev-step-button');
    const nextStepButton = document.getElementById('next-step-button');
    
    if (videoName === 'card1') {
        instructionText.textContent = '先選擇主題，再選擇難度';
        stepIndicator.textContent = '步驟 1/2';
        prevStepButton.style.display = 'none';
        nextStepButton.style.display = 'block';
    } else if (videoName === 'card2') {
        instructionText.innerHTML = '點卡片翻面，比對圖案<br>在時間內完成配對！';
        stepIndicator.textContent = '步驟 2/2';
        prevStepButton.style.display = 'block';
        nextStepButton.style.display = 'none';
    }
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
    
    // 蔬菜主題特別處理：使用稍微深一點的淺綠色作為卡片正面顏色和配對成功顏色
    if (theme === 'vegetable') {
        document.documentElement.style.setProperty('--card-front-color', '#D4E6D4');
        document.documentElement.style.setProperty('--matched-color', '#D4E6D4');
    } else {
        document.documentElement.style.setProperty('--card-front-color', themeStyle.cardFront);
        document.documentElement.style.setProperty('--matched-color', themeStyle.matched);
    }
    
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

// 初始化音效
function initSounds() {
    try {
        flipSound = new Audio('music/card.m4a');
        flipSound.volume = 0.6; // 提高音量
        flipSound.preload = 'auto';
        flipSound.load(); // 強制載入音效
        
        bingoSound = new Audio('music/Bingo.m4a');
        bingoSound.volume = 0.4; // 提高音量
        bingoSound.playbackRate = 1.3; // 加快播放速度
        bingoSound.preload = 'auto';
        bingoSound.load(); // 強制載入音效
        
        console.log('音效初始化成功');
    } catch (e) {
        console.error('音效初始化失敗:', e);
    }
}

// 播放翻牌音效
function playFlipSound() {
    if (flipSound) {
        // 立即重置並播放，減少延遲
        flipSound.currentTime = 0;
        flipSound.pause(); // 先暫停確保重置
        flipSound.play().catch(e => {
            // 如果播放失敗（例如用戶還沒與頁面互動），靜默處理
            console.log('翻牌音效播放失敗:', e);
        });
    } else {
        console.log('翻牌音效對象不存在');
    }
}

// 播放配對成功音效
function playBingoSound() {
    if (bingoSound) {
        bingoSound.currentTime = 0; // 重置音效到開始位置
        bingoSound.play().catch(e => {
            // 如果播放失敗（例如用戶還沒與頁面互動），靜默處理
            console.log('配對成功音效播放失敗:', e);
        });
    } else {
        console.log('配對成功音效對象不存在');
    }
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
    // 只對圖片設定大小，emoji使用CSS統一大小
    const allFronts = document.querySelectorAll('.card-front');
    allFronts.forEach(face => {
        // 只對包含圖片的卡片設定字體大小
        if (face.querySelector('img')) {
            face.style.fontSize = fontSize;
        } else {
            // 對emoji卡片完全清除內聯樣式，讓CSS生效
            face.style.fontSize = '';
            face.style.minHeight = '';
            face.style.lineHeight = '';
        }
    });

    // 設定 gameBoard 寬高
    gameBoard.style.width = `calc(${cardSize} * ${cols})`;
    gameBoard.style.height = `calc(${cardSize} * ${rows})`;
    gameBoard.style.marginLeft = 'auto';
    gameBoard.style.marginRight = 'auto';
    gameBoard.style.display = 'grid';
   
    // 新增:
    setTimeout(adjustGameBoardSize, 0);
   
    // 先顯示所有卡片5秒，然後蓋牌
    showAllCards();
    
    // 顯示倒數計時器
    startPreviewCountdown();
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
        // 如果是emoji，直接顯示，使用CSS統一大小
        card.innerHTML = `
            <div class="card-front">${symbol}</div>
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
   
    // 每翻一張牌都播放音效
    playFlipSound();
    
    // 立即響應，減少延遲感
    card.classList.add('flipped');
    flippedCards.push(card);
   
    if (flippedCards.length === 2) {
        moves++;
        document.getElementById('moves').textContent = moves;
        canFlip = false;
       
        // 減少延遲時間，讓遊戲更流暢
        setTimeout(checkMatch, 800);
    }
}
 
// 檢查配對
function checkMatch() {
    const [card1, card2] = flippedCards;
    const match = card1.dataset.symbol === card2.dataset.symbol;
   
    if (match) {
        // 播放配對成功音效
        playBingoSound();
        
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

// 顯示所有卡片（預覽功能）
function showAllCards() {
    const allCards = document.querySelectorAll('.card');
    allCards.forEach(card => {
        card.classList.add('flipped');
    });
}

// 隱藏所有卡片（開始遊戲）
function hideAllCards() {
    const allCards = document.querySelectorAll('.card');
    allCards.forEach(card => {
        card.classList.remove('flipped');
    });
}

// 開始預覽倒數計時器
function startPreviewCountdown() {
    const countdownElement = document.getElementById('countdown-timer');
    const previewCountdown = document.getElementById('preview-countdown');
    let countdown = 5;
    
    // 顯示倒數計時器
    previewCountdown.classList.remove('hidden');
    
    // 更新倒數顯示
    const updateCountdown = () => {
        countdownElement.textContent = countdown;
        countdown--;
        
        if (countdown >= 0) {
            setTimeout(updateCountdown, 1000);
        } else {
            // 倒數結束，隱藏倒數計時器，開始遊戲
            previewCountdown.classList.add('hidden');
            hideAllCards();
            
            // 開始計時
            startTimer();
           
            // 顯示控制按鈕
            document.getElementById('pauseBtn').classList.remove('hidden');
            document.getElementById('endBtn').classList.remove('hidden');
            document.getElementById('resetBtn').classList.remove('hidden');
            document.getElementById('resumeBtn').classList.add('hidden');
        }
    };
    
    // 開始倒數
    updateCountdown();
}
 
// 暫停遊戲
function pauseGame() {
    if (!gamePaused) {
        gamePaused = true;
        clearInterval(gameTimer);
        canFlip = false;
        document.getElementById('pauseBtn').classList.add('hidden');
        document.getElementById('resumeBtn').classList.remove('hidden');
        // 顯示暫停提示
        document.getElementById('pause-indicator').classList.remove('hidden');
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
        // 隱藏暫停提示
        document.getElementById('pause-indicator').classList.add('hidden');
    }
}
 
// 結束遊戲
function endGame(isManualExit = false) {
    console.log('endGame 函數被調用，isManualExit:', isManualExit);
    
    if (gameTimer) {
        clearInterval(gameTimer);
        gameTimer = null;
    }
    
    canFlip = false;
    
    // 隱藏控制按鈕
    const endBtn = document.getElementById('endBtn');
    const pauseBtn = document.getElementById('pauseBtn');
    const resetBtn = document.getElementById('resetBtn');
    const resumeBtn = document.getElementById('resumeBtn');
    const pauseIndicator = document.getElementById('pause-indicator');
    
    if (endBtn) endBtn.classList.add('hidden');
    if (pauseBtn) pauseBtn.classList.add('hidden');
    if (resetBtn) resetBtn.classList.add('hidden');
    if (resumeBtn) resumeBtn.classList.add('hidden');
    if (pauseIndicator) pauseIndicator.classList.add('hidden');
    
    // 如果是手動退出，不顯示失敗狀態
    if (isManualExit) {
        showGameOver('manual'); // 傳遞特殊標識
    } else {
        showGameOver(false);
    }
}
 
// 綁定按鈕事件 - 添加DOM檢查
document.addEventListener('DOMContentLoaded', function() {
    bindGameButtons();
});

// 備用綁定（如果DOMContentLoaded已經觸發）
if (document.readyState === 'loading') {
    // DOM還在載入中，等待DOMContentLoaded
} else {
    // DOM已載入完成，直接綁定
    bindGameButtons();
}

// 綁定遊戲按鈕函數
function bindGameButtons() {
    const pauseBtn = document.getElementById('pauseBtn');
    const resumeBtn = document.getElementById('resumeBtn');
    const endBtn = document.getElementById('endBtn');
    const resetBtn = document.getElementById('resetBtn');
    
    console.log('綁定按鈕狀態:', {
        pauseBtn: !!pauseBtn,
        resumeBtn: !!resumeBtn,
        endBtn: !!endBtn,
        resetBtn: !!resetBtn
    });
    
    if (pauseBtn) {
        pauseBtn.onclick = pauseGame;
        console.log('暫停按鈕已綁定');
    }
    if (resumeBtn) {
        resumeBtn.onclick = resumeGame;
        console.log('繼續按鈕已綁定');
    }
    if (endBtn) {
        endBtn.onclick = function(e) {
            console.log('結束按鈕被點擊');
            e.preventDefault();
            endGame(true); // 直接結束遊戲，不顯示確認視窗
        };
        console.log('結束按鈕已綁定');
    }
    if (resetBtn) {
        resetBtn.onclick = resetGame;
        console.log('重置按鈕已綁定');
    }
}
 
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
async function saveGameResult(isWin, score, playTime, isManualExit = false) {
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
                play_time: playTime,
                is_manual_exit: isManualExit
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
    // 嘗試從多個來源獲取會員ID
    // 1. 從 URL 參數獲取
    const urlParams = new URLSearchParams(window.location.search);
    const memberIdFromUrl = urlParams.get('member_id');
    if (memberIdFromUrl) return memberIdFromUrl;
    
    // 2. 從 localStorage 獲取
    const memberIdFromStorage = localStorage.getItem('member_id');
    if (memberIdFromStorage) return memberIdFromStorage;
    
    // 3. 從 sessionStorage 獲取
    const memberIdFromSession = sessionStorage.getItem('member_id');
    if (memberIdFromSession) return memberIdFromSession;
    
    // 4. 如果都找不到，返回預設值（用於測試）
    console.warn('無法獲取會員ID，使用預設值');
    return 23; // 預設測試會員ID
}
 
// 顯示遊戲結束彈窗
function showGameOver(isWin) {
    gameEndTimestamp = Date.now();
    let playTime = 0;
    if (gameStartTimestamp && gameEndTimestamp) {
        playTime = Math.round((gameEndTimestamp - gameStartTimestamp) / 1000); // 單位：秒
    }
    
    // 檢查DOM元素是否存在
    const gameOverModal = document.getElementById('game-over-modal');
    const gameOverTitle = document.getElementById('game-over-title');
    const difficultySpan = document.getElementById('memory-gameover-difficulty');
    const targetSpan = document.getElementById('memory-gameover-target');
    const scoreSpan = document.getElementById('memory-gameover-score');
    
    if (!gameOverModal || !gameOverTitle) {
        console.error('遊戲結束彈窗元素未找到');
        return;
    }

    // 獲取難度中文名稱
    const difficultyNames = {
        'easy': '簡單',
        'normal': '普通',
        'hard': '困難'
    };

    // 獲取目標分數
    const targetScores = {
        'easy': 20,
        'normal': 50,
        'hard': 100
    };

    // 設置標題
    if (isWin === 'manual') {
        // 手動退出時，根據是否達到目標分數決定標題
        score = calculateScore(); // 先計算分數
        if (score > 0) {
            gameOverTitle.textContent = '🎉恭喜破關';
        } else {
            gameOverTitle.textContent = '⏰ 遊戲失敗';
        }
    } else {
        gameOverTitle.textContent = isWin ? '🎉恭喜破關' : '⏰ 遊戲失敗';
    }
   
    // 設置結果訊息
    let score = 0;
    const timeRow = document.getElementById('memory-time-row');
    const bonusRow = document.getElementById('memory-bonus-row');
    const failMessage = document.getElementById('memory-fail-message');
    const timeSpan = document.getElementById('memory-gameover-time');
    const bonusSpan = document.getElementById('memory-gameover-bonus');
    
    if (isWin) {
        // 勝利時：顯示難度、遊戲時間、過關分數
        if (score === 0) score = calculateScore(); // 如果還沒計算過分數，才計算
        if (difficultySpan) difficultySpan.textContent = difficultyNames[currentDifficulty];
        if (timeSpan) timeSpan.textContent = `${playTime}秒`;
        if (bonusSpan) bonusSpan.textContent = `+${score}`;
        // 勝利時顯示遊戲時間和過關分數，隱藏失敗訊息
        if (timeRow) timeRow.style.display = 'block';
        if (bonusRow) bonusRow.style.display = 'block';
        if (failMessage) failMessage.style.display = 'none';
    } else {
        // 失敗時：顯示難度、遊戲時間、過關分數+0
        if (difficultySpan) difficultySpan.textContent = difficultyNames[currentDifficulty];
        if (timeSpan) timeSpan.textContent = `${playTime}秒`;
        if (bonusSpan) bonusSpan.textContent = `+0`;
        // 失敗時顯示遊戲時間和過關分數
        if (timeRow) timeRow.style.display = 'block';
        if (bonusRow) bonusRow.style.display = 'block';
        
        // 只有真正失敗時才顯示失敗訊息，手動退出不顯示
        if (isWin === 'manual') {
            if (failMessage) failMessage.style.display = 'none';
        } else {
            if (failMessage) failMessage.style.display = 'block';
        }
    }
 
    // 儲存遊戲結果（帶分數與 play_time）
    saveGameResult(isWin, score, playTime, isWin === 'manual');
    
    // 立即更新主頁面分數
    if (window.forceRefreshScore) {
        setTimeout(() => {
            window.forceRefreshScore();
        }, 1000); // 1秒後更新，確保資料庫已保存
    }
 
    // 立即顯示遊戲結束視窗
    gameOverModal.classList.remove('hidden');
    console.log('遊戲結束彈窗已顯示', { isWin, score, playTime });
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
    // 智能返回：回到上一頁，如果沒有上一頁則回到首頁
    if (document.referrer && document.referrer !== window.location.href) {
        history.back();
    } else {
        window.location.href = 'index.php';
    }
}
 
// 頁面載入時初始化
window.onload = function() {
    initSounds(); // 初始化音效
    
    // 添加音效預熱機制
    const prewarmAudio = () => {
        if (flipSound) {
            const originalVolume = flipSound.volume;
            flipSound.volume = 0; // 靜音預熱
            flipSound.currentTime = 0;
            flipSound.play().then(() => {
                flipSound.pause();
                flipSound.currentTime = 0;
                flipSound.volume = originalVolume; // 恢復音量
                console.log('翻牌音效預熱完成');
            }).catch(e => {
                // 靜默處理預熱失敗，這是正常的瀏覽器行為
                console.log('翻牌音效預熱跳過（需要用戶互動）');
            });
        }
        
        if (bingoSound) {
            const originalVolume = bingoSound.volume;
            bingoSound.volume = 0; // 靜音預熱
            bingoSound.currentTime = 0;
            bingoSound.play().then(() => {
                bingoSound.pause();
                bingoSound.currentTime = 0;
                bingoSound.volume = originalVolume; // 恢復音量
                console.log('配對音效預熱完成');
            }).catch(e => {
                // 靜默處理預熱失敗，這是正常的瀏覽器行為
                console.log('配對音效預熱跳過（需要用戶互動）');
            });
        }
        
        // 移除事件監聽器，避免重複執行
        document.removeEventListener('click', prewarmAudio);
        document.removeEventListener('touchstart', prewarmAudio);
    };
    
    // 在用戶第一次互動時預熱音效
    document.addEventListener('click', prewarmAudio, { once: true });
    document.addEventListener('touchstart', prewarmAudio, { once: true });
    
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
    // 停止視頻播放
    const video = document.getElementById('current-video');
    if (video) {
        video.pause();
        video.currentTime = 0; // 重置到開始位置
    }
    
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
    
    const containerWidth = container.clientWidth;
    const viewportWidth = window.innerWidth;
    
    // 手機上困難模式使用更小的間距
    const gap = (viewportWidth <= 600 && cols === 8) ? 4 : 6; // px
    let maxCardSize;
    if (cols === 4 && rows === 3) {
        maxCardSize = 120;
    } else {
        maxCardSize = 90;
    }
    
    // 手機上特別處理
    let maxBoardWidth;
    if (viewportWidth <= 600 && cols === 8) {
        // 手機上困難模式：使用90%的視窗寬度，避免被擋到
        maxBoardWidth = viewportWidth * 0.90;
    } else if (viewportWidth <= 600 && cols === 4 && rows === 4) {
        // 手機上普通模式：使用95%的視窗寬度，避免被擋到
        maxBoardWidth = viewportWidth * 0.95;
    } else {
        maxBoardWidth = Math.min(containerWidth, calcCols * maxCardSize + (calcCols - 1) * gap);
    }
    
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
    // 圖示大小自動調整 - 只調整圖片，emoji使用CSS統一大小
    const fontSize = cardSize * 0.95;
    document.querySelectorAll('.card-front img').forEach(img => {
        img.style.width = fontSize + 'px';
        img.style.height = fontSize + 'px';
    });
    
    // 確保emoji不受動態調整影響，使用CSS統一大小
    document.querySelectorAll('.card-front').forEach(front => {
        // 只對包含emoji的卡片（不包含img標籤的）保持CSS設定
        if (!front.querySelector('img')) {
            front.style.fontSize = ''; // 清除內聯樣式，讓CSS生效
            front.style.minHeight = ''; // 清除內聯樣式
            front.style.lineHeight = ''; // 清除內聯樣式
        }
    });

    // 讓 .game-container 寬度自動比 .game-board 大 600px，永遠包住所有牌
    const boardWidth = board.offsetWidth;
    
    // 手機上特別處理：確保容器不會超出視窗
    if (viewportWidth <= 600 && cols === 8) {
        // 困難模式
        container.style.width = '100vw';
        container.style.maxWidth = '100vw';
    } else if (viewportWidth <= 600 && cols === 4 && rows === 4) {
        // 普通模式
        container.style.width = '100vw';
        container.style.maxWidth = '100vw';
    } else {
        container.style.width = (boardWidth + 600) + 'px';
    }
}

// 視窗縮放時自動調整
window.addEventListener('resize', adjustGameBoardSize);

function showThemeModal() {
    document.getElementById('difficulty-modal').classList.add('hidden');
    document.getElementById('theme-modal').classList.remove('hidden');
}
window.showThemeModal = showThemeModal;