// 全域邀請檢查器
// 在任何頁面都能檢查並顯示好友邀請

class GlobalInvitationChecker {
    constructor() {
        this.checkInterval = null;
        this.currentUserId = null;
        this.isChecking = false;
        this.init();
    }

    init() {
        // 獲取當前用戶ID
        this.currentUserId = this.getCurrentMemberId();
        
        // 清除任何可能觸發瀏覽器警告的事件監聽器
        window.onbeforeunload = null;
        
        // 創建邀請通知元素
        this.createInvitationModal();
        
        // 開始檢查邀請
        this.startChecking();
        
        console.log('全域邀請檢查器已初始化，用戶ID:', this.currentUserId);
    }

    getCurrentMemberId() {
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

    createInvitationModal() {
        // 檢查是否已存在邀請模態框
        if (document.getElementById('global-invitation-modal')) {
            return;
        }

        const modalHTML = `
            <div id="global-invitation-modal" class="global-invitation-modal hidden">
                <div class="global-invitation-content">
                    <div class="global-invitation-header">
                        <h3>收到遊戲邀請</h3>
                    </div>
                    <div class="global-invitation-body">
                        <p><span id="inviter-name"></span> 邀請你進行<span id="game-type-text">翻牌對對樂</span></p>
                        <div class="global-invitation-actions">
                            <button id="accept-invitation-btn" class="accept-btn">接受邀請</button>
                            <button id="reject-invitation-btn" class="reject-btn">拒絕邀請</button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHTML);

        // 添加事件監聽器
        document.getElementById('accept-invitation-btn').addEventListener('click', () => {
            this.acceptInvitation();
        });

        document.getElementById('reject-invitation-btn').addEventListener('click', () => {
            this.rejectInvitation();
        });
    }

    startChecking() {
        if (this.checkInterval) {
            clearInterval(this.checkInterval);
        }

        // 每3秒檢查一次邀請
        this.checkInterval = setInterval(() => {
            this.checkInvitations();
        }, 3000);

        console.log('開始全域邀請檢查');
    }

    stopChecking() {
        if (this.checkInterval) {
            clearInterval(this.checkInterval);
            this.checkInterval = null;
        }
    }

    async checkInvitations() {
        if (this.isChecking) return;
        
        this.isChecking = true;

        try {
            const response = await fetch('game-invitation-api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'get_pending_invitations',
                    user_id: this.currentUserId
                })
            });

            const data = await response.json();

            if (data.success && data.invitations && data.invitations.length > 0) {
                // 檢查是否有對手退出的邀請
                const quitInvitation = data.invitations.find(inv => inv.status === 'quit');
                if (quitInvitation) {
                    console.log('檢測到對手退出，停止邀請檢查');
                    this.stopChecking();
                    return;
                }
                
                // 只顯示真正待處理的邀請（pending狀態）
                const pendingInvitations = data.invitations.filter(inv => 
                    inv.status === 'pending'
                );
                
                if (pendingInvitations.length > 0) {
                    // 顯示第一個待處理的邀請
                    const invitation = pendingInvitations[0];
                    this.showInvitation(invitation);
                }
            }
        } catch (error) {
            console.error('檢查邀請錯誤:', error);
        } finally {
            this.isChecking = false;
        }
    }

    showInvitation(invitation) {
        // 檢查是否已經顯示了邀請
        const modal = document.getElementById('global-invitation-modal');
        if (!modal || !modal.classList.contains('hidden')) {
            return;
        }

        // 更新邀請信息
        document.getElementById('inviter-name').textContent = invitation.from_user_name || '好友';
        
        // 根據遊戲類型更新文字
        const gameTypeText = document.getElementById('game-type-text');
        const gameType = invitation.game_type || 'memory_game_2p';
        
        console.log('遊戲類型:', gameType);
        console.log('邀請對象:', invitation);
        
        switch(gameType) {
            case 'memory_game_2p':
                gameTypeText.textContent = '翻牌對對樂';
                break;
            case 'prisoner_game_2p':
                gameTypeText.textContent = '追蹤犯人遊戲';
                break;
            case 'rhythm_game_2p':
            case 'rhythm_game_multiplayer':
                gameTypeText.textContent = '節奏遊戲';
                console.log('設置為節奏遊戲');
                break;
            case 'text_color_2p':
                gameTypeText.textContent = '看字選色遊戲';
                break;
            default:
                gameTypeText.textContent = '遊戲';
                console.log('使用預設遊戲文字');
        }
        
        console.log('最終遊戲文字:', gameTypeText.textContent);
        
        // 存儲當前邀請信息
        this.currentInvitation = invitation;
        
        // 顯示邀請模態框
        modal.classList.remove('hidden');
        
        console.log('顯示全域邀請:', invitation);
    }

    hideInvitation() {
        const modal = document.getElementById('global-invitation-modal');
        if (modal) {
            modal.classList.add('hidden');
        }
    }

    async acceptInvitation() {
        if (!this.currentInvitation) return;

        try {
            const response = await fetch('game-invitation-api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'accept_invitation',
                    invitation_id: this.currentInvitation.invitation_id,
                    user_id: this.currentUserId
                })
            });

            const data = await response.json();

            if (data.success) {
                console.log('邀請已接受');
                this.hideInvitation();
                
                // 清除任何未保存的變更狀態，避免瀏覽器警告
                window.onbeforeunload = null;
                
                // 如果目標頁面有 clearBeforeUnload 函數，也調用它
                if (typeof window.clearBeforeUnload === 'function') {
                    window.clearBeforeUnload();
                }
                
                // 根據遊戲類型跳轉到對應的遊戲頁面
                const gameType = this.currentInvitation.game_type || 'memory_game_2p';
                let gameUrl = 'Memory-Game-2P.php';
                
                switch(gameType) {
                    case 'memory_game_2p':
                        gameUrl = 'Memory-Game-2P.php';
                        break;
                    case 'prisoner_game_2p':
                        gameUrl = 'prisoner.php?mode=2p';
                        break;
                    case 'rhythm_game_2p':
                    case 'rhythm_game_multiplayer':
                        gameUrl = 'rhythm_game_multiplayer.php';
                        break;
                    case 'text_color_2p':
                        gameUrl = 'text-color.php?mode=2p';
                        break;
                }
                
                // 使用 replace 而不是 href 來避免瀏覽器歷史記錄問題
                window.location.replace(`${gameUrl}?invitation=${this.currentInvitation.invitation_id}`);
            } else {
                alert('接受邀請失敗: ' + data.message);
            }
        } catch (error) {
            console.error('接受邀請錯誤:', error);
            alert('接受邀請時發生錯誤');
        }
    }

    async rejectInvitation() {
        if (!this.currentInvitation) return;

        try {
            const response = await fetch('game-invitation-api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'reject_invitation',
                    invitation_id: this.currentInvitation.invitation_id,
                    user_id: this.currentUserId
                })
            });

            const data = await response.json();

            if (data.success) {
                console.log('邀請已拒絕');
                this.hideInvitation();
                this.currentInvitation = null;
                
                // 繼續檢查邀請，以便接收新的邀請
                console.log('繼續檢查新的邀請');
            } else {
                alert('拒絕邀請失敗: ' + data.message);
            }
        } catch (error) {
            console.error('拒絕邀請錯誤:', error);
            alert('拒絕邀請時發生錯誤');
        }
    }
}

// 頁面載入時初始化全域邀請檢查器
document.addEventListener('DOMContentLoaded', function() {
    // 只在非雙人遊戲頁面初始化（避免重複檢查）
    if (!window.location.pathname.includes('Memory-Game-2P.php') && 
        !window.location.pathname.includes('prisoner.php') &&
        !window.location.pathname.includes('rhythm_game.php') &&
        !window.location.pathname.includes('rhythm_game_multiplayer.php') &&
        !window.location.pathname.includes('text-color.php')) {
        window.globalInvitationChecker = new GlobalInvitationChecker();
    }
}); 