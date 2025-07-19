// 等待 DOM 完全加載後再初始化遊戲
document.addEventListener('DOMContentLoaded', () => {
    console.log('DOM 加載完成，開始初始化遊戲');
    
    class Game2048 {
        constructor() {
            console.log('開始創建遊戲實例');
            this.board = Array(4).fill().map(() => Array(4).fill(0));
            this.score = 0;
            this.bestScore = parseInt(localStorage.getItem('bestScore')) || 0;
            this.targetScore = 1500;
            this.difficulty = 'easy';
            this.gameOver = false;
            this.won = false;
            this.isInitialized = false;
            this.isPaused = false;
            this.isContinuing = false;
            this.winModal = document.getElementById('win-modal');
            this.gameOverModal = document.getElementById('game-over-modal');
            
            console.log('遊戲實例基本屬性已初始化');
            
            // 初始化遊戲板
            this.createBoard();
            console.log('遊戲板已創建');
            
            // 設置事件監聽器
            this.setupEventListeners();
            this.setupWinModalListeners();
            this.setupGameOverModalListeners();
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
                        difficulty = 'medium';
                    } else if (button.classList.contains('hard')) {
                        difficulty = 'hard';
                    }
                    
                    this.difficulty = difficulty;
                    this.targetScore = parseInt(button.dataset.target) || 1500;
                    console.log('設置難度:', difficulty, '目標分數:', this.targetScore);
                    
                    // 切換到遊戲頁面
                    const difficultyPage = document.getElementById('difficulty-page');
                    const gamePage = document.getElementById('game-page');
                    
                    if (difficultyPage && gamePage) {
                        console.log('切換到遊戲頁面');
                        difficultyPage.style.display = 'none';
                        gamePage.style.display = 'block';
                        
                        // 重置遊戲狀態並開始新遊戲
                        this.isInitialized = false;
                        this.resetGame();
                        this.init();
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
                if (this.gameOver) {
                    console.log('遊戲已結束，忽略按鍵');
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
                    if (confirm('確定要結束遊戲並返回選單嗎？')) {
                        this.isContinuing = false;
                        this.endGame();
                        document.getElementById('game-page').style.display = 'none';
                        document.getElementById('difficulty-page').style.display = 'flex';
                    }
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
                    if (confirm('確定要重新開始遊戲嗎？')) {
                        this.isContinuing = false;
                        this.resetGame();
                    }
                };
            } else {
                console.error('錯誤：找不到重置按鈕 #resetBtn');
            }
            
            console.log('事件監聽器設置完成');
        }

        setupWinModalListeners() {
            // 繼續遊戲按鈕
            const continueButton = document.getElementById('continue-game');
            if (continueButton) {
                continueButton.addEventListener('click', () => {
                    this.winModal.style.display = 'none';
                    this.won = false;
                    this.isPaused = false;
                    this.isContinuing = true;
                    const pauseButton = document.getElementById('pause');
                    if (pauseButton) {
                        pauseButton.textContent = '暫停遊戲';
                        pauseButton.classList.remove('paused');
                    }
                });
            }

            // 新遊戲按鈕
            const newGameButton = document.getElementById('new-game');
            if (newGameButton) {
                newGameButton.addEventListener('click', () => {
                    this.winModal.style.display = 'none';
                    this.isContinuing = false;
                    document.getElementById('game-page').style.display = 'none';
                    document.getElementById('difficulty-page').style.display = 'flex';
                });
            }
        }

        setupGameOverModalListeners() {
            // 再試一次按鈕
            const tryAgainButton = document.getElementById('try-again');
            if (tryAgainButton) {
                tryAgainButton.addEventListener('click', () => {
                    this.gameOverModal.style.display = 'none';
                    this.isContinuing = false;
                    this.resetGame();
                });
            }

            // 返回選單按鈕
            const backToMenuButton = document.getElementById('back-to-menu');
            if (backToMenuButton) {
                backToMenuButton.addEventListener('click', () => {
                    window.location.href = 'index.php';
                    this.gameOverModal.style.display = 'none';
                    this.isContinuing = false;
                    document.getElementById('game-page').style.display = 'none';
                    document.getElementById('difficulty-page').style.display = 'flex';
                });
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
            
            if (bestScoreElement) {
                bestScoreElement.textContent = this.bestScore;
                console.log(`更新最高分數: ${this.bestScore}`);
            } else {
                console.error('找不到最高分數元素 #best-score');
            }
        }

        moveLeft() {
            return this.move(row => {
                const newRow = row.filter(cell => cell !== 0);
                for (let i = 0; i < newRow.length - 1; i++) {
                    if (newRow[i] === newRow[i + 1]) {
                        newRow[i] *= 2;
                        this.score += newRow[i];
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
            if (!this.isContinuing && this.score >= this.targetScore && !this.won) {
                this.won = true;
                this.showWinModal();
                return;
            }

            let hasEmptyCell = false;
            for (let i = 0; i < 4; i++) {
                for (let j = 0; j < 4; j++) {
                    if (this.board[i][j] === 0) {
                        hasEmptyCell = true;
                        return;
                    }
                }
            }

            if (!hasEmptyCell) {
                let canMove = false;
                for (let i = 0; i < 4; i++) {
                    for (let j = 0; j < 3; j++) {
                        if (this.board[i][j] === this.board[i][j + 1]) {
                            canMove = true;
                            return;
                        }
                    }
                }
                for (let i = 0; i < 3; i++) {
                    for (let j = 0; j < 4; j++) {
                        if (this.board[i][j] === this.board[i + 1][j]) {
                            canMove = true;
                            return;
                        }
                    }
                }
                if (!canMove) {
                    this.gameOver = true;
                    this.isContinuing = false;
                    this.showGameOverModal();
                }
            }
        }

        getDifficultyText() {
            switch (this.difficulty) {
                case 'easy':
                    return '簡單';
                case 'medium':
                    return '普通';
                case 'hard':
                    return '困難';
                default:
                    return '簡單';
            }
        }

        showWinModal() {
            const difficultyLabel = document.getElementById('win-difficulty');
            difficultyLabel.textContent = this.getDifficultyText();
            difficultyLabel.className = 'difficulty-label ' + this.difficulty;
            
            document.getElementById('win-score').textContent = this.score;
            document.getElementById('win-best-score').textContent = this.bestScore;
            document.getElementById('win-target').textContent = this.targetScore;
            
            // 自動呼叫 saveGameRecord
            if (typeof saveGameRecord === 'function') {
                console.log('遊戲勝利，呼叫 saveGameRecord');
                saveGameRecord(8, this.score, this.difficulty, 60);
            }
            this.winModal.style.display = 'block';
        }

        showGameOverModal() {
            const difficultyLabel = document.getElementById('game-over-difficulty');
            difficultyLabel.textContent = this.getDifficultyText();
            difficultyLabel.className = 'difficulty-label ' + this.difficulty;
            
            document.getElementById('game-over-score').textContent = this.score;
            document.getElementById('game-over-best-score').textContent = this.bestScore;
            document.getElementById('game-over-target').textContent = this.targetScore;
            
            if (this.score > this.bestScore) {
                this.bestScore = this.score;
                localStorage.setItem('bestScore', this.bestScore);
                document.getElementById('game-over-best-score').textContent = this.bestScore;
            }
            // 自動呼叫 saveGameRecord
            if (typeof saveGameRecord === 'function') {
                console.log('遊戲失敗，呼叫 saveGameRecord');
                saveGameRecord(8, this.score, this.difficulty, 60);
            }
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
            this.gameOver = true;
            this.isInitialized = false;
            if (this.score > this.bestScore) {
                this.bestScore = this.score;
                localStorage.setItem('bestScore', this.bestScore);
            }
            alert(`遊戲結束！\n你的分數是: ${this.score}\n最高分數是: ${this.bestScore}`);
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
    }

    // 創建遊戲實例並保存到全局變量
    window.game = new Game2048();
    console.log('遊戲實例已創建並保存到 window.game');

    async function saveGameRecord(member_id, score, difficulty, play_time) {
        console.log('送出紀錄', {member_id, score, difficulty, play_time});
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
        if (difficulty === 'medium') {
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
