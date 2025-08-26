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

// 獲取會員ID
let memberId = window.phpMemberId || 8;

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

// 清理食材名稱（移除多餘空白）
function cleanIngredientName(str) {
    return str.trim();
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
        while (selectedItems.length < numItems && tries < 50) {
            const randomItem = allItems[Math.floor(Math.random() * allItems.length)];
            if (!usedNames.has(randomItem.name)) {
                selectedItems.push(randomItem);
                usedNames.add(randomItem.name);
            }
            tries++;
        }
        
        // 確保至少有5個項目，如果不足則重複選擇
        while (selectedItems.length < 5 && allItems.length >= 5) {
            for (let item of allItems) {
                if (!usedNames.has(item.name) && selectedItems.length < 5) {
                    selectedItems.push(item);
                    usedNames.add(item.name);
                }
            }
            // 如果還是沒有5個，就重複使用已有的項目
            if (selectedItems.length < 5) {
                const existingItems = [...selectedItems];
                for (let item of existingItems) {
                    if (selectedItems.length < 5) {
                        selectedItems.push(item);
                    }
                }
            }
        }
        
        // 重新設計：確保只有一個正確答案
        // 先計算所有可能的組合
        let allCombos = [];
        for (let i = 0; i < selectedItems.length; i++) {
            for (let j = i + 1; j < selectedItems.length; j++) {
                allCombos.push([selectedItems[i], selectedItems[j]]);
            }
        }
        
        // 選擇一個組合作為正確答案
        const answerCombo = allCombos[0];
        const finalBudget = answerCombo[0].price + answerCombo[1].price;
        
        console.log('正確答案組合:', answerCombo.map(item => item.name).join(' + '), '總價:', finalBudget);
        
        // 生成其他不符合預算的選項
        let combos = [answerCombo];
        
        // 從所有組合中選擇不符合預算的組合
        for (let combo of allCombos) {
            if (combos.length >= 4) break;
            
            const comboSum = combo[0].price + combo[1].price;
            
            // 如果這個組合不符合預算且不是正確答案
            if (comboSum !== finalBudget) {
                // 檢查是否已存在相同的組合（不管順序）
                const isDuplicate = combos.some(opt => 
                    (opt[0] === combo[0] && opt[1] === combo[1]) || 
                    (opt[0] === combo[1] && opt[1] === combo[0])
                );
                if (!isDuplicate) {
                    combos.push(combo);
                    console.log('添加錯誤選項:', combo.map(item => item.name).join(' + '), '總價:', comboSum);
                }
            }
        }
        
        // 如果還不夠4個選項，用隨機組合填充
        while (combos.length < 4) {
            let fakeCombo = [];
            let fillTries = 0;
            while (fakeCombo.length < 2 && fillTries < 10) {
                const item = selectedItems[Math.floor(Math.random() * selectedItems.length)];
                if (!fakeCombo.includes(item)) fakeCombo.push(item);
                fillTries++;
            }
            if (fakeCombo.length === 2) {
                const comboSum = fakeCombo[0].price + fakeCombo[1].price;
                // 檢查是否已存在相同的組合（不管順序）且不符合預算
                const isDuplicate = combos.some(opt => 
                    (opt[0] === fakeCombo[0] && opt[1] === fakeCombo[1]) || 
                    (opt[0] === fakeCombo[1] && opt[1] === fakeCombo[0])
                );
                if (!isDuplicate && comboSum !== finalBudget) {
                    combos.push(fakeCombo);
                    console.log('填充錯誤選項:', fakeCombo.map(item => item.name).join(' + '), '總價:', comboSum);
                }
            }
        }
        
        // 如果還不夠4個選項，用隨機組合填充
        while (combos.length < 4) {
            let fakeCombo = [];
            let fillTries = 0;
            while (fakeCombo.length < 2 && fillTries < 10) {
                const item = selectedItems[Math.floor(Math.random() * selectedItems.length)];
                if (!fakeCombo.includes(item)) fakeCombo.push(item);
                fillTries++;
            }
            if (fakeCombo.length === 2) {
                // 檢查是否已存在相同的組合（不管順序）
                const isDuplicate = combos.some(opt => 
                    (opt[0] === fakeCombo[0] && opt[1] === fakeCombo[1]) || 
                    (opt[0] === fakeCombo[1] && opt[1] === fakeCombo[0])
                );
                if (!isDuplicate) {
                    combos.push(fakeCombo);
                }
            }
        }
        
        // **修正這裡：將5種蔬果清單分成兩欄**
        let itemsHTML = '';
        const firstThree = selectedItems.slice(0, 3);
        const remaining = selectedItems.slice(3);
        
        console.log('selectedItems:', selectedItems.length, selectedItems.map(item => item.name));
        console.log('firstThree:', firstThree.length, firstThree.map(item => item.name));
        console.log('remaining:', remaining.length, remaining.map(item => item.name));
        
        const firstThreeHTML = firstThree.map(item => `<div class="item-line"><img src="img/${item.image}" alt="${item.name}">${item.name} $${item.price}</div>`).join('');
        const remainingHTML = remaining.map(item => `<div class="item-line"><img src="img/${item.image}" alt="${item.name}">${item.name} $${item.price}</div>`).join('');
        
        // 確保兩欄都有內容，如果右邊沒有內容，從左邊移動一個過去
        let finalFirstThreeHTML = firstThreeHTML;
        let finalRemainingHTML = remainingHTML;
        
        if (remainingHTML === '' && firstThree.length > 2) {
            // 如果右邊沒有內容且左邊有超過2個，從左邊移動一個到右邊
            const lastItem = firstThree[firstThree.length - 1];
            finalFirstThreeHTML = firstThree.slice(0, 2).map(item => `<div class="item-line"><img src="img/${item.image}" alt="${item.name}">${item.name} $${item.price}</div>`).join('');
            finalRemainingHTML = `<div class="item-line"><img src="img/${lastItem.image}" alt="${lastItem.name}">${lastItem.name} $${lastItem.price}</div>`;
            console.log('移動項目到右邊:', lastItem.name);
        }
        
        console.log('finalFirstThreeHTML:', finalFirstThreeHTML);
        console.log('finalRemainingHTML:', finalRemainingHTML);
        
        itemsHTML = `<div class="item-list-container"><div class="item-list-column">${finalFirstThreeHTML}</div><div class="item-list-column">${finalRemainingHTML}</div></div>`;
        
        let questionText = itemsHTML + `<br><br>阿嬤口袋剛好有<span class="highlight-text">$${finalBudget}元</span>，要買兩種蔬果回家，請幫她挑出<span class="highlight-text">總價剛好$${finalBudget}元</span>的組合。（買太便宜或太貴都不行喔！）`;
        
        let options = combos;
        
        // **修正選項的格式，並保持正確答案為純文字**
        options = options.map(opt => ({ text: opt.map(i => `<img src="img/${i.image}" alt="${i.name}" style="width: 20px; height: 20px; vertical-align: middle; margin-right: 3px;">${i.name}`).join('＋') })).sort(() => Math.random() - 0.5);
        return {
            question: questionText,
            options: options,
            correctAnswer: answerCombo.map(i => i.name).join('＋'), // 正確答案是純文字，用於比對
            items: selectedItems
        };
    } else { // type === '指定物品'
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
        let questionText = selectedItems.map(item => `<img src="img/${item.image}" alt="${item.name}" style="width: 24px; height: 24px; vertical-align: middle; margin-right: 5px;">${item.name} $${item.price}`).join('<br>');
        questionText += `<br><br>如果我要買「${buyItems.map(i => `<img src="img/${i.image}" alt="${i.name}" style="width: 20px; height: 20px; vertical-align: middle; margin-right: 3px;">${i.name}`).join('＋')}」，請問要多少錢？`;
        
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
            correctAnswer: `$${total}`, // 正確答案是純文字，用於比對
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

    // 題型隨機：1. 促銷價計算（包含新的組合計算） 2. 預算可買數量
    const type = Math.random() < 0.6 ? '促銷' : '預算';

    if (type === '促銷') {
        const veg = ingredients.vegetables[Math.floor(Math.random() * ingredients.vegetables.length)];
        const egg = ingredients.others.find(i => i.name.includes('蛋')) || ingredients.others[Math.floor(Math.random() * ingredients.others.length)];

        // 原始促銷資訊
        let questionText = `<img src="img/${veg.image}" alt="${veg.name}" style="width: 24px; height: 24px; vertical-align: middle; margin-right: 5px;">${veg.name} $${veg.price}/${veg.unit}，買4把送1把。<br>`;
        questionText += `<img src="img/${egg.image}" alt="${egg.name}" style="width: 24px; height: 24px; vertical-align: middle; margin-right: 5px;">${egg.name} $${egg.price}/${egg.unit}，買3${egg.unit}共100元。<br><br>`;
        
        // **修改這裡，在阿嬤的題目文字中加上圖片**
        const buyVegCount = Math.floor(Math.random() * 3) + 1; // 1~3把
        const buyEggCount = Math.floor(Math.random() * 2) + 1; // 1~2盒

        questionText += `如果阿嬤要買 ${buyVegCount}${veg.unit} <img src="img/${veg.image}" alt="${veg.name}" style="width: 20px; height: 20px; vertical-align: middle; margin-right: 3px;">${veg.name} 和 ${buyEggCount}${egg.unit} <img src="img/${egg.image}" alt="${egg.name}" style="width: 20px; height: 20px; vertical-align: middle; margin-right: 3px;">${egg.name}，請問總共要花多少錢？`;
        
        // 計算總價（這裡只計算組合，不考慮促銷）
        const total = (veg.price * buyVegCount) + (egg.price * buyEggCount);
        
        // 生成選項
        const options = [total];
        let offsetTries = 0;
        while (options.length < 4 && offsetTries < 20) {
            let offset = (Math.floor(Math.random() * 5) + 1) * 5; // 隨機生成5的倍數
            let fake = Math.random() < 0.5 ? total + offset : total - offset;
            if (fake > 0 && !options.includes(fake)) options.push(fake);
            offsetTries++;
        }
        
        // 確保有4個選項
        while (options.length < 4) {
            let fake = total + (Math.floor(Math.random() * 10) + 1) * (Math.random() < 0.5 ? 1 : -1);
            fake = Math.abs(fake);
            if (fake > 0 && !options.includes(fake)) options.push(fake);
        }

        // 回傳新問題物件
        return {
            question: questionText,
            options: options.map(v => ({ text: `$${v}` })).sort(() => Math.random() - 0.5),
            correctAnswer: `$${total}`,
            items: [veg, egg]
        };

    } else { // type === '預算'
        // ... (預算題邏輯保持不變) ...
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
        let questionText = `<img src="img/${veg.image}" alt="${veg.name}" style="width: 24px; height: 24px; vertical-align: middle; margin-right: 5px;">${veg.name} $${veg.price}/${veg.unit} ${promoText}<br>阿嬤帶${budget}元去買${veg.name}，可以買幾${veg.unit}？`;
        let options = [maxCount];
        let offsetTries = 0;
        while (options.length < 4 && offsetTries < 20) {
            let fake = maxCount + (Math.floor(Math.random() * 3) - 1);
            if (fake > 0 && !options.includes(fake)) options.push(fake);
            offsetTries++;
        }
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
        let questionText = `<img src="img/${veg.image}" alt="${veg.name}" style="width: 24px; height: 24px; vertical-align: middle; margin-right: 5px;">${veg.name} $${veg.price}/${veg.unit}<br><img src="img/${meat.image}" alt="${meat.name}" style="width: 24px; height: 24px; vertical-align: middle; margin-right: 5px;">${meat.name} $${meat.price}/斤<br><img src="img/${egg.image}" alt="${egg.name}" style="width: 24px; height: 24px; vertical-align: middle; margin-right: 5px;">${egg.name} $${egg.price}/${egg.unit}<br><br>如果阿嬤要買1${veg.unit}<img src="img/${veg.image}" alt="${veg.name}" style="width: 20px; height: 20px; vertical-align: middle; margin-right: 3px;">${veg.name}＋1${egg.unit}<img src="img/${egg.image}" alt="${egg.name}" style="width: 20px; height: 20px; vertical-align: middle; margin-right: 3px;">${egg.name}＋2斤<img src="img/${meat.image}" alt="${meat.name}" style="width: 20px; height: 20px; vertical-align: middle; margin-right: 3px;">${meat.name}，要多少錢？`;
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
        let questionText = selectedItems.map(item => `<img src="img/${item.image}" alt="${item.name}" style="width: 24px; height: 24px; vertical-align: middle; margin-right: 5px;">${item.name} $${item.price}`).join('<br>');
        questionText += `<br><br>阿嬤只帶了$${budget}，可以買哪些東西回家？`;
        let options = [answerCombo];
        let comboTries = 0;
        while (options.length < 4 && comboTries < 30) {
            let fakeCombo = combos[Math.floor(Math.random() * combos.length)];
            // 檢查是否已存在相同的組合（不管順序）
            const isDuplicate = options.some(opt => 
                (opt[0] === fakeCombo[0] && opt[1] === fakeCombo[1]) || 
                (opt[0] === fakeCombo[1] && opt[1] === fakeCombo[0])
            );
            if (!isDuplicate) {
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
            if (fakeCombo.length === 2) {
                // 檢查是否已存在相同的組合（不管順序）
                const isDuplicate = options.some(opt => 
                    (opt[0] === fakeCombo[0] && opt[1] === fakeCombo[1]) || 
                    (opt[0] === fakeCombo[1] && opt[1] === fakeCombo[0])
                );
                if (!isDuplicate) {
                    options.push(fakeCombo);
                }
            }
        }
        options = options.map(opt => ({ text: opt.map(i => `<img src="img/${i.image}" alt="${i.name}" style="width: 20px; height: 20px; vertical-align: middle; margin-right: 3px;">${i.name}`).join('＋') })).sort(() => Math.random() - 0.5);
        return {
            question: questionText,
            options: options,
            correctAnswer: answerCombo.map(i => i.name).join('＋'),
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
        let questionText = selectedItems.map(item => `<img src="img/${item.image}" alt="${item.name}" style="width: 24px; height: 24px; vertical-align: middle; margin-right: 5px;">${item.name} $${item.price}`).join('<br>');
        questionText += `<br><br>小胖只帶了$${limit}，只能買兩樣，湊滿不超過這個金額，可以買哪些？`;
        let options = [answerCombo];
        let comboTries = 0;
        while (options.length < 4 && comboTries < 30) {
            let fakeCombo = combos[Math.floor(Math.random() * combos.length)];
            // 檢查是否已存在相同的組合（不管順序）
            const isDuplicate = options.some(opt => 
                (opt[0] === fakeCombo[0] && opt[1] === fakeCombo[1]) || 
                (opt[0] === fakeCombo[1] && opt[1] === fakeCombo[0])
            );
            if (!isDuplicate) {
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
            if (fakeCombo.length === 2) {
                // 檢查是否已存在相同的組合（不管順序）
                const isDuplicate = options.some(opt => 
                    (opt[0] === fakeCombo[0] && opt[1] === fakeCombo[1]) || 
                    (opt[0] === fakeCombo[1] && opt[1] === fakeCombo[0])
                );
                if (!isDuplicate) {
                    options.push(fakeCombo);
                }
            }
        }
        options = options.map(opt => ({ text: opt.map(i => `<img src="img/${i.image}" alt="${i.name}" style="width: 20px; height: 20px; vertical-align: middle; margin-right: 3px;">${i.name}`).join('＋') })).sort(() => Math.random() - 0.5);
        return {
            question: questionText,
            options: options,
            correctAnswer: answerCombo.map(i => i.name).join('＋'),
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
        
        // 檢查選項文字是否包含 HTML（圖片）
        if (option.text.includes('<img')) {
            button.innerHTML = option.text;
        } else {
            button.textContent = option.text;
            // 嘗試找出對應的食材物件
            let matchedItem = null;
            if (question.items && question.items.length > 0) {
                // 如果是組合題，option.text 可能是「A＋B」
                let names = option.text.replace(/\$/g, '').split('＋').map(s => cleanIngredientName(s));
                matchedItem = question.items.find(item => names.includes(item.name));
                // 如果是單一選項，直接比對
                if (!matchedItem && names.length === 1) {
                    matchedItem = question.items.find(item => cleanIngredientName(option.text).includes(item.name));
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

    // 定義一個新的清理函式
    const cleanAnswer = (answer) => {
    // 1. 找到所有 img 標籤並提取 alt 的值
    const altTexts = [];
    const imgRegex = /<img[^>]*alt="([^"]*)"[^>]*>/g;
    let match;
    while ((match = imgRegex.exec(answer)) !== null) {
        altTexts.push(match[1]);
    }
    
    // 2. 清理掉所有 HTML 標籤，取得純文字
    const textOnly = answer.replace(/<[^>]*>/g, '');

    // 3. 將 alt 文本和純文字結合。
    //    如果 altTexts 陣列有值，表示答案是圖片組合，就用 altTexts。
    //    如果 altTexts 沒值，就用純文字。
    if (altTexts.length > 0) {
        return altTexts.join('＋');
    } else {
        // 清理除了文字、數字、空白和以外的所有字元
        return textOnly.replace(/[^\w\s]/g, '');
    }
};

    const cleanSelectedAnswer = cleanAnswer(selectedAnswer);
    const cleanCorrectAnswer = correctAnswer.replace("$",'');
    
    console.log('清理後的選取答案:', cleanSelectedAnswer, '清理後的正確答案:', cleanCorrectAnswer);

    const isCorrect = cleanSelectedAnswer === cleanCorrectAnswer;
    
    if (isCorrect) {
        score += 3;
        updateScore();
        showAnswerFeedback(true);
    } else {
        showAnswerFeedback(false);
    }
    
    // 延遲載入下一題
    setTimeout(() => {
        loadQuestion();
    }, 1500);
}

// 顯示答案反饋
function showAnswerFeedback(isCorrect) {
    console.log('顯示答案反饋:', isCorrect ? '答對了' : '答錯了');
    
    const questionContainer = document.getElementById('question-container');
    if (!questionContainer) {
        console.error('找不到 question-container 元素');
        return;
    }
    
    const feedback = document.createElement('div');
    feedback.id = 'answer-feedback';
    feedback.style.cssText = `
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        font-size: 3rem;
        font-weight: bold;
        color: ${isCorrect ? '#4caf50' : '#f44336'};
        background: rgba(255, 255, 255, 0.95);
        padding: 30px 40px;
        border-radius: 15px;
        z-index: 9999;
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.3);
        border: 3px solid ${isCorrect ? '#4caf50' : '#f44336'};
        animation: feedbackFadeIn 0.3s ease-in;
    `;
    feedback.textContent = isCorrect ? '✓ 答對了！' : '✗ 答錯了！';
    
    // 添加動畫樣式
    const style = document.createElement('style');
    style.textContent = `
        @keyframes feedbackFadeIn {
            from { opacity: 0; transform: translate(-50%, -50%) scale(0.8); }
            to { opacity: 1; transform: translate(-50%, -50%) scale(1); }
        }
    `;
    document.head.appendChild(style);
    
    document.body.appendChild(feedback);
    
    console.log('反饋元素已添加到頁面');
    
    setTimeout(() => {
        if (document.body.contains(feedback)) {
            document.body.removeChild(feedback);
            console.log('反饋元素已移除');
        }
    }, 1500);
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
    
    // 添加 resume-btn 類別，讓按鈕變為綠色
    const pauseBtn = document.getElementById('pause-btn');
    if (pauseBtn) {
        pauseBtn.classList.add('resume-btn');
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
    
    // 移除 resume-btn 類別，讓按鈕變回橘色
    const pauseBtn = document.getElementById('pause-btn');
    if (pauseBtn) {
        pauseBtn.classList.remove('resume-btn');
    }
}

async function saveGameResult(bonusScore, playTime) {
    try {
        console.log('儲存遊戲結果:', {
            member_id: memberId,
            difficulty: currentDifficulty,
            score: bonusScore,
            play_time: playTime
        });
        
        const response = await fetch('Vegetable-Cost.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                member_id: memberId,
                difficulty: currentDifficulty,
                score: bonusScore,
                play_time: playTime
            })
        });
        const result = await response.json();
        console.log('儲存結果回應:', result);
        if (!result.success) {
            console.error('儲存遊戲結果失敗:', result.message);
        } else {
            console.log('遊戲結果儲存成功');
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
    
    // 獎勵分數設定
    let rewardScore = 0;
    if (currentDifficulty === 'easy') rewardScore = 20;
    else if (currentDifficulty === 'normal') rewardScore = 50;
    else if (currentDifficulty === 'hard') rewardScore = 100;
    
    // 顯示 modal
    const modal = document.getElementById('game-over-modal');
    let title = '';
    let msg = '';
    if (score >= passScore) {
        title = '恭喜破關';
        msg = `難度：${currentDifficulty === 'easy' ? '簡單' : currentDifficulty === 'normal' ? '普通' : '困難'}<br>獲得分數：${rewardScore}`;
    } else {
        title = '遊戲失敗';
        msg = `難度：${currentDifficulty === 'easy' ? '簡單' : currentDifficulty === 'normal' ? '普通' : '困難'}<br>未在時間內達成分數`;
    }
    modal.querySelector('.gameover-title').innerHTML = title;
    modal.querySelector('.gameover-msg').innerHTML = msg;
    modal.classList.remove('hidden');
    const playTime = (currentDifficulty === 'easy' ? 80 : currentDifficulty === 'normal' ? 150 : 200) - timer;
    
    // 傳遞獎勵分數而不是實際遊戲分數
    let finalRewardScore = 0;
    if (score >= passScore) {
        if (currentDifficulty === 'easy') finalRewardScore = 20;
        else if (currentDifficulty === 'normal') finalRewardScore = 50;
        else if (currentDifficulty === 'hard') finalRewardScore = 100;
    }
    saveGameResult(finalRewardScore, playTime);
    
    // 立即更新主頁面分數
    if (window.updateScoreImmediately) {
        setTimeout(() => {
            window.updateScoreImmediately();
        }, 1000); // 1秒後更新，確保資料庫已保存
    }
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

// 設為全局可訪問
window.closeHelpModal = closeHelpModal;

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
    // 正確設定會員ID
    memberId = window.phpMemberId || (document.getElementById('member-id') ? parseInt(document.getElementById('member-id').value) : 8);
    console.log('會員ID設定為:', memberId);
    
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

    // 暫停按鈕事件監聽器
    const pauseBtn = document.getElementById('pause-btn');
    if (pauseBtn) {
        pauseBtn.addEventListener('click', function() {
            console.log('暫停按鈕被點擊，gamePaused:', gamePaused);
            if (gamePaused) {
                resumeGame();
                this.textContent = '暫停遊戲';
            } else {
                pauseGame();
                this.textContent = '繼續遊戲';
            }
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