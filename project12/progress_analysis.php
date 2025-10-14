<?php
require_once 'check_login.php';
$account = isset($_SESSION['account']) ? $_SESSION['account'] : '訪客';
$name = isset($_SESSION['name']) ? $_SESSION['name'] : '您好';
$member_id = isset($_SESSION['member_id']) ? $_SESSION['member_id'] : null;
$avatar_url = isset($_SESSION['avatar_url']) && $_SESSION['avatar_url'] ? htmlspecialchars($_SESSION['avatar_url']) : 'img/big.jpg';

if (!$member_id) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>個人進步分析</title>
    <link rel="stylesheet" href="css/main.css" />
    <link rel="stylesheet" href="css/mission.css" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <style>
        .progress-container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 0 20px;
        }
        
        .progress-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .progress-header h1 {
            margin: 0 0 10px 0;
            font-size: 2.5em;
        }
        
        .progress-header p {
            margin: 0;
            opacity: 0.9;
            font-size: 1.1em;
        }
        
        .stats-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card .icon {
            font-size: 3em;
            margin-bottom: 10px;
        }
        
        .stat-card .value {
            font-size: 2em;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }
        
        .stat-card .label {
            color: #666;
            font-size: 0.9em;
        }
        
        .analysis-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .analysis-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .analysis-card .header {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            color: white;
            padding: 20px;
            font-weight: bold;
            font-size: 1.2em;
        }
        
        .analysis-card .content {
            padding: 20px;
        }
        
        .chart-container {
            height: 300px;
            margin: 20px 0;
        }
        
        .ability-progress {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .ability-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .ability-card .title {
            font-size: 1.3em;
            font-weight: bold;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .progress-bar {
            background: #f0f0f0;
            border-radius: 10px;
            height: 20px;
            margin: 10px 0;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            border-radius: 10px;
            transition: width 0.8s ease;
        }
        
        .progress-fill.reaction { background: linear-gradient(90deg, #667eea, #764ba2); }
        .progress-fill.memory { background: linear-gradient(90deg, #43e97b, #38f9d7); }
        .progress-fill.logic { background: linear-gradient(90deg, #fa709a, #fee140); }
        
        .recommendations {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .recommendations h3 {
            margin-top: 0;
            color: #333;
        }
        
        .recommendation-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 15px;
            border-left: 4px solid #007bff;
        }
        
        .recommendation-item.warning {
            border-left-color: #ffc107;
            background: #fff3cd;
        }
        
        .recommendation-item.positive {
            border-left-color: #28a745;
            background: #d4edda;
        }
        
        .recommendation-item h4 {
            margin: 0 0 8px 0;
            font-size: 1.1em;
        }
        
        .recommendation-item p {
            margin: 0;
            color: #666;
        }
        
        .time-selector {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .time-selector select {
            padding: 10px 20px;
            border: 2px solid #ddd;
            border-radius: 25px;
            font-size: 1em;
            background: white;
        }
        
        .loading {
            text-align: center;
            padding: 50px;
            color: #666;
        }
        
        .loading .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px auto;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .trend-indicator {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.9em;
            font-weight: bold;
        }
        
        .trend-improving { background: #d4edda; color: #155724; }
        .trend-declining { background: #f8d7da; color: #721c24; }
        .trend-stable { background: #d1ecf1; color: #0c5460; }
        
        @media (max-width: 768px) {
            .analysis-grid {
                grid-template-columns: 1fr;
            }
            
            .progress-header h1 {
                font-size: 2em;
            }
        }
    </style>
</head>
<body>
    <div id="overlay" class="overlay" onclick="toggleSidebar()"></div>
    <div id="modalOverlay" class="overlay" style="display:none;" onclick="closeAllModals()"></div>
    <div id="sidebar" class="sidebar">
        <a href="index.php" class="jelly-btn jelly-red">首頁</a>
        <a href="game-category.php" class="jelly-btn jelly-red">全部遊戲</a>
        <a href="friend.php" class="jelly-btn jelly-green">好友列表</a>
        <a href="Ranking_list.php" class="jelly-btn jelly-green">排行榜</a>
        <div class="btn-group">
            <a href="personal-analysis.php" class="jelly-btn jelly-yellow">個人分析</a>
            <a href="progress_analysis.php" class="jelly-btn jelly-yellow active">進步分析</a>
            <a href="news.php" class="jelly-btn jelly-yellow">相關報導</a>
            <a href="us.php" class="jelly-btn jelly-yellow">關於我們</a>
        </div>
    </div>

    <!-- 功能選單 -->
    <header>
        <div id="menuButton" class="menu" onclick="toggleSidebar()">
            <img src="img/contents.png" alt="功能選單" class="menu-icon" />
            <span id="menuText" class="menu-text">功能選單</span>
        </div>

        <form class="search-bar" action="game.php" method="GET" onsubmit="return validateSearch()">
            <input type="text" name="keyword" id="searchInput" placeholder="搜尋遊戲">
        </form>
        <div class="user-icons">
            <a href="#" onclick="openMissionModal()">
                <span class="notification-bell">🔔</span>
            </a>
            <a href="#" onclick="openProfileModal();return false;">
                <img src="<?php echo $avatar_url; ?>" alt="使用者" class="profile">
            </a>
        </div>
    </header>

    <div class="status-bar">
        <div class="score">您的分數 <span id="scoreValue" style="color: red;">0</span> 💰</div>
        <div class="time">
            已遊玩時間 <span id="timeValue">00:00:00</span>
            <button onclick="showTimeDetail()" class="time-icon-btn">⏱️</button>
        </div>
    </div>

    <div class="progress-container">
        <div class="progress-header">
            <h1>📊 個人進步分析</h1>
            <p>追蹤您的遊戲能力成長軌跡</p>
        </div>

        <div class="time-selector">
            <select id="analysisRange" onchange="loadProgressData()">
                <option value="7">近 7 天</option>
                <option value="30" selected>近 30 天</option>
                <option value="60">近 60 天</option>
                <option value="90">近 90 天</option>
            </select>
        </div>

        <div id="loadingIndicator" class="loading">
            <div class="spinner"></div>
            <p>正在分析您的進步數據...</p>
        </div>

        <div id="progressContent" style="display: none;">
            <!-- 統計概覽 -->
            <div class="stats-overview">
                <div class="stat-card">
                    <div class="icon">🎮</div>
                    <div class="value" id="totalGames">0</div>
                    <div class="label">總遊戲次數</div>
                </div>
                <div class="stat-card">
                    <div class="icon">📈</div>
                    <div class="value" id="avgImprovement">0%</div>
                    <div class="label">平均改善率</div>
                </div>
                <div class="stat-card">
                    <div class="icon">🏆</div>
                    <div class="value" id="mostImproved">-</div>
                    <div class="label">最佳進步能力</div>
                </div>
                <div class="stat-card">
                    <div class="icon">⚠️</div>
                    <div class="value" id="needsAttention">-</div>
                    <div class="label">需要關注</div>
                </div>
            </div>

            <!-- 分析圖表 -->
            <div class="analysis-grid">
                <div class="analysis-card">
                    <div class="header">🎯 能力雷達圖</div>
                    <div class="content">
                        <div class="chart-container">
                            <canvas id="radarChart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="analysis-card">
                    <div class="header">📊 趨勢分析</div>
                    <div class="content">
                        <div class="chart-container">
                            <canvas id="trendChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 各項能力進步情況 -->
            <div class="ability-progress">
                <div class="ability-card">
                    <div class="title">
                        ⚡ 反應力
                        <span id="reactionTrend" class="trend-indicator"></span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill reaction" id="reactionProgress"></div>
                    </div>
                    <div>
                        <small>平均分數: <span id="reactionAvg">0</span> | 一致性: <span id="reactionConsistency">0</span>%</small>
                    </div>
                </div>
                
                <div class="ability-card">
                    <div class="title">
                        🧠 記憶力
                        <span id="memoryTrend" class="trend-indicator"></span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill memory" id="memoryProgress"></div>
                    </div>
                    <div>
                        <small>平均分數: <span id="memoryAvg">0</span> | 一致性: <span id="memoryConsistency">0</span>%</small>
                    </div>
                </div>
                
                <div class="ability-card">
                    <div class="title">
                        🔢 邏輯思維
                        <span id="logicTrend" class="trend-indicator"></span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill logic" id="logicProgress"></div>
                    </div>
                    <div>
                        <small>平均分數: <span id="logicAvg">0</span> | 一致性: <span id="logicConsistency">0</span>%</small>
                    </div>
                </div>
            </div>

            <!-- 個人化建議 -->
            <div class="recommendations">
                <h3>💡 個人化建議</h3>
                <div id="recommendationsList">
                    <!-- 建議將在這裡動態載入 -->
                </div>
            </div>
        </div>
    </div>

    <script>
        let radarChart = null;
        let trendChart = null;

        // 載入進步數據
        async function loadProgressData() {
            const range = document.getElementById('analysisRange').value;
            
            document.getElementById('loadingIndicator').style.display = 'block';
            document.getElementById('progressContent').style.display = 'none';

            try {
                const response = await fetch(`api/progress_analysis.php?days=${range}`);
                const data = await response.json();

                if (data.success) {
                    displayProgressData(data);
                } else {
                    throw new Error(data.message || '載入失敗');
                }
            } catch (error) {
                console.error('載入進步分析失敗:', error);
                alert('載入進步分析失敗: ' + error.message);
            } finally {
                document.getElementById('loadingIndicator').style.display = 'none';
            }
        }

        // 顯示進步數據
        function displayProgressData(data) {
            const analysis = data.progress_analysis;
            const overall = analysis.overall;

            // 更新統計概覽
            document.getElementById('totalGames').textContent = overall.total_games;
            document.getElementById('avgImprovement').textContent = overall.avg_improvement + '%';
            document.getElementById('mostImproved').textContent = getAbilityName(overall.most_improved);
            document.getElementById('needsAttention').textContent = getAbilityName(overall.needs_attention);

            // 更新各項能力數據
            updateAbilityCard('reaction', analysis.reaction);
            updateAbilityCard('memory', analysis.memory);
            updateAbilityCard('logic', analysis.logic);

            // 更新圖表
            updateRadarChart(data.radar_data);
            updateTrendChart(analysis);

            // 顯示建議
            displayRecommendations(analysis.recommendations);

            document.getElementById('progressContent').style.display = 'block';
        }

        // 更新能力卡片
        function updateAbilityCard(ability, data) {
            const avgElement = document.getElementById(ability + 'Avg');
            const consistencyElement = document.getElementById(ability + 'Consistency');
            const progressElement = document.getElementById(ability + 'Progress');
            const trendElement = document.getElementById(ability + 'Trend');

            avgElement.textContent = data.average_score;
            consistencyElement.textContent = data.consistency;
            progressElement.style.width = Math.min(100, data.average_score) + '%';

            // 設置趨勢指示器
            const trendText = getTrendText(data.trend);
            const trendClass = getTrendClass(data.trend);
            trendElement.textContent = trendText;
            trendElement.className = 'trend-indicator ' + trendClass;
        }

        // 更新雷達圖 - 使用加權分平均
        function updateRadarChart(radarData) {
            const ctx = document.getElementById('radarChart').getContext('2d');
            
            if (radarChart) {
                radarChart.destroy();
            }

            const data = {
                labels: ['反應力', '記憶力', '邏輯思維'],
                datasets: [{
                    label: '加權分數平均',
                    data: [
                        radarData?.reaction?.weighted_average || 0,
                        radarData?.memory?.weighted_average || 0,
                        radarData?.logic?.weighted_average || 0
                    ],
                    backgroundColor: 'rgba(102, 126, 234, 0.2)',
                    borderColor: 'rgba(102, 126, 234, 1)',
                    pointBackgroundColor: 'rgba(102, 126, 234, 1)',
                    pointBorderColor: '#fff',
                    pointHoverBackgroundColor: '#fff',
                    pointHoverBorderColor: 'rgba(102, 126, 234, 1)',
                    pointRadius: 5
                }, {
                    label: '傳統分數平均',
                    data: [
                        radarData?.reaction?.traditional_average || 0,
                        radarData?.memory?.traditional_average || 0,
                        radarData?.logic?.traditional_average || 0
                    ],
                    backgroundColor: 'rgba(250, 112, 154, 0.1)',
                    borderColor: 'rgba(250, 112, 154, 1)',
                    pointBackgroundColor: 'rgba(250, 112, 154, 1)',
                    pointBorderColor: '#fff',
                    pointHoverBackgroundColor: '#fff',
                    pointHoverBorderColor: 'rgba(250, 112, 154, 1)',
                    pointRadius: 4,
                    borderDash: [5, 5]
                }, {
                    label: '表現一致性',
                    data: [
                        radarData?.reaction?.consistency || 0,
                        radarData?.memory?.consistency || 0,
                        radarData?.logic?.consistency || 0
                    ],
                    backgroundColor: 'rgba(67, 233, 123, 0.1)',
                    borderColor: 'rgba(67, 233, 123, 1)',
                    pointBackgroundColor: 'rgba(67, 233, 123, 1)',
                    pointBorderColor: '#fff',
                    pointHoverBackgroundColor: '#fff',
                    pointHoverBorderColor: 'rgba(67, 233, 123, 1)',
                    pointRadius: 3
                }]
            };

            radarChart = new Chart(ctx, {
                type: 'radar',
                data: data,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        r: {
                            beginAtZero: true,
                            max: 100,
                            ticks: {
                                stepSize: 20
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'bottom'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.dataset.label;
                                    const value = Math.round(context.parsed.r * 100) / 100;
                                    
                                    // 添加額外資訊
                                    const ability = ['reaction', 'memory', 'logic'][context.dataIndex];
                                    const abilityData = radarData?.[ability];
                                    
                                    let extra = '';
                                    if (abilityData) {
                                        if (label === '加權分數平均') {
                                            const improvement = abilityData.improvement || 0;
                                            extra = improvement > 0 ? ` (↗${improvement.toFixed(1)}%)` : improvement < 0 ? ` (↘${Math.abs(improvement).toFixed(1)}%)` : '';
                                        }
                                        if (abilityData.play_count) {
                                            extra += ` [${abilityData.play_count}場]`;
                                        }
                                    }
                                    
                                    return `${label}: ${value}${extra}`;
                                }
                            }
                        }
                    }
                }
            });
        }

        // 更新趨勢圖
        function updateTrendChart(analysis) {
            const ctx = document.getElementById('trendChart').getContext('2d');
            
            if (trendChart) {
                trendChart.destroy();
            }

            const data = {
                labels: ['反應力', '記憶力', '邏輯思維'],
                datasets: [{
                    label: '改善百分比',
                    data: [
                        analysis.reaction.improvement_percentage,
                        analysis.memory.improvement_percentage,
                        analysis.logic.improvement_percentage
                    ],
                    backgroundColor: [
                        'rgba(102, 126, 234, 0.8)',
                        'rgba(67, 233, 123, 0.8)',
                        'rgba(250, 112, 154, 0.8)'
                    ],
                    borderColor: [
                        'rgba(102, 126, 234, 1)',
                        'rgba(67, 233, 123, 1)',
                        'rgba(250, 112, 154, 1)'
                    ],
                    borderWidth: 2
                }]
            };

            trendChart = new Chart(ctx, {
                type: 'bar',
                data: data,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: '改善百分比 (%)'
                            }
                        }
                    }
                }
            });
        }

        // 顯示建議
        function displayRecommendations(recommendations) {
            const container = document.getElementById('recommendationsList');
            container.innerHTML = '';

            if (recommendations && recommendations.length > 0) {
                recommendations.forEach(rec => {
                    const item = document.createElement('div');
                    item.className = 'recommendation-item ' + rec.type;
                    item.innerHTML = `
                        <h4>${rec.title}</h4>
                        <p>${rec.message}</p>
                    `;
                    container.appendChild(item);
                });
            } else {
                container.innerHTML = '<p>暫無個人化建議</p>';
            }
        }

        // 輔助函數
        function getAbilityName(ability) {
            const names = {
                'reaction': '反應力',
                'memory': '記憶力',
                'logic': '邏輯思維'
            };
            return names[ability] || '-';
        }

        function getTrendText(trend) {
            const texts = {
                'strongly_improving': '大幅進步 📈',
                'improving': '持續進步 ↗️',
                'stable': '表現穩定 ➡️',
                'declining': '需要加強 ↘️',
                'strongly_declining': '急需改善 📉',
                'no_data': '無資料 ❓'
            };
            return texts[trend] || '未知';
        }

        function getTrendClass(trend) {
            if (trend.includes('improving')) return 'trend-improving';
            if (trend.includes('declining')) return 'trend-declining';
            return 'trend-stable';
        }

        // 頁面載入時自動載入數據
        document.addEventListener('DOMContentLoaded', function() {
            loadProgressData();
        });

        // 基本功能
        let sidebarOpen = false;
        function toggleSidebar() {
            const sidebar = document.getElementById("sidebar");
            const menuText = document.getElementById("menuText");
            const overlay = document.getElementById("overlay");
            if (!sidebarOpen) {
                sidebar.style.left = "0";
                menuText.style.display = "none";
                overlay.style.display = "block";
            } else {
                sidebar.style.left = "-300px";
                menuText.style.display = "inline";
                overlay.style.display = "none";
            }
            sidebarOpen = !sidebarOpen;
        }

        function validateSearch() {
            const input = document.getElementById('searchInput').value.trim();
            if (input === '') {
                alert('請輸入關鍵字');
                return false;
            }
            return true;
        }
    </script>
    
    <script src="js/auto-save-time-fixed.js"></script>
    <script src="js/load-daily-tasks.js"></script>
    <script src="js/mission.js"></script>
    <script src="js/save-score.js"></script>
    <script src="js/get-score.js"></script>
    <script src="js/achievements.js"></script>
</body>
</html>
