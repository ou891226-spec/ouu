/**
 * 通用遊戲函數庫
 * 所有遊戲都可以使用這些函數來處理遊戲結果
 */

/**
 * 儲存遊戲結果到資料庫
 * @param {Object} gameData - 遊戲數據
 * @returns {Promise<Object>} 儲存結果
 */
async function saveGameResult(gameData) {
    try {
        console.log('=== 儲存遊戲結果開始 ===');
        console.log('遊戲數據:', gameData);
        
        // 驗證必要參數
        const requiredFields = ['member_id', 'game_type', 'difficulty'];
        for (const field of requiredFields) {
            if (!gameData[field]) {
                throw new Error(`缺少必要參數: ${field}`);
            }
        }
        
        // 設定預設值
        const data = {
            score: 0,
            play_time: 0,
            is_manual_exit: false,
            is_passed: null,
            game_id: null,
            ...gameData
        };
        
        // 發送請求到統一的 API 端點
        const response = await fetch('api/game_result.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        });
        
        console.log('收到響應:', response.status, response.statusText);
        
        if (!response.ok) {
            throw new Error(`HTTP 錯誤: ${response.status}`);
        }
        
        const result = await response.json();
        console.log('API 響應:', result);
        
        if (!result.success) {
            throw new Error(result.message || '儲存失敗');
        }
        
        console.log('=== 遊戲結果儲存成功 ===');
        console.log('記錄ID:', result.record_id);
        console.log('最終狀態:', result.status);
        console.log('最終分數:', result.score);
        
        // 如果有完成的任務，顯示通知
        if (result.completed_tasks && result.completed_tasks.length > 0) {
            console.log('完成的任務:', result.completed_tasks);
            showTaskCompletedNotification(result.completed_tasks.length);
        }
        
        return result;
        
    } catch (error) {
        console.error('=== 儲存遊戲結果失敗 ===');
        console.error('錯誤:', error.message);
        console.error('遊戲數據:', gameData);
        
        // 顯示錯誤通知
        showErrorMessage('儲存遊戲結果失敗: ' + error.message);
        
        return {
            success: false,
            message: error.message
        };
    }
}

/**
 * 獲取當前會員ID
 * @returns {number|null} 會員ID
 */
function getCurrentMemberId() {
    // 嘗試從多個來源獲取會員ID
    // 1. 從 URL 參數獲取
    const urlParams = new URLSearchParams(window.location.search);
    const memberIdFromUrl = urlParams.get('member_id');
    if (memberIdFromUrl) {
        return parseInt(memberIdFromUrl);
    }
    
    // 2. 從全域變數獲取
    if (typeof window.memberId !== 'undefined' && window.memberId) {
        return parseInt(window.memberId);
    }
    
    // 3. 從 localStorage 獲取
    const memberIdFromStorage = localStorage.getItem('member_id');
    if (memberIdFromStorage) {
        return parseInt(memberIdFromStorage);
    }
    
    console.warn('無法獲取會員ID');
    return null;
}

/**
 * 檢查是否為手動退出
 * @param {string} reason - 退出原因
 * @returns {boolean} 是否為手動退出
 */
function isManualExit(reason) {
    const manualExitReasons = ['user_close', 'user_back', 'manual_exit', 'timeout'];
    return manualExitReasons.includes(reason);
}

/**
 * 計算遊戲分數
 * @param {Object} gameData - 遊戲數據
 * @returns {number} 計算後的分數
 */
function calculateGameScore(gameData) {
    let score = gameData.score || 0;
    
    // 根據難度給予基礎分數獎勵（如果遊戲通過）
    if (gameData.is_passed || score > 0) {
        switch (gameData.difficulty) {
            case 'easy':
            case '簡單':
                score += 20;
                break;
            case 'normal':
            case '普通':
                score += 50;
                break;
            case 'hard':
            case '困難':
                score += 100;
                break;
        }
    }
    
    return score;
}

/**
 * 顯示任務完成通知
 * @param {number} taskCount - 完成的任務數量
 */
function showTaskCompletedNotification(taskCount) {
    // 創建通知元素
    const notification = document.createElement('div');
    notification.className = 'task-completed-notification';
    notification.innerHTML = `
        <div class="notification-content">
            <i class="icon-check"></i>
            <span>完成了 ${taskCount} 個每日任務！</span>
        </div>
    `;
    
    // 添加樣式
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: #4CAF50;
        color: white;
        padding: 15px 20px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        z-index: 10000;
        animation: slideInRight 0.3s ease-out;
    `;
    
    // 添加到頁面
    document.body.appendChild(notification);
    
    // 3秒後自動移除
    setTimeout(() => {
        notification.style.animation = 'slideOutRight 0.3s ease-in';
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }, 3000);
}

/**
 * 顯示錯誤訊息
 * @param {string} message - 錯誤訊息
 */
function showErrorMessage(message) {
    // 創建錯誤通知元素
    const notification = document.createElement('div');
    notification.className = 'error-notification';
    notification.innerHTML = `
        <div class="notification-content">
            <i class="icon-error"></i>
            <span>${message}</span>
        </div>
    `;
    
    // 添加樣式
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: #f44336;
        color: white;
        padding: 15px 20px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        z-index: 10000;
        animation: slideInRight 0.3s ease-out;
    `;
    
    // 添加到頁面
    document.body.appendChild(notification);
    
    // 5秒後自動移除
    setTimeout(() => {
        notification.style.animation = 'slideOutRight 0.3s ease-in';
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }, 5000);
}

/**
 * 添加 CSS 動畫樣式
 */
function addNotificationStyles() {
    if (document.getElementById('notification-styles')) {
        return; // 已經添加過了
    }
    
    const style = document.createElement('style');
    style.id = 'notification-styles';
    style.textContent = `
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @keyframes slideOutRight {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }
        
        .notification-content {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .icon-check::before {
            content: '✓';
            font-weight: bold;
        }
        
        .icon-error::before {
            content: '✗';
            font-weight: bold;
        }
    `;
    
    document.head.appendChild(style);
}

// 頁面載入時添加樣式
document.addEventListener('DOMContentLoaded', addNotificationStyles);

