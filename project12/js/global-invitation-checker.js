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
                        <h3>好友邀請</h3>
                    </div>
                    <div class="global-invitation-body">
                        <p><span id="inviter-name"></span> 邀請你進行雙人記憶遊戲</p>
                        <div class="global-invitation-actions">
                            <button id="accept-invitation-btn" class="accept-btn">接受</button>
                            <button id="reject-invitation-btn" class="reject-btn">拒絕</button>
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
                // 顯示第一個收到的邀請
                const invitation = data.invitations[0];
                this.showInvitation(invitation);
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
                
                // 跳轉到雙人遊戲頁面
                window.location.href = `Memory-Game-2P.php?invitation=${this.currentInvitation.invitation_id}`;
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
    if (!window.location.pathname.includes('Memory-Game-2P.php')) {
        window.globalInvitationChecker = new GlobalInvitationChecker();
    }
}); 