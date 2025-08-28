// 等待 DOM 完全加載後再初始化遊戲
document.addEventListener('DOMContentLoaded', () => {
    console.log('DOM 加載完成，開始初始化遊戲');
    
    class Game2048 {
        constructor() {
            console.log('開始創建遊戲實例');
            this.board = Array(4).fill().map(() => Array(4).fill(0));
            this.score = 0;
            this.bestScore = 0; // 初始化為 0，稍後從資料庫讀取
            this.targetScore = 1500;
            this.difficulty = 'easy';
            this.gameOver = false;
            this.won = false;
            this.isInitialized = false;
            this.isPaused = false;
            this.isContinuing = false;
            this.winModal = document.getElementById('win-modal');
            this.gameOverModal = document.getElementById('game-over-modal');
            this.targetScoreElement = document.getElementById('target-score');
            
            // 觸控相關變數
            this.touchStartX = 0;
            this.touchStartY = 0;
            this.touchEndX = 0;
            this.touchEndY = 0;
            this.minSwipeDistance = 30; // 最小滑動距離
            
            console.log('遊戲實例基本屬性已初始化');
            
            // 初始化遊戲板
            this.createBoard();
            console.log('遊戲板已創建');
            
            // 設置事件監聽器
            this.setupEventListeners();
            this.setupTouchEvents(); // 新增觸控事件
            this.setupWinModalListeners();
            this.setupGameOverModalListeners();
            
            // 從資料庫讀取最高分數
            this.loadBestScore();
        }

        init() {
            console.log('開始初始化遊戲...');
            if (!this.isInitialized) {
                console.log('創建遊戲板...');
                this.createBoard();
                console.log('添加初始方塊...');
                this.addNewTile();
                this.addNewTile();
                console.log('更新顯示...');
                this.updateDisplay();
                this.isInitialized = true;
                this.gameOver = false;
                this.won = false;
                this.isPaused = false;
                console.log('遊戲初始化完成');
            } else {
                console.log('遊戲已經初始化過');
            }
        }

        createBoard() {
            console.log('開始創建遊戲板...');
            const gameBoard = document.getElementById('board');
            if (!gameBoard) {
                console.error('錯誤：找不到遊戲板元素 #board');
                return;
            }
            
            console.log('清空遊戲板...');
            gameBoard.innerHTML = '';
            
            console.log('創建遊戲格子...');
            for (let i = 0; i < 4; i++) {
                for (let j = 0; j < 4; j++) {
                    const cell = document.createElement('div');
                    cell.className = 'cell';
                    cell.dataset.row = i;
                    cell.dataset.col = j;
                    gameBoard.appendChild(cell);
                }
            }
            console.log('遊戲板創建完成');
        }

        setupTouchEvents() {
            console.log('設置觸控事件監聽器...');
            
            const gameBoard = document.getElementById('board');
            if (!gameBoard) {
                console.error('找不到遊戲板元素，無法設置觸控事件');
                return;
            }
            
            // 觸控開始事件
            gameBoard.addEventListener('touchstart', (e) => {
                if (!this.isInitialized || this.gameOver || this.won || this.isPaused) {
                    return;
                }
                
                e.preventDefault(); // 防止頁面滾動
                const touch = e.touches[0];
                this.touchStartX = touch.clientX;
                this.touchStartY = touch.clientY;
                console.log('觸控開始:', this.touchStartX, this.touchStartY);
            }, { passive: false });
            
            // 觸控結束事件
            gameBoard.addEventListener('touchend', (e) => {
                if (!this.isInitialized || this.gameOver || this.won || this.isPaused) {
                    return;
                }
                
                e.preventDefault();
                const touch = e.changedTouches[0];
                this.touchEndX = touch.clientX;
                this.touchEndY = touch.clientY;
                
                console.log('觸控結束:', this.touchEndX, this.touchEndY);
                this.handleSwipe();
            }, { passive: false });
            
            // 防止觸控時選中文字
            gameBoard.addEventListener('touchmove', (e) => {
                e.preventDefault();
            }, { passive: false });
            
            console.log('觸控事件監聽器設置完成');
        }
        
        handleSwipe() {
            // 檢查遊戲狀態
            if (this.gameOver || this.won) {
                console.log('遊戲已結束或已勝利，忽略滑動');
                return;
            }
            
            const deltaX = this.touchEndX - this.touchStartX;
            const deltaY = this.touchEndY - this.touchStartY;
            const distance = Math.sqrt(deltaX * deltaX + deltaY * deltaY);
            
            console.log('滑動距離:', distance, 'deltaX:', deltaX, 'deltaY:', deltaY);
            
            if (distance < this.minSwipeDistance) {
                console.log('滑動距離太小，忽略');
                return;
            }
            
            let moved = false;
            
            // 判斷滑動方向
            if (Math.abs(deltaX) > Math.abs(deltaY)) {
                // 水平滑動
                if (deltaX > 0) {
                    console.log('向右滑動');
                    moved = this.moveRight();
                } else {
                    console.log('向左滑動');
                    moved = this.moveLeft();
                }
            } else {
                // 垂直滑動
                if (deltaY > 0) {
                    console.log('向下滑動');
                    moved = this.moveDown();
                } else {
                    console.log('向上滑動');
                    moved = this.moveUp();
                }
            }
            
            if (moved) {
                console.log('移動有效，添加新方塊');
                this.addNewTile();
                this.updateDisplay();
                this.checkGameStatus();
            } else {
                console.log('移動無效');
            }
        }

        setupEventListeners() {
            console.log('開始設置事件監聽器...');
            
            // 難度選擇按鈕
            const difficultyButtons = document.querySelectorAll('.difficulty-btn');
            console.log('找到難度按鈕數量:', difficultyButtons.length);
            
            difficultyButtons.forEach(button => {
                button.onclick = (e) => {
                    e.preventDefault();
                    console.log('難度按鈕被點擊:', button.textContent);
                    
                    // 從按鈕的類名獲取難度
                    let difficulty = 'easy';
                    if (button.classList.contains('normal')) {
                        difficulty = 'normal';
                    } else if (button.classList.contains('hard')) {
                        difficulty = 'hard';
                    }
                    
                    this.difficulty = difficulty;
                    this.targetScore = parseInt(button.dataset.target) || 1500;
                    console.log('設置難度:', difficulty, '目標分數:', this.targetScore);
                    
                    // 切換到遊戲頁面
                    const difficultyPage = document.getElementById('difficultyModal');
                    const gamePage = document.getElementById('game-page');
                    
                    if (difficultyPage && gamePage) {
                        console.log('切換到遊戲頁面');
                        difficultyPage.style.display = 'none';
                        gamePage.style.display = 'block';
                        
                        // 重置遊戲狀態並開始新遊戲
                        this.isInitialized = false;
                        this.resetGame();
                        this.init();
                        this.updateTargetScoreDisplay();
                    } else {
                        console.error('找不到必要的頁面元素');
                    }
                };
            });

            // 遊戲說明按鈕
            const showInstructionsBtn = document.getElementById('show-instructions');
            const modal = document.getElementById('instructions-modal');
            const closeButton = document.querySelector('.close-button');

            if (showInstructionsBtn && modal && closeButton) {
                // 顯示遊戲說明
                showInstructionsBtn.addEventListener('click', () => {
                    modal.style.display = 'block';
                });

                // 點擊關閉按鈕關閉
                closeButton.addEventListener('click', () => {
                    modal.style.display = 'none';
                });

                // 點擊 modal 外部關閉
                window.addEventListener('click', (event) => {
                    if (event.target === modal) {
                        modal.style.display = 'none';
                    }
                });
            }

            // 鍵盤事件監聽
            document.addEventListener('keydown', (e) => {
                console.log('鍵盤事件觸發:', e.key);
                if (!this.isInitialized) {
                    console.log('遊戲未初始化，忽略按鍵');
                    return;
                }
                if (this.gameOver || this.won) {
                    console.log('遊戲已結束或已勝利，忽略按鍵');
                    return;
                }
                if (this.isPaused) {
                    console.log('遊戲已暫停，忽略按鍵');
                    return;
                }
                
                let moved = false;
                switch(e.key) {
                    case 'ArrowLeft':
                        console.log('向左移動');
                        moved = this.moveLeft();
                        break;
                    case 'ArrowRight':
                        console.log('向右移動');
                        moved = this.moveRight();
                        break;
                    case 'ArrowUp':
                        console.log('向上移動');
                        moved = this.moveUp();
                        break;
                    case 'ArrowDown':
                        console.log('向下移動');
                        moved = this.moveDown();
                        break;
                }
                
                if (moved) {
                    console.log('移動有效，添加新方塊');
                    this.addNewTile();
                    this.updateDisplay();
                    this.checkGameStatus();
                } else {
                    console.log('移動無效');
                }
            });

            // 暫停按鈕
            const pauseButton = document.getElementById('pauseBtn');
            if (pauseButton) {
                console.log('找到暫停按鈕，設置事件監聽器');
                pauseButton.onclick = () => {
                    console.log('暫停按鈕被點擊');
                    this.togglePause();
                };
            } else {
                console.error('錯誤：找不到暫停按鈕 #pauseBtn');
            }

            // 結束遊戲按鈕
             const endButton = document.getElementById('endBtn');
            if (endButton) {
                console.log('找到結束按鈕，設置事件監聽器');
                endButton.onclick = () => {
                    console.log('結束按鈕被點擊');
                    // 如果已經勝利，不重複記錄分數
                    if (this.won) {
                        console.log('遊戲已勝利，只顯示勝利彈窗，不重複記錄分數');
                        this.showWinModal();
                        return;
                    }
                    // 直接呼叫 endGame 顯示彈窗
                    this.isContinuing = false;
                    this.endGame();
                };
            } else {
                console.error('錯誤：找不到結束按鈕 #endBtn');
            }

            // 重置按鈕
            const resetButton = document.getElementById('resetBtn');
            if (resetButton) {
                console.log('找到重置按鈕，設置事件監聽器');
                resetButton.onclick = () => {
                    console.log('重置按鈕被點擊');
            
                    // 顯示難度選擇彈窗
                    const difficultyModal = document.getElementById('difficultyModal');
                    if (difficultyModal) {
                        difficultyModal.style.display = 'flex';
                    }

                    // 重置遊戲狀態
                    this.resetGame();
            
                };
            } else {
                console.error('錯誤：找不到重置按鈕 #resetBtn');
            }
            
            console.log('事件監聽器設置完成');
        }

        setupWinModalListeners() {
            // 獲取「恭喜破關」彈窗中的「再玩一次」按鈕，ID為 'continue-game'
            const tryAgainWinButton = document.getElementById('continue-game');
            if (tryAgainWinButton) {
                tryAgainWinButton.addEventListener('click', () => {
                    console.log('遊戲勝利彈窗中的「再玩一次」按鈕被點擊');
                    this.winModal.style.display = 'none'; // 隱藏勝利彈窗
                    this.isContinuing = false;
                    
                    const gamePage = document.getElementById('game-page');
                    if (gamePage) {
                        gamePage.style.display = 'none'; // 隱藏遊戲主畫面
                    }

                    const difficultyModal = document.getElementById('difficultyModal');
                    if (difficultyModal) {
                        difficultyModal.style.display = 'flex'; // 顯示難度選擇彈窗
                    }
                    
                    this.resetGame(); // 重置遊戲狀態
                });
            }

            // 獲取「恭喜破關」彈窗中的「返回主頁」按鈕，ID為 'new-game'
            const backToMenuWinButton = document.getElementById('new-game');
            if (backToMenuWinButton) {
                backToMenuWinButton.addEventListener('click', () => {
                    console.log('遊戲勝利彈窗中的「返回主頁」按鈕被點擊');
                    window.location.href = 'index.php'; // 直接導向首頁
                });
            }
        }

        setupGameOverModalListeners() {
            // 再試一次按鈕
            const tryAgainButton = document.getElementById('try-again');
            if (tryAgainButton) {
                tryAgainButton.addEventListener('click', () => {
                    console.log('再玩一次按鈕被點擊');
                    this.gameOverModal.style.display = 'none'; // 隱藏遊戲結束彈窗
                    this.isContinuing = false;
                    
                    // 顯示難度選擇彈窗
                    const difficultyModal = document.getElementById('difficultyModal');
                    if (difficultyModal) {
                        difficultyModal.style.display = 'flex'; // 直接設定 display 為 'flex' 來顯示彈窗
                    } else {
                        console.error('錯誤：找不到難度選擇彈窗 #difficultyModal');
                    }
                    
                    // 重置遊戲狀態
                    this.resetGame();
                });
            }

            // 返回選單按鈕
            const backToMenuButton = document.getElementById('back-to-menu');
            if (backToMenuButton) {
                backToMenuButton.addEventListener('click', () => {
                    console.log('返回主頁按鈕被點擊');
                    // 直接將頁面導向至 index.php
                    window.location.href = 'index.php';
                });
            }
        }

        updateTargetScoreDisplay() {
            if (this.targetScoreElement) {
                this.targetScoreElement.textContent = this.targetScore;
                console.log(`更新目標分數顯示為: ${this.targetScore}`);
            }
        }

        addNewTile() {
            console.log('開始添加新方塊...');
            const emptyCells = [];
            for (let i = 0; i < 4; i++) {
                for (let j = 0; j < 4; j++) {
                    if (this.board[i][j] === 0) {
                        emptyCells.push({ row: i, col: j });
                    }
                }
            }
            
            console.log(`找到 ${emptyCells.length} 個空格子`);
            
            if (emptyCells.length > 0) {
                const randomCell = emptyCells[Math.floor(Math.random() * emptyCells.length)];
                const value = Math.random() < 0.9 ? 2 : 4;
                this.board[randomCell.row][randomCell.col] = value;
                console.log(`在位置 (${randomCell.row}, ${randomCell.col}) 添加了值 ${value}`);
            } else {
                console.log('沒有空格子可以添加新方塊');
            }
        }

        updateDisplay() {
            console.log('開始更新顯示...');
            const cells = document.querySelectorAll('.cell');
            console.log(`找到 ${cells.length} 個格子元素`);
            
            cells.forEach(cell => {
                const row = parseInt(cell.dataset.row);
                const col = parseInt(cell.dataset.col);
                const value = this.board[row][col];
                
                // 更新格子內容
                cell.textContent = value || '';
                cell.className = 'cell';  // 重置類名
                if (value) {
                    cell.classList.add(`cell-${value}`);
                }
                
                console.log(`更新格子 (${row}, ${col}): 值 = ${value}`);
            });

            // 更新分數
            const scoreElement = document.getElementById('score');
            const bestScoreElement = document.getElementById('best-score');
            
            if (scoreElement) {
                scoreElement.textContent = this.score;
                console.log(`更新分數: ${this.score}`);
            } else {
                console.error('找不到分數元素 #score');
            }
            
            // 檢查並更新最高分數
            if (this.score > this.bestScore) {
                this.bestScore = this.score;
                console.log(`更新最高分數為: ${this.bestScore}`);
            }
            
            if (bestScoreElement) {
                bestScoreElement.textContent = this.bestScore;
                console.log(`更新最高分數: ${this.bestScore}`);
            } else {
                console.error('找不到最高分數元素 #best-score');
            }
            
            // 更新目標分數顯示
            this.updateTargetScoreDisplay();
        }

        moveLeft() {
            return this.move(row => {
                const newRow = row.filter(cell => cell !== 0);
                for (let i = 0; i < newRow.length - 1; i++) {
                    if (newRow[i] === newRow[i + 1]) {
                        newRow[i] *= 2;
                        this.score += newRow[i];
                        
                        // 檢查是否達到目標分數
                        if (this.score >= this.targetScore && !this.won) {
                            this.won = true;
                            this.gameOver = true;
                            console.log('移動過程中達到目標分數，立即停止遊戲');
                            // 立即顯示勝利彈窗
                            setTimeout(() => {
                                this.showWinModal();
                            }, 100);
                        }
                        
                        newRow.splice(i + 1, 1);
                    }
                }
                while (newRow.length < 4) {
                    newRow.push(0);
                }
                return newRow;
            });
        }

        moveRight() {
            return this.move(row => {
                const newRow = row.filter(cell => cell !== 0);
                for (let i = newRow.length - 1; i > 0; i--) {
                    if (newRow[i] === newRow[i - 1]) {
                        newRow[i] *= 2;
                        this.score += newRow[i];
                        
                        // 檢查是否達到目標分數
                        if (this.score >= this.targetScore && !this.won) {
                            this.won = true;
                            this.gameOver = true;
                            console.log('移動過程中達到目標分數，立即停止遊戲');
                            // 立即顯示勝利彈窗
                            setTimeout(() => {
                                this.showWinModal();
                            }, 100);
                        }
                        
                        newRow.splice(i - 1, 1);
                    }
                }
                while (newRow.length < 4) {
                    newRow.unshift(0);
                }
                return newRow;
            });
        }

        moveUp() {
            return this.move(col => {
                const newCol = col.filter(cell => cell !== 0);
                for (let i = 0; i < newCol.length - 1; i++) {
                    if (newCol[i] === newCol[i + 1]) {
                        newCol[i] *= 2;
                        this.score += newCol[i];
                        
                        // 檢查是否達到目標分數
                        if (this.score >= this.targetScore && !this.won) {
                            this.won = true;
                            this.gameOver = true;
                            console.log('移動過程中達到目標分數，立即停止遊戲');
                            // 立即顯示勝利彈窗
                            setTimeout(() => {
                                this.showWinModal();
                            }, 100);
                        }
                        
                        newCol.splice(i + 1, 1);
                    }
                }
                while (newCol.length < 4) {
                    newCol.push(0);
                }
                return newCol;
            }, true);
        }

        moveDown() {
            return this.move(col => {
                const newCol = col.filter(cell => cell !== 0);
                for (let i = newCol.length - 1; i > 0; i--) {
                    if (newCol[i] === newCol[i - 1]) {
                        newCol[i] *= 2;
                        this.score += newCol[i];
                        
                        // 檢查是否達到目標分數
                        if (this.score >= this.targetScore && !this.won) {
                            this.won = true;
                            this.gameOver = true;
                            console.log('移動過程中達到目標分數，立即停止遊戲');
                            // 立即顯示勝利彈窗
                            setTimeout(() => {
                                this.showWinModal();
                            }, 100);
                        }
                        
                        newCol.splice(i - 1, 1);
                    }
                }
                while (newCol.length < 4) {
                    newCol.unshift(0);
                }
                return newCol;
            }, true);
        }

        move(moveFunction, isVertical = false) {
            const oldBoard = JSON.stringify(this.board);
            
            if (isVertical) {
                for (let col = 0; col < 4; col++) {
                    const column = this.board.map(row => row[col]);
                    const newColumn = moveFunction(column);
                    for (let row = 0; row < 4; row++) {
                        this.board[row][col] = newColumn[row];
                    }
                }
            } else {
                for (let row = 0; row < 4; row++) {
                    this.board[row] = moveFunction([...this.board[row]]);
                }
            }

            return oldBoard !== JSON.stringify(this.board);
        }

        checkGameStatus() {
            console.log('=== 開始檢查遊戲狀態 ===');
            console.log('當前分數:', this.score);
            console.log('目標分數:', this.targetScore);
            console.log('已勝利狀態:', this.won);
            console.log('遊戲結束狀態:', this.gameOver);
            
            // 檢查勝利條件 - 添加更嚴格的檢查
            if (this.score >= this.targetScore && !this.won) {
                console.log('=== 遊戲勝利檢查 ===');
                console.log('當前分數:', this.score);
                console.log('目標分數:', this.targetScore);
                console.log('已勝利狀態:', this.won);
                
                // 確保分數真的達到了目標
                if (this.score >= this.targetScore) {
                    console.log('達到目標分數，遊戲勝利！');
                    this.won = true;
                    this.gameOver = true;
                    
                    // 立即顯示勝利彈窗
                    console.log('準備顯示勝利彈窗...');
                    setTimeout(() => {
                        console.log('執行顯示勝利彈窗');
                        this.showWinModal();
                    }, 100);
                    
                    return; // 立即返回，不再檢查其他條件
                }
            } else if (this.score >= this.targetScore && this.won) {
                console.log('已經勝利，跳過勝利檢查');
            } else {
                console.log('未達到目標分數，繼續遊戲');
            }

            // 只有在未勝利的情況下才檢查遊戲失敗條件
            if (!this.won) {
                // 檢查是否有空格子
                let hasEmptyCell = false;
                for (let i = 0; i < 4; i++) {
                    for (let j = 0; j < 4; j++) {
                        if (this.board[i][j] === 0) {
                            hasEmptyCell = true;
                            break;
                        }
                    }
                    if (hasEmptyCell) break;
                }

                // 如果沒有空格子，檢查是否還能移動
                if (!hasEmptyCell) {
                    let canMove = false;
                    
                    // 檢查水平方向
                    for (let i = 0; i < 4; i++) {
                        for (let j = 0; j < 3; j++) {
                            if (this.board[i][j] === this.board[i][j + 1] && this.board[i][j] !== 0) {
                                canMove = true;
                                break;
                            }
                        }
                        if (canMove) break;
                    }
                    
                    // 檢查垂直方向
                    if (!canMove) {
                        for (let i = 0; i < 3; i++) {
                            for (let j = 0; j < 4; j++) {
                                if (this.board[i][j] === this.board[i + 1][j] && this.board[i][j] !== 0) {
                                    canMove = true;
                                    break;
                                }
                            }
                            if (canMove) break;
                        }
                    }
                    
                    if (!canMove) {
                        console.log('無法移動，遊戲失敗');
                        this.gameOver = true;
                        this.isContinuing = false;
                        this.showGameOverModal();
                    }
                }
            }
            
            console.log('=== 遊戲狀態檢查完成 ===');
        }

        getDifficultyText() {
            switch (this.difficulty) {
                case 'easy':
                    return '簡單';
                case 'normal':
                case 'medium': // 保持向後兼容
                    return '普通';
                case 'hard':
                    return '困難';
                default:
                    return '簡單';
            }
        }

        showWinModal() {
            // 防止重複調用
            if (this.winModal.style.display === 'block') {
                console.log('勝利彈窗已顯示，忽略重複調用');
                return;
            }
            
            console.log('=== 顯示勝利彈窗 ===');
            // 確保遊戲狀態正確
            this.gameOver = true;
            this.won = true;
            
            const difficultyLabel = document.getElementById('win-difficulty');
            difficultyLabel.textContent = this.getDifficultyText();
            difficultyLabel.className = 'difficulty-label ' + this.difficulty;
            
            // 根據難度計算獎勵分數
            let rewardScore = 0;
            switch (this.difficulty) {
                case 'easy':
                    rewardScore = 20;
                    break;
                case 'normal':
                case 'medium': // 保持向後兼容
                    rewardScore = 50;
                    break;
                case 'hard':
                    rewardScore = 100;
                    break;
                default:
                    rewardScore = 20;
            }
            
            // 顯示遊戲分數和獎勵分數
            document.getElementById('win-game-score').textContent = this.score;
            document.getElementById('win-reward-score').textContent = rewardScore;
            document.getElementById('win-best-score').textContent = this.bestScore;
            
            // 自動呼叫 saveGameRecord，使用獎勵分數而不是遊戲分數
            if (typeof saveGameRecord === 'function') {
                console.log('遊戲勝利，呼叫 saveGameRecord，獎勵分數：', rewardScore);
                saveGameRecord(memberId, rewardScore, this.difficulty, 60);
            }
            this.winModal.style.display = 'block';
        }

        showGameOverModal() {
            // 確保只在失敗時調用此函數
            if (this.won) {
                console.log('遊戲已勝利，不顯示失敗彈窗');
                return;
            }

            // 調整標題為「遊戲失敗」
            const modalTitle = this.gameOverModal.querySelector('h2');
            if (modalTitle) {
                modalTitle.textContent = '遊戲失敗';
            }

            // 更新彈窗內的難度、分數等資訊
            const difficultyLabel = document.getElementById('game-over-difficulty');
            console.log('當前難度:', this.difficulty);
            console.log('難度文字:', this.getDifficultyText());
            difficultyLabel.textContent = this.getDifficultyText();
            difficultyLabel.className = 'difficulty-label ' + this.difficulty;
            
            document.getElementById('game-over-score').textContent = this.score;            
            
            // 遊戲失敗時記錄0分
            console.log('遊戲失敗，記錄0分');
            if (typeof saveGameRecord === 'function') {
                console.log('遊戲失敗，呼叫 saveGameRecord，分數：0');
                saveGameRecord(memberId, 0, this.difficulty, 60);
            }

            // 顯示彈窗
            this.gameOverModal.style.display = 'block';
        }
    

        togglePause() {
            this.isPaused = !this.isPaused;
            const pauseButton = document.getElementById('pauseBtn');
            if (pauseButton) {
                if (this.isPaused) {
                    pauseButton.textContent = '繼續遊戲';
                    pauseButton.classList.add('paused');
                } else {
                    pauseButton.textContent = '暫停遊戲';
                    pauseButton.classList.remove('paused');
                }
            }
        }

        endGame() {
            console.log('結束遊戲流程...');
            this.gameOver = true;
            this.isInitialized = false;
            
            // 更新最高分（如果需要，可以保存到資料庫）
            if (this.score > this.bestScore) {
                this.bestScore = this.score;
                // 注意：這裡不保存到 localStorage，因為我們從資料庫讀取
            }
            
            // 如果已經勝利，顯示勝利彈窗；否則顯示失敗彈窗
            if (this.won) {
                this.showWinModal();
            } else {
                this.showGameOverModal();
            }
            console.log('顯示遊戲結束彈窗');
        }

        resetGame() {
            console.log('重置遊戲...');
            if (this.isPaused) {
                this.togglePause();
            }
            
            // 清空遊戲板
            this.board = Array(4).fill().map(() => Array(4).fill(0));
            this.score = 0;
            this.gameOver = false;
            this.won = false;
            this.isContinuing = false;
            
            // 重新創建遊戲板
            this.createBoard();
            
            // 添加初始方塊
            console.log('添加初始方塊...');
            this.addNewTile();
            this.addNewTile();
            
            // 更新顯示
            this.updateDisplay();
            
            console.log('遊戲重置完成');
        }

        // 從資料庫讀取最高分數
        async loadBestScore() {
            console.log('開始從資料庫讀取最高分數...');
            try {
                const response = await fetch('get_high_score.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        game_id: 4, // 2048 遊戲的 ID
                        member_id: memberId
                    })
                });
                
                console.log('API 回應狀態:', response.status);
                const data = await response.json();
                console.log('API 回應數據:', data);
                
                if (data.success) {
                    this.bestScore = data.high_score || 0;
                    console.log(`從資料庫讀取最高分數: ${this.bestScore}`);
                } else {
                    console.log('無法從資料庫讀取最高分數，使用預設值 0');
                    this.bestScore = 0;
                }
            } catch (error) {
                console.error('讀取最高分數失敗:', error);
                this.bestScore = 0;
            }
            
            // 更新顯示
            this.updateBestScoreDisplay();
        }
        
        // 更新最高分數顯示
        updateBestScoreDisplay() {
            if (this.bestScoreElement) {
                this.bestScoreElement.textContent = this.bestScore;
                console.log(`更新最高分數顯示: ${this.bestScore}`);
            }
        }
    }

    // 創建遊戲實例並保存到全局變量
    window.game = new Game2048();
    console.log('遊戲實例已創建並保存到 window.game');

    async function saveGameRecord(member_id, score, difficulty, play_time) {
        console.log('=== 開始記錄遊戲分數 ===');
        console.log('會員ID:', member_id);
        console.log('記錄分數:', score);
        console.log('難度:', difficulty);
        console.log('遊戲時間:', play_time);
        console.log('=== 分數記錄詳情 ===');
        const res = await fetch(window.location.href, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                member_id,
                score,
                difficulty,
                play_time
            })
        });
        const result = await res.json();
        console.log('API回應', result);
        if (!result.success) {
            alert('儲存紀錄失敗: ' + result.message);
        }
        
        // 檢查並更新任務狀態
        if (difficulty === 'normal') {
            fetch("update_task_status.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({
                    task_type: "achievement",
                    difficulty: difficulty,
                    game_type: "2048"
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('任務狀態已更新');
                    // 清除成就快取，確保下次打開個人資訊時顯示最新成就
                    if (typeof clearAchievementsCache === 'function') {
                        clearAchievementsCache();
                    }
                } else {
                    console.error('更新任務狀態失敗:', data.message);
                }
            })
            .catch(error => {
                console.error('更新任務狀態時發生錯誤:', error);
            });
        }
    }
});
