// 遊戲配置變數
let currentBoardSize = null;
let currentDifficultyName = '';
let tileCount = null;
let finalState = [];
let tiles = [];

// 遊戲進入跟踪
let currentGameRecordId = null;

// 記錄遊戲進入
function trackGameEntry() {
    const gameData = {
        game_type: '算術邏輯力',
        game_id: 10,
        difficulty: currentDifficultyName || 'easy'
    };

    fetch('start_game.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(gameData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.record_id) {
            currentGameRecordId = data.record_id;
            console.log('遊戲進入記錄成功，ID:', currentGameRecordId);
        }
    })
    .catch(error => {
        console.error('記錄遊戲進入失敗:', error);
    });
}

// 記錄遊戲退出
function trackGameExit() {
    if (!currentGameRecordId) return;

    const exitData = {
        record_id: currentGameRecordId
    };

    // 使用 navigator.sendBeacon 確保在頁面關閉時也能發送請求
    if (navigator.sendBeacon) {
        navigator.sendBeacon('mark_game_exit.php', JSON.stringify(exitData));
    } else {
        // 備用方案：使用 fetch
        fetch('mark_game_exit.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(exitData)
        }).catch(error => {
            console.error('記錄遊戲退出失敗:', error);
        });
    }
}

// 遊戲狀態變數
let moveCount = 0;
let timeElapsed = 0;
let timerInterval = null;
let gameActive = false; // 遊戲是否在進行中
let isPaused = false;   // 遊戲是否暫停的狀態

// 分數獎勵
let SCORE_REWARDS = {
    '簡單': 20,
    '普通': 50,
    '困難': 100
};

// 滑動/拖曳檢測變數
let startX = 0;
let startY = 0;
let isDragging = false;
const SWIPE_THRESHOLD = 50; // 判斷為滑動所需的最小像素位移

// DOM 元素
const boardElement = document.getElementById('game-board');
const messageElement = document.getElementById('message');
const scoreBoardElement = document.getElementById('score-board');

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
const helpPage1 = document.getElementById('help-page-1');
const helpPage2 = document.getElementById('help-page-2');
const helpBackBtn = document.getElementById('help-back-btn');
const helpNextBtn = document.getElementById('help-next-btn');
const helpPageIndicator = document.getElementById('help-page-indicator');
const helpVideo = document.getElementById('help-video');

// 過關視窗
const winModalOverlay = document.getElementById('win-modal-overlay');
const winDifficultyName = document.getElementById('win-difficulty-name');
const winMoveCount = document.getElementById('win-move-count');
const winTimeElapsed = document.getElementById('win-time-elapsed');
const winScore = document.getElementById('win-score');
const winFailMessage = document.getElementById('win-fail-message');
const playAgainButton = document.getElementById('play-again-button');
const backToHomeButton = document.getElementById('back-to-home-button');

// 完成圖彈窗相關元素
const showSolutionButton = document.getElementById('show-solution-btn');
const solutionModalOverlay = document.getElementById('solution-modal-overlay');
const closeSolutionButton = document.getElementById('close-solution-btn');
const solutionBoardElement = document.getElementById('solution-board');

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
    moveCountDisplay.textContent = '0';
    moveCountDisplay.classList.remove('green-text', 'red-text');
    moveCountDisplay.classList.add('green-text');
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
}

function isSolvable(arr) {
    return arr.join(',') !== finalState.join(',');
}

function shuffleTiles() {
    let currentTiles = [...finalState];
    do {
        for (let i = currentTiles.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [currentTiles[i], currentTiles[j]] = [currentTiles[j], currentTiles[i]];
        }
        if (currentBoardSize >= 4) {
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
            if (gameActive && !isPaused) {
                tile.addEventListener('click', () => handleTileClick(index));
            }
        }

        tile.dataset.index = index;
        boardElement.appendChild(tile);
    });
}

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
        drawBoard();
        if (checkWin()) {
            showResultModal('win');
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

/**
 * 從完成狀態開始，逆向移動指定步數來生成一個可解的謎題。
 * @param {number} moves - 要逆向移動的步數，步數越多，難度越高。
 * @returns {Array<number>} - 生成的謎題陣列。
 */
function generateSolvablePuzzle(moves) {
    let board = [...finalState];
    let emptyIndex = board.indexOf(0);
    let lastMove = -1; // 用於防止無效的來回移動 (例如：上一步往下，下一步馬上往上)

    const movesMap = [
        1,  // 向上移動 (emptyIndex 增加 boardSize)
       -1,  // 向下移動 (emptyIndex 減少 boardSize)
        2,  // 向左移動 (emptyIndex 增加 1)
       -2   // 向右移動 (emptyIndex 減少 1)
    ];

    for (let i = 0; i < moves; i++) {
        const emptyRow = Math.floor(emptyIndex / currentBoardSize);
        const emptyCol = emptyIndex % currentBoardSize;

        const possibleMoves = [];

        // 檢查是否可以向上移動 (空白格往下移)
        if (emptyRow < currentBoardSize - 1 && lastMove !== 1) possibleMoves.push(-1);
        // 檢查是否可以向下移動 (空白格往上移)
        if (emptyRow > 0 && lastMove !== -1) possibleMoves.push(1);
        // 檢查是否可以向左移動 (空白格往右移)
        if (emptyCol < currentBoardSize - 1 && lastMove !== 2) possibleMoves.push(-2);
        // 檢查是否可以向右移動 (空白格往左移)
        if (emptyCol > 0 && lastMove !== -2) possibleMoves.push(2);

        // 從所有可能的移動中隨機選擇一個
        const randomMove = possibleMoves[Math.floor(Math.random() * possibleMoves.length)];
        let tileToMoveIndex = -1;

        switch (randomMove) {
            case 1:  // 空白格向上移
                tileToMoveIndex = emptyIndex - currentBoardSize;
                lastMove = 1;
                break;
            case -1: // 空白格向下移
                tileToMoveIndex = emptyIndex + currentBoardSize;
                lastMove = -1;
                break;
            case 2:  // 空白格向左移
                tileToMoveIndex = emptyIndex - 1;
                lastMove = 2;
                break;
            case -2: // 空白格向右移
                tileToMoveIndex = emptyIndex + 1;
                lastMove = -2;
                break;
        }

        // 交換方塊與空白格
        [board[emptyIndex], board[tileToMoveIndex]] = [board[tileToMoveIndex], board[emptyIndex]];
        // 更新空白格的新位置
        emptyIndex = tileToMoveIndex;
    }
    
    // 如果剛好回到原點 (機率極低)，重新生成一次
    if (board.join(',') === finalState.join(',')) {
        return generateSolvablePuzzle(moves);
    }

    return board;
}


/**
 * 根據選擇的難度，呼叫生成器來創建謎題。
 */
function createPuzzle() {
    let scrambleMoves = 0;
    switch (currentDifficultyName) {
        case '簡單':
            scrambleMoves = 35; // 約 30-40 步的複雜度
            break;
        case '普通':
            scrambleMoves = 80; // 約 70-90 步的複雜度
            break;
        case '困難':
            scrambleMoves = 150; // 約 140-160 步的複雜度
            break;
        default:
            scrambleMoves = 35;
            break;
    }
    return generateSolvablePuzzle(scrambleMoves);
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

function updateHelpPage(pageNumber) {
    if (pageNumber === 1) {
        helpPage1.classList.remove('hidden');
        helpPage2.classList.add('hidden');
        helpBackBtn.classList.add('hidden');
        helpNextBtn.classList.remove('hidden');
        helpPageIndicator.textContent = '步驟 1/2';
    } else if (pageNumber === 2) {
        helpPage1.classList.add('hidden');
        helpPage2.classList.remove('hidden');
        helpBackBtn.classList.remove('hidden');
        helpNextBtn.classList.add('hidden');
        helpPageIndicator.textContent = '步驟 2/2';
    }
}

function showHelpModal() {
    modalOverlay.classList.add('hidden');
    helpModalOverlay.classList.remove('hidden');
    updateHelpPage(1);

    if (helpVideo) {
        helpVideo.currentTime = 0;
        helpVideo.pause();
    }
}

function hideHelpModal() {
    helpModalOverlay.classList.add('hidden');
    modalOverlay.classList.remove('hidden');

    if (helpVideo) {
        helpVideo.pause();
        helpVideo.currentTime = 0;
    }
}

// --- 統一的移動處理邏輯 ---

function handleSwipeOrDrag(endX, endY) {
    const dx = endX - startX;
    const dy = endY - startY;

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
        return true;
    }
    return false;
}

// --- 觸控事件 (平板/手機) ---

function handleTouchStart(event) {
    if (!gameActive || isPaused || event.touches.length !== 1) return;
    startX = event.touches[0].clientX;
    startY = event.touches[0].clientY;
}

function handleTouchMove(event) {
    // 保持為空，以允許頁面滾動
}

function handleTouchEnd(event) {
    if (!gameActive || isPaused || event.changedTouches.length !== 1) return;
    const touchEndX = event.changedTouches[0].clientX;
    const touchEndY = event.changedTouches[0].clientY;
    handleSwipeOrDrag(touchEndX, touchEndY);
}

// --- 滑鼠拖曳事件 (電腦) ---

function handleMouseDown(event) {
    if (!gameActive || isPaused || event.button !== 0) return;
    isDragging = true;
    startX = event.clientX;
    startY = event.clientY;
}

function handleMouseUp(event) {
    if (!isDragging || !gameActive || isPaused) return;
    isDragging = false;
    const mouseUpX = event.clientX;
    const mouseUpY = event.clientY;
    handleSwipeOrDrag(mouseUpX, mouseUpY);
}

// --- 顯示與關閉完成圖邏輯 ---

function showSolutionModal() {
    if (!gameActive || !currentBoardSize) return;

    // 暫停計時器
    stopTimer();

    // 清空舊的內容並設定網格
    solutionBoardElement.innerHTML = '';
    solutionBoardElement.style.gridTemplateColumns = `repeat(${currentBoardSize}, 1fr)`;
    solutionBoardElement.style.fontSize = boardElement.style.fontSize; // 使用和遊戲盤一樣的字體大小

    // 根據 finalState 生成完成圖
    finalState.forEach(value => {
        const tile = document.createElement('div');
        tile.classList.add('tile');
        
        if (value === 0) {
            tile.classList.add('empty');
            tile.innerHTML = ''; 
        } else {
            tile.innerHTML = value;
        }
        solutionBoardElement.appendChild(tile);
    });

    // 顯示彈窗
    solutionModalOverlay.classList.remove('hidden');
}

function closeSolutionModal() {
    solutionModalOverlay.classList.add('hidden');
    
    // 如果遊戲正在進行且不是被玩家手動暫停的狀態，則恢復計時
    if (gameActive && !isPaused) {
        startTimer();
    }
}

// --- 遊戲檢查與結果 ---

function checkWin() {
    return tiles.join(',') === finalState.join(',');
}

async function showResultModal(resultType) {
    gameActive = false;
    stopTimer();

    // 結束時間追踪
    if (typeof manualEndGameTimer === 'function') {
        manualEndGameTimer();
        console.log('已結束遊戲時間追蹤');
    }

    boardElement.style.display = 'none';
    document.querySelector('.game-controls').style.display = 'none';
    scoreBoardElement.classList.add('hidden');
    messageElement.classList.add('hidden');

    let scoreDisplay = '+0 分';
    let resultTitle = '⏰ 挑戰失敗！';
    let showFailMessage = true;
    let failMessageText = '未在步數內完成遊戲！';
    let finalScore = 0;
    let isPassed = false;

    if (resultType === 'win') {
        finalScore = SCORE_REWARDS[currentDifficultyName] || 0;
        scoreDisplay = `+${finalScore} 分`;
        resultTitle = '🎉 恭喜過關！';
        showFailMessage = false;
        isPassed = true;
    } else if (resultType === 'ended_manually') {
        failMessageText = '未能成功過關！';
        isPassed = false;
    }

    try {
        const gameData = {
            member_id: getCurrentMemberId(),
            game_type: '算術邏輯力',
            game_id: 10,
            difficulty: getDifficultyLevel(currentDifficultyName),
            score: finalScore,
            play_time: timeElapsed,
            is_manual_exit: resultType === 'ended_manually',
            is_passed: isPassed
        };
        
        if (typeof saveGameResult === 'function') {
            await saveGameResult(gameData);
        }
        
        if (typeof gameExitHandler !== 'undefined') {
            gameExitHandler.endGame();
        }
        
        currentGameRecordId = null;
    } catch (error) {
        console.error('保存puzzle遊戲結果失敗:', error);
        currentGameRecordId = null;
    }

    document.querySelector('.win-title').textContent = resultTitle;
    winDifficultyName.textContent = currentDifficultyName;
    winMoveCount.textContent = moveCount;
    winTimeElapsed.textContent = `${timeElapsed} 秒`;
    winScore.textContent = scoreDisplay;

    if (showFailMessage) {
        winFailMessage.textContent = failMessageText;
        winFailMessage.classList.remove('hidden');
    } else {
        winFailMessage.classList.add('hidden');
    }

    winModalOverlay.classList.remove('hidden');
}

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
    
    // 開始時間追踪
    if (typeof manualStartGameTimer === 'function') {
        manualStartGameTimer();
        console.log('已開始遊戲時間追蹤');
    }
    
    // 記錄遊戲進入（在真正開始遊戲時）
    trackGameEntry();
    
    // 啟動遊戲退出處理器追蹤
    if (typeof gameExitHandler !== 'undefined') {
        gameExitHandler.startGame();
        console.log('遊戲追蹤已啟動');
    }
    
    // 初始化遊戲追蹤器 - 在真正開始遊戲時才開始計時
    if (typeof gameTracker !== 'undefined') {
        gameTracker.init("算術邏輯力", 10);
    }

    trackGameEntry();

    if (typeof gameExitHandler !== 'undefined') {
        gameExitHandler.startGame();
    }
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

    tiles = createPuzzle();
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
    messageElement.classList.add('hidden');

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
endGameButton.addEventListener('click', () => { showResultModal('ended_manually'); });

pauseContinueButton.addEventListener('click', () => {
    if (currentBoardSize && gameActive) {
        togglePause();
    } else {
        showDifficultyModal();
    }
});

difficultyButtons.forEach(button => {
    button.addEventListener('click', handleDifficultySelect);
});

modalBackButton.addEventListener('click', () => {
    if (currentBoardSize && gameActive) {
        modalOverlay.classList.add('hidden');
        boardElement.style.display = 'grid';
        document.querySelector('.game-controls').style.display = 'flex';
        scoreBoardElement.classList.remove('hidden');
        if (!isPaused) {
           startTimer();
        }
    } else {
        window.location.href = 'game-category.php';
    }
});
modalHelpButton.addEventListener('click', showHelpModal);
helpModalCloseButton.addEventListener('click', hideHelpModal);

helpNextBtn.addEventListener('click', () => {
    updateHelpPage(2);
    if (helpVideo) {
        helpVideo.pause();
    }
});

helpBackBtn.addEventListener('click', () => {
    updateHelpPage(1);
    if (helpVideo) {
        helpVideo.currentTime = 0;
        helpVideo.play().catch(e => {
            console.log("從第二頁返回時，影片非靜音自動播放被瀏覽器阻止。");
        });
    }
});

// 觸控與滑鼠事件
boardElement.addEventListener('touchstart', handleTouchStart, { passive: true });
boardElement.addEventListener('touchmove', handleTouchMove, { passive: true });
boardElement.addEventListener('touchend', handleTouchEnd, { passive: true });
boardElement.addEventListener('mousedown', handleMouseDown);
document.addEventListener('mouseup', handleMouseUp);

// ===== 完成圖彈窗的事件監聽 =====
showSolutionButton.addEventListener('click', showSolutionModal);
closeSolutionButton.addEventListener('click', closeSolutionModal);
// 點擊彈窗背景也能關閉
solutionModalOverlay.addEventListener('click', (event) => {
    if (event.target === solutionModalOverlay) {
        closeSolutionModal();
    }
});

// 初始啟動
document.addEventListener('DOMContentLoaded', function() {
    window.addEventListener('beforeunload', trackGameExit);
    document.addEventListener('visibilitychange', function() {
        if (document.hidden && currentGameRecordId) {
            trackGameExit();
        }
    });
    showDifficultyModal();
});

// 通關彈窗按鈕
playAgainButton.addEventListener('click', () => {
    winModalOverlay.classList.add('hidden');
    showDifficultyModal();
});

backToHomeButton.addEventListener('click', () => {
    window.location.href = 'game-category.php';
});