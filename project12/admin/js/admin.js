// 後台管理系統 JavaScript

// 更新當前時間
function updateCurrentTime() {
    const now = new Date();
    const timeString = now.toLocaleString('zh-TW', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
    });
    document.getElementById('currentTime').textContent = timeString;
}

// 每秒更新時間
setInterval(updateCurrentTime, 1000);
updateCurrentTime();

// 載入儀表板數據
async function loadDashboardData() {
    try {
        const response = await fetch('api/dashboard_stats.php');
        const data = await response.json();
        
        if (data.success) {
            document.getElementById('totalUsers').textContent = data.stats.total_users;
            document.getElementById('totalGames').textContent = data.stats.today_games;
            document.getElementById('totalPlaytime').textContent = data.stats.today_playtime;
            document.getElementById('avgScore').textContent = data.stats.avg_score;
            
            // 載入圖表
            loadCharts(data.charts);
            loadRecentActivity(data.activities);
        }
    } catch (error) {
        console.error('載入儀表板數據失敗:', error);
    }
}

// 載入圖表
function loadCharts(chartData) {
    // 遊戲趨勢圖表
    const gameTrendCtx = document.getElementById('gameTrendChart');
    if (gameTrendCtx) {
        new Chart(gameTrendCtx, {
            type: 'line',
            data: {
                labels: chartData.trend.labels,
                datasets: [{
                    label: '遊戲次數',
                    data: chartData.trend.data,
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }
    
    // 遊戲類型分布圖表
    const gameTypeCtx = document.getElementById('gameTypeChart');
    if (gameTypeCtx) {
        new Chart(gameTypeCtx, {
            type: 'doughnut',
            data: {
                labels: chartData.types.labels,
                datasets: [{
                    data: chartData.types.data,
                    backgroundColor: [
                        '#667eea',
                        '#f093fb',
                        '#4facfe',
                        '#43e97b',
                        '#ffd89b'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
}

// 載入最新活動
function loadRecentActivity(activities) {
    const activityList = document.getElementById('activityList');
    if (!activityList) return;
    
    if (activities.length === 0) {
        activityList.innerHTML = '<div class="loading">暫無活動</div>';
        return;
    }
    
    activityList.innerHTML = activities.map(activity => `
        <div class="activity-item">
            <div class="activity-icon" style="background: ${getActivityColor(activity.type)}">
                ${getActivityIcon(activity.type)}
            </div>
            <div class="activity-content">
                <div class="activity-title">${activity.title}</div>
                <div class="activity-time">${activity.time}</div>
            </div>
        </div>
    `).join('');
}

// 獲取活動顏色
function getActivityColor(type) {
    const colors = {
        'game': 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
        'user': 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)',
        'system': 'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)',
        'achievement': 'linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)'
    };
    return colors[type] || colors.system;
}

// 獲取活動圖標
function getActivityIcon(type) {
    const icons = {
        'game': '🎮',
        'user': '👤',
        'system': '⚙️',
        'achievement': '🏆'
    };
    return icons[type] || '📝';
}

// 頁面載入時執行
document.addEventListener('DOMContentLoaded', function() {
    loadDashboardData();
    
    // 導航菜單高亮
    const currentPage = window.location.pathname.split('/').pop();
    const navItems = document.querySelectorAll('.nav-item');
    
    navItems.forEach(item => {
        const link = item.querySelector('.nav-link');
        if (link.getAttribute('href') === currentPage) {
            item.classList.add('active');
        }
    });
});

// 通用確認對話框
function confirmAction(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

// 通用成功提示
function showSuccess(message) {
    // 創建提示元素
    const toast = document.createElement('div');
    toast.className = 'toast success';
    toast.textContent = message;
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: #43e97b;
        color: white;
        padding: 15px 20px;
        border-radius: 5px;
        z-index: 1000;
        animation: slideIn 0.3s ease;
    `;
    
    document.body.appendChild(toast);
    
    // 3秒後自動移除
    setTimeout(() => {
        toast.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => {
            document.body.removeChild(toast);
        }, 300);
    }, 3000);
}

// 通用錯誤提示
function showError(message) {
    const toast = document.createElement('div');
    toast.className = 'toast error';
    toast.textContent = message;
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: #f5576c;
        color: white;
        padding: 15px 20px;
        border-radius: 5px;
        z-index: 1000;
        animation: slideIn 0.3s ease;
    `;
    
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => {
            document.body.removeChild(toast);
        }, 300);
    }, 3000);
}

// 添加CSS動畫
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    @keyframes slideOut {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
`;
document.head.appendChild(style); 