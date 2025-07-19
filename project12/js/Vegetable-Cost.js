function debugLog() {}
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
let isPaused = false;

// 食材資料庫（從資料庫 AJAX 取得）
let ingredients = {};

async function fetchIngredients() {
    const response = await fetch('Vegetable-Cost.php?get_ingredients=1');
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
        const budget = [40, 50, 60, 70, 80][Math.floor(Math.random() * 5)];
        let combos = [];
        for (let i = 0; i < selectedItems.length; i++) {
            for (let j = i + 1; j < selectedItems.length; j++) {
                let sum = selectedItems[i].price + selectedItems[j].price;
                if (sum <= budget) combos.push([selectedItems[i], selectedItems[j]]);
            }
        }
        if (combos.length === 0) combos.push([selectedItems[0], selectedItems[1]]);
        // 只選一組作為正確答案
        const answerCombo = combos[Math.floor(Math.random() * combos.length)];
        let questionText = selectedItems.map(item => `${getIngredientWithEmoji(item.name)} $${item.price}`).join('<br>');
        questionText += `<br><br>我只有 $${budget}，可以買「哪一組」？`;
        // 選項
        let options = [answerCombo];
        let comboTries = 0;
        while (options.length < 4 && comboTries < 20) {
            let fakeCombo = combos[Math.floor(Math.random() * combos.length)];
            if (!options.some(opt => opt[0] === fakeCombo[0] && opt[1] === fakeCombo[1])) {
                options.push(fakeCombo);
            }
            comboTries++;
        }
        // 若還是不足4個，隨機補假組合
        while (options.length < 4) {
            let fakeCombo = [];
            let tries = 0;
            while (fakeCombo.length < 2 && tries < 10) {
                const item = selectedItems[Math.floor(Math.random() * selectedItems.length)];
                if (!fakeCombo.includes(item)) fakeCombo.push(item);
                tries++;
            }
            if (!options.some(opt => opt[0] === fakeCombo[0] && opt[1] === fakeCombo[1])) {
                options.push(fakeCombo);
            }
        }
        options = options.map(opt => ({ text: opt.map(i => getIngredientWithEmoji(i.name)).join('＋') })).sort(() => Math.random() - 0.5);
        return {
            question: questionText,
            options: options,
            correctAnswer: answerCombo.map(i => getIngredientWithEmoji(i.name)).join('＋'),
            items: selectedItems
        };
    } else {
        // 其他題型維持2~3種
        const numItems = Math.min(Math.floor(Math.random() * 2) + 2, allItems.length); // 2~3種
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
    if (type === '指定物品') {
        // 隨機選2樣要買的物品
        const buyCount = Math.min(2, selectedItems.length);
        const buyItems = [];
        let buyNames = new Set();
        let buyTries = 0;
        while (buyItems.length < buyCount && buyTries < 10) {
            const item = selectedItems[Math.floor(Math.random() * selectedItems.length)];
            if (!buyNames.has(item.name)) {
                buyItems.push(item);
                buyNames.add(item.name);
            }
            buyTries++;
        }
        const total = buyItems.reduce((sum, item) => sum + item.price, 0);
        let questionText = selectedItems.map(item => `${getIngredientWithEmoji(item.name)} $${item.price}`).join('<br>');
        questionText += `<br><br>如果我要買「${buyItems.map(i => getIngredientWithEmoji(i.name)).join('＋')}」，請問要多少錢？`;
        // 選項
        const options = [total];
        let offsetTries = 0;
        while (options.length < 4 && offsetTries < 20) {
            let offset = (Math.floor(Math.random() * 5) + 1) * 5;
            let fake = Math.random() < 0.5 ? total + offset : total - offset;
            if (fake > 0 && !options.includes(fake)) options.push(fake);
            offsetTries++;
        }
        return {
            question: questionText,
            options: options.map(v => ({ text: `$${v}` })).sort(() => Math.random() - 0.5),
            correctAnswer: `$${total}`,
            items: selectedItems
        };
    } else {
        // 預算組合題
        const budget = [40, 50, 60, 70, 80][Math.floor(Math.random() * 5)];
        let combos = [];
        for (let i = 0; i < selectedItems.length; i++) {
            for (let j = i + 1; j < selectedItems.length; j++) {
                let sum = selectedItems[i].price + selectedItems[j].price;
                if (sum <= budget) combos.push([selectedItems[i], selectedItems[j]]);
            }
        }
        if (combos.length === 0) combos.push([selectedItems[0], selectedItems[1]]);
        const answerCombo = combos[Math.floor(Math.random() * combos.length)];
        let questionText = selectedItems.map(item => `${getIngredientWithEmoji(item.name)} $${item.price}`).join('<br>');
        questionText += `<br><br>我只有 $${budget}，可以買「哪些組合」？`;
        // 選項
        let options = [answerCombo];
        let comboTries = 0;
        while (options.length < 4 && comboTries < 20) {
            let fakeCombo = combos[Math.floor(Math.random() * combos.length)];
            if (!options.some(opt => opt[0] === fakeCombo[0] && opt[1] === fakeCombo[1])) {
                options.push(fakeCombo);
            }
            comboTries++;
        }
        // 若還是不足4個，隨機補假組合
        while (options.length < 4) {
            let fakeCombo = [];
            let tries = 0;
            while (fakeCombo.length < 2 && tries < 10) {
                const item = selectedItems[Math.floor(Math.random() * selectedItems.length)];
                if (!fakeCombo.includes(item)) fakeCombo.push(item);
                tries++;
            }
            if (!options.some(opt => opt[0] === fakeCombo[0] && opt[1] === fakeCombo[1])) {
                options.push(fakeCombo);
            }
        }
        options = options.map(opt => ({ text: opt.map(i => getIngredientWithEmoji(i.name)).join('＋') })).sort(() => Math.random() - 0.5);
        return {
            question: questionText,
            options: options,
            correctAnswer: answerCombo.map(i => getIngredientWithEmoji(i.name)).join('＋'),
            items: selectedItems
        };
    }
}

// 生成普通題目
function generateNormalQuestion() {
    // 顯示3~5種蔬果
    const allItems = ingredients.vegetables.concat(ingredients.fruits, ingredients.others);
    const numItems = Math.min(Math.floor(Math.random() * 3) + 3, allItems.length); // 3~5種
    const selectedItems = [];
    let usedNames = new Set();
    let tries = 0;
    while (selectedItems.length < numItems && tries < 30) {
        const randomItem = allItems[Math.floor(Math.random() * allItems.length)];
        if (!usedNames.has(randomItem.name)) {
            selectedItems.push(randomItem);
            usedNames.add(randomItem.name);
        }
        tries++;
    }
    // 題型隨機：1. 促銷價計算 2. 預算可買數量
    const type = Math.random() < 0.5 ? '促銷' : '預算';
    if (type === '促銷') {
        // 促銷：買4送1、3盒100元
        const veg = ingredients.vegetables[Math.floor(Math.random() * ingredients.vegetables.length)];
        const egg = ingredients.others.find(i => i.name.includes('蛋')) || ingredients.others[Math.floor(Math.random() * ingredients.others.length)];
        let questionText = `${getIngredientWithEmoji(veg.name)} $${veg.price}/${veg.unit}，買4把送1把。<br>${getIngredientWithEmoji(egg.name)} $${egg.price}/${egg.unit}，買3${egg.unit}共100元。<br><br>`;
        // 隨機數量
        const vegCount = Math.floor(Math.random() * 3) + 2; // 2~4
        const eggCount = Math.floor(Math.random() * 2) + 2; // 2~3
        // 計算價格
        let vegTotal = veg.price * vegCount;
        if (vegCount >= 5) vegTotal = veg.price * (vegCount - 1); // 買4送1
        let eggTotal = egg.price * eggCount;
        if (eggCount === 3) eggTotal = 100;
        const total = vegTotal + eggTotal;
        let options = [total];
        let offsetTries = 0;
        while (options.length < 4 && offsetTries < 20) {
            let offset = (Math.floor(Math.random() * 3) + 1) * 10;
            let fake = Math.random() < 0.5 ? total + offset : total - offset;
            if (fake > 0 && !options.includes(fake)) options.push(fake);
            offsetTries++;
        }
        // 補足4個選項
        while (options.length < 4) {
            let fake = total + (Math.floor(Math.random() * 8) + 2) * 5 * (Math.random() < 0.5 ? 1 : -1);
            fake = Math.abs(fake);
            if (fake > 0 && !options.includes(fake)) options.push(fake);
        }
        return {
            question: questionText,
            options: options.map(v => ({ text: `$${v}` })).sort(() => Math.random() - 0.5),
            correctAnswer: `$${total}`,
            items: selectedItems.length ? selectedItems : [veg, egg]
        };
    } else {
        // 預算可買數量
        const veg = ingredients.vegetables[Math.floor(Math.random() * ingredients.vegetables.length)];
        const budget = [100, 120, 150, 200][Math.floor(Math.random() * 4)];
        const promo = Math.random() < 0.5;
        let maxCount;
        let promoText = '';
        if (promo) {
            promoText = `（買4把送1把）`;
            maxCount = Math.floor(budget / (veg.price * 4 / 5));
        } else {
            maxCount = Math.floor(budget / veg.price);
        }
        let questionText = `${getIngredientWithEmoji(veg.name)} $${veg.price}/${veg.unit} ${promoText}<br>阿嬤帶${budget}元去買${veg.name}，可以買幾${veg.unit}？`;
        let options = [maxCount];
        let offsetTries = 0;
        while (options.length < 4 && offsetTries < 20) {
            let fake = maxCount + (Math.floor(Math.random() * 3) - 1);
            if (fake > 0 && !options.includes(fake)) options.push(fake);
            offsetTries++;
        }
        // 補足4個選項
        while (options.length < 4) {
            let fake = maxCount + (Math.floor(Math.random() * 8) + 2) * (Math.random() < 0.5 ? 1 : -1);
            fake = Math.abs(fake);
            if (fake > 0 && !options.includes(fake)) options.push(fake);
        }
        return {
            question: questionText,
            options: options.map(v => ({ text: `${v}${veg.unit}` })).sort(() => Math.random() - 0.5),
            correctAnswer: `${maxCount}${veg.unit}`,
            items: selectedItems.length ? selectedItems : [veg]
        };
    }
}

// 生成困難題目
function generateHardQuestion() {
    // 顯示5~7種蔬果
    const allItems = [].concat(ingredients.vegetables, ingredients.fruits, ingredients.meat, ingredients.others);
    const numItems = Math.min(Math.floor(Math.random() * 3) + 5, allItems.length); // 5~7種
    const selectedItems = [];
    let usedNames = new Set();
    let tries = 0;
    while (selectedItems.length < numItems && tries < 50) {
        const item = allItems[Math.floor(Math.random() * allItems.length)];
        if (!usedNames.has(item.name)) {
            selectedItems.push(item);
            usedNames.add(item.name);
        }
        tries++;
    }
    // 題型隨機：1. 買指定組合 2. 預算能買哪些 3. 只能買兩樣/湊滿不超過金額
    const typeRand = Math.random();
    if (typeRand < 0.4) {
        // 指定組合
        const veg = ingredients.vegetables[Math.floor(Math.random() * ingredients.vegetables.length)];
        const meat = ingredients.meat[Math.floor(Math.random() * ingredients.meat.length)];
        const egg = ingredients.others.find(i => i.name.includes('蛋')) || ingredients.others[Math.floor(Math.random() * ingredients.others.length)];
        let questionText = `${getIngredientWithEmoji(veg.name)} $${veg.price}/${veg.unit}<br>${getIngredientWithEmoji(meat.name)} $${meat.price}/斤<br>${getIngredientWithEmoji(egg.name)} $${egg.price}/${egg.unit}<br><br>如果阿嬤要買1${veg.unit}${getIngredientWithEmoji(veg.name)}＋1${egg.unit}${getIngredientWithEmoji(egg.name)}＋2斤${getIngredientWithEmoji(meat.name)}，要多少錢？`;
        const total = veg.price + egg.price + meat.price * 2;
        const options = [total];
        let offsetTries = 0;
        while (options.length < 4 && offsetTries < 20) {
            let offset = (Math.floor(Math.random() * 3) + 1) * 20;
            let fake = Math.random() < 0.5 ? total + offset : total - offset;
            if (fake > 0 && !options.includes(fake)) options.push(fake);
            offsetTries++;
        }
        return {
            question: questionText,
            options: options.map(v => ({ text: `$${v}` })).sort(() => Math.random() - 0.5),
            correctAnswer: `$${total}`,
            items: selectedItems.length ? selectedItems : [veg, meat, egg]
        };
    } else if (typeRand < 0.7) {
        // 預算能買哪些
        const budget = [100, 150, 200, 250, 300][Math.floor(Math.random() * 5)];
        let combos = [];
        for (let i = 0; i < selectedItems.length; i++) {
            for (let j = i + 1; j < selectedItems.length; j++) {
                let sum = selectedItems[i].price + selectedItems[j].price;
                if (sum <= budget) combos.push([selectedItems[i], selectedItems[j]]);
            }
        }
        if (combos.length === 0) combos.push([selectedItems[0], selectedItems[1]]);
        const answerCombo = combos[Math.floor(Math.random() * combos.length)];
        let questionText = selectedItems.map(item => `${getIngredientWithEmoji(item.name)} $${item.price}`).join('<br>');
        questionText += `<br><br>阿嬤只帶了$${budget}，可以買哪些東西回家？`;
        let options = [answerCombo];
        let comboTries = 0;
        while (options.length < 4 && comboTries < 30) {
            let fakeCombo = combos[Math.floor(Math.random() * combos.length)];
            if (!options.some(opt => opt[0] === fakeCombo[0] && opt[1] === fakeCombo[1])) {
                options.push(fakeCombo);
            }
            comboTries++;
        }
        while (options.length < 4) {
            let fakeCombo = [];
            let tries = 0;
            while (fakeCombo.length < 2 && tries < 10) {
                const item = selectedItems[Math.floor(Math.random() * selectedItems.length)];
                if (!fakeCombo.includes(item)) fakeCombo.push(item);
                tries++;
            }
            if (!options.some(opt => opt[0] === fakeCombo[0] && opt[1] === fakeCombo[1])) {
                options.push(fakeCombo);
            }
        }
        options = options.map(opt => ({ text: opt.map(i => getIngredientWithEmoji(i.name)).join('＋') })).sort(() => Math.random() - 0.5);
        return {
            question: questionText,
            options: options,
            correctAnswer: answerCombo.map(i => getIngredientWithEmoji(i.name)).join('＋'),
            items: selectedItems
        };
    } else {
        // 只能買兩樣/湊滿不超過金額
        const limit = [100, 150, 200, 250][Math.floor(Math.random() * 4)];
        let combos = [];
        for (let i = 0; i < selectedItems.length; i++) {
            for (let j = i + 1; j < selectedItems.length; j++) {
                let sum = selectedItems[i].price + selectedItems[j].price;
                if (sum <= limit) combos.push([selectedItems[i], selectedItems[j]]);
            }
        }
        if (combos.length === 0) combos.push([selectedItems[0], selectedItems[1]]);
        const answerCombo = combos[Math.floor(Math.random() * combos.length)];
        let questionText = selectedItems.map(item => `${getIngredientWithEmoji(item.name)} $${item.price}`).join('<br>');
        questionText += `<br><br>小胖只帶了$${limit}，只能買兩樣，湊滿不超過這個金額，可以買哪些？`;
        let options = [answerCombo];
        let comboTries = 0;
        while (options.length < 4 && comboTries < 30) {
            let fakeCombo = combos[Math.floor(Math.random() * combos.length)];
            if (!options.some(opt => opt[0] === fakeCombo[0] && opt[1] === fakeCombo[1])) {
                options.push(fakeCombo);
            }
            comboTries++;
        }
        while (options.length < 4) {
            let fakeCombo = [];
            let tries = 0;
            while (fakeCombo.length < 2 && tries < 10) {
                const item = selectedItems[Math.floor(Math.random() * selectedItems.length)];
                if (!fakeCombo.includes(item)) fakeCombo.push(item);
                tries++;
            }
            if (!options.some(opt => opt[0] === fakeCombo[0] && opt[1] === fakeCombo[1])) {
                options.push(fakeCombo);
            }
        }
        options = options.map(opt => ({ text: opt.map(i => getIngredientWithEmoji(i.name)).join('＋') })).sort(() => Math.random() - 0.5);
        return {
            question: questionText,
            options: options,
            correctAnswer: answerCombo.map(i => getIngredientWithEmoji(i.name)).join('＋'),
            items: selectedItems
        };
    }
}

// 顯示題目時帶圖片
function loadQuestion() {
    if (gamePaused) return;
    let question;
    switch (currentDifficulty) {
        case 'easy':
            question = generateEasyQuestion();
            break;
        case 'normal':
            question = generateNormalQuestion();
            break;
        case 'hard':
            question = generateHardQuestion();
            break;
        default:
            question = generateEasyQuestion();
    }
    const questionElement = document.getElementById('question');
    const optionsContainer = document.getElementById('options-container');
    let lines = [];
    if (question.question) {
        lines.push(question.question);
    }
    questionElement.innerHTML = lines.join('<br>');
    optionsContainer.innerHTML = '';
    // 缺圖清單
    let missingImages = [];
    question.options.forEach(option => {
        const button = document.createElement('button');
        button.textContent = option.text;
        // 嘗試找出對應的食材物件
        let matchedItem = null;
        if (question.items && question.items.length > 0) {
            // 如果是組合題，option.text 可能是「A＋B」
            let names = option.text.replace(/\$/g, '').split('＋').map(s => stripEmoji(s));
            matchedItem = question.items.find(item => names.includes(item.name));
            // 如果是單一選項，直接比對
            if (!matchedItem && names.length === 1) {
                matchedItem = question.items.find(item => stripEmoji(option.text).includes(item.name));
            }
        }
        // 顯示圖片
        if (matchedItem && matchedItem.image) {
            const img = document.createElement('img');
            img.src = 'img/' + matchedItem.image;
            img.alt = matchedItem.name;
            img.style.width = '32px';
            img.style.height = '32px';
            img.style.marginRight = '6px';
            button.prepend(img);
        } else if (matchedItem && !matchedItem.image) {
            missingImages.push(matchedItem.name);
        }
        button.onclick = () => checkAnswer(option.text, question.correctAnswer);
        optionsContainer.appendChild(button);
    });
    if (missingImages.length > 0) {
        console.log('缺圖清單：', Array.from(new Set(missingImages)));
    }
}

// 遊戲控制函數
function startGame() {
    debugLog('開始遊戲');
    score = 0;
    // 根據難度設定時間
    if (currentDifficulty === 'easy') {
        timer = 80;
        savedTimer = 80;
    } else if (currentDifficulty === 'normal') {
        timer = 150;
        savedTimer = 150;
    } else if (currentDifficulty === 'hard') {
        timer = 200;
        savedTimer = 200;
    } else {
        timer = 60;
        savedTimer = 60;
    }
    gamePaused = false;
    gameStarted = true;
    updateScore();
    document.getElementById('timer').textContent = timer;
    const endBtn = document.getElementById('end-btn');
    if (endBtn) endBtn.style.display = 'inline-block';
    const pauseBtn = document.getElementById('pause-btn');
    if (pauseBtn) pauseBtn.style.display = 'inline-block';
    const resumeBtn = document.getElementById('resume-btn');
    if (resumeBtn) resumeBtn.style.display = 'none';
    loadQuestion();
    startTimer();
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
    const optionsContainer = document.getElementById('options-container');
    const buttons = optionsContainer.getElementsByTagName('button');
    for (let button of buttons) {
        button.disabled = true;
    }
}

function resumeGame() {
    if (!gameStarted) return;
    if (!gamePaused) return; // 避免重複執行
    gamePaused = false;
    timer = savedTimer;
    document.getElementById('timer').textContent = timer;
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
    // 過關分數設定
    let passScore = 0;
    if (currentDifficulty === 'easy') passScore = 15;
    else if (currentDifficulty === 'normal') passScore = 20;
    else if (currentDifficulty === 'hard') passScore = 25;
    // 顯示 modal
    const modal = document.getElementById('game-over-modal');
    let title = '';
    let msg = '';
    if (score >= passScore) {
        title = '恭喜破關';
        msg = `難度：${currentDifficulty === 'easy' ? '簡單' : currentDifficulty === 'normal' ? '普通' : '困難'}<br>獲得分數：${score}`;
    } else {
        title = '遊戲失敗';
        msg = `難度：${currentDifficulty === 'easy' ? '簡單' : currentDifficulty === 'normal' ? '普通' : '困難'}<br>未在時間內達成分數`;
    }
    modal.querySelector('.gameover-title').innerHTML = title;
    modal.querySelector('.gameover-msg').innerHTML = msg;
    modal.classList.remove('hidden');
    const playTime = (currentDifficulty === 'easy' ? 80 : currentDifficulty === 'normal' ? 150 : 200) - timer;
    saveGameResult(0, playTime);
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

document.addEventListener('DOMContentLoaded', async function() {
    document.getElementById('help-modal').classList.add('hidden'); // 強制一開始隱藏
    await fetchIngredients();
    debugLog('初始化完成，隱藏結束視窗與遊戲主體，只顯示難度選擇');
    document.getElementById('game-over-modal').classList.add('hidden');
    document.querySelector('.game-container').style.display = 'none';
    document.getElementById('difficulty-modal').classList.remove('hidden');

    // 幫所有難度按鈕綁定事件
    document.querySelectorAll('.difficulty-btn').forEach(button => {
        button.addEventListener('click', () => {
            selectDifficulty(button.dataset.difficulty);
        });
    });

    // 幫所有說明按鈕綁定事件
    document.querySelectorAll('.help-btn').forEach(btn => {
        btn.addEventListener('click', openHelpModal);
    });

    // 幫所有關閉按鈕綁定事件
    document.querySelectorAll('.close-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            this.closest('.modal').classList.add('hidden');
        });
    });

    // 其他控制按鈕
    const helpIcon = document.getElementById('help-icon');
    if (helpIcon) helpIcon.addEventListener('click', openHelpModal);

    const endBtn = document.getElementById('end-btn');
    if (endBtn) endBtn.addEventListener('click', endGame);

    const pauseBtn = document.getElementById('pause-btn');
    if (pauseBtn) {
        pauseBtn.addEventListener('click', function() {
            if (!isPaused) {
                pauseGame();
                pauseBtn.textContent = '繼續遊戲';
            } else {
                resumeGame();
                pauseBtn.textContent = '暫停遊戲';
            }
            isPaused = !isPaused;
        });
    }

    const resumeBtn = document.getElementById('resume-btn');
    if (resumeBtn) resumeBtn.addEventListener('click', resumeGame);

    const restartBtn = document.getElementById('restart-btn');
    if (restartBtn) restartBtn.addEventListener('click', restartGame);

    // 結束視窗的按鈕另外綁定
    const modalRestartBtn = document.getElementById('modal-restart-btn');
    if (modalRestartBtn) modalRestartBtn.addEventListener('click', restartGame);

    const exitBtn = document.getElementById('exit-btn');
    if (exitBtn) exitBtn.addEventListener('click', exitGame);

    // 保險：強制 200ms 後隱藏 help-modal
    setTimeout(() => {
        document.getElementById('help-modal').classList.add('hidden');
    }, 200);
}); 
