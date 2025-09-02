// 線上狀態追蹤器
class OnlineTracker {
    constructor() {
        this.interval = null;
        this.isActive = false;
        this.init();
    }

    init() {
        // 每30秒更新一次線上狀態
        this.interval = setInterval(() => {
            this.updateOnlineStatus();
        }, 30000);

        // 頁面載入時立即更新
        this.updateOnlineStatus();

        // 頁面關閉或切換時標記為離線
        window.addEventListener('beforeunload', () => {
            this.markOffline();
        });

        // 頁面可見性變化時處理
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                // 頁面隱藏時不更新狀態
                this.isActive = false;
            } else {
                // 頁面可見時恢復更新
                this.isActive = true;
                this.updateOnlineStatus();
            }
        });

        // 用戶活動時更新狀態
        ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart'].forEach(event => {
            document.addEventListener(event, () => {
                if (this.isActive) {
                    this.updateOnlineStatus();
                }
            });
        });
    }

    updateOnlineStatus() {
        if (!this.isActive && !document.hidden) {
            this.isActive = true;
        }

        fetch('online_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ action: 'update' })
        })
        .then(response => {
            if (response.ok) {
                return response.json();
            } else {
                // 如果返回401或其他錯誤，靜默處理
                console.log('線上狀態更新跳過（未登入或會話過期）');
                return null;
            }
        })
        .then(data => {
            if (data && data.status === 'success') {
                console.log('線上狀態更新成功');
            }
        })
        .catch(error => {
            // 靜默處理錯誤，避免控制台污染
            console.log('線上狀態更新跳過');
        });
    }

    markOffline() {
        // 發送離線信號
        try {
            navigator.sendBeacon('online_status.php', JSON.stringify({ action: 'offline' }));
            console.log('離線信號已發送');
        } catch (error) {
            console.error('發送離線信號失敗:', error);
            // 備用方法：使用 fetch
            fetch('online_status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'offline' })
            }).catch(e => console.error('備用離線信號也失敗:', e));
        }
    }

    destroy() {
        if (this.interval) {
            clearInterval(this.interval);
            this.interval = null;
        }
    }
}

// 初始化線上追蹤器
if (typeof window !== 'undefined') {
    window.onlineTracker = new OnlineTracker();
}
