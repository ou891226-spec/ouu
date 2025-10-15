<?php
// custom_score_trend.php (樣式與 test_results.php 統一)
session_start();
require_once '../db.php'; // 確保路徑正確

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$admin_name = $_SESSION['admin_name'] ?? '管理員';

// 預載部分用戶供下拉選單（最多200）
$users = [];
try {
    // 這裡的查詢必須與 test_results.php 中的寫法一致
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
    <title>自訂分數趨勢圖 - 後台管理系統</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; }
        .header { background: white; padding: 20px; margin-bottom: 20px; border-radius: 5px; }
        .nav { background: white; padding: 15px; margin-bottom: 20px; border-radius: 5px; }
        .nav a { margin-right: 20px; text-decoration: none; color: #007bff; }
        .nav a:hover { text-decoration: underline; }
        .nav a.active { color: #0056b3; font-weight: bold; }
        .logout { 
            float: right; 
            background: #dc3545; 
            color: white; 
            padding: 8px 16px; 
            text-decoration: none; 
            border-radius: 5px; 
            font-weight: bold;
            transition: background-color 0.3s ease;
            margin-top: -85px;
        }
        .logout:hover { 
            background: #c82333; 
            text-decoration: none; 
        }


        .filters { background: white; padding: 20px; margin-bottom: 20px; border-radius: 5px; }
        .filters form { display: flex; gap: 15px; align-items: end; flex-wrap: wrap; }
        .filters select, .filters input { padding: 8px; border: 1px solid #ddd; border-radius: 3px; }
        .filters button { 
            padding: 8px 15px; 
            background: #007bff; 
            color: white; 
            border: none; 
            border-radius: 3px; 
            cursor: pointer; 
            font-size: 14px;
            min-width: 80px;
        }
        .filters button:hover { background: #0056b3; }
        
        .card { background: white; padding: 20px; margin-bottom: 20px; border-radius: 5px; }
        .card h3 { margin-top: 0; color: #444; font-size: 1.2em; }

        /* 圖表與錯誤訊息樣式 */
        .chart-container { height: 400px; width: 100%; position: relative; }
        .loading { text-align: center; padding: 50px; color: #6c757d; font-size: 18px; }
        .error-container { color: #dc3545; background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 8px; margin-top: 20px; text-align: center; }
        
        .formula-box { 
            background-color: #f8f9fa; 
            padding: 15px; 
            margin-top: 15px; 
            border-radius: 5px; 
            border: 1px solid #dee2e6;
        }
        .formula-box code { 
            background-color: #e9ecef; 
            padding: 2px 4px; 
            border-radius: 3px; 
            font-weight: bold; 
            color: #495057;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>自訂分數趨勢分析</h1>
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
            <a href="test_results.php">📈 測試結果</a>
            <a href="custom_score_trend.php" class="active">📊 趨勢分析</a>
            <a href="baseline_time_management.php">📊 雷達圖分析</a>
        </div>


        <div class="filters">
            <form id="filterForm">
            <label for="memberId">使用者：</label>
            <select id="memberId" name="member_id">
                <option value="">全部用戶</option>
                <?php foreach ($users as $user): ?>
                    <option value="<?php echo htmlspecialchars($user['member_id']); ?>">
                        <?php echo htmlspecialchars($user['display_name']) . ' (ID: ' . htmlspecialchars($user['member_id']) . ')'; ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="range">時間範圍：</label>
            <select id="range" name="range">
                <option value="30d" selected>近 30 天</option>
                <option value="7d">近 7 天</option>
                <option value="90d">近 90 天</option>
                <option value="all">所有數據</option>
            </select>
            
            <button type="submit">套用篩選</button>
        </form>
        </div>

        <div id="formulaCard" class="card">
            <h3>📈 自訂分數 (V) 公式說明</h3>
            <div class="formula-box">
                <p><strong>公式：</strong> <code>V = score + P_t</code></p>
                <p><strong>時間懲罰/獎勵項 ($P_t$):</strong></p>
                <ul>
                    <li>如果 <code>time &lt; 90</code>: <code>P_t = cos(time)</code></li>
                    <li>如果 <code>time &ge; 90</code>: <code>P_t = sin(time - 90)</code></li>
                </ul>
            </div>
        </div>

        <div id="customVTrendCard" class="card">
            <h3 id="chartTitle">📉 自訂分數 (V) 週趨勢圖</h3>
            <div id="chartContainer" class="chart-container">
                <div id="loadingMessage" class="loading">正在載入數據...</div>
                <canvas id="customVChart" style="display: none;"></canvas>
            </div>
        </div>
        
        <div id="errorContainer" class="error-container" style="display: none;"></div>
    </div>

    <script>
        const $ = id => document.getElementById(id);
        let customVChart = null;

        function updateTitle(data) {
            const memberId = $('memberId').value;
            if (memberId) {
                // 找到選擇的用戶名稱
                const selectElement = $('memberId');
                const selectedOption = selectElement.options[selectElement.selectedIndex];
                const userName = selectedOption.text.split(' (')[0]; // 移除ID部分
                $('chartTitle').textContent = `📉 ${userName} 的自訂分數 (V) 週趨勢圖`;
            } else {
                $('chartTitle').textContent = '📉 自訂分數 (V) 週趨勢圖';
            }
        }

        function displayCustomVChart(data) {
            const ctx = $('customVChart').getContext('2d');
            
            if (customVChart) {
                customVChart.destroy();
            }

            if (!data.labels || data.labels.length === 0) {
                $('loadingMessage').textContent = '無趨勢數據';
                $('customVChart').style.display = 'none';
                $('loadingMessage').style.display = 'block';
                return;
            }

            const chartData = {
                labels: data.labels,
                datasets: [
                    {
                        label: '⚡ 反應力 (V)',
                        data: data.reaction,
                        borderColor: '#667eea',
                        backgroundColor: 'rgba(102, 126, 234, 0.2)',
                        tension: 0.3,
                        fill: false,
                        pointRadius: 5 
                    },
                    {
                        label: '🧠 記憶力 (V)',
                        data: data.memory,
                        borderColor: '#43e97b',
                        backgroundColor: 'rgba(67, 233, 123, 0.2)',
                        tension: 0.3,
                        fill: false,
                        pointRadius: 5
                    },
                    {
                        label: '🔢 算術邏輯力 (V)',
                        data: data.logic,
                        borderColor: '#f093fb',
                        backgroundColor: 'rgba(240, 147, 251, 0.2)',
                        tension: 0.3,
                        fill: false,
                        pointRadius: 5
                    }
                ]
            };

            customVChart = new Chart(ctx, {
                type: 'line', 
                data: chartData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            grid: { color: 'rgba(0,0,0,0.1)' },
                            title: {
                                display: true,
                                text: '自訂分數 V',
                                font: { size: 12, weight: 'bold' }
                            },
                            min: 0, /* 確保 Y 軸從 0 開始 */
                            max: 100 /* 確保 Y 軸最大值為 100 */
                        },
                        x: {
                            grid: { display: false },
                            title: {
                                display: true,
                                text: '週區間',
                                font: { size: 12, weight: 'bold' }
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: (context) => {
                                    const value = context.parsed.y;
                                    return `${context.dataset.label}: ${value !== null ? value.toFixed(2) : 'N/A'}`;
                                }
                            }
                        },
                        legend: {
                            display: true, 
                            position: 'top',
                            labels: {
                                boxWidth: 15,
                                padding: 20
                            }
                        }
                    }
                }
            });

            $('loadingMessage').style.display = 'none';
            $('customVChart').style.display = 'block';
            $('errorContainer').style.display = 'none';
        }

        async function loadData() {
            $('loadingMessage').textContent = '正在載入數據...';
            $('loadingMessage').style.display = 'block';
            $('customVChart').style.display = 'none';
            $('errorContainer').style.display = 'none';


            const memberId = $('memberId').value;
            const range = $('range').value;
            
            // 呼叫新的 API 檔案：api/custom_score_data.php
            const apiUrl = `api/custom_score_data.php?range=${range}` + (memberId ? `&member_id=${memberId}` : '');

            try {
                const response = await fetch(apiUrl);
                // 檢查 HTTP 狀態碼
                if (!response.ok) {
                    throw new Error(`HTTP Error: ${response.status}`);
                }
                const data = await response.json();

                if (data.success) {
                    updateTitle(data); 
                    displayCustomVChart(data);
                } else {
                    console.error('API Error:', data.message);
                    $('loadingMessage').style.display = 'none';
                    $('errorContainer').textContent = `錯誤：載入失敗，訊息：${data.message}`;
                    $('errorContainer').style.display = 'block';
                }
            } catch (error) {
                console.error('Fetch Error:', error);
                $('loadingMessage').style.display = 'none';
                $('errorContainer').textContent = '錯誤：無法連接至伺服器或解析數據，請檢查 API 檔案 (api/custom_score_data.php) 是否正確。' + (error.message ? ` 詳細: ${error.message}` : '');
                $('errorContainer').style.display = 'block';
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