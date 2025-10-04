// 遊戲核心邏輯
let score = 0;
let timer = 60;
let interval = null;
let currentQuestion = 0;
let questions = [];
let gamePaused = false;
let savedTimer = 60;
let gameStarted = false;
let currentDifficulty = null;
let isPaused = false;

// 雙人遊戲變數
let player1 = {
    id: window.phpMemberId,
    name: window.currentUser.member_name,
    score: 0,
    correct: 0,
    avatar: window.currentUser.avatar
};

let player2 = {
    id: 'local_player',
    name: '本地玩家',
    score: 0,
    correct: 0,
    avatar: 'img/user.png'
};

let currentPlayer = 1; // 1 或 2
let totalQuestions = 0;

// 邀請系統變數
let invitedFriend = null;
let invitationId = null;
let invitationData = null;

// 食材資料庫（從資料庫 AJAX 取得）
let ingredients = { vegetables: [], fruits: [], meat: [], seafood: [], mushroom: [], others: [] };

// 頁面載入時初始化遊戲
window.addEventListener('DOMContentLoaded', async () => {
    console.log('頁面載入完成，開始初始化...');
    console.log('當前用戶ID:', window.phpMemberId);
    console.log('當前用戶信息:', window.currentUser);
    
    // 確保 session 和變數都已正確設置
    if (!window.phpMemberId) {
        console.log('等待 session 設置...');
        // 如果 session 還沒設置，延遲一下再檢查
        setTimeout(async () => {
            await initializeGame();
        }, 1000);
        return;
    }
    
    await initializeGame();
});

// 初始化遊戲函數
async function initializeGame() {
    console.log('開始初始化遊戲...');
    
    // 載入食材資料
    await fetchIngredients();
    
    // 檢查是否有收到的邀請
    await checkReceivedInvitations();
    
    // 只有在沒有其他模態視窗顯示時才顯示好友邀請視窗
    const receivedInvitationModal = document.getElementById('received-invitation-modal');
    const waitingDifficultyModal = document.getElementById('waiting-difficulty-modal');
    
    if ((!receivedInvitationModal || receivedInvitationModal.classList.contains('hidden')) &&
        (!waitingDifficultyModal || waitingDifficultyModal.classList.contains('hidden'))) {
        showFriendInviteModal();
    }
}

// 檢查收到的邀請
async function checkReceivedInvitations() {
    try {
        console.log('開始檢查收到的邀請...');
        const response = await fetch('game-invitation-api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'get_pending_invitations'
            })
        });
        
        const result = await response.json();
        console.log('檢查邀請結果:', result);
        
        if (result.success && result.invitations && result.invitations.length > 0) {
            console.log('找到邀請數量:', result.invitations.length);
            console.log('所有邀請:', result.invitations);
            
            // 找到算菜錢遊戲的邀請
            const vegetableCostInvitation = result.invitations.find(inv => inv.game_type === 'vegetable_cost_2p');
            console.log('算菜錢邀請:', vegetableCostInvitation);
            
            if (vegetableCostInvitation) {
                // 設置邀請信息
                invitationId = vegetableCostInvitation.invitation_id;
                invitationData = vegetableCostInvitation;
                invitedFriend = {
                    id: vegetableCostInvitation.from_user_id,
                    name: vegetableCostInvitation.from_user_name
                };
                
                console.log('設置邀請信息:', { invitationId, invitedFriend, invitationData });
                console.log('邀請者判斷:', { 
                    myId: window.phpMemberId, 
                    fromUserId: vegetableCostInvitation.from_user_id, 
                    isInviter: window.phpMemberId == vegetableCostInvitation.from_user_id
                });
                
                // 顯示收到的邀請視窗
                showReceivedInvitationModal();
                return;
            }
        }
        
        // 如果沒有待處理的邀請，檢查是否有已接受的邀請需要同步
        console.log('沒有待處理的邀請，檢查是否有需要同步的已接受邀請...');
        await checkForAcceptedInvitations();
        
    } catch (error) {
        console.error('檢查收到的邀請錯誤:', error);
    }
}

// 檢查是否有已接受的邀請需要同步
async function checkForAcceptedInvitations() {
    try {
        const response = await fetch('game-invitation-api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'get_recent_accepted_invitations'
            })
        });
        
        const result = await response.json();
        console.log('檢查已接受邀請結果:', result);
        
        if (result.success && result.invitations && result.invitations.length > 0) {
            // 找到算菜錢遊戲的已接受邀請
            const vegetableCostInvitation = result.invitations.find(inv => 
                inv.game_type === 'vegetable_cost_2p' && 
                inv.status === 'accepted'
            );
            
            if (vegetableCostInvitation) {
                console.log('找到已接受的算菜錢邀請:', vegetableCostInvitation);
                
                // 設置邀請信息
                invitationId = vegetableCostInvitation.invitation_id;
                invitationData = vegetableCostInvitation;
                invitedFriend = {
                    id: vegetableCostInvitation.from_user_id,
                    name: vegetableCostInvitation.from_user_name
                };
                
                console.log('設置已接受邀請信息:', { invitationId, invitedFriend, invitationData });
                console.log('邀請者判斷:', { 
                    myId: window.phpMemberId, 
                    fromUserId: vegetableCostInvitation.from_user_id, 
                    isInviter: window.phpMemberId == vegetableCostInvitation.from_user_id
                });
                
                // 檢查當前用戶是邀請者還是被邀請者
                const currentUserId = window.phpMemberId;
                if (vegetableCostInvitation.from_user_id == currentUserId) {
                    // 當前用戶是邀請者
                    if (vegetableCostInvitation.game_settings && vegetableCostInvitation.game_settings.difficulty) {
                        // 如果已經選擇了難度，直接開始遊戲
                        console.log('邀請者已選擇難度，直接開始遊戲');
                        const difficulty = vegetableCostInvitation.game_settings.difficulty;
                        startGameWithDifficulty(difficulty);
                    } else {
                        // 如果還沒選擇難度，顯示難度選擇視窗
                        console.log('邀請者需要選擇難度，顯示難度選擇視窗');
                        showDifficultyModal();
                    }
                } else {
                    // 當前用戶是被邀請者
                    if (vegetableCostInvitation.game_settings && vegetableCostInvitation.game_settings.difficulty) {
                        // 如果對手已經選擇了難度，直接開始遊戲
                        console.log('對手已選擇難度，直接開始遊戲');
                        const difficulty = vegetableCostInvitation.game_settings.difficulty;
                        startGameWithDifficulty(difficulty);
                    } else {
                        // 如果對手還沒選擇難度，顯示等待難度視窗
                        console.log('等待對手選擇難度，顯示等待難度視窗');
                        showWaitingDifficultyModal();
                        // 開始檢查對手是否已選擇難度
                        setTimeout(checkOpponentDifficultySelection, 100);
                    }
                }
                return;
            }
        }
        
        console.log('沒有需要同步的已接受邀請');
    } catch (error) {
        console.error('檢查已接受邀請錯誤:', error);
    }
}

// 顯示收到的邀請視窗
function showReceivedInvitationModal() {
    const receivedInvitationModal = document.getElementById('received-invitation-modal');
    const inviterName = document.getElementById('inviter-name');
    
    if (receivedInvitationModal && invitedFriend) {
        inviterName.textContent = invitedFriend.name;
        receivedInvitationModal.classList.remove('hidden');
    }
}

// 顯示好友邀請視窗
function showFriendInviteModal() {
    const friendInviteModal = document.getElementById('friend-invite-modal');
    const difficultyModal = document.getElementById('difficulty-modal');
    const gameContainer = document.getElementById('game-container');
    const receivedInvitationModal = document.getElementById('received-invitation-modal');
    const waitingDifficultyModal = document.getElementById('waiting-difficulty-modal');
    
    // 如果有收到的邀請，不顯示好友邀請視窗
    if (receivedInvitationModal && !receivedInvitationModal.classList.contains('hidden')) {
        return;
    }
    
    // 如果正在等待對手選擇難度，不顯示好友邀請視窗
    if (waitingDifficultyModal && !waitingDifficultyModal.classList.contains('hidden')) {
        return;
    }
    
    if (friendInviteModal) {
        friendInviteModal.classList.remove('hidden');
    }
    if (difficultyModal) {
        difficultyModal.classList.add('hidden');
    }
    if (gameContainer) {
        gameContainer.style.display = 'none';
    }
}

// 顯示難度選擇視窗
function showDifficultyModal() {
    console.log('顯示難度選擇視窗');
    const friendInviteModal = document.getElementById('friend-invite-modal');
    const difficultyModal = document.getElementById('difficulty-modal');
    const waitingModal = document.getElementById('waiting-modal');
    const waitingDifficultyModal = document.getElementById('waiting-difficulty-modal');
    const receivedInvitationModal = document.getElementById('received-invitation-modal');
    
    console.log('friendInviteModal:', friendInviteModal, 'difficultyModal:', difficultyModal);
    
    // 隱藏所有其他模態視窗
    if (friendInviteModal) {
        friendInviteModal.classList.add('hidden');
    }
    if (waitingModal) {
        waitingModal.classList.add('hidden');
    }
    if (waitingDifficultyModal) {
        waitingDifficultyModal.classList.add('hidden');
    }
    if (receivedInvitationModal) {
        receivedInvitationModal.classList.add('hidden');
    }
    
    if (difficultyModal) {
        difficultyModal.classList.remove('hidden');
        console.log('難度選擇視窗已顯示');
    } else {
        console.error('找不到難度選擇視窗元素');
    }
}

// 邀請好友
async function inviteFriend(friendId, friendName) {
    invitedFriend = {
        id: friendId,
        name: friendName
    };
    
    // 顯示等待視窗
    showWaitingModal();
    
    try {
        // 發送真正的邀請
        const response = await fetch('game-invitation-api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'send_invitation',
                to_user_id: friendId,
                game_type: 'vegetable_cost_2p'
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            invitationId = result.invitation_id;
            console.log('邀請已發送:', result);
            
            // 開始檢查邀請狀態
            checkInvitationStatus();
        } else {
            console.error('發送邀請失敗:', result.message);
            hideWaitingModal();
            alert('發送邀請失敗: ' + result.message);
            showFriendInviteModal();
        }
    } catch (error) {
        console.error('發送邀請錯誤:', error);
        hideWaitingModal();
        alert('發送邀請時發生錯誤');
        showFriendInviteModal();
    }
}

// 檢查邀請狀態
async function checkInvitationStatus() {
    if (!invitationId) return;
    
    try {
        const response = await fetch('game-invitation-api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'check_invitation',
                invitation_id: invitationId
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            console.log('邀請狀態檢查結果:', result.status, 'invitation:', result.invitation);
            switch (result.status) {
                case 'accepted':
                    hideWaitingModal();
                    console.log('邀請已接受，當前用戶ID:', window.phpMemberId, '邀請者ID:', result.invitation?.from_user_id);
                    
                    // 設置 invitedFriend 為對手（邀請者或接收者）
                    if (result.invitation) {
                        // 設置 invitationData
                        invitationData = result.invitation;
                        console.log('設置 invitationData:', invitationData);
                        
                        if (result.invitation.from_user_id == window.phpMemberId) {
                            // 當前用戶是邀請者，對手是接收者
                            invitedFriend = {
                                id: result.invitation.to_user_id,
                                name: result.invitation.to_user_name
                            };
                            console.log('當前用戶是邀請者，對手是:', invitedFriend);
                            showDifficultyModal();
                        } else {
                            // 當前用戶是被邀請者，對手是發送者
                            invitedFriend = {
                                id: result.invitation.from_user_id,
                                name: result.invitation.from_user_name
                            };
                            console.log('當前用戶是被邀請者，對手是:', invitedFriend);
                            // 被邀請者接受邀請後，顯示等待難度視窗
                            showWaitingDifficultyModal();
                            // 開始檢查對手是否已選擇難度
                            setTimeout(checkOpponentDifficultySelection, 100);
                        }
                    } else {
                        // 如果沒有邀請信息，也開始檢查對手難度選擇
                        console.log('沒有邀請信息，開始檢查對手難度選擇');
                        setTimeout(checkOpponentDifficultySelection, 100);
                    }
                    break;
                case 'rejected':
                    hideWaitingModal();
                    simulateFriendReject();
                    break;
                case 'expired':
                    hideWaitingModal();
                    simulateInvitationExpired();
                    break;
                case 'pending':
                    // 繼續等待，1秒後再次檢查
                    setTimeout(checkInvitationStatus, 1000);
                    break;
                default:
                    console.log('邀請狀態:', result.status);
                    setTimeout(checkInvitationStatus, 1000);
            }
        } else {
            console.error('檢查邀請狀態失敗:', result.message);
            setTimeout(checkInvitationStatus, 2000);
        }
    } catch (error) {
        console.error('檢查邀請狀態錯誤:', error);
        setTimeout(checkInvitationStatus, 2000);
    }
}

// 顯示等待視窗
function showWaitingModal() {
    const waitingModal = document.getElementById('waiting-modal');
    const invitedFriendName = document.getElementById('invited-friend-name');
    
    if (waitingModal && invitedFriend) {
        invitedFriendName.textContent = invitedFriend.name;
        waitingModal.classList.remove('hidden');
    }
}

// 顯示等待對手選擇難度視窗
function showWaitingDifficultyModal() {
    console.log('顯示等待對手選擇難度視窗');
    const waitingDifficultyModal = document.getElementById('waiting-difficulty-modal');
    const opponentName = document.getElementById('opponent-name');
    const friendInviteModal = document.getElementById('friend-invite-modal');
    const waitingModal = document.getElementById('waiting-modal');
    const difficultyModal = document.getElementById('difficulty-modal');
    const receivedInvitationModal = document.getElementById('received-invitation-modal');
    const gameContainer = document.getElementById('game-container');
    
    console.log('waitingDifficultyModal:', waitingDifficultyModal, 'opponentName:', opponentName);
    
    // 隱藏所有其他模態視窗和遊戲容器
    if (friendInviteModal) {
        friendInviteModal.classList.add('hidden');
    }
    if (waitingModal) {
        waitingModal.classList.add('hidden');
    }
    if (difficultyModal) {
        difficultyModal.classList.add('hidden');
    }
    if (receivedInvitationModal) {
        receivedInvitationModal.classList.add('hidden');
    }
    if (gameContainer) {
        gameContainer.style.display = 'none';
        console.log('隱藏遊戲容器');
    }
    
    if (waitingDifficultyModal) {
        // 如果有 invitedFriend，使用它；否則從邀請信息中獲取對手名稱
        if (invitedFriend) {
            console.log('使用 invitedFriend:', invitedFriend);
            opponentName.textContent = invitedFriend.name;
        } else if (invitationId) {
            console.log('從邀請信息中獲取對手名稱');
            // 從邀請信息中獲取對手名稱
            getInvitationInfo().then(invitation => {
                if (invitation && invitation.from_user_name) {
                    opponentName.textContent = invitation.from_user_name;
                }
            });
        }
        
        console.log('移除 hidden 類別');
        waitingDifficultyModal.classList.remove('hidden');
        console.log('等待難度視窗已顯示');
        
        // 注意：不要在這裡調用 checkOpponentDifficultySelection()，因為它會在 acceptInvitation 中被調用
        // 避免重複調用
    } else {
        console.error('找不到 waiting-difficulty-modal 元素');
    }
}

// 隱藏等待視窗
function hideWaitingModal() {
    const waitingModal = document.getElementById('waiting-modal');
    if (waitingModal) {
        waitingModal.classList.add('hidden');
    }
}

// 隱藏等待對手選擇難度視窗
function hideWaitingDifficultyModal() {
    const waitingDifficultyModal = document.getElementById('waiting-difficulty-modal');
    if (waitingDifficultyModal) {
        waitingDifficultyModal.classList.add('hidden');
    }
}

// 獲取邀請信息
async function getInvitationInfo() {
    if (!invitationId) return null;
    
    try {
        const response = await fetch('game-invitation-api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'check_invitation',
                invitation_id: invitationId
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            return result.invitation;
        }
    } catch (error) {
        console.error('獲取邀請信息錯誤:', error);
    }
    
    return null;
}

// 檢查對手是否已選擇難度
async function checkOpponentDifficultySelection() {
    console.log('檢查對手難度選擇，invitationId:', invitationId);
    if (!invitationId) return;
    
    try {
        const response = await fetch('game-invitation-api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'check_invitation',
                invitation_id: invitationId
            })
        });
        
        const result = await response.json();
        console.log('檢查對手難度選擇結果:', result);
        
        if (result.success) {
            console.log('邀請狀態:', result.status);
            console.log('難度信息:', result.difficulty);
            console.log('遊戲設定:', result.game_settings);
            
            // 檢查邀請狀態是否包含難度信息
            if (result.difficulty && result.status === 'accepted') {
                console.log('對手已選擇難度:', result.difficulty, '開始遊戲');
                // 對手已選擇難度，開始遊戲
                hideWaitingDifficultyModal();
                await startGameWithDifficulty(result.difficulty);
            } else if (result.game_settings && result.game_settings.difficulty && result.status === 'accepted') {
                console.log('從 game_settings 中獲取難度:', result.game_settings.difficulty, '開始遊戲');
                // 從 game_settings 中獲取難度
                hideWaitingDifficultyModal();
                await startGameWithDifficulty(result.game_settings.difficulty);
            } else if (result.status === 'rejected' || result.status === 'expired') {
                // 邀請被拒絕或過期
                console.log('邀請狀態異常:', result.status);
                hideWaitingDifficultyModal();
                if (result.status === 'rejected') {
                    simulateFriendReject();
                } else {
                    simulateInvitationExpired();
                }
            } else {
                console.log('繼續等待對手選擇難度，狀態:', result.status, '難度:', result.difficulty, 'game_settings:', result.game_settings);
                // 繼續等待，500毫秒後再次檢查（更快的輪詢）
                setTimeout(checkOpponentDifficultySelection, 500);
            }
        } else {
            console.error('檢查對手難度選擇失敗:', result.message);
            setTimeout(checkOpponentDifficultySelection, 1000);
        }
    } catch (error) {
        console.error('檢查對手難度選擇錯誤:', error);
        setTimeout(checkOpponentDifficultySelection, 1000);
    }
}

// 取消邀請
async function cancelInvitation() {
    if (invitationId) {
        try {
            const response = await fetch('game-invitation-api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'cancel_invitation',
                    invitation_id: invitationId
                })
            });
            
            const result = await response.json();
            console.log('取消邀請結果:', result);
        } catch (error) {
            console.error('取消邀請錯誤:', error);
        }
    }
    
    hideWaitingModal();
    hideWaitingDifficultyModal();
    invitedFriend = null;
    invitationId = null;
    
    // 重置後顯示好友邀請視窗
    showFriendInviteModal();
}

// 接受邀請
async function acceptInvitation() {
    console.log('開始接受邀請，invitationId:', invitationId, 'invitedFriend:', invitedFriend);
    
    if (invitationId) {
        try {
            const response = await fetch('game-invitation-api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'accept_invitation',
                    invitation_id: invitationId
                })
            });
            
            const result = await response.json();
            console.log('接受邀請結果:', result);
            
            // 設置邀請數據
            if (result.invitation) {
                invitationData = result.invitation;
                console.log('設置 invitationData:', invitationData);
            }
            
            // 如果 invitedFriend 沒有設置，從邀請信息中獲取
            if (!invitedFriend && result.invitation) {
                // 被邀請者接受邀請時，設置對手為邀請者
                invitedFriend = {
                    id: result.invitation.from_user_id,
                    name: result.invitation.from_user_name
                };
                console.log('被邀請者設置 invitedFriend（對手為邀請者）:', invitedFriend);
            }
            
            // 隱藏收到的邀請視窗
            hideReceivedInvitationModal();
            
            // 被邀請者接受邀請後，顯示等待難度視窗並開始輪詢
            console.log('被邀請者接受邀請，顯示等待難度視窗並開始輪詢');
            showWaitingDifficultyModal();
            // 立即開始檢查對手是否已選擇難度
            setTimeout(checkOpponentDifficultySelection, 100);
        } catch (error) {
            console.error('接受邀請錯誤:', error);
            hideReceivedInvitationModal();
            showFriendInviteModal();
        }
    } else {
        console.error('沒有邀請ID');
        hideReceivedInvitationModal();
        showFriendInviteModal();
    }
}

// 拒絕邀請
async function rejectInvitation() {
    if (invitationId) {
        try {
            const response = await fetch('game-invitation-api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'reject_invitation',
                    invitation_id: invitationId
                })
            });
            
            const result = await response.json();
            console.log('拒絕邀請結果:', result);
        } catch (error) {
            console.error('拒絕邀請錯誤:', error);
        }
    }
    
    hideReceivedInvitationModal();
    invitedFriend = null;
    invitationId = null;
    showFriendInviteModal();
}

// 隱藏收到邀請視窗
function hideReceivedInvitationModal() {
    const receivedInvitationModal = document.getElementById('received-invitation-modal');
    if (receivedInvitationModal) {
        receivedInvitationModal.classList.add('hidden');
    }
}

// 隱藏邀請過期視窗
function hideExpiredModal() {
    const expiredModal = document.getElementById('invitation-expired-modal');
    if (expiredModal) {
        expiredModal.classList.add('hidden');
    }
    invitedFriend = null;
    invitationId = null;
    showFriendInviteModal();
}

// 隱藏好友拒絕視窗
function hideRejectModal() {
    const rejectModal = document.getElementById('friend-reject-modal');
    if (rejectModal) {
        rejectModal.classList.add('hidden');
    }
    invitedFriend = null;
    invitationId = null;
    showFriendInviteModal();
}

function restoreInvitation() {
    // 隱藏拒絕視窗
    hideRejectModal();
    // 重新邀請同一個好友
    if (invitedFriend) {
        inviteFriend(invitedFriend.id, invitedFriend.name);
    }
}

// 隱藏退出對戰視窗
function hideQuitModal() {
    const quitModal = document.getElementById('quit-game-modal');
    if (quitModal) {
        quitModal.classList.add('hidden');
    }
}

// 確認退出對戰
function confirmQuitGame() {
    hideQuitModal();
    
    // 隱藏遊戲容器
    const gameContainer = document.getElementById('game-container');
    if (gameContainer) {
        gameContainer.style.display = 'none';
    }
    
    // 重置遊戲狀態
    resetGameState();
    
    // 重置邀請狀態
    invitedFriend = null;
    invitationId = null;
    
    // 顯示好友邀請視窗
    showFriendInviteModal();
}

// 從退出返回主選單
function returnToMainFromQuit() {
    const playerQuitModal = document.getElementById('player-quit-modal');
    if (playerQuitModal) {
        playerQuitModal.classList.add('hidden');
    }
    
    // 隱藏遊戲容器
    const gameContainer = document.getElementById('game-container');
    if (gameContainer) {
        gameContainer.style.display = 'none';
    }
    
    // 重置遊戲狀態
    resetGameState();
    
    // 重置邀請狀態
    invitedFriend = null;
    invitationId = null;
    
    // 顯示好友邀請視窗
    showFriendInviteModal();
}

// 確認返回
function confirmReturn() {
    const returnConfirmModal = document.getElementById('return-confirm-modal');
    if (returnConfirmModal) {
        returnConfirmModal.classList.add('hidden');
    }
    
    // 退出對戰並返回主選單
    confirmQuitGame();
}

// 取消返回
function cancelReturn() {
    const returnConfirmModal = document.getElementById('return-confirm-modal');
    if (returnConfirmModal) {
        returnConfirmModal.classList.add('hidden');
    }
}

// 重置遊戲狀態
function resetGameState() {
    timer = 60;
    gamePaused = false;
    gameStarted = false;
    currentQuestion = 0;
    questions = [];
    currentPlayer = 1;
    
    // 重置玩家分數
    player1.score = 0;
    player1.correct = 0;
    player2.score = 0;
    player2.correct = 0;
    
    // 重置邀請狀態
    invitedFriend = null;
    invitationId = null;
    
    // 重置玩家2信息
    player2.id = 'local_player';
    player2.name = '本地玩家';
    player2.avatar = 'img/user.png';
    
    // 重置顯示
    if (document.getElementById('timer')) {
        document.getElementById('timer').textContent = timer;
    }
    if (document.getElementById('pause-btn')) {
        document.getElementById('pause-btn').textContent = '暫停遊戲';
        document.getElementById('pause-btn').classList.remove('resume-btn');
    }
}

// 模擬邀請過期
function simulateInvitationExpired() {
    hideWaitingModal();
    hideWaitingDifficultyModal();
    const expiredModal = document.getElementById('invitation-expired-modal');
    if (expiredModal) {
        expiredModal.classList.remove('hidden');
    }
}

// 模擬好友拒絕邀請
function simulateFriendReject() {
    hideWaitingModal();
    hideWaitingDifficultyModal();
    const rejectModal = document.getElementById('friend-reject-modal');
    if (rejectModal) {
        rejectModal.classList.remove('hidden');
    }
}

// 顯示退出對戰確認視窗
function showQuitGameModal() {
    const quitModal = document.getElementById('quit-game-modal');
    if (quitModal) {
        quitModal.classList.remove('hidden');
    }
}

// 顯示返回確認視窗
function showReturnConfirmModal() {
    const returnConfirmModal = document.getElementById('return-confirm-modal');
    if (returnConfirmModal) {
        returnConfirmModal.classList.remove('hidden');
    }
}

async function fetchIngredients() {
    try {
        console.log('開始載入食材數據...');
        const response = await fetch('vegetable_cost_2P.php?get_ingredients=1');
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        console.log('fetchIngredients 回傳:', data);
        
        if (!Array.isArray(data)) {
            console.error('食材數據格式錯誤:', data);
            if (data.error) {
                console.error('錯誤信息:', data.error);
            }
            // 初始化空的食材數據，避免後續錯誤
            ingredients = { vegetables: [], fruits: [], meat: [], seafood: [], mushroom: [], others: [] };
            return;
        }
        
        // 分類
        ingredients = { vegetables: [], fruits: [], meat: [], seafood: [], mushroom: [], others: [] };
        data.forEach(item => {
            if (!ingredients[item.category]) ingredients[item.category] = [];
            ingredients[item.category].push(item);
        });
        console.log('分類後的食材:', ingredients);
        console.log('食材載入完成！');
    } catch (error) {
        console.error('載入食材數據失敗:', error);
        // 初始化空的食材數據，避免後續錯誤
        ingredients = { vegetables: [], fruits: [], meat: [], seafood: [], mushroom: [], others: [] };
    }
}

// 生成題目時，將食材名稱加上圖片
function getIngredientWithImage(item) {
    if (item.image) {
        return `<img src="img/${item.image}" alt="${item.name}" style="width: 24px; height: 24px; vertical-align: middle; margin-right: 5px;">${item.name}`;
    }
    return item.name;
}

// 新增：去除 HTML 標籤只留食材名稱
function stripHtml(str) {
    return str.replace(/<[^>]*>/g, '').trim();
}

// 生成簡單題目
function generateEasyQuestion() {
    console.log('開始生成簡單題目');
    console.log('食材數據：', ingredients);
    
    // 檢查食材數據是否已載入
    if (!ingredients || !ingredients.vegetables) {
        console.error('食材數據未載入！');
        // 返回一個預設題目
        return {
            question: '阿嬤去市場買菜，買了：<br>蘋果 30元<br>香蕉 25元<br><br>請問總共要付多少錢？',
            options: [55, 50, 60, 45],
            correctAnswer: 55,
            type: '指定物品',
            items: [{name: '蘋果', price: 30}, {name: '香蕉', price: 25}]
        };
    }
    
    // 顯示2~3種蔬果與價格（組合題時固定5種）
    const allItems = (ingredients.vegetables || []).concat(ingredients.fruits || [], ingredients.others || []);
    console.log('可用食材數量：', allItems.length);
    
    if (allItems.length === 0) {
        console.error('沒有可用食材！');
        // 返回一個預設題目
        return {
            question: '阿嬤去市場買菜，買了：<br>蘋果 30元<br>香蕉 25元<br><br>請問總共要付多少錢？',
            options: [55, 50, 60, 45],
            correctAnswer: 55,
            type: '指定物品',
            items: [{name: '蘋果', price: 30}, {name: '香蕉', price: 25}]
        };
    }
    
    // 題型隨機：1. 指定物品總價 2. 固定預算能買哪些
    const type = Math.random() < 0.6 ? '指定物品' : '預算組合';
    let selectedItems = [];

    if (type === '預算組合') {
        // 預算組合題固定5種蔬果
        const numItems = Math.min(5, allItems.length);
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
    } else {
        // 指定物品題隨機2~3種
        const numItems = Math.random() < 0.5 ? 2 : 3;
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
    }

    if (selectedItems.length === 0) {
        console.error('無法選擇食材，使用預設題目');
        return {
            question: '阿嬤去市場買菜，買了：<br>蘋果 30元<br>香蕉 25元<br><br>請問總共要付多少錢？',
            options: [55, 50, 60, 45],
            correctAnswer: 55,
            type: '指定物品',
            items: [{name: '蘋果', price: 30}, {name: '香蕉', price: 25}]
        };
    }

    // 計算總價
    const totalPrice = selectedItems.reduce((sum, item) => sum + parseFloat(item.price), 0);
    console.log('選擇的食材：', selectedItems);
    console.log('總價：', totalPrice);
    
    // 生成選項（包含正確答案和3個錯誤答案）
    const options = [totalPrice];
    while (options.length < 4) {
        const wrongPrice = totalPrice + (Math.random() < 0.5 ? 1 : -1) * Math.floor(Math.random() * 20 + 5);
        if (wrongPrice > 0 && !options.includes(wrongPrice)) {
            options.push(wrongPrice);
        }
    }
    
    // 打亂選項順序
    for (let i = options.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [options[i], options[j]] = [options[j], options[i]];
    }

    // 生成題目文字
    let questionText = '阿嬤去市場買菜，買了：<br>';
    selectedItems.forEach(item => {
        questionText += `${getIngredientWithImage(item)} ${item.price}元<br>`;
    });
    questionText += '<br>請問總共要付多少錢？';

    const result = {
        question: questionText,
        options: options,
        correctAnswer: totalPrice,
        type: type,
        items: selectedItems
    };
    
    console.log('生成的題目：', result);
    return result;
}

// 生成普通題目
function generateNormalQuestion() {
    console.log('開始生成普通題目');
    
    // 檢查食材數據是否已載入
    if (!ingredients || !ingredients.vegetables) {
        console.error('食材數據未載入！');
        // 返回一個預設題目
        return {
            question: '阿嬤去市場買菜，買了：<br>蘋果 30元<br>香蕉 25元<br>胡蘿蔔 15元<br><br>請問總共要付多少錢？',
            options: [70, 65, 75, 60],
            correctAnswer: 70,
            type: '指定物品',
            items: [{name: '蘋果', price: 30}, {name: '香蕉', price: 25}, {name: '胡蘿蔔', price: 15}]
        };
    }
    
    const allItems = (ingredients.vegetables || []).concat(ingredients.fruits || [], ingredients.meat || [], ingredients.seafood || [], ingredients.others || []);
    console.log('可用食材數量：', allItems.length);
    
    if (allItems.length === 0) {
        console.error('沒有可用食材！');
        // 返回一個預設題目
        return {
            question: '阿嬤去市場買菜，買了：<br>蘋果 30元<br>香蕉 25元<br>胡蘿蔔 15元<br><br>請問總共要付多少錢？',
            options: [70, 65, 75, 60],
            correctAnswer: 70,
            type: '指定物品',
            items: [{name: '蘋果', price: 30}, {name: '香蕉', price: 25}, {name: '胡蘿蔔', price: 15}]
        };
    }
    
    const type = Math.random() < 0.5 ? '指定物品' : '預算組合';
    let selectedItems = [];

    if (type === '預算組合') {
        // 預算組合題固定6種食材
        const numItems = Math.min(6, allItems.length);
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
    } else {
        // 指定物品題隨機3~4種
        const numItems = Math.random() < 0.5 ? 3 : 4;
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
    }

    if (selectedItems.length === 0) {
        console.error('無法選擇食材，使用預設題目');
        return {
            question: '阿嬤去市場買菜，買了：<br>蘋果 30元<br>香蕉 25元<br>胡蘿蔔 15元<br><br>請問總共要付多少錢？',
            options: [70, 65, 75, 60],
            correctAnswer: 70,
            type: '指定物品',
            items: [{name: '蘋果', price: 30}, {name: '香蕉', price: 25}, {name: '胡蘿蔔', price: 15}]
        };
    }

    // 計算總價
    const totalPrice = selectedItems.reduce((sum, item) => sum + parseFloat(item.price), 0);
    
    // 生成選項（包含正確答案和3個錯誤答案）
    const options = [totalPrice];
    while (options.length < 4) {
        const wrongPrice = totalPrice + (Math.random() < 0.5 ? 1 : -1) * Math.floor(Math.random() * 30 + 10);
        if (wrongPrice > 0 && !options.includes(wrongPrice)) {
            options.push(wrongPrice);
        }
    }
    
    // 打亂選項順序
    for (let i = options.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [options[i], options[j]] = [options[j], options[i]];
    }

    // 生成題目文字
    let questionText = '阿嬤去市場買菜，買了：<br>';
    selectedItems.forEach(item => {
        questionText += `${getIngredientWithImage(item)} ${item.price}元<br>`;
    });
    questionText += '<br>請問總共要付多少錢？';

    return {
        question: questionText,
        options: options,
        correctAnswer: totalPrice,
        type: type,
        items: selectedItems
    };
}

// 生成困難題目
function generateHardQuestion() {
    console.log('開始生成困難題目');
    
    // 檢查食材數據是否已載入
    if (!ingredients || !ingredients.vegetables) {
        console.error('食材數據未載入！');
        // 返回一個預設題目
        return {
            question: '阿嬤去市場買菜，買了：<br>蘋果 30元<br>香蕉 25元<br>胡蘿蔔 15元<br>馬鈴薯 20元<br><br>請問總共要付多少錢？',
            options: [90, 85, 95, 80],
            correctAnswer: 90,
            type: '指定物品',
            items: [{name: '蘋果', price: 30}, {name: '香蕉', price: 25}, {name: '胡蘿蔔', price: 15}, {name: '馬鈴薯', price: 20}]
        };
    }
    
    const allItems = (ingredients.vegetables || []).concat(ingredients.fruits || [], ingredients.meat || [], ingredients.seafood || [], ingredients.mushroom || [], ingredients.others || []);
    console.log('可用食材數量：', allItems.length);
    
    if (allItems.length === 0) {
        console.error('沒有可用食材！');
        // 返回一個預設題目
        return {
            question: '阿嬤去市場買菜，買了：<br>蘋果 30元<br>香蕉 25元<br>胡蘿蔔 15元<br>馬鈴薯 20元<br><br>請問總共要付多少錢？',
            options: [90, 85, 95, 80],
            correctAnswer: 90,
            type: '指定物品',
            items: [{name: '蘋果', price: 30}, {name: '香蕉', price: 25}, {name: '胡蘿蔔', price: 15}, {name: '馬鈴薯', price: 20}]
        };
    }
    
    const type = Math.random() < 0.4 ? '指定物品' : '預算組合';
    let selectedItems = [];

    if (type === '預算組合') {
        // 預算組合題固定7種食材
        const numItems = Math.min(7, allItems.length);
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
    } else {
        // 指定物品題隨機4~5種
        const numItems = Math.random() < 0.5 ? 4 : 5;
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
    }

    if (selectedItems.length === 0) {
        console.error('無法選擇食材，使用預設題目');
        return {
            question: '阿嬤去市場買菜，買了：<br>蘋果 30元<br>香蕉 25元<br>胡蘿蔔 15元<br>馬鈴薯 20元<br><br>請問總共要付多少錢？',
            options: [90, 85, 95, 80],
            correctAnswer: 90,
            type: '指定物品',
            items: [{name: '蘋果', price: 30}, {name: '香蕉', price: 25}, {name: '胡蘿蔔', price: 15}, {name: '馬鈴薯', price: 20}]
        };
    }

    // 計算總價
    const totalPrice = selectedItems.reduce((sum, item) => sum + parseFloat(item.price), 0);
    
    // 生成選項（包含正確答案和3個錯誤答案）
    const options = [totalPrice];
    while (options.length < 4) {
        const wrongPrice = totalPrice + (Math.random() < 0.5 ? 1 : -1) * Math.floor(Math.random() * 50 + 15);
        if (wrongPrice > 0 && !options.includes(wrongPrice)) {
            options.push(wrongPrice);
        }
    }
    
    // 打亂選項順序
    for (let i = options.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [options[i], options[j]] = [options[j], options[i]];
    }

    // 生成題目文字
    let questionText = '阿嬤去市場買菜，買了：<br>';
    selectedItems.forEach(item => {
        questionText += `${getIngredientWithImage(item)} ${item.price}元<br>`;
    });
    questionText += '<br>請問總共要付多少錢？';

    return {
        question: questionText,
        options: options,
        correctAnswer: totalPrice,
        type: type,
        items: selectedItems
    };
}

// 載入題目
function loadQuestion() {
    console.log(`載入題目：currentQuestion=${currentQuestion}, questions.length=${questions.length}`);
    
    // 確保 currentQuestion 是有效的數字
    if (currentQuestion === undefined || currentQuestion === null) {
        console.log('currentQuestion 無效，重置為 0');
        currentQuestion = 0;
    }
    
    // 如果是線上模式，優先使用伺服器同步的題目
    if (invitationId && gameStarted) {
        // 嘗試從伺服器獲取當前題目
        fetch('game-sync-api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'get_game_state',
                invitation_id: invitationId,
                player_id: window.phpMemberId
            })
        })
        .then(response => response.json())
        .then(result => {
            if (result.success && result.game_state && result.game_state.current_question_data) {
                console.log('使用伺服器同步的題目：', result.game_state.current_question_data);
                displayQuestion(result.game_state.current_question_data);
                return;
            }
            // 如果沒有伺服器題目，使用本地題目
            loadLocalQuestion();
        })
        .catch(error => {
            console.error('獲取伺服器題目失敗，使用本地題目：', error);
            loadLocalQuestion();
        });
    } else {
        // 本地模式，使用本地題目
        loadLocalQuestion();
    }
}

// 載入本地題目
function loadLocalQuestion() {
    // 確保有題目可以載入
    if (currentQuestion >= questions.length) {
        // 生成新題目
        let newQuestion;
        switch (currentDifficulty) {
            case 'easy':
                newQuestion = generateEasyQuestion();
                break;
            case 'normal':
                newQuestion = generateNormalQuestion();
                break;
            case 'hard':
                newQuestion = generateHardQuestion();
                break;
            default:
                newQuestion = generateEasyQuestion();
        }
        questions.push(newQuestion);
        console.log(`生成新題目：`, newQuestion);
    }

    const question = questions[currentQuestion];
    
    // 檢查題目是否有效
    if (!question || !question.question || !question.options) {
        console.error('題目無效：', question);
        console.error('currentQuestion:', currentQuestion);
        console.error('questions:', questions);
        return;
    }
    
    console.log(`載入本地題目 ${currentQuestion + 1}：`, question.question);
    displayQuestion(question);
}

// 顯示題目
function displayQuestion(question) {
    
    // 檢查題目是否有效
    if (!question || !question.question || !question.options) {
        console.error('題目無效：', question);
        console.error('currentQuestion:', currentQuestion);
        console.error('questions:', questions);
        return;
    }
    
    // 檢查題目是否有效
    if (!question || !question.question || !question.options) {
        console.error('題目無效：', question);
        return;
    }
    
    console.log(`顯示題目：`, question.question);
    
    // 顯示題目（支援 HTML）
    const questionElement = document.getElementById('question');
    questionElement.innerHTML = question.question;
    
    // 顯示選項
    const optionsContainer = document.getElementById('options-container');
    optionsContainer.innerHTML = '';
    
    question.options.forEach((option, index) => {
        const button = document.createElement('button');
        button.textContent = option + '元';
        
        // 檢查是否輪到當前玩家答題
        const isInviter = window.phpMemberId == invitationData?.from_user_id;
        let shouldBeMyTurn = (currentPlayer === 1 && isInviter) || (currentPlayer === 2 && !isInviter);
        
        // 如果 invitationData 為空，使用備用判斷方法
        if (!invitationData) {
            console.log('invitationData 為空，使用備用判斷方法');
            // 如果我是玩家1，且當前回合是玩家1，那麼我可以答題
            shouldBeMyTurn = (currentPlayer === 1 && window.phpMemberId == player1.id) || 
                           (currentPlayer === 2 && window.phpMemberId == player2.id);
        }
        
        // 強制修復：直接基於玩家ID判斷答題權限
        const isPlayer1 = window.phpMemberId == player1.id;
        const isPlayer2 = window.phpMemberId == player2.id;
        const shouldBeMyTurnByPlayerId = (currentPlayer === 1 && isPlayer1) || (currentPlayer === 2 && isPlayer2);
        
        console.log('強制修復權限檢查:', {
            isPlayer1,
            isPlayer2,
            shouldBeMyTurnByPlayerId,
            originalShouldBeMyTurn: shouldBeMyTurn
        });
        
        // 強制使用玩家ID計算，因為 invitationData 可能不正確
        shouldBeMyTurn = shouldBeMyTurnByPlayerId;
        console.log('強制使用玩家ID計算答題權限');
        
        console.log('=== 詳細調試信息 ===');
        console.log('當前玩家:', currentPlayer);
        console.log('我的ID:', window.phpMemberId);
        console.log('玩家1 ID:', player1.id, '名字:', player1.name);
        console.log('玩家2 ID:', player2.id, '名字:', player2.name);
        console.log('我的名字:', window.currentUser.member_name);
        console.log('當前回合玩家名字:', currentPlayer === 1 ? player1.name : player2.name);
        console.log('我是玩家1?', window.phpMemberId == player1.id);
        console.log('我是玩家2?', window.phpMemberId == player2.id);
        console.log('最終答題權限:', shouldBeMyTurn);
        console.log('==================');
        
        // 強制檢查：如果總題數 > 0，說明已經有人答過題，需要輪流
        if (totalQuestions > 0) {
            console.log('已有人答過題，強制檢查輪流答題權限');
            console.log('詳細權限分析:', {
                condition1: currentPlayer === 1 && isInviter,
                condition2: currentPlayer === 2 && !isInviter,
                currentPlayer,
                isInviter,
                shouldBeMyTurn
            });
        }
        
        if (shouldBeMyTurn) {
            button.onclick = () => checkAnswer(option, question.correctAnswer);
            button.style.cursor = 'pointer';
            console.log('設置按鈕為可點擊狀態');
        } else {
            button.disabled = true;
            button.style.cursor = 'not-allowed';
            button.style.opacity = '0.6';
            console.log('設置按鈕為禁用狀態');
        }
        
        optionsContainer.appendChild(button);
    });

    // 更新當前玩家指示器
    updateCurrentPlayerIndicator();
}

// 開始遊戲
async function startGame() {
    console.log('開始遊戲');
    gameStarted = true;
    currentQuestion = 0;
    questions = [];
    totalQuestions = 0;
    
    // 重置玩家分數
    player1.score = 0;
    player1.correct = 0;
    player2.score = 0;
    player2.correct = 0;
    
    // 確保邀請者先答題
    const isInviter = window.phpMemberId == invitationData?.from_user_id;
    if (isInviter) {
        // 如果我是邀請者，我應該是玩家1，先答題
        currentPlayer = 1;
        console.log('我是邀請者，設置為玩家1先答題');
    } else {
        // 如果我是被邀請者，對手是玩家1，我先等待
        currentPlayer = 1;
        console.log('我是被邀請者，設置對手為玩家1先答題，我等待');
    }
    
    console.log('回合設置完成:', { currentPlayer, isInviter, player1Name: player1.name, player2Name: player2.name });
    
    console.log(`遊戲開始！初始玩家：${player1.name}，對手：${player2.name}`);
    console.log('詳細遊戲狀態:', {
        currentPlayer,
        isInviter,
        player1: { id: player1.id, name: player1.name },
        player2: { id: player2.id, name: player2.name },
        myId: window.phpMemberId,
        invitationData: invitationData
    });
    
    // 隱藏所有等待視窗
    const waitingDifficultyModal = document.getElementById('waiting-difficulty-modal');
    const waitingModal = document.getElementById('waiting-modal');
    const difficultyModal = document.getElementById('difficulty-modal');
    
    if (waitingDifficultyModal) {
        waitingDifficultyModal.classList.add('hidden');
    }
    if (waitingModal) {
        waitingModal.classList.add('hidden');
    }
    if (difficultyModal) {
        difficultyModal.classList.add('hidden');
    }
    
    // 顯示遊戲容器
    const gameContainer = document.getElementById('game-container');
    if (gameContainer) {
        gameContainer.style.display = 'block';
        console.log('顯示遊戲容器');
    }
    
    // 更新顯示
    updatePlayerDisplay();
    updateCurrentPlayerIndicator();
    
    // 開始計時器
    startTimer();
    
    // 載入第一題
    loadQuestion();
    
    // 如果是線上對戰，立即同步初始狀態並開始同步檢查
    if (invitationId) {
        // 等待第一題載入完成後再同步
        setTimeout(async () => {
            // 同步題目到伺服器
            await syncQuestionsToServer();
            
            // 立即同步初始遊戲狀態
            try {
                const currentQuestionData = questions[currentQuestion] || null;
                
                await fetch('game-sync-api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'sync_answer',
                        invitation_id: invitationId,
                        player_id: window.phpMemberId,
                        selected_answer: null,
                        correct_answer: null,
                        is_correct: false,
                        current_question: currentQuestion,
                        current_player: currentPlayer, // 同步當前玩家
                        player1_score: player1.score,
                        player2_score: player2.score,
                        player1_correct: player1.correct,
                        player2_correct: player2.correct,
                        total_questions: totalQuestions,
                        current_question_data: currentQuestionData // 同步第一題數據
                    })
                });
                console.log('初始遊戲狀態和第一題已同步到伺服器');
            } catch (error) {
                console.error('同步初始狀態失敗:', error);
            }
        }, 500); // 等待500ms確保題目已載入
        
        // 啟動同步檢查
        startGameSync();
        console.log('同步功能已啟動');
    }
}
    
    // 隱藏所有相關模態視窗
    const modals = [
        'difficulty-modal',
        'friend-invite-modal',
        'received-invitation-modal',
        'waiting-modal',
        'waiting-difficulty-modal'
    ];
    
    modals.forEach(modalId => {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('hidden');
            console.log('隱藏模態視窗:', modalId);
        }
    });
    
    // 顯示遊戲容器
    const gameContainer = document.getElementById('game-container');
    if (gameContainer) {
        gameContainer.style.display = 'block';
        console.log('顯示遊戲容器');
    }


// 檢查答案
async function checkAnswer(selectedAnswer, correctAnswer) {
    // 檢查是否輪到當前玩家答題
    const isInviter = window.phpMemberId == invitationData?.from_user_id;
    const shouldBeMyTurn = (currentPlayer === 1 && isInviter) || (currentPlayer === 2 && !isInviter);
    
    if (!shouldBeMyTurn) {
        console.log('不是你的回合，無法答題');
        return;
    }
    
    const isCorrect = selectedAnswer === correctAnswer;
    
    console.log(`玩家${currentPlayer} (${currentPlayer === 1 ? player1.name : player2.name}) 選擇了 ${selectedAnswer}元，正確答案是 ${correctAnswer}元`);
    console.log(`答案${isCorrect ? '正確' : '錯誤'}`);
    
    if (isCorrect) {
        // 當前玩家得分
        if (currentPlayer === 1) {
            player1.score += 3;
            player1.correct += 1;
            console.log(`${player1.name} 答對了！得分：${player1.score}，答對：${player1.correct}`);
        } else {
            player2.score += 3;
            player2.correct += 1;
            console.log(`${player2.name} 答對了！得分：${player2.score}，答對：${player2.correct}`);
        }
        
        // 更新顯示
        updatePlayerDisplay();
        
        // 顯示正確提示
        showAnswerFeedback(true);
    } else {
        // 顯示錯誤提示
        showAnswerFeedback(false);
        console.log(`${currentPlayer === 1 ? player1.name : player2.name} 答錯了，不扣分`);
    }
    
    // 增加總題數
    totalQuestions++;
    document.getElementById('total-questions').textContent = totalQuestions;
    
    // 同步答案到伺服器
    if (invitationId) {
        try {
            // 獲取當前題目數據
            const currentQuestionData = questions[currentQuestion] || null;
            
            await fetch('game-sync-api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'sync_answer',
                    invitation_id: invitationId,
                    player_id: window.phpMemberId,
                    selected_answer: selectedAnswer,
                    correct_answer: correctAnswer,
                    is_correct: isCorrect,
                    current_question: currentQuestion,
                    player1_score: player1.score,
                    player2_score: player2.score,
                    player1_correct: player1.correct,
                    player2_correct: player2.correct,
                    total_questions: totalQuestions,
                    current_question_data: currentQuestionData, // 同步當前題目數據
                    last_action: 'answer_submitted',
                    last_action_by: window.phpMemberId,
                    waiting_for_opponent: true,
                    my_answer_count: (currentPlayer === 1 ? player1.correct : player2.correct) + (isCorrect ? 1 : 0)
                })
            });
            console.log('答案和題目已同步到伺服器，等待對手答題');
        } catch (error) {
            console.error('同步答案失敗:', error);
        }
    }
    
    // 立即切換到對手，禁止當前玩家繼續答題
    const previousPlayer = currentPlayer;
    currentPlayer = currentPlayer === 1 ? 2 : 1;
    console.log(`答題後立即切換：從 ${previousPlayer === 1 ? player1.name : player2.name} 切換到 ${currentPlayer === 1 ? player1.name : player2.name}`);
    
    // 立即同步玩家切換到伺服器
    if (invitationId) {
        try {
            await fetch('game-sync-api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'sync_answer',
                    invitation_id: invitationId,
                    player_id: window.phpMemberId,
                    selected_answer: selectedAnswer,
                    correct_answer: correctAnswer,
                    is_correct: isCorrect,
                    current_question: currentQuestion,
                    current_player: currentPlayer, // 同步切換後的玩家
                    player1_score: player1.score,
                    player2_score: player2.score,
                    player1_correct: player1.correct,
                    player2_correct: player2.correct,
                    total_questions: totalQuestions,
                    current_question_data: questions[currentQuestion] || null,
                    last_action: 'switch_player',
                    last_action_by: window.phpMemberId,
                    waiting_for_opponent: true
                })
            });
            console.log('玩家切換已同步到伺服器');
        } catch (error) {
            console.error('同步玩家切換失敗:', error);
        }
    }
    
    // 更新當前玩家指示器
    updateCurrentPlayerIndicator();
    
    // 重新顯示當前題目以更新選項按鈕狀態
    if (questions.length > currentQuestion) {
        displayQuestion(questions[currentQuestion]);
    }
    
    console.log(`等待對手 ${currentPlayer === 1 ? player2.name : player1.name} 答題...`);
    
    // 不要立即增加題目編號，等待雙方都答完後再切換
    // currentQuestion++ 會在雙方都答完後由同步機制處理
}

// 開始遊戲同步
function startGameSync() {
    console.log('開始遊戲同步檢查');
    // 每1秒檢查一次同步狀態，提高同步頻率
    const syncInterval = setInterval(async () => {
        if (gameStarted && invitationId) {
            try {
                await checkGameSync();
            } catch (error) {
                console.error('同步檢查失敗:', error);
            }
        } else {
            // 如果遊戲結束或沒有邀請ID，停止同步
            clearInterval(syncInterval);
            console.log('停止同步檢查');
        }
    }, 1000);
    
    // 保存interval ID以便後續清理
    window.gameSyncInterval = syncInterval;
}

// 同步題目到伺服器
async function syncQuestionsToServer() {
    if (!invitationId) return;
    
    try {
        console.log('同步題目到伺服器...');
        
        await fetch('game-sync-api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'sync_questions',
                invitation_id: invitationId,
                player_id: window.phpMemberId,
                questions: questions,
                total_questions: totalQuestions,
                current_difficulty: currentDifficulty
            })
        });
        console.log('題目同步成功');
    } catch (error) {
        console.error('題目同步失敗:', error);
    }
}

// 檢查遊戲同步狀態
async function checkGameSync() {
    try {
        const response = await fetch('game-sync-api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'get_game_state',
                invitation_id: invitationId,
                player_id: window.phpMemberId
            })
        });
        
        const result = await response.json();
        if (result.success && result.game_state) {
            const gameState = result.game_state;
            
            console.log('收到遊戲狀態同步：', gameState);
            
            // 同步題目數據
            if (gameState.questions && gameState.questions.length > 0 && questions.length === 0) {
                console.log('同步題目數據：', gameState.questions.length);
                questions = gameState.questions;
                totalQuestions = gameState.total_questions || questions.length;
            }
            
            // 同步遊戲狀態
            if (gameState.current_question !== undefined && gameState.current_question !== currentQuestion) {
                console.log('同步題目：', gameState.current_question);
                currentQuestion = gameState.current_question;
                
                // 如果有同步的題目數據，直接使用
                if (gameState.current_question_data) {
                    console.log('使用同步的題目數據：', gameState.current_question_data);
                    displayQuestion(gameState.current_question_data);
                } else if (questions.length > currentQuestion) {
                    // 使用本地題目數據
                    displayQuestion(questions[currentQuestion]);
                } else {
                    // 否則載入本地題目
                    loadQuestion();
                }
            }
            
            // 同步玩家分數
            if (gameState.player1_score !== undefined) {
                player1.score = gameState.player1_score;
                console.log('同步玩家1分數：', player1.score);
            }
            if (gameState.player2_score !== undefined) {
                player2.score = gameState.player2_score;
                console.log('同步玩家2分數：', player2.score);
            }
            
            // 同步正確答題數
            if (gameState.player1_correct !== undefined) {
                player1.correct = gameState.player1_correct;
            }
            if (gameState.player2_correct !== undefined) {
                player2.correct = gameState.player2_correct;
            }
            
            // 更新顯示
            updatePlayerDisplay();
            
            // 處理答案提交和玩家切換
            if (gameState.last_action === 'answer_submitted' && gameState.last_action_by !== window.phpMemberId) {
                console.log('檢測到對手已答題，準備切換玩家');
                
                // 強制切換到對手，確保輪流答題
                const previousPlayer = currentPlayer;
                currentPlayer = currentPlayer === 1 ? 2 : 1;
                console.log(`輪流答題：從 ${previousPlayer === 1 ? player1.name : player2.name} 切換到 ${currentPlayer === 1 ? player1.name : player2.name}`);
                
                // 更新當前玩家指示器
                updateCurrentPlayerIndicator();
                
                // 重新顯示當前題目以更新選項按鈕狀態
                if (questions.length > currentQuestion) {
                    displayQuestion(questions[currentQuestion]);
                }
                
                // 同步玩家切換到伺服器
                if (invitationId) {
                    try {
                        await fetch('game-sync-api.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                action: 'sync_answer',
                                invitation_id: invitationId,
                                player_id: window.phpMemberId,
                                current_player: currentPlayer,
                                last_action: 'switch_player',
                                last_action_by: window.phpMemberId
                            })
                        });
                        console.log('玩家切換已同步到伺服器');
                    } catch (error) {
                        console.error('同步玩家切換失敗:', error);
                    }
                }
            }
            
            // 處理題目切換 - 當對手答題後
            if (gameState.last_action === 'next_question' && gameState.last_action_by !== window.phpMemberId) {
                console.log('檢測到對手已答題，切換到新題目');
                if (gameState.current_question !== undefined && gameState.current_question !== currentQuestion) {
                    currentQuestion = gameState.current_question;
                    console.log('切換到新題目:', currentQuestion);
                    
                    // 生成新題目並顯示
                    let newQuestion;
                    switch (currentDifficulty) {
                        case 'easy':
                            newQuestion = generateEasyQuestion();
                            break;
                        case 'normal':
                            newQuestion = generateNormalQuestion();
                            break;
                        case 'hard':
                            newQuestion = generateHardQuestion();
                            break;
                        default:
                            newQuestion = generateNormalQuestion();
                    }
                    
                    // 更新題目數組
                    if (questions.length <= currentQuestion) {
                        questions[currentQuestion] = newQuestion;
                    }
                    
                    // 顯示新題目
                    displayQuestion(newQuestion);
                }
            }
            
            // 處理玩家切換同步
            if (gameState.last_action === 'switch_player' && gameState.last_action_by !== window.phpMemberId) {
                console.log('檢測到對手已切換玩家');
                if (gameState.current_player !== undefined && gameState.current_player !== currentPlayer) {
                    currentPlayer = gameState.current_player;
                    updateCurrentPlayerIndicator();
                    console.log('同步玩家切換到:', currentPlayer === 1 ? player1.name : player2.name);
                    
                    // 重新顯示當前題目以更新選項按鈕狀態
                    if (questions.length > currentQuestion) {
                        displayQuestion(questions[currentQuestion]);
                    }
                }
            } else if (gameState.current_player !== undefined && gameState.current_player !== currentPlayer) {
                console.log('同步當前玩家：', gameState.current_player, '從', currentPlayer);
                currentPlayer = gameState.current_player;
                updateCurrentPlayerIndicator();
                
                // 重新顯示當前題目以更新選項按鈕狀態
                if (questions.length > currentQuestion) {
                    displayQuestion(questions[currentQuestion]);
                }
            }
            
            // 同步分數和正確答題數
            if (gameState.player1_score !== undefined && gameState.player1_score !== player1.score || 
                gameState.player2_score !== undefined && gameState.player2_score !== player2.score) {
                console.log('同步分數');
                if (gameState.player1_score !== undefined) player1.score = gameState.player1_score;
                if (gameState.player2_score !== undefined) player2.score = gameState.player2_score;
                if (gameState.player1_correct !== undefined) player1.correct = gameState.player1_correct;
                if (gameState.player2_correct !== undefined) player2.correct = gameState.player2_correct;
                if (gameState.total_questions !== undefined) totalQuestions = gameState.total_questions;
                updatePlayerDisplay();
                document.getElementById('total-questions').textContent = totalQuestions;
            }
        } else {
            console.log('遊戲狀態同步失敗或無數據：', result);
        }
    } catch (error) {
        console.error('檢查遊戲同步失敗:', error);
    }
}

// 顯示答案反饋
function showAnswerFeedback(isCorrect) {
    const questionContainer = document.getElementById('question-container');
    const feedback = document.createElement('div');
    feedback.style.cssText = `
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        font-size: 2rem;
        font-weight: bold;
        color: ${isCorrect ? '#4caf50' : '#f44336'};
        background: rgba(255, 255, 255, 0.9);
        padding: 20px;
        border-radius: 10px;
        z-index: 100;
    `;
    feedback.textContent = isCorrect ? '✓ 答對了！' : '✗ 答錯了！';
    
    questionContainer.style.position = 'relative';
    questionContainer.appendChild(feedback);
    
    setTimeout(() => {
        questionContainer.removeChild(feedback);
    }, 1500);
}

// 更新玩家顯示
function updatePlayerDisplay() {
    // 更新玩家1信息
    document.getElementById('player1-name-display').textContent = player1.name;
    document.getElementById('player1-score').textContent = player1.score;
    document.getElementById('player1-correct').textContent = player1.correct;
    
    // 更新玩家2信息
    document.getElementById('player2-name-display').textContent = player2.name;
    document.getElementById('player2-score').textContent = player2.score;
    document.getElementById('player2-correct').textContent = player2.correct;
    
    // 更新玩家2頭像
    const player2Avatar = document.getElementById('player2-avatar');
    if (player2Avatar) {
        player2Avatar.src = player2.avatar;
    }
}

// 更新當前玩家指示器
function updateCurrentPlayerIndicator() {
    const player1Info = document.getElementById('player1-info');
    const player2Info = document.getElementById('player2-info');
    const currentTurnText = document.getElementById('current-turn');
    
    // 確保 currentPlayer 是有效的數字
    if (currentPlayer === undefined || currentPlayer === null) {
        console.log('currentPlayer 無效，重置為 1');
        currentPlayer = 1;
    }
    
    console.log(`更新當前玩家指示器：玩家${currentPlayer} (${currentPlayer === 1 ? player1.name : player2.name})`);
    
    if (currentPlayer === 1) {
        player1Info.classList.add('active');
        player2Info.classList.remove('active');
        currentTurnText.textContent = player1.name;
        console.log(`設置 ${player1.name} 為當前玩家（橙色背景）`);
    } else {
        player1Info.classList.remove('active');
        player2Info.classList.add('active');
        currentTurnText.textContent = player2.name;
        console.log(`設置 ${player2.name} 為當前玩家（橙色背景）`);
    }
}

// 開始計時器
function startTimer() {
    interval = setInterval(updateTimer, 1000);
}

// 更新計時器
function updateTimer() {
    if (!gamePaused) {
        timer--;
        document.getElementById('timer').textContent = timer;
        
        if (timer <= 0) {
            clearInterval(interval);
            endGame();
        }
    }
}

// 暫停遊戲
function pauseGame() {
    if (!gameStarted) return;
    
    if (!gamePaused) {
        gamePaused = true;
        savedTimer = timer;
        document.getElementById('pause-btn').textContent = '繼續遊戲';
        document.getElementById('pause-btn').classList.add('resume-btn');
    } else {
        gamePaused = false;
        timer = savedTimer;
        document.getElementById('pause-btn').textContent = '暫停遊戲';
        document.getElementById('pause-btn').classList.remove('resume-btn');
    }
}

// 結束遊戲
function endGame() {
    clearInterval(interval);
    gameStarted = false;
    
    // 清理同步檢查
    if (window.gameSyncInterval) {
        clearInterval(window.gameSyncInterval);
        window.gameSyncInterval = null;
        console.log('已清理同步檢查');
    }
    
    // 計算獎勵分數
    let player1Bonus = 0;
    let player2Bonus = 0;
    
    switch (currentDifficulty) {
        case 'easy':
            if (player1.score >= 15) player1Bonus = 20;
            if (player2.score >= 15) player2Bonus = 20;
            break;
        case 'normal':
            if (player1.score >= 20) player1Bonus = 50;
            if (player2.score >= 20) player2Bonus = 50;
            break;
        case 'hard':
            if (player1.score >= 25) player1Bonus = 100;
            if (player2.score >= 25) player2Bonus = 100;
            break;
    }
    
    player1.score += player1Bonus;
    player2.score += player2Bonus;
    
    // 顯示遊戲結束視窗
    showGameOverModal();
    
    // 保存遊戲結果
    saveGameResult();
}

// 顯示遊戲結束視窗
function showGameOverModal() {
    const modal = document.getElementById('game-over-modal');
    const player1Result = document.getElementById('player1-result');
    const player2Result = document.getElementById('player2-result');
    const winnerAnnouncement = document.getElementById('winner-announcement');
    
    // 更新結果顯示
    player1Result.querySelector('.player-name').textContent = player1.name;
    player1Result.querySelector('.final-score').textContent = player1.score + '分';
    player1Result.querySelector('.final-correct').textContent = '答對 ' + player1.correct + ' 題';
    
    player2Result.querySelector('.player-name').textContent = player2.name;
    player2Result.querySelector('.final-score').textContent = player2.score + '分';
    player2Result.querySelector('.final-correct').textContent = '答對 ' + player2.correct + ' 題';
    
    // 判斷勝負
    if (player1.score > player2.score) {
        winnerAnnouncement.textContent = `🏆 ${player1.name} 獲勝！`;
    } else if (player2.score > player1.score) {
        winnerAnnouncement.textContent = `🏆 ${player2.name} 獲勝！`;
    } else {
        winnerAnnouncement.textContent = '🤝 平手！';
    }
    
    modal.classList.remove('hidden');
}

// 保存遊戲結果
async function saveGameResult() {
    try {
        const response = await fetch('vegetable_cost_2P.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                player1_id: player1.id,
                player2_id: player2.id, // 使用實際的玩家2 ID
                player1_score: player1.score,
                player2_score: player2.score,
                difficulty: currentDifficulty,
                play_time: 60 - timer
            })
        });
        
        const result = await response.json();
        console.log('遊戲結果保存:', result);
        
        // 立即更新主頁面分數
        if (window.forceRefreshScore) {
            setTimeout(() => {
                window.forceRefreshScore();
            }, 1000); // 1秒後更新，確保資料庫已保存
        }
    } catch (error) {
        console.error('保存遊戲結果失敗:', error);
    }
}

// 重新開始遊戲
function restartGame() {
    // 隱藏遊戲結束視窗
    document.getElementById('game-over-modal').classList.add('hidden');
    
    // 隱藏遊戲容器
    document.getElementById('game-container').style.display = 'none';
    
    // 清理同步檢查
    if (window.gameSyncInterval) {
        clearInterval(window.gameSyncInterval);
        window.gameSyncInterval = null;
        console.log('已清理同步檢查');
    }
    
    // 重置遊戲狀態
    timer = 60;
    gamePaused = false;
    gameStarted = false;
    currentQuestion = 0;
    questions = [];
    
    document.getElementById('timer').textContent = timer;
    document.getElementById('pause-btn').textContent = '暫停遊戲';
    document.getElementById('pause-btn').classList.remove('resume-btn');
    
    // 重置邀請狀態
    invitedFriend = null;
    invitationId = null;
    invitationData = null;
    
    // 重置玩家狀態
    player1.score = 0;
    player1.correct = 0;
    player2.score = 0;
    player2.correct = 0;
    currentPlayer = 1;
    
    // 重置玩家2信息
    player2.id = 'local_player';
    player2.name = '本地玩家';
    player2.avatar = 'img/user.png';
    
    // 顯示好友邀請視窗
    showFriendInviteModal();
}

// 退出遊戲
function exitGame() {
    // 智能返回：回到上一頁，如果沒有上一頁則回到首頁
    if (document.referrer && document.referrer !== window.location.href) {
        history.back();
    } else {
        window.location.href = 'index.php';
    }
}

// 開始遊戲（帶難度）
async function startGameWithDifficulty(difficulty) {
    console.log('開始遊戲（帶難度）:', difficulty);
    currentDifficulty = difficulty;
    
    // 正確設置玩家身份 - 邀請者先答題
    const isInviter = window.phpMemberId == invitationData?.from_user_id;
    console.log('邀請者判斷:', { 
        myId: window.phpMemberId, 
        fromUserId: invitationData?.from_user_id, 
        isInviter,
        invitationData: invitationData
    });
    
    if (invitedFriend) {
        if (isInviter) {
            // 如果我是邀請者，我是玩家1（先答題），對手是玩家2
            player1.id = window.phpMemberId;
            player1.name = window.currentUser.member_name;
            player1.avatar = window.currentUser.avatar;
            
            player2.id = invitedFriend.id;
            player2.name = invitedFriend.name;
            player2.avatar = 'img/user.png';
            console.log('我是邀請者，設置為玩家1（先答題）');
        } else {
            // 如果我是被邀請者，對手是玩家1（先答題），我是玩家2
            player1.id = invitedFriend.id;
            player1.name = invitedFriend.name;
            player1.avatar = 'img/user.png';
            
            player2.id = window.phpMemberId;
            player2.name = window.currentUser.member_name;
            player2.avatar = window.currentUser.avatar;
            console.log('我是被邀請者，對手是玩家1（先答題）');
        }
        console.log('玩家身份設置完成:', { 
            player1: { id: player1.id, name: player1.name }, 
            player2: { id: player2.id, name: player2.name }, 
            isInviter, 
            note: '邀請者先答題',
            myId: window.phpMemberId,
            invitationData: invitationData
        });
    }
    
    // 設定時間
    switch (difficulty) {
        case 'easy':
            timer = 80;
            break;
        case 'normal':
            timer = 150;
            break;
        case 'hard':
            timer = 200;
            break;
    }
    
    savedTimer = timer;
    document.getElementById('timer').textContent = timer;
    
    // 確保食材數據已加載
    try {
        console.log('檢查食材數據是否已加載...');
        if (!ingredients.vegetables || ingredients.vegetables.length === 0) {
            console.log('食材數據未加載，正在加載...');
            await fetchIngredients();
        }
        console.log('食材數據已準備就緒');
        
        // 開始遊戲
        await startGame();
    } catch (error) {
        console.error('加載食材數據失敗:', error);
        alert('加載遊戲數據失敗，請重新整理頁面');
    }
}

// 難度選擇
async function selectDifficulty(difficulty) {
    console.log('選擇難度:', difficulty, 'invitationId:', invitationId, 'invitedFriend:', invitedFriend);
    currentDifficulty = difficulty;
    
    // 正確設置玩家身份 - 邀請者先答題
    const isInviter = window.phpMemberId == invitationData?.from_user_id;
    console.log('邀請者判斷:', { 
        myId: window.phpMemberId, 
        fromUserId: invitationData?.from_user_id, 
        isInviter,
        invitationData: invitationData
    });
    
    if (invitedFriend) {
        if (isInviter) {
            // 如果我是邀請者，我是玩家1（先答題），對手是玩家2
            player1.id = window.phpMemberId;
            player1.name = window.currentUser.member_name;
            player1.avatar = window.currentUser.avatar;
            
            player2.id = invitedFriend.id;
            player2.name = invitedFriend.name;
            player2.avatar = 'img/user.png';
            console.log('我是邀請者，設置為玩家1（先答題）');
        } else {
            // 如果我是被邀請者，對手是玩家1（先答題），我是玩家2
            player1.id = invitedFriend.id;
            player1.name = invitedFriend.name;
            player1.avatar = 'img/user.png';
            
            player2.id = window.phpMemberId;
            player2.name = window.currentUser.member_name;
            player2.avatar = window.currentUser.avatar;
            console.log('我是被邀請者，對手是玩家1（先答題）');
        }
        console.log('玩家身份設置完成:', { 
            player1: { id: player1.id, name: player1.name }, 
            player2: { id: player2.id, name: player2.name }, 
            isInviter, 
            note: '邀請者先答題',
            myId: window.phpMemberId,
            invitationData: invitationData
        });
    }
    
    // 設定時間
    switch (difficulty) {
        case 'easy':
            timer = 80;
            break;
        case 'normal':
            timer = 150;
            break;
        case 'hard':
            timer = 200;
            break;
    }
    
    savedTimer = timer;
    document.getElementById('timer').textContent = timer;
    
    // 更新邀請狀態，通知對手已選擇難度
    if (invitationId) {
        try {
            console.log('發送難度更新請求:', difficulty);
            const response = await fetch('game-invitation-api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'update_invitation_difficulty',
                    invitation_id: invitationId,
                    difficulty: difficulty
                })
            });
            
            const result = await response.json();
            console.log('更新難度結果:', result);
            
            if (result.success) {
                console.log('難度更新成功，等待對手確認');
                // 隱藏難度選擇視窗
                const difficultyModal = document.getElementById('difficulty-modal');
                if (difficultyModal) {
                    difficultyModal.classList.add('hidden');
                }
                
                // 顯示等待對手確認視窗
                showWaitingDifficultyModal();
                
                // 開始檢查對手是否已確認難度並開始遊戲
                setTimeout(checkOpponentDifficultySelection, 100);
            } else {
                console.error('難度更新失敗:', result.message);
                alert('難度設置失敗: ' + result.message);
            }
        } catch (error) {
            console.error('更新難度錯誤:', error);
            alert('難度設置時發生錯誤');
        }
    } else {
        console.log('沒有邀請ID，直接開始遊戲');
        // 隱藏難度選擇視窗
        const difficultyModal = document.getElementById('difficulty-modal');
        if (difficultyModal) {
            difficultyModal.classList.add('hidden');
        }
        
        // 確保食材數據已加載
        try {
            console.log('檢查食材數據是否已加載...');
            if (!ingredients.vegetables || ingredients.vegetables.length === 0) {
                console.log('食材數據未加載，正在加載...');
                await fetchIngredients();
            }
            console.log('食材數據已準備就緒');
            
            // 開始遊戲
            await startGame();
        } catch (error) {
            console.error('加載食材數據失敗:', error);
            alert('加載遊戲數據失敗，請重新整理頁面');
        }
    }
}

// 說明視窗控制
function openHelpModal() {
    document.getElementById('help-modal').classList.remove('hidden');
}

function closeHelpModal() {
    document.getElementById('help-modal').classList.add('hidden');
}

// 事件監聽器
document.addEventListener('DOMContentLoaded', function() {
    // 綁定按鈕事件
    const pauseBtn = document.getElementById('pause-btn');
    if (pauseBtn) {
        pauseBtn.addEventListener('click', pauseGame);
    }
    
    // 綁定說明按鈕事件
    document.querySelectorAll('.help-button').forEach(btn => {
        btn.addEventListener('click', openHelpModal);
    });
    
    // 點擊模態框外部關閉
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.add('hidden');
            }
        });
    });
});