// ===== 樂齡智趣網 - 小遊戲時間記錄系統（全新版本） =====

// 全局變數
let gameStartTime = null;
let totalGameTime = 0;
let isInGame = false;

// 從 localStorage 讀取已累計的遊戲時間
function loadTotalGameTime() {
    const saved = localStorage.getItem('totalGameTime');
    totalGameTime = saved ? parseInt(saved) : 0;
    console.log('載入已累計遊戲時間:', totalGameTime, '秒');
}

// 保存總遊戲時間到 localStorage
function saveTotalGameTime() {
    localStorage.setItem('totalGameTime', totalGameTime.toString());
    console.log('保存總遊戲時間:', totalGameTime, '秒');
}

// 時間格式化：轉成 HH:MM:SS
function formatTime(seconds) {
    const hrs = String(Math.floor(seconds / 3600)).padStart(2, '0');
    const mins = String(Math.floor((seconds % 3600) / 60)).padStart(2, '0');
    const secs = String(seconds % 60).padStart(2, '0');
    return `${hrs}:${mins}:${secs}`;
}

// 更新時間顯示
function updateGameTimeDisplay() {
    const timeValue = document.getElementById('timeValue');
    if (timeValue) {
        let displayTime = totalGameTime;
        
        // 如果在遊戲中，加上當前遊戲時間
        if (isInGame && gameStartTime) {
            const currentGameTime = Math.floor((Date.now() - gameStartTime) / 1000);
            displayTime += currentGameTime;
        }
        
        timeValue.textContent = formatTime(displayTime);
        console.log('更新時間顯示:', formatTime(displayTime), 'totalGameTime:', totalGameTime, 'isInGame:', isInGame);
    } else {
        // 只在非遊戲頁面才顯示找不到元素的訊息，避免控制台雜訊
        if (!isMiniGamePage()) {
            console.log('找不到 timeValue 元素');
        }
    }
}

// 檢查是否為小遊戲頁面
function isMiniGamePage() {
    const currentPath = window.location.pathname;
    const miniGamePages = [
        '/2048ht.php',
        '/Catch-Egg-Game.php',
        '/Vegetable-Cost.php',
        '/Memory-Game.php',
        '/Memory-Game-2P.php',
        '/prisoner.php',
        '/rhythm_game.php',
        '/text-color.php',
        '/clue.php',
        '/river.php',
        '/river.index.php'
    ];
    
    const isGame = miniGamePages.some(page => currentPath.includes(page));
    console.log('檢查遊戲頁面 - 當前路徑:', currentPath, '是否為遊戲頁面:', isGame);
    
    return isGame;
}

// 開始遊戲計時
function startGameTimer() {
    if (isMiniGamePage() && !isInGame) {
        gameStartTime = Date.now();
        isInGame = true;
        console.log('開始遊戲計時');
    }
}

// 手動開始計時（供遊戲調用）
function manualStartGameTimer() {
    if (!isInGame) {
        gameStartTime = Date.now();
        isInGame = true;
        console.log('手動開始遊戲計時');
    }
}

// 導出到全局
window.manualStartGameTimer = manualStartGameTimer;
window.manualEndGameTimer = endGameTimer;

// 結束遊戲計時
function endGameTimer() {
    if (isInGame && gameStartTime) {
        const gameTime = Math.floor((Date.now() - gameStartTime) / 1000);
        totalGameTime += gameTime;
        saveTotalGameTime();
        console.log('結束遊戲計時，本次遊戲時間:', gameTime, '秒，總時間:', totalGameTime, '秒');
        
        gameStartTime = null;
        isInGame = false;
        
        // 立即更新顯示
        updateGameTimeDisplay();
    }
}

// 顯示時間詳情
function showTimeDetail() {
    let message = "您已累積的遊戲時間：" + formatTime(totalGameTime);
    
    if (isInGame && gameStartTime) {
        const currentGameTime = Math.floor((Date.now() - gameStartTime) / 1000);
        message += "\n當前遊戲時間：" + formatTime(currentGameTime);
        message += "\n總計時間：" + formatTime(totalGameTime + currentGameTime);
    }
    
    alert(message);
}

// 頁面載入時初始化
document.addEventListener('DOMContentLoaded', function() {
    loadTotalGameTime();
    forceShowTime(); // 強制顯示時間
    updateGameTimeDisplay();
    
    // 不再自動開始計時，等待遊戲手動調用
    if (isMiniGamePage()) {
        console.log('進入遊戲頁面，等待遊戲開始才計時');
    } else {
        console.log('非遊戲頁面，不計時');
    }
});

// 頁面卸載時結束計時
window.addEventListener('beforeunload', function() {
    endGameTimer();
});

// 頁面隱藏時結束計時
document.addEventListener('visibilitychange', function() {
    if (document.hidden) {
        endGameTimer();
    }
    // 不再自動重新開始計時，避免在說明頁面時計時
});

// 每秒更新顯示
setInterval(updateGameTimeDisplay, 1000);

// 定期保存（每30秒）
setInterval(function() {
    if (isInGame && gameStartTime) {
        const gameTime = Math.floor((Date.now() - gameStartTime) / 1000);
        totalGameTime += gameTime;
        saveTotalGameTime();
        
        // 重置計時器
        gameStartTime = Date.now();
        console.log('定期保存，累計遊戲時間:', gameTime, '秒，總時間:', totalGameTime, '秒');
    }
}, 30000);

// 監聽連結點擊，離開遊戲頁面時結束計時
document.addEventListener('click', function(event) {
    const target = event.target.closest('a');
    if (target && isInGame) {
        endGameTimer();
    }
});

// 強制顯示時間
function forceShowTime() {
    const timeValue = document.getElementById('timeValue');
    if (timeValue) {
        timeValue.style.display = 'inline';
        timeValue.style.visibility = 'visible';
        timeValue.style.opacity = '1';
        console.log('強制顯示時間元素');
    }
    
    const timeContainer = document.querySelector('.time');
    if (timeContainer) {
        timeContainer.style.display = 'block';
        timeContainer.style.visibility = 'visible';
        timeContainer.style.opacity = '1';
        console.log('強制顯示時間容器');
    }
}

// 全局測試函數
window.testGameTime = function() {
    console.log('=== 遊戲時間測試 ===');
    console.log('是否在遊戲頁面:', isMiniGamePage());
    console.log('是否正在遊戲中:', isInGame);
    console.log('遊戲開始時間:', gameStartTime);
    console.log('已累計總時間:', totalGameTime, '秒');
    console.log('localStorage中的時間:', localStorage.getItem('totalGameTime'));
    
    // 強制顯示時間
    forceShowTime();
    updateGameTimeDisplay();
    
    if (isInGame && gameStartTime) {
        const currentGameTime = Math.floor((Date.now() - gameStartTime) / 1000);
        console.log('當前遊戲時間:', currentGameTime, '秒');
        console.log('總顯示時間:', totalGameTime + currentGameTime, '秒');
    }
};

// 手動添加測試時間
window.addTestTime = function(seconds) {
    totalGameTime += seconds;
    saveTotalGameTime();
    updateGameTimeDisplay();
    console.log(`已添加 ${seconds} 秒測試時間，總時間: ${totalGameTime} 秒`);
};

// 重置時間
window.resetTime = function() {
    totalGameTime = 0;
    saveTotalGameTime();
    updateGameTimeDisplay();
    console.log('已重置時間為 0');
};

// 強制開始計時（用於測試）
window.forceStartTimer = function() {
    if (!isInGame) {
        gameStartTime = Date.now();
        isInGame = true;
        console.log('強制開始計時');
        updateGameTimeDisplay();
    } else {
        console.log('已經在計時中');
    }
};

// 強制結束計時（用於測試）
window.forceEndTimer = function() {
    if (isInGame && gameStartTime) {
        const gameTime = Math.floor((Date.now() - gameStartTime) / 1000);
        totalGameTime += gameTime;
        saveTotalGameTime();
        console.log('強制結束計時，本次時間:', gameTime, '秒，總時間:', totalGameTime, '秒');
        
        gameStartTime = null;
        isInGame = false;
        updateGameTimeDisplay();
    } else {
        console.log('沒有在計時中');
    }
};

console.log('小遊戲時間記錄系統已載入');
console.log('可用測試函數: testGameTime(), addTestTime(seconds), resetTime(), forceStartTimer(), forceEndTimer()');

// 立即測試一次
setTimeout(() => {
    console.log('=== 系統初始化檢查 ===');
    console.log('當前頁面:', window.location.pathname);
    console.log('是否為遊戲頁面:', isMiniGamePage());
    console.log('已載入時間:', totalGameTime, '秒');
    console.log('localStorage時間:', localStorage.getItem('totalGameTime'));
    updateGameTimeDisplay();
}, 1000);
