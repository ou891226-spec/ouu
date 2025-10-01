// 遊戲狀態
let gameState = {
    items: [],
    leftSide: [],
    rightSide: [],
    boatSide: "left",
    selectedItems: [],
    mode: "easy",
    stepCount: 0,
    score: 0,
    boatCapacity: 1,
    gameOver: false,
    paused: false,
    weather: null,
    weatherInfo: "",
    gameHistory: [],
    bestSteps: {
        easy: Infinity,
        normal: Infinity,
        hard: Infinity
    },
    startTime: null,
    gameTime: 0,
    gameTimer: null
};

// DOM 元素
const leftItemsEl = document.getElementById("left-items");
const rightItemsEl = document.getElementById("right-items");
const boatEl = document.getElementById("boat");
const boatItemsEl = document.getElementById("boat-items");
const messageEl = document.getElementById("message");
const stepCountEl = document.getElementById("step-count");
const boatCapacityEl = document.getElementById("boat-capacity");
const leftFarmerEl = document.getElementById("left-farmer");
const rightFarmerEl = document.getElementById("right-farmer");
const weatherInfoEl = document.getElementById("weather-info");

// 畫面元素
const startScreen = document.getElementById("start-screen");
const difficultyScreen = document.getElementById("difficulty-screen");
const rulesScreen = document.getElementById("rules-screen");
const gameScreen = document.getElementById("game-screen");

// 返回主頁按鈕處理
function handleBackButton() {
    // 智能返回：回到上一頁，如果沒有上一頁則回到首頁
    if (document.referrer && document.referrer !== window.location.href) {
        history.back();
    } else {
        window.location.href = 'index.php';
    }
}

// 音效函數
function playSound(type) {
    // 創建音效（使用 Web Audio API）
    const audioContext = new (window.AudioContext || window.webkitAudioContext)();
    const oscillator = audioContext.createOscillator();
    const gainNode = audioContext.createGain();
    
    oscillator.connect(gainNode);
    gainNode.connect(audioContext.destination);
    
    switch(type) {
        case 'select':
            oscillator.frequency.setValueAtTime(800, audioContext.currentTime);
            oscillator.frequency.setValueAtTime(600, audioContext.currentTime + 0.1);
            break;
        case 'move':
            oscillator.frequency.setValueAtTime(400, audioContext.currentTime);
            oscillator.frequency.setValueAtTime(600, audioContext.currentTime + 0.2);
            break;
        case 'win':
            oscillator.frequency.setValueAtTime(523, audioContext.currentTime);
            oscillator.frequency.setValueAtTime(659, audioContext.currentTime + 0.2);
            oscillator.frequency.setValueAtTime(784, audioContext.currentTime + 0.4);
            break;
        case 'lose':
            oscillator.frequency.setValueAtTime(200, audioContext.currentTime);
            oscillator.frequency.setValueAtTime(150, audioContext.currentTime + 0.3);
            break;
    }
    
    gainNode.gain.setValueAtTime(0.1, audioContext.currentTime);
    gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.5);
    
    oscillator.start(audioContext.currentTime);
    oscillator.stop(audioContext.currentTime + 0.5);
}

// 畫面切換函數
function showScreen(screenId) {
    // 隱藏所有畫面
    document.querySelectorAll('.screen').forEach(screen => {
        screen.classList.remove('active');
    });
    
    // 顯示指定畫面
    document.getElementById(screenId).classList.add('active');
}

// 事件監聽器
document.addEventListener('DOMContentLoaded', function() {
    // 開始畫面按鈕
    document.getElementById("start-game-btn").addEventListener("click", () => {
        showScreen("difficulty-screen");
    });
    
    document.getElementById("help-btn").addEventListener("click", () => {
        showScreen("rules-screen");
    });
    
    
    // 難度選擇按鈕
    document.querySelectorAll('.difficulty-option').forEach(option => {
        option.addEventListener("click", (e) => {
            const difficulty = option.getAttribute('data-difficulty');
            selectDifficulty(difficulty);
        });
    });
    
    document.getElementById("back-to-start").addEventListener("click", () => {
        handleBackButton();
    });
    
    document.getElementById("help-from-difficulty").addEventListener("click", () => {
        showScreen("rules-screen");
    });
    
    // 規則畫面按鈕
    document.getElementById("go-to-difficulty").addEventListener("click", () => {
        showScreen("difficulty-screen");
    });
    
    // 遊戲畫面按鈕
    document.getElementById("back-to-difficulty").addEventListener("click", () => {
        showScreen("difficulty-screen");
    });
    
    document.getElementById("show-hint-btn").addEventListener("click", () => {
        nextHint();
    });
    
    document.getElementById("resetBtn").addEventListener("click", initGame);
    
    document.getElementById("pauseBtn").addEventListener("click", () => {
        // 暫停遊戲功能
        if (gameState.gameOver) return;
        
        if (gameState.paused) {
            gameState.paused = false;
            document.getElementById("pauseBtn").textContent = "暫停遊戲";
            showMessage("遊戲繼續");
            // 重新開始計時
            if (gameState.startTime) {
                gameState.startTime = Date.now() - (gameState.gameTime * 1000);
            }
        } else {
            gameState.paused = true;
            document.getElementById("pauseBtn").textContent = "繼續遊戲";
            showMessage("遊戲已暫停");
        }
    });
    
    document.getElementById("endGameBtn").addEventListener("click", () => {
        // 結束遊戲功能 - 直接結束，不顯示確認視窗
        // 停止計時器
        stopGameTimer();
        // 保存遊戲結果（即使沒有完成）
        saveGameResultOnExit();
        showScreen("difficulty-screen");
    });
    
    boatEl.addEventListener("click", moveBoat);
    
    // 彈出對話框按鈕事件
    document.getElementById("play-again-btn").addEventListener("click", () => {
        hideModal("game-fail-modal");
        stopGameTimer();
        initGame();
    });
    
    document.getElementById("return-home-btn").addEventListener("click", () => {
        hideModal("game-fail-modal");
        stopGameTimer();
        handleBackButton();
    });
    
    document.getElementById("play-again-success-btn").addEventListener("click", () => {
        hideModal("game-success-modal");
        stopGameTimer();
        initGame();
    });
    
    document.getElementById("return-home-success-btn").addEventListener("click", () => {
        hideModal("game-success-modal");
        stopGameTimer();
        handleBackButton();
    });
});

// 選擇難度
function selectDifficulty(difficulty) {
    gameState.mode = difficulty;
    
    // 移除所有選中狀態
    document.querySelectorAll('.difficulty-option').forEach(option => {
        option.classList.remove('selected');
    });
    
    // 添加選中狀態到當前選項
    const selectedOption = document.querySelector(`[data-difficulty="${difficulty}"]`);
    if (selectedOption) {
        selectedOption.classList.add('selected');
    }
    
    // 更新難度顯示
    const difficultyNames = {
        "easy": "簡單模式",
        "normal": "普通模式", 
        "hard": "困難模式"
    };
    
    document.getElementById("current-difficulty").textContent = difficultyNames[difficulty];
    
    // 切換到遊戲畫面
    showScreen("game-screen");
    
    // 初始化遊戲
    initGame();
}

// 初始化遊戲
function initGame() {
    gameState = {
        items: [],
        leftSide: [],
        rightSide: [],
        boatSide: "left",
        selectedItems: [],
        mode: gameState.mode,
        stepCount: 0,
        score: 0,
        boatCapacity: 1,
        gameOver: false,
        paused: false,
        weather: null,
        weatherInfo: "",
        gameHistory: [],
        bestSteps: gameState.bestSteps,
        startTime: null,
        gameTime: 0,
        gameTimer: null
    };
    
    // 重置暫停按鈕文字
    document.getElementById("pauseBtn").textContent = "暫停遊戲";
    
    // 開始遊戲計時
    startGameTimer();

    // 設定模式
    switch (gameState.mode) {
        case "easy":
            gameState.items = ["狼", "羊", "菜"];
            gameState.boatCapacity = 1;
            break;
        case "normal":
            gameState.items = ["狼", "羊", "菜", "狗"];
            gameState.boatCapacity = 1;
            break;
        case "hard":
            gameState.items = ["狼", "羊", "菜", "狗", "狐狸"];
            gameState.boatCapacity = 2;
            break;
    }

    gameState.leftSide = [...gameState.items];
    gameState.rightSide = [];

    updateDisplay();
    clearMessage();
    showHint();
}

// 提示系統
let currentHintIndex = 0;
const hintSets = {
    easy: [
        "💡 基本策略：羊是最關鍵的，要優先處理",
        "💡 記住：狼和羊不能單獨在一起，羊和菜也不能單獨在一起",
        "💡 關鍵概念：羊是唯一會被狼吃和吃菜的角色，所以必須小心處理",
        "💡 安全原則：永遠不要讓狼和羊單獨在一起，也不要讓羊和菜單獨在一起",
        "💡 成功秘訣：每次移動前都要檢查兩岸是否安全，避免違規",
        "💡 思考方向：先運送哪個物品最安全？",
        "💡 提示：先運羊，再運狼，然後把羊帶回來，運菜，最後運羊",
        "💡 詳細步驟：1.運羊到右岸 2.空船回左岸 3.運狼到右岸 4.帶羊回左岸 5.運菜到右岸 6.空船回左岸 7.運羊到右岸",
        "💡 最佳步數：7步可以完成簡單模式，試試看能否達到！"
    ],
    normal: [
        "💡 記住：狗可以保護羊，但狗和狼會打架",
        "💡 狗的作用：狗在右岸時，狼不能吃羊；狗在左岸時，狼不能吃羊",
        "💡 危險組合：狗+狼（沒有羊）= 狗會咬死狼",
        "💡 安全組合：狗+羊+狼 = 安全，狗+羊+菜 = 安全",
        "💡 關鍵：狗是保護者，但要小心狗和狼的衝突",
        "💡 思考方向：如何利用狗來保護羊？",
        "💡 進階技巧：利用狗作為保護者，但要避免狗和狼的衝突",
        "💡 失敗原因：最常見的錯誤是讓狗和狼單獨在一起",
        "💡 提示：狗可以保護羊不被狼吃掉，但狗和狼不能單獨在一起（沒有羊時）",
        "💡 策略：先運羊，再運狗，然後運狼，最後運菜",
        "💡 步驟：羊→狗→羊→狼→羊→菜→羊",
        "💡 詳細策略：1.運羊到右岸 2.空船回左岸 3.運狗到右岸 4.帶羊回左岸 5.運狼到右岸 6.空船回左岸 7.運羊到右岸 8.空船回左岸 9.運菜到右岸",
        "💡 最佳步數：9步可以完成普通模式，挑戰自己吧！"
    ],
    hard: [
        "💡 記住：困難模式有隨機事件，需要靈活應對",
        "💡 狐狸的威脅：狐狸會偷吃菜，所以狐狸和菜不能單獨在一起",
        "💡 船容量優勢：可以一次運兩個物品，大大減少步數",
        "💡 隨機事件說明：暴風雨(8%機率)、船壞了(6%機率)、物品移動(4%機率)",
        "💡 應對策略：遇到隨機事件時保持冷靜，重新規劃路線",
        "💡 安全檢查：每次移動前檢查所有可能的違規組合",
        "💡 高級技巧：預留安全物品，建立安全區域",
        "💡 心理準備：困難模式需要耐心和策略，不要輕易放棄！",
        "💡 思考方向：如何利用船的大容量來減少步數？",
        "💡 提示：狐狸會偷吃菜，船可以載兩個物品，注意隨機事件！",
        "💡 策略：利用船的大容量，一次運送多個物品",
        "💡 關鍵：狐狸和菜不能單獨在一起，要特別小心",
        "💡 步驟：先運羊和狗，再運狼和狐狸，最後運菜",
        "💡 隨機事件：暴風雨會讓船回到上一步，船壞了只能載一個物品",
        "💡 高級策略：預留安全物品在岸上，避免違規情況",
        "💡 詳細步驟：1.運羊+狗到右岸 2.空船回左岸 3.運狼+狐狸到右岸 4.帶狗回左岸 5.運菜到右岸 6.空船回左岸 7.運狗到右岸",
        "💡 最佳步數：7步可以完成困難模式，但隨機事件會增加難度"
    ]
};

// 顯示提示
function showHint() {
    const hints = hintSets[gameState.mode] || hintSets.easy;
    currentHintIndex = 0;
    
    // 立即更新按鈕文字顯示當前提示編號
    const hintBtn = document.getElementById("show-hint-btn");
    if (hintBtn) {
        hintBtn.textContent = `💡 提示 ${currentHintIndex + 1}/${hints.length}`;
    }
    
    // 不自動顯示提示，等待用戶點擊按鈕
}

// 切換到下一個提示
function nextHint() {
    const hints = hintSets[gameState.mode] || hintSets.easy;
    currentHintIndex = (currentHintIndex + 1) % hints.length;
    const hintText = hints[currentHintIndex];
    const hintWithCounter = `[${currentHintIndex + 1}/${hints.length}] ${hintText}`;
    showMessage(hintWithCounter);
    
    // 更新按鈕文字顯示當前提示編號
    const hintBtn = document.getElementById("show-hint-btn");
    hintBtn.textContent = `💡 提示 ${currentHintIndex + 1}/${hints.length}`;
}

// 更新顯示
function updateDisplay() {
    // 更新物品顯示
    renderItems(leftItemsEl, gameState.leftSide, "left");
    renderItems(rightItemsEl, gameState.rightSide, "right");
    
    // 更新船上的物品
    renderBoatItems();
    
    // 更新農夫位置
    updateFarmerPosition();
    
    // 更新遊戲資訊
    stepCountEl.textContent = `步數: ${gameState.stepCount}`;
    
    // 分數顯示已移除
    
    boatCapacityEl.textContent = `船容量: ${gameState.boatCapacity}`;
    
    // 更新船位置顯示
    const boatPositionEl = document.getElementById("boat-position");
    const positionText = gameState.boatSide === "left" ? "左岸" : "右岸";
    boatPositionEl.textContent = `船位置: ${positionText}`;
    
    // 更新天氣資訊
    updateWeatherInfo();
    
    // 檢查最佳步數
    updateBestSteps();
}

// 更新最佳步數顯示
function updateBestSteps() {
    const best = gameState.bestSteps[gameState.mode];
    if (best !== Infinity) {
        stepCountEl.textContent += ` | 最佳: ${best}`;
    }
}

// 渲染物品
function renderItems(container, items, side) {
    container.innerHTML = "";
    items.forEach(item => {
        const el = createItemEl(item, side);
        container.appendChild(el);
    });
}

// 創建物品元素
function createItemEl(name, side) {
    const div = document.createElement("div");
    div.classList.add("item");
    div.setAttribute("data-item", name);
    
    // 添加表情符號
    const emojis = {
        "狼": "🐺",
        "羊": "🐑", 
        "菜": "🥬",
        "狗": "🐕",
        "狐狸": "🦊"
    };
    
    if (emojis[name]) {
        div.innerHTML = `<span class="emoji">${emojis[name]}</span><span class="name">${name}</span>`;
    } else {
        div.textContent = name;
    }
    
    div.addEventListener("click", () => {
        if (gameState.gameOver) return;
        if (gameState.boatSide !== side) {
            showMessage("船不在這一岸！");
            return;
        }
        
        toggleItemSelection(name);
    });
    
    return div;
}

// 切換物品選擇
function toggleItemSelection(itemName) {
    const index = gameState.selectedItems.indexOf(itemName);
    
    if (index > -1) {
        // 取消選擇
        gameState.selectedItems.splice(index, 1);
        playSound('select');
    } else {
        // 選擇物品
        if (gameState.selectedItems.length >= gameState.boatCapacity) {
            showMessage(`船最多只能載 ${gameState.boatCapacity} 個物品！`);
            return;
        }
        gameState.selectedItems.push(itemName);
        playSound('select');
    }
    
    updateDisplay();
}

// 渲染船上的物品
function renderBoatItems() {
    boatItemsEl.innerHTML = "";
    gameState.selectedItems.forEach(item => {
        const div = document.createElement("div");
        div.classList.add("item", "selected");
        div.setAttribute("data-item", item);
        
        const emojis = {
            "狼": "🐺",
            "羊": "🐑", 
            "菜": "🥬",
            "狗": "🐕",
            "狐狸": "🦊"
        };
        
        if (emojis[item]) {
            div.innerHTML = `<span class="emoji">${emojis[item]}</span><span class="name">${item}</span>`;
        } else {
            div.textContent = item;
        }
        
        boatItemsEl.appendChild(div);
    });
}

// 更新農夫位置和船位置指示
function updateFarmerPosition() {
    // 更新農夫位置
    if (gameState.boatSide === "left") {
        leftFarmerEl.textContent = "👨‍🌾";
        rightFarmerEl.textContent = "";
    } else {
        leftFarmerEl.textContent = "";
        rightFarmerEl.textContent = "👨‍🌾";
    }
    
    // 更新船位置指示
    const leftSide = document.getElementById("left-side");
    const rightSide = document.getElementById("right-side");
    
    if (gameState.boatSide === "left") {
        leftSide.classList.add("active");
        rightSide.classList.remove("active");
    } else {
        leftSide.classList.remove("active");
        rightSide.classList.add("active");
    }
}

// 移動船
function moveBoat() {
    if (gameState.gameOver || gameState.paused) return;
    
    // 保存遊戲歷史
    saveGameState();
    
    // 運送物品（如果有的話）
    if (gameState.selectedItems.length > 0) {
        const currentSide = gameState.boatSide === "left" ? gameState.leftSide : gameState.rightSide;
        const otherSide = gameState.boatSide === "left" ? gameState.rightSide : gameState.leftSide;
        
        gameState.selectedItems.forEach(item => {
            const index = currentSide.indexOf(item);
            if (index > -1) {
                currentSide.splice(index, 1);
                otherSide.push(item);
            }
        });
    }
    
    // 換岸
    gameState.boatSide = gameState.boatSide === "left" ? "right" : "left";
    gameState.selectedItems = [];
    gameState.stepCount++;
    
    playSound('move');
    
    // 困難模式的隨機事件
    if (gameState.mode === "hard") {
        triggerRandomEvent();
    }
    
    // 檢查規則
    checkRules();
    updateDisplay();
}

// 保存遊戲狀態
function saveGameState() {
    gameState.gameHistory.push({
        leftSide: [...gameState.leftSide],
        rightSide: [...gameState.rightSide],
        boatSide: gameState.boatSide,
        stepCount: gameState.stepCount
    });
}

// 檢查遊戲規則
function checkRules() {
    const leftWithoutFarmer = gameState.boatSide !== "left";
    const rightWithoutFarmer = gameState.boatSide !== "right";
    
    // 檢查左岸
    if (leftWithoutFarmer) {
        checkSideRules(gameState.leftSide, "左岸");
    }
    
    // 檢查右岸
    if (rightWithoutFarmer) {
        checkSideRules(gameState.rightSide, "右岸");
    }
    
    // 檢查勝利條件
    if (gameState.rightSide.length === gameState.items.length) {
        handleWin();
    }
}

// 處理勝利
function handleWin() {
    playSound('win');
    
    // 停止計時器
    stopGameTimer();
    
    const currentBest = gameState.bestSteps[gameState.mode];
    if (gameState.stepCount < currentBest) {
        gameState.bestSteps[gameState.mode] = gameState.stepCount;
    }
    
    // 計算分數 - 從資料庫設定讀取
    let scoreReward = 0;
    if (window.difficultySettings && window.difficultySettings[gameState.mode]) {
        scoreReward = window.difficultySettings[gameState.mode].pass_score;
    } else {
        // 預設值
        const defaultScores = {
            "easy": 20,
            "normal": 50,
            "hard": 100
        };
        scoreReward = defaultScores[gameState.mode] || 0;
    }
    
    gameState.score = scoreReward;
    
    // 更新成功對話框內容
    const difficultyNames = {
        "easy": "簡單",
        "normal": "普通", 
        "hard": "困難"
    };
    
    document.getElementById("success-difficulty").textContent = difficultyNames[gameState.mode];
    document.getElementById("success-score").textContent = gameState.score;
    
    // 顯示成功對話框
    showModal("game-success-modal");
    gameState.gameOver = true;
    
    // 保存遊戲結果
    saveGameResult();
}

// 保存遊戲結果到資料庫
async function saveGameResult() {
    try {
        if (!window.memberId) {
            console.log('未登入，跳過保存遊戲結果');
            alert('您尚未登入，遊戲結果不會被保存。請登入後再遊玩以保存記錄！');
            return;
        }
        
        const gameData = {
            member_id: window.memberId,
            game_id: 9, // 過河遊戲的ID
            difficulty: gameState.mode,
            score: gameState.score,
            play_time: gameState.gameTime, // 使用實際遊戲時間
            game_type: '算術邏輯力',
            is_passed: true // 成功完成遊戲
        };
        
        console.log('保存過河遊戲結果:', gameData);
        
        const response = await fetch('api/game_result.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(gameData)
        });
        
        const result = await response.json();
        
        if (result.success) {
            console.log('過河遊戲結果保存成功');
        } else {
            console.error('過河遊戲結果保存失敗:', result.message);
        }
    } catch (error) {
        console.error('保存過河遊戲結果時發生錯誤:', error);
    }
}

// 結束遊戲時保存記錄（未完成遊戲）
async function saveGameResultOnExit() {
    try {
        if (!window.memberId) {
            console.log('未登入，跳過保存遊戲結果');
            return;
        }
        
        // 如果遊戲已經結束（勝利或失敗），不重複保存
        if (gameState.gameOver) {
            return;
        }
        
        const gameData = {
            member_id: window.memberId,
            game_id: 9, // 過河遊戲的ID
            difficulty: gameState.mode,
            score: 0, // 未完成遊戲，分數為0
            play_time: gameState.gameTime, // 使用實際遊戲時間
            game_type: '算術邏輯力',
            is_manual_exit: true, // 手動退出
            is_passed: false // 未完成遊戲
        };
        
        console.log('保存過河遊戲中途退出結果:', gameData);
        
        const response = await fetch('api/game_result.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(gameData)
        });
        
        const result = await response.json();
        
        if (result.success) {
            console.log('過河遊戲中途退出結果保存成功');
        } else {
            console.error('過河遊戲中途退出結果保存失敗:', result.message);
        }
    } catch (error) {
        console.error('保存過河遊戲中途退出結果時發生錯誤:', error);
    }
}

// 遊戲失敗時保存記錄
async function saveGameResultOnFailure() {
    try {
        if (!window.memberId) {
            console.log('未登入，跳過保存遊戲結果');
            alert('您尚未登入，遊戲結果不會被保存。請登入後再遊玩以保存記錄！');
            return;
        }
        
        const gameData = {
            member_id: window.memberId,
            game_id: 9, // 過河遊戲的ID
            difficulty: gameState.mode,
            score: 0, // 遊戲失敗，分數為0
            play_time: gameState.gameTime, // 使用實際遊戲時間
            game_type: '算術邏輯力',
            is_passed: false // 遊戲失敗
        };
        
        console.log('保存過河遊戲失敗結果:', gameData);
        
        const response = await fetch('api/game_result.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(gameData)
        });
        
        const result = await response.json();
        
        if (result.success) {
            console.log('過河遊戲失敗結果保存成功');
        } else {
            console.error('過河遊戲失敗結果保存失敗:', result.message);
        }
    } catch (error) {
        console.error('保存過河遊戲失敗結果時發生錯誤:', error);
    }
}

// 檢查單岸規則
function checkSideRules(sideItems, sideName) {
    const rules = getRulesForMode();
    
    for (const rule of rules) {
        if (rule.check(sideItems)) {
            playSound('lose');
            
            // 停止計時器
            stopGameTimer();
            
            // 更新失敗對話框內容
            const difficultyNames = {
                "easy": "簡單",
                "normal": "普通", 
                "hard": "困難"
            };
            
            document.getElementById("fail-difficulty").textContent = difficultyNames[gameState.mode];
            document.getElementById("fail-game-time").textContent = formatGameTime(gameState.gameTime);
            document.getElementById("fail-score").textContent = `+${gameState.score}`;
            
            // 顯示失敗對話框
            showModal("game-fail-modal");
            gameState.gameOver = true;
            
            // 保存遊戲失敗結果
            saveGameResultOnFailure();
            return;
        }
    }
}

// 根據模式獲取規則
function getRulesForMode() {
    const baseRules = [
        {
            check: (items) => items.includes("狼") && items.includes("羊") && !items.includes("狗"),
            message: "狼吃掉了羊！"
        },
        {
            check: (items) => items.includes("羊") && items.includes("菜"),
            message: "羊吃掉了菜！"
        }
    ];
    
    if (gameState.mode === "normal" || gameState.mode === "hard") {
        baseRules.push({
            check: (items) => items.includes("狼") && items.includes("狗") && !items.includes("羊") && !items.includes("菜"),
            message: "狗咬死了狼！"
        });
    }
    
    if (gameState.mode === "hard") {
        baseRules.push({
            check: (items) => items.includes("狐狸") && items.includes("菜"),
            message: "狐狸偷吃了菜！"
        });
    }
    
    return baseRules;
}

// 困難模式隨機事件
function triggerRandomEvent() {
    const events = [
        {
            name: "暴風雨",
            probability: 0.08,
            effect: () => {
                gameState.weather = "storm";
                gameState.weatherInfo = "🌩️ 暴風雨！船被吹回上一步！";
                // 回到上一步
                if (gameState.gameHistory.length > 0) {
                    const lastState = gameState.gameHistory.pop();
                    gameState.leftSide = [...lastState.leftSide];
                    gameState.rightSide = [...lastState.rightSide];
                    gameState.boatSide = lastState.boatSide;
                    gameState.stepCount = lastState.stepCount;
                }
            }
        },
        {
            name: "船壞了",
            probability: 0.06,
            effect: () => {
                gameState.weather = "broken";
                gameState.weatherInfo = "🔧 船壞了！只能載一個物品！";
                gameState.boatCapacity = 1;
            }
        },
        {
            name: "物品自己移動",
            probability: 0.04,
            effect: () => {
                gameState.weather = "random_move";
                gameState.weatherInfo = "🌀 有物品自己移動了！";
                // 隨機移動一個物品
                const allItems = [...gameState.leftSide, ...gameState.rightSide];
                if (allItems.length > 0) {
                    const randomItem = allItems[Math.floor(Math.random() * allItems.length)];
                    moveRandomItem(randomItem);
                }
            }
        }
    ];
    
    for (const event of events) {
        if (Math.random() < event.probability) {
            event.effect();
            break;
        }
    }
}

// 隨機移動物品
function moveRandomItem(itemName) {
    if (gameState.leftSide.includes(itemName)) {
        gameState.leftSide = gameState.leftSide.filter(item => item !== itemName);
        gameState.rightSide.push(itemName);
    } else if (gameState.rightSide.includes(itemName)) {
        gameState.rightSide = gameState.rightSide.filter(item => item !== itemName);
        gameState.leftSide.push(itemName);
    }
}

// 更新天氣資訊
function updateWeatherInfo() {
    if (gameState.weatherInfo) {
        weatherInfoEl.textContent = gameState.weatherInfo;
        weatherInfoEl.classList.add("weather-active");
        
        // 3秒後清除天氣資訊
        setTimeout(() => {
            gameState.weatherInfo = "";
            weatherInfoEl.classList.remove("weather-active");
        }, 3000);
    }
}

// 顯示訊息
function showMessage(text) {
    messageEl.textContent = text;
    messageEl.style.animation = "none";
    messageEl.offsetHeight; // 觸發重繪
    messageEl.style.animation = "slideIn 0.5s ease-out";
}

// 清除訊息
function clearMessage() {
    messageEl.textContent = "";
}

// 開始遊戲計時器
function startGameTimer() {
    gameState.startTime = Date.now();
    gameState.gameTime = 0;
    
    gameState.gameTimer = setInterval(() => {
        if (!gameState.paused && !gameState.gameOver) {
            gameState.gameTime = Math.floor((Date.now() - gameState.startTime) / 1000);
        }
    }, 1000);
}

// 停止遊戲計時器
function stopGameTimer() {
    if (gameState.gameTimer) {
        clearInterval(gameState.gameTimer);
        gameState.gameTimer = null;
    }
}

// 格式化遊戲時間
function formatGameTime(seconds) {
    if (seconds < 60) {
        return `${seconds}秒`;
    } else {
        const minutes = Math.floor(seconds / 60);
        const remainingSeconds = seconds % 60;
        return `${minutes}分${remainingSeconds}秒`;
    }
}

// 顯示彈出對話框
function showModal(modalId) {
    document.getElementById(modalId).classList.add("active");
}

// 隱藏彈出對話框
function hideModal(modalId) {
    document.getElementById(modalId).classList.remove("active");
}

// 主題選擇視窗相關函數

function showHelp() {
    // 這裡可以添加顯示幫助的邏輯
    showScreen("rules-screen");
}
