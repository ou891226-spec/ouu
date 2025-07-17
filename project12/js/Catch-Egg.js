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
const bgm = document.getElementById('bgm');
const catchSound = document.getElementById('catchSound');
const bombSound = document.getElementById('bombSound');
const gameOverSound = document.getElementById('gameOverSound');

let score = 0;
let highScore = 0;
let timeLeft = 60;
let itemInterval;
let countdown;
let gameStarted = false;
let gamePaused = false;
let currentDifficulty = 'easy';

// 選擇難度
function selectDifficulty(difficulty) {
    currentDifficulty = difficulty;
    fetch("Catch-Egg.php", {
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
            document.getElementById('endBtn').classList.remove('hidden');
            document.getElementById('resetBtn').classList.remove('hidden');
            showCountdown(startGameTimer);
        }
    });
}

// 更新分數顯示
function updateScore() {
    document.getElementById('score').textContent = score;
    if (score > highScore) {
        highScore = score;
        document.getElementById('high-score').textContent = highScore;
    }
}
 
// 顯示開始倒數
function showCountdown(callback) {
    countdownOverlay.style.display = 'block';
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
            game.removeChild(countdownElement);
            callback();
        }
    }, 1000);
}

// 開始遊戲
function startGameTimer() {
    score = 0;
    updateScore();
    timeLeft = 60;
    document.getElementById('timer').textContent = timeLeft;
    gameStarted = true;
    gamePaused = false;
    pauseBtn.classList.remove('hidden');
    resumeBtn.classList.add('hidden');

    bgm.play();

    let dropInterval = 600;
    if (currentDifficulty === 'normal') dropInterval = 400;
    else if (currentDifficulty === 'hard') dropInterval = 200;

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
                
                if (currentDifficulty === 'easy' && score >= 200) {
                    bonusScore = 20;
                    showEggGameOver(true, score, 200, bonusScore);
                } else if (currentDifficulty === 'normal' && score >= 450) {
                    bonusScore = 50;
                    showEggGameOver(true, score, 450, bonusScore);
                } else if (currentDifficulty === 'hard' && score >= 600) {
                    bonusScore = 100;
                    showEggGameOver(true, score, 600, bonusScore);
                } else {
                    showEggGameOver(false, score, currentDifficulty === 'easy' ? 200 : currentDifficulty === 'normal' ? 450 : 600, 0);
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
 
  bgm.pause();
  bgm.currentTime = 0;
 
  score = 0;
  timeLeft = 60;
  gameStarted = false;
  gamePaused = false;
 
  updateScore();
  timerDisplay.textContent = timeLeft;
 
  const items = document.querySelectorAll('.gold, .white, .bomb');
  items.forEach(item => {
    game.removeChild(item);
  });
 
    document.getElementById('difficulty-modal').style.display = 'flex';
    document.getElementById('endBtn').classList.add('hidden');
    document.getElementById('resetBtn').classList.add('hidden');

  basket.style.transform = 'translateX(250px)';
  basket.style.left = '0';
 
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
        
        basket.style.transform = `translateX(${newLeft}px)`;
        basket.style.left = '0';
    }
});

// 籃子觸控拖曳
let touchStartX = 0;
basket.addEventListener('touchstart', (e) => {
    if (gameStarted && !gamePaused && basket.style.pointerEvents !== 'none') {
        touchStartX = e.touches[0].clientX;
        e.preventDefault();
    }
});
document.addEventListener('touchmove', (e) => {
    if (touchStartX !== 0 && gameStarted && !gamePaused && basket.style.pointerEvents !== 'none') {
        e.preventDefault();
        const gameRect = game.getBoundingClientRect();
        const touchX = e.touches[0].clientX - gameRect.left;
        const basketWidth = basket.offsetWidth;
        const maxLeft = game.offsetWidth - basketWidth;
        
        let newLeft = touchX - (basketWidth / 2);
        newLeft = Math.max(0, Math.min(newLeft, maxLeft));
        
        basket.style.transform = `translateX(${newLeft}px)`;
        basket.style.left = '0';
    }
});
document.addEventListener('touchend', () => {
    touchStartX = 0;
});

// 鍵盤左右鍵移動
document.addEventListener('keydown', (e) => {
    if (gameStarted && !gamePaused && basket.style.pointerEvents !== 'none') {
        const currentTransform = basket.style.transform;
        const currentX = currentTransform ? parseInt(currentTransform.replace('translateX(', '').replace('px)', '')) : 0;
        const moveDistance = 10;
        const maxLeft = game.offsetWidth - basket.offsetWidth;
        
        if (e.key === 'ArrowLeft') {
            const newX = Math.max(0, currentX - moveDistance);
            basket.style.transform = `translateX(${newX}px)`;
            basket.style.left = '0';
        } else if (e.key === 'ArrowRight') {
            const newX = Math.min(maxLeft, currentX + moveDistance);
            basket.style.transform = `translateX(${newX}px)`;
            basket.style.left = '0';
        }
    }
});

// 掉落物品
function dropItem() {
    if (gamePaused || !gameStarted) return;
    const types = ['gold', 'gold', 'gold', 'white', 'white', 'bomb'];
    const type = types[Math.floor(Math.random() * types.length)];
    const item = document.createElement('div');
    item.className = type;
    item.setAttribute('data-type', type);
    item.innerText = type === 'bomb' ? '💣' : type === 'gold' ? '🥚' : '';
    if (type === 'white') {
        const img = document.createElement('img');
        img.src = 'img/egg.png';
        img.alt = '白蛋';
        img.style.width = '55px';
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
        if (top >= game.offsetHeight - 100) {
            const itemLeft = parseInt(item.style.left);
            const basketTransform = basket.style.transform;
            const basketX = basketTransform ? parseInt(basketTransform.replace('translateX(', '').replace('px)', '')) : 250;
            const basketWidth = basket.offsetWidth;
            const itemCenter = itemLeft + 25;
            const basketLeft = basketX;
            const basketRight = basketX + basketWidth;
            const itemBottom = top + 50;
            const basketTop = game.offsetHeight - 100;
            if (itemCenter >= basketLeft && itemCenter <= basketRight && itemBottom >= basketTop && !isScored && !gamePaused && gameStarted) {
                isScored = true;
                const type = item.getAttribute('data-type');
                if (type === 'gold') {
                    score += 10;
                    catchSound.currentTime = 0;
                    catchSound.play();
                } else if (type === 'white') {
                    score += 3;
                    catchSound.currentTime = 0;
                    catchSound.play();
                } else if (type === 'bomb') {
                    score -= 20;
                    bombSound.currentTime = 0;
                    bombSound.play();
                }
                updateScore();
                game.removeChild(item);
                clearInterval(item.fallInterval);
            } else if (top >= game.offsetHeight) {
                game.removeChild(item);
                clearInterval(item.fallInterval);
            } else {
                let speed = 5;
                if (currentDifficulty === 'normal') speed = 7;
                else if (currentDifficulty === 'hard') speed = 9;
                item.style.top = (top + speed) + 'px';
            }
        } else {
            let speed = 5;
            if (currentDifficulty === 'normal') speed = 7;
            else if (currentDifficulty === 'hard') speed = 9;
            item.style.top = (top + speed) + 'px';
        }
    }, 50);
}

// 暫停遊戲
function pauseGame() {
    gamePaused = true;
    pauseBtn.classList.add('hidden');
    resumeBtn.classList.remove('hidden');
    clearInterval(itemInterval);
    clearInterval(countdown);
    bgm.pause();
    
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
    bgm.play();
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
            game.removeChild(countdownElement);
            basket.style.pointerEvents = 'auto';
            basket.style.cursor = 'grab';
            
            // 倒數結束後才開始掉落新物品和恢復現有物品的移動
            let dropInterval = 600;
            if (currentDifficulty === 'normal') dropInterval = 400;
            else if (currentDifficulty === 'hard') dropInterval = 200;
            itemInterval = setInterval(dropItem, dropInterval);
            
            // 恢復現有物品的移動
            items.forEach(item => {
                if (!item.fallInterval) {
                    item.fallInterval = setInterval(() => {
                        if (gamePaused || !gameStarted) return;
                        const top = parseInt(item.style.top);
                        if (top >= game.offsetHeight - 100) {
                            const itemLeft = parseInt(item.style.left);
                            const basketTransform = basket.style.transform;
                            const basketX = basketTransform ? parseInt(basketTransform.replace('translateX(', '').replace('px)', '')) : 250;
                            const basketWidth = basket.offsetWidth;
                            const itemCenter = itemLeft + 25;
                            const basketLeft = basketX;
                            const basketRight = basketX + basketWidth;
                            const itemBottom = top + 50;
                            const basketTop = game.offsetHeight - 100;
                            if (itemCenter >= basketLeft && itemCenter <= basketRight && itemBottom >= basketTop && !gamePaused && gameStarted) {
                                const type = item.getAttribute('data-type');
                                if (type === 'gold') {
                                    score += 10;
                                    catchSound.currentTime = 0;
                                    catchSound.play();
                                } else if (type === 'white') {
                                    score += 3;
                                    catchSound.currentTime = 0;
                                    catchSound.play();
                                } else if (type === 'bomb') {
                                    score -= 20;
                                    bombSound.currentTime = 0;
                                    bombSound.play();
                                }
                                updateScore();
                                game.removeChild(item);
                                clearInterval(item.fallInterval);
                            } else if (top >= game.offsetHeight) {
                                game.removeChild(item);
                                clearInterval(item.fallInterval);
                            } else {
                                let speed = 5;
                                if (currentDifficulty === 'normal') speed = 7;
                                else if (currentDifficulty === 'hard') speed = 9;
                                item.style.top = (top + speed) + 'px';
                            }
                        } else {
                            let speed = 5;
                            if (currentDifficulty === 'normal') speed = 7;
                            else if (currentDifficulty === 'hard') speed = 9;
                            item.style.top = (top + speed) + 'px';
                        }
                    }, 50);
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
                        if (currentDifficulty === 'easy' && score >= 200) {
                            bonusScore = 20;
                            showEggGameOver(true, score, 200, bonusScore);
                        } else if (currentDifficulty === 'normal' && score >= 450) {
                            bonusScore = 50;
                            showEggGameOver(true, score, 450, bonusScore);
                        } else if (currentDifficulty === 'hard' && score >= 600) {
                            bonusScore = 100;
                            showEggGameOver(true, score, 600, bonusScore);
                        } else {
                            showEggGameOver(false, score, currentDifficulty === 'easy' ? 200 : currentDifficulty === 'normal' ? 450 : 600, 0);
                        }
                        endGame();
                    }
                }
            }, 1000);
        }
    }, 1000);
}

// 結束遊戲
function endGame() {
    clearInterval(itemInterval);
    clearInterval(countdown);
    
    bgm.pause();
    bgm.currentTime = 0;
    
    gameOverSound.play();
    
    // 計算獎勵分數
    let bonusScore = 0;
    if (currentDifficulty === 'easy' && score >= 200) {
        bonusScore = 20;
        showEggGameOver(true, score, 200, bonusScore);
    } else if (currentDifficulty === 'normal' && score >= 450) {
        bonusScore = 50;
        showEggGameOver(true, score, 450, bonusScore);
    } else if (currentDifficulty === 'hard' && score >= 600) {
        bonusScore = 100;
        showEggGameOver(true, score, 600, bonusScore);
    } else {
        showEggGameOver(false, score, currentDifficulty === 'easy' ? 200 : currentDifficulty === 'normal' ? 450 : 600, 0);
    }
    
    // 只保存獎勵分數到資料庫
    fetch("Catch-Egg.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/x-www-form-urlencoded",
            "X-Requested-With": "XMLHttpRequest"
        },
        body: `action=end_game&score=${bonusScore}&member_id=${localStorage.getItem('member_id')}`
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
}

function closeEggHelpModal() {
    document.getElementById('egg-help-modal').classList.add('hidden');
}

// 結束彈窗控制
function showEggGameOver(isWin, score, targetScore, bonusScore) {
    document.getElementById('difficulty-modal').style.display = 'none';
    const modal = document.getElementById('egg-game-over-modal');
    const title = document.getElementById('egg-game-over-title');
    const message = document.getElementById('egg-result-message');
    title.textContent = isWin ? '🎉 恭喜破關！' : '⏰ 遊戲失敗';
    if (isWin) {
        message.innerHTML = `難度 : ${currentDifficulty === 'easy' ? '簡單' : currentDifficulty === 'normal' ? '普通' : '困難'}<br><br>獲得分數 : ${score}<br>過關獎勵 : +${bonusScore}`;
    } else {
        message.innerHTML = `難度 : ${currentDifficulty === 'easy' ? '簡單' : currentDifficulty === 'normal' ? '普通' : '困難'}<br><br>您的分數 : ${score}<br>未達到過關標準！`;
    }
    modal.classList.remove('hidden');
}

function eggReplayGame() {
    document.getElementById('egg-game-over-modal').classList.add('hidden');
    resetGame();
}

function eggReturnToMain() {
    window.location.href = 'index.php';
}

// 初始化
window.onload = function() {
    document.getElementById('difficulty-modal').style.display = 'flex';
    if (pauseBtn) pauseBtn.onclick = pauseGame;
    if (resumeBtn) resumeBtn.onclick = resumeGame;
    if (endBtn) endBtn.onclick = endGame;
    if (resetBtn) resetBtn.onclick = resetGame;
};