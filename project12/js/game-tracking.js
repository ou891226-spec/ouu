/**
 * 遊戲追蹤JavaScript輔助函數
 * 用於處理遊戲的開始、結束和退出
 */

class GameTracker {
    constructor() {
        this.currentRecordId = null;
        this.gameStartTime = null;
        this.isGameActive = false;
        
        // 頁面關閉時處理遊戲退出
        this.setupPageExitHandling();
    }
    
    /**
     * 開始遊戲
     * @param {string} gameType - 遊戲類型
     * @param {string} difficulty - 難度 (easy/normal/hard)
     * @param {number} gameId - 遊戲ID
     * @param {number} memberId - 會員ID
     */
    async startGame(gameType, difficulty = 'easy', gameId = null, memberId = null) {
        try {
            const response = await fetch('start_game.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new URLSearchParams({
                    game_type: gameType,
                    difficulty: difficulty,
                    game_id: gameId || '',
                    member_id: memberId || ''
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.currentRecordId = data.record_id;
                this.gameStartTime = Date.now();
                this.isGameActive = true;
                
                console.log('遊戲開始記錄成功:', data.record_id);
                return true;
            } else {
                console.error('遊戲開始記錄失敗:', data.message);
                return false;
            }
        } catch (error) {
            console.error('遊戲開始請求失敗:', error);
            return false;
        }
    }
    
    /**
     * 結束遊戲
     * @param {number} score - 遊戲分數
     * @param {string} gameType - 遊戲類型
     * @param {string} difficulty - 難度
     * @param {number} memberId - 會員ID
     */
    async endGame(score, gameType = null, difficulty = null, memberId = null) {
        if (!this.isGameActive) {
            console.warn('沒有活躍的遊戲記錄');
            return false;
        }
        
        try {
            const playTime = Math.floor((Date.now() - this.gameStartTime) / 1000);
            
            const response = await fetch('end_game.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new URLSearchParams({
                    score: score,
                    play_time: playTime,
                    game_type: gameType || '',
                    difficulty: difficulty || '',
                    member_id: memberId || ''
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                console.log('遊戲結束記錄成功:', data.status, '分數:', score, '時間:', playTime + '秒');
                this.clearGameSession();
                return data;
            } else {
                console.error('遊戲結束記錄失敗:', data.message);
                return false;
            }
        } catch (error) {
            console.error('遊戲結束請求失敗:', error);
            return false;
        }
    }
    
    /**
     * 退出遊戲
     */
    async exitGame() {
        if (!this.isGameActive) {
            return false;
        }
        
        try {
            const response = await fetch('exit_game.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new URLSearchParams({
                    record_id: this.currentRecordId
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                console.log('遊戲退出記錄成功');
                this.clearGameSession();
                return true;
            } else {
                console.error('遊戲退出記錄失敗:', data.message);
                return false;
            }
        } catch (error) {
            console.error('遊戲退出請求失敗:', error);
            return false;
        }
    }
    
    /**
     * 清理遊戲會話
     */
    clearGameSession() {
        this.currentRecordId = null;
        this.gameStartTime = null;
        this.isGameActive = false;
    }
    
    /**
     * 設置頁面退出處理
     */
    setupPageExitHandling() {
        // 頁面關閉前處理
        window.addEventListener('beforeunload', (event) => {
            if (this.isGameActive) {
                // 使用 sendBeacon 確保請求能發送
                if (navigator.sendBeacon) {
                    const formData = new FormData();
                    formData.append('record_id', this.currentRecordId);
                    navigator.sendBeacon('exit_game.php', formData);
                } else {
                    // 備用方案：同步請求
                    const xhr = new XMLHttpRequest();
                    xhr.open('POST', 'exit_game.php', false);
                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                    xhr.send('record_id=' + this.currentRecordId);
                }
            }
        });
        
        // 頁面隱藏時處理（移動端）
        document.addEventListener('visibilitychange', () => {
            if (document.hidden && this.isGameActive) {
                this.exitGame();
            }
        });
    }
    
    /**
     * 初始化遊戲追蹤（兼容舊的 init 調用）
     * @param {string} gameType - 遊戲類型
     * @param {number} gameId - 遊戲ID
     */
    init(gameType, gameId = null) {
        console.log('GameTracker init 被調用:', gameType, gameId);
        // 這個方法主要是為了兼容性，實際的遊戲開始記錄會在 startGame 中進行
        this.currentGameType = gameType;
        this.currentGameId = gameId;
        
        // 自動開始遊戲記錄
        const memberId = document.getElementById('member-id')?.value || 
                        window.memberId || 
                        localStorage.getItem('member_id') || 
                        1;
        
        // 獲取當前難度
        const difficulty = this.getCurrentDifficulty();
        
        this.startGame(gameType, difficulty, gameId, memberId);
    }
    
    /**
     * 獲取當前難度
     */
    getCurrentDifficulty() {
        // 嘗試從各種來源獲取難度
        const difficultySelect = document.querySelector('select[name="difficulty"]');
        if (difficultySelect) {
            return difficultySelect.value || 'easy';
        }
        
        const difficultyInput = document.querySelector('input[name="difficulty"]:checked');
        if (difficultyInput) {
            return difficultyInput.value || 'easy';
        }
        
        // 檢查是否有全局難度變數
        if (window.currentDifficulty) {
            return window.currentDifficulty;
        }
        
        return 'easy'; // 預設值
    }

    /**
     * 獲取當前遊戲狀態
     */
    getGameStatus() {
        return {
            isActive: this.isGameActive,
            recordId: this.currentRecordId,
            startTime: this.gameStartTime,
            playTime: this.gameStartTime ? Math.floor((Date.now() - this.gameStartTime) / 1000) : 0
        };
    }
}

// 創建全局實例
window.gameTracker = new GameTracker();

// 導出類（如果使用模塊化）
if (typeof module !== 'undefined' && module.exports) {
    module.exports = GameTracker;
}
