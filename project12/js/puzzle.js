// 遊戲配置變數
let currentBoardSize = null;
let currentDifficultyName = ''; 
let tileCount = null;
let finalState = [];
let tiles = []; 

// 遊戲狀態變數
let moveCount = 0;
let timeElapsed = 0;
let timerInterval = null;
let gameActive = false; // 遊戲是否在進行中
let isPaused = false;   // 遊戲是否暫停的狀態

// *** 關鍵新增：步數限制配置 ***
// 不從數據庫載入
const MOVE_LIMITS = {
    '簡單': 100,  
    '普通': 200,  
    '困難': 400   
};
// 分數獎勵從數據庫動態載入
let SCORE_REWARDS = {
    '簡單': 20,   // 
    '普通': 50,   // 
    '困難': 100   // 
};
let maxMoveLimit = 0; // 當前難度的最大允許步數

// 分數獎勵設置保持固定值，不從數據庫載入
// 如果需要修改分數，直接修改下面的數值

// 滑動/拖曳檢測變數
let startX = 0; // 統一用於 touchStartX 和 mouseDownX
let startY = 0; // 統一用於 touchStartY 和 mouseDownY
let isDragging = false; // 追蹤滑鼠是否正在拖曳
const SWIPE_THRESHOLD = 50; // 判斷為滑動所需的最小像素位移

// DOM 元素
const boardElement = document.getElementById('game-board');
const messageElement = document.getElementById('message');
const scoreBoardElement = document.getElementById('score-board'); // 計分板

// 計分板顯示元素
const moveCountDisplay = document.getElementById('move-count');
const timeElapsedDisplay = document.getElementById('time-elapsed');

// 控制按鈕
const resetButton = document.getElementById('reset-button');
const endGameButton = document.getElementById('end-game-button');
const pauseContinueButton = document.getElementById('pause-continue-button'); 

// 彈跳視窗相關元素
const modalOverlay = document.getElementById('difficulty-modal-overlay');
const difficultyButtons = document.querySelectorAll('.modal-body .modal-btn');
const modalBackButton = document.getElementById('modal-back-button'); 
const modalHelpButton = document.getElementById('modal-help-button');

// 說明視窗相關元素
const helpModalOverlay = document.getElementById('help-modal-overlay');
const helpModalCloseButton = document.getElementById('help-modal-close-button'); 

// 過關視窗
const winModalOverlay = document.getElementById('win-modal-overlay');
const winDifficultyName = document.getElementById('win-difficulty-name');
const winMoveCount = document.getElementById('win-move-count');
const winMovesLeft = document.getElementById('win-moves-left');
const winTimeElapsed = document.getElementById('win-time-elapsed');
const winScore = document.getElementById('win-score');
const winFailMessage = document.getElementById('win-fail-message');
const playAgainButton = document.getElementById('play-again-button');
const backToHomeButton = document.getElementById('back-to-home-button');

// --- 時間和計步器邏輯 ---

function startTimer() {
    stopTimer(); 
    const startTime = Date.now() - (timeElapsed * 1000); 
    
    timerInterval = setInterval(() => {
        timeElapsed = Math.floor((Date.now() - startTime) / 1000);
        timeElapsedDisplay.textContent = `${timeElapsed} 秒`;
    }, 1000); 
}

function stopTimer() {
    clearInterval(timerInterval);
    timerInterval = null;
}

function resetStats() {
    moveCount = 0;
    timeElapsed = 0;
    moveCountDisplay.textContent = maxMoveLimit > 0 ? maxMoveLimit : '--'; 
    moveCountDisplay.classList.remove('red-text');
    moveCountDisplay.classList.add('green-text'); // 重置顏色為倒數綠色

    timeElapsedDisplay.textContent = '0 秒';
    stopTimer();
}

function updateMoveCount() {
    moveCount++;
    moveCountDisplay.textContent = moveCount;
}


// --- 遊戲核心邏輯 ---

function initializeConfig(size, difficultyName) {
    currentBoardSize = size;
    currentDifficultyName = difficultyName; 
    tileCount = size * size;
    finalState = Array.from({ length: tileCount }, (_, i) => i + 1); 
    finalState[tileCount - 1] = 0; 

    maxMoveLimit = MOVE_LIMITS[difficultyName] || 0;
}

function isSolvable(arr) {
    // 检查数组是否不等于最终状态（即不是已经完成的状态）
    // 这样确保我们生成的是可解的谜题
    return arr.join(',') !== finalState.join(',');
}

function shuffleTiles() {
    let currentTiles = [...finalState]; 
    do {
        for (let i = currentTiles.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [currentTiles[i], currentTiles[j]] = [currentTiles[j], currentTiles[i]];
        }
        if(currentBoardSize >= 4) {
            for (let k = 0; k < 3; k++) {
                 const a = Math.floor(Math.random() * (tileCount - 1));
                 const b = Math.floor(Math.random() * (tileCount - 1));
                 [currentTiles[a], currentTiles[b]] = [currentTiles[b], currentTiles[a]];
            }
        }
    } while (!isSolvable(currentTiles)); 

    return currentTiles;
}

function drawBoard() {
    if (!currentBoardSize) return; 

    boardElement.innerHTML = ''; 
    messageElement.classList.add('hidden'); 
    
    boardElement.style.gridTemplateColumns = `repeat(${currentBoardSize}, 1fr)`;
    
    let fontSize;
    switch (currentBoardSize) {
        case 3:
            fontSize = '2.5em'; 
            break;
        case 4:
            fontSize = '1.8em'; 
            break;
        case 5:
            fontSize = '1.4em'; 
            break;
        default:
            fontSize = '2em';
    }
    boardElement.style.fontSize = fontSize;

    tiles.forEach((value, index) => {
        const tile = document.createElement('div');
        tile.classList.add('tile');
        
        if (value === 0) {
            tile.classList.add('empty');
            tile.innerHTML = ''; 
        } else {
            tile.innerHTML = value;
            tile.dataset.value = value;
            
            // 點擊事件監聽：適用於所有裝置的點擊
            if (gameActive && !isPaused) { 
                tile.addEventListener('click', () => handleTileClick(index));
            }
        }

        tile.dataset.index = index; 
        boardElement.appendChild(tile);
    });
}

/**
 * 檢查並執行移動。(通用移動函式)
 */
function tryMoveTile(index1, index2) {
    if (!gameActive || isPaused) return false;

    const row1 = Math.floor(index1 / currentBoardSize);
    const col1 = index1 % currentBoardSize;
    const row2 = Math.floor(index2 / currentBoardSize);
    const col2 = index2 % currentBoardSize;

    const isAdjacent = 
        (row1 === row2 && Math.abs(col1 - col2) === 1) || 
        (col1 === col2 && Math.abs(row1 - row2) === 1);  

    if (isAdjacent) {
        [tiles[index1], tiles[index2]] = [tiles[index2], tiles[index1]];
        updateMoveCount(); 
        if (maxMoveLimit > 0) {
            const movesLeft = maxMoveLimit - moveCount;
            moveCountDisplay.textContent = movesLeft;

            // 步數不足時變色 (例如剩餘 10 步或 20%)
            if (movesLeft <= 10) {
                moveCountDisplay.classList.remove('blue-text');
                moveCountDisplay.classList.add('red-text');
            } else {
                moveCountDisplay.classList.remove('red-text');
                moveCountDisplay.classList.add('blue-text');
            }
        }
        drawBoard();

        if (checkWin()) {
            showResultModal('win'); // <-- 修改點
        } 
        else if (maxMoveLimit > 0 && moveCount >= maxMoveLimit) {
            showResultModal('out_of_moves'); // <-- 修改點
        }
        return true;
    }
    return false;
}

function handleTileClick(clickedIndex) {
    if (!gameActive || isPaused) return; 
    const emptyIndex = tiles.indexOf(0); 
    tryMoveTile(clickedIndex, emptyIndex);
}


// --- 遊戲控制邏輯 (暫停/繼續) ---

function togglePause() {
    if (!gameActive) return; 

    isPaused = !isPaused;

    if (isPaused) {
        stopTimer(); 
        pauseContinueButton.textContent = '繼續遊戲';
        pauseContinueButton.classList.remove('orange-btn');
        pauseContinueButton.classList.add('green-btn'); 
        messageElement.textContent = "遊戲已暫停，點擊「繼續遊戲」以恢復。";
        messageElement.classList.remove('hidden');
        messageElement.style.backgroundColor = '#4CAF50'; 
    } else {
        startTimer(); 
        pauseContinueButton.textContent = '暫停遊戲';
        pauseContinueButton.classList.remove('green-btn');
        pauseContinueButton.classList.add('orange-btn');
        messageElement.classList.add('hidden');
        messageElement.style.backgroundColor = '#FFA500'; 
    }
    drawBoard(); 
}

// --- 遊戲說明視窗邏輯 ---

function showHelpModal() {
    modalOverlay.classList.add('hidden'); // 隱藏難度選擇視窗
    helpModalOverlay.classList.remove('hidden'); // 顯示遊戲說明視窗
}

function hideHelpModal() {
    helpModalOverlay.classList.add('hidden'); // 隱藏遊戲說明視窗
    modalOverlay.classList.remove('hidden'); // 顯示難度選擇視窗
}


// --- 統一的移動處理邏輯 ---

function handleSwipeOrDrag(endX, endY) {
    const dx = endX - startX;
    const dy = endY - startY;

    // 如果位移很小，則視為點擊，讓 click 事件處理
    if (Math.abs(dx) < SWIPE_THRESHOLD && Math.abs(dy) < SWIPE_THRESHOLD) {
        return false; 
    }

    let direction = null;
    if (Math.abs(dx) > Math.abs(dy)) {
        direction = dx > 0 ? 'right' : 'left';
    } else {
        direction = dy > 0 ? 'down' : 'up';
    }

    const emptyIndex = tiles.indexOf(0);
    const emptyRow = Math.floor(emptyIndex / currentBoardSize);
    const emptyCol = emptyIndex % currentBoardSize;
    let tileToMoveIndex = -1;

    switch (direction) {
        case 'up': 
            tileToMoveIndex = (emptyRow + 1) * currentBoardSize + emptyCol;
            break;
        case 'down': 
            tileToMoveIndex = (emptyRow - 1) * currentBoardSize + emptyCol;
            break;
        case 'left': 
            tileToMoveIndex = emptyRow * currentBoardSize + (emptyCol + 1);
            break;
        case 'right': 
            tileToMoveIndex = emptyRow * currentBoardSize + (emptyCol - 1);
            break;
    }

    if (tileToMoveIndex >= 0 && tileToMoveIndex < tileCount) {
        tryMoveTile(tileToMoveIndex, emptyIndex);
        return true; // 成功移動
    }
    return false; // 沒有移動
}


// --- 觸控事件 (平板/手機) ---

function handleTouchStart(event) {
    if (!gameActive || isPaused || event.touches.length !== 1) return; 
    
    // *** 不阻止預設行為 (passive: true)，解決 Intervention 錯誤和點擊問題 ***
    startX = event.touches[0].clientX;
    startY = event.touches[0].clientY;
}

function handleTouchMove(event) {
    // *** 不阻止預設行為，讓頁面可以滾動 ***
}

function handleTouchEnd(event) {
    if (!gameActive || isPaused || event.changedTouches.length !== 1) return;
    
    const touchEndX = event.changedTouches[0].clientX;
    const touchEndY = event.changedTouches[0].clientY;

    if (handleSwipeOrDrag(touchEndX, touchEndY)) {
        // 如果偵測到滑動並移動了方塊，則阻止 click 事件 (如果它會觸發的話)
        // 由於 passive: true，這裡調用 event.preventDefault() 也不會有錯誤
    }
}


// --- 滑鼠拖曳事件 (電腦) ---

function handleMouseDown(event) {
    if (!gameActive || isPaused || event.button !== 0) return; // 只處理左鍵
    
    isDragging = true;
    startX = event.clientX;
    startY = event.clientY;   
}

function handleMouseMove(event) {
    
}

function handleMouseUp(event) {
    if (!isDragging || !gameActive || isPaused) return;

    isDragging = false;
    const mouseUpX = event.clientX;
    const mouseUpY = event.clientY;
    
    handleSwipeOrDrag(mouseUpX, mouseUpY);
}

// --- 拖曳事件結束 ---


function checkWin() {
    const isWin = tiles.join(',') === finalState.join(',');
    console.log('检查胜利条件:');
    console.log('当前状态:', tiles.join(','));
    console.log('目标状态:', finalState.join(','));
    console.log('是否胜利:', isWin);
    return isWin;
}

/**
 * 顯示通關彈窗並填入數據
 * @param {string} resultType - 遊戲結果 ('win', 'out_of_moves', 'ended_manually')
 */
async function showResultModal(resultType) {
    // 停止遊戲、隱藏遊戲介面
    gameActive = false;
    stopTimer(); 
    boardElement.style.display = 'none';
    document.querySelector('.game-controls').style.display = 'none';
    scoreBoardElement.classList.add('hidden'); 
    messageElement.classList.add('hidden');

    const movesLeft = maxMoveLimit > 0 ? maxMoveLimit - moveCount : 0;
    let scoreDisplay = '+0 分';
    let resultTitle = '挑戰失敗！😭';
    let movesLeftDisplay = 0;
    let showFailMessage = true;
    let failMessageText = '未在步數內完成遊戲！';
    let finalScore = 0;
    let isPassed = false;

    if (resultType === 'win') {
        finalScore = SCORE_REWARDS[currentDifficultyName] || 0;
        scoreDisplay = `+${finalScore} 分`; 
        resultTitle = '🎉 恭喜過關！';
        movesLeftDisplay = movesLeft;
        showFailMessage = false;
        isPassed = true;
    } else if (resultType === 'ended_manually') {
        movesLeftDisplay = movesLeft;
        failMessageText = '未能成功過關！';
        isPassed = false;
    } else {
        // out_of_moves
        isPassed = false;
    }
    
    // 保存遊戲結果到資料庫
    try {
        const gameData = {
            member_id: getCurrentMemberId(), // 使用標準的會員ID獲取函數
            game_type: '算術邏輯力',
            game_id: 10, // puzzle遊戲的ID
            difficulty: getDifficultyLevel(currentDifficultyName),
            score: finalScore,
            play_time: timeElapsed,
            is_manual_exit: resultType === 'ended_manually',
            is_passed: isPassed
        };
        
        console.log('準備保存puzzle遊戲結果:', gameData);
        
        // 使用通用函數保存遊戲結果
        if (typeof saveGameResult === 'function') {
            const saveResult = await saveGameResult(gameData);
            console.log('puzzle遊戲結果保存:', saveResult);
        } else {
            console.warn('saveGameResult函數不存在，跳過保存');
        }
        
        // 關鍵：停止遊戲追蹤，防止重複記錄
        if (typeof gameExitHandler !== 'undefined') {
            gameExitHandler.endGame();
            console.log('遊戲追蹤已停止，防止重複記錄');
        }
    } catch (error) {
        console.error('保存puzzle遊戲結果失敗:', error);
    }
    
    // 填充數據到彈窗
    document.querySelector('.win-title').textContent = resultTitle;
    winDifficultyName.textContent = currentDifficultyName;
    winMoveCount.textContent = moveCount;
    winMovesLeft.textContent = movesLeftDisplay;
    winTimeElapsed.textContent = `${timeElapsed} 秒`;
    winScore.textContent = scoreDisplay;

    // 處理失敗/成功訊息
    if (showFailMessage) {
        winFailMessage.textContent = failMessageText;
        winFailMessage.classList.remove('hidden');
    } else {
        winFailMessage.classList.add('hidden');
    }

    // 顯示通關彈窗
    winModalOverlay.classList.remove('hidden');
}


// 將中文難度轉換為英文
function getDifficultyLevel(chineseDifficulty) {
    const difficultyMap = {
        '簡單': 'easy',
        '普通': 'normal',
        '困難': 'hard'
    };
    return difficultyMap[chineseDifficulty] || 'easy';
}

function startGame() {
    if (!currentBoardSize) return;
    
    // 初始化遊戲追蹤器 - 在真正開始遊戲時才開始計時
    if (typeof gameTracker !== 'undefined') {
        gameTracker.init("算術邏輯力", 10);
    }
    
    // 遊戲退出追蹤已在頁面載入時啟動
    
    modalOverlay.classList.add('hidden');
    boardElement.style.display = 'grid';
    document.querySelector('.game-controls').style.display = 'flex';
    scoreBoardElement.classList.remove('hidden'); 
    
    isPaused = false; 
    pauseContinueButton.textContent = '暫停遊戲';
    pauseContinueButton.classList.remove('green-btn'); 
    pauseContinueButton.classList.add('orange-btn');

    gameActive = true;
    resetStats(); 
    startTimer(); 

    tiles = shuffleTiles(); 
    drawBoard();
}

function handleReset() {
    showDifficultyModal();
}

function showDifficultyModal() {
    if (currentBoardSize) {
        gameActive = false; 
        stopTimer();
    }
    
    isPaused = false; 
    pauseContinueButton.textContent = '暫停遊戲';
    pauseContinueButton.classList.remove('green-btn');
    pauseContinueButton.classList.add('orange-btn');
   
    boardElement.style.display = 'none';
    document.querySelector('.game-controls').style.display = 'none';
    scoreBoardElement.classList.add('hidden'); 
    messageElement.classList.add('hidden'); // 隱藏任何勝利訊息

    modalOverlay.classList.remove('hidden');
}

function handleDifficultySelect(event) {
    difficultyButtons.forEach(btn => btn.classList.remove('active'));
    event.currentTarget.classList.add('active');

    const size = parseInt(event.currentTarget.dataset.size);
    const difficultyName = event.currentTarget.dataset.difficultyName;
    
    initializeConfig(size, difficultyName); 
    startGame(); 
}


// --- 事件監聽器 ---

resetButton.addEventListener('click', handleReset); 

// **修改點**：當點擊結束遊戲時，傳入 'ended_manually'
endGameButton.addEventListener('click', () => { showResultModal('ended_manually'); });

pauseContinueButton.addEventListener('click', () => { 
    if (currentBoardSize && gameActive) {
        togglePause();
    } else if (currentBoardSize && !gameActive) {
        showDifficultyModal();
    } else {
        showDifficultyModal(); 
    }
}); 

difficultyButtons.forEach(button => {
    button.addEventListener('click', handleDifficultySelect);
});

modalBackButton.addEventListener('click', () => {
    window.location.href = 'game-category.php';
    // 檢查是否有正在進行的遊戲或難度已選擇
    if (currentBoardSize) { 
        // 如果遊戲已開始，則返回遊戲畫面
        modalOverlay.classList.add('hidden');
        boardElement.style.display = 'grid';
        document.querySelector('.game-controls').style.display = 'flex';
        scoreBoardElement.classList.remove('hidden');
        gameActive = true;
        isPaused = false; 
        startTimer(); 
        drawBoard(); 
    } else {
        // 如果沒有選擇難度，則直接導向到 game-category.php
        // 使用 window.location.href 進行頁面導向
        window.location.href = 'game-category.php';
    }
});
modalHelpButton.addEventListener('click', showHelpModal); // 改為顯示新的說明視窗

// *** 點擊「關閉 X」回到難度選擇視窗 ***
helpModalCloseButton.addEventListener('click', hideHelpModal); 


// *** 關鍵修正: 觸摸事件監聽器 (平板/手機) ***
// 將 passive 設為 true，解決 Intervention 錯誤，並允許點擊
boardElement.addEventListener('touchstart', handleTouchStart, { passive: true }); 
boardElement.addEventListener('touchmove', handleTouchMove, { passive: true }); 
boardElement.addEventListener('touchend', handleTouchEnd, { passive: true });


// *** 關鍵新增: 滑鼠拖曳事件監聽器 (電腦) ***
boardElement.addEventListener('mousedown', handleMouseDown);
document.addEventListener('mousemove', handleMouseMove);
document.addEventListener('mouseup', handleMouseUp);

// --- 遊戲啟動時，顯示難度選擇彈跳視窗 ---
document.addEventListener('DOMContentLoaded', showDifficultyModal);

// --- 通關彈窗按鈕事件監聽器 ---

// 「再玩一次」按鈕：回到難度選擇視窗
playAgainButton.addEventListener('click', () => {
    winModalOverlay.classList.add('hidden');
    showDifficultyModal();
});

// 「返回主頁」按鈕：導向到 game-category.php
backToHomeButton.addEventListener('click', () => {
    window.location.href = 'game-category.php';
});