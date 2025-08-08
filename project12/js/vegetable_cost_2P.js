// 遊戲核心邏輯
let score = 0;
let timer = 60;
let interval = null;
let currentQuestion = 0;
let questions = [];
let gamePaused = false;
let savedTimer = 60;
let gameStarted = false;
let currentDifficulty = null;
let isPaused = false;

// 雙人遊戲變數
let player1 = {
    id: window.phpMemberId,
    name: window.currentUser.member_name,
    score: 0,
    correct: 0,
    avatar: window.currentUser.avatar
};

let player2 = {
    id: 'local_player',
    name: '本地玩家',
    score: 0,
    correct: 0,
    avatar: 'img/user.png'
};

let currentPlayer = 1; // 1 或 2
let totalQuestions = 0;

// 食材資料庫（從資料庫 AJAX 取得）
let ingredients = {};

// 頁面載入時初始化遊戲
window.addEventListener('DOMContentLoaded', async () => {
    // 載入食材資料
    await fetchIngredients();
    
    // 直接顯示難度選擇視窗
    showDifficultyModal();
});

// 顯示難度選擇視窗
function showDifficultyModal() {
    const difficultyModal = document.getElementById('difficulty-modal');
    if (difficultyModal) {
        difficultyModal.classList.remove('hidden');
    }
}

async function fetchIngredients() {
    const response = await fetch('vegetable_cost_2P.php?get_ingredients=1');
    const data = await response.json();
    console.log('fetchIngredients 回傳:', data);
    if (!Array.isArray(data)) {
        alert(data.error ? data.error : '取得食材資料失敗');
        return;
    }
    // 分類
    ingredients = { vegetables: [], fruits: [], meat: [], seafood: [], mushroom: [], others: [] };
    data.forEach(item => {
        if (!ingredients[item.category]) ingredients[item.category] = [];
        ingredients[item.category].push(item);
    });
}

// 食材對應 emoji
const ingredientEmojis = {
    '小白菜': '🥬',
    '高麗菜': '🥬',
    '青江菜': '🥬',
    '蘋果': '🍎',
    '香蕉': '🍌',
    '番茄': '🍅',
    '胡蘿蔔': '🥕',
    '馬鈴薯': '🥔',
    '洋蔥': '🧅',
    '葡萄': '🍇',
    '西瓜': '🍉',
    '鳳梨': '🍍',
    '草莓': '🍓',
    '南瓜': '🎃',
    '玉米': '🌽',
    '茄子': '🍆',
    '辣椒': '🌶️',
    '檸檬': '🍋',
    '橘子': '🍊',
    '芒果': '🥭',
    '蘑菇': '🍄',
    '雞蛋': '🥚',
    '牛肉': '🥩',
    '豬肉': '🥓',
    '雞肉': '🍗',
    '魚': '🐟',
    '蝦': '🦐',
    '螃蟹': '🦀',
    '龍蝦': '🦞',
    '章魚': '🐙',
    '海膽': '🦑',
    '起司': '🧀',
    '其他': '🥗'
};

// 生成題目時，將食材名稱加上 emoji
function getIngredientWithEmoji(name) {
    return name + (ingredientEmojis[name] ? ' ' + ingredientEmojis[name] : '');
}

// 新增：去除 emoji 只留食材名稱
function stripEmoji(str) {
    // 去除 emoji 和多餘空白，只留食材名稱
    return str.replace(/\s*[\u{1F300}-\u{1FAFF}\u{1F600}-\u{1F64F}\u{1F680}-\u{1F6FF}\u{2600}-\u{26FF}\u{2700}-\u{27BF}]+/gu, '').trim();
}

// 生成簡單題目
function generateEasyQuestion() {
    // 顯示2~3種蔬果與價格（組合題時固定5種）
    const allItems = ingredients.vegetables.concat(ingredients.fruits, ingredients.others);
    // 題型隨機：1. 指定物品總價 2. 固定預算能買哪些
    const type = Math.random() < 0.6 ? '指定物品' : '預算組合';
    let selectedItems = [];

    if (type === '預算組合') {
        // 預算組合題固定5種蔬果
        const numItems = Math.min(5, allItems.length);
        let usedNames = new Set();
        let tries = 0;
        while (selectedItems.length < numItems && tries < 20) {
            const randomItem = allItems[Math.floor(Math.random() * allItems.length)];
            if (!usedNames.has(randomItem.name)) {
                selectedItems.push(randomItem);
                usedNames.add(randomItem.name);
            }
            tries++;
        }
    } else {
        // 指定物品題隨機2~3種
        const numItems = Math.random() < 0.5 ? 2 : 3;
        let usedNames = new Set();
        let tries = 0;
        while (selectedItems.length < numItems && tries < 20) {
            const randomItem = allItems[Math.floor(Math.random() * allItems.length)];
            if (!usedNames.has(randomItem.name)) {
                selectedItems.push(randomItem);
                usedNames.add(randomItem.name);
            }
            tries++;
        }
    }

    if (selectedItems.length === 0) {
        return generateEasyQuestion(); // 遞迴重試
    }

    // 計算總價
    const totalPrice = selectedItems.reduce((sum, item) => sum + parseFloat(item.price), 0);
    
    // 生成選項（包含正確答案和3個錯誤答案）
    const options = [totalPrice];
    while (options.length < 4) {
        const wrongPrice = totalPrice + (Math.random() < 0.5 ? 1 : -1) * Math.floor(Math.random() * 20 + 5);
        if (wrongPrice > 0 && !options.includes(wrongPrice)) {
            options.push(wrongPrice);
        }
    }
    
    // 打亂選項順序
    for (let i = options.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [options[i], options[j]] = [options[j], options[i]];
    }

    // 生成題目文字
    let questionText = '阿嬤去市場買菜，買了：\n';
    selectedItems.forEach(item => {
        questionText += `${getIngredientWithEmoji(item.name)} ${item.price}元\n`;
    });
    questionText += '\n請問總共要付多少錢？';

    return {
        question: questionText,
        options: options,
        correctAnswer: totalPrice,
        type: type
    };
}

// 生成普通題目
function generateNormalQuestion() {
    const allItems = ingredients.vegetables.concat(ingredients.fruits, ingredients.meat, ingredients.seafood, ingredients.others);
    const type = Math.random() < 0.5 ? '指定物品' : '預算組合';
    let selectedItems = [];

    if (type === '預算組合') {
        // 預算組合題固定6種食材
        const numItems = Math.min(6, allItems.length);
        let usedNames = new Set();
        let tries = 0;
        while (selectedItems.length < numItems && tries < 20) {
            const randomItem = allItems[Math.floor(Math.random() * allItems.length)];
            if (!usedNames.has(randomItem.name)) {
                selectedItems.push(randomItem);
                usedNames.add(randomItem.name);
            }
            tries++;
        }
    } else {
        // 指定物品題隨機3~4種
        const numItems = Math.random() < 0.5 ? 3 : 4;
        let usedNames = new Set();
        let tries = 0;
        while (selectedItems.length < numItems && tries < 20) {
            const randomItem = allItems[Math.floor(Math.random() * allItems.length)];
            if (!usedNames.has(randomItem.name)) {
                selectedItems.push(randomItem);
                usedNames.add(randomItem.name);
            }
            tries++;
        }
    }

    if (selectedItems.length === 0) {
        return generateNormalQuestion(); // 遞迴重試
    }

    // 計算總價
    const totalPrice = selectedItems.reduce((sum, item) => sum + parseFloat(item.price), 0);
    
    // 生成選項（包含正確答案和3個錯誤答案）
    const options = [totalPrice];
    while (options.length < 4) {
        const wrongPrice = totalPrice + (Math.random() < 0.5 ? 1 : -1) * Math.floor(Math.random() * 30 + 10);
        if (wrongPrice > 0 && !options.includes(wrongPrice)) {
            options.push(wrongPrice);
        }
    }
    
    // 打亂選項順序
    for (let i = options.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [options[i], options[j]] = [options[j], options[i]];
    }

    // 生成題目文字
    let questionText = '阿嬤去市場買菜，買了：\n';
    selectedItems.forEach(item => {
        questionText += `${getIngredientWithEmoji(item.name)} ${item.price}元\n`;
    });
    questionText += '\n請問總共要付多少錢？';

    return {
        question: questionText,
        options: options,
        correctAnswer: totalPrice,
        type: type
    };
}

// 生成困難題目
function generateHardQuestion() {
    const allItems = ingredients.vegetables.concat(ingredients.fruits, ingredients.meat, ingredients.seafood, ingredients.mushroom, ingredients.others);
    const type = Math.random() < 0.4 ? '指定物品' : '預算組合';
    let selectedItems = [];

    if (type === '預算組合') {
        // 預算組合題固定7種食材
        const numItems = Math.min(7, allItems.length);
        let usedNames = new Set();
        let tries = 0;
        while (selectedItems.length < numItems && tries < 20) {
            const randomItem = allItems[Math.floor(Math.random() * allItems.length)];
            if (!usedNames.has(randomItem.name)) {
                selectedItems.push(randomItem);
                usedNames.add(randomItem.name);
            }
            tries++;
        }
    } else {
        // 指定物品題隨機4~5種
        const numItems = Math.random() < 0.5 ? 4 : 5;
        let usedNames = new Set();
        let tries = 0;
        while (selectedItems.length < numItems && tries < 20) {
            const randomItem = allItems[Math.floor(Math.random() * allItems.length)];
            if (!usedNames.has(randomItem.name)) {
                selectedItems.push(randomItem);
                usedNames.add(randomItem.name);
            }
            tries++;
        }
    }

    if (selectedItems.length === 0) {
        return generateHardQuestion(); // 遞迴重試
    }

    // 計算總價
    const totalPrice = selectedItems.reduce((sum, item) => sum + parseFloat(item.price), 0);
    
    // 生成選項（包含正確答案和3個錯誤答案）
    const options = [totalPrice];
    while (options.length < 4) {
        const wrongPrice = totalPrice + (Math.random() < 0.5 ? 1 : -1) * Math.floor(Math.random() * 50 + 15);
        if (wrongPrice > 0 && !options.includes(wrongPrice)) {
            options.push(wrongPrice);
        }
    }
    
    // 打亂選項順序
    for (let i = options.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [options[i], options[j]] = [options[j], options[i]];
    }

    // 生成題目文字
    let questionText = '阿嬤去市場買菜，買了：\n';
    selectedItems.forEach(item => {
        questionText += `${getIngredientWithEmoji(item.name)} ${item.price}元\n`;
    });
    questionText += '\n請問總共要付多少錢？';

    return {
        question: questionText,
        options: options,
        correctAnswer: totalPrice,
        type: type
    };
}

// 載入題目
function loadQuestion() {
    if (currentQuestion >= questions.length) {
        // 生成新題目
        let newQuestion;
        switch (currentDifficulty) {
            case 'easy':
                newQuestion = generateEasyQuestion();
                break;
            case 'normal':
                newQuestion = generateNormalQuestion();
                break;
            case 'hard':
                newQuestion = generateHardQuestion();
                break;
            default:
                newQuestion = generateEasyQuestion();
        }
        questions.push(newQuestion);
    }

    const question = questions[currentQuestion];
    
    // 顯示題目
    document.getElementById('question').textContent = question.question;
    
    // 顯示選項
    const optionsContainer = document.getElementById('options-container');
    optionsContainer.innerHTML = '';
    
    question.options.forEach((option, index) => {
        const button = document.createElement('button');
        button.textContent = option + '元';
        button.onclick = () => checkAnswer(option, question.correctAnswer);
        optionsContainer.appendChild(button);
    });

    // 更新當前玩家指示器
    updateCurrentPlayerIndicator();
}

// 開始遊戲
function startGame() {
    gameStarted = true;
    currentQuestion = 0;
    questions = [];
    totalQuestions = 0;
    
    // 重置玩家分數
    player1.score = 0;
    player1.correct = 0;
    player2.score = 0;
    player2.correct = 0;
    currentPlayer = 1;
    
    // 更新顯示
    updatePlayerDisplay();
    updateCurrentPlayerIndicator();
    
    // 開始計時器
    startTimer();
    
    // 載入第一題
    loadQuestion();
    
    // 隱藏難度選擇視窗
    document.getElementById('difficulty-modal').classList.add('hidden');
    
    // 顯示遊戲容器
    document.getElementById('game-container').style.display = 'block';
}

// 檢查答案
function checkAnswer(selectedAnswer, correctAnswer) {
    const isCorrect = selectedAnswer === correctAnswer;
    
    if (isCorrect) {
        // 當前玩家得分
        if (currentPlayer === 1) {
            player1.score += 3;
            player1.correct += 1;
        } else {
            player2.score += 3;
            player2.correct += 1;
        }
        
        // 更新顯示
        updatePlayerDisplay();
        
        // 顯示正確提示
        showAnswerFeedback(true);
    } else {
        // 顯示錯誤提示
        showAnswerFeedback(false);
    }
    
    // 增加總題數
    totalQuestions++;
    document.getElementById('total-questions').textContent = totalQuestions;
    
    // 切換玩家
    currentPlayer = currentPlayer === 1 ? 2 : 1;
    
    // 更新當前玩家指示器
    updateCurrentPlayerIndicator();
    
    // 下一題
    currentQuestion++;
    setTimeout(() => {
        loadQuestion();
    }, 1500);
}

// 顯示答案反饋
function showAnswerFeedback(isCorrect) {
    const questionContainer = document.getElementById('question-container');
    const feedback = document.createElement('div');
    feedback.style.cssText = `
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        font-size: 2rem;
        font-weight: bold;
        color: ${isCorrect ? '#4caf50' : '#f44336'};
        background: rgba(255, 255, 255, 0.9);
        padding: 20px;
        border-radius: 10px;
        z-index: 100;
    `;
    feedback.textContent = isCorrect ? '✓ 答對了！' : '✗ 答錯了！';
    
    questionContainer.style.position = 'relative';
    questionContainer.appendChild(feedback);
    
    setTimeout(() => {
        questionContainer.removeChild(feedback);
    }, 1500);
}

// 更新玩家顯示
function updatePlayerDisplay() {
    document.getElementById('player1-score').textContent = player1.score;
    document.getElementById('player1-correct').textContent = player1.correct;
    document.getElementById('player2-score').textContent = player2.score;
    document.getElementById('player2-correct').textContent = player2.correct;
}

// 更新當前玩家指示器
function updateCurrentPlayerIndicator() {
    const player1Info = document.getElementById('player1-info');
    const player2Info = document.getElementById('player2-info');
    const currentTurnText = document.getElementById('current-turn');
    
    if (currentPlayer === 1) {
        player1Info.classList.add('active');
        player2Info.classList.remove('active');
        currentTurnText.textContent = player1.name;
    } else {
        player1Info.classList.remove('active');
        player2Info.classList.add('active');
        currentTurnText.textContent = player2.name;
    }
}

// 開始計時器
function startTimer() {
    interval = setInterval(updateTimer, 1000);
}

// 更新計時器
function updateTimer() {
    if (!gamePaused) {
        timer--;
        document.getElementById('timer').textContent = timer;
        
        if (timer <= 0) {
            clearInterval(interval);
            endGame();
        }
    }
}

// 暫停遊戲
function pauseGame() {
    if (!gameStarted) return;
    
    if (!gamePaused) {
        gamePaused = true;
        savedTimer = timer;
        document.getElementById('pause-btn').textContent = '繼續遊戲';
        document.getElementById('pause-btn').classList.add('resume-btn');
    } else {
        gamePaused = false;
        timer = savedTimer;
        document.getElementById('pause-btn').textContent = '暫停遊戲';
        document.getElementById('pause-btn').classList.remove('resume-btn');
    }
}

// 結束遊戲
function endGame() {
    clearInterval(interval);
    gameStarted = false;
    
    // 計算獎勵分數
    let player1Bonus = 0;
    let player2Bonus = 0;
    
    switch (currentDifficulty) {
        case 'easy':
            if (player1.score >= 15) player1Bonus = 20;
            if (player2.score >= 15) player2Bonus = 20;
            break;
        case 'normal':
            if (player1.score >= 20) player1Bonus = 50;
            if (player2.score >= 20) player2Bonus = 50;
            break;
        case 'hard':
            if (player1.score >= 25) player1Bonus = 100;
            if (player2.score >= 25) player2Bonus = 100;
            break;
    }
    
    player1.score += player1Bonus;
    player2.score += player2Bonus;
    
    // 顯示遊戲結束視窗
    showGameOverModal();
    
    // 保存遊戲結果
    saveGameResult();
}

// 顯示遊戲結束視窗
function showGameOverModal() {
    const modal = document.getElementById('game-over-modal');
    const player1Result = document.getElementById('player1-result');
    const player2Result = document.getElementById('player2-result');
    const winnerAnnouncement = document.getElementById('winner-announcement');
    
    // 更新結果顯示
    player1Result.querySelector('.player-name').textContent = player1.name;
    player1Result.querySelector('.final-score').textContent = player1.score + '分';
    player1Result.querySelector('.final-correct').textContent = '答對 ' + player1.correct + ' 題';
    
    player2Result.querySelector('.player-name').textContent = player2.name;
    player2Result.querySelector('.final-score').textContent = player2.score + '分';
    player2Result.querySelector('.final-correct').textContent = '答對 ' + player2.correct + ' 題';
    
    // 判斷勝負
    if (player1.score > player2.score) {
        winnerAnnouncement.textContent = `🏆 ${player1.name} 獲勝！`;
    } else if (player2.score > player1.score) {
        winnerAnnouncement.textContent = `🏆 ${player2.name} 獲勝！`;
    } else {
        winnerAnnouncement.textContent = '🤝 平手！';
    }
    
    modal.classList.remove('hidden');
}

// 保存遊戲結果
async function saveGameResult() {
    try {
        const response = await fetch('vegetable_cost_2P.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                player1_id: player1.id,
                player2_id: 'local_player', // 本地玩家
                player1_score: player1.score,
                player2_score: player2.score,
                difficulty: currentDifficulty,
                play_time: 60 - timer
            })
        });
        
        const result = await response.json();
        console.log('遊戲結果保存:', result);
    } catch (error) {
        console.error('保存遊戲結果失敗:', error);
    }
}

// 重新開始遊戲
function restartGame() {
    // 隱藏遊戲結束視窗
    document.getElementById('game-over-modal').classList.add('hidden');
    
    // 顯示難度選擇視窗
    showDifficultyModal();
    
    // 隱藏遊戲容器
    document.getElementById('game-container').style.display = 'none';
    
    // 重置遊戲狀態
    timer = 60;
    gamePaused = false;
    gameStarted = false;
    currentQuestion = 0;
    questions = [];
    
    document.getElementById('timer').textContent = timer;
    document.getElementById('pause-btn').textContent = '暫停遊戲';
    document.getElementById('pause-btn').classList.remove('resume-btn');
}

// 退出遊戲
function exitGame() {
    window.location.href = 'index.php';
}

// 難度選擇
function selectDifficulty(difficulty) {
    currentDifficulty = difficulty;
    
    // 設定時間
    switch (difficulty) {
        case 'easy':
            timer = 80;
            break;
        case 'normal':
            timer = 150;
            break;
        case 'hard':
            timer = 200;
            break;
    }
    
    savedTimer = timer;
    document.getElementById('timer').textContent = timer;
    
    // 直接開始遊戲
    startGame();
}

// 說明視窗控制
function openHelpModal() {
    document.getElementById('help-modal').classList.remove('hidden');
}

function closeHelpModal() {
    document.getElementById('help-modal').classList.add('hidden');
}

// 事件監聽器
document.addEventListener('DOMContentLoaded', function() {
    // 綁定按鈕事件
    const pauseBtn = document.getElementById('pause-btn');
    if (pauseBtn) {
        pauseBtn.addEventListener('click', pauseGame);
    }
    
    // 綁定說明按鈕事件
    document.querySelectorAll('.help-button').forEach(btn => {
        btn.addEventListener('click', openHelpModal);
    });
    
    // 點擊模態框外部關閉
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.add('hidden');
            }
        });
    });
});


