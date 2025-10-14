// 統一遊戲追蹤器
const gameTracker = {
    gameName: '',
    gameId: 0,
    currentRecordId: null,
    startTime: null,
    isTracking: false,
    
    // 初始化追蹤器
    init: function(gameName, gameId) {
        // 如果已經在追蹤，先結束舊的追蹤
        if (this.isTracking) {
            console.log(`遊戲追蹤器正在追蹤，重新初始化`);
        }
        
        this.gameName = gameName;
        this.gameId = gameId;
        this.startTime = Date.now();
        this.isTracking = true;
        
        console.log(`遊戲追蹤器已初始化: ${gameName} (ID: ${gameId})`);
        
        // 記錄遊戲進入
        this.recordGameEntry();
    },
    
    // 記錄遊戲進入
    recordGameEntry: function() {
        if (!this.isTracking) return;
        
        // 這裡可以添加 AJAX 請求記錄遊戲進入
        // 目前只是模擬功能
        console.log(`記錄遊戲進入: ${this.gameName}`);
        
        // 模擬記錄 ID
        this.currentRecordId = Date.now();
    },
    
    // 記錄遊戲退出
    exitGame: function() {
        if (!this.isTracking || !this.currentRecordId) return;
        
        const playTime = Math.floor((Date.now() - this.startTime) / 1000);
        console.log(`記錄遊戲退出: ${this.gameName}, 遊戲時間: ${playTime}秒`);
        
        // 這裡可以添加 AJAX 請求記錄遊戲退出
        // 目前只是模擬功能
        
        this.isTracking = false;
        this.currentRecordId = null;
    },
    
    // 記錄遊戲行為
    logBehavior: function(action, data = {}) {
        if (!this.isTracking) return;
        
        const behaviorData = {
            gameName: this.gameName,
            gameId: this.gameId,
            action: action,
            timestamp: Date.now(),
            data: data
        };
        
        console.log('記錄遊戲行為:', behaviorData);
        
        // 這裡可以添加 AJAX 請求記錄行為
        // 目前只是模擬功能
    },
    
    // 獲取遊戲時間
    getPlayTime: function() {
        if (!this.isTracking || !this.startTime) return 0;
        return Math.floor((Date.now() - this.startTime) / 1000);
    },
    
    // 暫停追蹤
    pause: function() {
        this.isTracking = false;
        console.log(`暫停遊戲追蹤: ${this.gameName}`);
    },
    
    // 恢復追蹤
    resume: function() {
        this.isTracking = true;
        console.log(`恢復遊戲追蹤: ${this.gameName}`);
    }
};

// 全域可用
window.gameTracker = gameTracker;

// 頁面離開時自動記錄退出
window.addEventListener('beforeunload', function() {
    if (gameTracker.isTracking) {
        gameTracker.exitGame();
    }
});

// 頁面隱藏時暫停追蹤
document.addEventListener('visibilitychange', function() {
    if (document.hidden) {
        gameTracker.pause();
    } else {
        gameTracker.resume();
    }
});

console.log('統一遊戲追蹤器已載入');
