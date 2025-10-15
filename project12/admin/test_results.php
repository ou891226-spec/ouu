<?php
session_start();
require_once '../db.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$admin_name = $_SESSION['admin_name'] ?? '管理員';

// 預載部分用戶供下拉選單（最多200）
$users = [];
try {
    // 與 ability_analysis 採用相同寫法，確保欄位存在
    $stmt = $pdo->query("SELECT member_id, member_name AS display_name FROM member ORDER BY member_id DESC LIMIT 200");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $users = [];
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>測試結果 - 後台管理系統</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; padding: 15px; background: linear-gradient(135deg,rgb(255, 255, 255) 0%,rgb(255, 255, 255) 100%); min-height: 100vh; }
        .container { max-width: 1400px; margin: 0 auto; }
        .header { background: rgba(255,255,255,0.95); padding: 15px 20px; margin-bottom: 15px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); backdrop-filter: blur(10px); }
        .header h1 { margin: 0; color: #333; font-size: 24px; }
        .header p { margin: 5px 0 0 0; color: #666; font-size: 14px; }
        .nav { background: rgba(255,255,255,0.95); padding: 12px 20px; margin-bottom: 15px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); backdrop-filter: blur(10px); }
        .nav a { margin-right: 25px; text-decoration: none; color: #007bff; font-weight: 500; transition: color 0.3s ease; }
        .nav a:hover { color: #0056b3; }
        .nav a.active { color: #0056b3; font-weight: bold; }
        .filters { background: rgba(255,255,255,0.95); padding: 15px 20px; margin-bottom: 15px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); backdrop-filter: blur(10px); }
        .filters form { display: flex; gap: 15px; align-items: end; flex-wrap: wrap; }
        .filters select, .filters input { padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; }
        .filters button { padding: 8px 20px; background: linear-gradient(45deg, #007bff, #0056b3); color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 500; transition: transform 0.2s ease; }
        .filters button:hover { transform: translateY(-1px); }
        .card { background: rgba(255,255,255,0.95); padding: 20px; margin-bottom: 15px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); backdrop-filter: blur(10px); }
        .card h3 { margin-top: 0; color: #333; font-size: 18px; }
        .logout { float: right; background: linear-gradient(45deg, #dc3545, #c82333); color: white; padding: 8px 16px; text-decoration: none; border-radius: 6px; font-weight: 500; transition: transform 0.2s ease; margin-top: -60px; }
        .logout:hover { transform: translateY(-1px); text-decoration: none; }
        .formula-box { background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); padding: 15px; border-radius: 8px; margin-bottom: 15px; border-left: 4px solid #007bff; }
        .formula-box h4 { margin-top: 0; color: #007bff; font-size: 16px; }
        .formula-box p { margin: 8px 0; font-size: 14px; }
        .formula-box code { background: #fff; padding: 3px 6px; border-radius: 4px; font-family: 'Courier New', monospace; font-size: 13px; border: 1px solid #ddd; }
        .loading { text-align: center; padding: 30px; color: #666; font-size: 16px; }
        .error { background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%); color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 15px; border-left: 4px solid #dc3545; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; }
        .stat-card { background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); padding: 15px; border-radius: 8px; text-align: center; border: 1px solid #dee2e6; }
        .stat-card h4 { margin: 0 0 10px 0; color: #495057; font-size: 16px; }
        .stat-card p { margin: 5px 0; font-size: 14px; color: #6c757d; }
        .stat-card strong { color: #007bff; }
        #abilityChart { max-height: 400px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>測試結果分析</h1>
            <p>歡迎，<?php echo htmlspecialchars($admin_name); ?></p>
            <a href="logout.php" class="logout">登出</a>
        </div>

        <div class="nav">
            <a href="game_records.php">🎮 遊戲紀錄</a>
            <a href="user_behavior.php">👤 行為軌跡</a>
            <a href="question_management.php">🎯 遊戲管理</a>
            <a href="user_management.php">👥 用戶管理</a>
            <a href="ability_analysis.php">🧠 能力分析</a>
            <a href="ai_analysis_history.php">🤖 AI分析歷史</a>
            <a href="test_results.php" class="active">📈 測試結果</a>
            <a href="custom_score_trend.php">📊 趨勢分析</a>
            <a href="baseline_time_management.php">📊 雷達圖分析</a>
        </div>

        <div class="formula-box">
            <h4>📈 提升率計算公式</h4>
            <p><strong>提升率（%）：</strong> <code>(最近平均分 - 第一次平均分) / 第一次平均分 × 100%</code></p>
            <p><em>此處的「平均分」是能力值分數 (0-100)。</em></p>
        </div>

        <div class="filters">
            <form id="filterForm">
                <div>
                    <label>使用者：</label>
                    <select id="memberId">
                        <option value="">全部用戶</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?php echo $u['member_id']; ?>"><?php echo htmlspecialchars($u['display_name'] . ' (#' . $u['member_id'] . ')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>時間範圍：</label>
                    <select id="range">
                        <option value="7d">近7天</option>
                        <option value="30d" selected>近30天</option>
                        <option value="90d">近90天</option>
                        <option value="all">全部</option>
                    </select>
                </div>
                <button type="submit">套用</button>
            </form>
        </div>

        <div id="errorContainer"></div>

        <div class="card">
            <h3 id="chartTitle">📈 能力值趨勢圖</h3>
            <div id="chartContainer">
                <div class="loading">載入中...</div>
            </div>
            <canvas id="abilityChart" style="display: none; max-height: 350px;"></canvas>
        </div>

        <div class="card">
            <h3>📊 統計摘要</h3>
            <div id="statsSummary">
                <div class="loading">載入中...</div>
            </div>
        </div>


    </div>

    <script>
        const $ = (id) => document.getElementById(id);
        let abilityChart;

        async function loadData() {
            const memberId = $('memberId').value;
            const range = $('range').value;
            const qs = new URLSearchParams();
            if (memberId) qs.set('member_id', memberId);
            if (range) qs.set('range', range);
            
            try {
                $('chartContainer').innerHTML = '<div class="loading">載入中...</div>';
                $('statsSummary').innerHTML = '<div class="loading">載入中...</div>';
                $('errorContainer').innerHTML = '';
                
                const res = await fetch('api/test_results.php?' + qs.toString());
                const data = await res.json();
                
                if (!data.success) {
                    throw new Error(data.message || '載入失敗');
                }

                // 更新標題
                updateTitle(data);
                
                // 顯示折線圖
                displayChart(data);
                
                // 顯示統計摘要
                displayStats(data.stats);

            } catch (error) {
                console.error('Error:', error);
                $('errorContainer').innerHTML = `<div class="error">錯誤：${error.message}</div>`;
                $('chartContainer').innerHTML = '<div class="loading">載入失敗</div>';
                $('statsSummary').innerHTML = '<div class="loading">載入失敗</div>';
            }
        }

        function displayChart(data) {
            const ctx = $('abilityChart').getContext('2d');
            
            if (abilityChart) {
                abilityChart.destroy();
            }
            
            // ***【修正點 1】***：從後端實際傳回的 improvement_stats 欄位取值
            const improvementData = data.improvement_stats;

            if (!improvementData || Object.keys(improvementData).length === 0) {
                $('chartContainer').innerHTML = '<div class="loading">無提升率數據</div>';
                $('abilityChart').style.display = 'none';
                return;
            }

            const chartData = {
                labels: ['⚡ 反應力', '🧠 記憶力', '🔢 算術邏輯力'],
                datasets: [
                    {
                        label: '提升率 (%)',
                        data: [
                            // ***【修正點 2】***：取用 nested 的 improvement_rate 數值
                            improvementData.reaction?.improvement_rate || 0,
                            improvementData.memory?.improvement_rate || 0,
                            improvementData.logic?.improvement_rate || 0
                        ],
                        backgroundColor: ['#667eea', '#43e97b', '#f093fb'], 
                        borderColor: ['#667eea', '#43e97b', '#f093fb'],
                        borderWidth: 1
                    }
                ]
            };


            abilityChart = new Chart(ctx, {
                type: 'bar', // 改為長條圖
                data: chartData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            // max: 100, // 提升率可超過 100%，因此移除 max 限制
                            grid: {
                                color: 'rgba(0,0,0,0.1)'
                            },
                            title: {
                                display: true,
                                text: '提升率 (%)', // 修改 Y 軸標題
                                font: {
                                    size: 12,
                                    weight: 'bold'
                                }
                            }
                        },
                        x: {
                            grid: {
                                color: 'rgba(0,0,0,0.1)'
                            },
                            title: {
                                display: true,
                                text: '能力項目', // 修改 X 軸標題
                                font: {
                                    size: 12,
                                    weight: 'bold'
                                }
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            backgroundColor: 'rgba(0,0,0,0.8)',
                            titleColor: 'white',
                            bodyColor: 'white',
                            borderColor: 'rgba(255,255,255,0.2)',
                            borderWidth: 1,
                            callbacks: {
                                label: (context) => {
                                    const value = context.parsed.y;
                                    return `${context.dataset.label}: ${value !== null ? value.toFixed(1) : 'N/A'}%`; // 加上 %
                                }
                            }
                        },
                        legend: {
                            display: false, // 長條圖通常不需要圖例，因為只有一個數據集
                        }
                    }
                }
            });

            $('chartContainer').innerHTML = '';
            $('abilityChart').style.display = 'block';
        }

        // ... (其他函式不變)

        function displayStats(stats) {
            if (!stats) {
                $('statsSummary').innerHTML = '<p>無統計資料</p>';
                return;
            }

            const statsHtml = `
                <div class="stats-grid">
                    <div class="stat-card">
                        <h4>⚡ 反應力</h4>
                        <p><strong>第一次平均分：</strong>${stats.reaction?.first_avg?.toFixed(1) || 'N/A'}</p>
                        <p><strong>最近平均分：</strong>${stats.reaction?.recent_avg?.toFixed(1) || 'N/A'}</p>
                        <p><strong>提升率：</strong><strong style="color: ${stats.reaction?.improvement_rate >= 0 ? '#43e97b' : '#dc3545'};">${stats.reaction?.improvement_rate >= 0 ? '+' : ''}${stats.reaction?.improvement_rate?.toFixed(1) || 'N/A'}%</strong></p>
                    </div>
                    <div class="stat-card">
                        <h4>🧠 記憶力</h4>
                        <p><strong>第一次平均分：</strong>${stats.memory?.first_avg?.toFixed(1) || 'N/A'}</p>
                        <p><strong>最近平均分：</strong>${stats.memory?.recent_avg?.toFixed(1) || 'N/A'}</p>
                        <p><strong>提升率：</strong><strong style="color: ${stats.memory?.improvement_rate >= 0 ? '#43e97b' : '#dc3545'};">${stats.memory?.improvement_rate >= 0 ? '+' : ''}${stats.memory?.improvement_rate?.toFixed(1) || 'N/A'}%</strong></p>
                    </div>
                    <div class="stat-card">
                        <h4>🔢 算術邏輯力</h4>
                        <p><strong>第一次平均分：</strong>${stats.logic?.first_avg?.toFixed(1) || 'N/A'}</p>
                        <p><strong>最近平均分：</strong>${stats.logic?.recent_avg?.toFixed(1) || 'N/A'}</p>
                        <p><strong>提升率：</strong><strong style="color: ${stats.logic?.improvement_rate >= 0 ? '#43e97b' : '#dc3545'};">${stats.logic?.improvement_rate >= 0 ? '+' : ''}${stats.logic?.improvement_rate?.toFixed(1) || 'N/A'}%</strong></p>
                    </div>
                </div>
            `;
            
            $('statsSummary').innerHTML = statsHtml;
        }

        function updateTitle(data) {
            const memberId = $('memberId').value;
            if (memberId) {
                // 找到選擇的用戶名稱
                const selectElement = $('memberId');
                const selectedOption = selectElement.options[selectElement.selectedIndex];
                const userName = selectedOption.text.split(' (')[0]; // 移除ID部分
                $('chartTitle').textContent = `📊 ${userName} 的能力提升率分析`;
            } else {
                $('chartTitle').textContent = '📊 能力提升率分析';
            }
        }

        document.getElementById('filterForm').addEventListener('submit', function(e){
            e.preventDefault();
            loadData();
        });

        document.addEventListener('DOMContentLoaded', loadData);
    </script>
</body>
</html>
