// 遊戲變數
const game = document.getElementById('game');
const basket = document.getElementById('basket');
const scoreBoard = document.getElementById('score');
const timerDisplay = document.getElementById('timer');
const difficultyModal = document.getElementById('difficulty-modal');
const gameScreen = document.getElementById('game-container');
const pauseBtn = document.getElementById('pauseBtn');
const resumeBtn = document.getElementById('resumeBtn');
const endBtn = document.getElementById('endBtn');
const resetBtn = document.getElementById('resetBtn');
const countdownOverlay = document.getElementById('countdownOverlay');

// 音頻元素
// const bgm = document.getElementById('bgm'); // 背景音樂已移除
const catchSound = document.getElementById('catchSound');
const bombSound = document.getElementById('bombSound');
const gameOverSound = document.getElementById('gameOverSound');

// 音效播放函數 - 高效能版本
function playCatchSound() {
    console.log('嘗試播放接蛋音效...');
    console.log('catchSound元素:', catchSound);
    console.log('catchSound readyState:', catchSound ? catchSound.readyState : 'undefined');
    
    if (catchSound) {
        // 直接播放，不使用 cloneNode
        catchSound.currentTime = 0; // 重置播放位置
        catchSound.volume = 0.8;
        catchSound.play().then(() => {
            console.log('接蛋音效播放成功');
        }).catch(e => {
            console.log('接蛋音效播放失敗:', e);
        });
    } else {
        console.log('接蛋音效元素不存在');
    }
}

function playBombSound() {
    console.log('嘗試播放炸彈音效...');
    console.log('bombSound元素:', bombSound);
    console.log('bombSound readyState:', bombSound ? bombSound.readyState : 'undefined');
    
    if (bombSound) {
        // 直接播放，不使用 cloneNode
        bombSound.currentTime = 0; // 重置播放位置
        bombSound.volume = 0.3;
        bombSound.play().then(() => {
            console.log('炸彈音效播放成功');
        }).catch(e => {
            console.log('炸彈音效播放失敗:', e);
        });
    } else {
        console.log('炸彈音效元素不存在');
    }
}

function playGameOverSound() {
    console.log('嘗試播放遊戲結束音效...');
    console.log('gameOverSound元素:', gameOverSound);
    console.log('gameOverSound readyState:', gameOverSound ? gameOverSound.readyState : 'undefined');
    
    if (gameOverSound) {
        // 直接播放，不使用 cloneNode
        gameOverSound.currentTime = 0; // 重置播放位置
        gameOverSound.volume = 0.5;
        gameOverSound.play().then(() => {
            console.log('遊戲結束音效播放成功');
        }).catch(e => {
            console.log('遊戲結束音效播放失敗:', e);
        });
    } else {
        console.log('遊戲結束音效元素不存在');
    }
}

let score = 0;
let highScore = 0;
let difficultyHighScores = {
    easy: 0,
    normal: 0,
    hard: 0
};
let timeLeft = 60;
let totalTime = 60; // 記錄總時間用於計算
let itemInterval;
let countdown;
let gameStarted = false;
let gamePaused = false;
let currentDifficulty = 'easy';

// 選擇難度
function selectDifficulty(difficulty) {
    currentDifficulty = difficulty;
    
    // 顯示該難度的過關分數
    let passScore;
    if (difficulty === 'easy') {
        passScore = 200;
    } else if (difficulty === 'normal') {
        passScore = 450;
    } else if (difficulty === 'hard') {
        passScore = 600;
    }
    document.getElementById('high-score').textContent = passScore;
    
    // 仍然保存最高分數用於統計，但不顯示
    const savedHighScore = localStorage.getItem(`egg_highscore_${difficulty}`);
    if (savedHighScore) {
        difficultyHighScores[difficulty] = parseInt(savedHighScore);
        highScore = difficultyHighScores[difficulty];
    } else {
        highScore = 0;
    }
    
    // 根據難度設定時間
    if (difficulty === 'easy') {
        timeLeft = 60;
        totalTime = 60;
    } else if (difficulty === 'normal') {
        timeLeft = 80;
        totalTime = 80;
    } else if (difficulty === 'hard') {
        timeLeft = 120;
        totalTime = 120;
    }
    fetch("Catch-Egg-Game.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/x-www-form-urlencoded",
            "X-Requested-With": "XMLHttpRequest"
        },
        body: `action=start_game&difficulty=${difficulty}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('difficulty-modal').style.display = 'none';
            // 倒數期間隱藏所有按鈕
            document.getElementById('endBtn').classList.add('hidden');
            document.getElementById('resetBtn').classList.add('hidden');
            document.getElementById('pauseBtn').classList.add('hidden');
            document.getElementById('resumeBtn').classList.add('hidden');
            showCountdown(startGameTimer);
        }
    });
}

// 更新分數顯示
function updateScore() {
    document.getElementById('score').textContent = score;
    
    // 更新最高分數記錄（但不顯示，只保存用於統計）
    if (score > highScore) {
        highScore = score;
        
        // 保存該難度的最高分數
        difficultyHighScores[currentDifficulty] = highScore;
        localStorage.setItem(`egg_highscore_${currentDifficulty}`, highScore.toString());
    }
    // 注意：當score < highScore時（比如接到炸彈），最高分數保持不變，這是正確的行為
    // 過關分數顯示保持不變，因為它是固定的目標分數
    
    // 檢查是否達到目標分數
    let targetScore;
    if (currentDifficulty === 'easy') {
        targetScore = 200;
    } else if (currentDifficulty === 'normal') {
        targetScore = 450;
    } else if (currentDifficulty === 'hard') {
        targetScore = 600;
    }
    
    if (score >= targetScore && gameStarted) {
        // 達到目標分數，立即結束遊戲並顯示勝利
        endGame(true);
    }
}
 
// 顯示開始倒數
function showCountdown(callback) {
    countdownOverlay.style.display = 'block';
    // 倒數期間禁用籃子滑動
    basket.style.pointerEvents = 'none';
    basket.style.cursor = 'default';
    
    let countdownTime = 5;
    const countdownElement = document.createElement('div');
    countdownElement.style.position = 'absolute';
    countdownElement.style.top = '50%';
    countdownElement.style.left = '50%';
    countdownElement.style.transform = 'translate(-50%, -50%)';
    countdownElement.style.fontSize = '48px';
    countdownElement.style.color = 'red';
    countdownElement.innerText = countdownTime;
    game.appendChild(countdownElement);

    const interval = setInterval(() => {
        countdownTime--;
        countdownElement.innerText = countdownTime;
        if (countdownTime === 0) {
            clearInterval(interval);
            if (countdownElement.parentNode) {
                game.removeChild(countdownElement);
            }
            // 倒數結束後啟用籃子滑動
            basket.style.pointerEvents = 'auto';
            basket.style.cursor = 'grab';
            callback();
        }
    }, 1000);
}

// 開始遊戲
function startGameTimer() {
    score = 0;
    updateScore();
    document.getElementById('timer').textContent = timeLeft;
    gameStarted = true;
    gamePaused = false;
    pauseBtn.classList.remove('hidden');
    resumeBtn.classList.add('hidden');
    endBtn.classList.remove('hidden');
    resetBtn.classList.remove('hidden');
    
    // 遊戲開始時再次預熱音效，確保即時播放
    if (catchSound) {
        catchSound.currentTime = 0;
        catchSound.volume = 0;
        catchSound.play().then(() => {
            catchSound.pause();
            catchSound.currentTime = 0;
            catchSound.volume = 0.8;
        }).catch(e => console.log('遊戲開始音效預熱失敗:', e));
    }
    
    if (bombSound) {
        bombSound.currentTime = 0;
        bombSound.volume = 0;
        bombSound.play().then(() => {
            bombSound.pause();
            bombSound.currentTime = 0;
            bombSound.volume = 0.3;
        }).catch(e => console.log('遊戲開始音效預熱失敗:', e));
    }

    // 確保籃子置中
    const centerBasketForGame = () => {
      const basketWidth = basket.offsetWidth;
      const gameWidth = game.offsetWidth;
      if (basketWidth > 0 && gameWidth > 0) {
        const centerLeft = (gameWidth - basketWidth) / 2;
        basket.style.transform = 'none';
        basket.style.left = centerLeft + 'px';
        console.log('遊戲開始時籃子置中:', centerLeft);
      } else {
        setTimeout(centerBasketForGame, 50);
      }
    };
    centerBasketForGame();

    // bgm.play(); // 移除背景音樂，只保留接到物品的音效

    let dropInterval = 600;
    if (currentDifficulty === 'normal') dropInterval = 400;
    else if (currentDifficulty === 'hard') dropInterval = 350;

    itemInterval = setInterval(dropItem, dropInterval);

    countdown = setInterval(() => {
        if (!gamePaused) {
            timeLeft--;
            document.getElementById('timer').textContent = timeLeft;
            if (timeLeft <= 0) {
                clearInterval(itemInterval);
                clearInterval(countdown);
                let bonusScore = 0;
                let baseScore = score;
                
                // 時間結束時檢查是否達到目標分數
                let targetScore = currentDifficulty === 'easy' ? 200 : currentDifficulty === 'normal' ? 450 : 600;
                if (score >= targetScore) {
                    // 達到目標分數，勝利
                    let bonusScore = currentDifficulty === 'easy' ? 20 : currentDifficulty === 'normal' ? 50 : 100;
                    showEggGameOver(true, score, targetScore, bonusScore);
                } else {
                    // 未達到目標分數，失敗
                    showEggGameOver(false, score, targetScore, 0);
                }
                endGame();
            }
        }
    }, 1000);
}

// 重來遊戲
function resetGame() {
  clearInterval(itemInterval);
  clearInterval(countdown);
 
  // bgm.pause(); // 移除背景音樂
  // bgm.currentTime = 0; // 移除背景音樂
 
  score = 0;
  // timeLeft 保持當前難度的設定
  gameStarted = false;
  gamePaused = false;
 
  updateScore();
  timerDisplay.textContent = timeLeft;
 
  // 安全地移除所有物品
  const items = document.querySelectorAll('.gold, .white, .bomb');
  items.forEach(item => {
    if (item.parentNode && item.parentNode === game) {
      game.removeChild(item);
    }
  });
 
    document.getElementById('difficulty-modal').style.display = 'flex';
    document.getElementById('endBtn').classList.add('hidden');
    document.getElementById('resetBtn').classList.add('hidden');
    document.getElementById('pauseBtn').classList.add('hidden');
    document.getElementById('resumeBtn').classList.add('hidden');

  // 自動置中籃子 - 使用絕對位置而不是百分比
  const centerBasket = () => {
    const basketWidth = basket.offsetWidth;
    const gameWidth = game.offsetWidth;
    if (basketWidth > 0 && gameWidth > 0) {
      const centerLeft = (gameWidth - basketWidth) / 2;
      basket.style.transform = `translateX(${centerLeft}px)`;
      basket.style.left = '0px';
      console.log('重置時籃子置中:', centerLeft);
    } else {
      setTimeout(centerBasket, 50);
    }
  };
  centerBasket();
 
  isDragging = false;
  touchStartX = 0;
  basket.style.pointerEvents = 'auto';
  basket.style.cursor = 'grab';
}
 
// 籃子滑鼠拖曳
let isDragging = false;
basket.addEventListener('mousedown', (e) => {
    if (gameStarted && !gamePaused && basket.style.pointerEvents !== 'none') {
        isDragging = true;
        e.preventDefault();
    }
});
document.addEventListener('mouseup', () => {
    isDragging = false;
});
document.addEventListener('mousemove', (e) => {
    if (isDragging && gameStarted && !gamePaused && basket.style.pointerEvents !== 'none') {
        e.preventDefault();
        const gameRect = game.getBoundingClientRect();
        const mouseX = e.clientX - gameRect.left;
        const basketWidth = basket.offsetWidth;
        const maxLeft = game.offsetWidth - basketWidth;
        
        let newLeft = mouseX - (basketWidth / 2);
        newLeft = Math.max(0, Math.min(newLeft, maxLeft));
        
        // 使用 transform 提升效能
        basket.style.transform = `translateX(${newLeft}px)`;
        basket.style.left = '0px';
    }
});

// 籃子觸控拖曳 - 修正版本
let touchStartX = 0;
let isTouching = false;

// 觸控開始
basket.addEventListener('touchstart', (e) => {
    if (gameStarted && !gamePaused && basket.style.pointerEvents !== 'none') {
        isTouching = true;
        touchStartX = e.touches[0].clientX;
        e.preventDefault();
        e.stopPropagation();
        console.log('觸控開始:', touchStartX);
    }
}, { passive: false });

// 觸控移動
document.addEventListener('touchmove', (e) => {
    if (isTouching && touchStartX !== 0 && gameStarted && !gamePaused && basket.style.pointerEvents !== 'none') {
        e.preventDefault();
        e.stopPropagation();
        
        const gameRect = game.getBoundingClientRect();
        const touchX = e.touches[0].clientX - gameRect.left;
        const basketWidth = basket.offsetWidth;
        const maxLeft = game.offsetWidth - basketWidth;
        
        let newLeft = touchX - (basketWidth / 2);
        newLeft = Math.max(0, Math.min(newLeft, maxLeft));
        
        // 使用 transform 提升效能
        basket.style.transform = `translateX(${newLeft}px)`;
        basket.style.left = '0px';
        
        console.log('觸控移動:', {
            touchX: touchX,
            newLeft: newLeft,
            basketWidth: basketWidth,
            maxLeft: maxLeft
        });
    }
}, { passive: false });

// 觸控結束
document.addEventListener('touchend', (e) => {
    if (isTouching) {
        e.preventDefault();
        e.stopPropagation();
        console.log('觸控結束');
        isTouching = false;
        touchStartX = 0;
    }
}, { passive: false });

// 觸控取消
document.addEventListener('touchcancel', (e) => {
    if (isTouching) {
        e.preventDefault();
        e.stopPropagation();
        console.log('觸控取消');
        isTouching = false;
        touchStartX = 0;
    }
}, { passive: false });

// 鍵盤左右鍵移動
document.addEventListener('keydown', (e) => {
    if (gameStarted && !gamePaused && basket.style.pointerEvents !== 'none') {
        const currentLeft = parseInt(basket.style.left) || 0;
        const moveDistance = 10;
        const maxLeft = game.offsetWidth - basket.offsetWidth;
        
        if (e.key === 'ArrowLeft') {
            const newLeft = Math.max(0, currentLeft - moveDistance);
            basket.style.transform = `translateX(${newLeft}px)`;
            basket.style.left = '0px';
        } else if (e.key === 'ArrowRight') {
            const newLeft = Math.min(maxLeft, currentLeft + moveDistance);
            basket.style.transform = `translateX(${newLeft}px)`;
            basket.style.left = '0px';
        }
    }
});

// 優化的碰撞檢測函數
function checkItemCollision(item, itemLeft, itemWidth, itemTop) {
    const itemCenter = itemLeft + (itemWidth / 2);
    const itemBottom = itemTop + itemWidth;
    
    // 獲取籃子的實際位置和大小 - 優化版本
    const basketRect = basket.getBoundingClientRect();
    const gameRect = game.getBoundingClientRect();
    const basketX = basketRect.left - gameRect.left;
    const basketWidth = basketRect.width;
    const basketHeight = basketRect.height;
    
    // 計算碰撞範圍 - 擴大檢測範圍
    const basketLeft = basketX - 10; // 左邊擴大10px
    const basketRight = basketX + basketWidth + 10; // 右邊擴大10px
    const basketTop = game.offsetHeight - basketHeight - 20; // 上邊擴大20px
    
    // 更寬鬆的碰撞檢測
    return itemCenter >= basketLeft && itemCenter <= basketRight && itemBottom >= basketTop;
}

// 掉落物品
function dropItem() {
    if (gamePaused || !gameStarted) return;
    const types = ['gold', 'gold', 'gold', 'white', 'white', 'bomb'];
    const type = types[Math.floor(Math.random() * types.length)];
    const item = document.createElement('div');
    item.className = type;
    item.setAttribute('data-type', type);
    item.innerText = type === 'bomb' ? '💣' : '';
    if (type === 'gold') {
        const img = document.createElement('img');
        img.src = 'img/egg.png';
        img.alt = '金蛋';
        img.style.width = '50px';
        img.style.height = '50px';
        item.appendChild(img);
    } else if (type === 'white') {
        const img = document.createElement('img');
        img.src = 'img/catch_egg.png';
        img.alt = '白蛋';
        img.style.width = '55px';
        img.style.height = '55px';
        item.appendChild(img);
    }
    item.style.position = 'absolute';
    item.style.left = Math.floor(Math.random() * (game.offsetWidth - 50)) + 'px';
    item.style.top = '0px';
    game.appendChild(item);
    let isScored = false;
    
    item.fallInterval = setInterval(() => {
        if (gamePaused || !gameStarted) return;
        const top = parseInt(item.style.top);
        if (top >= game.offsetHeight - 120) {
            const itemLeft = parseInt(item.style.left);
            const itemWidth = 50;
            
            // 使用優化的碰撞檢測函數
            if (checkItemCollision(item, itemLeft, itemWidth, top) && !isScored && !gamePaused && gameStarted) {
                isScored = true;
                const type = item.getAttribute('data-type');
                
                // 播放音效 - 只有物品碰到籃子時才播放
                if (type === 'gold') {
                    score += 10;
                    playCatchSound();
                } else if (type === 'white') {
                    score += 3;
                    playCatchSound();
                } else if (type === 'bomb') {
                    score -= 20;
                    playBombSound();
                }
                
                updateScore();
                
                // 讓物品進入籃子後有視覺效果
                item.style.transition = 'all 0.3s ease';
                item.style.transform = 'scale(0.8)';
                item.style.opacity = '0.7';
                
                // 延遲移除物品，讓玩家看到接到的效果
                setTimeout(() => {
                    if (item.parentNode) {
        if (item.parentNode) {
            game.removeChild(item);
        }
                    }
                    clearInterval(item.fallInterval);
                }, 300);
            } else if (top >= game.offsetHeight) {
                        if (item.parentNode) {
                    game.removeChild(item);
                }
                clearInterval(item.fallInterval);
            } else {
                let speed = 3;
                if (currentDifficulty === 'normal') speed = 5;
                else if (currentDifficulty === 'hard') speed = 5;
                item.style.top = (top + speed) + 'px';
            }
        } else {
            let speed = 3;
            if (currentDifficulty === 'normal') speed = 5;
            else if (currentDifficulty === 'hard') speed = 5;
            item.style.top = (top + speed) + 'px';
        }
    }, 16); // 使用60fps的更新頻率，更流暢
}

// 暫停遊戲
function pauseGame() {
    gamePaused = true;
    pauseBtn.classList.add('hidden');
    resumeBtn.classList.remove('hidden');
    clearInterval(itemInterval);
    clearInterval(countdown);
    // bgm.pause(); // 移除背景音樂
    
    const items = document.querySelectorAll('.gold, .white, .bomb');
    items.forEach(item => {
        if (item.fallInterval) {
            clearInterval(item.fallInterval);
            item.fallInterval = null;
        }
    });
    isDragging = false;
    touchStartX = 0;
    basket.style.pointerEvents = 'none';
    basket.style.cursor = 'default';
}

// 繼續遊戲
function resumeGame() {
    gamePaused = false;
    pauseBtn.classList.remove('hidden');
    resumeBtn.classList.add('hidden');
    // bgm.play(); // 移除背景音樂，只保留接到物品的音效
    basket.style.pointerEvents = 'none';
    basket.style.cursor = 'default';
    
    let countdownTime = 3;
    const countdownElement = document.createElement('div');
    countdownElement.style.position = 'absolute';
    countdownElement.style.top = '50%';
    countdownElement.style.left = '50%';
    countdownElement.style.transform = 'translate(-50%, -50%)';
    countdownElement.style.fontSize = '48px';
    countdownElement.style.color = 'red';
    countdownElement.innerText = countdownTime;
    game.appendChild(countdownElement);
    
    // 在倒數期間保持物品暫停
    const items = document.querySelectorAll('.gold, .white, .bomb');
    items.forEach(item => {
        if (item.fallInterval) {
            clearInterval(item.fallInterval);
            item.fallInterval = null;
        }
    });
    
    const interval = setInterval(() => {
        countdownTime--;
        countdownElement.innerText = countdownTime;
        if (countdownTime === 0) {
            clearInterval(interval);
            if (countdownElement.parentNode) {
                game.removeChild(countdownElement);
            }
            basket.style.pointerEvents = 'auto';
            basket.style.cursor = 'grab';
            
            // 倒數結束後才開始掉落新物品和恢復現有物品的移動
            let dropInterval = 600;
            if (currentDifficulty === 'normal') dropInterval = 400;
            else if (currentDifficulty === 'hard') dropInterval = 350;
            itemInterval = setInterval(dropItem, dropInterval);
            
            // 恢復現有物品的移動
            items.forEach(item => {
                if (!item.fallInterval) {
                    item.fallInterval = setInterval(() => {
                        if (gamePaused || !gameStarted) return;
                        const top = parseInt(item.style.top);
                        if (top >= game.offsetHeight - 120) {
                            const itemLeft = parseInt(item.style.left);
                            const itemWidth = 50;
                            const itemCenter = itemLeft + (itemWidth / 2);
                            
                            // 獲取籃子的實際位置和大小
                            const basketRect = basket.getBoundingClientRect();
                            const gameRect = game.getBoundingClientRect();
                            const basketX = basketRect.left - gameRect.left;
                            const basketWidth = basketRect.width;
                            const basketHeight = basketRect.height;
                            
                            // 計算碰撞範圍 - 擴大檢測範圍
                            const basketLeft = basketX - 10; // 左邊擴大10px
                            const basketRight = basketX + basketWidth + 10; // 右邊擴大10px
                            const basketTop = game.offsetHeight - basketHeight - 20; // 上邊擴大20px
                            
                            // 物品底部位置
                            const itemBottom = top + itemWidth;
                            
                            // 更寬鬆的碰撞檢測
                            if (itemCenter >= basketLeft && itemCenter <= basketRight && itemBottom >= basketTop && !gamePaused && gameStarted) {
                                const type = item.getAttribute('data-type');
                                
                                // 播放音效 - 只有物品碰到籃子時才播放
                                if (type === 'gold') {
                                    score += 10;
                                    playCatchSound();
                                } else if (type === 'white') {
                                    score += 3;
                                    playCatchSound();
                                } else if (type === 'bomb') {
                                    score -= 20;
                                    playBombSound();
                                }
                                
                                updateScore();
                                
                                // 讓物品進入籃子後有視覺效果
                                item.style.transition = 'all 0.3s ease';
                                item.style.transform = 'scale(0.8)';
                                item.style.opacity = '0.7';
                                
                                // 延遲移除物品，讓玩家看到接到的效果
                                setTimeout(() => {
                                    if (item.parentNode) {
        if (item.parentNode) {
            game.removeChild(item);
        }
                                    }
                                    clearInterval(item.fallInterval);
                                }, 300);
                            } else if (top >= game.offsetHeight) {
        if (item.parentNode) {
            game.removeChild(item);
        }
                                clearInterval(item.fallInterval);
                            } else {
                                let speed = 3;
                                if (currentDifficulty === 'normal') speed = 5;
                                else if (currentDifficulty === 'hard') speed = 5;
                                item.style.top = (top + speed) + 'px';
                            }
                        } else {
                            let speed = 3;
                            if (currentDifficulty === 'normal') speed = 5;
                            else if (currentDifficulty === 'hard') speed = 5;
                            item.style.top = (top + speed) + 'px';
                        }
                    }, 16); // 使用60fps的更新頻率，更流暢
                }
            });
            
            // 重新開始計時器
            countdown = setInterval(() => {
                if (!gamePaused) {
                    timeLeft--;
                    timerDisplay.textContent = timeLeft;
                    if (timeLeft <= 0) {
                        clearInterval(itemInterval);
                        clearInterval(countdown);
                        let bonusScore = 0;
                        let baseScore = score;
                        // 時間結束時檢查是否達到目標分數
                        let targetScore = currentDifficulty === 'easy' ? 200 : currentDifficulty === 'normal' ? 450 : 600;
                        if (score >= targetScore) {
                            // 達到目標分數，勝利
                            let bonusScore = currentDifficulty === 'easy' ? 20 : currentDifficulty === 'normal' ? 50 : 100;
                            showEggGameOver(true, score, targetScore, bonusScore);
                        } else {
                            // 未達到目標分數，失敗
                            showEggGameOver(false, score, targetScore, 0);
                        }
                        endGame();
                    }
                }
            }, 1000);
        }
    }, 1000);
}

// 結束遊戲
function endGame(isWin = false, isManualExit = false) {
    gameStarted = false;  // 重置遊戲狀態
    gamePaused = false;   // 重置暫停狀態
    clearInterval(itemInterval);
    clearInterval(countdown);
    
    // 清理所有掉落的物品
    const items = game.querySelectorAll('.gold, .white, .bomb');
    items.forEach(item => {
        if (item.fallInterval) {
            clearInterval(item.fallInterval);
        }
                        if (item.parentNode) {
                    game.removeChild(item);
                }
    });
    
    // bgm.pause(); // 移除背景音樂
    // bgm.currentTime = 0; // 移除背景音樂
    
    // 遊戲結束音效已移除 - 只有接到物品時才有音效
    
    // 確保 totalTime 有正確的值
    if (!totalTime) {
        if (currentDifficulty === 'easy') totalTime = 60;
        else if (currentDifficulty === 'normal') totalTime = 80;
        else if (currentDifficulty === 'hard') totalTime = 120;
        else totalTime = 60; // 默認值
    }
    
    // 計算獎勵分數
    let bonusScore = 0;
    let targetScore = currentDifficulty === 'easy' ? 200 : currentDifficulty === 'normal' ? 450 : 600;
    
    // 檢查是否真正達到目標分數
    const actuallyWon = score >= targetScore;
    
    if (actuallyWon) {
        // 勝利
        if (currentDifficulty === 'easy') bonusScore = 20;
        else if (currentDifficulty === 'normal') bonusScore = 50;
        else if (currentDifficulty === 'hard') bonusScore = 100;
        showEggGameOver(true, score, targetScore, bonusScore);
    } else {
        // 失敗
        showEggGameOver(false, score, targetScore, 0);
    }
    
    // 只保存獎勵分數到資料庫
    const memberId = window.memberId || localStorage.getItem('member_id') || 8;
    fetch("Catch-Egg-Game.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/x-www-form-urlencoded",
            "X-Requested-With": "XMLHttpRequest"
        },
        body: `action=end_game&score=${bonusScore}&member_id=${memberId}&play_time=${gameTime - timeLeft}&is_manual_exit=${isManualExit ? '1' : '0'}`
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            console.error('儲存遊戲結果失敗:', data.message);
        }
    })
    .catch(error => {
        console.error('儲存遊戲結果時發生錯誤:', error);
    });
    
    // 重置遊戲狀態
    gameStarted = false;
    gamePaused = false;
    document.getElementById('difficulty-modal').style.display = 'none';
}

// 說明彈窗控制
function showEggHelp() {
    document.getElementById('egg-help-modal').classList.remove('hidden');
    
    // 初始化視頻播放邏輯
    initEggVideoPlayback();
    
    // 確保下一步按鈕事件被正確綁定
    setTimeout(() => {
        const nextStepButton = document.getElementById('egg-next-step-button');
        if (nextStepButton) {
            nextStepButton.onclick = goToEggNextStep;
            console.log('接金蛋下一步按鈕事件已綁定');
        }
    }, 100);
}

function closeEggHelpModal() {
    // 停止視頻播放
    const video = document.getElementById('egg-current-video');
    if (video) {
        video.pause();
        video.currentTime = 0; // 重置到開始位置
    }
    
    // 隱藏說明彈窗
    document.getElementById('egg-help-modal').classList.add('hidden');
}

// 初始化接金蛋視頻連續播放
function initEggVideoPlayback() {
    const video = document.getElementById('egg-current-video');
    const instructionText = document.getElementById('egg-instruction-text');
    const stepIndicator = document.getElementById('egg-step-indicator');
    const nextStepBtn = document.getElementById('egg-next-step-btn');
    const prevStepBtn = document.getElementById('egg-prev-step-btn');
    
    // 清除之前的事件監聽器
    video.removeEventListener('ended', handleEggVideoEnd);
    
    // 設置第一個視頻
    video.src = 'gd/egg1.mp4';
    instructionText.textContent = '先選擇遊戲困難度';
    stepIndicator.textContent = '步驟 1/2';
    
    // 設置當前視頻標記
    video.setAttribute('data-current-video', 'egg1');
    
    // 顯示下一步按鈕，隱藏上一步按鈕
    nextStepBtn.style.display = 'block';
    prevStepBtn.style.display = 'none';
    
    // 添加視頻結束事件監聽器
    video.addEventListener('ended', handleEggVideoEnd);
    
    // 強制加載視頻
    video.load();
    
    // 添加下一步按鈕點擊事件
    const nextStepButton = document.getElementById('egg-next-step-button');
    if (nextStepButton) {
        nextStepButton.onclick = goToEggNextStep;
        console.log('接金蛋下一步按鈕事件已綁定到 initEggVideoPlayback');
    } else {
        console.error('找不到接金蛋下一步按鈕元素');
    }
}

// 處理接金蛋視頻結束事件
function handleEggVideoEnd() {
    const video = document.getElementById('egg-current-video');
    const currentVideo = video.getAttribute('data-current-video');
    
    console.log('接金蛋視頻結束事件觸發，當前視頻：', currentVideo);
    
    if (currentVideo === 'egg1') {
        // 第一個視頻播完，等待用戶點擊下一步
        console.log('第一個接金蛋視頻播完，等待用戶點擊下一步');
    } else if (currentVideo === 'egg2') {
        // 第二個視頻播完，自動回到第一個
        console.log('第二個接金蛋視頻播完，自動回到第一個');
        goToEggFirstStep();
    }
}

// 前往接金蛋下一步
function goToEggNextStep() {
    const video = document.getElementById('egg-current-video');
    const instructionText = document.getElementById('egg-instruction-text');
    const stepIndicator = document.getElementById('egg-step-indicator');
    const nextStepBtn = document.getElementById('egg-next-step-btn');
    const prevStepBtn = document.getElementById('egg-prev-step-btn');
    
    // 切換到第二個視頻
    video.src = 'gd/egg2.mp4';
    video.setAttribute('data-current-video', 'egg2');
    instructionText.innerHTML = '動動手指，左右拖曳籃子來接蛋！<br>接到<img src="img/egg.png" style="width:1.8em;height:1.8em;vertical-align:middle;margin:0 2px;">金蛋(+10分)、<img src="img/catch_egg.png" style="width:2.2em;height:1.8em;vertical-align:middle;margin:0 2px;">白蛋(+3分)，並閃避<span style="font-size:1.2em;">💣</span>炸彈(-20分)。<br>只要達到過關分數，就能立即獲勝！<br><small>分數說明：目前分數會因炸彈減少，<br>過關分數是您需要達到的目標分數</small>';
    stepIndicator.textContent = '步驟 2/2';
    
    // 隱藏下一步按鈕，顯示上一步按鈕
    nextStepBtn.style.display = 'none';
    prevStepBtn.style.display = 'block';
    
    // 加載並播放視頻
    video.load();
    video.play();
}

// 回到接金蛋上一步
function goToEggPrevStep() {
    const video = document.getElementById('egg-current-video');
    const instructionText = document.getElementById('egg-instruction-text');
    const stepIndicator = document.getElementById('egg-step-indicator');
    const nextStepBtn = document.getElementById('egg-next-step-btn');
    const prevStepBtn = document.getElementById('egg-prev-step-btn');
    
    // 切換到第一個視頻
    video.src = 'gd/egg1.mp4';
    video.setAttribute('data-current-video', 'egg1');
    instructionText.textContent = '先選擇遊戲困難度';
    stepIndicator.textContent = '步驟 1/2';
    
    // 顯示下一步按鈕，隱藏上一步按鈕
    nextStepBtn.style.display = 'block';
    prevStepBtn.style.display = 'none';
    
    // 加載並播放視頻
    video.load();
    video.play();
}

// 回到接金蛋第一步
function goToEggFirstStep() {
    const video = document.getElementById('egg-current-video');
    const instructionText = document.getElementById('egg-instruction-text');
    const stepIndicator = document.getElementById('egg-step-indicator');
    const nextStepBtn = document.getElementById('egg-next-step-btn');
    const prevStepBtn = document.getElementById('egg-prev-step-btn');
    
    // 切換到第一個視頻
    video.src = 'gd/egg1.mp4';
    video.setAttribute('data-current-video', 'egg1');
    instructionText.textContent = '先選擇遊戲困難度';
    stepIndicator.textContent = '步驟 1/2';
    
    // 顯示下一步按鈕，隱藏上一步按鈕
    nextStepBtn.style.display = 'block';
    prevStepBtn.style.display = 'none';
    
    // 加載並播放視頻
    video.load();
    video.play();
}

// 結束彈窗控制
function showEggGameOver(isWin, score, targetScore, bonusScore) {
    document.getElementById('difficulty-modal').style.display = 'none';
    const modal = document.getElementById('egg-game-over-modal');
    const title = document.getElementById('egg-game-over-title');
    
    // 計算遊戲時間（總時間減去剩餘時間）
    // 確保 totalTime 有正確的值
    if (!totalTime) {
        if (currentDifficulty === 'easy') totalTime = 60;
        else if (currentDifficulty === 'normal') totalTime = 80;
        else if (currentDifficulty === 'hard') totalTime = 120;
        else totalTime = 60; // 默認值
    }
    const playTime = totalTime - timeLeft;
    
    // 獲取難度名稱
    const difficultyName = currentDifficulty === 'easy' ? '簡單' : currentDifficulty === 'normal' ? '普通' : '困難';
    
    title.textContent = isWin ? '🎉恭喜破關' : '⏰ 遊戲失敗';
    
    // 設置結果訊息
    const scoreRow = document.getElementById('egg-score-row');
    const timeRow = document.getElementById('egg-time-row');
    const bonusRow = document.getElementById('egg-bonus-row');
    const failMessage = document.getElementById('egg-fail-message');
    
    document.getElementById('egg-gameover-difficulty').textContent = difficultyName;
    
    if (isWin) {
        // 勝利時：顯示難度、遊戲分數、遊戲時間、過關分數
        document.getElementById('egg-gameover-score').textContent = score;
        document.getElementById('egg-gameover-time').textContent = playTime + '秒';
        document.getElementById('egg-gameover-bonus').textContent = '+' + bonusScore;
        if (scoreRow) scoreRow.style.display = 'block';
        if (timeRow) timeRow.style.display = 'block';
        if (bonusRow) bonusRow.style.display = 'block';
        if (failMessage) failMessage.style.display = 'none';
    } else {
        // 失敗時：顯示難度、遊戲分數、遊戲時間、過關分數+0、失敗訊息
        document.getElementById('egg-gameover-score').textContent = score;
        document.getElementById('egg-gameover-time').textContent = playTime + '秒';
        document.getElementById('egg-gameover-bonus').textContent = '+0';
        if (scoreRow) scoreRow.style.display = 'block';
        if (timeRow) timeRow.style.display = 'block';
        if (bonusRow) bonusRow.style.display = 'block';
        if (failMessage) failMessage.style.display = 'block';
    }
    
    modal.classList.remove('hidden');
}

function eggReplayGame() {
    document.getElementById('egg-game-over-modal').classList.add('hidden');
    resetGame();
}

function eggReturnToMain() {
    history.back();
}

// 初始化
window.onload = function() {
    // 確保初始變量設定
    totalTime = 60; // 默認簡單模式時間
    timeLeft = 60;
    
    // 初始化過關分數顯示（默認簡單模式）
    document.getElementById('high-score').textContent = '200'; // 簡單模式的過關分數
    
    // 初始化最高分數記錄（用於統計，不顯示）
    const savedHighScore = localStorage.getItem('egg_highscore_easy');
    if (savedHighScore) {
        highScore = parseInt(savedHighScore);
    } else {
        highScore = 0;
    }
    
    document.getElementById('difficulty-modal').style.display = 'flex';
    if (pauseBtn) pauseBtn.onclick = pauseGame;
    if (resumeBtn) resumeBtn.onclick = resumeGame;
    if (endBtn) endBtn.onclick = () => endGame(false, true); // 手動退出
    if (resetBtn) resetBtn.onclick = resetGame;
    
    // 確保關閉按鈕事件綁定正確
    const closeBtn = document.querySelector('#egg-help-modal .close-btn');
    if (closeBtn) {
        closeBtn.onclick = closeEggHelpModal;
        console.log('接金蛋關閉按鈕事件已綁定');
    }
    
    // 初始化籃子位置 - 多次嘗試確保正確置中
    const initBasketPosition = () => {
        const basketWidth = basket.offsetWidth;
        const gameWidth = game.offsetWidth;
        if (basketWidth > 0 && gameWidth > 0) {
            const centerLeft = (gameWidth - basketWidth) / 2;
            basket.style.transform = `translateX(${centerLeft}px)`;
            basket.style.left = '0px';
            console.log('籃子初始化位置:', {
                basketWidth: basketWidth,
                gameWidth: gameWidth,
                centerLeft: centerLeft,
                finalLeft: basket.style.left
            });
        } else {
            // 如果尺寸還沒計算出來，再試一次
            setTimeout(initBasketPosition, 50);
        }
    };
    
    // 立即嘗試一次，然後延遲再試一次
    initBasketPosition();
    setTimeout(initBasketPosition, 200);
    
    // 監聽視窗大小改變，重新置中籃子
    window.addEventListener('resize', () => {
        setTimeout(initBasketPosition, 100);
    });
    
    // 測試音效是否正常載入
    console.log('音效元素檢查:');
    console.log('catchSound:', catchSound);
    console.log('bombSound:', bombSound);
    console.log('gameOverSound:', gameOverSound);
    
    // 預載入音效，確保第一次播放沒有延遲
    if (catchSound) {
        catchSound.volume = 0.8; // 設定音量
        catchSound.preload = 'auto';
        catchSound.load(); // 強制載入音效
        
        // 監聽音效載入事件
        catchSound.addEventListener('canplaythrough', () => {
            console.log('接蛋音效載入完成，readyState:', catchSound.readyState);
        });
        catchSound.addEventListener('error', (e) => {
            console.error('接蛋音效載入失敗:', e);
        });
        
        console.log('接蛋音效已預載入');
    }
    
    if (bombSound) {
        bombSound.volume = 0.3; // 設定音量
        bombSound.preload = 'auto';
        bombSound.load(); // 強制載入音效
        
        // 監聽音效載入事件
        bombSound.addEventListener('canplaythrough', () => {
            console.log('炸彈音效載入完成，readyState:', bombSound.readyState);
        });
        bombSound.addEventListener('error', (e) => {
            console.error('炸彈音效載入失敗:', e);
        });
        
        console.log('炸彈音效已預載入');
    }
    
    if (gameOverSound) {
        gameOverSound.volume = 0.5; // 設定音量
        gameOverSound.preload = 'auto';
        gameOverSound.load(); // 強制載入音效
        
        // 監聽音效載入事件
        gameOverSound.addEventListener('canplaythrough', () => {
            console.log('遊戲結束音效載入完成，readyState:', gameOverSound.readyState);
        });
        gameOverSound.addEventListener('error', (e) => {
            console.error('遊戲結束音效載入失敗:', e);
        });
        
        console.log('遊戲結束音效已預載入');
    }
    
    // 添加音效預熱機制
    const prewarmAudio = () => {
        if (catchSound) {
            const originalVolume = catchSound.volume;
            catchSound.volume = 0; // 靜音預熱
            catchSound.currentTime = 0;
            catchSound.play().then(() => {
                catchSound.pause();
                catchSound.currentTime = 0;
                catchSound.volume = originalVolume; // 恢復音量
                console.log('接蛋音效預熱完成');
            }).catch(e => console.log('音效預熱失敗:', e));
        }
        
        if (bombSound) {
            const originalVolume = bombSound.volume;
            bombSound.volume = 0; // 靜音預熱
            bombSound.currentTime = 0;
            bombSound.play().then(() => {
                bombSound.pause();
                bombSound.currentTime = 0;
                bombSound.volume = originalVolume; // 恢復音量
                console.log('炸彈音效預熱完成');
            }).catch(e => console.log('音效預熱失敗:', e));
        }
        
        if (gameOverSound) {
            const originalVolume = gameOverSound.volume;
            gameOverSound.volume = 0; // 靜音預熱
            gameOverSound.currentTime = 0;
            gameOverSound.play().then(() => {
                gameOverSound.pause();
                gameOverSound.currentTime = 0;
                gameOverSound.volume = originalVolume; // 恢復音量
                console.log('遊戲結束音效預熱完成');
            }).catch(e => console.log('音效預熱失敗:', e));
        }
        
        // 移除事件監聽器，避免重複執行
        document.removeEventListener('click', prewarmAudio);
        document.removeEventListener('touchstart', prewarmAudio);
    };
    
    // 在用戶第一次互動時預熱音效
    document.addEventListener('click', prewarmAudio, { once: true });
    document.addEventListener('touchstart', prewarmAudio, { once: true });
    
    // 返回按鈕事件已在HTML中直接綁定為 onclick="history.back()"
    const backBtn = document.getElementById('back-btn');
    if (backBtn) {
        console.log('找到返回按鈕，已在HTML中綁定事件');
    } else {
        console.log('找不到返回按鈕元素');
    }
};