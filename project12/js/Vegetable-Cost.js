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
let isAnswering = false; // 防止重複回答

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
        
        const firstThreeHTML = firstThree.map(item => `<div class="item-line"><img src="${item.name.includes('蛋') ? 'img/catch_egg.png' : `img/${item.image}`}" alt="${item.name}" style="width: ${item.name.includes('蛋') ? '32px' : '24px'}; height: ${item.name.includes('蛋') ? '32px' : '24px'}; vertical-align: middle; margin-right: 5px;">${item.name} $${item.price}</div>`).join('');
        const remainingHTML = remaining.map(item => `<div class="item-line"><img src="${item.name.includes('蛋') ? 'img/catch_egg.png' : `img/${item.image}`}" alt="${item.name}" style="width: ${item.name.includes('蛋') ? '32px' : '24px'}; height: ${item.name.includes('蛋') ? '32px' : '24px'}; vertical-align: middle; margin-right: 5px;">${item.name} $${item.price}</div>`).join('');
        
        // 確保兩欄都有內容，如果右邊沒有內容，從左邊移動一個過去
        let finalFirstThreeHTML = firstThreeHTML;
        let finalRemainingHTML = remainingHTML;
        
        if (remainingHTML === '' && firstThree.length > 2) {
            // 如果右邊沒有內容且左邊有超過2個，從左邊移動一個到右邊
            const lastItem = firstThree[firstThree.length - 1];
            finalFirstThreeHTML = firstThree.slice(0, 2).map(item => `<div class="item-line"><img src="${item.name.includes('蛋') ? 'img/catch_egg.png' : `img/${item.image}`}" alt="${item.name}" style="width: ${item.name.includes('蛋') ? '32px' : '24px'}; height: ${item.name.includes('蛋') ? '32px' : '24px'}; vertical-align: middle; margin-right: 5px;">${item.name} $${item.price}</div>`).join('');
            finalRemainingHTML = `<div class="item-line"><img src="${lastItem.name.includes('蛋') ? 'img/catch_egg.png' : `img/${lastItem.image}`}" alt="${lastItem.name}" style="width: ${lastItem.name.includes('蛋') ? '32px' : '24px'}; height: ${lastItem.name.includes('蛋') ? '32px' : '24px'}; vertical-align: middle; margin-right: 5px;">${lastItem.name} $${lastItem.price}</div>`;
            console.log('移動項目到右邊:', lastItem.name);
        }
        
        console.log('finalFirstThreeHTML:', finalFirstThreeHTML);
        console.log('finalRemainingHTML:', finalRemainingHTML);
        
        itemsHTML = `<div class="item-list-container"><div class="item-list-column">${finalFirstThreeHTML}</div><div class="item-list-column">${finalRemainingHTML}</div></div>`;
        
        let questionText = itemsHTML + `<br><br>阿嬤口袋剛好有<span class="highlight-text">$${finalBudget}元</span>，要買兩種蔬果回家，請幫她挑出<span class="highlight-text">總價剛好$${finalBudget}元</span>的組合。（買太便宜或太貴都不行喔！）`;
        
        let options = combos;
        
        // **修正選項的格式，並保持正確答案為純文字**
        options = options.map(opt => ({ text: opt.map(i => `<img src="${i.name.includes('蛋') ? 'img/catch_egg.png' : `img/${i.image}`}" alt="${i.name}" style="width: ${i.name.includes('蛋') ? '32px' : '20px'}; height: ${i.name.includes('蛋') ? '32px' : '20px'}; vertical-align: middle; margin-right: 3px;">${i.name}`).join('＋') })).sort(() => Math.random() - 0.5);
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
        let questionText = selectedItems.map(item => `<img src="${item.name.includes('蛋') ? 'img/catch_egg.png' : `img/${item.image}`}" alt="${item.name}" style="width: ${item.name.includes('蛋') ? '32px' : '24px'}; height: ${item.name.includes('蛋') ? '32px' : '24px'}; vertical-align: middle; margin-right: 5px;">${item.name} $${item.price}`).join('<br>');
        questionText += `<br><br>如果我要買<span style="color: #1976D2; font-weight: bold;">「${buyItems.map(i => `<img src="${i.name.includes('蛋') ? 'img/catch_egg.png' : `img/${i.image}`}" alt="${i.name}" style="width: ${i.name.includes('蛋') ? '32px' : '20px'}; height: ${i.name.includes('蛋') ? '32px' : '20px'}; vertical-align: middle; margin-right: 3px;">${i.name}`).join('＋')}」</span>，請問要多少錢？`;
        
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
        let questionText = `<img src="img/${veg.image}" alt="${veg.name}" style="width: 24px; height: 24px; vertical-align: middle; margin-right: 5px;">${veg.name} $${veg.price}/${veg.unit}。<br>`;
        questionText += `<img src="img/catch_egg.png" alt="${egg.name}" style="width: 32px; height: 32px; vertical-align: middle; margin-right: 5px;">${egg.name} $${egg.price}/${egg.unit}。<br><br>`;
        
        // **修改這裡，在阿嬤的題目文字中加上圖片**
        const buyVegCount = Math.floor(Math.random() * 3) + 1; // 1~3把
        const buyEggCount = Math.floor(Math.random() * 2) + 1; // 1~2盒

        questionText += `如果阿嬤要買<span style="color: #1976D2; font-weight: bold;">「${buyVegCount}${veg.unit}<img src="img/${veg.image}" alt="${veg.name}" style="width: 20px; height: 20px; vertical-align: middle; margin-right: 3px;">${veg.name}＋${buyEggCount}${egg.unit}<img src="img/catch_egg.png" alt="${egg.name}" style="width: 32px; height: 32px; vertical-align: middle; margin-right: 3px;">${egg.name}」</span>，請問總共要花多少錢？`;
        
        // 計算總價（考慮促銷優惠）
        let vegTotal = 0;
        let eggTotal = 0;
        
        // 計算豌豆的促銷價格（買4斤送1斤）
        if (buyVegCount >= 4) {
            const promoGroups = Math.floor(buyVegCount / 4);
            const remaining = buyVegCount % 4;
            vegTotal = (promoGroups * 4 * veg.price) + (remaining * veg.price);
        } else {
            vegTotal = buyVegCount * veg.price;
        }
        
        // 計算蛋的促銷價格（買3盒共100元）
        if (buyEggCount >= 3) {
            const promoGroups = Math.floor(buyEggCount / 3);
            const remaining = buyEggCount % 3;
            eggTotal = (promoGroups * 100) + (remaining * egg.price);
        } else {
            eggTotal = buyEggCount * egg.price;
        }
        
        const total = vegTotal + eggTotal;
        
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

    } else { // 普通模式
        // 隨機選擇兩種蔬果或三種蔬果的促銷題型
        const useTwoVegs = Math.random() < 0.5; // 50%機率使用兩種蔬果
        // 50%機率使用三種蔬果
        
        if (useTwoVegs) {
            // 兩種蔬果的促銷題型
            const veg1 = ingredients.vegetables[Math.floor(Math.random() * ingredients.vegetables.length)];
            let veg2;
            do {
                veg2 = ingredients.vegetables[Math.floor(Math.random() * ingredients.vegetables.length)];
            } while (veg2.name === veg1.name); // 確保兩種不同的蔬果
            
            const buyCount1 = [2, 3, 4, 5, 6][Math.floor(Math.random() * 5)]; // 第一種買2、3、4、5、6個
            const buyCount2 = [2, 3, 4, 5, 6][Math.floor(Math.random() * 5)]; // 第二種買2、3、4、5、6個
            const freeCount1 = [1, 2][Math.floor(Math.random() * 2)]; // 送1個或2個
            const freeCount2 = [1, 2][Math.floor(Math.random() * 2)]; // 送1個或2個
            const totalCount1 = buyCount1 + freeCount1; // 第一種實際總共買到幾個
            const totalCount2 = buyCount2 + freeCount2; // 第二種實際總共買到幾個
            const totalCount = totalCount1 + totalCount2; // 總共買到幾個
            
            let questionText = `<img src="img/${veg1.image}" alt="${veg1.name}" style="width: 24px; height: 24px; vertical-align: middle; margin-right: 5px;">${veg1.name} $${veg1.price}/${veg1.unit}<span style="color: red; font-weight: bold;">（買${buyCount1}${veg1.unit}送${freeCount1}${veg1.unit}）</span>。<br><img src="img/${veg2.image}" alt="${veg2.name}" style="width: 24px; height: 24px; vertical-align: middle; margin-right: 5px;">${veg2.name} $${veg2.price}/${veg2.unit}<span style="color: red; font-weight: bold;">（買${buyCount2}${veg2.unit}送${freeCount2}${veg2.unit}）</span>。<br><br>阿嬤買<span style="color: #1976D2; font-weight: bold;">「${buyCount1}${veg1.unit}<img src="img/${veg1.image}" alt="${veg1.name}" style="width: 20px; height: 20px; vertical-align: middle; margin-right: 3px;">${veg1.name}＋${buyCount2}${veg2.unit}<img src="img/${veg2.image}" alt="${veg2.name}" style="width: 20px; height: 20px; vertical-align: middle; margin-right: 3px;">${veg2.name}」</span>，實際總共買到多少個？`;
            
            let options = [totalCount];
            let offsetTries = 0;
            while (options.length < 4 && offsetTries < 20) {
                let fake = totalCount + (Math.floor(Math.random() * 3) - 1);
                if (fake > 0 && !options.includes(fake)) options.push(fake);
                offsetTries++;
            }
            while (options.length < 4) {
                let fake = totalCount + (Math.floor(Math.random() * 6) + 2) * (Math.random() < 0.5 ? 1 : -1);
                fake = Math.abs(fake);
                if (fake > 0 && !options.includes(fake)) options.push(fake);
            }
            return {
                question: questionText,
                options: options.map(v => ({ text: `${v}個` })).sort(() => Math.random() - 0.5),
                correctAnswer: `${totalCount}個`,
                items: selectedItems.length ? selectedItems : [veg1, veg2]
            };
        } else {
            // 三種蔬果的促銷題型 - 計算數量
            const veg1 = ingredients.vegetables[Math.floor(Math.random() * ingredients.vegetables.length)];
            let veg2;
            do {
                veg2 = ingredients.vegetables[Math.floor(Math.random() * ingredients.vegetables.length)];
            } while (veg2.name === veg1.name);
            let veg3;
            do {
                veg3 = ingredients.vegetables[Math.floor(Math.random() * ingredients.vegetables.length)];
            } while (veg3.name === veg1.name || veg3.name === veg2.name);
            
            const buyCount1 = [2, 3, 4, 5, 6][Math.floor(Math.random() * 5)];
            const buyCount2 = [2, 3, 4, 5, 6][Math.floor(Math.random() * 5)];
            const buyCount3 = [2, 3, 4, 5, 6][Math.floor(Math.random() * 5)];
            const freeCount1 = [1, 2][Math.floor(Math.random() * 2)]; // 送1個或2個
            const freeCount2 = [1, 2][Math.floor(Math.random() * 2)]; // 送1個或2個
            const freeCount3 = [1, 2][Math.floor(Math.random() * 2)]; // 送1個或2個
            const totalCount1 = buyCount1 + freeCount1;
            const totalCount2 = buyCount2 + freeCount2;
            const totalCount3 = buyCount3 + freeCount3;
            const totalCount = totalCount1 + totalCount2 + totalCount3;
            
            let questionText = `<img src="img/${veg1.image}" alt="${veg1.name}" style="width: 24px; height: 24px; vertical-align: middle; margin-right: 5px;">${veg1.name} $${veg1.price}/${veg1.unit}<span style="color: red; font-weight: bold;">（買${buyCount1}${veg1.unit}送${freeCount1}${veg1.unit}）</span>。<br><img src="img/${veg2.image}" alt="${veg2.name}" style="width: 24px; height: 24px; vertical-align: middle; margin-right: 5px;">${veg2.name} $${veg2.price}/${veg2.unit}<span style="color: red; font-weight: bold;">（買${buyCount2}${veg2.unit}送${freeCount2}${veg2.unit}）</span>。<br><img src="img/${veg3.image}" alt="${veg3.name}" style="width: 24px; height: 24px; vertical-align: middle; margin-right: 5px;">${veg3.name} $${veg3.price}/${veg3.unit}<span style="color: red; font-weight: bold;">（買${buyCount3}${veg3.unit}送${freeCount3}${veg3.unit}）</span>。<br><br>阿嬤買<span style="color: #1976D2; font-weight: bold;">「${buyCount1}${veg1.unit}<img src="img/${veg1.image}" alt="${veg1.name}" style="width: 20px; height: 20px; vertical-align: middle; margin-right: 3px;">${veg1.name}＋${buyCount2}${veg2.unit}<img src="img/${veg2.image}" alt="${veg2.name}" style="width: 20px; height: 20px; vertical-align: middle; margin-right: 3px;">${veg2.name}＋${buyCount3}${veg3.unit}<img src="img/${veg3.image}" alt="${veg3.name}" style="width: 20px; height: 20px; vertical-align: middle; margin-right: 3px;">${veg3.name}」</span>，實際總共買到多少個？`;
            
            let options = [totalCount];
        let offsetTries = 0;
        while (options.length < 4 && offsetTries < 20) {
                let fake = totalCount + (Math.floor(Math.random() * 3) - 1);
            if (fake > 0 && !options.includes(fake)) options.push(fake);
            offsetTries++;
        }
        while (options.length < 4) {
                let fake = totalCount + (Math.floor(Math.random() * 6) + 2) * (Math.random() < 0.5 ? 1 : -1);
            fake = Math.abs(fake);
            if (fake > 0 && !options.includes(fake)) options.push(fake);
        }
        return {
            question: questionText,
                options: options.map(v => ({ text: `${v}個` })).sort(() => Math.random() - 0.5),
                correctAnswer: `${totalCount}個`,
                items: selectedItems.length ? selectedItems : [veg1, veg2, veg3]
            };
        }
    }
}


// 生成困難題目
function generateHardQuestion() {
    console.log('開始生成困難題目');
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
    console.log('困難題目類型隨機數:', typeRand);
    if (typeRand < 0.4) {
        console.log('生成指定組合題型');
        // 指定組合
        const veg = ingredients.vegetables[Math.floor(Math.random() * ingredients.vegetables.length)];
        const meat = ingredients.meat[Math.floor(Math.random() * ingredients.meat.length)];
        const egg = ingredients.others.find(i => i.name.includes('蛋')) || ingredients.others[Math.floor(Math.random() * ingredients.others.length)];
        let questionText = `<img src="img/${veg.image}" alt="${veg.name}" style="width: 24px; height: 24px; vertical-align: middle; margin-right: 5px;">${veg.name} $${veg.price}/${veg.unit}<br><img src="img/${meat.image}" alt="${meat.name}" style="width: 24px; height: 24px; vertical-align: middle; margin-right: 5px;">${meat.name} $${meat.price}/斤<br><img src="img/catch_egg.png" alt="${egg.name}" style="width: 32px; height: 32px; vertical-align: middle; margin-right: 5px;">${egg.name} $${egg.price}/${egg.unit}<br><br>如果阿嬤要買<span style="color: #1976D2; font-weight: bold;">「1${veg.unit}<img src="img/${veg.image}" alt="${veg.name}" style="width: 20px; height: 20px; vertical-align: middle; margin-right: 3px;">${veg.name}＋1${egg.unit}<img src="img/catch_egg.png" alt="${egg.name}" style="width: 32px; height: 28px; vertical-align: middle; margin-right: 3px;">${egg.name}＋2斤<img src="img/${meat.image}" alt="${meat.name}" style="width: 20px; height: 20px; vertical-align: middle; margin-right: 3px;">${meat.name}」</span>，要多少錢？`;
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
        console.log('生成預算能買哪些題型');
        // 預算能買哪些 - 困難版本
        // 降低預算，增加挑戰性
        let budget = [40, 45, 50, 55, 60, 65][Math.floor(Math.random() * 6)];
        
        // 簡化的正確答案生成
        let validCombos = [];
        for (let i = 0; i < selectedItems.length && validCombos.length < 5; i++) {
            for (let j = i + 1; j < selectedItems.length && validCombos.length < 5; j++) {
                let sum = selectedItems[i].price + selectedItems[j].price;
                if (sum <= budget) {
                    validCombos.push([selectedItems[i], selectedItems[j]]);
                }
            }
        }
        
        // 如果沒有符合預算的組合，重新選擇物品
        if (validCombos.length === 0) {
            // 重新選擇更便宜的物品組合
            const cheapestItems = selectedItems.sort((a, b) => a.price - b.price).slice(0, 4);
            for (let i = 0; i < cheapestItems.length && validCombos.length < 5; i++) {
                for (let j = i + 1; j < cheapestItems.length && validCombos.length < 5; j++) {
                    let sum = cheapestItems[i].price + cheapestItems[j].price;
                    if (sum <= budget) {
                        validCombos.push([cheapestItems[i], cheapestItems[j]]);
                    }
                }
            }
            
            // 如果還是沒有，重新生成預算
            if (validCombos.length === 0) {
                // 找到最便宜的兩個物品組合
                const minSum = Math.min(...selectedItems.map(item => item.price)) * 2;
                const maxSum = selectedItems.sort((a, b) => a.price - b.price).slice(0, 2).reduce((sum, item) => sum + item.price, 0);
                
                // 重新生成預算，確保至少有一個組合符合
                const newBudget = Math.max(budget, maxSum + 5);
                
                // 重新生成所有符合新預算的組合
                for (let i = 0; i < selectedItems.length && validCombos.length < 5; i++) {
                    for (let j = i + 1; j < selectedItems.length && validCombos.length < 5; j++) {
                        let sum = selectedItems[i].price + selectedItems[j].price;
                        if (sum <= newBudget) {
                            validCombos.push([selectedItems[i], selectedItems[j]]);
                        }
                    }
                }
                
                // 更新問題中的預算顯示
                budget = newBudget;
            }
        }
        
        // 選擇一個正確答案
        const answerCombo = validCombos[Math.floor(Math.random() * validCombos.length)];
        console.log('第二種題型選擇的正確答案:', answerCombo.map(i => i.name).join('＋'), '價格:', answerCombo[0].price + answerCombo[1].price);
        
        // 生成錯誤選項（確保只有一個正確答案）
        let options = [answerCombo];
        let usedCombos = new Set();
        usedCombos.add(answerCombo.map(i => i.name).sort().join('+'));
        
        // 生成3個錯誤選項（價格超過預算的組合）
        let attempts = 0;
        while (options.length < 4 && attempts < 500) {
            let item1 = selectedItems[Math.floor(Math.random() * selectedItems.length)];
            let item2 = selectedItems[Math.floor(Math.random() * selectedItems.length)];
            if (item1 !== item2) {
                let sum = item1.price + item2.price;
                let comboKey = [item1.name, item2.name].sort().join('+');
                // 確保選項價格明顯超過預算，這樣就只有一個正確答案
                if (sum > budget + 20 && !usedCombos.has(comboKey)) {
                    options.push([item1, item2]);
                    usedCombos.add(comboKey);
                }
            }
            attempts++;
        }
        
        // 如果還是不夠4個選項，生成一些更貴的組合
        let fallbackTries = 0;
        while (options.length < 4 && fallbackTries < 200) {
            let item1 = selectedItems[Math.floor(Math.random() * selectedItems.length)];
            let item2 = selectedItems[Math.floor(Math.random() * selectedItems.length)];
            if (item1 !== item2) {
                let sum = item1.price + item2.price;
                let comboKey = [item1.name, item2.name].sort().join('+');
                // 確保選項價格明顯超過預算
                if (sum > budget + 30 && !usedCombos.has(comboKey)) {
                    options.push([item1, item2]);
                    usedCombos.add(comboKey);
                }
            }
            fallbackTries++;
        }
        
        // 如果還是不夠4個選項，強制添加一些錯誤組合
        if (options.length < 4) {
            // 找到所有價格超過預算的組合
            let expensiveCombos = [];
            for (let i = 0; i < selectedItems.length; i++) {
                for (let j = i + 1; j < selectedItems.length; j++) {
                    let sum = selectedItems[i].price + selectedItems[j].price;
                    let comboKey = [selectedItems[i].name, selectedItems[j].name].sort().join('+');
                    if (sum > budget + 15 && !usedCombos.has(comboKey)) {
                        expensiveCombos.push([selectedItems[i], selectedItems[j]]);
                    }
                }
            }
            
            // 隨機選擇一些貴的組合
            while (options.length < 4 && expensiveCombos.length > 0) {
                let randomIndex = Math.floor(Math.random() * expensiveCombos.length);
                let combo = expensiveCombos.splice(randomIndex, 1)[0];
                options.push(combo);
                usedCombos.add(combo.map(i => i.name).sort().join('+'));
            }
            
            // 如果還是不夠，添加任何未使用的組合（但標記為錯誤）
            if (options.length < 4) {
                for (let i = 0; i < selectedItems.length && options.length < 4; i++) {
                    for (let j = i + 1; j < selectedItems.length && options.length < 4; j++) {
                        let sum = selectedItems[i].price + selectedItems[j].price;
                        let comboKey = [selectedItems[i].name, selectedItems[j].name].sort().join('+');
                        // 只添加價格超過預算的組合作為錯誤選項
                        if (sum > budget && !usedCombos.has(comboKey)) {
                            options.push([selectedItems[i], selectedItems[j]]);
                            usedCombos.add(comboKey);
                        }
                    }
                }
            }
        }
        
        let questionText = selectedItems.map(item => `<img src="${item.name.includes('蛋') ? 'img/catch_egg.png' : `img/${item.image}`}" alt="${item.name}" style="width: ${item.name.includes('蛋') ? '32px' : '24px'}; height: ${item.name.includes('蛋') ? '32px' : '24px'}; vertical-align: middle; margin-right: 5px;">${item.name} $${item.price}`).join('<br>');
        questionText += `<br><br>阿嬤只帶了<span style="color: red; font-weight: bold;">$${budget}</span>，足夠可以買哪些東西回家？`;
        
        options = options.map(opt => ({ 
            text: opt.map(i => `<img src="${i.name.includes('蛋') ? 'img/catch_egg.png' : `img/${i.image}`}" alt="${i.name}" style="width: ${i.name.includes('蛋') ? '32px' : '20px'}; height: ${i.name.includes('蛋') ? '32px' : '20px'}; vertical-align: middle; margin-right: 3px;">${i.name}`).join('＋'),
            isCorrect: opt === answerCombo
        })).sort(() => Math.random() - 0.5);
        
        console.log('第二種題型生成的選項:', options.map(opt => ({
            text: opt.text.replace(/<[^>]*>/g, ''),
            isCorrect: opt.isCorrect,
            price: opt.text.includes('＋') ? 
                opt.text.split('＋').map(item => {
                    const itemName = item.replace(/<[^>]*>/g, '').trim();
                    const foundItem = selectedItems.find(i => i.name === itemName);
                    return foundItem ? foundItem.price : 0;
                }).reduce((a, b) => a + b, 0) : 0
        })));
        
        return {
            question: questionText,
            options: options,
            correctAnswer: answerCombo.map(i => i.name).join('＋'),
            items: selectedItems
        };
    } else {
        console.log('生成只能買兩樣題型');
        // 只能買兩樣/湊滿不超過金額 - 困難版本
        // 降低預算，增加挑戰性
        let limit = [40, 45, 50, 55, 60, 65][Math.floor(Math.random() * 6)];
        
        // 先選擇一些較貴的物品，確保有挑戰性
        let expensiveItems = selectedItems.filter(item => item.price > 30);
        let cheapItems = selectedItems.filter(item => item.price <= 30);
        
        // 如果沒有足夠的貴物品，就從所有物品中選擇
        if (expensiveItems.length < 3) {
            expensiveItems = selectedItems.slice(0, Math.ceil(selectedItems.length / 2));
            cheapItems = selectedItems.slice(Math.ceil(selectedItems.length / 2));
        }
        
        // 確保有正確答案的組合
        let validCombos = [];
        for (let i = 0; i < selectedItems.length; i++) {
            for (let j = i + 1; j < selectedItems.length; j++) {
                let sum = selectedItems[i].price + selectedItems[j].price;
                if (sum <= limit) {
                    validCombos.push([selectedItems[i], selectedItems[j]]);
                }
            }
        }
        
        // 如果沒有符合預算的組合，重新生成預算
        if (validCombos.length === 0) {
            // 找到最便宜的兩個物品組合
            const maxSum = selectedItems.sort((a, b) => a.price - b.price).slice(0, 2).reduce((sum, item) => sum + item.price, 0);
            
            // 重新生成預算，確保至少有一個組合符合
            const newLimit = Math.max(limit, maxSum + 5);
            
            // 重新生成所有符合新預算的組合
            for (let i = 0; i < selectedItems.length; i++) {
                for (let j = i + 1; j < selectedItems.length; j++) {
                    let sum = selectedItems[i].price + selectedItems[j].price;
                    if (sum <= newLimit) {
                        validCombos.push([selectedItems[i], selectedItems[j]]);
                    }
                }
            }
            
            // 更新問題中的預算顯示
            limit = newLimit;
        }
        
        // 選擇一個正確答案
        const answerCombo = validCombos[Math.floor(Math.random() * validCombos.length)];
        console.log('選擇的正確答案:', answerCombo.map(i => i.name).join('＋'), '價格:', answerCombo[0].price + answerCombo[1].price);
        
        // 生成錯誤選項（確保只有一個正確答案）
        let options = [answerCombo];
        let usedCombos = new Set();
        usedCombos.add(answerCombo.map(i => i.name).sort().join('+'));
        
        // 生成3個錯誤選項（價格超過預算的組合）
        let attempts = 0;
        while (options.length < 4 && attempts < 500) {
            let item1 = selectedItems[Math.floor(Math.random() * selectedItems.length)];
            let item2 = selectedItems[Math.floor(Math.random() * selectedItems.length)];
            if (item1 !== item2) {
                let sum = item1.price + item2.price;
                let comboKey = [item1.name, item2.name].sort().join('+');
                // 確保選項價格明顯超過預算，這樣就只有一個正確答案
                if (sum > limit + 20 && !usedCombos.has(comboKey)) {
                    options.push([item1, item2]);
                    usedCombos.add(comboKey);
                }
            }
            attempts++;
        }
        
        // 如果還是不夠4個選項，生成一些更貴的組合
        let fallbackTries = 0;
        while (options.length < 4 && fallbackTries < 200) {
            let item1 = selectedItems[Math.floor(Math.random() * selectedItems.length)];
            let item2 = selectedItems[Math.floor(Math.random() * selectedItems.length)];
            if (item1 !== item2) {
                let sum = item1.price + item2.price;
                let comboKey = [item1.name, item2.name].sort().join('+');
                // 確保選項價格明顯超過預算
                if (sum > limit + 30 && !usedCombos.has(comboKey)) {
                    options.push([item1, item2]);
                    usedCombos.add(comboKey);
                }
            }
            fallbackTries++;
        }
        
        // 如果還是不夠4個選項，強制添加一些錯誤組合
        if (options.length < 4) {
            // 找到所有價格超過預算的組合
            let expensiveCombos = [];
            for (let i = 0; i < selectedItems.length; i++) {
                for (let j = i + 1; j < selectedItems.length; j++) {
                    let sum = selectedItems[i].price + selectedItems[j].price;
                    let comboKey = [selectedItems[i].name, selectedItems[j].name].sort().join('+');
                    if (sum > limit + 15 && !usedCombos.has(comboKey)) {
                        expensiveCombos.push([selectedItems[i], selectedItems[j]]);
                    }
                }
            }
            
            // 隨機選擇一些貴的組合
            while (options.length < 4 && expensiveCombos.length > 0) {
                let randomIndex = Math.floor(Math.random() * expensiveCombos.length);
                let combo = expensiveCombos.splice(randomIndex, 1)[0];
                options.push(combo);
                usedCombos.add(combo.map(i => i.name).sort().join('+'));
            }
            
            // 如果還是不夠，添加任何未使用的組合（但標記為錯誤）
            if (options.length < 4) {
                for (let i = 0; i < selectedItems.length && options.length < 4; i++) {
                    for (let j = i + 1; j < selectedItems.length && options.length < 4; j++) {
                        let sum = selectedItems[i].price + selectedItems[j].price;
                        let comboKey = [selectedItems[i].name, selectedItems[j].name].sort().join('+');
                        // 只添加價格超過預算的組合作為錯誤選項
                        if (sum > limit && !usedCombos.has(comboKey)) {
                            options.push([selectedItems[i], selectedItems[j]]);
                            usedCombos.add(comboKey);
                        }
                    }
                }
            }
        }
        
        let questionText = selectedItems.map(item => `<img src="${item.name.includes('蛋') ? 'img/catch_egg.png' : `img/${item.image}`}" alt="${item.name}" style="width: ${item.name.includes('蛋') ? '32px' : '24px'}; height: ${item.name.includes('蛋') ? '32px' : '24px'}; vertical-align: middle; margin-right: 5px;">${item.name} $${item.price}`).join('<br>');
        questionText += `<br><br>阿嬤只帶了<span style="color: red; font-weight: bold;">$${limit}</span>，足夠只能買兩樣，湊滿<span style="color: red; font-weight: bold;">不超過這個金額</span>，可以買哪些？`;
        
        options = options.map(opt => ({ 
            text: opt.map(i => `<img src="${i.name.includes('蛋') ? 'img/catch_egg.png' : `img/${i.image}`}" alt="${i.name}" style="width: ${i.name.includes('蛋') ? '32px' : '20px'}; height: ${i.name.includes('蛋') ? '32px' : '20px'}; vertical-align: middle; margin-right: 3px;">${i.name}`).join('＋'),
            isCorrect: opt === answerCombo
        })).sort(() => Math.random() - 0.5);
        
        console.log('第三種題型生成的選項:', options.map(opt => ({
            text: opt.text.replace(/<[^>]*>/g, ''),
            isCorrect: opt.isCorrect,
            price: opt.text.includes('＋') ? 
                opt.text.split('＋').map(item => {
                    const itemName = item.replace(/<[^>]*>/g, '').trim();
                    const foundItem = selectedItems.find(i => i.name === itemName);
                    return foundItem ? foundItem.price : 0;
                }).reduce((a, b) => a + b, 0) : 0
        })));
        
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
    console.log('loadQuestion 被調用，gamePaused:', gamePaused);
    if (gamePaused) return;
    
    // 重置回答狀態
    isAnswering = false;
    let question;
    console.log('開始生成題目，難度:', currentDifficulty);
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
    console.log('題目生成完成');
    const questionElement = document.getElementById('question');
    const optionsContainer = document.getElementById('options-container');
    let lines = [];
    if (question.question) {
        lines.push(question.question);
    }
    questionElement.innerHTML = lines.join('<br>');
    optionsContainer.innerHTML = '';
    question.options.forEach(option => {
        const button = document.createElement('button');
        
        // 簡化的選項處理
        if (option.text.includes('<img')) {
            button.innerHTML = option.text;
        } else {
            button.textContent = option.text;
        }
        
        button.onclick = () => {
            // 防止重複點擊
            if (button.disabled) return;
            
            // 立即禁用所有選項按鈕，防止快速點擊
            const allButtons = optionsContainer.querySelectorAll('button');
            allButtons.forEach(btn => {
                btn.disabled = true;
                btn.style.pointerEvents = 'none'; // 完全禁用點擊
            });
            
            // 使用 setTimeout 確保按鈕狀態更新後再處理答案
            setTimeout(() => {
                checkAnswer(option.text, question.correctAnswer);
            }, 10);
        };
        optionsContainer.appendChild(button);
    });
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
    if (isAnswering) return; // 防止重複回答
    
    isAnswering = true; // 設置回答狀態

    // 簡化的清理函式
    const cleanAnswer = (answer) => {
        // 移除所有HTML標籤
    const textOnly = answer.replace(/<[^>]*>/g, '');
        // 只保留文字、數字、空白和特殊符號（如＋）
        return textOnly.replace(/[^\w\s＋]/g, '');
    };
    
    // 專門處理組合答案的清理函式
    const cleanComboAnswer = (answer) => {
        // 移除所有HTML標籤
        const textOnly = answer.replace(/<[^>]*>/g, '');
        // 移除表情符號和其他特殊字符，只保留中文和＋
        return textOnly.replace(/[^\u4e00-\u9fa5＋]/g, '');
};

    const cleanSelectedAnswer = cleanAnswer(selectedAnswer);
    const cleanCorrectAnswer = correctAnswer.replace("$",'');
    
    console.log('清理後的選取答案:', cleanSelectedAnswer, '清理後的正確答案:', cleanCorrectAnswer);
    console.log('原始選取答案:', selectedAnswer, '原始正確答案:', correctAnswer);

    // 對於不同題型，需要特殊處理
    let isCorrect;
    if (correctAnswer.includes('條') || correctAnswer.includes('顆') || correctAnswer.includes('把') || correctAnswer.includes('斤') || correctAnswer.includes('盒') || correctAnswer.includes('個')) {
        // 數量題型：比較數字部分
        const selectedNum = parseInt(cleanSelectedAnswer);
        const correctNum = parseInt(cleanCorrectAnswer);
        isCorrect = selectedNum === correctNum;
    } else if (correctAnswer.startsWith('$')) {
        // 金額題型：比較數字部分
        const selectedNum = parseInt(cleanSelectedAnswer);
        const correctNum = parseInt(cleanCorrectAnswer.replace('$', ''));
        isCorrect = selectedNum === correctNum;
    } else if (correctAnswer.includes('＋')) {
        // 組合題型：使用專門的清理函式
        const selectedClean = cleanComboAnswer(selectedAnswer).trim();
        const correctClean = cleanComboAnswer(correctAnswer).trim();
        console.log('組合題型比較:', selectedClean, 'vs', correctClean);
        isCorrect = selectedClean === correctClean;
    } else {
        // 其他題型：直接比較清理後的字符串
        isCorrect = cleanSelectedAnswer === cleanCorrectAnswer;
    }
    
    if (isCorrect) {
        score += 3;
        updateScore();
        showAnswerFeedback(true);
    } else {
        showAnswerFeedback(false);
    }
    
    // 延遲載入下一題
    console.log('準備載入下一題，延遲時間:', isCorrect ? 1500 : 1200);
    setTimeout(() => {
        console.log('開始載入下一題');
        loadQuestion();
    }, isCorrect ? 1500 : 1200);
}

// 顯示答案反饋
function showAnswerFeedback(isCorrect) {
    console.log('顯示答案反饋:', isCorrect ? '答對了' : '答錯了');
    
    // 移除舊的反饋元素
    const oldFeedback = document.getElementById('answer-feedback');
    if (oldFeedback) {
        oldFeedback.remove();
    }
    
    const feedback = document.createElement('div');
    feedback.id = 'answer-feedback';
    feedback.style.cssText = `
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        font-size: 2rem;
        font-weight: bold;
        color: ${isCorrect ? '#4caf50' : '#f44336'};
        background: white;
        padding: 15px 25px;
        border-radius: 8px;
        z-index: 9999;
        border: 2px solid ${isCorrect ? '#4caf50' : '#f44336'};
    `;
    feedback.textContent = isCorrect ? '✓ 答對了！' : '✗ 答錯了！';
    
    document.body.appendChild(feedback);
    
    console.log('反饋元素已添加到頁面');
    
    // 簡化的移除邏輯
    setTimeout(() => {
        if (feedback && document.body.contains(feedback)) {
            feedback.remove();
            console.log('反饋元素已移除');
        }
    }, 1000);
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
    if (currentDifficulty === 'easy') passScore = 20;
    else if (currentDifficulty === 'normal') passScore = 30;
    else if (currentDifficulty === 'hard') passScore = 50;
    
    // 獎勵分數設定
    let rewardScore = 0;
    if (currentDifficulty === 'easy') rewardScore = 20;
    else if (currentDifficulty === 'normal') rewardScore = 50;
    else if (currentDifficulty === 'hard') rewardScore = 100;
    
    // 顯示 modal
    const modal = document.getElementById('game-over-modal');
    const playTime = (currentDifficulty === 'easy' ? 80 : currentDifficulty === 'normal' ? 150 : 200) - timer;
    
    let title = '';
    const difficultyName = currentDifficulty === 'easy' ? '簡單' : currentDifficulty === 'normal' ? '普通' : '困難';
    
    if (score >= passScore) {
        title = '🎉恭喜破關';
    } else {
        title = '⏰遊戲失敗';
        rewardScore = 0;
    }
    
    // 設置標題
    modal.querySelector('.gameover-title').innerHTML = title;
    
    // 設置四行信息
    document.getElementById('vegetable-gameover-difficulty').textContent = difficultyName;
    document.getElementById('vegetable-gameover-earned-score').textContent = score;
    document.getElementById('vegetable-gameover-time').textContent = playTime + '秒';
    document.getElementById('vegetable-gameover-bonus').textContent = score >= passScore ? '+' + rewardScore : '0';
    
    modal.classList.remove('hidden');
    
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
    
    // 初始化視頻播放邏輯
    initVegetableVideoPlayback();
    
    // 強制綁定下一步按鈕事件
    bindNextStepButton();
    
    // 延遲再次綁定，確保DOM完全加載
    setTimeout(() => {
        bindNextStepButton();
    }, 200);
}

// 綁定下一步按鈕事件
function bindNextStepButton() {
    const nextStepButton = document.getElementById('vegetable-next-step-button');
    if (nextStepButton) {
        // 清除之前的所有事件監聽器
        const newButton = nextStepButton.cloneNode(true);
        nextStepButton.parentNode.replaceChild(newButton, nextStepButton);
        
        // 重新綁定事件
        newButton.onclick = goToVegetableNextStep;
        newButton.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('換算菜錢下一步按鈕被點擊了！');
            goToVegetableNextStep();
        });
        
        console.log('換算菜錢下一步按鈕事件已重新綁定');
    } else {
        console.error('找不到換算菜錢下一步按鈕元素');
    }
}

function closeHelpModal() {
    document.getElementById('help-modal').classList.add('hidden');
}

// 初始化換算菜錢視頻連續播放
function initVegetableVideoPlayback() {
    const video = document.getElementById('vegetable-current-video');
    const instructionText = document.getElementById('vegetable-instruction-text');
    const stepIndicator = document.getElementById('vegetable-step-indicator');
    const nextStepBtn = document.getElementById('vegetable-next-step-btn');
    const prevStepBtn = document.getElementById('vegetable-prev-step-btn');
    
    // 清除之前的事件監聽器
    video.removeEventListener('ended', handleVegetableVideoEnd);
    
    // 設置第一個視頻
    video.src = 'gd/vegetable1.mp4';
    instructionText.textContent = '計算阿嬤買菜的總金額';
    stepIndicator.textContent = '步驟 1/2';
    
    // 設置當前視頻標記
    video.setAttribute('data-current-video', 'vegetable1');
    
    // 顯示下一步按鈕，隱藏上一步按鈕
    nextStepBtn.style.display = 'block';
    prevStepBtn.style.display = 'none';
    
    // 添加視頻結束事件監聽器
    video.addEventListener('ended', handleVegetableVideoEnd);
    
    // 強制加載視頻
    video.load();
    
    // 添加下一步按鈕點擊事件
    const nextStepButton = document.getElementById('vegetable-next-step-button');
    if (nextStepButton) {
        nextStepButton.onclick = goToVegetableNextStep;
        console.log('換算菜錢下一步按鈕事件已綁定到 initVegetableVideoPlayback');
        
        // 測試按鈕是否真的可以點擊
        nextStepButton.addEventListener('click', function() {
            console.log('換算菜錢下一步按鈕被點擊了！');
            goToVegetableNextStep();
        });
    } else {
        console.error('找不到換算菜錢下一步按鈕元素');
    }
}

// 處理換算菜錢視頻結束事件
function handleVegetableVideoEnd() {
    const video = document.getElementById('vegetable-current-video');
    const currentVideo = video.getAttribute('data-current-video');
    
    console.log('換算菜錢視頻結束事件觸發，當前視頻：', currentVideo);
    
    if (currentVideo === 'vegetable1') {
        // 第一個視頻播完，等待用戶點擊下一步
        console.log('第一個換算菜錢視頻播完，等待用戶點擊下一步');
    } else if (currentVideo === 'vegetable2') {
        // 第二個視頻播完，自動回到第一個
        console.log('第二個換算菜錢視頻播完，自動回到第一個');
        goToVegetableFirstStep();
    }
}

// 前往換算菜錢下一步
function goToVegetableNextStep() {
    const video = document.getElementById('vegetable-current-video');
    const instructionText = document.getElementById('vegetable-instruction-text');
    const stepIndicator = document.getElementById('vegetable-step-indicator');
    const nextStepBtn = document.getElementById('vegetable-next-step-btn');
    const prevStepBtn = document.getElementById('vegetable-prev-step-btn');
    
    // 切換到第二個視頻
    video.src = 'gd/vegetable2.mp4';
    video.setAttribute('data-current-video', 'vegetable2');
    instructionText.innerHTML = '每答對一題得3分<br>時間內達到目標分數就過關！';
    stepIndicator.textContent = '步驟 2/2';
    
    // 隱藏下一步按鈕，顯示上一步按鈕
    nextStepBtn.style.display = 'none';
    prevStepBtn.style.display = 'block';
    
    // 加載並播放視頻
    video.load();
    video.play();
}

// 回到換算菜錢第一步
function goToVegetableFirstStep() {
    const video = document.getElementById('vegetable-current-video');
    const instructionText = document.getElementById('vegetable-instruction-text');
    const stepIndicator = document.getElementById('vegetable-step-indicator');
    const nextStepBtn = document.getElementById('vegetable-next-step-btn');
    const prevStepBtn = document.getElementById('vegetable-prev-step-btn');
    
    // 切換到第一個視頻
    video.src = 'gd/vegetable1.mp4';
    video.setAttribute('data-current-video', 'vegetable1');
    instructionText.textContent = '計算阿嬤買菜的總金額';
    stepIndicator.textContent = '步驟 1/2';
    
    // 顯示下一步按鈕，隱藏上一步按鈕
    nextStepBtn.style.display = 'block';
    prevStepBtn.style.display = 'none';
    
    // 加載並播放視頻
    video.load();
    video.play();
}

// 回到換算菜錢上一步
function goToVegetablePrevStep() {
    const video = document.getElementById('vegetable-current-video');
    const instructionText = document.getElementById('vegetable-instruction-text');
    const stepIndicator = document.getElementById('vegetable-step-indicator');
    const nextStepBtn = document.getElementById('vegetable-next-step-btn');
    const prevStepBtn = document.getElementById('vegetable-prev-step-btn');
    
    // 切換到第一個視頻
    video.src = 'gd/vegetable1.mp4';
    video.setAttribute('data-current-video', 'vegetable1');
    instructionText.textContent = '計算阿嬤買菜的總金額';
    stepIndicator.textContent = '步驟 1/2';
    
    // 顯示下一步按鈕，隱藏上一步按鈕
    nextStepBtn.style.display = 'block';
    prevStepBtn.style.display = 'none';
    
    // 加載並播放視頻
    video.load();
    video.play();
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