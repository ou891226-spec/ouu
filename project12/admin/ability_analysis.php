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
    <title>能力分析 - 後台管理系統</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; }
        .header { background: white; padding: 20px; margin-bottom: 20px; border-radius: 5px; }
        .nav { background: white; padding: 15px; margin-bottom: 20px; border-radius: 5px; }
        .nav a { margin-right: 20px; text-decoration: none; color: #007bff; }
        .nav a:hover { text-decoration: underline; }
        .filters { background: white; padding: 20px; margin-bottom: 20px; border-radius: 5px; }
        .filters form { display: flex; gap: 15px; align-items: end; flex-wrap: wrap; }
        .filters select, .filters input { padding: 8px; border: 1px solid #ddd; border-radius: 3px; }
        .filters button { padding: 8px 15px; background: #007bff; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 14px; min-width: 80px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; }
        .card { background: white; padding: 20px; border-radius: 5px; }
        .summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin-bottom: 20px; }
        .summary-item { background: white; padding: 16px; border-radius: 6px; text-align: center; }
        .delta-up { color: #28a745; }
        .delta-down { color: #dc3545; }
        .logout { float: right; background: #dc3545; color: white; padding: 8px 16px; text-decoration: none; border-radius: 5px; font-weight: bold; transition: background-color 0.3s ease; margin-top: -85px; }
        .logout:hover { background: #c82333; text-decoration: none; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>能力分析</h1>
            <p>歡迎，<?php echo htmlspecialchars($admin_name); ?></p>
            <a href="logout.php" class="logout">登出</a>
        </div>

        <div class="nav">
            <a href="game_records.php">📊 遊戲紀錄</a>
            <a href="user_behavior.php">👤 行為軌跡</a>
            <a href="question_management.php">🎯 遊戲管理</a>
            <a href="user_management.php">👥 用戶管理</a>
            <a href="ability_analysis.php" class="active">🧠 能力分析</a>
            <a href="test_results.php">📈 測試結果</a>
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

        <div class="summary" id="summary">
            <div class="summary-item">
                <div>反應力平均強度(%) <span style="color:#888; cursor:help;" title="基於第95百分位數計算的強度百分比。100%代表頂尖5%水準，更抗極端值影響。">ⓘ</span></div>
                <h3 id="avgReaction">-</h3>
                <div id="deltaReaction"></div>
            </div>
            <div class="summary-item">
                <div>記憶力平均強度(%) <span style="color:#888; cursor:help;" title="基於第95百分位數計算的強度百分比。100%代表頂尖5%水準，更抗極端值影響。">ⓘ</span></div>
                <h3 id="avgMemory">-</h3>
                <div id="deltaMemory"></div>
            </div>
            <div class="summary-item">
                <div>算術邏輯力平均強度(%) <span style="color:#888; cursor:help;" title="基於第95百分位數計算的強度百分比。100%代表頂尖5%水準，更抗極端值影響。">ⓘ</span></div>
                <h3 id="avgLogic">-</h3>
                <div id="deltaLogic"></div>
            </div>
        </div>

        <div class="grid">
            <div class="card">
                <h3>能力雷達圖</h3>
                <canvas id="radarChart" height="280"></canvas>
            </div>
            <div class="card">
                <h3>能力趨勢</h3>
                <canvas id="trendChart" height="280"></canvas>
            </div>
        </div>

    </div>

    <script>
        const $ = (id) => document.getElementById(id);
        let radar, trend;

        async function loadData() {
            const memberId = $('memberId').value;
            const range = $('range').value;
            const qs = new URLSearchParams();
            if (memberId) qs.set('member_id', memberId);
            if (range) qs.set('range', range);
            
            console.log('Loading data with params:', qs.toString());
            const res = await fetch('api/ability_stats.php?' + qs.toString());
            const data = await res.json();
            console.log('API Response:', data);
            
            if (!data.success) { 
                console.error('API Error:', data.message);
                alert(data.message || '載入失敗'); 
                return; 
            }

            // Summary: 以百分比強度（與趨勢一致）
            const ts = data.trend_strength || null;
            const getAvg = (arr) => {
                if (!arr) return 0;
                let s = 0, c = 0; arr.forEach(v => { if (v !== null && v !== undefined) { s += Number(v); c++; }});
                return c ? (s / c) : 0;
            };
            const getDelta = (arr) => {
                if (!arr) return null;
                let first = null, last = null;
                for (let i = 0; i < arr.length; i++) { if (arr[i] !== null && arr[i] !== undefined) { first = Number(arr[i]); break; } }
                for (let i = arr.length - 1; i >= 0; i--) { if (arr[i] !== null && arr[i] !== undefined) { last = Number(arr[i]); break; } }
                if (first === null || last === null) return null;
                return last - first;
            };

            const reactionAvgPct = getAvg(ts ? ts.reaction : null);
            const memoryAvgPct   = getAvg(ts ? ts.memory   : null);
            const logicAvgPct    = getAvg(ts ? ts.logic    : null);
            $('avgReaction').textContent = `${reactionAvgPct.toFixed(2)}%`;
            $('avgMemory').textContent   = `${memoryAvgPct.toFixed(2)}%`;
            $('avgLogic').textContent    = `${logicAvgPct.toFixed(2)}%`;

            const setDeltaPct = (el, series) => {
                const d = getDelta(series);
                if (d === null) { el.className = ''; el.textContent = '-'; return; }
                const up = d >= 0; el.className = up ? 'delta-up' : 'delta-down';
                el.textContent = `${up ? '▲' : '▼'} ${Math.abs(d).toFixed(2)}%（區間變化）`;
            };
            setDeltaPct($('deltaReaction'), ts ? ts.reaction : null);
            setDeltaPct($('deltaMemory'),   ts ? ts.memory   : null);
            setDeltaPct($('deltaLogic'),    ts ? ts.logic    : null);

            // Radar: 以百分比強度 (0-100) 顯示，與前台一致
            const radarValues = [
                (data.strength && data.strength.reaction) || 0,
                (data.strength && data.strength.memory) || 0,
                (data.strength && data.strength.logic) || 0
            ];
            
            console.log('Radar Values:', radarValues);
            console.log('Strength Data:', data.strength);
            
            const radarData = {
                labels: ['反應力', '記憶力', '算術邏輯力'],
                datasets: [{
                    label: '能力強度(%)',
                    data: radarValues,
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.2)'
                }]
            };
            if (radar) radar.destroy();
            radar = new Chart($('radarChart'), { 
                type: 'radar', 
                data: radarData, 
                options: { 
                    responsive: true,
                    scales: { 
                        r: { beginAtZero: true, max: 100, ticks: { stepSize: 20 } } 
                    } 
                } 
            });

            // Trend: 預設使用 MA7 強度％（更平滑、可看訓練效果）
            const labels = (data.trend_strength_ma7 && data.trend_strength_ma7.labels) || data.trend.labels;
            const dsReaction = (data.trend_strength_ma7 && data.trend_strength_ma7.reaction) || data.trend.reaction;
            const dsMemory   = (data.trend_strength_ma7 && data.trend_strength_ma7.memory)   || data.trend.memory;
            const dsLogic    = (data.trend_strength_ma7 && data.trend_strength_ma7.logic)    || data.trend.logic;

            if (trend) trend.destroy();
            trend = new Chart($('trendChart'), {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        { label: '反應力(強度%·MA7)', data: dsReaction, borderColor: '#667eea', backgroundColor: 'rgba(102,126,234,0.1)', tension: 0.3, spanGaps: true },
                        { label: '記憶力(強度%·MA7)', data: dsMemory, borderColor: '#43e97b', backgroundColor: 'rgba(67,233,123,0.1)', tension: 0.3, spanGaps: true },
                        { label: '算術邏輯力(強度%·MA7)', data: dsLogic, borderColor: '#f093fb', backgroundColor: 'rgba(240,147,251,0.1)', tension: 0.3, spanGaps: true }
                    ]
                },
                options: { 
                    responsive: true, 
                    scales: { y: { beginAtZero: true, max: 100 } },
                    plugins: { tooltip: { callbacks: { label: (c) => `${c.dataset.label}: ${c.parsed.y ?? '-'}%` } } }
                }
            });

        }

        document.getElementById('filterForm').addEventListener('submit', function(e){
            e.preventDefault();
            loadData();
        });

        document.addEventListener('DOMContentLoaded', loadData);
    </script>
</body>
</html>



