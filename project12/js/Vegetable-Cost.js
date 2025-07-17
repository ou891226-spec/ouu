// 遊戲核心邏輯
let score = 0;
let highScore = 0;
let timer = 60;
let interval = null;
let currentQuestion = 0;
let questions = [];
let gamePaused = false;
let savedTimer = 60;
let gameStarted = false;
let currentDifficulty = null;

// 食材資料庫
const ingredients = {
    vegetables: [
        { name: "高麗菜", price: 25, unit: "半顆" },
        { name: "青江菜", price: 25, unit: "把" },
        { name: "小白菜", price: 20, unit: "把" },
        { name: "空心菜", price: 15, unit: "把" },
        { name: "紅蘿蔔", price: 15, unit: "根" },
        { name: "胡蘿蔔", price: 30, unit: "把" },
        { name: "馬鈴薯", price: 20, unit: "顆" },
        { name: "番茄", price: 15, unit: "顆" },
        { name: "青椒", price: 20, unit: "顆" },
        { name: "苦瓜", price: 32, unit: "條" }
    ],
    fruits: [
        { name: "蘋果", price: 30, unit: "顆" },
        { name: "香蕉", price: 25, unit: "串" },
        { name: "橘子", price: 20, unit: "顆" }
    ],
    meat: [
        { name: "五花肉", price: 150, unit: "斤" },
        { name: "絞肉", price: 70, unit: "斤" },
        { name: "小里肌", price: 280, unit: "斤" },
        { name: "雞肉", price: 120, unit: "斤" }
    ],
    others: [
        { name: "雞蛋", price: 40, unit: "盒" },
        { name: "豆腐", price: 20, unit: "盒" },
        { name: "九層塔", price: 15, unit: "把" }
    ]
};

// 生成簡單題目
function generateEasyQuestion() {
    const numItems = Math.floor(Math.random() * 3) + 3; // 3-5個物品
    const allItems = [...ingredients.vegetables, ...ingredients.fruits, ...ingredients.others];
    const selectedItems = [];
    let totalCost = 0;
    
    for (let i = 0; i < numItems; i++) {
        const randomItem = allItems[Math.floor(Math.random() * allItems.length)];
        const quantity = Math.floor(Math.random() * 3) + 1;
        selectedItems.push({
            name: randomItem.name,
            price: randomItem.price,
            unit: randomItem.unit,
            quantity: quantity
        });
        totalCost += randomItem.price * quantity;
    }
    
    let questionText = "阿嬤要買：\n";
    selectedItems.forEach(item => {
        questionText += `${item.name} ${item.price}元/${item.unit} x${item.quantity}\n`;
    });
    questionText += "👉 一共要付多少錢？";
    
    const options = [
        `$${totalCost}`,
        `$${totalCost + 10}`,
        `$${totalCost - 10}`,
        `$${totalCost + 20}`
    ].sort(() => Math.random() - 0.5);
    
    return {
        question: questionText,
        options: options,
        correctAnswer: `$${totalCost}`
    };
}

// 生成普通題目
function generateNormalQuestion() {
    const type = Math.random() < 0.5 ? 'discount' : 'budget';
    
    if (type === 'discount') {
        const item1 = ingredients.vegetables[Math.floor(Math.random() * ingredients.vegetables.length)];
        const item2 = ingredients.others[Math.floor(Math.random() * ingredients.others.length)];
        
        const quantity1 = Math.floor(Math.random() * 3) + 2;
        const quantity2 = Math.floor(Math.random() * 3) + 2;
        
        let totalCost = (item1.price * quantity1) + (item2.price * quantity2);
        const discount = Math.floor(Math.random() * 20) + 10;
        totalCost -= discount;
        
        const questionText = `${item1.name} ${item1.price}元/${item1.unit} x${quantity1}\n` +
                           `${item2.name} ${item2.price}元/${item2.unit} x${quantity2}\n` +
                           `👉 優惠${discount}元，一共要付多少錢？`;
        
        const options = [
            `$${totalCost}`,
            `$${totalCost + 10}`,
            `$${totalCost - 10}`,
            `$${totalCost + 20}`
        ].sort(() => Math.random() - 0.5);
        
        return {
            question: questionText,
            options: options,
            correctAnswer: `$${totalCost}`
        };
    } else {
        const item = ingredients.vegetables[Math.floor(Math.random() * ingredients.vegetables.length)];
        const budget = Math.floor(Math.random() * 200) + 100;
        const maxQuantity = Math.floor(budget / item.price);
        
        const questionText = `阿嬤帶了$${budget}元去買${item.name}（${item.price}元/${item.unit}）\n` +
                           `👉 最多可以買幾個${item.unit}？`;
        
        const options = [
            `${maxQuantity}${item.unit}`,
            `${maxQuantity + 1}${item.unit}`,
            `${maxQuantity - 1}${item.unit}`,
            `${maxQuantity + 2}${item.unit}`
        ].sort(() => Math.random() - 0.5);
        
        return {
            question: questionText,
            options: options,
            correctAnswer: `${maxQuantity}${item.unit}`
        };
    }
}

// 生成困難題目
function generateHardQuestion() {
    const type = Math.random() < 0.5 ? 'combination' : 'budget';
    
    if (type === 'combination') {
        const items = [];
        let totalCost = 0;
        
        const categories = ['vegetables', 'meat', 'others'];
        categories.sort(() => Math.random() - 0.5);
        
        for (let i = 0; i < Math.floor(Math.random() * 2) + 2; i++) {
            const category = categories[i];
            const item = ingredients[category][Math.floor(Math.random() * ingredients[category].length)];
            const quantity = Math.floor(Math.random() * 3) + 1;
            items.push({
                name: item.name,
                price: item.price,
                unit: item.unit,
                quantity: quantity
            });
            totalCost += item.price * quantity;
        }
        
        let questionText = "阿嬤要買：\n";
        items.forEach(item => {
            questionText += `${item.name} ${item.price}元/${item.unit} x${item.quantity}\n`;
        });
        questionText += "👉 一共要付多少錢？";
        
        const options = [
            `$${totalCost}`,
            `$${totalCost + 20}`,
            `$${totalCost - 20}`,
            `$${totalCost + 30}`
        ].sort(() => Math.random() - 0.5);
        
        return {
            question: questionText,
            options: options,
            correctAnswer: `$${totalCost}`
        };
    } else {
        const budget = Math.floor(Math.random() * 300) + 200;
        const items = [];
        let totalCost = 0;
        
        const allItems = [...ingredients.vegetables, ...ingredients.meat, ...ingredients.others];
        const numItems = Math.floor(Math.random() * 2) + 2;
        
        for (let i = 0; i < numItems; i++) {
            const item = allItems[Math.floor(Math.random() * allItems.length)];
            const quantity = Math.floor(Math.random() * 3) + 1;
            items.push({
                name: item.name,
                price: item.price,
                unit: item.unit,
                quantity: quantity
            });
            totalCost += item.price * quantity;
        }
        
        const questionText = `阿嬤帶了$${budget}元去買菜，要買：\n` +
                           items.map(item => `${item.name} ${item.price}元/${item.unit} x${item.quantity}`).join('\n') +
                           `\n👉 錢夠不夠？如果不夠，還差多少？`;
        
        const difference = totalCost - budget;
        const options = [
            difference <= 0 ? "錢夠" : `還差$${difference}`,
            difference <= 0 ? `還差$${Math.abs(difference)}` : "錢夠",
            difference <= 0 ? `還差$${Math.abs(difference) + 10}` : `還差$${difference + 10}`,
            difference <= 0 ? `還差$${Math.abs(difference) - 10}` : `還差$${difference - 10}`
        ].sort(() => Math.random() - 0.5);
        
        return {
            question: questionText,
            options: options,
            correctAnswer: difference <= 0 ? "錢夠" : `還差$${difference}`
        };
    }
}

// 根據難度生成題目
function generateQuestion(difficulty) {
    switch (difficulty) {
        case 'easy':
            return generateEasyQuestion();
        case 'normal':
            return generateNormalQuestion();
        case 'hard':
            return generateHardQuestion();
        default:
            return generateEasyQuestion();
    }
}

// 遊戲控制函數
function startGame() {
    debugLog('開始遊戲');
    score = 0;
    timer = 60;
    savedTimer = 60;
    gamePaused = false;
    gameStarted = true;
    updateScore();
    document.getElementById('timer').textContent = timer;
    document.getElementById('end-btn').style.display = 'inline-block';
    document.getElementById('pause-btn').style.display = 'inline-block';
    document.getElementById('resume-btn').style.display = 'none';
    loadQuestion();
    startTimer();
}

function loadQuestion() {
    if (gamePaused) return;
    
    const question = generateQuestion(currentDifficulty);
    const questionElement = document.getElementById('question');
    const optionsContainer = document.getElementById('options-container');
    
    questionElement.textContent = question.question;
    optionsContainer.innerHTML = '';
    
    question.options.forEach(option => {
        const button = document.createElement('button');
        button.textContent = option;
        button.onclick = () => checkAnswer(option, question.correctAnswer);
        optionsContainer.appendChild(button);
    });
}

function checkAnswer(selectedAnswer, correctAnswer) {
    if (gamePaused) return;
    
    if (selectedAnswer === correctAnswer) {
        score += 3;
        updateScore();
    }
    
    loadQuestion();
}

function updateScore() {
    document.getElementById('score').textContent = score;
    if (score > highScore) {
        highScore = score;
        document.getElementById('high-score').textContent = highScore;
    }
}

function startTimer() {
    if (interval) {
        clearInterval(interval);
    }
    interval = setInterval(updateTimer, 1000);
}

function updateTimer() {
    if (!gamePaused && gameStarted) {
        timer--;
        document.getElementById('timer').textContent = timer;
        if (timer <= 0) {
            clearInterval(interval);
            interval = null;
            endGame();
        }
    }
}

function pauseGame() {
    if (!gameStarted) return;
    
    gamePaused = true;
    savedTimer = timer;
    
    if (interval) {
        clearInterval(interval);
        interval = null;
    }
    
    document.getElementById('pause-btn').style.display = 'none';
    document.getElementById('resume-btn').style.display = 'inline-block';
    
    const optionsContainer = document.getElementById('options-container');
    const buttons = optionsContainer.getElementsByTagName('button');
    for (let button of buttons) {
        button.disabled = true;
    }
}

function resumeGame() {
    if (!gameStarted) return;
    
    gamePaused = false;
    timer = savedTimer;
    document.getElementById('timer').textContent = timer;
    
    document.getElementById('pause-btn').style.display = 'inline-block';
    document.getElementById('resume-btn').style.display = 'none';
    
        const optionsContainer = document.getElementById('options-container');
    const buttons = optionsContainer.getElementsByTagName('button');
    for (let button of buttons) {
            button.disabled = false;
    }
    
    startTimer();
}

async function saveGameResult(bonusScore, playTime) {
    try {
        const response = await fetch('Vegetable-Cost.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                member_id: memberId,
                difficulty: currentDifficulty,
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

function endGame() {
    debugLog('執行 endGame，gameStarted: ' + gameStarted);
    if (!gameStarted) return;
    if (interval) {
        clearInterval(interval);
        interval = null;
    }
    gameStarted = false;
    gamePaused = false;
    let bonusScore = 0;
    if (currentDifficulty === 'easy' && score >= 200) bonusScore = 20;
    else if (currentDifficulty === 'normal' && score >= 450) bonusScore = 50;
    else if (currentDifficulty === 'hard' && score >= 600) bonusScore = 100;
    const modal = document.getElementById('game-over-modal');
    const finalScore = document.getElementById('final-score');
    finalScore.textContent = `${score}分${bonusScore > 0 ? ` (獎勵+${bonusScore}分)` : ''}`;
    modal.classList.remove('hidden');
    const playTime = 60 - timer;
    saveGameResult(bonusScore, playTime);
}

function restartGame() {
    debugLog('重新開始遊戲，回到難度選擇');
    document.getElementById('game-over-modal').classList.add('hidden');
    document.querySelector('.game-container').style.display = 'none';
    document.getElementById('difficulty-modal').classList.remove('hidden');
}

function exitGame() {
    window.location.href = 'index.php';
}

// 遊戲說明視窗
function openHelpModal() {
    document.getElementById('help-modal').classList.remove('hidden');
}

function closeHelpModal() {
    document.getElementById('help-modal').classList.add('hidden');
}

// 選擇難度
function selectDifficulty(difficulty) {
    debugLog('選擇難度: ' + difficulty);
    currentDifficulty = difficulty;
    document.getElementById('difficulty-modal').classList.add('hidden');
    document.querySelector('.game-container').style.display = 'block';
    startGame();
}

// 初始化遊戲
// 只做事件綁定，不自動開始或結束遊戲

document.addEventListener('DOMContentLoaded', function() {
    debugLog('初始化完成，隱藏結束視窗與遊戲主體，只顯示難度選擇');
    document.getElementById('game-over-modal').classList.add('hidden');
    document.querySelector('.game-container').style.display = 'none';
    document.getElementById('difficulty-modal').classList.remove('hidden');

    document.getElementById('help-icon').addEventListener('click', openHelpModal);
    document.getElementById('end-btn').addEventListener('click', endGame);
    document.getElementById('pause-btn').addEventListener('click', pauseGame);
    document.getElementById('resume-btn').addEventListener('click', resumeGame);
    document.getElementById('restart-btn').addEventListener('click', restartGame);
    document.getElementById('exit-btn').addEventListener('click', exitGame);
    document.querySelectorAll('.difficulty-btn').forEach(button => {
        button.addEventListener('click', () => {
            selectDifficulty(button.dataset.difficulty);
        });
    });
    // 統一綁定所有 close-btn
    document.querySelectorAll('.close-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            this.closest('.modal').classList.add('hidden');
        });
    });
}); 