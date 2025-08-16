// 同步優化 - 改進版
// 如果 WebSocket 不可用，使用高效的輪詢機制

// 同步頻率設定
const SYNC_CONFIG = {
    // 遊戲狀態同步頻率（毫秒）
    GAME_SYNC_INTERVAL: 3000,  // 減少到 3000ms，提高同步頻率
    
    // 邀請狀態檢查頻率（毫秒）
    INVITATION_CHECK_INTERVAL: 5000,  // 減少到 5000ms
    
    // 遊戲設定檢查頻率（毫秒）
    SETTINGS_CHECK_INTERVAL: 3000,  // 減少到 3000ms
    
    // 防抖延遲（毫秒）
    DEBOUNCE_DELAY: 500,  // 減少到 500ms
    
    // 重試次數
    MAX_RETRY_ATTEMPTS: 2,  // 增加到 2次
    
    // 重試延遲（毫秒）
    RETRY_DELAY: 1000  // 減少到 1000ms
};

// 同步狀態追蹤
let syncState = {
    lastGameSync: 0,
    lastInvitationCheck: 0,
    lastSettingsCheck: 0,
    retryCount: 0,
    isProcessing: false
};

// 改進的遊戲同步函數
function optimizedGameSync() {
    if (gameMode !== 'online' || !invitationId) return;
    
    const now = Date.now();
    if (now - syncState.lastGameSync < SYNC_CONFIG.GAME_SYNC_INTERVAL) return;
    
    syncState.lastGameSync = now;
    
    // 使用 fetch 的 AbortController 來取消重複請求
    if (window.currentGameSyncController) {
        window.currentGameSyncController.abort();
    }
    
    window.currentGameSyncController = new AbortController();
    
    fetch('game-sync-api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        credentials: 'include',
        signal: window.currentGameSyncController.signal,
        body: JSON.stringify({
            action: 'get_game_state',
            invitation_id: invitationId
        })
    })
    .then(response => {
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        return response.json();
    })
    .then(data => {
        if (data.success) {
            syncState.retryCount = 0;
            updateGameFromSync(data.game_state);
        } else {
            console.warn('遊戲同步失敗:', data.message);
        }
    })
    .catch(error => {
        if (error.name === 'AbortError') {
            console.log('遊戲同步請求已取消');
            return;
        }
        
        console.error('遊戲同步錯誤:', error);
        syncState.retryCount++;
        
        if (syncState.retryCount < SYNC_CONFIG.MAX_RETRY_ATTEMPTS) {
            setTimeout(optimizedGameSync, SYNC_CONFIG.RETRY_DELAY);
        }
    });
}

// 改進的邀請檢查函數
function optimizedInvitationCheck() {
    if (gameMode !== 'online' || !invitationId) return;
    
    const now = Date.now();
    if (now - syncState.lastInvitationCheck < SYNC_CONFIG.INVITATION_CHECK_INTERVAL) return;
    
    syncState.lastInvitationCheck = now;
    
    fetch('game-invitation-api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        credentials: 'include',
        body: JSON.stringify({
            action: 'get_invitation_status',
            invitation_id: invitationId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            handleInvitationStatusUpdate(data);
        }
    })
    .catch(error => {
        console.error('邀請狀態檢查錯誤:', error);
    });
}

// 改進的設定檢查函數
function optimizedSettingsCheck() {
    if (gameMode !== 'online' || !invitationId) return;
    
    const now = Date.now();
    if (now - syncState.lastSettingsCheck < SYNC_CONFIG.SETTINGS_CHECK_INTERVAL) return;
    
    syncState.lastSettingsCheck = now;
    
    fetch('game-invitation-api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        credentials: 'include',
        body: JSON.stringify({
            action: 'get_game_settings',
            invitation_id: invitationId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.game_settings) {
            handleGameSettingsUpdate(data.game_settings);
        }
    })
    .catch(error => {
        console.error('遊戲設定檢查錯誤:', error);
    });
}

// 處理邀請狀態更新
function handleInvitationStatusUpdate(data) {
    if (data.status === 'accepted' && data.game_settings) {
        console.log('邀請已接受，遊戲設定已就緒');
        // 觸發遊戲開始
        if (typeof startOnlineGame === 'function') {
            startOnlineGame(data);
        }
    }
}

// 處理遊戲設定更新
function handleGameSettingsUpdate(settings) {
    const newDifficulty = settings.difficulty;
    const newTheme = settings.theme;
    
    if (newDifficulty && newDifficulty !== currentDifficulty) {
        console.log('檢測到困難度變更:', newDifficulty);
        currentDifficulty = newDifficulty;
        
        // 重新初始化遊戲
        setTimeout(() => {
            if (typeof initializeGame === 'function') {
                initializeGame();
            }
        }, 100);
    }
    
    if (newTheme && newTheme !== currentTheme) {
        console.log('檢測到主題變更:', newTheme);
        currentTheme = newTheme;
    }
}

// 防抖函數
function debounce(func, delay) {
    let timeoutId;
    return function (...args) {
        clearTimeout(timeoutId);
        timeoutId = setTimeout(() => func.apply(this, args), delay);
    };
}

// 節流函數
function throttle(func, limit) {
    let inThrottle;
    return function (...args) {
        if (!inThrottle) {
            func.apply(this, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    };
}

// 啟動優化同步
function startOptimizedSync() {
    console.log('啟動優化同步機制');
    
    // 遊戲狀態同步
    setInterval(optimizedGameSync, SYNC_CONFIG.GAME_SYNC_INTERVAL);
    
    // 邀請狀態檢查
    setInterval(optimizedInvitationCheck, SYNC_CONFIG.INVITATION_CHECK_INTERVAL);
    
    // 遊戲設定檢查
    setInterval(optimizedSettingsCheck, SYNC_CONFIG.SETTINGS_CHECK_INTERVAL);
}

// 停止同步
function stopOptimizedSync() {
    console.log('停止優化同步機制');
    
    if (window.currentGameSyncController) {
        window.currentGameSyncController.abort();
    }
    
    syncState = {
        lastGameSync: 0,
        lastInvitationCheck: 0,
        lastSettingsCheck: 0,
        retryCount: 0,
        isProcessing: false
    };
}

// 導出函數供其他模組使用
window.optimizedSync = {
    start: startOptimizedSync,
    stop: stopOptimizedSync,
    config: SYNC_CONFIG
};

// 確保在 DOM 載入後立即可用
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        console.log('sync-optimization.js 已載入，optimizedSync 可用');
    });
} else {
    console.log('sync-optimization.js 已載入，optimizedSync 可用');
} 