/**
 * 通用遊戲退出處理器
 * 處理瀏覽器返回按鈕和頁面關閉事件
 */

class GameExitHandler {
    constructor(gameConfig) {
        this.gameConfig = gameConfig;
        this.isGameActive = false;
        this.gameStartTime = null;
        this.getPlayerPlayTime = null; // 函數，用於獲取玩家實際操作時間
        this.setupEventListeners();
    }
    
    /**
     * 設置事件監聽器
     */
    setupEventListeners() {
        // 頁面離開時標記遊戲退出
        window.addEventListener('beforeunload', (event) => {
            this.handlePageExit();
        });
        
        // 頁面隱藏時也標記退出
        document.addEventListener('visibilitychange', () => {
            if (document.hidden && this.isGameActive) {
                this.handlePageExit();
            }
        });
    }
    
    /**
     * 處理頁面退出
     */
    handlePageExit() {
        if (!this.isGameActive) {
            return;
        }
        
        // 立即停止追蹤，防止重複觸發
        this.isGameActive = false;
        
        // 使用 sendBeacon 確保請求能發送
        if (navigator.sendBeacon) {
            // 獲取玩家實際操作時間
            let playTime = 0;
            if (this.getPlayerPlayTime && typeof this.getPlayerPlayTime === 'function') {
                playTime = this.getPlayerPlayTime();
            } else if (this.gameStartTime) {
                // 備用方案：使用總時間
                playTime = Math.floor((Date.now() - this.gameStartTime) / 1000);
            }
            
            const data = {
                member_id: this.gameConfig.memberId,
                game_type: this.gameConfig.gameType,
                game_id: this.gameConfig.gameId,
                difficulty: this.gameConfig.difficulty || 'easy',
                score: 0, // 強制退出時分數為0
                play_time: playTime,
                is_manual_exit: true,
                is_passed: false
            };
            
            const formData = new FormData();
            formData.append('data', JSON.stringify(data));
            navigator.sendBeacon('api/game_result.php', formData);
            
            console.log('遊戲退出記錄已發送:', data);
        }
    }
    
    /**
     * 開始遊戲
     */
    startGame() {
        this.isGameActive = true;
        this.gameStartTime = Date.now();
        console.log('遊戲開始追蹤');
    }
    
    /**
     * 結束遊戲
     */
    endGame() {
        this.isGameActive = false;
        this.gameStartTime = null;
        console.log('遊戲結束追蹤');
    }
    
    /**
     * 更新遊戲配置
     */
    updateConfig(newConfig) {
        this.gameConfig = { ...this.gameConfig, ...newConfig };
    }
    
    /**
     * 設置獲取玩家操作時間的函數
     */
    setPlayerPlayTimeFunction(func) {
        this.getPlayerPlayTime = func;
    }
}

// 創建全域實例
window.gameExitHandler = new GameExitHandler({
    memberId: document.getElementById('member-id')?.value || 1,
    gameType: '',
    gameId: 0,
    difficulty: 'easy'
});

console.log('遊戲退出處理器已載入');
