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

// 頁面載入時初始化currentUserId
document.addEventListener('DOMContentLoaded', function() {
    currentUserId = getCurrentMemberId();
    console.log('初始化currentUserId:', currentUserId);
    
    // 自動檢查是否有已接受的邀請
    checkForAcceptedInvitations();
    
    // 添加頁面離開事件監聽器
    window.addEventListener('beforeunload', function(e) {
        if (gameMode === 'online' && invitationId) {
            // 強制退出戰局
            forceQuitGame();
            
            // 顯示警告訊息
            e.preventDefault();
            e.returnValue = '您正在進行線上對戰，離開頁面將自動退出戰局。';
            return e.returnValue;
        }
    });
    
    // 添加頁面隱藏事件監聽器（手機切換應用程式時）
    let visibilityTimeout;
    document.addEventListener('visibilitychange', function() {
        if (document.hidden && gameMode === 'online' && invitationId) {
            // 減少延遲到1秒，提高退出檢測速度
            visibilityTimeout = setTimeout(() => {
                console.log('頁面隱藏超過1秒，強制退出對戰');
                forceQuitGame();
            }, 1000);
        } else if (!document.hidden && visibilityTimeout) {
            // 頁面重新可見時取消退出
            clearTimeout(visibilityTimeout);
            visibilityTimeout = null;
        }
    });
    
    // 移除舊的事件監聽器，現在使用 handleBackButton 函數處理
    
    // 添加調試函數到全局
    window.debugGameState = function() {
        console.log('=== 遊戲狀態調試 ===');
        console.log('matchedPairs:', matchedPairs);
        console.log('player1Pairs:', player1Pairs);
        console.log('player2Pairs:', player2Pairs);
        console.log('player1Name:', player1Name);
        console.log('player2Name:', player2Name);
        console.log('currentPlayer:', currentPlayer);
        console.log('isMyTurn:', isMyTurn);
        console.log('gameMode:', gameMode);
        console.log('invitationId:', invitationId);
        
        // 檢查配對的卡片
        const matchedCards = cards.filter(card => card.classList.contains('matched'));
        console.log('實際配對卡片數量:', matchedCards.length);
        console.log('配對卡片:', matchedCards.map(card => card.dataset.symbol));
        
        // 檢查所有卡片的狀態
        console.log('=== 所有卡片狀態 ===');
        cards.forEach((card, index) => {
            console.log(`卡片${index}:`, {
                symbol: card.dataset.symbol,
                flipped: card.classList.contains('flipped'),
                matched: card.classList.contains('matched'),
                index: card.dataset.index
            });
        });
        
        // 檢查回合狀態
        const isInviter = getCurrentMemberId() == invitationData?.from_user_id;
        const shouldBeMyTurn = (currentPlayer === 1 && isInviter) || (currentPlayer === 2 && !isInviter);
        console.log('=== 回合狀態調試 ===');
        console.log('isInviter:', isInviter);
        console.log('shouldBeMyTurn:', shouldBeMyTurn);
        console.log('isMyTurn:', isMyTurn);
        console.log('currentPlayer:', currentPlayer);
        console.log('currentUserId:', getCurrentMemberId());
        console.log('fromUserId:', invitationData?.from_user_id);
        console.log('回合狀態一致:', shouldBeMyTurn === isMyTurn);
    };
    
    // 添加檢查遊戲狀態的函數
    window.checkGameState = function() {
        if (!invitationId) {
            console.log('沒有邀請ID，無法檢查遊戲狀態');
            return;
        }
        
        fetch('test_game_state.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'check_game_state',
                invitation_id: invitationId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('=== 伺服器遊戲狀態 ===');
                console.log('邀請數據:', data.invitation);
                console.log('遊戲狀態:', data.game_state);
                console.log('遊戲結束狀態:', data.game_end_state);
                console.log('當前用戶ID:', data.current_user_id);
            } else {
                console.error('檢查遊戲狀態失敗:', data.message);
            }
        })
        .catch(error => {
            console.error('檢查遊戲狀態錯誤:', error);
        });
    };
    
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
        gridSize: 4, // 4x3 = 12張卡片 (6對)
        timeLimit: 60,
        baseScore: 20
    },
    normal: {
        gridSize: 4, // 4x4 = 16張卡片 (8對)
        timeLimit: 120,
        baseScore: 50
    },
    hard: {
        gridSize: 6, // 6x6 = 36張卡片 (18對)
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
    fruit: ['🍎', '🍐', '🍊', '🍋', '🍌', '🍉', '🍇', '🍓', '🍈', '🍒', '🍑', '🥭',
           '🍍', '🥥', '🥝', '🍅', '🍆', '🥑', '🥦', '🥬', '🥒', '🌶️', '🌽', '🥕'],
    animal: ['🐶', '🐱', '🐭', '🐹', '🐰', '🦊', '🐻', '🐼', '🐨', '🐯', '🦁', '🐮',
            '🐷', '🐸', '🐵', '🐔', '🐧', '🐦', '🦆', '🦅', '🦉', '🦇', '🐺', '🐗'],
    daily: ['⌚', '📱', '💻', '⌨️', '🖥️', '🖨️', '🖱️', '🖲️', '🕹️', '🗜️', '💽', '💾',
           '💿', '📀', '📼', '📷', '📹', '🎥', '📽️', '🎞️', '📞', '☎️', '📟', '📠'],
    vegetable: ['🥬', '🥦', '🥒', '🌶️', '🌽', '🥕', '🧄', '🧅', '🥔', '🍠', '🥐', '🥯',
               '🥖', '🥨', '🧀', '🥚', '🍳', '🧈', '🥞', '🧇', '🥓', '🥩', '🍗', '🍖']
};

// 好友邀請功能 - 使用 AJAX
function inviteFriend(friendId, friendName) {
    // 先清理任何現有的邀請檢查
    if (invitationCheckInterval) {
        clearInterval(invitationCheckInterval);
        invitationCheckInterval = null;
    }
    
    invitedFriendId = friendId;
    invitedFriendName = friendName;
    
    // 顯示等待視窗
    document.getElementById('invited-friend-name').textContent = friendName;
    document.getElementById('friend-invite-modal').classList.add('hidden');
    document.getElementById('waiting-modal').classList.remove('hidden');
    
    // 發送邀請到伺服器
    fetch('game-invitation-api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'send_invitation',
            from_user_id: getCurrentMemberId(),
            to_user_id: friendId,
            game_type: 'memory_game_2p'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            invitationId = data.invitation_id;
            // 開始輪詢檢查邀請狀態
            startInvitationPolling(invitationId);
        } else {
            alert('發送邀請失敗：' + data.message);
            hideWaitingModal();
        }
    })
    .catch(error => {
        console.error('發送邀請錯誤:', error);
        alert('發送邀請失敗，請稍後再試');
        hideWaitingModal();
    });
}

// 開始輪詢檢查邀請狀態
function startInvitationPolling(invitationId) {
    invitationCheckInterval = setInterval(() => {
        checkInvitationStatus(invitationId);
    }, 1000); // 每1秒檢查一次，提高響應速度
}

// 檢查邀請狀態
function checkInvitationStatus(invitationId) {
    fetch('game-invitation-api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'check_invitation',
            invitation_id: invitationId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // 存儲邀請數據
            invitationData = data.invitation;
            
            switch (data.status) {
                case 'accepted':
                    clearInterval(invitationCheckInterval);
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
                    clearInterval(invitationCheckInterval);
                    hideWaitingModal();
                    // 重置邀請相關變數
                    invitationId = null;
                    invitationData = null;
                    invitedFriendId = null;
                    invitedFriendName = null;
                    showRejectModal();
                    break;
                case 'cancelled':
                    clearInterval(invitationCheckInterval);
                    hideWaitingModal();
                    alert('邀請已取消');
                    break;
                case 'expired':
                    clearInterval(invitationCheckInterval);
                    hideWaitingModal();
                    showExpiredModal();
                    break;
                case 'quit':
                    clearInterval(invitationCheckInterval);
                    hideWaitingModal();
                    hideAllModals();
                    document.getElementById('player-quit-modal').classList.remove('hidden');
                    break;
                // 'pending' 狀態繼續等待
            }
        }
    })
    .catch(error => {
        console.error('檢查邀請狀態錯誤:', error);
    });
}

// 取消邀請
function cancelInvitation() {
    if (invitationId) {
        fetch('game-invitation-api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'cancel_invitation',
                invitation_id: invitationId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                clearInterval(invitationCheckInterval);
                hideWaitingModal();
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
        },
        body: JSON.stringify({
            action: 'accept_invitation',
            invitation_id: currentInvitationId
        })
    })
    .then(response => response.json())
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
            alert('接受邀請失敗：' + (data.message || '未知錯誤'));
        }
    })
    .catch(error => {
        console.error('接受邀請錯誤:', error);
        alert('接受邀請失敗，請稍後再試');
    });
}

// 拒絕邀請
function rejectInvitation() {
    if (window.currentInvitation) {
        fetch('game-invitation-api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
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
    document.getElementById('waiting-modal').classList.add('hidden');
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
            
            // 顯示退出成功訊息
            alert('您已退出對戰');
            
            // 返回主選單
            returnToMain();
        }, 1000);
            } else {
                alert('退出對戰失敗：' + (data.message || '未知錯誤'));
            }
        })
        .catch(error => {
            console.error('退出對戰錯誤:', error);
            alert('退出對戰失敗，請稍後再試');
        });
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
    // 隱藏主題選擇視窗
    document.getElementById('theme-modal').classList.add('hidden');
    // 顯示邀請好友視窗
    document.getElementById('friend-invite-modal').classList.remove('hidden');
}

// 選擇主題
function selectTheme(theme) {
    currentTheme = theme;
    console.log('選擇主題:', theme);
    
    // 如果是線上模式，立即更新邀請設定
    if (gameMode === 'online' && invitationId) {
        updateInvitationSettings().then(() => {
            console.log('主題設定已同步');
        }).catch(error => {
            console.error('同步主題設定失敗:', error);
        });
    }
    
    // 顯示難度選擇
    document.getElementById('theme-modal').classList.add('hidden');
    document.getElementById('difficulty-modal').classList.remove('hidden');
}

// 選擇難度
function selectDifficulty(difficulty) {
    currentDifficulty = difficulty;
    console.log('選擇難度:', difficulty);
    
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
    canFlip = true;
    currentPlayer = 1;
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
    
    // 如果是本地模式或線上模式且是我的回合，開始計時器
    if (gameMode === 'local' || (gameMode === 'online' && isMyTurn)) {
        console.log('開始回合計時器，遊戲模式:', gameMode, '我的回合:', isMyTurn);
        // 延遲一點啟動計時器，確保DOM已更新
        setTimeout(() => {
            startTurnTimer();
        }, 100);
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
    
    // 根據難度設置正確的卡片數量
    let totalCards, rows, cols;
    if (currentDifficulty === 'easy') {
        totalCards = 12; // 4x3 = 12張卡片 (6對)
        rows = 3;
        cols = 4;
    } else if (currentDifficulty === 'normal') {
        totalCards = 16; // 4x4 = 16張卡片 (8對)
        rows = 4;
        cols = 4;
    } else if (currentDifficulty === 'hard') {
        totalCards = 36; // 6x6 = 36張卡片 (18對)
        rows = 6;
        cols = 6;
    } else {
        // 預設值
        totalCards = 12;
        rows = 3;
        cols = 4;
    }
    
    // 更新gridSize為實際的列數
    gridSize = cols;
    
    console.log('創建卡片:', {
        difficulty: currentDifficulty,
        totalCards: totalCards,
        rows: rows,
        cols: cols,
        pairs: totalCards / 2
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

    // 創建卡片元素
    cardSymbols.forEach((symbol, index) => {
        const card = createCard(symbol, index);
        gameBoard.appendChild(card);
        cards.push(card);
    });
    
    // 設置遊戲板的網格樣式
    gameBoard.style.gridTemplateColumns = `repeat(${cols}, 1fr)`;
    gameBoard.style.gridTemplateRows = `repeat(${rows}, 1fr)`;
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
    
    card.innerHTML = `
        <div class="card-inner">
        <div class="card-front">${symbol}</div>
        <div class="card-back"></div>
        </div>
    `;
    
    // 確保卡片一開始是蓋著的（不顯示符號）
    card.classList.remove('flipped');
    card.classList.remove('matched');
   
    card.addEventListener('click', () => flipCard(card));
    
    console.log('創建卡片:', { index, symbol, cardElement: card });
    return card;
}

// 翻牌
function flipCard(card) {
    console.log('嘗試翻牌，當前狀態:', {
        canFlip: canFlip,
        isFlipped: card.classList.contains('flipped'),
        isMatched: card.classList.contains('matched'),
        gameMode: gameMode,
        isMyTurn: isMyTurn,
        currentPlayer: currentPlayer,
        currentUserId: currentUserId,
        invitationData: invitationData
    });
    
    if (!canFlip || card.classList.contains('flipped') || card.classList.contains('matched')) {
        console.log('翻牌被阻止:', {
            canFlip: canFlip,
            isFlipped: card.classList.contains('flipped'),
            isMatched: card.classList.contains('matched')
        });
        return;
    }
    
    // 如果是線上模式，檢查是否輪到自己
    if (gameMode === 'online') {
        // 強制檢查並修復回合狀態
        const isInviter = getCurrentMemberId() == invitationData?.from_user_id;
        const shouldBeMyTurn = (currentPlayer === 1 && isInviter) || (currentPlayer === 2 && !isInviter);
        
        console.log('回合檢查:', {
            currentPlayer,
            isInviter,
            shouldBeMyTurn,
            isMyTurn,
            currentUserId: getCurrentMemberId(),
            fromUserId: invitationData?.from_user_id
        });
        
        if (shouldBeMyTurn !== isMyTurn) {
            console.log('檢測到回合狀態不一致，強制修復');
            isMyTurn = shouldBeMyTurn;
            updateCurrentPlayer();
        }
        
        // 檢查是否輪到自己
        if (!isMyTurn) {
            console.log('不是你的回合，無法翻牌，當前回合:', isMyTurn, '遊戲模式:', gameMode, '當前玩家:', currentPlayer);
            return; // 不是我的回合，不允許翻牌
        }
    }
    
    console.log('翻牌成功:', card.dataset.symbol);
    card.classList.add('flipped');
    flippedCards.push(card);
    
    // 如果是第一次翻牌，開始計時器
    if (flippedCards.length === 1 && !isTurnActive) {
        startTurnTimer();
    }
    
    // 如果是線上模式，同步翻牌動作
    if (gameMode === 'online') {
        console.log('同步翻牌動作');
        syncGameState();
    }
    
    if (flippedCards.length === 2) {
        canFlip = false;
        totalMoves++;
        document.getElementById('total-moves').textContent = totalMoves;
        
        setTimeout(() => {
            checkMatchSync();
        }, 1200); // 從800ms增加到1200ms
    }
}

// 檢查配對
function checkMatchSync() {
    // 確保有兩張翻開的卡片
    if (flippedCards.length !== 2) {
        console.error('翻開的卡片數量不正確:', flippedCards.length);
        return;
    }
    
    const [card1, card2] = flippedCards;
    
    // 確保卡片元素存在
    if (!card1 || !card2) {
        console.error('卡片元素不存在:', { card1, card2 });
        flippedCards = [];
        canFlip = true;
        return;
    }
    
    // 確保兩個卡片都有符號數據
    if (!card1.dataset || !card1.dataset.symbol || !card2.dataset || !card2.dataset.symbol) {
        console.error('卡片缺少符號數據:', {
            card1Symbol: card1.dataset?.symbol,
            card2Symbol: card2.dataset?.symbol
        });
        // 重置翻牌狀態
        setTimeout(() => {
            card1.classList.remove('flipped');
            card2.classList.remove('flipped');
            flippedCards = [];
            canFlip = true;
        }, 1000);
        return;
    }
    
    const match = card1.dataset.symbol === card2.dataset.symbol;
    console.log('檢查配對:', {
        card1Symbol: card1.dataset.symbol,
        card2Symbol: card2.dataset.symbol,
        isMatch: match,
        card1Index: card1.dataset.index,
        card2Index: card2.dataset.index
    });
    
    if (match) {
        // 配對成功
        console.log('配對成功！');
        card1.classList.add('matched');
        card2.classList.add('matched');
        matchedPairs++;
        
        // 更新當前玩家分數
        if (currentPlayer === 1) {
            player1Score += 10;
            player1Pairs++;
        } else {
            player2Score += 10;
            player2Pairs++;
        }
        
        consecutiveMatches++;
        
        // 停止計時器
        stopTurnTimer();
        
        // 如果是線上模式，同步配對結果
        if (gameMode === 'online') {
            console.log('同步配對成功:', { matchedPairs, player1Pairs, player2Pairs, currentPlayer });
            // 延遲同步，確保DOM更新完成
            setTimeout(() => {
                syncGameState();
            }, 100);
        }
        
        // 配對成功可以再翻一次
        setTimeout(() => {
            updatePlayerDisplay();
            canFlip = true;
        }, 800); // 從500ms增加到800ms
        
    } else {
        // 配對失敗
        console.log('配對失敗，翻回卡片');
        setTimeout(() => {
            card1.classList.remove('flipped');
            card2.classList.remove('flipped');
            
            // 停止計時器並切換玩家
            stopTurnTimer();
            
            // 如果是線上模式，同步配對失敗和玩家切換
            if (gameMode === 'online') {
                console.log('同步配對失敗和玩家切換');
                // 延遲同步，確保DOM更新完成
                setTimeout(() => {
                    switchPlayer();
                }, 100);
            } else {
                switchPlayer();
            }
            
            updatePlayerDisplay();
            canFlip = true;
        }, 2000); // 從1500ms增加到2000ms
    }
    
    flippedCards = [];
    
    // 檢查遊戲是否結束
    const totalPairs = cards.length / 2;
    if (matchedPairs === totalPairs) {
        console.log('遊戲結束！配對完成:', { matchedPairs, totalPairs });
        setTimeout(() => {
            endGame();
        }, 500);
    }
}

// 更新當前玩家顯示
function updateCurrentPlayer() {
    const player1Info = document.getElementById('player1-info');
    const player2Info = document.getElementById('player2-info');
    const gameBoard = document.getElementById('game-board');
    
    // 更新玩家資訊面板的active狀態
    if (currentPlayer === 1) {
        player1Info.classList.add('active');
        player2Info.classList.remove('active');
    } else {
        player1Info.classList.remove('active');
        player2Info.classList.add('active');
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
                difficulty: currentDifficulty,
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
        return memberIdElement.value || memberIdElement.dataset.memberId;
    }
    
    // 如果都找不到，返回預設值
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
    window.location.href = 'game-category.php';
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
function syncGameState() {
    if (gameMode !== 'online' || !invitationId) return;
    
    // 檢查是否需要同步（避免重複同步）
    const currentState = {
        flippedCards: flippedCards.map(card => card.dataset.index),
        matchedPairs: matchedPairs,
        totalMoves: totalMoves,
        currentPlayer: currentPlayer,
        player1Score: player1Score,
        player2Score: player2Score,
        player1Pairs: player1Pairs,
        player2Pairs: player2Pairs
    };
    
    // 如果狀態沒有變化，不進行同步
    if (JSON.stringify(currentState) === JSON.stringify(lastSyncState)) {
        console.log('狀態未變化，跳過同步');
        return;
    }
    
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
        lastAction: 'flip',
        lastActionBy: currentUserId,
        player1Name: player1Name,
        player2Name: player2Name,
        isMyTurn: isMyTurn
    };
    
    // 保存當前狀態用於比較
    lastSyncState = currentState;
    
    console.log('同步遊戲狀態:', gameState);
    
    fetch('game-sync-api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
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
            console.log('遊戲狀態已同步');
    } else {
            console.error('同步失敗:', data.message);
        }
    })
    .catch(error => {
        console.error('同步錯誤:', error);
    });
}

    // 開始遊戲同步輪詢
    function startGameSync() {
        if (gameMode !== 'online' || !invitationId) return;
        
        console.log('開始遊戲同步，邀請ID:', invitationId);
        
                // 立即進行一次退出檢測
        checkForPlayerQuit();
        
        // 立即進行第二次退出檢測，確保不會遺漏
        setTimeout(() => {
            checkForPlayerQuit();
        }, 100);
        
        // 啟動積極的退出檢測
        aggressiveQuitCheck();
        
        gameSyncInterval = setInterval(() => {
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
            // 優先檢查是否有玩家退出（即使沒有遊戲狀態也要檢查）
            if (data.success && data.player_quit) {
                console.log('檢測到玩家退出，立即停止同步');
                
                // 立即停止所有計時器和同步
                clearInterval(gameSyncInterval);
                gameSyncInterval = null;
                
                // 停止回合計時器
                if (turnTimer) {
                    clearInterval(turnTimer);
                    turnTimer = null;
                }
                
                // 停止邀請檢查計時器
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
                
                console.log('玩家退出處理完成');
                return;
            }
            
            if (data.success && data.game_state) {
                console.log('收到遊戲狀態更新:', data.game_state);
                
                // 檢查是否是遊戲結束狀態
                if (data.is_game_end) {
                    console.log('收到遊戲結束狀態，停止同步');
                    clearInterval(gameSyncInterval);
                    gameSyncInterval = null;
                }
                
                updateGameFromSync(data.game_state);
            }
        })
        .catch(error => {
            console.error('獲取遊戲狀態錯誤:', error);
        });
    }, 200); // 進一步加快同步頻率，提高退出檢測速度
}

// 快速檢查玩家退出狀態
function checkForPlayerQuit() {
    if (gameMode !== 'online' || !invitationId) return;
    
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
        }
    })
    .catch(error => {
        console.error('快速退出檢測錯誤:', error);
    });
}

// 添加一個更積極的退出檢測函數
function aggressiveQuitCheck() {
    if (gameMode !== 'online' || !invitationId) return;
    
    // 每500ms檢查一次退出狀態
    const quitCheckInterval = setInterval(() => {
        if (gameMode !== 'online' || !invitationId) {
            clearInterval(quitCheckInterval);
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
            }
        })
        .catch(error => {
            console.error('積極退出檢測錯誤:', error);
        });
    }, 500);
}

// 從同步數據更新遊戲
function updateGameFromSync(gameState) {
    console.log('收到遊戲狀態更新:', gameState);
    
    // 防抖機制：避免重複的翻牌動畫
    const now = Date.now();
    if (now - lastSyncTime < syncDebounceDelay) {
        console.log('防抖：跳過重複的同步更新');
        return;
    }
    lastSyncTime = now;
    
    // 檢查是否是最新的動作 - 但不要跳過，因為我們需要同步所有狀態
    const isMyAction = gameState.lastActionBy === getCurrentMemberId();
    console.log('是否是我的動作:', isMyAction);
    
    // 更新遊戲狀態
    if (gameState.lastAction === 'flip' || gameState.lastAction === 'switch_player' || gameState.lastAction === 'player_quit_signal') {
        console.log('更新翻牌狀態:', gameState);
        
        // 更新翻牌狀態 - 只同步已翻開的卡片，不自動翻牌
        gameState.cards.forEach((cardData, index) => {
            const card = cards[index];
            if (card) {
                // 確保符號同步
                if (cardData.symbol && card.dataset.symbol !== cardData.symbol) {
                    card.dataset.symbol = cardData.symbol;
                    const cardFront = card.querySelector('.card-front');
                    if (cardFront) {
                        cardFront.textContent = cardData.symbol;
                    }
                    console.log('同步卡片符號:', index, cardData.symbol);
                }
                
                // 只在狀態不同時更新，避免閃爍
                const shouldBeFlipped = cardData.flipped;
                const shouldBeMatched = cardData.matched;
                const isCurrentlyFlipped = card.classList.contains('flipped');
                const isCurrentlyMatched = card.classList.contains('matched');
                
                // 正常同步邏輯
                if (shouldBeFlipped && !isCurrentlyFlipped) {
                    card.classList.add('flipped');
                    console.log('同步翻開卡片:', index, cardData.symbol);
                } else if (!shouldBeFlipped && isCurrentlyFlipped) {
                    card.classList.remove('flipped');
                    console.log('同步蓋回卡片:', index, cardData.symbol);
                }
                
                if (shouldBeMatched && !isCurrentlyMatched) {
                    card.classList.add('matched');
                    console.log('同步配對卡片:', index, cardData.symbol);
                }
            }
        });
        
        // 更新flippedCards數組
        flippedCards = gameState.flippedCards.map(index => cards[index]).filter(card => card);
        
        // 重置翻牌權限（如果沒有翻開的卡片或是玩家切換）
        if (flippedCards.length === 0 || gameState.lastAction === 'switch_player') {
            canFlip = true;
            console.log('重置翻牌權限:', { 
                flippedCardsLength: flippedCards.length, 
                lastAction: gameState.lastAction,
                canFlip: canFlip 
            });
        }
        
        // 檢查是否是退出信號
        if (gameState.lastAction === 'player_quit_signal' || gameState.player_quit) {
            console.log('收到退出信號，立即處理退出');
            
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
            
            console.log('退出信號處理完成');
            return;
        }
        
        // 檢查是否是其他玩家的退出信號
        if (gameState.lastActionBy && gameState.lastActionBy !== getCurrentMemberId() && 
            (gameState.lastAction === 'player_quit_signal' || gameState.player_quit)) {
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
        const totalMatchesElement = document.getElementById('total-moves');
        if (totalMatchesElement) totalMatchesElement.textContent = matchedPairs;
        
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
        
        // 如果是我的回合，開始計時器
        if (gameMode === 'local' || (gameMode === 'online' && isMyTurn)) {
            startTurnTimer();
        } else {
            stopTurnTimer();
        }
        
        // 調試：檢查翻牌權限
        console.log('翻牌權限檢查:', {
            canFlip: canFlip,
            isMyTurn: isMyTurn,
            gameMode: gameMode,
            currentPlayer: currentPlayer
        });
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
}

// 調整遊戲板大小
function adjustGameBoardSize() {
    const gameBoard = document.getElementById('game-board');
    const cardSize = Math.min(80, Math.max(60, 800 / gridSize - 10));
    
    gameBoard.style.gridTemplateColumns = `repeat(${gridSize}, ${cardSize}px)`;
    gameBoard.style.gap = '5px';

    document.querySelectorAll('.card').forEach(card => {
        card.style.width = `${cardSize}px`;
        card.style.height = `${cardSize}px`;
    });
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
            currentPlayer = 1; // 確保從玩家1開始
            
            console.log('遊戲開始設定:', {
                isMyTurn,
                currentPlayer,
                isInviter,
                player1Name,
                player2Name
            });
            
            if (gameMode === 'online') {
                startGameSync();
                // 立即進行一次退出檢測
                setTimeout(() => {
                    checkForPlayerQuit();
                }, 100);
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
                // 立即進行一次退出檢測
                setTimeout(() => {
                    checkForPlayerQuit();
                }, 100);
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
        },
        body: JSON.stringify({
            action: 'get_pending_invitations'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.invitations && data.invitations.length > 0) {
            // 顯示第一個收到的邀請
            const invitation = data.invitations[0];
            showReceivedInvitation(invitation);
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
        },
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
                alert('邀請已被取消');
                window.currentInvitation = null;
            } else if (data.status === 'quit') {
                // 邀請者退出
                hideWaitingModal();
                hideAllModals();
                document.getElementById('player-quit-modal').classList.remove('hidden');
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
    
    return fetch('game-invitation-api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'update_invitation_settings',
            invitation_id: invitationId,
            game_settings: {
                theme: currentTheme,
                difficulty: currentDifficulty
            }
        })
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
        },
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
    const settingsInterval = setInterval(() => {
        checkGameSettings(invitationId, settingsInterval);
        // 同時檢查是否被取消
        checkInvitationCancelled();
    }, 1000); // 每1秒檢查一次，提高響應速度
}

// 檢查遊戲設定
function checkGameSettings(invitationId, interval) {
    console.log('檢查遊戲設定，邀請ID:', invitationId);
    
    fetch('game-invitation-api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'check_invitation',
            invitation_id: invitationId
        })
    })
    .then(response => response.json())
    .then(data => {
        console.log('檢查遊戲設定回應:', data);
        
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
            const gameSettings = data.game_settings || (data.invitation && data.invitation.game_settings);
            
            if (gameSettings) {
                console.log('收到遊戲設定:', gameSettings);
                
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
                
                // 根據難度設定遊戲參數
                if (currentDifficulty === 'easy') {
                    gridSize = 4;
                } else if (currentDifficulty === 'normal') {
                    gridSize = 4;
                } else if (currentDifficulty === 'hard') {
                    gridSize = 6;
    } else {
                    gridSize = 4;
                }
                
                console.log('應用遊戲設定:', {
                    theme: currentTheme,
                    difficulty: currentDifficulty,
                    gridSize: gridSize,
                    invitationId: invitationId
                });
                
                // 隱藏所有相關視窗
                hideAllModals();
                
                // 額外確保隱藏所有可能的邀請視窗
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
                
                // 開始遊戲
                gameStartTimestamp = Date.now();
                initializeGame();
                
                // 設定回合（邀請者先開始）
                const isInviter = getCurrentMemberId() == (window.currentInvitation?.from_user_id || data.invitation?.from_user_id);
                isMyTurn = isInviter; // 邀請者先開始
                currentPlayer = 1; // 確保從玩家1開始
                
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
                    player2Name = data.invitation?.to_user_name || window.currentInvitation?.to_user_name || '被邀請者';
                } else {
                    // 被邀請者：玩家1是好友，玩家2是自己
                    player1Name = data.invitation?.from_user_name || window.currentInvitation?.from_user_name || '邀請者';
                    player2Name = currentUserDisplayName;
                }
                
                console.log('設定玩家名字:', { 
                    player1Name, player2Name, 
                    currentUserDisplayName, 
                    isInviter,
                    from_user_name: data.invitation?.from_user_name,
                    to_user_name: data.invitation?.to_user_name
                });
                
                // 立即更新顯示
                updatePlayerDisplay();
                forceUpdatePlayerNames();
                
                console.log('開始遊戲，我的回合:', isMyTurn, '當前玩家:', currentPlayer, '是邀請者:', isInviter);
                
                // 開始遊戲同步
                if (gameMode === 'online') {
                    startGameSync();
                    // 立即進行一次退出檢測
                    setTimeout(() => {
                        checkForPlayerQuit();
                    }, 100);
                    // 初始同步玩家名字
                    setTimeout(() => {
                        syncGameState();
                    }, 500);
                }
            } else {
                console.log('還沒有遊戲設定，繼續等待...');
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
        },
        body: JSON.stringify({
            action: 'get_pending_invitations'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.invitations && data.invitations.length > 0) {
            // 檢查是否有邀請者退出的邀請
            const quitInvitation = data.invitations.find(inv => inv.status === 'quit');
            if (quitInvitation) {
                console.log('邀請者已退出對戰');
                hideAllModals();
                document.getElementById('player-quit-modal').classList.remove('hidden');
                return;
            }
            
            // 檢查是否有已接受且有遊戲設定的邀請（直接開始遊戲）
            const acceptedWithSettings = data.invitations.find(inv => 
                inv.status === 'accepted' && inv.game_settings
            );
            
            if (acceptedWithSettings) {
                console.log('找到已接受且有遊戲設定的邀請，直接開始遊戲:', acceptedWithSettings);
                
                // 設定遊戲模式
                gameMode = 'online';
                invitationId = acceptedWithSettings.invitation_id;
                invitationData = acceptedWithSettings;
                window.currentInvitation = acceptedWithSettings;
                
                // 解析遊戲設定
                const gameSettings = JSON.parse(acceptedWithSettings.game_settings);
                currentTheme = gameSettings.theme || 'fruit';
                currentDifficulty = gameSettings.difficulty || 'easy';
                
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
                currentPlayer = 1; // 確保從玩家1開始
                
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
                    // 立即進行一次退出檢測
                    setTimeout(() => {
                        checkForPlayerQuit();
                    }, 100);
                    setTimeout(() => {
                        syncGameState();
                    }, 500);
                }
                return;
            }
            
            // 檢查是否有已接受但還沒有遊戲設定的邀請（進入主題選擇）
            const acceptedWithoutSettings = data.invitations.find(inv => 
                inv.status === 'accepted' && !inv.game_settings && inv.from_user_id == getCurrentMemberId()
            );
            
            if (acceptedWithoutSettings) {
                console.log('找到已接受但還沒有遊戲設定的邀請，進入主題選擇:', acceptedWithoutSettings);
                
                // 設定邀請數據
                invitationId = acceptedWithoutSettings.invitation_id;
                invitationData = acceptedWithoutSettings;
                window.currentInvitation = acceptedWithoutSettings;
                gameMode = 'online';
                
                // 隱藏等待視窗
                hideWaitingModal();
                
                // 顯示主題選擇視窗
                showThemeModal();
                return;
            } else {
                console.log('沒有找到已接受的邀請，顯示好友邀請視窗');
                // 確保好友邀請視窗顯示
                const friendInviteModal = document.getElementById('friend-invite-modal');
                if (friendInviteModal) {
                    friendInviteModal.classList.remove('hidden');
                }
            }
        } else {
            console.log('沒有待處理的邀請，顯示好友邀請視窗');
            // 確保好友邀請視窗顯示
            const friendInviteModal = document.getElementById('friend-invite-modal');
            if (friendInviteModal) {
                friendInviteModal.classList.remove('hidden');
            }
        }
    })
    .catch(error => {
        console.error('檢查邀請錯誤:', error);
        // 發生錯誤時也顯示好友邀請視窗
        const friendInviteModal = document.getElementById('friend-invite-modal');
        if (friendInviteModal) {
            friendInviteModal.classList.remove('hidden');
        }
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

// 開始回合計時器
function startTurnTimer() {
    if (turnTimer) {
        clearInterval(turnTimer);
    }
    
    turnTimeLeft = 10;
    isTurnActive = true;
    const timerElement = document.getElementById('turn-timer');
    timerElement.textContent = turnTimeLeft;
    timerElement.className = ''; // 清除警告樣式
    
    turnTimer = setInterval(() => {
        turnTimeLeft--;
        timerElement.textContent = turnTimeLeft;
        
        // 添加視覺警告
        if (turnTimeLeft <= 3) {
            timerElement.className = 'danger';
        } else if (turnTimeLeft <= 5) {
            timerElement.className = 'warning';
        }
        
        if (turnTimeLeft <= 0) {
            clearInterval(turnTimer);
            turnTimer = null;
            isTurnActive = false;
            
            // 時間到，切換玩家
            console.log('回合時間到，切換玩家');
            
            // 蓋回所有翻開的卡片
            flippedCards.forEach(card => {
                card.classList.remove('flipped');
            });
            flippedCards = [];
            
            // 切換玩家
            switchPlayer();
        }
            }, 1000);
        }

// 停止回合計時器
function stopTurnTimer() {
    if (turnTimer) {
        clearInterval(turnTimer);
        turnTimer = null;
    }
    isTurnActive = false;
    const timerElement = document.getElementById('turn-timer');
    timerElement.textContent = '10';
    timerElement.className = ''; // 清除警告樣式
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
    
    // 重置翻牌權限
    canFlip = true;
    
    // 切換當前玩家
    currentPlayer = currentPlayer === 1 ? 2 : 1;
    consecutiveMatches = 0;
    
    // 如果是線上模式，切換回合並同步
    if (gameMode === 'online') {
        // 根據當前玩家和用戶身份重新計算回合
        const isInviter = getCurrentMemberId() == invitationData?.from_user_id;
        isMyTurn = (currentPlayer === 1 && isInviter) || (currentPlayer === 2 && !isInviter);
        
        console.log('切換回合詳細信息:', {
            currentPlayer,
            isInviter,
            isMyTurn,
            currentUserId: getCurrentMemberId(),
            fromUserId: invitationData?.from_user_id,
            canFlip: canFlip
        });
        
        // 立即同步狀態，包含回合信息
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
            lastAction: 'switch_player',
            lastActionBy: getCurrentMemberId(), // 使用發送同步的玩家ID
            player1Name: player1Name,
            player2Name: player2Name,
            isMyTurn: isMyTurn,
            // 添加額外的回合信息，確保對手能正確計算
            isInviter: getCurrentMemberId() == invitationData?.from_user_id,
            currentUserId: getCurrentMemberId(),
            fromUserId: invitationData?.from_user_id
        };
        
        console.log('發送同步數據:', gameState);
        
        // 立即發送同步
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
                console.log('玩家切換已同步成功');
            } else {
                console.error('同步玩家切換失敗:', data.message);
            }
        })
        .catch(error => {
            console.error('同步玩家切換錯誤:', error);
        });
    }
    
    // 更新顯示
    updateCurrentPlayer();
    
    // 重新開始計時器（確保只有一個計時器運行）
    stopTurnTimer(); // 先停止現有計時器
    if (gameMode === 'local' || (gameMode === 'online' && isMyTurn)) {
        setTimeout(() => {
            startTurnTimer();
        }, 100); // 延遲100ms確保計時器不會重疊
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
    
    // 更新顯示
    updateCurrentPlayer();
    
    // 重新開始計時器（確保只有一個計時器運行）
    stopTurnTimer(); // 先停止現有計時器
    if (gameMode === 'local' || (gameMode === 'online' && isMyTurn)) {
        setTimeout(() => {
            startTurnTimer();
        }, 100); // 延遲100ms確保計時器不會重疊
    }
    
    return isMyTurn;
}

// 將調試函數暴露到全局，方便在控制台調用
window.forceFixTurnState = forceFixTurnState;

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
    if (gameMode === 'local' || (gameMode === 'online' && isMyTurn)) {
        setTimeout(() => {
            startTurnTimer();
        }, 100); // 延遲100ms確保計時器不會重疊
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
    document.getElementById('friend-reject-modal').classList.remove('hidden');
}

// 隱藏好友拒絕邀請視窗
function hideRejectModal() {
    document.getElementById('friend-reject-modal').classList.add('hidden');
    // 確保回到好友邀請視窗
    document.getElementById('friend-invite-modal').classList.remove('hidden');
    // 確保其他相關視窗都被隱藏
    document.getElementById('waiting-modal').classList.add('hidden');
    document.getElementById('received-invitation-modal').classList.add('hidden');
    document.getElementById('player-setup-modal').classList.add('hidden');
    document.getElementById('theme-modal').classList.add('hidden');
    document.getElementById('difficulty-modal').classList.add('hidden');
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
    forceQuitGame();
    hideReturnConfirmModal();
    // 延遲一下再返回，確保退出請求已發送
    setTimeout(() => {
        window.location.href = 'game-category.php';
    }, 500);
}

// 取消返回
function cancelReturn() {
    hideReturnConfirmModal();
}

// 處理返回按鈕點擊
function handleBackButton() {
    // 檢查是否在線上對戰中
    if (gameMode === 'online' && invitationId) {
        // 顯示自定義確認對話框
        showReturnConfirmModal();
    } else {
        // 直接返回主選單
        window.location.href = 'game-category.php';
    }
}