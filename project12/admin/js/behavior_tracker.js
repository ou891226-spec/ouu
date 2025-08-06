// 用戶行為軌跡追蹤系統

class BehaviorTracker {
    constructor() {
        this.sessionId = this.generateSessionId();
        this.startTime = Date.now();
        this.currentPage = window.location.pathname;
        this.trackEvents();
    }
    
    // 生成會話ID
    generateSessionId() {
        return 'session_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    }
    
    // 追蹤所有事件
    trackEvents() {
        this.trackPageView();
        this.trackGameEvents();
        this.trackUserActions();
        this.trackPageExit();
    }
    
    // 追蹤頁面瀏覽
    trackPageView() {
        this.logBehavior('page_view', {
            page_url: window.location.pathname,
            referrer: document.referrer
        });
    }
    
    // 追蹤遊戲事件
    trackGameEvents() {
        // 監聽遊戲開始事件
        document.addEventListener('gameStart', (e) => {
            this.logBehavior('game_start', {
                game_type: e.detail.gameType,
                difficulty: e.detail.difficulty
            });
        });
        
        // 監聽遊戲完成事件
        document.addEventListener('gameComplete', (e) => {
            this.logBehavior('game_complete', {
                game_type: e.detail.gameType,
                score: e.detail.score,
                play_time: e.detail.playTime
            });
        });
        
        // 監聽遊戲退出事件
        document.addEventListener('gameExit', (e) => {
            this.logBehavior('game_exit', {
                game_type: e.detail.gameType,
                exit_point: e.detail.exitPoint,
                play_time: e.detail.playTime
            });
        });
    }
    
    // 追蹤用戶操作
    trackUserActions() {
        // 點擊事件
        document.addEventListener('click', (e) => {
            const target = e.target;
            if (target.tagName === 'BUTTON' || target.tagName === 'A') {
                this.logBehavior('user_action', {
                    action: 'click',
                    element: target.tagName.toLowerCase(),
                    text: target.textContent?.trim(),
                    href: target.href || null
                });
            }
        });
        
        // 表單提交
        document.addEventListener('submit', (e) => {
            this.logBehavior('user_action', {
                action: 'form_submit',
                form_id: e.target.id || 'unknown',
                form_action: e.target.action
            });
        });
    }
    
    // 追蹤頁面退出
    trackPageExit() {
        window.addEventListener('beforeunload', () => {
            this.logBehavior('page_exit', {
                page_url: window.location.pathname,
                time_spent: Date.now() - this.startTime
            });
        });
    }
    
    // 記錄行為數據
    async logBehavior(actionType, additionalData = {}) {
        try {
            const behaviorData = {
                action_type: actionType,
                session_id: this.sessionId,
                page_url: window.location.pathname,
                timestamp: new Date().toISOString(),
                user_agent: navigator.userAgent,
                ...additionalData
            };
            
            // 發送到後端API
            await fetch('../admin/api/log_behavior.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(behaviorData)
            });
            
        } catch (error) {
            console.error('行為追蹤失敗:', error);
        }
    }
    
    // 手動觸發遊戲事件
    static triggerGameEvent(eventType, data) {
        const event = new CustomEvent(eventType, {
            detail: data
        });
        document.dispatchEvent(event);
    }
}

// 初始化行為追蹤器
if (typeof window !== 'undefined') {
    window.behaviorTracker = new BehaviorTracker();
}

// 導出給其他模組使用
if (typeof module !== 'undefined' && module.exports) {
    module.exports = BehaviorTracker;
} 