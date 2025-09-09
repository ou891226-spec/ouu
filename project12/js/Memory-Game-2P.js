// 雙人模式遊戲變數
let cards = [];
let flippedCards = [];
let matchedPairs = 0;
let totalMoves = 0;
let canFlip = true;
let currentDifficulty = 'easy';
let currentTheme = 'fruit';
let gridSize = 4;
let gamePaused = false;
let gameStartTimestamp = null;
let gameEndTimestamp = null;

// 回合計時器相關變數
let turnTimer = null;
let turnTimeLeft = 10;
let isTurnActive = false;

// 雙人模式專用變數
let currentPlayer = 1; // 1 或 2
let player1Score = 0;
let player2Score = 0;
let player1Pairs = 0;
let player2Pairs = 0;
let player1Name = '玩家 1';
let player2Name = '玩家 2';
let consecutiveMatches = 0; // 連續配對次數

// 好友邀請相關變數
let invitedFriendId = null;
let invitedFriendName = null;
let gameMode = 'local'; // 'local', 'online'
let invitationId = null;
let invitationData = null; // 存儲邀請數據
let invitationCheckInterval = null; // 用於檢查邀請狀態的輪詢

// 遊戲同步相關變數
let gameSyncInterval = null;
let isMyTurn = false;
let currentUserId = null; // 將在頁面載入時初始化

// 防抖變數，避免重複翻牌動畫
let lastSyncTime = 0;
let syncDebounceDelay = 500; // 500ms防抖延遲
let lastSyncState = null; // 追蹤上次同步的狀態

// HTTP 同步相關變數
let httpSyncEnabled = false; // 是否啟用 HTTP 同步

    // 頁面載入時初始化currentUserId
    document.addEventListener('DOMContentLoaded', function() {
        currentUserId = getCurrentMemberId();
        console.log('初始化currentUserId:', currentUserId);
        

        
        // 自動檢查是否有已接受的邀請
        checkForAcceptedInvitations();
        
        // 延遲檢查並修復同步問題
        setTimeout(() => {
            if (gameMode === 'online' && invitationId) {
                console.log('檢查邀請同步狀態...');
                // 如果已經在遊戲中但狀態不正確，嘗試修復
                const gameContainer = document.getElementById('game-container');
                if (gameContainer && !gameContainer.classList.contains('hidden')) {
                    console.log('🎮 檢測到遊戲已開始，檢查同步狀態');
                    if (window.fixInvitationSync) {
                        window.fixInvitationSync();
                    }
                }
            }
        }, 2000); // 2秒後檢查
    
    // 添加頁面離開事件監聽器 - 暫時禁用
    const beforeUnloadHandler = function(e) {
        console.log('頁面離開檢測已禁用，避免誤判');
        return; // 暫時禁用頁面離開退出檢測
        
        if (gameMode === 'online' && invitationId && gameStartTimestamp && canFlip) {
            // 檢查遊戲是否已經真正開始且進行了一段時間（至少15秒）
            const gameDuration = Date.now() - gameStartTimestamp;
            if (gameDuration > 15000) {
                // 強制退出戰局
                forceQuitGame();
                
                // 顯示警告訊息
                e.preventDefault();
                e.returnValue = '您正在進行線上對戰，離開頁面將自動退出戰局。';
                return e.returnValue;
            } else {
                console.log('遊戲進行時間不足15秒，不觸發退出');
            }
        }
    };
    
    window.addEventListener('beforeunload', beforeUnloadHandler);
    
    // 提供清除 beforeunload 事件的函數
    window.clearBeforeUnload = function() {
        window.removeEventListener('beforeunload', beforeUnloadHandler);
        window.onbeforeunload = null;
    };
    
    // 添加頁面隱藏事件監聽器（手機切換應用程式時）- 暫時禁用
    let visibilityTimeout;
    document.addEventListener('visibilitychange', function() {
        console.log('頁面隱藏檢測已禁用，避免誤判');
        return; // 暫時禁用頁面隱藏退出檢測
        
        if (document.hidden && gameMode === 'online' && invitationId) {
            // 檢查遊戲是否已經真正開始且進行了一段時間（至少10秒）
            if (gameStartTimestamp && canFlip) {
                const gameDuration = Date.now() - gameStartTimestamp;
                if (gameDuration > 10000) { // 10秒
                    // 增加延遲到5秒，避免誤判
                    visibilityTimeout = setTimeout(() => {
                        console.log('頁面隱藏超過5秒，強制退出對戰');
                        forceQuitGame();
                    }, 5000);
                } else {
                    console.log('遊戲進行時間不足10秒，不觸發退出');
                }
            } else {
                console.log('遊戲尚未真正開始，不觸發退出');
            }
        } else if (!document.hidden && visibilityTimeout) {
            // 頁面重新可見時取消退出
            clearTimeout(visibilityTimeout);
            visibilityTimeout = null;
        }
    });
    
    
    // 添加強制隱藏所有視窗的函數
    window.forceHideAll = function() {
        console.log('強制隱藏所有視窗...');
        hideAllModals();
        
        // 額外強制隱藏所有可能的邀請視窗
        const allModals = document.querySelectorAll('.modal, [id*="modal"], [id*="invite"], [id*="friend"]');
        allModals.forEach(modal => {
            if (modal.classList.contains('hidden') === false) {
                modal.classList.add('hidden');
                console.log('強制隱藏視窗:', modal.id || modal.className);
            }
        });
        
        // 顯示遊戲界面
        const gameContainer = document.getElementById('game-container');
        if (gameContainer) {
            gameContainer.classList.remove('hidden');
        }
        
        console.log('所有視窗已隱藏，遊戲界面已顯示');
    };
});

// === 純 PHP + AJAX 邀請系統 ===
const playerName = localStorage.getItem('member_id') || '玩家';

// 檢查是否有邀請參數
const urlParams = new URLSearchParams(window.location.search);
const invitationParam = urlParams.get('invitation');
if (invitationParam) {
    // 如果有邀請參數，檢查邀請狀態
    checkInvitationStatus(invitationParam);
}

// 遊戲設置
const gameSettings = {
    easy: {
        gridSize: 3, // 4x3 = 12張卡片 (6對)
        timeLimit: 60,
        baseScore: 20
    },
    normal: {
        gridSize: 4, // 4x4 = 16張卡片 (8對)
        timeLimit: 120,
        baseScore: 50
    },
    hard: {
        gridSize: 8, // 8x4 = 32張卡片 (16對)
        timeLimit: 180,
        baseScore: 100
    }
};

// 使用從PHP傳來的資料更新設定（如果存在）
if (typeof difficulties !== 'undefined') {
difficulties.forEach(diff => {
    if (gameSettings[diff.difficulty_level]) {
        gameSettings[diff.difficulty_level] = {
            ...gameSettings[diff.difficulty_level],
            gridSize: diff.color_count,
            timeLimit: diff.time_limit,
            baseScore: diff.score_multiplier
        };
    }
});
}

// 使用從PHP傳來的顏色設定（如果存在）
const themeColors = {};
if (typeof colors !== 'undefined') {
colors.forEach(color => {
    if (!themeColors[color.difficulty_level]) {
        themeColors[color.difficulty_level] = {};
    }
    themeColors[color.difficulty_level][color.color_name] = color.color_code;
});
}

// 卡片符號
const symbols = {
    fruit: ['papaya.png', 'starfruit.png', 'passionfruit.png', 'durian.png', 'mangosteen.png', '🍎', '🍐', '🍊', '🍋', '🍌', '🍉', '🍇',
           '🍓', '🍈', '🍒', '🍑', '🥭', '🍍', '🥥', '🥝', '🍋‍🟩', '🍏', '🥑', '🫐'],
    animal: ['🐶', '🐱', '🐭', '🐹', '🐰', '🦊', '🐻', '🐼', '🐨', '🐯', '🦁', '🐮',
            '🐷', '🐸', '🐵', '🐔', '🐧', '🐦', '🦆', '🦅', '🦉', '🦇', '🐺', '🐗'],
    daily: ['⌚', '📱', '🧯', '📺', '🖥️', '🖨️', '🖱️', '🖊️', '💉', '🪥', '🧢', '🩴',
           '💿', '👓', '💰', '📷', '💡', '🔑', '📽️', '✂️', '📞', '☎️', '🔨', '🧦'],
    vegetable: ['green_pepper.png', 'white_radish.png', 'red_pepper.png', 'scallion.png', 'napa_cabbage.png', '🥬', '🥦', '🥒', '🌶️', '🌽', '🥕', '🧄',
               '🧅', '🥔', '🍅', '🥩', '🎃', '🫛', '🫑', '🧀', '🥚', '🫚', '🍄‍🟫', '🍆']
};

// 好友邀請功能 - 使用 AJAX
function inviteFriend(friendId, friendName) {
    console.log('=== 開始發送邀請 ===');
    console.log('發送邀請給:', friendName, 'ID:', friendId);
    console.log('當前用戶ID:', getCurrentMemberId());
    console.log('瀏覽器信息:', navigator.userAgent);
    console.log('當前時間:', new Date().toISOString());
    console.log('當前頁面URL:', window.location.href);
    console.log('當前域名:', window.location.origin);
    console.log('Cookie 是否啟用:', navigator.cookieEnabled);
    console.log('當前會話存儲:', sessionStorage.getItem('member_id'));
    console.log('本地存儲:', localStorage.getItem('member_id'));
    
    // 先清理任何現有的邀請檢查
    if (invitationCheckInterval) {
        clearInterval(invitationCheckInterval);
        invitationCheckInterval = null;
    }
    
    invitedFriendId = friendId;
    invitedFriendName = friendName;
    
    // 顯示等待視窗 - 添加元素存在性檢查
    const invitedFriendNameElement = document.getElementById('invited-friend-name');
    if (invitedFriendNameElement) {
        invitedFriendNameElement.textContent = friendName;
    } else {
        console.warn('找不到 invited-friend-name 元素');
    }
    
    const friendInviteModal = document.getElementById('friend-invite-modal');
    const waitingModal = document.getElementById('waiting-modal');
    
    if (friendInviteModal) {
        friendInviteModal.classList.add('hidden');
    } else {
        console.warn('找不到 friend-invite-modal 元素');
    }
    
    if (waitingModal) {
        waitingModal.classList.remove('hidden');
        // 設置初始等待訊息
        const waitingMessage = document.getElementById('waiting-message');
        if (waitingMessage) {
            waitingMessage.textContent = '邀請已發送，等待對方回應...';
        }
    } else {
        console.warn('找不到 waiting-modal 元素');
    }
    
    // 發送邀請到伺服器
    fetch('game-invitation-api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
        },
        credentials: 'include',
        body: JSON.stringify({
            action: 'send_invitation',
            from_user_id: getCurrentMemberId(),
            to_user_id: friendId,
            game_type: 'memory_game_2p'
        })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.json();
    })
    .then(data => {
        console.log('發送邀請回應:', data);
        
        if (data.success) {
            invitationId = data.invitation_id;
            console.log('邀請發送成功，ID:', invitationId);
            // 開始輪詢檢查邀請狀態
            startInvitationPolling(invitationId);
        } else {
            console.error('發送邀請失敗:', data.message);
            alert('發送邀請失敗：' + data.message);
            hideWaitingModal();
        }
    })
    .catch(error => {
        console.error('=== 發送邀請錯誤 ===');
        console.error('錯誤類型:', error.constructor.name);
        console.error('錯誤訊息:', error.message);
        console.error('錯誤堆疊:', error.stack);
        console.error('網絡狀態:', navigator.onLine);
        console.error('請求URL:', 'game-invitation-api.php');
        console.error('請求方法:', 'POST');
        console.error('請求憑證:', 'include');
        
        // 檢查是否是CORS錯誤
        if (error.message.includes('CORS') || error.message.includes('cors')) {
            console.error('檢測到CORS錯誤');
            alert('跨域請求錯誤，請檢查瀏覽器設置或嘗試使用其他瀏覽器');
        } else if (error.message.includes('fetch')) {
            console.error('檢測到網絡連接錯誤');
            alert('網絡連接錯誤，請檢查網絡連接後再試');
        } else {
            alert('發送邀請失敗：' + error.message);
        }
        hideWaitingModal();
    });
}

// 開始輪詢檢查邀請狀態
function startInvitationPolling(invitationId) {
    // 立即檢查一次
    checkInvitationStatus(invitationId);
    
    // 設置超時處理（5分鐘後自動停止）
    const timeout = setTimeout(() => {
        console.log('邀請檢查超時，停止輪詢');
        if (invitationCheckInterval) {
            clearInterval(invitationCheckInterval);
            invitationCheckInterval = null;
        }
        hideWaitingModal();
        alert('邀請超時，請重新發送邀請');
    }, 300000); // 5分鐘
    
    // 然後每500ms檢查一次，確保快速響應
    invitationCheckInterval = setInterval(() => {
        checkInvitationStatus(invitationId);
    }, 500);
    
    // 保存超時ID，以便在邀請成功時清除
    window.invitationTimeout = timeout;
}

// 檢查邀請狀態
function checkInvitationStatus(invitationId) {
    console.log('=== 檢查邀請狀態 ===');
    console.log('邀請ID:', invitationId);
    console.log('當前用戶ID:', getCurrentMemberId());
    console.log('瀏覽器信息:', navigator.userAgent);
    console.log('當前時間:', new Date().toISOString());
    
    fetch('game-invitation-api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
        },
        credentials: 'include',
        body: JSON.stringify({
            action: 'check_invitation',
            invitation_id: invitationId
        })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.json();
    })
    .then(data => {
        console.log('邀請狀態回應:', data);
        
        if (data.success) {
            // 存儲邀請數據
            invitationData = data.invitation;
            
            switch (data.status) {
                case 'accepted':
                    console.log('邀請已接受，處理下一步');
                    clearInterval(invitationCheckInterval);
                    // 清除超時
                    if (window.invitationTimeout) {
                        clearTimeout(window.invitationTimeout);
                        window.invitationTimeout = null;
                    }
                    hideWaitingModal();
                    
                    // 設定邀請數據
                    invitationData = data.invitation;
                    window.currentInvitation = data.invitation;
                    gameMode = 'online';
                    
                    // 檢查當前用戶是邀請者還是被邀請者
                    if (data.invitation.from_user_id == getCurrentMemberId()) {
                        // 邀請者：顯示主題選擇介面
                        console.log('邀請者：顯示主題選擇介面');
                        showThemeModal();
                    } else {
                        // 被邀請者：等待邀請者選擇主題
                        console.log('被邀請者：等待邀請者選擇主題');
                        document.getElementById('waiting-modal').classList.remove('hidden');
                        document.getElementById('waiting-title').textContent = '等待遊戲設定';
                        document.getElementById('waiting-message').textContent = '正在等待邀請者設定遊戲...';
                        
                        // 檢查元素是否存在再設定
                        const invitedFriendNameElement = document.getElementById('invited-friend-name');
                        if (invitedFriendNameElement) {
                            invitedFriendNameElement.textContent = data.invitation.from_user_name || '邀請者';
                        }
                        
                        // 開始檢查遊戲設定
                        startGameSettingsPolling(invitationId);
                    }
                    break;
                case 'rejected':
                    console.log('邀請被拒絕');
                    clearInterval(invitationCheckInterval);
                    // 清除超時
                    if (window.invitationTimeout) {
                        clearTimeout(window.invitationTimeout);
                        window.invitationTimeout = null;
                    }
                    hideWaitingModal();
                    // 重置邀請相關變數
                    invitationId = null;
                    invitationData = null;
                    invitedFriendId = null;
                    invitedFriendName = null;
                    showRejectModal();
                    break;
                case 'cancelled':
                    console.log('邀請已取消');
                    clearInterval(invitationCheckInterval);
                    // 清除超時
                    if (window.invitationTimeout) {
                        clearTimeout(window.invitationTimeout);
                        window.invitationTimeout = null;
                    }
                    hideWaitingModal();
                    alert('邀請已取消');
                    break;
                case 'expired':
                    console.log('邀請已過期');
                    clearInterval(invitationCheckInterval);
                    // 清除超時
                    if (window.invitationTimeout) {
                        clearTimeout(window.invitationTimeout);
                        window.invitationTimeout = null;
                    }
                    hideWaitingModal();
                    showExpiredModal();
                    break;
                case 'quit':
                    console.log('對手已退出');
                    clearInterval(invitationCheckInterval);
                    // 清除超時
                    if (window.invitationTimeout) {
                        clearTimeout(window.invitationTimeout);
                        window.invitationTimeout = null;
                    }
                    hideWaitingModal();
                    hideAllModals();
                    document.getElementById('player-quit-modal').classList.remove('hidden');
                    break;
                case 'pending':
                    console.log('邀請待處理中...');
                    break;
                default:
                    console.log('未知的邀請狀態:', data.status);
            }
        } else {
            console.error('檢查邀請狀態失敗:', data.message);
        }
    })
    .catch(error => {
        console.error('=== 檢查邀請狀態錯誤 ===');
        console.error('錯誤類型:', error.constructor.name);
        console.error('錯誤訊息:', error.message);
        console.error('錯誤堆疊:', error.stack);
        console.error('網絡狀態:', navigator.onLine);
        console.error('請求URL:', 'game-invitation-api.php');
        console.error('請求方法:', 'POST');
        console.error('請求憑證:', 'include');
        
        // 如果是網絡錯誤，可以考慮重試
        if (error.name === 'TypeError' || error.message.includes('fetch')) {
            console.log('網絡錯誤，將在下次輪詢時重試');
        }
        
        // 檢查是否是CORS錯誤
        if (error.message.includes('CORS') || error.message.includes('cors')) {
            console.error('檢測到CORS錯誤');
        }
    });
}

// 取消邀請
function cancelInvitation() {
    if (invitationId) {
        fetch('game-invitation-api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            credentials: 'include',
            body: JSON.stringify({
                action: 'cancel_invitation',
                invitation_id: invitationId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('邀請已取消');
                // 重置遊戲狀態
                gameMode = 'local';
                invitationId = null;
                invitationData = null;
                gameStartTimestamp = null;
                
                // 停止所有檢查
                clearInterval(invitationCheckInterval);
                
                // 隱藏等待視窗
                hideWaitingModal();
                
                // 顯示邀請好友視窗
                document.getElementById('friend-invite-modal').classList.remove('hidden');
            }
        })
        .catch(error => {
            console.error('取消邀請錯誤:', error);
        });
    } else {
        hideWaitingModal();
        document.getElementById('friend-invite-modal').classList.remove('hidden');
    }
}

// 顯示收到的邀請
function showReceivedInvitation(data) {
    document.getElementById('inviter-name').textContent = data.from_user_name || '好友';
    document.getElementById('received-invitation-modal').classList.remove('hidden');
    
    // 儲存邀請資料
    window.currentInvitation = data;
}

// 接受邀請
function acceptInvitation() {
    const currentInvitationId = window.currentInvitation?.invitation_id;
    if (!currentInvitationId) {
        alert('沒有邀請ID');
        return;
    }
    
    console.log('接受邀請:', currentInvitationId);
    
    fetch('game-invitation-api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
        },
        credentials: 'include',
        body: JSON.stringify({
            action: 'accept_invitation',
            invitation_id: currentInvitationId
        })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.json();
    })
    .then(data => {
        console.log('接受邀請回應:', data);
        
        if (data.success) {
            console.log('邀請已接受:', data);
            
            // 隱藏邀請視窗
            hideAllModals();
            
            // 額外確保隱藏所有可能的邀請視窗
            const allModals = document.querySelectorAll('.modal, [id*="modal"], [id*="invite"], [id*="friend"]');
            allModals.forEach(modal => {
                if (modal.classList.contains('hidden') === false) {
                    modal.classList.add('hidden');
                    console.log('接受邀請時強制隱藏視窗:', modal.id || modal.className);
                }
            });
            
            // 設定遊戲模式為線上
            gameMode = 'online';
            invitationId = currentInvitationId; // 更新全局變數
            
            // 設定玩家名稱 - 簡化邏輯
            const fromUserName = window.currentInvitation?.from_user_name || '邀請者';
            const currentUserDisplayName = typeof currentUserName !== 'undefined' && currentUserName && currentUserName !== '玩家' 
                ? currentUserName 
                : `玩家${getCurrentMemberId()}`;
            
            // 被邀請者：玩家1是好友，玩家2是自己
            player1Name = fromUserName;
            player2Name = currentUserDisplayName;
            
            console.log('設定玩家名字:', { 
                player1Name, player2Name, 
                currentUserDisplayName, 
                fromUserName
            });
            
            // 立即更新顯示
            updatePlayerDisplay();
            forceUpdatePlayerNames();
            
            // 顯示等待視窗
            const waitingModal = document.getElementById('waiting-modal');
            const waitingTitle = document.getElementById('waiting-title');
            const waitingMessage = document.getElementById('waiting-message');
            
            if (waitingModal) waitingModal.classList.remove('hidden');
            if (waitingTitle) waitingTitle.textContent = '等待遊戲設定';
            if (waitingMessage) waitingMessage.textContent = '正在等待邀請者設定遊戲...';
            
            // 開始檢查遊戲設定
            startGameSettingsPolling(currentInvitationId);
            
        } else {
            console.error('接受邀請失敗:', data.message);
            alert('接受邀請失敗：' + (data.message || '未知錯誤'));
        }
    })
    .catch(error => {
        console.error('接受邀請錯誤:', error);
        alert('接受邀請失敗，請檢查網絡連接後再試');
    });
}

// 拒絕邀請
function rejectInvitation() {
    if (window.currentInvitation) {
        fetch('game-invitation-api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            credentials: 'include',
            body: JSON.stringify({
                action: 'reject_invitation',
                invitation_id: window.currentInvitation.invitation_id
            })
        })
        .then(response => response.json())
        .then(data => {
            hideReceivedInvitationModal();
        })
        .catch(error => {
            console.error('拒絕邀請錯誤:', error);
            hideReceivedInvitationModal();
        });
    }
}

function hideWaitingModal() {
    const waitingModal = document.getElementById('waiting-modal');
    if (waitingModal) {
        waitingModal.classList.add('hidden');
    } else {
        console.warn('找不到 waiting-modal 元素');
    }
}

function hideReceivedInvitationModal() {
    document.getElementById('received-invitation-modal').classList.add('hidden');
}

// 顯示邀請過期視窗
function showExpiredModal() {
    document.getElementById('invitation-expired-modal').classList.remove('hidden');
}

// 隱藏邀請過期視窗
function hideExpiredModal() {
    document.getElementById('invitation-expired-modal').classList.add('hidden');
    // 回到好友邀請視窗
    document.getElementById('friend-invite-modal').classList.remove('hidden');
}

// 顯示退出對戰確認視窗
function showQuitModal() {
    document.getElementById('quit-game-modal').classList.remove('hidden');
}

// 隱藏退出對戰確認視窗
function hideQuitModal() {
    document.getElementById('quit-game-modal').classList.add('hidden');
}

// 強制退出對戰（不顯示確認視窗）
let isQuitting = false; // 防止重複退出
function forceQuitGame() {
    if (gameMode === 'online' && invitationId && !isQuitting) {
        isQuitting = true; // 設置退出標記
        console.log('強制退出對戰...');
        
        // 立即停止所有同步，避免重複退出
        if (gameSyncInterval) {
            clearInterval(gameSyncInterval);
            gameSyncInterval = null;
        }
        if (turnTimer) {
            clearInterval(turnTimer);
            turnTimer = null;
        }
        if (invitationCheckInterval) {
            clearInterval(invitationCheckInterval);
            invitationCheckInterval = null;
        }
        
        // 退出信號發送已完全禁用，避免誤判
        console.log('退出信號發送已完全禁用，避免誤判');
        /*
        // 使用 sendBeacon 確保在頁面關閉時也能發送請求
        const data = JSON.stringify({
            action: 'player_quit',
            invitation_id: invitationId,
            player_id: getCurrentMemberId()
        });
        
        // 優先使用 sendBeacon（頁面關閉時更可靠）
        if (navigator.sendBeacon) {
            navigator.sendBeacon('game-sync-api.php', data);
            console.log('使用 sendBeacon 發送退出請求');
        } else {
            // 備用方案：使用 fetch
            fetch('game-sync-api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: data
            })
            .then(response => response.json())
            .then(data => {
                console.log('強制退出對戰回應:', data);
            })
            .catch(error => {
                console.error('強制退出對戰錯誤:', error);
            });
        }
        
        // 立即發送退出信號，確保對手能快速檢測到
        const quitSignal = JSON.stringify({
            action: 'update_game_state',
            invitation_id: invitationId,
            player_id: getCurrentMemberId(),
            game_state: {
                lastAction: 'player_quit_signal',
                lastActionBy: getCurrentMemberId(),
                player_quit: true,
                currentPlayer: currentPlayer,
                isMyTurn: false
            }
        });
        
        if (navigator.sendBeacon) {
            navigator.sendBeacon('game-sync-api.php', quitSignal);
            console.log('使用 sendBeacon 發送退出信號');
        } else {
            fetch('game-sync-api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: quitSignal
            }).catch(error => {
                console.log('發送退出信號失敗:', error);
            });
        }
        */
    }
}

// 確認退出對戰
function confirmQuitGame() {
    if (gameMode === 'online' && invitationId) {
        console.log('開始退出對戰流程...');
        
        // 先停止所有同步，避免重複退出
        if (gameSyncInterval) {
            clearInterval(gameSyncInterval);
            gameSyncInterval = null;
        }
        if (turnTimer) {
            clearInterval(turnTimer);
            turnTimer = null;
        }
        
        // 隱藏退出確認視窗
        hideQuitModal();
        
        // 顯示自定義退出對戰視窗
        showExitBattleModal();
        
        // 退出信號發送已完全禁用，避免誤判
        console.log('退出信號發送已完全禁用，避免誤判');
        /*
        // 通知伺服器玩家退出
        fetch('game-sync-api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'player_quit',
                invitation_id: invitationId,
                player_id: getCurrentMemberId()
            })
        })
        .then(response => response.json())
        .then(data => {
            console.log('退出對戰回應:', data);
            if (data.success) {
                        // 立即發送一個特殊的同步信號，確保對手能快速檢測到退出
        fetch('game-sync-api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'update_game_state',
                invitation_id: invitationId,
                player_id: getCurrentMemberId(),
                game_state: {
                    lastAction: 'player_quit_signal',
                    lastActionBy: getCurrentMemberId(),
                    player_quit: true,
                    currentPlayer: currentPlayer,
                    isMyTurn: false
                }
            })
        }).catch(error => {
            console.log('發送退出信號失敗:', error);
        });
        
        // 立即發送第二次退出信號，確保對手能收到
        setTimeout(() => {
            fetch('game-sync-api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'update_game_state',
                    invitation_id: invitationId,
                    player_id: getCurrentMemberId(),
                    game_state: {
                        lastAction: 'player_quit_signal',
                        lastActionBy: getCurrentMemberId(),
                        player_quit: true,
                        currentPlayer: currentPlayer,
                        isMyTurn: false
                    }
                })
            }).catch(error => {
                console.log('發送第二次退出信號失敗:', error);
            });
        }, 100);
                */
        
        // 等待1秒，確保對手有時間收到退出通知
        setTimeout(() => {
            console.log('退出對戰完成，返回主選單');
            
            // 重置遊戲狀態
            gameMode = 'local';
            invitationId = null;
            invitationData = null;
            isQuitting = false;
            
            // 隱藏所有視窗
            hideAllModals();
            
            // 顯示自定義退出對戰視窗
            showExitBattleModal();
        }, 1000);
        /*
            } else {
                alert('退出對戰失敗：' + (data.message || '未知錯誤'));
            }
        })
        .catch(error => {
            console.error('退出對戰錯誤:', error);
            alert('退出對戰失敗，請稍後再試');
        });
        */
    } else {
        // 單人模式直接返回
        hideQuitModal();
        returnToMain();
    }
}

// 從退出返回主選單
function returnToMainFromQuit() {
    // 停止所有同步
    if (gameSyncInterval) {
        clearInterval(gameSyncInterval);
        gameSyncInterval = null;
    }
    if (turnTimer) {
        clearInterval(turnTimer);
        turnTimer = null;
    }
    
    // 清除 beforeunload 事件，避免瀏覽器警告
    if (typeof window.clearBeforeUnload === 'function') {
        window.clearBeforeUnload();
    }
    window.onbeforeunload = null;
    
    // 隱藏玩家退出視窗
    document.getElementById('player-quit-modal').classList.add('hidden');
    
    // 返回主選單
    returnToMain();
}

// 開始線上對戰
function startOnlineGame(data) {
    console.log('開始線上遊戲:', data);
    
    // 設定遊戲模式為線上
    gameMode = 'online';
    invitationId = data.invitation_id;
    invitationData = data;
    
    // 啟用 HTTP 同步
    httpSyncEnabled = true;
    
    // 初始化 HTTP 同步
    if (window.memoryGameHttpSync) {
        window.memoryGameHttpSync.init(invitationId, getCurrentMemberId());
        setupHttpSync();
    }
    
    // 確保翻牌權限啟用
    canFlip = true;
    
    // 設定玩家名稱
    const isInviter = getCurrentMemberId() == data.from_user_id;
    
    // 獲取當前用戶名
    let currentUserDisplayName = '玩家';
    if (typeof currentUserName !== 'undefined' && currentUserName && currentUserName !== '玩家') {
        currentUserDisplayName = currentUserName;
    } else {
        currentUserDisplayName = `玩家${getCurrentMemberId()}`;
    }
    
    if (isInviter) {
        // 邀請者：玩家1是自己，玩家2是好友
        player1Name = currentUserDisplayName;
        player2Name = data.from_user_name || '被邀請者';
    } else {
        // 被邀請者：玩家1是好友，玩家2是自己
        player1Name = data.from_user_name || '邀請者';
        player2Name = currentUserDisplayName;
    }
    
    console.log('設定玩家名字:', { 
        player1Name, player2Name, 
        currentUserDisplayName, 
        fromUserName: data.from_user_name, 
        isInviter 
    });
    
    // 立即更新顯示
    updatePlayerDisplay();
    forceUpdatePlayerNames();
    
    // 隱藏邀請視窗
    hideAllModals();
    
    // 額外確保隱藏所有可能的邀請視窗
    const allModals = document.querySelectorAll('.modal, [id*="modal"], [id*="invite"], [id*="friend"]');
    allModals.forEach(modal => {
        if (modal.classList.contains('hidden') === false) {
            modal.classList.add('hidden');
            console.log('開始線上遊戲時強制隱藏視窗:', modal.id || modal.className);
        }
    });
    
    if (isInviter) {
        // 邀請者：顯示主題選擇
        console.log('邀請者：顯示主題選擇');
        document.getElementById('theme-modal').classList.remove('hidden');
        
        // 邀請者也要等待被邀請者準備好
        console.log('邀請者：開始等待被邀請者準備');
        startGameSettingsPolling(invitationId);
    } else {
        // 被邀請者：顯示等待視窗
        console.log('被邀請者：顯示等待視窗');
        const waitingModal = document.getElementById('waiting-modal');
        const waitingTitle = document.getElementById('waiting-title');
        const waitingMessage = document.getElementById('waiting-message');
        
        if (waitingModal) waitingModal.classList.remove('hidden');
        if (waitingTitle) waitingTitle.textContent = '等待遊戲設定';
        if (waitingMessage) waitingMessage.textContent = '正在等待邀請者設定遊戲...';
        
        // 開始檢查遊戲設定
        startGameSettingsPolling(invitationId);
    }
}

function showPlayerSetupModal() {
    // 隱藏主題選擇視窗
    document.getElementById('theme-modal').classList.add('hidden');
    // 顯示玩家設定視窗
    document.getElementById('player-setup-modal').classList.remove('hidden');
}

function showHelp() {
    document.getElementById('help-modal').classList.remove('hidden');
}

// 返回主題選擇
function backToThemeSelection() {
    // 隱藏難度選擇視窗
    document.getElementById('difficulty-modal').classList.add('hidden');
    // 顯示主題選擇視窗
    document.getElementById('theme-modal').classList.remove('hidden');
}

// 返回邀請好友畫面
function backToInviteFriends() {
    // 檢查是否在線上模式且有邀請
    if (gameMode === 'online' && invitationId) {
        // 顯示自定義確認對話框
        showReturnConfirmModal();
    } else {
        // 隱藏主題選擇視窗
        document.getElementById('theme-modal').classList.add('hidden');
        // 顯示邀請好友視窗
        document.getElementById('friend-invite-modal').classList.remove('hidden');
    }
}

// 選擇主題
function selectTheme(theme) {
    currentTheme = theme;
    console.log('選擇主題:', theme, '遊戲模式:', gameMode, '邀請ID:', invitationId);
    
    // 更新卡片顏色（與單人模式一致）
    const themeData = themes.find(t => t.theme_name === theme);
    if (themeData) {
        const themeStyle = JSON.parse(themeData.theme_style);
        
        // 更新卡片顏色
        document.documentElement.style.setProperty('--card-back-color', themeStyle.cardBack);
        document.documentElement.style.setProperty('--card-front-color', themeStyle.cardFront);
        document.documentElement.style.setProperty('--matched-color', themeStyle.matched);
        document.documentElement.style.setProperty('--background-color', themeStyle.background);
        document.documentElement.style.setProperty('--container-color', themeStyle.container);
    }
    
    // 更新按鈕狀態
    document.querySelectorAll('.theme-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    document.querySelector(`.${theme}-theme`).classList.add('active');
    
    // 如果是線上模式，立即更新邀請設定
    if (gameMode === 'online' && invitationId) {
        updateInvitationSettings().then(() => {
            console.log('主題設定已同步');
            // 主題同步完成後才顯示難度選擇
            document.getElementById('theme-modal').classList.add('hidden');
            document.getElementById('difficulty-modal').classList.remove('hidden');
        }).catch(error => {
            console.error('同步主題設定失敗:', error);
            // 即使失敗也繼續到難度選擇
            document.getElementById('theme-modal').classList.add('hidden');
            document.getElementById('difficulty-modal').classList.remove('hidden');
        });
    } else {
        // 本地模式直接顯示難度選擇
        document.getElementById('theme-modal').classList.add('hidden');
        document.getElementById('difficulty-modal').classList.remove('hidden');
    }
    
    // 專門處理從其他頁面進入的同步問題
    window.fixInvitationSync = function() {
        console.log('🔧 修復邀請同步問題...');
        
        if (gameMode === 'online' && invitationId && invitationData) {
            // 強制重新解析遊戲設定
            try {
                const gameSettings = JSON.parse(invitationData.game_settings);
                currentTheme = gameSettings.theme || 'fruit';
                currentDifficulty = gameSettings.difficulty || 'easy';
                
                // 更新全域變數
                window.currentDifficulty = currentDifficulty;
                window.currentTheme = currentTheme;
                
                console.log('✅ 遊戲設定已重新同步:', { currentTheme, currentDifficulty });
                
                // 重新初始化遊戲
                if (typeof initializeGame === 'function') {
                    initializeGame();
                }
                
                // 強制同步到伺服器
                syncGameState('fix_sync');
                
                console.log('✅ 邀請同步修復完成');
            } catch (error) {
                console.error('❌ 解析遊戲設定失敗:', error);
            }
        } else {
            console.log('❌ 不是線上模式或缺少邀請數據');
        }
    };
}

// 選擇難度
function selectDifficulty(difficulty) {
    currentDifficulty = difficulty;
    console.log('選擇難度:', difficulty, '遊戲模式:', gameMode, '邀請ID:', invitationId);
    
    // 根據困難度調整遊戲板大小 - 修復困難度同步問題
    switch (difficulty) {
        case 'easy':
            gridSize = 4; // 4x3 = 12張卡片
            break;
        case 'normal':
            gridSize = 4; // 4x4 = 16張卡片
            break;
        case 'hard':
            gridSize = 8; // 8x4 = 32張卡片 - 修正為正確的困難模式
            break;
        default:
            gridSize = 4;
    }
    
    // 強制同步困難度設定到全局變數
    window.currentDifficulty = currentDifficulty;
    window.gridSize = gridSize;
    
    console.log('困難度設定已更新:', { difficulty, gridSize, currentDifficulty });
    
    // 強制同步困難度設定到全局變數
    window.currentDifficulty = currentDifficulty;
    window.gridSize = gridSize;
    
    // 強制同步到伺服器
    if (gameMode === 'online' && invitationId) {
        fetch('game-invitation-api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'update_invitation_status',
                invitation_id: invitationId,
                status: 'game_started',
                difficulty: difficulty,
                theme: currentTheme || 'fruit-theme'
            })
        })
        .then(response => response.json())
        .then(data => {
            console.log('✅ 困難度同步成功:', data);
        })
        .catch(error => {
            console.error('❌ 困難度同步失敗:', error);
        });
    }
    
    // 立即強制重新創建遊戲板以確保正確的網格布局
    setTimeout(() => {
        if (typeof createCards === 'function') {
            console.log('🔧 強制重新創建遊戲板，困難度:', currentDifficulty, 'gridSize:', gridSize);
            createCards();
            
            // 額外強制修復：確保困難模式顯示正確的網格
            if (difficulty === 'hard') {
                setTimeout(() => {
                    const gameBoard = document.getElementById('game-board');
                    if (gameBoard) {
                        gameBoard.style.display = 'grid';
                        gameBoard.style.gridTemplateColumns = 'repeat(8, 1fr)';
                        gameBoard.style.gridTemplateRows = 'repeat(4, 1fr)';
                        gameBoard.style.gap = '6px';
                        gameBoard.style.maxWidth = '1000px';
                        console.log('✅ 困難模式網格佈局已強制修復');
                    }
                }, 200);
            }
        }
    }, 100);
    
    console.log('困難度設定已應用,遊戲板大小已調整:', currentDifficulty, gridSize);
    
    // 立即同步困難度設定
    if (gameMode === 'online' && invitationId) {
        syncDifficultyImmediately();
        
        // 強制同步困難度到伺服器
        syncGameState('sync_difficulty');
        
        // 延遲再次同步，確保對手收到
        setTimeout(() => {
            syncDifficultyImmediately();
            syncGameState('sync_difficulty');
            console.log('延遲同步困難度設定');
        }, 1000);
        
        // 強制修復困難度同步
        forceFixDifficultySync();
    }
    
    // 測試卡片內容顯示
    window.testCardContent = function() {
        console.log('測試卡片內容顯示');
        
        const cards = document.querySelectorAll('.card');
        cards.forEach((card, index) => {
            const symbol = card.dataset.symbol || card.dataset.value;
            const cardFront = card.querySelector('.card-front');
            const isFlipped = card.classList.contains('flipped');
            
            console.log(`卡片 ${index}:`, {
                symbol: symbol,
                isFlipped: isFlipped,
                hasContent: cardFront ? cardFront.innerHTML.length > 0 : false,
                content: cardFront ? cardFront.innerHTML : 'N/A'
            });
            

        });
    };
    
    // 測試 symbols 定義
    window.testSymbols = function() {
        console.log('測試 symbols 定義');
        console.log('currentTheme:', currentTheme);
        console.log('symbols:', symbols);
        console.log('symbols[currentTheme]:', symbols[currentTheme]);
        
        if (symbols[currentTheme]) {
            console.log('當前主題的符號數量:', symbols[currentTheme].length);
            symbols[currentTheme].forEach((symbol, index) => {
                console.log(`符號 ${index}:`, symbol, '類型:', typeof symbol);
            });
        } else {
            console.error('找不到當前主題的符號:', currentTheme);
        }
    };
    
    // 強制修復網格布局
    window.forceFixGrid = function() {
        console.log('🔧 強制修復網格布局');
        const gameBoard = document.getElementById('game-board');
        if (!gameBoard) {
            console.error('找不到遊戲板');
            return;
        }
        
        const difficulty = window.currentDifficulty || 'normal';
        console.log('當前難度:', difficulty);
        
        if (difficulty === 'easy') {
            gameBoard.style.gridTemplateColumns = 'repeat(4, 1fr)';
            gameBoard.style.gridTemplateRows = 'repeat(3, 1fr)';
            console.log('✅ 已修復為簡單模式: 4x3');
        } else if (difficulty === 'normal') {
            gameBoard.style.gridTemplateColumns = 'repeat(4, 1fr)';
            gameBoard.style.gridTemplateRows = 'repeat(4, 1fr)';
            console.log('✅ 已修復為普通模式: 4x4');
        } else if (difficulty === 'hard') {
            gameBoard.style.gridTemplateColumns = 'repeat(8, 1fr)';
            gameBoard.style.gridTemplateRows = 'repeat(4, 1fr)';
            console.log('✅ 已修復為困難模式: 8x4');
        }
        
        // 檢查卡片數量
        const cards = gameBoard.querySelectorAll('.card');
        console.log('卡片數量:', cards.length);
    };
    
    // 強制同步翻牌狀態
    window.forceSyncCards = function() {
        console.log('🔧 強制同步翻牌狀態');
        
        // 同步所有翻開的卡片
        const flippedCards = document.querySelectorAll('.card.flipped');
        const matchedCards = document.querySelectorAll('.card.matched');
        
        console.log('當前翻開的卡片:', flippedCards.length);
        console.log('當前配對的卡片:', matchedCards.length);
        
        // 同步翻牌狀態到伺服器
        if (gameMode === 'online' && invitationId) {
            const gameState = {
                flippedCards: Array.from(flippedCards).map(card => card.dataset.index),
                matchedCards: Array.from(matchedCards).map(card => card.dataset.index),
                currentPlayer: currentPlayer,
                isMyTurn: isMyTurn
            };
            
            syncGameState('force_sync_cards');
            console.log('✅ 翻牌狀態已強制同步');
        }
    };
    
    // 緊急修復所有同步問題
    window.emergencyFixAll = function() {
        console.log('🚨 緊急修復所有同步問題');
        
        // 1. 修復網格布局
        forceFixGrid();
        
        // 2. 強制重新創建卡片
        const gameBoard = document.getElementById('game-board');
        if (gameBoard) {
            gameBoard.innerHTML = '';
            createCards();
            console.log('✅ 卡片已重新創建');
        }
        
        // 3. 強制同步狀態
        setTimeout(() => {
            forceSyncCards();
            console.log('✅ 狀態已強制同步');
        }, 500);
        
        // 4. 重置計時器
        stopTurnTimer();
        turnTimeLeft = 10;
        startTurnTimer();
        
        console.log('🚨 緊急修復完成');
    };
    
    // 超級修復函數 - 一次性解決所有問題
    window.superFix = function() {
        console.log('🔥 超級修復開始');
        
        // 1. 強制設定困難度
        window.currentDifficulty = 'hard';
        currentDifficulty = 'hard';
        
        // 2. 清空遊戲板
        const gameBoard = document.getElementById('game-board');
        if (gameBoard) {
            gameBoard.innerHTML = '';
            gameBoard.style.gridTemplateColumns = 'repeat(8, 1fr)';
            gameBoard.style.gridTemplateRows = 'repeat(4, 1fr)';
            gameBoard.style.maxWidth = '1000px';
            gameBoard.style.maxHeight = '500px';
            gameBoard.style.overflow = 'hidden';
        }
        
        // 3. 重新創建卡片
        createCards();
        
        // 4. 重置所有狀態
        flippedCards = [];
        matchedPairs = 0;
        player1Score = 0;
        player2Score = 0;
        player1Pairs = 0;
        player2Pairs = 0;
        currentPlayer = 1;
        isMyTurn = true;
        canFlip = true;
        
        // 5. 更新顯示
        updateScoreDisplay();
        updatePlayerDisplay();
        updateCurrentPlayer();
        
        // 6. 重置計時器
        stopTurnTimer();
        turnTimeLeft = 10;
        startTurnTimer();
        
        // 7. 強制同步
        if (gameMode === 'online' && invitationId) {
            syncGameState('super_fix');
        }
        
        console.log('🔥 超級修復完成');
    };
    
    // 專門修復從其他頁面進入的同步問題
    window.fixInvitationJoin = function() {
        console.log('🔧 修復從其他頁面進入的同步問題...');
        
        if (gameMode === 'online' && invitationId && invitationData) {
            try {
                // 1. 重新解析遊戲設定
                const gameSettings = JSON.parse(invitationData.game_settings);
                currentTheme = gameSettings.theme || 'fruit';
                currentDifficulty = gameSettings.difficulty || 'easy';
                
                // 2. 更新全域變數
                window.currentDifficulty = currentDifficulty;
                window.currentTheme = currentTheme;
                
                // 3. 重新初始化遊戲
                if (typeof initializeGame === 'function') {
                    initializeGame();
                }
                
                // 4. 強制同步到伺服器
                syncGameState('invitation_join_fix');
                
                // 5. 延遲再次同步確保狀態正確
                setTimeout(() => {
                    syncGameState('invitation_join_confirm');
                }, 1000);
                
                console.log('✅ 從其他頁面進入的同步問題修復完成');
            } catch (error) {
                console.error('❌ 修復失敗:', error);
            }
        } else {
            console.log('❌ 不是線上模式或缺少邀請數據');
        }
    };
    
    // 專門修復翻牌同步問題
    window.fixFlipSync = function() {
        console.log('🔄 修復翻牌同步問題...');
        
        if (gameMode === 'online' && invitationId) {
            // 1. 強制同步當前遊戲狀態
            syncGameState('force_sync');
            
            // 2. 延遲再次同步確保狀態正確
            setTimeout(() => {
                syncGameState('flip_sync_fix');
            }, 500);
            
            // 3. 再次延遲同步
            setTimeout(() => {
                syncGameState('flip_sync_confirm');
            }, 1000);
            
            console.log('✅ 翻牌同步修復完成');
        } else {
            console.log('❌ 不是線上模式');
        }
    };
    
    // 如果是線上模式，立即更新邀請設定
    if (gameMode === 'online' && invitationId) {
        updateInvitationSettings().then(() => {
            console.log('難度設定已同步');
            
            // 隱藏所有相關視窗
            const themeModal = document.getElementById('theme-modal');
            const difficultyModal = document.getElementById('difficulty-modal');
            const inviteModal = document.getElementById('invite-friend-modal');
            const waitingModal = document.getElementById('waiting-modal');
            const receivedModal = document.getElementById('received-invitation-modal');
            const playerSetupModal = document.getElementById('player-setup-modal');
            
            if (themeModal) themeModal.classList.add('hidden');
            if (difficultyModal) difficultyModal.classList.add('hidden');
            if (inviteModal) inviteModal.classList.add('hidden');
            if (waitingModal) waitingModal.classList.add('hidden');
            if (receivedModal) receivedModal.classList.add('hidden');
            if (playerSetupModal) playerSetupModal.classList.add('hidden');
            
            // 顯示遊戲界面
            const gameContainer = document.getElementById('game-container');
            if (gameContainer) {
                gameContainer.classList.remove('hidden');
            }
            
            // 設定完成後開始遊戲
            startGame();
            
            // 強制重新整理確保困難度正確應用
            setTimeout(() => {
                forceRefreshGameBoard();
                console.log('困難度設定已強制應用:', currentDifficulty);
            }, 300);
            
            // 強制同步邀請狀態，確保對方看到等待畫面
            setTimeout(() => {
                console.log('🔧 強制同步邀請狀態...');
                fetch('game-invitation-api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    credentials: 'include',
                    body: JSON.stringify({
                        action: 'update_invitation_status',
                        invitation_id: invitationId,
                        status: 'game_started',
                        difficulty: currentDifficulty,
                        theme: currentTheme
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        console.log('✅ 邀請狀態已強制同步，對方應該看到等待畫面');
                    } else {
                        console.error('邀請狀態同步失敗:', data.message);
                    }
                })
                .catch(error => {
                    console.error('邀請狀態同步請求失敗:', error);
                });
            }, 500);
            
            // 通知被邀人遊戲設定已完成
            console.log('主邀人通知被邀人遊戲設定已完成');
            // 被邀人會通過輪詢檢查到邀請狀態變化
        }).catch(error => {
            console.error('同步難度設定失敗:', error);
            // 即使失敗也嘗試開始遊戲
            startGame();
        });
    } else {
        // 本地模式直接開始遊戲
        startGame();
    }
}



// 初始化遊戲
function initializeGame() {
    // 重置遊戲狀態
    cards = [];
    flippedCards = [];
    matchedPairs = 0;
    totalMoves = 0;
    
    // 設置翻牌權限 - 修復翻牌權限問題
    if (gameMode === 'online') {
        // 線上模式：根據回合狀態設置
        canFlip = isMyTurn;
        console.log('線上模式初始化 - canFlip:', canFlip, 'isMyTurn:', isMyTurn);
        
        // 強制修復翻牌權限
        if (typeof isMyTurn === 'undefined' || isMyTurn === null) {
            isMyTurn = true;
    canFlip = true;
            console.log('強制修復翻牌權限 - isMyTurn:', isMyTurn, 'canFlip:', canFlip);
        }
    } else {
        // 單人模式：始終可以翻牌
        canFlip = true;
        console.log('單人模式初始化 - canFlip: true');
    }
    
    // 強制同步翻牌權限到全局變數
    window.canFlip = canFlip;
    window.isMyTurn = isMyTurn;
    
    // 線上模式保持回合設置，本地模式重置為玩家1
    if (gameMode !== 'online') {
    currentPlayer = 1;
    }
    
    player1Score = 0;
    player2Score = 0;
    player1Pairs = 0;
    player2Pairs = 0;
    consecutiveMatches = 0;
    
    // 確保遊戲開始時所有狀態都是重置的
    console.log('初始化遊戲 - 重置所有狀態');
    
    // 使用已設定的玩家名稱（如果沒有設定則使用預設值）
    // 只有在線上模式且玩家名字未設定時才設置預設值
    if (gameMode === 'online') {
        // 線上模式：保持已設定的玩家名字
        console.log('線上模式 - 保持玩家名字:', { player1Name, player2Name });
    } else {
        // 本地模式：設置預設玩家名字
        if (!player1Name || player1Name === '玩家 1') {
            player1Name = typeof currentUserName !== 'undefined' ? currentUserName : '玩家 1';
        }
        if (!player2Name || player2Name === '玩家 2') {
            player2Name = invitedFriendName || '玩家 2';
        }
    }
    
    console.log('初始化遊戲 - 玩家名字:', { player1Name, player2Name, currentUserName, invitedFriendName });
    
    // 更新顯示
    updatePlayerDisplay();
    
    // 強制更新玩家名字顯示
    setTimeout(() => {
        updatePlayerDisplay();
        // 再次確保玩家名字正確顯示
        forceUpdatePlayerNames();
    }, 100);
    document.getElementById('total-moves').textContent = '0';
    updateCurrentPlayer();
    
    // 如果是本地模式或線上模式且是我的回合，開始計時器（避免重複啟動）
    if (gameMode === 'local' || (gameMode === 'online' && isMyTurn)) {
        console.log('檢查是否需要開始回合計時器，遊戲模式:', gameMode, '我的回合:', isMyTurn, '計時器狀態:', turnTimer ? '運行中' : '未運行');
        // 只有在計時器未運行時才啟動
        if (!turnTimer) {
            setTimeout(() => {
                startTurnTimer();
            }, 100);
        }
    } else {
        console.log('不開始計時器，遊戲模式:', gameMode, '我的回合:', isMyTurn);
    }
   
    // 清空遊戲板
    const gameBoard = document.getElementById('game-board');
    gameBoard.innerHTML = '';
   
    // 創建卡片
    createCards();
    
    // 調整遊戲板大小
    adjustGameBoardSize();
    
    // 強制重新整理確保困難度正確應用
    setTimeout(() => {
        forceRefreshGameBoard();
    }, 100);
    
    // 強制確保所有卡片都是蓋著的
    cards.forEach(card => {
        card.classList.remove('flipped');
        card.classList.remove('matched');
    });
    
    // 確保退出按鈕在線上模式時顯示
    const quitBtn = document.getElementById('quitBtn');
    if (quitBtn) {
        if (gameMode === 'online') {
            quitBtn.style.display = 'inline-block';
        } else {
            quitBtn.style.display = 'none';
        }
    }
    
    console.log('遊戲初始化完成，所有卡片已蓋著');
}

// 創建卡片
function createCards() {
    const gameBoard = document.getElementById('game-board');
    
    // 強制確保困難度設定正確
    const difficulty = window.currentDifficulty || currentDifficulty || 'normal';
    console.log('🔧 createCards 函數中的困難度:', difficulty);
    
    // 根據難度設置正確的卡片數量
    let totalCards, rows, cols;
    if (difficulty === 'easy') {
        totalCards = 12; // 4x3 = 12張卡片 (6對)
        rows = 3;
        cols = 4;
    } else if (difficulty === 'normal') {
        totalCards = 16; // 4x4 = 16張卡片 (8對)
        rows = 4;
        cols = 4;
    } else if (difficulty === 'hard') {
        totalCards = 32; // 8x4 = 32張卡片 (16對)
        rows = 4;
        cols = 8;
    } else {
        // 預設值
        totalCards = 12;
        rows = 3;
        cols = 4;
    }
    
    // 強制同步困難度到全域變數
    window.currentDifficulty = difficulty;
    window.gridSize = cols;
    
    // 更新gridSize為實際的列數
    gridSize = cols;
    

    
    // 確保卡片顯示正確的圖示
    console.log('🎨 卡片圖示設定完成，使用主題:', currentTheme);
    
    console.log('創建卡片:', {
        difficulty: window.currentDifficulty,
        totalCards: totalCards,
        rows: rows,
        cols: cols,
        pairs: totalCards / 2
    });
    
    console.log('創建卡片時的主題設置:', {
        currentTheme: currentTheme,
        availableThemes: Object.keys(symbols),
        symbolsForTheme: symbols[currentTheme] ? symbols[currentTheme].length : 'undefined'
    });
    
    const symbolsForGame = symbols[currentTheme].slice(0, totalCards / 2);
    const cardSymbols = [...symbolsForGame, ...symbolsForGame];
    
    // 在線上模式下，使用邀請ID作為隨機種子，確保兩個玩家看到相同的排列
    if (gameMode === 'online' && invitationId) {
        // 使用邀請ID的哈希值作為隨機種子
        let seed = 0;
        for (let i = 0; i < invitationId.length; i++) {
            seed += invitationId.charCodeAt(i);
        }
        seed = seed % 10000; // 限制範圍
        
        // 使用種子進行確定性洗牌
        console.log('線上模式使用確定性洗牌，種子:', seed);
        shuffleArrayWithSeed(cardSymbols, seed);
    } else {
        // 本地模式使用隨機洗牌
    shuffleArray(cardSymbols);
    }

    // 設定網格佈局（根據難度動態調整視窗大小）
    gameBoard.style.display = 'grid';
    gameBoard.style.gap = '10px';
    gameBoard.style.width = '100%';
    

    
    // 根據難度動態調整視窗大小 - 參考單人模式的計算方式
    const gameContainer = document.getElementById('game-container');
    if (gameContainer) {
        let maxCardSize;
        if (difficulty === 'easy') {
            maxCardSize = 120;
        } else if (difficulty === 'normal') {
            maxCardSize = 90;
        } else if (difficulty === 'hard') {
            maxCardSize = 90;
        }
        
        const gap = 10; // px
        const containerWidth = gameContainer.clientWidth || 800;
        
        // 使用正確的列數和行數計算
        let actualCols, actualRows;
        if (difficulty === 'easy') {
            actualCols = 4; actualRows = 3;
        } else if (difficulty === 'normal') {
            actualCols = 4; actualRows = 4;
        } else if (difficulty === 'hard') {
            actualCols = 8; actualRows = 4;
        } else {
            actualCols = cols; actualRows = rows;
        }
        
        const maxBoardWidth = Math.min(containerWidth - 100, actualCols * maxCardSize + (actualCols - 1) * gap);
        const cardSize = Math.floor((maxBoardWidth - (actualCols - 1) * gap) / actualCols);
        
        // 設定 CSS Grid 佈局
        gameBoard.style.gridTemplateColumns = `repeat(${actualCols}, 1fr)`;
        gameBoard.style.gridTemplateRows = `repeat(${actualRows}, 1fr)`;
        
        // 設定遊戲板寬高
        const boardWidth = cardSize * actualCols + (actualCols - 1) * gap;
        const boardHeight = cardSize * actualRows + (actualRows - 1) * gap;
        
        gameBoard.style.width = boardWidth + 'px';
        gameBoard.style.height = boardHeight + 'px';
        gameBoard.style.maxWidth = 'none';
        gameBoard.style.maxHeight = 'none';
        gameBoard.style.overflow = 'hidden';
        gameBoard.style.margin = '0 auto';
        
        // 設定容器寬度，確保能容納所有卡片
        gameContainer.style.width = (boardWidth + 200) + 'px';
        gameContainer.style.maxWidth = 'none';
        gameContainer.style.minHeight = (boardHeight + 300) + 'px';
        gameContainer.style.padding = '30px';
        
        console.log(`✅ 動態調整視窗大小: ${difficulty}模式, 寬度:${boardWidth}px, 高度:${boardHeight}px, 卡片大小:${cardSize}px`);
        
        // 延遲調用動態調整函數確保所有元素都已渲染
        setTimeout(() => {
            if (window.adjustWindowSize) {
                window.adjustWindowSize();
            }
        }, 100);
    }
    
    console.log('✅ 動態視窗佈局已設定:', { 
        difficulty: difficulty,
        cols: cols, 
        rows: rows, 
        gridTemplateColumns: gameBoard.style.gridTemplateColumns, 
        gridTemplateRows: gameBoard.style.gridTemplateRows,
        maxWidth: gameBoard.style.maxWidth,
        containerMaxWidth: gameContainer ? gameContainer.style.maxWidth : 'N/A'
    });
    
    // 創建卡片元素（參考 phptest 的簡潔實現）
    cardSymbols.forEach((symbol, index) => {
        const card = document.createElement('div');
        card.className = 'card';
        card.dataset.index = index;
        card.dataset.value = symbol;
        
        // 設定卡片內容 - 參考單人模式的實現
        const isImage = symbol.includes('.png') || symbol.includes('.jpg') || symbol.includes('.jpeg');
        
        if (isImage) {
            // 如果是圖片，使用img標籤
            console.log('創建圖片卡片:', symbol, '路徑:', `img/${symbol}`);
            card.innerHTML = `
                <div class="card-front">
                    <img src="img/${symbol}" alt="${symbol}" style="width: 70%; height: 70%; object-fit: contain; margin: auto; display: block;" onerror="console.error('圖片載入失敗:', this.src)">
                </div>
                <div class="card-back"></div>
            `;
        } else {
            // 如果是emoji，直接顯示
            card.innerHTML = `
                <div class="card-front" style="font-size: 5.5rem; display: flex; align-items: center; justify-content: center;">${symbol}</div>
                <div class="card-back"></div>
            `;
        }
        
        // 儲存卡片符號以便翻牌時使用
        card.dataset.symbol = symbol;
        card.dataset.value = symbol; // 確保兩個屬性都設定
        
        // 確保卡片一開始是蓋著的，並且有正確的內容
        card.classList.remove('flipped');
        card.classList.remove('matched');
        
        // 確保卡片一開始是蓋著的
        card.classList.remove('flipped');
        card.classList.remove('matched');
        
        card.addEventListener('click', () => {
        if (!canFlip || !isMyTurn) {
            console.log('無法翻牌：', { canFlip, isMyTurn });
            return;
        }

        const cardIndex = parseInt(card.dataset.index);
        
        // 檢查卡片是否已經翻開或配對（參考 phptest 的檢查邏輯）
        if (card.classList.contains('flipped') || card.classList.contains('matched')) {
            console.log('卡片已翻開或配對，無法再次點擊');
            return;
        }
        
        // 檢查是否已經翻開了兩張卡片
        if (flippedCards.length >= 2) {
            console.log('已經翻開了兩張卡片，等待配對檢查');
            return;
        }
        
        console.log('翻牌：', { cardIndex, symbol: card.dataset.value });
        
        // 使用 HTTP 同步翻牌
        if (httpSyncEnabled && window.memoryGameHttpSync && gameMode === 'online') {
            window.memoryGameHttpSync.flipCard(cardIndex, card.dataset.value).then(success => {
                if (success) {
                    card.classList.add('flipped');
                    card.textContent = card.dataset.value;
                    flippedCards.push(card);
                    
                    if (flippedCards.length === 2) {
                        setTimeout(checkMatchSyncWithHttp, 500);
                    }
                }
            });
        } else {
            // 本地模式或 HTTP 同步未啟用
            card.classList.add('flipped');
            card.textContent = card.dataset.value;
            flippedCards.push(card);
            
            // 同步到伺服器
            if (gameMode === 'online' && invitationId) {
                syncGameState();
            }
            
            // 檢查是否翻開了兩張卡片
            if (flippedCards.length === 2) {
                setTimeout(checkMatchSync, 500);
            }
        }
    });
        gameBoard.appendChild(card);
        cards.push(card);
    });
    
    console.log('✅ 卡片創建完成，總共', cards.length, '張卡片');
    
    // 設定動態視窗屬性
    gameBoard.className = 'game-board';
    gameBoard.setAttribute('data-difficulty', difficulty);
    
    // 同時設定遊戲容器的困難度屬性
    if (gameContainer) {
        gameContainer.setAttribute('data-difficulty', difficulty);
    }
    
    console.log('✅ 動態視窗屬性已設定:', difficulty);
    
    // 調整遊戲板大小
    setTimeout(adjustGameBoardSize, 0);
    
    // 強制確保網格佈局正確
    console.log('🎯 最終網格佈局確認:', {
        difficulty: difficulty,
        cols: cols,
        rows: rows,
        totalCards: totalCards,
        actualCards: cards.length,
        gridTemplateColumns: gameBoard.style.gridTemplateColumns,
        gridTemplateRows: gameBoard.style.gridTemplateRows
    });
}

// 創建單張卡片
function createCard(symbol, index) {
    const card = document.createElement('div');
    card.className = 'card';
    card.dataset.symbol = symbol;
    card.dataset.index = index;
    
    // 確保符號是有效的
    if (!symbol) {
        console.error('創建卡片時符號為空:', { index, symbol });
        symbol = '❓'; // 預設符號
    }
    
    // 檢查是否為圖片檔案（包含.png副檔名）
    const isImage = symbol.includes('.png');
    
    if (isImage) {
        // 如果是圖片，使用img標籤
        console.log('創建圖片卡片:', symbol, '路徑:', `img/${symbol}`);
        card.innerHTML = `
            <div class="card-front">
                <img src="img/${symbol}" alt="${symbol}" style="width: 100%; height: 100%; object-fit: contain;" onerror="console.error('圖片載入失敗:', this.src)">
            </div>
            <div class="card-back"></div>
        `;
    } else {
        // 如果是emoji，直接顯示
        card.innerHTML = `
            <div class="card-front">${symbol}</div>
            <div class="card-back"></div>
        `;
    }
    
    // 確保卡片一開始是蓋著的（不顯示符號）
    card.classList.remove('flipped');
    card.classList.remove('matched');
   
    card.addEventListener('click', () => {
        console.log('卡片被點擊:', { 
            index: card.dataset.index, 
            symbol: card.dataset.symbol,
            canFlip: canFlip,
            isMyTurn: isMyTurn,
            gameMode: gameMode,
            currentPlayer: currentPlayer
        });
        
        // 強制修復翻牌權限
        if (!canFlip) {
            console.log('檢測到canFlip為false，強制修復...');
            const currentIsInviter = getCurrentMemberId() == invitationData?.from_user_id;
            isMyTurn = (currentPlayer === 1 && currentIsInviter) || (currentPlayer === 2 && !currentIsInviter);
            canFlip = isMyTurn || gameMode === 'local';
            console.log('強制修復後:', { canFlip, isMyTurn, gameMode });
        }
        
        // 強制執行翻牌（不管狀態如何）
        console.log('強制執行翻牌...');
        forceFlipCard(card);
    });
    
    console.log('創建卡片:', { index, symbol, cardElement: card });
    return card;
}

// 翻牌
function flipCard(card) {
    console.log('嘗試翻牌:', card.dataset.index, card.dataset.symbol);
    
    // 檢查翻牌條件
    if (card.classList.contains('flipped') || card.classList.contains('matched')) {
        console.log('翻牌被阻止: 卡片已翻開或已配對');
        return;
    }
    
    // 檢查翻牌權限
    if (!canFlip) {
        console.log('翻牌被阻止: canFlip = false，嘗試修復...');
        
        // 強制修復翻牌權限
        const currentIsInviter = getCurrentMemberId() == invitationData?.from_user_id;
        isMyTurn = (currentPlayer === 1 && currentIsInviter) || (currentPlayer === 2 && !currentIsInviter);
        canFlip = isMyTurn;
        
        console.log('強制修復翻牌權限:', { currentPlayer, currentIsInviter, isMyTurn, canFlip });
        
        if (!canFlip) {
            console.log('修復失敗，仍然無法翻牌');
            return;
        }
    }
    
    // 線上模式檢查回合
    if (gameMode === 'online') {
        if (!isMyTurn) {
            console.log('不是你的回合，無法翻牌');
            return;
        }
        
        // 檢查翻牌權限
        if (!canFlip) {
            console.log('翻牌權限被禁用，嘗試修復...');
            canFlip = true;
            console.log('已修復翻牌權限');
        }
        
        // 如果正在檢查配對，暫時阻止翻牌
        if (window.isCheckingMatch) {
            console.log('正在檢查配對中，無法翻牌');
            return;
        }
    } else {
        // 單人模式或離線模式，確保可以翻牌
        canFlip = true;
    }
    
    // 立即更新本地狀態
    card.classList.add('flipped');
    flippedCards.push(card);
    
    // 設定卡片內容

    
    console.log('翻牌成功:', card.dataset.symbol, '當前翻牌數量:', flippedCards.length);
    
    // 翻牌後立即禁用翻牌權限，防止對方翻牌時自己也能翻
    canFlip = false;
    console.log('翻牌後禁用翻牌權限，等待配對檢查或回合切換');
    
    // 線上模式同步到伺服器
    if (gameMode === 'online' && invitationId) {
        // 使用 WebSocket 同步
        if (window.memoryGameWebSocket && window.memoryGameWebSocket.isConnected) {
            window.memoryGameWebSocket.send('flip_card', {
                game_id: invitationId,
                player_id: getCurrentMemberId(),
                index: parseInt(card.dataset.index),
                value: card.dataset.symbol
            });
        } else {
            // 如果 WebSocket 未連接，使用傳統 API
        fetch('game-sync-api.php', {
            method: 'POST',
                headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                    action: 'flip_card_immediate',
                invitation_id: invitationId,
                player_id: getCurrentMemberId(),
                    card_index: parseInt(card.dataset.index),
                    card_symbol: card.dataset.symbol
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('翻牌同步成功');
            } else {
                console.error('翻牌同步失敗:', data.message);
            }
        })
            .catch(error => console.error('翻牌同步錯誤:', error));
        }
    }
    
    // 處理翻牌後的邏輯
    handleFlipAfterSync();
}

// 新增：處理翻牌後的邏輯
function handleFlipAfterSync() {
    // 如果是第一次翻牌，開始計時器（避免重複啟動）
    if (flippedCards.length === 1 && !isTurnActive && !turnTimer) {
        startTurnTimer();
    }
    
    // 檢查配對
    if (flippedCards.length === 2) {
        canFlip = false;
        totalMoves++;
        document.getElementById('total-moves').textContent = totalMoves;
        
        setTimeout(() => {
            checkMatchSync();
        }, 1200);
    }
}

// 新增：翻牌函數（用於 WebSocket 事件）
function flipCard(index, value) {
    const card = document.querySelector(`[data-index="${index}"]`);
    if (card && !card.classList.contains('flipped') && !card.classList.contains('matched')) {
        card.classList.add('flipped');
        
        // 如果是對手的翻牌，加入翻牌陣列
        if (!isMyTurn && !flippedCards.includes(card)) {
            flippedCards.push(card);
        }
        
        console.log('WebSocket 翻牌成功:', index, value);
    }
}

// 新增：蓋回卡片函數
function unflipCards(indices) {
    indices.forEach(index => {
        const card = document.querySelector(`[data-index="${index}"]`);
        if (card && !card.classList.contains('matched')) {
            card.classList.remove('flipped');
        }
    });
    flippedCards = [];
    console.log('卡片已蓋回:', indices);
}

// 新增：更新分數函數
function updateScore(playerId, score) {
    const isInviter = getCurrentMemberId() == invitationData?.from_user_id;
    if (playerId == invitationData?.from_user_id) {
        // 邀請者
        player1Score = score;
        document.getElementById('player1-score').textContent = score;
    } else {
        // 被邀請者
        player2Score = score;
        document.getElementById('player2-score').textContent = score;
    }
    console.log('分數已更新:', playerId, score);
}

// 新增：將翻牌動作發送給伺服器
function syncCardFlipToServer(cardIndex) {
    fetch('game-sync-api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'update_game_state',
            invitation_id: invitationId,
            game_state: {
                lastAction: 'flip_card',
                lastActionBy: getCurrentMemberId(),
                card_index: cardIndex,
                currentPlayer: currentPlayer,
                flippedCards: flippedCards.map(card => card.dataset.index)
            }
        })
    })
    .then(response => response.json())
    .then(data => {
        console.log('翻牌同步成功:', data);
    })
    .catch(error => {
        console.error('翻牌同步失敗:', error);
    });
}

// 新增：將配對結果發送給伺服器
function sendMatchResultToServer(isMatch) {
    fetch('game-sync-api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'update_game_state',
            invitation_id: invitationId,
            game_state: {
                lastAction: 'check_match',
                lastActionBy: getCurrentMemberId(),
                is_match: isMatch,
                flipped_card_indexes: flippedCards.map(card => card.dataset.index),
                currentPlayer: currentPlayer,
                matchedPairs: matchedPairs,
                player1Score: player1Score,
                player2Score: player2Score,
                player1Pairs: player1Pairs,
                player2Pairs: player2Pairs
            }
        })
    })
    .then(response => response.json())
    .then(data => {
        console.log('配對結果同步成功:', data);
    })
    .catch(error => {
        console.error('配對結果同步失敗:', error);
    });
}

// 檢查配對（參考 phptest 的簡潔實現）
function checkMatchSync() {
    // 確保有兩張翻開的卡片
    if (flippedCards.length !== 2) {
        console.error('翻開的卡片數量不正確:', flippedCards.length);
        flippedCards = [];
        return;
    }
    
    const card1 = flippedCards[0];
    const card2 = flippedCards[1];
    const match = card1.dataset.value === card2.dataset.value;
    
    console.log('配對檢查:', card1.dataset.value, card2.dataset.value, '結果:', match);
    
    if (match) {
        // 配對成功（參考 phptest 的邏輯）
        card1.classList.add('matched');
        card2.classList.add('matched');
        matchedPairs++;
        
        // 更新分數
        if (currentPlayer === 1) {
            player1Score += 10;
            player1Pairs++;
        } else {
            player2Score += 10;
            player2Pairs++;
        }
        
        console.log('配對成功！繼續當前玩家回合');
        // 配對成功，保持當前玩家回合
        // 同步到伺服器
        if (gameMode === 'online' && invitationId) {
            // 確保同步包含翻開的卡片索引
            const flippedCardIndexes = flippedCards.map(card => card.dataset.index);
            console.log('配對成功，同步翻開的卡片索引:', flippedCardIndexes);
            syncGameState('match_success');
        }
        
        flippedCards = [];
        
        // 檢查是否遊戲結束
        checkWin();
    } else {
        // 配對失敗（參考 phptest 的邏輯）
        console.log('配對失敗，2秒後蓋回並切換回合');
        
        setTimeout(() => {
            card1.classList.remove('flipped');
            card2.classList.remove('flipped');
            
            flippedCards = [];
            
            // 切換回合
            switchTurn();
            
            // 同步到伺服器
            if (gameMode === 'online' && invitationId) {
                syncGameState('match_fail');
            }
        }, 2000);
    }
}

// 切換回合（參考 phptest 的簡潔實現）
function switchTurn() {
    console.log('切換回合，當前玩家:', currentPlayer);
    
    // 切換玩家
    currentPlayer = currentPlayer === 1 ? 2 : 1;
    
    // 更新翻牌權限
    if (gameMode === 'online') {
        const currentIsInviter = getCurrentMemberId() == invitationData?.from_user_id;
        isMyTurn = (currentPlayer === 1 && currentIsInviter) || (currentPlayer === 2 && !currentIsInviter);
        canFlip = isMyTurn;
    } else {
        canFlip = true;
    }
    
    // 同步到全域變數
    window.currentPlayer = currentPlayer;
    window.isMyTurn = isMyTurn;
    window.canFlip = canFlip;
    
    console.log('回合切換完成:', { currentPlayer, isMyTurn, canFlip });
    
    // 重新開始計時器
    stopTurnTimer(); // 先停止現有計時器
    if (gameMode === 'local' || (gameMode === 'online' && isMyTurn)) {
        // 重置計時器時間為10秒
        turnTimeLeft = 10;
        setTimeout(() => {
            startTurnTimer();
        }, 100);
    }
    
    // 更新UI顯示
    updatePlayerDisplay();
}

// 新增：處理配對結果的函數
function handleMatchResult(isMatch, card1, card2) {
    if (isMatch) {
        // 配對成功
        card1.classList.add('matched');
        card2.classList.add('matched');
        matchedPairs++;
        
        // 更新分數
        if (currentPlayer === 1) {
            player1Score += 10;
            player1Pairs++;
        } else {
            player2Score += 10;
            player2Pairs++;
        }
        
        console.log('配對成功！');
    } else {
        // 配對失敗
        console.log('配對失敗，準備蓋回卡片並切換回合');
        setTimeout(() => {
            card1.classList.remove('flipped');
            card2.classList.remove('flipped');
            
            console.log('配對失敗，卡片已蓋回');
        
        // 切換玩家
        switchPlayer();
        }, 1000);
    }
    
    // 重置翻牌狀態
    flippedCards = [];
    
    // 設置翻牌權限 - 修復翻牌同步問題
    if (gameMode === 'online') {
        // 線上模式：根據回合狀態設置
        canFlip = isMyTurn;
        console.log('線上模式翻牌權限:', { canFlip, isMyTurn, currentPlayer });
        
        // 如果不是我的回合，確保不能翻牌
        if (!isMyTurn) {
            canFlip = false;
            console.log('不是我的回合，禁用翻牌權限');
        }
        
        // 強制同步翻牌權限到全局變數
        window.canFlip = canFlip;
        window.isMyTurn = isMyTurn;
    } else {
        // 本地模式：始終可以翻牌
        canFlip = true;
        console.log('本地模式翻牌權限:', canFlip);
        
        // 強制同步翻牌權限到全局變數
        window.canFlip = canFlip;
        window.isMyTurn = true;
    }
    
    // 更新顯示
        updatePlayerDisplay();
    updateCurrentPlayer();
        
        // 檢查遊戲是否結束
        checkWin();
}

// 檢查遊戲是否獲勝 - 修復困難度設定
function checkWin() {
    // 計算總配對數
    let totalPairs;
    if (currentDifficulty === 'easy') {
        totalPairs = 6; // 4x3 = 12張卡片 (6對)
    } else if (currentDifficulty === 'normal') {
        totalPairs = 8; // 4x4 = 16張卡片 (8對)
    } else if (currentDifficulty === 'hard') {
        totalPairs = 16; // 8x4 = 32張卡片 (16對) - 修正為正確的困難模式
    } else {
        // 默認值
        totalPairs = 8;
    }
    
    console.log('檢查遊戲結束:', { matchedPairs, totalPairs, player1Pairs, player2Pairs });
    
    // 如果所有配對都完成，遊戲結束
    if (matchedPairs >= totalPairs) {
        console.log('遊戲結束！所有配對完成');
        endGame();
    }
    }


// 更新當前玩家顯示
function updateCurrentPlayer() {
    const player1Info = document.getElementById('player1-info');
    const player2Info = document.getElementById('player2-info');
    const gameBoard = document.getElementById('game-board');
    const player1Indicator = document.getElementById('player1-indicator');
    const player2Indicator = document.getElementById('player2-indicator');
    
    // 更新玩家資訊面板的active狀態
    if (gameMode === 'online') {
        // 線上模式：根據isMyTurn來決定回合指示器
        const isInviter = getCurrentMemberId() == invitationData?.from_user_id;
        if (isMyTurn) {
            // 輪到我的回合
            if (isInviter) {
                // 我是邀請者，顯示玩家1指示器
                player1Info.classList.add('active');
                player2Info.classList.remove('active');
                if (player1Indicator) player1Indicator.style.display = 'block';
                if (player2Indicator) player2Indicator.style.display = 'none';
            } else {
                // 我是被邀請者，顯示玩家2指示器
                player1Info.classList.remove('active');
                player2Info.classList.add('active');
                if (player1Indicator) player1Indicator.style.display = 'none';
                if (player2Indicator) player2Indicator.style.display = 'block';
            }
        } else {
            // 不是我的回合
            if (isInviter) {
                // 我是邀請者，顯示玩家2指示器
                player1Info.classList.remove('active');
                player2Info.classList.add('active');
                if (player1Indicator) player1Indicator.style.display = 'none';
                if (player2Indicator) player2Indicator.style.display = 'block';
            } else {
                // 我是被邀請者，顯示玩家1指示器
                player1Info.classList.add('active');
                player2Info.classList.remove('active');
                if (player1Indicator) player1Indicator.style.display = 'block';
                if (player2Indicator) player2Indicator.style.display = 'none';
            }
        }
    } else {
        // 本地模式：根據currentPlayer來決定
    if (currentPlayer === 1) {
        player1Info.classList.add('active');
        player2Info.classList.remove('active');
            if (player1Indicator) player1Indicator.style.display = 'block';
            if (player2Indicator) player2Indicator.style.display = 'none';
    } else {
        player1Info.classList.remove('active');
        player2Info.classList.add('active');
            if (player1Indicator) player1Indicator.style.display = 'none';
            if (player2Indicator) player2Indicator.style.display = 'block';
        }
    }
    
    // 綠框邏輯：立即顯示綠框（不等待翻牌）
    if (gameMode === 'online') {
        // 線上模式：根據isMyTurn來決定是否顯示綠框
        if (isMyTurn) {
            gameBoard.classList.add('current-player-1');
            gameBoard.classList.remove('current-player-2');
            console.log('立即顯示綠框：輪到我的回合');
        } else {
            gameBoard.classList.remove('current-player-1');
            gameBoard.classList.remove('current-player-2');
            console.log('立即移除綠框：不是我的回合');
        }
        

    } else {
        // 本地模式：根據currentPlayer來決定綠框
        if (currentPlayer === 1) {
            gameBoard.classList.add('current-player-1');
            gameBoard.classList.remove('current-player-2');
        } else {
            gameBoard.classList.remove('current-player-1');
            gameBoard.classList.add('current-player-2');
        }
    }
    
    // 如果是線上模式，顯示回合提示
    if (gameMode === 'online') {
        const turnIndicator = document.getElementById('turn-indicator');
        if (turnIndicator) {
            if (isMyTurn) {
                turnIndicator.textContent = '輪到你了！';
                turnIndicator.style.color = '#4CAF50';
            } else {
                turnIndicator.textContent = '等待對手...';
                turnIndicator.style.color = '#FF9800';
            }
        }
    }
}

// 強制更新玩家名字
function forceUpdatePlayerNames() {
    console.log('強制更新玩家名字:', { player1Name, player2Name });
    
    // 直接設置所有可能的玩家名字元素
    const allPlayer1Elements = document.querySelectorAll('#player1-info .player-name, .player1-name');
    const allPlayer2Elements = document.querySelectorAll('#player2-info .player-name, .player2-name');
    
    allPlayer1Elements.forEach(element => {
        element.textContent = player1Name;
        console.log('強制更新玩家1元素:', element, player1Name);
    });
    
    allPlayer2Elements.forEach(element => {
        element.textContent = player2Name;
        console.log('強制更新玩家2元素:', element, player2Name);
    });
}

// 更新玩家顯示
function updatePlayerDisplay() {
    console.log('更新玩家顯示:', { player1Name, player2Name });
    
    // 更新玩家名字
    const player1NameElement = document.querySelector('#player1-info .player-name');
    const player2NameElement = document.querySelector('#player2-info .player-name');
    
    if (player1NameElement) {
        player1NameElement.textContent = player1Name;
        console.log('更新玩家1名字:', player1Name);
    } else {
        console.warn('找不到玩家1名字元素');
    }
    
    if (player2NameElement) {
        player2NameElement.textContent = player2Name;
        console.log('更新玩家2名字:', player2Name);
    } else {
        console.warn('找不到玩家2名字元素');
    }
    
    // 更新分數和配對數
    const player1ScoreElement = document.getElementById('player1-score');
    const player2ScoreElement = document.getElementById('player2-score');
    const player1PairsElement = document.getElementById('player1-pairs');
    const player2PairsElement = document.getElementById('player2-pairs');
    const totalMatchesElement = document.getElementById('total-moves');
    
    if (player1ScoreElement) player1ScoreElement.textContent = player1Score;
    if (player2ScoreElement) player2ScoreElement.textContent = player2Score;
    if (player1PairsElement) player1PairsElement.textContent = player1Pairs;
    if (player2PairsElement) player2PairsElement.textContent = player2Pairs;
    if (totalMatchesElement) totalMatchesElement.textContent = matchedPairs;
    
    console.log('更新配對數顯示:', { 
        player1Pairs, player2Pairs, matchedPairs,
        totalMatchesElement: totalMatchesElement ? '找到' : '未找到'
    });
}



// 重置遊戲
function resetGame() {
    // 停止回合計時器
    stopTurnTimer();
    
    cards = [];
    flippedCards = [];
    matchedPairs = 0;
    totalMoves = 0;
    canFlip = true;
    currentPlayer = 1;
    player1Score = 0;
    player2Score = 0;
    player1Pairs = 0;
    player2Pairs = 0;
    consecutiveMatches = 0;
    
    updatePlayerDisplay();
    updateCurrentPlayer();
    const totalMatchesElement = document.getElementById('total-moves');
    if (totalMatchesElement) totalMatchesElement.textContent = '0';

    initializeGame();
}

// 洗牌函數
function shuffleArray(array) {
    for (let i = array.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [array[i], array[j]] = [array[j], array[i]];
    }
}

// 確定性洗牌函數（使用種子）
function shuffleArrayWithSeed(array, seed) {
    // 簡單的線性同餘生成器
    let random = seed;
    const a = 1664525;
    const c = 1013904223;
    const m = Math.pow(2, 32);
    
    for (let i = array.length - 1; i > 0; i--) {
        // 生成下一個隨機數
        random = (a * random + c) % m;
        const j = random % (i + 1);
        [array[i], array[j]] = [array[j], array[i]];
    }
}

// 暫停遊戲
function pauseGame() {
        gamePaused = true;
        // 暫停回合計時器
        if (turnTimer) {
            clearInterval(turnTimer);
            turnTimer = null;
        }
        document.getElementById('pauseBtn').classList.add('hidden');
        document.getElementById('resumeBtn').classList.remove('hidden');
}

// 繼續遊戲
function resumeGame() {
        gamePaused = false;
        // 恢復回合計時器
        if (isTurnActive && isMyTurn) {
            startTurnTimer();
        }
        document.getElementById('pauseBtn').classList.remove('hidden');
        document.getElementById('resumeBtn').classList.add('hidden');
}

// 結束遊戲
function endGame() {
    // 停止回合計時器
    stopTurnTimer();
    
    gameEndTimestamp = Date.now();
    const playTime = gameEndTimestamp - gameStartTimestamp;
    
    const winner = player1Pairs > player2Pairs ? 1 : player2Pairs > player1Pairs ? 2 : 0;
    
    console.log('遊戲結束:', { winner, player1Pairs, player2Pairs, player1Name, player2Name });
    
    // 如果是線上模式，同步遊戲結束
    if (gameMode === 'online') {
        console.log('同步遊戲結束:', { winner, player1Pairs, player2Pairs });
        syncGameEnd(winner, playTime);
        
        // 等待一小段時間確保同步完成後再顯示遊戲結束
        setTimeout(() => {
            showGameOver(winner, playTime);
        }, 1000);
    } else {
        showGameOver(winner, playTime);
    }
}

// 保存遊戲結果
async function saveGameResult(isWin, playTime) {
    try {
        console.log('=== 保存遊戲結果開始 ===');
        console.log('參數:', { isWin, playTime });
        console.log('遊戲數據:', {
            player1_id: currentUserId,
            player2_id: invitedFriendId || currentUserId,
            player1_score: player1Score,
            player2_score: player2Score,
            player1_name: player1Name,
            player2_name: player2Name,
            difficulty: currentDifficulty,
            theme: currentTheme,
            total_moves: totalMoves
        });
        
        // 使用正式版本的API端點來保存遊戲結果
        console.log('發送POST請求到: save_2p_game_result.php');
        const response = await fetch('save_2p_game_result.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                player1_id: currentUserId,
                player2_id: invitedFriendId || currentUserId,
                player1_name: player1Name,
                player2_name: player2Name,
                difficulty: (() => {
                    const difficultyNames = {
                        'easy': '簡單',
                        'normal': '普通',
                        'hard': '困難'
                    };
                    return difficultyNames[currentDifficulty] || currentDifficulty;
                })(),
                player1_score: player1Score,
                player2_score: player2Score,
                player1_pairs: player1Pairs,
                player2_pairs: player2Pairs,
                play_time: Math.floor(playTime / 1000),
                game_mode: gameMode,
                theme: currentTheme,
                total_moves: totalMoves,
                game_status: 'completed'
            })
        });
        
        console.log('收到響應:', response.status, response.statusText);
        console.log('響應頭:', Object.fromEntries(response.headers.entries()));
        
        // 檢查響應是否為JSON
        const contentType = response.headers.get('content-type');
        console.log('內容類型:', contentType);
        
        if (contentType && contentType.includes('application/json')) {
        const result = await response.json();
            console.log('JSON響應:', result);
            if (result.success) {
                console.log('遊戲結果已保存成功:', result);
            } else {
                console.error('保存失敗:', result.message, result.debug);
            }
        } else {
            // 如果不是JSON，讀取文本並記錄
            const text = await response.text();
            console.error('服務器返回非JSON響應:', text.substring(0, 500));
        }
    } catch (error) {
        console.error('=== 保存遊戲結果錯誤 ===');
        console.error('錯誤類型:', error.constructor.name);
        console.error('錯誤消息:', error.message);
        console.error('錯誤堆疊:', error.stack);
    }
}

// 獲取當前會員ID
function getCurrentMemberId() {
    // 如果currentUserId已經設定，直接返回
    if (currentUserId !== null) {
        return currentUserId;
    }
    
    // 優先使用PHP傳來的用戶ID
    if (typeof phpCurrentUserId !== 'undefined') {
        return phpCurrentUserId;
    }
    
    // 從隱藏的input獲取用戶ID
    const memberIdElement = document.querySelector('input[name="member_id"]') || 
                           document.querySelector('[data-member-id]');
    
    if (memberIdElement) {
        const memberId = memberIdElement.value || memberIdElement.dataset.memberId;
        if (memberId && memberId !== 'undefined' && memberId !== 'null') {
            return memberId;
        }
    }
    
    // 嘗試從其他可能的來源獲取
    const sessionMemberId = document.querySelector('meta[name="member_id"]');
    if (sessionMemberId && sessionMemberId.content) {
        return sessionMemberId.content;
    }
    
    // 如果都找不到，返回預設值
    console.warn('無法獲取用戶ID，使用預設值1');
    return 1;
}

// 顯示遊戲結束
function showGameOver(winner, playTime) {
    // 強制更新玩家名字顯示
    forceUpdatePlayerNames();
    
    const gameOverModal = document.getElementById('game-over-modal');
    const gameOverTitle = document.getElementById('game-over-title');
    const winnerAnnouncement = document.getElementById('winner-announcement');
    const resultMessage = document.getElementById('result-message');
    
    // 設定玩家結果
    document.getElementById('player1-result').innerHTML = `
        <span class="player-name">${player1Name}</span>
        <span class="final-score">${player1Score} 分</span>
        <span class="final-pairs">${player1Pairs} 對</span>
    `;
    
    document.getElementById('player2-result').innerHTML = `
        <span class="player-name">${player2Name}</span>
        <span class="final-score">${player2Score} 分</span>
        <span class="final-pairs">${player2Pairs} 對</span>
    `;

    // 判斷勝負
    if (winner === 1) {
        gameOverTitle.textContent = '遊戲結束';
        winnerAnnouncement.textContent = `${player1Name} 獲勝！`;
        resultMessage.textContent = '';
    } else if (winner === 2) {
        gameOverTitle.textContent = '遊戲結束';
        winnerAnnouncement.textContent = `${player2Name} 獲勝！`;
        resultMessage.textContent = '';
    } else {
        gameOverTitle.textContent = '遊戲結束';
        winnerAnnouncement.textContent = '平手！';
        resultMessage.textContent = '兩位玩家表現都很棒！';
    }
    
    gameOverModal.classList.remove('hidden');
    
    // 保存遊戲結果
    saveGameResult(winner > 0, playTime);
}

// 重新開始遊戲
function replayGame() {
    document.getElementById('game-over-modal').classList.add('hidden');
    document.getElementById('game-container').classList.add('hidden');
    // 直接回到好友邀請頁面
    document.getElementById('friend-invite-modal').classList.remove('hidden');
}

// 返回主選單
function returnToMain() {
    // 清除 beforeunload 事件，避免瀏覽器警告
    if (typeof window.clearBeforeUnload === 'function') {
        window.clearBeforeUnload();
    }
    window.onbeforeunload = null;
    
    // 智能返回：回到上一頁，如果沒有上一頁則回到遊戲分類頁面
    if (document.referrer && document.referrer !== window.location.href) {
        history.back();
    } else {
        window.location.href = 'game-category.php';
    }
}

// 關閉說明視窗
function closeHelpModal() {
    document.getElementById('help-modal').classList.add('hidden');
}

// 同步遊戲結束
function syncGameEnd(winner, playTime) {
    if (gameMode !== 'online' || !invitationId) return;
    
    const gameEndState = {
        winner: winner,
        player1Pairs: player1Pairs,
        player2Pairs: player2Pairs,
        player1Score: player1Score,
        player2Score: player2Score,
        playTime: playTime,
        lastAction: 'game_end',
        lastActionBy: currentUserId,
        player1Name: player1Name,
        player2Name: player2Name
    };
    
    console.log('同步遊戲結束狀態:', gameEndState);
    
    fetch('game-sync-api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'update_game_end',
            invitation_id: invitationId,
            player_id: currentUserId,
            game_end_state: gameEndState
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('遊戲結束狀態已同步');
    } else {
            console.error('同步遊戲結束失敗:', data.message);
        }
    })
    .catch(error => {
        console.error('同步遊戲結束錯誤:', error);
    });
}

// 同步遊戲狀態
function syncGameState(actionType = 'flip_card') {
    if (gameMode !== 'online' || !invitationId) return;
    
    // 簡化同步邏輯，移除複雜的狀態比較
    const allCards = document.querySelectorAll('.card');
    const gameState = {
        cards: Array.from(allCards).map(card => ({
            symbol: card.dataset.symbol,
            index: card.dataset.index,
            flipped: card.classList.contains('flipped'),
            matched: card.classList.contains('matched')
        })),
        flippedCards: flippedCards.map(card => card.dataset.index),
        matchedPairs: matchedPairs,
        totalMoves: totalMoves,
        currentPlayer: currentPlayer,
        player1Score: player1Score,
        player2Score: player2Score,
        player1Pairs: player1Pairs,
        player2Pairs: player2Pairs,
        lastAction: actionType,
        lastActionBy: currentUserId,
        player1Name: player1Name,
        player2Name: player2Name,
        isMyTurn: isMyTurn,
        difficulty: currentDifficulty,
        theme: currentTheme
    };
    
    console.log('同步遊戲狀態:', actionType, gameState);
    
    fetch('game-sync-api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        credentials: 'include',
        body: JSON.stringify({
            action: 'update_game_state',
            invitation_id: invitationId,
            player_id: currentUserId,
            game_state: gameState
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('遊戲狀態同步成功');
        } else {
            console.error('同步失敗:', data.message);
        }
    })
    .catch(error => {
        console.error('同步錯誤:', error);
    });
}

// 新增：防止重複處理的旗標
let isProcessingSync = false;

// 強制重新整理遊戲板
function forceRefreshGameBoard() {
    console.log('強制重新整理遊戲板');
    
    // 強制重新計算所有卡片
    const cards = document.querySelectorAll('.card');
    cards.forEach((card, index) => {
        // 觸發重排
        card.offsetHeight;
        card.style.display = 'block';
    });
    
    // 重新調整遊戲板大小
    adjustGameBoardSize();
    
    // 強制重新繪製
    const board = document.getElementById('game-board');
    if (board) {
        board.style.display = 'none';
        setTimeout(() => {
            board.style.display = 'grid';
            console.log('遊戲板重新顯示完成');
        }, 50);
    }
}

// 手動同步困難度
function manualSyncDifficulty() {
    if (gameMode === 'online' && invitationId) {
        console.log('手動同步困難度:', currentDifficulty);
        
        // 立即同步
        syncDifficultyImmediately();
        
        // 強制重新整理
        setTimeout(() => {
            forceRefreshGameBoard();
            console.log('手動同步完成');
        }, 200);
    }
}

// 立即同步困難度設定
function syncDifficultyImmediately() {
    if (gameMode === 'online' && invitationId) {
        console.log('立即同步困難度設定:', currentDifficulty);
        
        // WebSocket 同步
        if (window.memoryGameWebSocket && window.memoryGameWebSocket.isConnected) {
            window.memoryGameWebSocket.syncDifficulty(
                invitationId,
                getCurrentMemberId(),
                currentDifficulty
            );
        }
        
        // 使用可靠的遊戲同步API
        fetch('game-sync-api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'sync_difficulty',
                invitation_id: invitationId,
                player_id: getCurrentMemberId(),
                difficulty: currentDifficulty
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('困難度同步成功:', data);
            } else {
                console.error('困難度同步失敗:', data.message);
            }
        })
        .catch(error => {
            console.error('困難度同步請求失敗:', error);
        });
        
        // 強制重新整理遊戲板
        setTimeout(() => {
            forceRefreshGameBoard();
            console.log('困難度同步後強制重新整理遊戲板');
        }, 100);
    }
}

// 開始遊戲同步輪詢 - 修復同步問題
function startGameSync() {
    if (gameMode !== 'online' || !invitationId) return;
    
    console.log('開始遊戲同步，邀請ID:', invitationId);
    
    // 初始化同步狀態變數
    if (typeof isProcessingSync === 'undefined') {
        isProcessingSync = false;
    }
    
    // 立即進行一次困難度同步檢查
    setTimeout(() => {
        console.log('立即進行困難度同步檢查');
        forceSyncDifficultyCheck();
    }, 500);
    
    // 延遲進行退出檢測，確保遊戲已經初始化
    setTimeout(() => {
        checkForPlayerQuit();
    }, 10000);
    
    // 降低同步頻率到每1500ms一次，提高同步效率
    gameSyncInterval = setInterval(() => {
        if (isProcessingSync) {
            console.log('正在處理同步，跳過本次輪詢');
            return;
        }
        
        // 如果最近有錯誤，延長等待時間
        if (window.lastSyncError && Date.now() - window.lastSyncError < 2000) {
            console.log('最近有同步錯誤，延長等待時間');
            return;
        }
        
        isProcessingSync = true;
        
        fetch('game-sync-api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            credentials: 'include',
            body: JSON.stringify({
                action: 'get_game_state',
                invitation_id: invitationId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // 檢查是否有玩家退出
                if (data.player_quit) {
                    console.log('檢測到玩家退出');
                    // 處理玩家退出邏輯
                    return;
                }
                
                // 檢查遊戲是否結束
                if (data.is_game_end) {
                    console.log('遊戲已結束');
                    // 處理遊戲結束邏輯
                    return;
                }
                
                // 更新遊戲狀態
                if (data.game_state) {
                    updateGameFromSync(data.game_state);
                }
            } else {
                console.error('獲取遊戲狀態失敗:', data.message);
            }
        })
        .catch(error => {
            console.error('同步錯誤:', error);
            window.lastSyncError = Date.now();
            
            // 如果是 JSON 解析錯誤，記錄詳細信息
            if (error.name === 'SyntaxError') {
                console.error('JSON 解析錯誤，可能是伺服器回應格式問題');
            }
        })
        .finally(() => {
            isProcessingSync = false;
        });
    }, 1500); // 每1500ms同步一次，提高同步效率
}

// 強制困難度同步檢查
function forceSyncDifficultyCheck() {
    console.log('🔧 強制困難度同步檢查...');
    
    // 檢查當前困難度設定
    const currentDifficulty = window.currentDifficulty || 'normal';
    console.log('當前困難度:', currentDifficulty);
    
    // 根據困難度設置正確的gridSize
    let gridSize;
    switch (currentDifficulty) {
        case 'easy':
            gridSize = 4; // 4x3 = 12張卡片
            break;
        case 'normal':
            gridSize = 4; // 4x4 = 16張卡片
            break;
        case 'hard':
            gridSize = 8; // 8x4 = 32張卡片 - 修正為正確的困難模式
            break;
        default:
            gridSize = 4;
    }
    
    // 強制更新全局變數
    if (typeof window.gridSize !== 'undefined') {
        window.gridSize = gridSize;
    }
    if (typeof window.currentDifficulty !== 'undefined') {
        window.currentDifficulty = currentDifficulty;
    }
    
    // 強制重新創建遊戲板
    if (typeof window.createCards === 'function') {
        window.createCards();
        console.log('遊戲板已重新創建');
    }
    
    // 強制重新整理遊戲板
    if (typeof window.forceRefreshGameBoard === 'function') {
        window.forceRefreshGameBoard();
    }
    
    // 強制應用CSS類別
    const gameBoard = document.getElementById('game-board');
    if (gameBoard) {
        gameBoard.classList.remove('easy-mode', 'hard-mode');
        if (currentDifficulty === 'easy') {
            gameBoard.classList.add('easy-mode');
        } else if (currentDifficulty === 'hard') {
            gameBoard.classList.add('hard-mode');
            console.log('已應用困難模式CSS類別');
        }
    }
    
    // 強制調整遊戲板大小
    if (typeof window.adjustGameBoardSize === 'function') {
        setTimeout(() => {
            window.adjustGameBoardSize();
            console.log('已強制調整遊戲板大小');
        }, 100);
    }
    
    console.log('困難度已強制同步:', { difficulty: currentDifficulty, gridSize: gridSize });
}

// 快速檢查玩家退出狀態 - 暫時禁用
function checkForPlayerQuit() {
    console.log('快速退出檢測已禁用，避免誤判');
    return; // 暫時禁用快速退出檢測
    
    if (gameMode !== 'online' || !invitationId) return;
    
    // 檢查遊戲是否已經真正開始且進行了一段時間（至少10秒）
    if (!gameStartTimestamp || !canFlip) {
        console.log('遊戲尚未真正開始，跳過退出檢測');
        return;
    }
    
    const gameDuration = Date.now() - gameStartTimestamp;
    if (gameDuration < 10000) { // 10秒
        console.log('遊戲進行時間不足10秒，跳過退出檢測');
        return;
    }
    
    fetch('game-sync-api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'get_game_state',
            invitation_id: invitationId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.player_quit) {
            console.log('快速退出檢測已完全禁用，避免誤判');
            // 完全禁用快速退出檢測
            /*
            console.log('快速檢測到玩家退出，立即處理');
            
            // 立即停止所有計時器和同步
            if (gameSyncInterval) {
                clearInterval(gameSyncInterval);
                gameSyncInterval = null;
            }
            if (turnTimer) {
                clearInterval(turnTimer);
                turnTimer = null;
            }
            if (invitationCheckInterval) {
                clearInterval(invitationCheckInterval);
                invitationCheckInterval = null;
            }
            
            // 立即隱藏遊戲界面
            const gameContainer = document.getElementById('game-container');
            if (gameContainer) {
                gameContainer.classList.add('hidden');
            }
            
            // 立即隱藏所有其他視窗
            hideAllModals();
            
            // 立即顯示玩家退出視窗
            document.getElementById('player-quit-modal').classList.remove('hidden');
            
            // 重置遊戲狀態
            gameMode = 'local';
            invitationId = null;
            invitationData = null;
            isQuitting = false;
            
            console.log('快速退出處理完成');
            */
        }
    })
    .catch(error => {
        console.error('快速退出檢測錯誤:', error);
    });
}

// 添加一個更積極的退出檢測函數（暫時禁用）
function aggressiveQuitCheck() {
    console.log('積極退出檢測已禁用，避免誤判');
    return; // 暫時禁用積極退出檢測
    
    if (gameMode !== 'online' || !invitationId) return;
    
    // 每1000ms檢查一次退出狀態，降低頻率
    const quitCheckInterval = setInterval(() => {
        if (gameMode !== 'online' || !invitationId) {
            clearInterval(quitCheckInterval);
            return;
        }
        
        // 檢查遊戲是否已經真正開始且進行了一段時間（至少10秒）
        if (!gameStartTimestamp || !canFlip) {
            console.log('遊戲尚未真正開始，跳過積極退出檢測');
            return;
        }
        
        const gameDuration = Date.now() - gameStartTimestamp;
        if (gameDuration < 10000) { // 10秒
            console.log('遊戲進行時間不足10秒，跳過積極退出檢測');
            return;
        }
        
        fetch('game-sync-api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'get_game_state',
                invitation_id: invitationId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.player_quit) {
                console.log('積極退出檢測已完全禁用，避免誤判');
                // 完全禁用積極退出檢測
                /*
                console.log('積極檢測到玩家退出，立即處理');
                clearInterval(quitCheckInterval);
                
                // 立即停止所有計時器和同步
                if (gameSyncInterval) {
                    clearInterval(gameSyncInterval);
                    gameSyncInterval = null;
                }
                if (turnTimer) {
                    clearInterval(turnTimer);
                    turnTimer = null;
                }
                if (invitationCheckInterval) {
                    clearInterval(invitationCheckInterval);
                    invitationCheckInterval = null;
                }
                
                // 立即隱藏遊戲界面
                const gameContainer = document.getElementById('game-container');
                if (gameContainer) {
                    gameContainer.classList.add('hidden');
                }
                
                // 立即隱藏所有其他視窗
                hideAllModals();
                
                // 立即顯示玩家退出視窗
                document.getElementById('player-quit-modal').classList.remove('hidden');
                
                // 重置遊戲狀態
                gameMode = 'local';
                invitationId = null;
                invitationData = null;
                isQuitting = false;
                
                console.log('積極退出處理完成');
                */
            }
        })
        .catch(error => {
            console.error('積極退出檢測錯誤:', error);
        });
    }, 1000);
}

// 從同步數據更新遊戲
function updateGameFromSync(gameState) {
    console.log('收到遊戲狀態更新:', gameState);
    console.log('當前遊戲狀態:', {
        currentPlayer: currentPlayer,
        isMyTurn: isMyTurn,
        flippedCards: flippedCards.length,
        matchedPairs: matchedPairs
    });
    
    // 簡化防抖機制，降低到10ms，確保不會錯過重要更新
    const now = Date.now();
    if (now - lastSyncTime < 10) {
        console.log('防抖：跳過重複的同步更新');
        return;
    }
    lastSyncTime = now;
    
    // 檢查是否是我的動作
    const isMyAction = gameState.lastActionBy === getCurrentMemberId();
    console.log('是否是我的動作:', isMyAction, '最後動作:', gameState.lastAction, '動作執行者:', gameState.lastActionBy);
    
    // 處理翻牌動作 - 修復同步問題
    if (gameState.lastAction === 'flip_card' || gameState.lastAction === 'flip_card_immediate') {
        // 檢查多種可能的卡片索引字段
        const cardIndex = gameState.card_index || gameState.lastFlippedCardIndex || gameState.cardIndex;
        if (cardIndex !== undefined) {
            const cardIndexInt = parseInt(cardIndex);
            const flippedCard = document.querySelector(`[data-index="${cardIndexInt}"]`);
            if (flippedCard && !flippedCard.classList.contains('matched')) {
                // 只有當卡片還沒有翻開時才翻開
                if (!flippedCard.classList.contains('flipped')) {
                flippedCard.classList.add('flipped');
                    
                    // 卡片內容已在創建時設定，不需要重新設定
                    
                // 確保flippedCards陣列包含這張卡片
                if (!flippedCards.includes(flippedCard)) {
                    flippedCards.push(flippedCard);
                        console.log('同步翻牌:', cardIndexInt, '由玩家:', gameState.lastActionBy, '當前翻牌數量:', flippedCards.length);
                    } else {
                        console.log('卡片已在翻牌陣列中:', cardIndexInt);
                    }
                }
                
                // 檢查是否需要配對檢查
                if (flippedCards.length === 2) {
                    console.log('檢測到兩張翻開的卡片，準備配對檢查');
                    // 延遲一下再檢查配對，確保兩張卡片都已翻開
                    setTimeout(() => {
                        checkMatchSync();
                    }, 200);
                }
            } else {
                console.log('無法找到卡片或卡片已配對:', cardIndexInt, flippedCard);
            }
        } else {
            console.log('沒有找到卡片索引:', gameState);
        }
    }
    
    // 處理配對成功
    if (gameState.lastAction === 'match_success') {
        console.log('收到配對成功同步');
        
        // 更新分數
        if (gameState.player1Score !== undefined) player1Score = gameState.player1Score;
        if (gameState.player2Score !== undefined) player2Score = gameState.player2Score;
        if (gameState.player1Pairs !== undefined) player1Pairs = gameState.player1Pairs;
        if (gameState.player2Pairs !== undefined) player2Pairs = gameState.player2Pairs;
        if (gameState.matchedPairs !== undefined) matchedPairs = gameState.matchedPairs;
        
        // 處理翻開的卡片，確保它們保持翻開狀態
        if (gameState.flippedCards && gameState.flippedCards.length > 0) {
            gameState.flippedCards.forEach(cardIndex => {
                const card = document.querySelector(`[data-index="${cardIndex}"]`);
                if (card) {
                    card.classList.add('flipped');
                    card.classList.add('matched');
                    console.log('同步配對成功，卡片保持翻開:', cardIndex);
                }
            });
        }
        
        // 清空本地翻牌陣列
        flippedCards = [];
        
        // 更新顯示
        updateScoreDisplay();
        updatePlayerDisplay();
        
        console.log('配對成功同步完成');
    }
    
    // 處理配對失敗
    if (gameState.lastAction === 'match_fail') {
        console.log('收到配對失敗同步');
        
        // 蓋回所有翻開的卡片
        flippedCards.forEach(card => {
            card.classList.remove('flipped');
        });
        flippedCards = [];
        
        // 切換玩家
        if (gameState.currentPlayer !== undefined) {
            currentPlayer = gameState.currentPlayer;
            const isInviter = getCurrentMemberId() == invitationData?.from_user_id;
            isMyTurn = (currentPlayer === 1 && isInviter) || (currentPlayer === 2 && !isInviter);
            canFlip = isMyTurn;
        }
        
        // 更新顯示
        updateCurrentPlayer();
        
        console.log('配對失敗同步完成');
    }
    
    // 處理配對結果
    if (gameState.lastAction === 'check_match_and_turn_switch' && gameState.lastFlippedCardIndexes) {
        const matchedCards = gameState.lastFlippedCardIndexes.map(index => 
            document.querySelector(`[data-index="${index}"]`)
        ).filter(card => card !== null);
        
        if (gameState.lastMatchResult) {
            // 配對成功，將卡片標記為配對
            matchedCards.forEach(card => {
                card.classList.add('matched');
                // 從翻牌陣列中移除卡片
                const index = flippedCards.indexOf(card);
                if (index > -1) {
                    flippedCards.splice(index, 1);
                }
                console.log('同步配對成功:', card.dataset.index, '由玩家:', gameState.lastActionBy);
            });
            
            // 更新分數
            if (gameState.player1Score !== undefined) player1Score = gameState.player1Score;
            if (gameState.player2Score !== undefined) player2Score = gameState.player2Score;
            if (gameState.player1Pairs !== undefined) player1Pairs = gameState.player1Pairs;
            if (gameState.player2Pairs !== undefined) player2Pairs = gameState.player2Pairs;
            if (gameState.matchedPairs !== undefined) matchedPairs = gameState.matchedPairs;
        } else {
            // 配對失敗，將卡片蓋回去
            matchedCards.forEach(card => {
                card.classList.remove('flipped');
                
                // 卡片內容已在創建時設定，不需要重置
                
                // 從翻牌陣列中移除卡片
                const index = flippedCards.indexOf(card);
                if (index > -1) {
                    flippedCards.splice(index, 1);
                }
                console.log('同步配對失敗，蓋回卡片:', card.dataset.index, '由玩家:', gameState.lastActionBy);
            });
            
            // 切換玩家回合
            if (gameState.currentPlayer !== undefined) {
                currentPlayer = gameState.currentPlayer;
                const isInviter = getCurrentMemberId() == invitationData?.from_user_id;
                isMyTurn = (currentPlayer === 1 && isInviter) || (currentPlayer === 2 && !isInviter);
                console.log('同步回合切換:', currentPlayer, '是否我的回合:', isMyTurn);
        
        // 重置翻牌狀態
        flippedCards = [];
                
                // 更新回合指示器
                updateCurrentPlayer();
                
                // 強制更新當前玩家顯示
                updateCurrentPlayer();
                
                // 修復：在線上模式中，只有在我的回合時才能翻牌
                if (gameMode === 'online') {
                    canFlip = isMyTurn;
                    if (isMyTurn) {
                        console.log('輪到我的回合，啟用翻牌功能');
                    } else {
                        console.log('不是我的回合，等待對手');
                    }
                } else {
                    // 單人模式或離線模式，始終可以翻牌
        canFlip = true;
                }
                
                // 確保翻牌權限與回合狀態一致
                console.log('同步後翻牌權限狀態:', { canFlip, isMyTurn, currentPlayer });
            }
        }
    }
    
    // 處理困難度同步
    if (gameState.difficulty && gameState.difficulty !== currentDifficulty) {
        console.log('同步困難度變更:', currentDifficulty, '->', gameState.difficulty);
        currentDifficulty = gameState.difficulty;
        
        // 強制更新全局變數
        window.currentDifficulty = currentDifficulty;
        
        // 根據困難度調整遊戲板大小
        switch (currentDifficulty) {
            case 'easy':
                gridSize = 4; // 4x3 = 12張卡片
                break;
            case 'normal':
                gridSize = 4; // 4x4 = 16張卡片
                break;
            case 'hard':
                gridSize = 8; // 8x4 = 32張卡片
                break;
            default:
                gridSize = 4;
        }
        
        // 強制更新全局變數
        window.gridSize = gridSize;
        
        console.log('困難度設定已應用,遊戲板大小已調整:', currentDifficulty, gridSize);
        
        // 強制重新創建遊戲板以確保正確的卡片數量
        const gameBoard = document.getElementById('game-board');
        if (gameBoard) {
            gameBoard.innerHTML = '';
            createCards();
            adjustGameBoardSize();
            console.log('困難度同步後重新創建遊戲板');
        }
    }
    
    // 更新遊戲數據
    if (gameState.matchedPairs !== undefined) matchedPairs = gameState.matchedPairs;
    if (gameState.totalMoves !== undefined) totalMoves = gameState.totalMoves;
    if (gameState.player1Score !== undefined) player1Score = gameState.player1Score;
    if (gameState.player2Score !== undefined) player2Score = gameState.player2Score;
    if (gameState.player1Pairs !== undefined) player1Pairs = gameState.player1Pairs;
    if (gameState.player2Pairs !== undefined) player2Pairs = gameState.player2Pairs;
    
    // 更新顯示
    updatePlayerDisplay();
    updateCurrentPlayer();
    
    // 強制更新配對數顯示
    const totalMatchesElement = document.getElementById('total-moves');
    if (totalMatchesElement) {
        totalMatchesElement.textContent = matchedPairs;
    }
    
    // 移除重複的困難度同步檢查，避免衝突
    
    console.log('遊戲狀態同步完成');
    console.log('同步後狀態:', {
        matchedPairs: matchedPairs,
        player1Pairs: player1Pairs,
        player2Pairs: player2Pairs,
        currentPlayer: currentPlayer,
        isMyTurn: isMyTurn,
        difficulty: currentDifficulty
    });
        
        // 檢查是否是其他玩家的退出信號（已完全禁用）
        if (gameState.lastActionBy && gameState.lastActionBy !== getCurrentMemberId() && 
            (gameState.lastAction === 'player_quit_signal' || gameState.player_quit)) {
            console.log('其他玩家退出信號檢測已完全禁用，避免誤判');
            // 完全禁用其他玩家退出信號檢測
            /*
            console.log('收到其他玩家的退出信號，立即處理退出');
            
            // 立即停止所有計時器和同步
            if (gameSyncInterval) {
                clearInterval(gameSyncInterval);
                gameSyncInterval = null;
            }
            if (turnTimer) {
                clearInterval(turnTimer);
                turnTimer = null;
            }
            if (invitationCheckInterval) {
                clearInterval(invitationCheckInterval);
                invitationCheckInterval = null;
            }
            
            // 立即隱藏遊戲界面
            const gameContainer = document.getElementById('game-container');
            if (gameContainer) {
                gameContainer.classList.add('hidden');
            }
            
            // 立即隱藏所有其他視窗
            hideAllModals();
            
            // 立即顯示玩家退出視窗
            document.getElementById('player-quit-modal').classList.remove('hidden');
            
            // 重置遊戲狀態
            gameMode = 'local';
            invitationId = null;
            invitationData = null;
            isQuitting = false;
            
            console.log('其他玩家退出信號處理完成');
            return;
            */
        }
        
        // 更新分數和配對數
        player1Score = gameState.player1Score || 0;
        player2Score = gameState.player2Score || 0;
        player1Pairs = gameState.player1Pairs || 0;
        player2Pairs = gameState.player2Pairs || 0;
        matchedPairs = gameState.matchedPairs || 0; // 重要：更新總配對數
        totalMoves = gameState.totalMoves || 0;
        
        // 先更新當前玩家
        const previousPlayer = currentPlayer;
        currentPlayer = gameState.currentPlayer || 1;
        
        // 更新玩家名字（如果同步數據中有）
        if (gameState.player1Name) player1Name = gameState.player1Name;
        if (gameState.player2Name) player2Name = gameState.player2Name;
        
        console.log('同步後狀態:', { 
            matchedPairs, player1Pairs, player2Pairs, 
            player1Score, player2Score, currentPlayer,
            player1Name, player2Name
        });
        
        // 更新顯示
        updatePlayerDisplay();
        forceUpdatePlayerNames(); // 強制更新玩家名字
        const totalMatchesDisplay2 = document.getElementById('total-moves');
        if (totalMatchesDisplay2) totalMatchesDisplay2.textContent = matchedPairs;
        
        // 根據同步數據更新回合狀態
        if (gameState.isMyTurn !== undefined) {
            isMyTurn = gameState.isMyTurn;
            console.log('使用同步數據的回合狀態:', isMyTurn);
        } else {
            // 如果沒有明確的回合狀態，根據玩家ID判斷
            const isInviter = getCurrentMemberId() == invitationData?.from_user_id;
            isMyTurn = (currentPlayer === 1 && isInviter) || (currentPlayer === 2 && !isInviter);
            console.log('計算回合狀態:', { 
                currentPlayer, isInviter, isMyTurn,
                currentUserId: getCurrentMemberId(),
                fromUserId: invitationData?.from_user_id
            });
        }
        
        // 如果是玩家切換動作，強制重新計算回合狀態
        if (gameState.lastAction === 'switch_player') {
            const isInviter = getCurrentMemberId() == invitationData?.from_user_id;
            isMyTurn = (currentPlayer === 1 && isInviter) || (currentPlayer === 2 && !isInviter);
            console.log('玩家切換後重新計算回合狀態:', { 
                currentPlayer, isInviter, isMyTurn,
                currentUserId: getCurrentMemberId(),
                fromUserId: invitationData?.from_user_id
            });
            
            // 重置翻牌權限
            canFlip = true;
            console.log('玩家切換後重置翻牌權限:', canFlip);
            
            // 確保所有翻開的卡片都被蓋回
            flippedCards.forEach(card => {
                if (card && !card.classList.contains('matched')) {
                    card.classList.remove('flipped');
                }
            });
            flippedCards = [];
            
            console.log('玩家切換後清空翻牌狀態');
        }
        
        // 更新當前玩家顯示（包含綠框邏輯）
        updateCurrentPlayer();
        
        // 強制更新回合狀態顯示
        console.log('強制更新回合狀態:', { 
            currentPlayer, isMyTurn, isInviter: getCurrentMemberId() == invitationData?.from_user_id,
            currentUserId: getCurrentMemberId(),
            fromUserId: invitationData?.from_user_id
        });
        
        // 強制修復回合狀態（如果檢測到不一致）
        const isInviter = getCurrentMemberId() == invitationData?.from_user_id;
        const shouldBeMyTurn = (currentPlayer === 1 && isInviter) || (currentPlayer === 2 && !isInviter);
        if (isMyTurn !== shouldBeMyTurn) {
            console.log('檢測到回合狀態不一致，強制修復');
            isMyTurn = shouldBeMyTurn;
            updateCurrentPlayer();
        }
        
        console.log('同步回合狀態:', { 
            previousPlayer, currentPlayer, isMyTurn, 
            gameStateIsMyTurn: gameState.isMyTurn,
            isInviter: getCurrentMemberId() == invitationData?.from_user_id,
            currentUserId: getCurrentMemberId(),
            fromUserId: invitationData?.from_user_id
        });
        
    // 如果是我的回合，開始計時器並啟用翻牌權限（避免重複啟動）
        if (gameMode === 'local' || (gameMode === 'online' && isMyTurn)) {
            // 只有在計時器未運行時才啟動
            if (!turnTimer) {
                startTurnTimer();
            }
        canFlip = true;
        console.log('輪到我的回合，已啟用翻牌權限');
        } else {
            stopTurnTimer();
        canFlip = false;
        console.log('不是我的回合，已禁用翻牌權限');
        }
        
            // 調試：檢查翻牌權限
    console.log('翻牌權限檢查:', {
        canFlip: canFlip,
        isMyTurn: isMyTurn,
        gameMode: gameMode,
        currentPlayer: currentPlayer
    });
    
    // 自動檢測遊戲是否卡住（延長檢測時間，避免與正常配對失敗處理衝突）
    if (gameMode === 'online' && !canFlip && flippedCards.length === 0) {
        console.log('檢測到可能的遊戲卡住，嘗試修復');
        setTimeout(() => {
            // 再次檢查是否真的卡住了
            if (!canFlip && flippedCards.length === 0) {
                canFlip = true;
                console.log('已重置翻牌權限');
            }
        }, 3000); // 延長到3秒，避免與配對失敗的2秒延遲衝突
    }
    
    // 確保翻牌權限與回合狀態一致
    if (gameMode === 'online' && isMyTurn && !canFlip) {
        console.log('檢測到回合狀態與翻牌權限不一致，修復中...');
        canFlip = true;
        console.log('已修復翻牌權限，現在可以翻牌');
    } else if (gameState.lastAction === 'game_end') {
        console.log('同步遊戲結束:', gameState);
        
        // 停止同步輪詢
        if (gameSyncInterval) {
            clearInterval(gameSyncInterval);
            gameSyncInterval = null;
        }
        
        // 更新最終分數和配對數
        player1Score = gameState.player1Score || 0;
        player2Score = gameState.player2Score || 0;
        player1Pairs = gameState.player1Pairs || 0;
        player2Pairs = gameState.player2Pairs || 0;
        
        // 更新玩家名字
        if (gameState.player1Name) player1Name = gameState.player1Name;
        if (gameState.player2Name) player2Name = gameState.player2Name;
        
        // 更新顯示
        updatePlayerDisplay();
        forceUpdatePlayerNames();
        
        // 顯示遊戲結束
        const winner = gameState.winner || 0;
        const playTime = gameState.playTime || 0;
        
        console.log('顯示遊戲結束:', { winner, playTime, player1Name, player2Name });
        showGameOver(winner, playTime);
    }
    
    // 同步困難度設定
    if (gameState.difficulty && gameState.difficulty !== currentDifficulty) {
        console.log('同步困難度設定:', { old: currentDifficulty, new: gameState.difficulty });
        currentDifficulty = gameState.difficulty;
        
        // 立即重新初始化遊戲
        console.log('困難度改變，重新初始化遊戲');
        initializeGame();
        
        // 強制重新調整遊戲板大小
        setTimeout(() => {
            forceRefreshGameBoard();
            console.log('困難度設定已同步並應用');
        }, 500); // 增加延遲確保DOM更新
    }
    
    // 處理困難度同步動作
    if (gameState.lastAction === 'sync_difficulty' && gameState.lastActionBy !== getCurrentMemberId()) {
        console.log('收到對手困難度同步:', gameState.difficulty);
        if (gameState.difficulty && gameState.difficulty !== currentDifficulty) {
            currentDifficulty = gameState.difficulty;
            console.log('應用對手的困難度設定:', currentDifficulty);
            initializeGame();
        }
    }
    
    // 同步主題設定
    if (gameState.theme && gameState.theme !== currentTheme) {
        console.log('同步主題設定:', { old: currentTheme, new: gameState.theme });
        currentTheme = gameState.theme;
        
        // 應用主題設定
        const themeData = themes.find(t => t.theme_name === currentTheme);
        if (themeData) {
            const themeStyle = JSON.parse(themeData.theme_style);
            
            // 更新卡片顏色
            document.documentElement.style.setProperty('--card-back-color', themeStyle.cardBack);
            document.documentElement.style.setProperty('--card-front-color', themeStyle.cardFront);
            document.documentElement.style.setProperty('--matched-color', themeStyle.matched);
            document.documentElement.style.setProperty('--background-color', themeStyle.background);
            document.documentElement.style.setProperty('--container-color', themeStyle.container);
            
            console.log('主題已同步並應用:', currentTheme, themeStyle);
        }
        }
    }


// 調整遊戲板大小
function adjustGameBoardSize() {
    const container = document.querySelector('.game-container');
    const board = document.getElementById('game-board');
    if (!container || !board) {
        console.error('找不到遊戲容器或遊戲板');
        return;
    }

    console.log('開始調整遊戲板大小，當前困難度:', currentDifficulty);

    // 根據困難度設定卡片大小和排列
    let cols, rows, maxCardSize;
    if (currentDifficulty === 'hard') {
        cols = 8; rows = 4; maxCardSize = 80; // 困難：8x4，32張卡片
    } else if (currentDifficulty === 'easy') {
        cols = 4; rows = 3; maxCardSize = 100; // 簡單：4x3，12張卡片
    } else {
        cols = 4; rows = 4; maxCardSize = 90; // 普通：4x4，16張卡片
    }
    
    // 強制設定CSS網格佈局（確保正確的網格佈局）
    board.style.gridTemplateColumns = `repeat(${cols}, 1fr)`;
    board.style.gridTemplateRows = `repeat(${rows}, 1fr)`;
    board.style.display = 'grid';
    board.style.gap = '10px';
    
    // 根據難度設定視窗大小
    if (currentDifficulty === 'easy') {
        board.style.maxWidth = '400px';
        board.style.maxHeight = '300px';
        board.style.overflow = 'hidden';
    } else if (currentDifficulty === 'normal') {
        board.style.maxWidth = '500px';
        board.style.maxHeight = '500px';
        board.style.overflow = 'hidden';
    } else if (currentDifficulty === 'hard') {
        board.style.maxWidth = '1000px';
        board.style.maxHeight = '500px';
        board.style.overflow = 'hidden';
    }
    
    console.log('✅ 已強制設定CSS網格佈局:', { 
        difficulty: currentDifficulty,
        cols, rows, 
        gridTemplateColumns: board.style.gridTemplateColumns, 
        gridTemplateRows: board.style.gridTemplateRows,
        maxWidth: board.style.maxWidth
    });
    
    const gap = 6; // px
    const containerWidth = container.clientWidth;
    const maxBoardWidth = Math.min(containerWidth, cols * maxCardSize + (cols - 1) * gap);
    const cardSize = Math.floor((maxBoardWidth - (cols - 1) * gap) / cols);

    console.log('計算的佈局:', { cols, rows, cardSize, maxBoardWidth, containerWidth });

    // 設定每張卡片為正方形
    const cards = document.querySelectorAll('.card');
    cards.forEach((card, index) => {
        // 確保卡片是正方形
        card.style.width = cardSize + 'px';
        card.style.height = cardSize + 'px';
        card.style.maxWidth = cardSize + 'px';
        card.style.maxHeight = cardSize + 'px';
        card.style.paddingBottom = '0'; // 移除 padding-bottom 確保正方形
        card.style.display = 'block'; // 確保卡片可見
        
        // 調試：檢查卡片是否為正方形
        const rect = card.getBoundingClientRect();
        console.log(`卡片 ${index} 尺寸檢查:`, {
            width: rect.width,
            height: rect.height,
            isSquare: Math.abs(rect.width - rect.height) < 1,
            cardSize: cardSize,
            difficulty: currentDifficulty
        });
    });
    
    // 調整圖標大小以適應卡片
    const iconSize = Math.floor(cardSize * 0.6); // 圖標大小為卡片大小的60%
    document.querySelectorAll('.card-front, .card-back').forEach(face => {
        face.style.fontSize = iconSize + 'px';
    });

    // 讓 .game-container 寬度自動適應遊戲板
    const boardWidth = board.offsetWidth;
    // 根據難度設定容器寬度，避免滾動條
    if (currentDifficulty === 'easy') {
        container.style.width = '450px';
    } else if (currentDifficulty === 'normal') {
        container.style.width = '550px';
    } else if (currentDifficulty === 'hard') {
        container.style.width = '1100px';
    }
    
    // 強制重新計算佈局
    board.style.display = 'none';
    board.offsetHeight; // 觸發重排
    board.style.display = 'grid';
    
    // 調試：檢查遊戲板尺寸
    console.log('遊戲板尺寸檢查:', {
        boardWidth: boardWidth,
        containerWidth: container.style.width,
        boardHeight: board.offsetHeight,
        cardCount: cards.length,
        difficulty: currentDifficulty,
        cols: cols,
        rows: rows,
        cardSize: cardSize
    });
    
    console.log('遊戲板大小調整完成');
}

// 顯示主題選擇視窗
function showThemeModal() {
    document.getElementById('player-setup-modal').classList.add('hidden');
    document.getElementById('theme-modal').classList.remove('hidden');
}

// 顯示難度選擇視窗
function showDifficultyModal() {
    document.getElementById('theme-modal').classList.add('hidden');
    document.getElementById('difficulty-modal').classList.remove('hidden');
}

// 開始遊戲
function startGame() {
    document.getElementById('difficulty-modal').classList.add('hidden');
    
    // 如果是線上模式，更新邀請設定並開始遊戲
    if (gameMode === 'online') {
        // 設定邀請數據（如果還沒有設定）
        if (!invitationData && window.currentInvitation) {
            invitationData = window.currentInvitation;
        }
        
        // 更新邀請設定，等待完成後開始遊戲
        updateInvitationSettings().then(() => {
            console.log('邀請設定已更新，開始遊戲');
            
            // 設定玩家名稱 - 邀請者為玩家1，被邀請者為玩家2
            // 檢查當前用戶是邀請者還是被邀請者
            const isInviter = getCurrentMemberId() == invitationData?.from_user_id;
            
            // 獲取當前用戶名
            let currentUserDisplayName = '玩家';
            if (typeof currentUserName !== 'undefined' && currentUserName && currentUserName !== '玩家') {
                currentUserDisplayName = currentUserName;
            } else {
                // 嘗試從其他地方獲取用戶名
                const memberNameElement = document.querySelector('input[name="member_name"]');
                if (memberNameElement && memberNameElement.value) {
                    currentUserDisplayName = memberNameElement.value;
                } else {
                    currentUserDisplayName = `玩家${getCurrentMemberId()}`;
                }
            }
            
            if (isInviter) {
                // 邀請者：玩家1是自己，玩家2是好友
                player1Name = currentUserDisplayName;
                player2Name = invitedFriendName || '被邀請者';
            } else {
                // 被邀請者：玩家1是好友，玩家2是自己
                player1Name = invitedFriendName || '邀請者';
                player2Name = currentUserDisplayName;
            }
            
            console.log('設定玩家名字:', { 
                player1Name, player2Name, 
                currentUserDisplayName, 
                invitedFriendName, 
                isInviter,
                currentUserName
            });
            
            // 立即更新顯示
            updatePlayerDisplay();
            forceUpdatePlayerNames();
            
            // 直接進入遊戲
            document.getElementById('game-container').classList.remove('hidden');
            gameStartTimestamp = Date.now();
            initializeGame();
            
            // 設定為邀請者的回合並開始同步
            isMyTurn = isInviter; // 邀請者先開始
            currentPlayer = isInviter ? 1 : 2; // 邀請者是玩家1，被邀請者是玩家2
            
            console.log('遊戲開始設定:', {
                isMyTurn,
                currentPlayer,
                isInviter,
                player1Name,
                player2Name
            });
            
            // 使用 WebSocket 加入遊戲房間
            if (window.memoryGameWebSocket && window.memoryGameWebSocket.isConnected) {
                window.memoryGameWebSocket.joinGame(invitationId, getCurrentMemberId());
                console.log('邀請者已加入 WebSocket 遊戲房間');
            }
            
            if (gameMode === 'online') {
                startGameSync();
                console.log('開始線上遊戲同步，邀請ID:', invitationId);
                
                // 立即進行一次困難度同步檢查
                setTimeout(() => {
                    console.log('立即進行困難度同步檢查');
                    fetch('game-sync-api.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        credentials: 'include',
                        body: JSON.stringify({
                            action: 'get_game_state',
                            invitation_id: invitationId
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.game_state && data.game_state.difficulty) {
                            console.log('初始困難度同步檢查成功:', data.game_state.difficulty);
                            if (data.game_state.difficulty !== currentDifficulty) {
                                console.log('檢測到困難度不一致，強制同步');
                                currentDifficulty = data.game_state.difficulty;
                                adjustGameBoardSize();
                                forceRefreshGameBoard();
                            }
                        }
                    })
                    .catch(error => {
                        console.error('初始困難度同步檢查失敗:', error);
                    });
                }, 200);
                
                // 延遲進行退出檢測，確保遊戲已經初始化（已禁用）
                setTimeout(() => {
                    checkForPlayerQuit();
                }, 5000); // 增加到5秒，但函數已禁用
                // 初始同步玩家名字
                setTimeout(() => {
                    syncGameState();
                }, 500);
            }
        }).catch(error => {
            console.error('更新邀請設定失敗:', error);
            // 即使失敗也嘗試開始遊戲
            const isInviter = getCurrentMemberId() == invitationData?.from_user_id;
            
            // 獲取當前用戶名
            let currentUserDisplayName = '玩家';
            if (typeof currentUserName !== 'undefined' && currentUserName && currentUserName !== '玩家') {
                currentUserDisplayName = currentUserName;
            } else {
                currentUserDisplayName = `玩家${getCurrentMemberId()}`;
            }
            
            if (isInviter) {
                player1Name = currentUserDisplayName;
                player2Name = invitedFriendName || '被邀請者';
    } else {
                player1Name = invitedFriendName || '邀請者';
                player2Name = currentUserDisplayName;
            }
            
            // 立即更新顯示
            updatePlayerDisplay();
            forceUpdatePlayerNames();
            
            document.getElementById('game-container').classList.remove('hidden');
            gameStartTimestamp = Date.now();
            initializeGame();
            
            // 設定為邀請者的回合並開始同步
            isMyTurn = isInviter;
            if (gameMode === 'online') {
                startGameSync();
                // 延遲進行退出檢測，確保遊戲已經初始化
                setTimeout(() => {
                    checkForPlayerQuit();
                }, 5000); // 增加到5秒
                // 初始同步玩家名字
                setTimeout(() => {
                    syncGameState();
                }, 500);
            }
        });
    } else {
        // 本地模式：顯示玩家設定
    document.getElementById('player-setup-modal').classList.remove('hidden');
    }
}

// 頁面載入完成後初始化
document.addEventListener('DOMContentLoaded', function() {
    // 初始化當前用戶ID
    currentUserId = getCurrentMemberId();
    
    // 設置預設主題顏色
    if (typeof themes !== 'undefined' && themes.length > 0) {
        const defaultTheme = themes.find(t => t.theme_name === 'fruit') || themes[0];
        if (defaultTheme) {
            const themeStyle = JSON.parse(defaultTheme.theme_style);
            document.documentElement.style.setProperty('--card-back-color', themeStyle.cardBack);
            document.documentElement.style.setProperty('--card-front-color', themeStyle.cardFront);
            document.documentElement.style.setProperty('--matched-color', themeStyle.matched);
            document.documentElement.style.setProperty('--background-color', themeStyle.background);
            document.documentElement.style.setProperty('--container-color', themeStyle.container);
        }
    }
    
    // 顯示好友邀請視窗
    document.getElementById('friend-invite-modal').classList.remove('hidden');
    
    // 監聽視窗大小變化
    window.addEventListener('resize', adjustGameBoardSize);
    
    // 初始化遊戲板大小
    adjustGameBoardSize();
    
    // 開始定期檢查收到的邀請
    startCheckingReceivedInvitations();
});

// 定期檢查收到的邀請
function startCheckingReceivedInvitations() {
    setInterval(() => {
        checkReceivedInvitations();
    }, 3000); // 每3秒檢查一次
}

// 檢查收到的邀請
function checkReceivedInvitations() {
    fetch('game-invitation-api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
        },
        credentials: 'include',
        body: JSON.stringify({
            action: 'get_pending_invitations'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.invitations && data.invitations.length > 0) {
            // 只顯示 pending 狀態的邀請
            const pendingInvitations = data.invitations.filter(inv => inv.status === 'pending');
            
            if (pendingInvitations.length > 0) {
                // 顯示第一個收到的邀請
                const invitation = pendingInvitations[0];
                showReceivedInvitation(invitation);
            }
        }
    })
    .catch(error => {
        console.error('檢查收到邀請錯誤:', error);
    });
}

// 檢查邀請是否被取消（被邀請者用）
function checkInvitationCancelled() {
    if (!window.currentInvitation) return;
    
    fetch('game-invitation-api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
        },
        credentials: 'include',
        body: JSON.stringify({
            action: 'check_invitation',
            invitation_id: window.currentInvitation.invitation_id
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (data.status === 'cancelled') {
                // 邀請被取消
                hideWaitingModal();
                
                // 檢查是否在等待遊戲開始階段
                const gameContainer = document.getElementById('game-container');
                const isWaitingForGame = gameContainer && gameContainer.classList.contains('hidden');
                
                // 檢查是否在邀請好友視窗
                const friendInviteModal = document.getElementById('friend-invite-modal');
                const isInInviteScreen = friendInviteModal && !friendInviteModal.classList.contains('hidden');
                
                // 只有在等待遊戲開始且不在邀請視窗時才顯示訊息
                if (isWaitingForGame && !isInInviteScreen) {
                    console.log('邀請被取消，但遊戲未開始，靜默處理');
                    // 靜默處理，不顯示彈出視窗
                } else if (!isWaitingForGame) {
                    // 如果遊戲已經開始，顯示退出訊息
                    console.log('遊戲進行中，邀請被取消，顯示退出訊息');
                    hideAllModals();
                    document.getElementById('player-quit-modal').classList.remove('hidden');
                }
                
                window.currentInvitation = null;
            } else if (data.status === 'quit') {
                // 邀請者退出
                hideWaitingModal();
                
                // 檢查是否在遊戲進行中
                const gameContainer = document.getElementById('game-container');
                const isGameActive = gameContainer && !gameContainer.classList.contains('hidden') && 
                                   gameStartTimestamp && canFlip;
                
                if (isGameActive) {
                    console.log('遊戲進行中，對手退出，顯示退出訊息');
                    hideAllModals();
                    document.getElementById('player-quit-modal').classList.remove('hidden');
                } else {
                    console.log('遊戲未開始，對手退出，靜默處理');
                    // 靜默處理，不顯示彈出視窗
                }
                
                window.currentInvitation = null;
            }
        }
    })
    .catch(error => {
        console.error('檢查邀請取消狀態錯誤:', error);
    });
}

// 更新邀請設定
function updateInvitationSettings() {
    if (!invitationId) {
        return Promise.reject('沒有邀請ID');
    }
    
    const gameSettingsData = {
        action: 'update_invitation_settings',
        invitation_id: invitationId,
        game_settings: {
            theme: currentTheme,
            difficulty: currentDifficulty
        }
    };
    
    console.log('發送遊戲設定:', {
        currentTheme: currentTheme,
        currentDifficulty: currentDifficulty,
        fullData: gameSettingsData
    });
    
    return     fetch('game-invitation-api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
        },
        credentials: 'include',
        body: JSON.stringify(gameSettingsData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('邀請設定已更新');
            // 設定已更新，遊戲可以開始
            console.log('遊戲設定完成，可以開始遊戲');
            return data;
    } else {
            console.error('更新邀請設定失敗:', data.message);
            throw new Error(data.message);
        }
    })
    .catch(error => {
        console.error('更新邀請設定錯誤:', error);
        throw error;
    });
}

// 開始檢查遊戲開始的輪詢
function startGameStartPolling(invitationId) {
    const gameStartInterval = setInterval(() => {
        checkGameStart(invitationId, gameStartInterval);
    }, 2000);
}

// 檢查遊戲是否開始
function checkGameStart(invitationId, interval) {
    fetch('game-invitation-api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
        },
        credentials: 'include',
        body: JSON.stringify({
            action: 'check_invitation',
            invitation_id: invitationId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.invitation.game_settings) {
            // 遊戲設定已準備好，開始遊戲
            clearInterval(interval);
            hideWaitingModal();
            startOnlineGame(data.invitation);
        }
    })
    .catch(error => {
        console.error('檢查遊戲開始錯誤:', error);
    });
}

// 開始檢查遊戲設定的輪詢
function startGameSettingsPolling(invitationId) {
    // 立即檢查一次
    checkGameSettings(invitationId, null);
    
    const settingsInterval = setInterval(() => {
        checkGameSettings(invitationId, settingsInterval);
        // 同時檢查是否被取消
        checkInvitationCancelled();
    }, 200); // 提高到每0.2秒檢查一次，確保更快速的同步
}

// 檢查遊戲設定
function checkGameSettings(invitationId, interval) {
    console.log('檢查遊戲設定，邀請ID:', invitationId);
    
    fetch('game-invitation-api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
        },
        credentials: 'include',
        body: JSON.stringify({
            action: 'check_invitation',
            invitation_id: invitationId
        })
    })
    .then(response => response.json())
    .then(data => {
        console.log('檢查遊戲設定回應:', data);
        console.log('回應中的遊戲設定:', {
            gameSettings: data.game_settings,
            invitationGameSettings: data.invitation?.game_settings,
            fullData: data
        });
        if (data.success) {
            // 檢查邀請狀態是否為 'quit'
            if (data.status === 'quit' || (data.invitation && data.invitation.status === 'quit')) {
                console.log('邀請者已退出對戰，停止等待');
                clearInterval(interval);
                // 隱藏等待視窗
                hideAllModals();
                // 顯示邀請者退出視窗
                document.getElementById('player-quit-modal').classList.remove('hidden');
                return;
            }
            // 檢查是否有遊戲設定
            const gameSettings = data.game_settings || (data.invitation && data.invitation.game_settings) || {};
            console.log('遊戲設定檢查:', {
                dataGameSettings: data.game_settings,
                invitationGameSettings: data.invitation?.game_settings,
                finalGameSettings: gameSettings
            });
            if (gameSettings && gameSettings.theme && gameSettings.difficulty) {
                console.log('找到完整的遊戲設定，準備應用:', gameSettings);
                console.log('收到完整的遊戲設定:', gameSettings);
                
                // 檢查困難度是否有變更
                if (gameSettings.difficulty && gameSettings.difficulty !== currentDifficulty) {
                    console.log('檢測到困難度變更:', currentDifficulty, '->', gameSettings.difficulty);
                }
            } else {
                console.log('遊戲設定不完整，繼續等待...', gameSettings);
                return;
            }
            // 清除輪詢
            clearInterval(interval);
            // 設定邀請數據
            invitationId = invitationId || data.invitation?.invitation_id;
            invitationData = data.invitation;
            window.currentInvitation = data.invitation;
            // 確保遊戲模式為線上
            gameMode = 'online';
            // 應用遊戲設定
            currentTheme = gameSettings.theme || 'fruit';
            currentDifficulty = gameSettings.difficulty || 'easy';
            console.log('接收到的遊戲設定:', {
                originalGameSettings: gameSettings,
                theme: gameSettings.theme,
                difficulty: gameSettings.difficulty,
                appliedTheme: currentTheme,
                appliedDifficulty: currentDifficulty
            });
            // 應用主題設定
            const themeData = themes.find(t => t.theme_name === currentTheme);
            if (themeData) {
                const themeStyle = JSON.parse(themeData.theme_style);
                // 更新卡片顏色
                document.documentElement.style.setProperty('--card-back-color', themeStyle.cardBack);
                document.documentElement.style.setProperty('--card-front-color', themeStyle.cardFront);
                document.documentElement.style.setProperty('--matched-color', themeStyle.matched);
                document.documentElement.style.setProperty('--background-color', themeStyle.background);
                document.documentElement.style.setProperty('--container-color', themeStyle.container);
                console.log('主題已應用:', currentTheme, themeStyle);
            }
            // 根據難度設定遊戲參數
            switch (currentDifficulty) {
                case 'easy':
                    gridSize = 3;
                    break;
                case 'normal':
                    gridSize = 4;
                    break;
                case 'hard':
                    gridSize = 8;
                    break;
                default:
                    gridSize = 4;
            }
            
            console.log('被邀人應用困難度設定:', { currentDifficulty, gridSize });
            console.log('困難度設定已應用,遊戲板大小已調整:', currentDifficulty, gridSize);
            console.log('應用遊戲設定:', {
                theme: currentTheme,
                difficulty: currentDifficulty,
                gridSize: gridSize,
                invitationId: invitationId
            });
            
            // 立即調整遊戲板大小
            adjustGameBoardSize();
            
            // 強制重新整理遊戲板
            setTimeout(() => {
                forceRefreshGameBoard();
                console.log('困難度設定已強制應用，遊戲板大小已調整');
                
                // 強制同步困難度設定到伺服器
                if (gameMode === 'online' && invitationId) {
                    syncDifficultyImmediately();
                    console.log('困難度設定已強制同步到伺服器');
                }
            }, 100);
            
            // 檢查是否為邀請者
            const currentIsInviter = getCurrentMemberId() == (window.currentInvitation?.from_user_id || data.invitation?.from_user_id);
            
            if (currentIsInviter) {
                // 邀請者：隱藏所有視窗並顯示遊戲界面
            hideAllModals();
            
            // 額外確保隱藏所有可能的邀請視窗
            const allModals = document.querySelectorAll('.modal, [id*="modal"], [id*="invite"], [id*="friend"]');
            allModals.forEach(modal => {
                if (modal.classList.contains('hidden') === false) {
                    modal.classList.add('hidden');
                        console.log('邀請者：強制隱藏視窗:', modal.id || modal.className);
                }
            });
            
            // 顯示遊戲界面
            const gameContainer = document.getElementById('game-container');
            if (gameContainer) {
                gameContainer.classList.remove('hidden');
                }
            } else {
                // 被邀請者：收到遊戲設定後也立即進入遊戲
                console.log('被邀請者：收到遊戲設定，立即進入遊戲');
                
                // 隱藏等待視窗
                hideAllModals();
                
                // 額外確保隱藏所有可能的邀請視窗
                const allModals = document.querySelectorAll('.modal, [id*="modal"], [id*="invite"], [id*="friend"]');
                allModals.forEach(modal => {
                    if (modal.classList.contains('hidden') === false) {
                        modal.classList.add('hidden');
                        console.log('被邀請者：強制隱藏視窗:', modal.id || modal.className);
                    }
                });
                
                // 顯示遊戲界面
                const gameContainer = document.getElementById('game-container');
                if (gameContainer) {
                    gameContainer.classList.remove('hidden');
                }
            }
            
            // 開始遊戲
            gameStartTimestamp = Date.now();
            initializeGame();
            
            // 立即同步困難度設定
            if (gameMode === 'online' && invitationId) {
                // 立即同步一次
                syncDifficultyImmediately();
                console.log('遊戲開始時立即同步困難度設定');
                
                // 延遲再次同步，確保對手收到
                setTimeout(() => {
                    syncDifficultyImmediately();
                    console.log('遊戲開始後延遲同步困難度設定');
                }, 1000);
            }
            
            // 根據困難度調整遊戲板大小
            setTimeout(() => {
                forceRefreshGameBoard();
                console.log('困難度設定已應用，遊戲板大小已調整');
                
                // 強制同步困難度設定到伺服器
                if (gameMode === 'online' && invitationId) {
                    syncDifficultyImmediately();
                }
            }, 200);
            
            // 設定回合（邀請者先開始）
            isMyTurn = currentIsInviter; // 邀請者先開始
            currentPlayer = 1; // 確保從玩家1開始
            
            // 確保翻牌權限正確設定
            canFlip = isMyTurn;
            console.log('設定回合和翻牌權限:', { isInviter: currentIsInviter, isMyTurn, canFlip });
            // 設定玩家名字
            let currentUserDisplayName = '玩家';
            if (typeof currentUserName !== 'undefined' && currentUserName && currentUserName !== '玩家') {
                currentUserDisplayName = currentUserName;
            } else {
                currentUserDisplayName = `玩家${getCurrentMemberId()}`;
            }
            if (currentIsInviter) {
                // 邀請者：玩家1是自己，玩家2是好友
                player1Name = currentUserDisplayName;
                player2Name = data.invitation?.to_user_name || window.currentInvitation?.to_user_name || '被邀請者';
            } else {
                // 被邀請者：玩家1是好友，玩家2是自己
                player1Name = data.invitation?.from_user_name || window.currentInvitation?.from_user_name || '邀請者';
                player2Name = currentUserDisplayName;
            }
            console.log('設定玩家名字:', {
                player1Name, player2Name,
                currentUserDisplayName,
                isInviter: currentIsInviter,
                from_user_name: data.invitation?.from_user_name,
                to_user_name: data.invitation?.to_user_name
            });
            // 立即更新顯示
            updatePlayerDisplay();
            forceUpdatePlayerNames();
            console.log('開始遊戲，我的回合:', isMyTurn, '當前玩家:', currentPlayer, '是邀請者:', currentIsInviter);
            // 開始遊戲同步
            if (gameMode === 'online') {
                startGameSync();
                // 延遲進行退出檢測，確保遊戲已經初始化
                setTimeout(() => {
                    checkForPlayerQuit();
                }, 5000); // 增加到5秒
                // 初始同步玩家名字
                setTimeout(() => {
                    syncGameState();
                }, 500);
            }
        } else {
            console.error('檢查遊戲設定失敗:', data.message);
        }
    })
    .catch(error => {
        console.error('檢查遊戲設定錯誤:', error);
    });
} 

// 檢查是否有已接受的邀請
function checkForAcceptedInvitations() {
    console.log('檢查是否有已接受的邀請...');
    
    fetch('game-invitation-api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
        },
        credentials: 'include',
        body: JSON.stringify({
            action: 'get_pending_invitations'
        })
    })
    .then(response => response.json())
    .then(data => {
        console.log('收到的邀請數據:', data);
        
        if (data.success && data.invitations && data.invitations.length > 0) {
            // 檢查是否有邀請者退出的邀請（暫時禁用）
            const quitInvitation = data.invitations.find(inv => inv.status === 'quit');
            if (quitInvitation) {
                console.log('退出邀請檢測已禁用，避免誤判');
                // 暫時禁用退出邀請檢測
                /*
                console.log('發現退出狀態的邀請:', quitInvitation);
                // 只有在遊戲真正開始後才顯示退出視窗
                // 檢查是否有遊戲容器顯示且遊戲已初始化
                const gameContainer = document.getElementById('game-container');
                const isGameActive = gameContainer && !gameContainer.classList.contains('hidden') && 
                                   gameStartTimestamp && canFlip;
                
                if (isGameActive) {
                    console.log('遊戲進行中，顯示退出視窗');
                    hideAllModals();
                    document.getElementById('player-quit-modal').classList.remove('hidden');
                } else {
                    console.log('遊戲未開始或剛開始，忽略退出狀態，清除該邀請');
                    // 清除這個有問題的 quit 邀請
                    clearQuitInvitation(quitInvitation.invitation_id);
                }
                return;
                */
            }
            
            // 檢查是否有已接受且有遊戲設定的邀請（直接開始遊戲）
            const acceptedWithSettings = data.invitations.find(inv => 
                inv.status === 'accepted' && inv.game_settings && inv.game_settings !== 'null'
            );
            
            if (acceptedWithSettings) {
                console.log('找到已接受且有遊戲設定的邀請，檢查邀請有效性:', acceptedWithSettings);
                
                // 檢查邀請是否在合理時間內（5分鐘內）
                const invitationTime = new Date(acceptedWithSettings.last_updated || acceptedWithSettings.created_at);
                const currentTime = new Date();
                const timeDiff = currentTime - invitationTime;
                const fiveMinutes = 5 * 60 * 1000; // 5分鐘
                
                if (timeDiff > fiveMinutes) {
                    console.log('邀請已過期，忽略:', timeDiff, 'ms');
                    return;
                }
                
                console.log('邀請有效，檢查是否應該自動開始遊戲');
                
                // 檢查是否已經在遊戲中
                const existingGameContainer = document.getElementById('game-container');
                const isAlreadyInGame = existingGameContainer && !existingGameContainer.classList.contains('hidden');
                
                if (isAlreadyInGame) {
                    console.log('已經在遊戲中，跳過自動開始');
                    return;
                }
                
                // 設定遊戲模式
                gameMode = 'online';
                invitationId = acceptedWithSettings.invitation_id;
                invitationData = acceptedWithSettings;
                window.currentInvitation = acceptedWithSettings;
                
                // 解析遊戲設定
                const parsedGameSettings = JSON.parse(acceptedWithSettings.game_settings);
                const gameSettings = parsedGameSettings;
                currentTheme = gameSettings.theme || 'fruit';
                currentDifficulty = gameSettings.difficulty || 'easy';
                
                console.log('被邀人同步遊戲設定:', { currentTheme, currentDifficulty });
                
                // 根據困難度調整遊戲板大小
                switch (currentDifficulty) {
                    case 'easy':
                        gridSize = 3;
                        break;
                    case 'normal':
                        gridSize = 4;
                        break;
                    case 'hard':
                        gridSize = 8;
                        break;
                    default:
                        gridSize = 4;
                }
                
                console.log('被邀人困難度設定已應用:', { currentDifficulty, gridSize });
                
                // 檢查是否應該等待邀請者設定
                const hasValidSettings = gameSettings && gameSettings.difficulty && gameSettings.theme;
                
                if (!hasValidSettings) {
                    console.log('遊戲設定不完整，等待邀請者設定');
                    // 顯示等待視窗
                    const waitingModal = document.getElementById('waiting-modal');
                    const waitingTitle = document.getElementById('waiting-title');
                    const waitingMessage = document.getElementById('waiting-message');
                    
                    if (waitingModal) waitingModal.classList.remove('hidden');
                    if (waitingTitle) waitingTitle.textContent = '等待遊戲設定';
                    if (waitingMessage) waitingMessage.textContent = '正在等待邀請者設定遊戲...';
                    return;
                }
                
                console.log('遊戲設定完整，開始遊戲');
                
                // 隱藏所有視窗並顯示遊戲界面
                hideAllModals();
                const gameContainer = document.getElementById('game-container');
                if (gameContainer) {
                    gameContainer.classList.remove('hidden');
                }
                
                // 開始遊戲
                gameStartTimestamp = Date.now();
                initializeGame();
                
                // 設定回合
                const isInviter = getCurrentMemberId() == acceptedWithSettings.from_user_id;
                isMyTurn = isInviter; // 邀請者先開始
                currentPlayer = isInviter ? 1 : 2; // 邀請者是玩家1，被邀請者是玩家2
                
                console.log('被邀人進入遊戲，回合設定:', { isInviter, isMyTurn, currentPlayer });
                
                // 使用 WebSocket 加入遊戲房間
                if (window.memoryGameWebSocket && window.memoryGameWebSocket.isConnected) {
                    window.memoryGameWebSocket.joinGame(invitationId, getCurrentMemberId());
                    console.log('已加入 WebSocket 遊戲房間');
                }
                
                // 強制調整遊戲板大小
                setTimeout(() => {
                    adjustGameBoardSize();
                    forceRefreshGameBoard();
                    console.log('被邀人遊戲板大小調整完成');
                    
                    // 強制同步遊戲狀態
                    if (gameMode === 'online' && invitationId) {
                        syncGameState('invitation_join');
                        console.log('被邀人強制同步遊戲狀態');
                    }
                }, 100);
                
                // 設定玩家名字
                let currentUserDisplayName = '玩家';
                if (typeof currentUserName !== 'undefined' && currentUserName && currentUserName !== '玩家') {
                    currentUserDisplayName = currentUserName;
                } else {
                    currentUserDisplayName = `玩家${getCurrentMemberId()}`;
                }
                
                if (isInviter) {
                    // 邀請者：玩家1是自己，玩家2是好友
                    player1Name = currentUserDisplayName;
                    player2Name = acceptedWithSettings.to_user_name || '被邀請者';
                } else {
                    // 被邀請者：玩家1是好友，玩家2是自己
                    player1Name = acceptedWithSettings.from_user_name || '邀請者';
                    player2Name = currentUserDisplayName;
                }
                
                console.log('設定玩家名字:', { 
                    player1Name, player2Name, 
                    currentUserDisplayName, 
                    isInviter,
                    from_user_name: acceptedWithSettings.from_user_name,
                    to_user_name: acceptedWithSettings.to_user_name
                });
                
                // 立即更新顯示
                updatePlayerDisplay();
                forceUpdatePlayerNames();
                
                console.log('自動開始遊戲，我的回合:', isMyTurn, '當前玩家:', currentPlayer, '是邀請者:', isInviter);
                
                // 開始遊戲同步
                if (gameMode === 'online') {
                    startGameSync();
                    // 延遲進行退出檢測，確保遊戲已經初始化（已禁用）
                    setTimeout(() => {
                        checkForPlayerQuit();
                    }, 5000); // 增加到5秒，但函數已禁用
                    setTimeout(() => {
                        syncGameState();
                    }, 500);
                }
            } else {
                console.log('沒有找到已接受且有遊戲設定的邀請');
            }
        } else {
            console.log('沒有待處理的邀請');
        }
    })
    .catch(error => {
        console.error('檢查邀請失敗:', error);
    });
}

// 隱藏所有視窗
function hideAllModals() {
    const modals = [
        'invite-friend-modal',
        'theme-modal',
        'difficulty-modal',
        'waiting-modal',
        'received-invitation-modal',
        'player-setup-modal',
        'friend-invite-modal'
    ];
    
    modals.forEach(modalId => {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('hidden');
            console.log('隱藏視窗:', modalId);
        }
    });
}

// 開始回合計時器（修復版本）
function startTurnTimer() {
    // 防止重複啟動計時器
    if (turnTimer || isTurnActive) {
        console.log('🛑 計時器已在運行，跳過重複啟動');
        return;
    }
    
    // 先停止現有計時器（以防萬一）
    if (turnTimer) {
        clearInterval(turnTimer);
        turnTimer = null;
    }
    
    // 重置計時器時間
    turnTimeLeft = 10;
    isTurnActive = true;
    
    console.log('🕐 重置計時器時間為:', turnTimeLeft);
    
    const timerElement = document.getElementById('turn-timer');
    if (timerElement) {
        timerElement.textContent = turnTimeLeft;
        timerElement.className = ''; // 清除警告樣式
    }
    
    console.log('🕐 開始回合計時器，剩餘時間:', turnTimeLeft);
    
    turnTimer = setInterval(() => {
        turnTimeLeft--;
        
        if (timerElement) {
            timerElement.textContent = turnTimeLeft;
            
            // 添加視覺警告
            if (turnTimeLeft <= 3) {
                timerElement.className = 'danger';
            } else if (turnTimeLeft <= 5) {
                timerElement.className = 'warning';
            }
        }
        
        console.log('🕐 回合計時器:', turnTimeLeft);
        
        if (turnTimeLeft <= 0) {
            clearInterval(turnTimer);
            turnTimer = null;
            isTurnActive = false;
            
            // 時間到，切換玩家
            console.log('⏰ 回合時間到，切換玩家');
            
            // 蓋回所有翻開的卡片
            flippedCards.forEach(card => {
                card.classList.remove('flipped');
            });
            flippedCards = [];
            
            // 切換玩家
            switchPlayer();
            
            // 重要：停止當前計時器，避免重複啟動
            return;
        }
    }, 1000);
}

// 停止回合計時器（修復版本）
function stopTurnTimer() {
    if (turnTimer) {
        clearInterval(turnTimer);
        turnTimer = null;
        console.log('🛑 回合計時器已停止');
    }
    isTurnActive = false;
    
    const timerElement = document.getElementById('turn-timer');
    if (timerElement) {
        timerElement.textContent = '10';
        timerElement.className = ''; // 清除警告樣式
    }
}

// 切換玩家
function switchPlayer() {
    console.log('開始切換玩家，當前狀態:', {
        currentPlayer,
        isMyTurn,
        canFlip,
        flippedCards: flippedCards.length,
        gameMode,
        invitationData
    });
    
    // 蓋回所有翻開的卡片
    flippedCards.forEach(card => {
        card.classList.remove('flipped');
    });
    flippedCards = [];
    
    // 切換當前玩家
    const oldPlayer = currentPlayer;
    currentPlayer = currentPlayer === 1 ? 2 : 1;
    consecutiveMatches = 0;
    
    console.log('玩家切換:', { oldPlayer, newPlayer: currentPlayer });
    
    // 檢查當前用戶是邀請者還是被邀請者
        const isInviter = getCurrentMemberId() == invitationData?.from_user_id;
    
    // 重置翻牌權限
    if (gameMode === 'online') {
        // 線上模式：根據回合狀態設置
        isMyTurn = (currentPlayer === 1 && isInviter) || (currentPlayer === 2 && !isInviter);
        canFlip = isMyTurn;
    } else {
        // 本地模式：始終可以翻牌
        canFlip = true;
        isMyTurn = true;
    }
    
    // 如果是線上模式，切換回合並同步
    if (gameMode === 'online') {
        
        console.log('切換回合詳細信息:', {
            currentPlayer,
            isInviter,
            isMyTurn,
            currentUserId: getCurrentMemberId(),
            fromUserId: invitationData?.from_user_id,
            canFlip: canFlip
        });
        
        // 使用 WebSocket 同步回合切換
        if (window.memoryGameWebSocket && window.memoryGameWebSocket.isConnected) {
            window.memoryGameWebSocket.switchTurn(
                invitationId,
                getCurrentMemberId(),
                currentPlayer
            );
        }
        
        console.log('玩家切換完成，已同步回合');
    } else {
        // 本地模式：所有玩家都可以翻牌，不需要檢查isMyTurn
        isMyTurn = true;
    }
    
    // 更新顯示
    updateCurrentPlayer();
    
    // 重新開始計時器（確保只有一個計時器運行）
    stopTurnTimer(); // 先停止現有計時器
    
    // 重置計時器時間為10秒
    turnTimeLeft = 10;
    isTurnActive = false; // 確保計時器狀態重置
    
    if (gameMode === 'local' || (gameMode === 'online' && isMyTurn)) {
        // 延遲啟動計時器，避免重複
        setTimeout(() => {
            if (!turnTimer && !isTurnActive) {
                startTurnTimer();
            }
        }, 200);
    }
    
    console.log('切換完成，最終狀態:', {
        currentPlayer,
        isMyTurn,
        canFlip,
        gameMode
    });
}

// 調試函數：強制修復回合狀態
function forceFixTurnState() {
    console.log('強制修復回合狀態');
    
    // 檢查當前用戶是邀請者還是被邀請者
    const isInviter = getCurrentMemberId() == invitationData?.from_user_id;
    
    // 根據當前玩家重新計算isMyTurn
    isMyTurn = (currentPlayer === 1 && isInviter) || (currentPlayer === 2 && !isInviter);
    
    console.log('修復後的回合狀態:', {
        currentPlayer,
        isMyTurn,
        isInviter,
        currentUserId: getCurrentMemberId(),
        fromUserId: invitationData?.from_user_id
    });
}

// 強制修復困難度同步
function forceFixDifficultySync() {
    console.log('強制修復困難度同步');
    
    // 確保困難度設定正確
    if (currentDifficulty === 'hard') {
        const gameBoard = document.getElementById('game-board');
        if (gameBoard) {
            // 強制設定困難模式網格
            gameBoard.style.gridTemplateColumns = 'repeat(8, 1fr)';
            gameBoard.style.gridTemplateRows = 'repeat(4, 1fr)';
            gameBoard.style.maxWidth = '1000px';
            gameBoard.style.maxHeight = '500px';
            gameBoard.style.overflow = 'hidden';
            
            console.log('強制修復困難模式網格佈局');
        }
        
        // 確保全局變數正確
        window.currentDifficulty = 'hard';
        window.gridSize = 8;
        
        // 重新創建遊戲板
        setTimeout(() => {
            if (typeof createCards === 'function') {
                createCards();
            }
        }, 100);
    }
}




    
    // 立即觸發同步
    if (gameMode === 'online' && invitationId) {
        // 停止當前同步
        if (window.currentGameSyncController) {
            window.currentGameSyncController.abort();
        }
        
        // 立即開始新的同步
        setTimeout(() => {
            fetch('game-sync-api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'get_game_state',
                    invitation_id: invitationId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('強制同步成功:', data);
                    updateGameFromSync(data.game_state);
                } else {
                    console.error('強制同步失敗:', data.message);
                }
            })
            .catch(error => {
                console.error('強制同步錯誤:', error);
            });
        }, 100);
    }
    

    console.log('=== 檢查翻牌權限狀態 ===');
    const isInviter = getCurrentMemberId() == invitationData?.from_user_id;
    console.log('當前狀態:', {
        gameMode: gameMode,
        currentPlayer: currentPlayer,
        isMyTurn: isMyTurn,
        canFlip: canFlip,
        isInviter: isInviter,
        currentUserId: getCurrentMemberId(),
        fromUserId: invitationData?.from_user_id,
        flippedCards: flippedCards.length,
        isCheckingMatch: window.isCheckingMatch
    });
    
    // 檢查翻牌權限是否正確
    if (gameMode === 'online') {
        const expectedCanFlip = isMyTurn;
        if (canFlip !== expectedCanFlip) {
            console.warn('⚠️ 翻牌權限不一致！', {
                expected: expectedCanFlip,
                actual: canFlip,
                isMyTurn: isMyTurn
            });
        } else {
            console.log('✅ 翻牌權限正確');
        }
    };


// 新增：強制修復翻牌權限
window.fixFlipPermission = function() {
    console.log('=== 強制修復翻牌權限 ===');
    const isInviter = getCurrentMemberId() == invitationData?.from_user_id;
    
    if (gameMode === 'online') {
        isMyTurn = (currentPlayer === 1 && isInviter) || (currentPlayer === 2 && !isInviter);
        canFlip = isMyTurn;
        console.log('修復後狀態:', { isMyTurn, canFlip, currentPlayer, isInviter });
    } else {
        canFlip = true;
        isMyTurn = true;
        console.log('本地模式修復完成');
    }
};

// 新增：強制修復困難度同步
window.fixDifficultySync = function() {
    console.log('=== 強制修復困難度同步 ===');
    console.log('當前困難度:', currentDifficulty);
    console.log('當前gridSize:', gridSize);
    
    // 強制重新設置困難度
    if (currentDifficulty === 'hard') {
        gridSize = 8;
        console.log('強制設置困難模式: 8x4 = 32張卡片');
    } else if (currentDifficulty === 'easy') {
        gridSize = 4;
        console.log('強制設置簡單模式: 4x3 = 12張卡片');
    } else {
        gridSize = 4;
        console.log('強制設置普通模式: 4x4 = 16張卡片');
    }
    
    // 重新創建遊戲板
    const gameBoard = document.getElementById('game-board');
    if (gameBoard) {
        gameBoard.innerHTML = '';
        createCards();
        adjustGameBoardSize();
        console.log('遊戲板已重新創建');
    }
};

// 新增：強制重置遊戲狀態
window.forceResetGame = function() {
    console.log('=== 強制重置遊戲狀態 ===');
    
    if (gameMode === 'online' && invitationId) {
        // 使用強制同步API重置遊戲
        fetch('force_sync_fix.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'reset_game_state',
                invitation_id: invitationId,
                difficulty: currentDifficulty,
                theme: currentTheme
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('遊戲狀態重置成功:', data);
                // 重新初始化遊戲
                initializeGame();
            } else {
                console.error('遊戲狀態重置失敗:', data.message);
            }
        })
        .catch(error => {
            console.error('重置遊戲錯誤:', error);
        });
    } else {
        // 本地模式直接重新初始化
        initializeGame();
    }
};

// 檢查同步狀態
window.checkSyncStatus = function() {
    console.log('=== 檢查同步狀態 ===');
    console.log('遊戲模式:', gameMode);
    console.log('邀請ID:', invitationId);
    console.log('當前玩家:', currentPlayer);
    console.log('是否我的回合:', isMyTurn);
    console.log('翻牌數量:', flippedCards.length);
    console.log('配對數量:', matchedPairs);
    
    // 檢查所有卡片狀態
    const allCards = document.querySelectorAll('.card');
    console.log('總卡片數量:', allCards.length);
    
    allCards.forEach((card, index) => {
        console.log(`卡片 ${index}:`, {
            index: card.dataset.index,
            symbol: card.dataset.symbol,
            isFlipped: card.classList.contains('flipped'),
            isMatched: card.classList.contains('matched'),
            content: card.textContent || card.querySelector('.card-front')?.textContent
        });
    });
};


// 將調試函數暴露到全局，方便在控制台調用
window.forceFixTurnState = forceFixTurnState;

// 添加全局修復函數
window.fixGameStuck = function() {
    console.log('=== 修復遊戲卡住 ===');
    console.log('當前狀態:', {
        canFlip: canFlip,
        isMyTurn: isMyTurn,
        currentPlayer: currentPlayer,
        flippedCards: flippedCards.length,
        gameMode: gameMode
    });
    
    // 強制重置翻牌權限
    canFlip = true;
    
    // 強制修復回合狀態
    forceFixTurnState();
    
    // 清空翻開的卡片
    flippedCards = [];
    
    console.log('修復完成，當前狀態:', {
        canFlip: canFlip,
        isMyTurn: isMyTurn,
        currentPlayer: currentPlayer,
        flippedCards: flippedCards.length
    });
};

// 添加邀請狀態重置函數
window.resetInvitationState = function() {
    console.log('=== 重置邀請狀態 ===');
    
    // 停止所有計時器
    if (invitationCheckInterval) {
        clearInterval(invitationCheckInterval);
        invitationCheckInterval = null;
    }
    if (window.invitationTimeout) {
        clearTimeout(window.invitationTimeout);
        window.invitationTimeout = null;
    }
    
    // 重置邀請相關變數
    invitationId = null;
    invitationData = null;
    invitedFriendId = null;
    invitedFriendName = null;
    gameMode = 'local';
    
    // 隱藏所有相關視窗
    hideAllModals();
    
    // 顯示好友邀請視窗
    const friendInviteModal = document.getElementById('friend-invite-modal');
    if (friendInviteModal) {
        friendInviteModal.classList.remove('hidden');
    }
    
    console.log('邀請狀態已重置');
};

// 添加配對成功同步函數
window.syncMatchSuccess = function() {
    console.log('=== 強制同步配對成功 ===');
    
    // 強制更新所有顯示
    updatePlayerDisplay();
    forceUpdatePlayerNames();
    updateCurrentPlayer();
    
    // 更新總配對次數
    const totalMatchesDisplay = document.getElementById('total-moves');
    if (totalMatchesDisplay) {
        totalMatchesDisplay.textContent = matchedPairs;
    }
    
    // 確保翻牌權限正確
    canFlip = true;
    
    console.log('配對成功同步完成:', {
        player1Score,
        player2Score,
        player1Pairs,
        player2Pairs,
        matchedPairs,
        currentPlayer,
        isMyTurn,
        canFlip
    });
};

// 添加配對失敗同步函數
window.syncMatchFail = function() {
    console.log('=== 強制同步配對失敗 ===');
    
    // 蓋回所有未配對的卡片
    flippedCards.forEach(card => {
        if (card && !card.classList.contains('matched')) {
            card.classList.remove('flipped');
        }
    });
    flippedCards = [];
    
    // 強制更新所有顯示
    updatePlayerDisplay();
    forceUpdatePlayerNames();
    updateCurrentPlayer();
    
    // 確保翻牌權限正確
    canFlip = true;
    
    console.log('配對失敗同步完成:', {
        currentPlayer,
        isMyTurn,
        canFlip,
        flippedCards: flippedCards.length
    });
};

// 添加快速修復回合狀態的函數
window.quickFixTurn = function() {
    console.log('快速修復回合狀態...');
    
    // 檢查當前用戶是邀請者還是被邀請者
    const isInviter = getCurrentMemberId() == invitationData?.from_user_id;
    
    // 根據當前玩家重新計算isMyTurn
    isMyTurn = (currentPlayer === 1 && isInviter) || (currentPlayer === 2 && !isInviter);
    
    console.log('修復後的回合狀態:', {
        currentPlayer,
        isMyTurn,
        isInviter,
        currentUserId: getCurrentMemberId(),
        fromUserId: invitationData?.from_user_id
    });
    
    // 更新顯示
    updateCurrentPlayer();
    
    // 重新開始計時器（確保只有一個計時器運行）
    stopTurnTimer(); // 先停止現有計時器
    
    // 重置計時器時間為10秒
    turnTimeLeft = 10;
    isTurnActive = false; // 確保計時器狀態重置
    
    if (gameMode === 'local' || (gameMode === 'online' && isMyTurn)) {
        // 延遲啟動計時器，避免重複
        setTimeout(() => {
            if (!turnTimer && !isTurnActive) {
                startTurnTimer();
            }
        }, 200);
    }
    
    return isMyTurn;
};

// 添加更多調試函數
window.debugGameState = function() {
    console.log('當前遊戲狀態:', {
        canFlip: canFlip,
        isMyTurn: isMyTurn,
        currentPlayer: currentPlayer,
        gameMode: gameMode,
        flippedCards: flippedCards.length,
        matchedPairs: matchedPairs,
        currentUserId: getCurrentMemberId(),
        invitationData: invitationData
    });
};

window.resetCanFlip = function() {
    canFlip = true;
    console.log('已重置翻牌權限');
};

// 添加強制修復所有狀態的函數
window.forceFixAllState = function() {
    console.log('強制修復所有狀態');
    
    // 檢查當前用戶是邀請者還是被邀請者
    const isInviter = getCurrentMemberId() == invitationData?.from_user_id;
    
    // 重新計算回合狀態
    isMyTurn = (currentPlayer === 1 && isInviter) || (currentPlayer === 2 && !isInviter);
    
    // 重置翻牌權限
    canFlip = true;
    
    // 更新顯示
    updateCurrentPlayer();
    updatePlayerDisplay();
    
    console.log('修復後的狀態:', {
        currentPlayer,
        isMyTurn,
        isInviter,
        canFlip,
        currentUserId: getCurrentMemberId(),
        fromUserId: invitationData?.from_user_id
    });
    
    return { currentPlayer, isMyTurn, canFlip };
};

// 添加強制同步回合狀態的函數
window.forceSyncTurn = function() {
    console.log('強制同步回合狀態...');
    
    if (gameMode !== 'online' || !invitationId) {
        console.log('不是線上模式，無法同步');
        return;
    }
    
    // 檢查當前用戶是邀請者還是被邀請者
    const isInviter = getCurrentMemberId() == invitationData?.from_user_id;
    
    // 重新計算回合狀態
    isMyTurn = (currentPlayer === 1 && isInviter) || (currentPlayer === 2 && !isInviter);
    
    // 重置翻牌權限
    canFlip = true;
    
    // 立即同步狀態
    const gameState = {
        cards: cards.map(card => ({
            symbol: card.dataset.symbol,
            index: card.dataset.index,
            flipped: card.classList.contains('flipped'),
            matched: card.classList.contains('matched')
        })),
        flippedCards: flippedCards.map(card => card.dataset.index),
        matchedPairs: matchedPairs,
        totalMoves: totalMoves,
        currentPlayer: currentPlayer,
        player1Score: player1Score,
        player2Score: player2Score,
        player1Pairs: player1Pairs,
        player2Pairs: player2Pairs,
        lastAction: 'force_sync_turn',
        lastActionBy: getCurrentMemberId(),
        player1Name: player1Name,
        player2Name: player2Name,
        isMyTurn: isMyTurn,
        isInviter: isInviter,
        currentUserId: getCurrentMemberId(),
        fromUserId: invitationData?.from_user_id
    };
    
    console.log('發送強制同步數據:', gameState);
    
    fetch('game-sync-api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'update_game_state',
            invitation_id: invitationId,
            player_id: getCurrentMemberId(),
            game_state: gameState
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('強制同步回合成功');
            updateCurrentPlayer();
            updatePlayerDisplay();
        } else {
            console.error('強制同步回合失敗:', data.message);
        }
    })
    .catch(error => {
        console.error('強制同步回合錯誤:', error);
    });
};

// 添加詳細狀態檢查函數
window.checkAllStates = function() {
    const isInviter = getCurrentMemberId() == invitationData?.from_user_id;
    const shouldBeMyTurn = (currentPlayer === 1 && isInviter) || (currentPlayer === 2 && !isInviter);
    
    console.log('=== 詳細狀態檢查 ===');
    console.log('基本狀態:', {
        currentPlayer,
        isMyTurn,
        shouldBeMyTurn,
        canFlip,
        gameMode,
        flippedCards: flippedCards.length
    });
    
    console.log('用戶身份:', {
        currentUserId: getCurrentMemberId(),
        fromUserId: invitationData?.from_user_id,
        isInviter,
        player1Name,
        player2Name
    });
    
    console.log('回合邏輯:', {
        'currentPlayer === 1': currentPlayer === 1,
        'currentPlayer === 2': currentPlayer === 2,
        'isInviter': isInviter,
        'currentPlayer === 1 && isInviter': currentPlayer === 1 && isInviter,
        'currentPlayer === 2 && !isInviter': currentPlayer === 2 && !isInviter
    });
    
    console.log('狀態一致性:', {
        'isMyTurn === shouldBeMyTurn': isMyTurn === shouldBeMyTurn,
        '需要修復': isMyTurn !== shouldBeMyTurn
    });
    
    return {
        currentPlayer,
        isMyTurn,
        shouldBeMyTurn,
        canFlip,
        isInviter,
        needsFix: isMyTurn !== shouldBeMyTurn
    };
};

// 顯示好友拒絕邀請視窗
function showRejectModal() {
    const rejectModal = document.getElementById('friend-reject-modal');
    if (rejectModal) {
        rejectModal.classList.remove('hidden');
    } else {
        console.warn('找不到 friend-reject-modal 元素');
        // 如果找不到拒絕視窗，直接回到邀請視窗
        hideRejectModal();
    }
}

// 隱藏好友拒絕邀請視窗
function hideRejectModal() {
    const rejectModal = document.getElementById('friend-reject-modal');
    const friendInviteModal = document.getElementById('friend-invite-modal');
    const waitingModal = document.getElementById('waiting-modal');
    const receivedModal = document.getElementById('received-invitation-modal');
    const playerSetupModal = document.getElementById('player-setup-modal');
    const themeModal = document.getElementById('theme-modal');
    const difficultyModal = document.getElementById('difficulty-modal');
    
    // 隱藏拒絕視窗
    if (rejectModal) {
        rejectModal.classList.add('hidden');
    }
    
    // 確保回到好友邀請視窗
    if (friendInviteModal) {
        friendInviteModal.classList.remove('hidden');
    } else {
        console.warn('找不到 friend-invite-modal 元素');
    }
    
    // 確保其他相關視窗都被隱藏
    if (waitingModal) waitingModal.classList.add('hidden');
    if (receivedModal) receivedModal.classList.add('hidden');
    if (playerSetupModal) playerSetupModal.classList.add('hidden');
    if (themeModal) themeModal.classList.add('hidden');
    if (difficultyModal) difficultyModal.classList.add('hidden');
    
    // 重置邀請相關變數，確保可以再次邀請
    invitationId = null;
    invitationData = null;
    invitedFriendId = null;
    invitedFriendName = null;
    
    console.log('拒絕視窗已隱藏，邀請狀態已重置');
}

// 顯示返回確認對話框
function showReturnConfirmModal() {
    document.getElementById('return-confirm-modal').classList.remove('hidden');
}

// 隱藏返回確認對話框
function hideReturnConfirmModal() {
    document.getElementById('return-confirm-modal').classList.add('hidden');
}

// 確認返回
function confirmReturn() {
    // 清除 beforeunload 事件，避免瀏覽器警告
    if (typeof window.clearBeforeUnload === 'function') {
        window.clearBeforeUnload();
    }
    window.onbeforeunload = null;
    
    // 檢查是否在遊戲進行中
    const gameContainer = document.getElementById('game-container');
    const isGameActive = gameContainer && !gameContainer.classList.contains('hidden') && 
                       gameStartTimestamp && canFlip;
    
    if (isGameActive) {
        // 如果遊戲正在進行中，強制退出戰局
        forceQuitGame();
    } else if (gameMode === 'online' && invitationId) {
        // 如果只是有邀請但遊戲還沒開始，取消邀請
        console.log('取消邀請並返回');
        cancelInvitation();
    }
    
    hideReturnConfirmModal();
    
    // 延遲一下再返回，確保退出請求已發送
    setTimeout(() => {
        // 檢查當前在哪個視窗，決定返回位置
        const themeModal = document.getElementById('theme-modal');
        const difficultyModal = document.getElementById('difficulty-modal');
        
        if (themeModal && !themeModal.classList.contains('hidden')) {
            // 如果在主題選擇視窗，返回到邀請好友視窗
            themeModal.classList.add('hidden');
            document.getElementById('friend-invite-modal').classList.remove('hidden');
        } else if (difficultyModal && !difficultyModal.classList.contains('hidden')) {
            // 如果在難度選擇視窗，返回到主題選擇視窗
            difficultyModal.classList.add('hidden');
            themeModal.classList.remove('hidden');
        } else {
            // 其他情況返回主選單
            // 智能返回：回到上一頁，如果沒有上一頁則回到遊戲分類頁面
    if (document.referrer && document.referrer !== window.location.href) {
        history.back();
    } else {
        window.location.href = 'game-category.php';
    }
        }
    }, 500);
}

// 取消返回
function cancelReturn() {
    hideReturnConfirmModal();
}

// 顯示退出對戰確認視窗
function showExitBattleModal() {
    document.getElementById('exit-battle-modal').classList.remove('hidden');
}

// 隱藏退出對戰確認視窗
function hideExitBattleModal() {
    document.getElementById('exit-battle-modal').classList.add('hidden');
    // 返回主選單
    returnToMain();
}

// 處理返回按鈕點擊
function handleBackButton() {
    // 檢查是否在線上對戰中且遊戲已開始
    if (gameMode === 'online' && invitationId && gameStartTimestamp) {
        // 顯示自定義確認對話框
        showReturnConfirmModal();
    } else {
        // 直接返回主選單（包括邀請頁面）
        // 智能返回：回到上一頁，如果沒有上一頁則回到遊戲分類頁面
    if (document.referrer && document.referrer !== window.location.href) {
        history.back();
    } else {
        window.location.href = 'game-category.php';
    }
    }
}

// 清除有問題的 quit 邀請
function clearQuitInvitation(invitationId) {
    fetch('game-invitation-api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'update_invitation_status',
            invitation_id: invitationId,
            status: 'cancelled'
        })
    })
    .then(response => response.json())
    .then(data => {
        console.log('清除 quit 邀請結果:', data);
    })
    .catch(error => {
        console.error('清除 quit 邀請失敗:', error);
    });
}

// 自動修復函數 - 解決困難度同步和翻牌權限問題
function autoFixMemoryGame() {
    console.log('🔧 自動修復記憶遊戲...');
    
    // 1. 強制同步困難度
    if (typeof currentDifficulty !== 'undefined') {
        window.currentDifficulty = currentDifficulty;
        window.gridSize = gridSize;
        console.log('✅ 困難度已同步:', currentDifficulty, gridSize);
    }
    
    // 2. 修復翻牌權限
    if (typeof canFlip !== 'undefined') {
        if (gameMode === 'online' && (typeof isMyTurn === 'undefined' || isMyTurn === null)) {
            isMyTurn = true;
            canFlip = true;
            console.log('✅ 翻牌權限已修復');
        }
        window.canFlip = canFlip;
        window.isMyTurn = isMyTurn;
    }
    
    // 3. 重新創建遊戲板
    if (typeof createCards === 'function') {
        createCards();
        console.log('✅ 遊戲板已重新創建');
    }
    
    // 4. 強制修復困難模式網格佈局
    if (window.currentDifficulty === 'hard') {
        setTimeout(() => {
            const gameBoard = document.getElementById('game-board');
            if (gameBoard) {
                gameBoard.style.display = 'grid';
                gameBoard.style.gridTemplateColumns = 'repeat(8, 1fr)';
                gameBoard.style.gridTemplateRows = 'repeat(4, 1fr)';
                gameBoard.style.gap = '6px';
                gameBoard.style.maxWidth = '1000px';
                gameBoard.className = 'game-board hard-mode';
                console.log('✅ 困難模式網格佈局已自動修復');
            }
        }, 500);
    }
    
    console.log('✅ 自動修復完成');
}

// 頁面載入時自動執行修復
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(autoFixMemoryGame, 1000);
    
    // 額外的強制同步修復
    setTimeout(() => {
        console.log('🔧 執行額外的強制同步修復...');
        
        // 強制同步困難度
        if (typeof currentDifficulty !== 'undefined') {
            window.currentDifficulty = currentDifficulty;
            console.log('困難度已同步:', currentDifficulty);
        }
        
        // 強制同步翻牌權限
        if (typeof canFlip !== 'undefined') {
            window.canFlip = canFlip;
            console.log('翻牌權限已同步:', canFlip);
        }
        
        // 強制同步回合狀態
        if (typeof isMyTurn !== 'undefined') {
            window.isMyTurn = isMyTurn;
            console.log('回合狀態已同步:', isMyTurn);
        }
        
        // 強制重新創建遊戲板
        if (typeof createCards === 'function') {
            createCards();
            console.log('遊戲板已重新創建');
        }
        
        console.log('✅ 額外強制同步修復完成');
    }, 2000);
});

// 提供手動修復函數
window.autoFixMemoryGame = autoFixMemoryGame;
window.forceSyncDifficultyCheck = forceSyncDifficultyCheck;
window.adjustGameBoardSize = adjustGameBoardSize;

// 全局修復函數 - 解決所有問題
window.fixAllIssues = function() {
    console.log('🔧 執行全局修復...');
    
    // 1. 修復計時器
    if (typeof stopTurnTimer === 'function') {
        stopTurnTimer();
    }
    
    // 重置計時器狀態
    turnTimeLeft = 10;
    isTurnActive = false;
    
    if (typeof startTurnTimer === 'function') {
        setTimeout(() => {
            startTurnTimer();
        }, 100);
    }
    
    // 2. 強制修復網格佈局和視窗
    const gameBoard = document.getElementById('game-board');
    const gameContainer = document.getElementById('game-container');
    
    if (gameBoard) {
        // 強制設定正確的網格佈局
        gameBoard.style.display = 'grid';
        gameBoard.style.gridTemplateColumns = 'repeat(8, 1fr)';
        gameBoard.style.gridTemplateRows = 'repeat(4, 1fr)';
        gameBoard.style.gap = '10px';
        
        // 使用動態計算設定視窗大小
        const cols = 8, rows = 4, maxCardSize = 90;
        const gap = 10;
        const containerWidth = gameContainer ? gameContainer.clientWidth : 800;
        const maxBoardWidth = Math.min(containerWidth - 100, cols * maxCardSize + (cols - 1) * gap);
        const cardSize = Math.floor((maxBoardWidth - (cols - 1) * gap) / cols);
        const boardWidth = cardSize * cols + (cols - 1) * gap;
        const boardHeight = cardSize * rows + (rows - 1) * gap;
        
        gameBoard.style.width = boardWidth + 'px';
        gameBoard.style.height = boardHeight + 'px';
        gameBoard.style.maxWidth = 'none';
        gameBoard.style.maxHeight = 'none';
        gameBoard.style.overflow = 'hidden';
        gameBoard.style.margin = '0 auto';
        gameBoard.setAttribute('data-difficulty', 'hard');
        
        console.log('✅ 強制設定困難模式網格: 8x4, 動態大小:', boardWidth + 'x' + boardHeight);
    }
    
    if (gameContainer) {
        const boardWidth = gameBoard ? gameBoard.offsetWidth : 800;
        gameContainer.style.width = (boardWidth + 200) + 'px';
        gameContainer.style.maxWidth = 'none';
        gameContainer.style.padding = '30px';
        gameContainer.style.minHeight = (gameBoard ? gameBoard.offsetHeight : 400) + 300 + 'px';
        gameContainer.style.overflow = 'hidden';
        gameContainer.setAttribute('data-difficulty', 'hard');
        
        console.log('✅ 強制設定困難模式容器，動態大小');
    }
    
    // 3. 重新創建卡片
    if (typeof createCards === 'function') {
        createCards();
    }
    
    // 4. 同步困難度
    window.currentDifficulty = 'hard';
    window.gridSize = 8;
    
    // 5. 強制重新調整大小
    if (typeof adjustGameBoardSize === 'function') {
        setTimeout(() => {
            adjustGameBoardSize();
        }, 100);
    }
    
    console.log('✅ 全局修復完成！');
};

// 緊急修復函數 - 解決網格和圖示問題
window.emergencyFix = function() {
    console.log('🚨 執行緊急修復...');
    
    // 1. 強制設定困難模式網格和視窗
    const gameBoard = document.getElementById('game-board');
    const gameContainer = document.getElementById('game-container');
    
    if (gameBoard) {
        gameBoard.style.display = 'grid';
        gameBoard.style.gridTemplateColumns = 'repeat(8, 1fr)';
        gameBoard.style.gridTemplateRows = 'repeat(4, 1fr)';
        gameBoard.style.gap = '10px';
        gameBoard.style.maxWidth = '1000px';
        gameBoard.style.maxHeight = '500px';
        gameBoard.style.margin = '0 auto';
        gameBoard.setAttribute('data-difficulty', 'hard');
        console.log('✅ 困難模式網格已強制設定');
    }
    
    if (gameContainer) {
        gameContainer.style.maxWidth = '1100px';
        gameContainer.style.padding = '30px';
        gameContainer.setAttribute('data-difficulty', 'hard');
        console.log('✅ 困難模式視窗已調整');
    }
    
    // 2. 重新創建卡片
    if (typeof createCards === 'function') {
        createCards();
        console.log('✅ 卡片已重新創建');
    }
    
    // 3. 確保困難度正確
    window.currentDifficulty = 'hard';
    window.gridSize = 8;
    console.log('✅ 困難度已設定為 hard');
    
    // 4. 修復計時器
    if (typeof stopTurnTimer === 'function') {
        stopTurnTimer();
    }
    if (typeof startTurnTimer === 'function') {
        startTurnTimer();
    }
    
    console.log('🚨 緊急修復完成！');
};

// 網格同步修復函數
window.fixGridSync = function() {
    console.log('🔧 修復網格同步...');
    
    // 強制同步困難度設定
    if (gameMode === 'online' && invitationId) {
        fetch('game-invitation-api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'update_invitation_status',
                invitation_id: invitationId,
                status: 'game_started',
                difficulty: 'hard',
                theme: currentTheme || 'fruit-theme'
            })
        })
        .then(response => response.json())
        .then(data => {
            console.log('✅ 困難度同步成功:', data);
            // 重新創建遊戲板
            if (typeof createCards === 'function') {
                createCards();
            }
        })
        .catch(error => {
            console.error('❌ 困難度同步失敗:', error);
        });
    }
    
    // 強制修復網格佈局
    const gameBoard = document.getElementById('game-board');
    const gameContainer = document.getElementById('game-container');
    
    if (gameBoard) {
        gameBoard.style.display = 'grid';
        gameBoard.style.gridTemplateColumns = 'repeat(8, 1fr)';
        gameBoard.style.gridTemplateRows = 'repeat(4, 1fr)';
        gameBoard.style.gap = '10px';
        gameBoard.style.maxWidth = '1000px';
        gameBoard.style.maxHeight = '500px';
        gameBoard.style.overflow = 'hidden';
        gameBoard.setAttribute('data-difficulty', 'hard');
    }
    
    if (gameContainer) {
        gameContainer.style.maxWidth = '1100px';
        gameContainer.style.width = '1100px';
        gameContainer.style.overflow = 'hidden';
        gameContainer.setAttribute('data-difficulty', 'hard');
    }
    
    // 強制重新創建遊戲板
    setTimeout(() => {
        if (typeof createCards === 'function') {
            createCards();
        }
    }, 500);
};

// 計時器修復函數
window.fixTimer = function() {
    console.log('🕐 修復計時器...');
    
    // 停止現有計時器
    if (typeof stopTurnTimer === 'function') {
        stopTurnTimer();
    }
    
    // 重置計時器狀態
    turnTimeLeft = 10;
    isTurnActive = false;
    
    // 更新顯示
    const timerElement = document.getElementById('turn-timer');
    if (timerElement) {
        timerElement.textContent = '10';
        timerElement.className = '';
    }
    
    // 重新開始計時器
    if (typeof startTurnTimer === 'function') {
        setTimeout(() => {
            startTurnTimer();
        }, 100);
    }
    
    console.log('✅ 計時器修復完成！');
};

// 測試自動切換回合功能
window.testAutoSwitch = function() {
    console.log('🧪 測試自動切換回合功能...');
    
    // 強制設定計時器為1秒，測試自動切換
    turnTimeLeft = 1;
    
    console.log('當前狀態:', {
        currentPlayer: currentPlayer,
        isMyTurn: isMyTurn,
        canFlip: canFlip,
        turnTimeLeft: turnTimeLeft,
        gameMode: gameMode
    });
    
    console.log('⏰ 1秒後應該自動切換回合...');
    
    // 重新開始計時器，1秒後自動切換
    if (typeof startTurnTimer === 'function') {
        startTurnTimer();
    }
};

// 測試圖片顯示功能
window.testImageDisplay = function() {
    console.log('🖼️ 測試圖片顯示功能...');
    
    // 找到第一張卡片
    const firstCard = document.querySelector('.card');
    if (firstCard) {
        const symbol = firstCard.dataset.value;
        console.log('第一張卡片的符號:', symbol);
        
        // 檢查是否為圖片檔案
        const isImageFile = symbol.includes('.png') || symbol.includes('.jpg') || symbol.includes('.jpeg');
        console.log('是否為圖片檔案:', isImageFile);
        
        if (isImageFile) {
            console.log('圖片路徑:', `img/${symbol}`);
            // 測試圖片是否可載入
            const img = new Image();
            img.onload = function() {
                console.log('✅ 圖片載入成功:', symbol);
            };
            img.onerror = function() {
                console.log('❌ 圖片載入失敗:', symbol);
            };
            img.src = `img/${symbol}`;
        }
        
        // 強制翻開第一張卡片測試
        firstCard.classList.add('flipped');
        console.log('✅ 已強制翻開第一張卡片');
    } else {
        console.log('❌ 沒有找到卡片元素');
    }
};

// 動態調整視窗大小函數 - 參考單人模式
window.adjustWindowSize = function() {
    console.log('🔄 動態調整視窗大小...');
    
    const gameBoard = document.getElementById('game-board');
    const gameContainer = document.getElementById('game-container');
    
    if (!gameBoard || !gameContainer) {
        console.log('❌ 找不到遊戲板或容器元素');
        return;
    }
    
    const difficulty = window.currentDifficulty || 'normal';
    let cols, rows, maxCardSize;
    
    if (difficulty === 'easy') {
        cols = 4; rows = 3; maxCardSize = 120;
    } else if (difficulty === 'normal') {
        cols = 4; rows = 4; maxCardSize = 90;
    } else if (difficulty === 'hard') {
        cols = 8; rows = 4; maxCardSize = 90;
    }
    
    const gap = 10;
    const containerWidth = gameContainer.clientWidth || 800;
    const maxBoardWidth = Math.min(containerWidth - 100, cols * maxCardSize + (cols - 1) * gap);
    const cardSize = Math.floor((maxBoardWidth - (cols - 1) * gap) / cols);
    
    const boardWidth = cardSize * cols + (cols - 1) * gap;
    const boardHeight = cardSize * rows + (rows - 1) * gap;
    
    gameBoard.style.width = boardWidth + 'px';
    gameBoard.style.height = boardHeight + 'px';
    gameContainer.style.width = (boardWidth + 200) + 'px';
    gameContainer.style.minHeight = (boardHeight + 300) + 'px';
    
    console.log(`✅ 視窗大小已調整: ${difficulty}模式, ${boardWidth}x${boardHeight}px`);
};

// 視窗縮放時自動調整
window.addEventListener('resize', function() {
    if (window.currentDifficulty) {
        setTimeout(window.adjustWindowSize, 100);
    }
});