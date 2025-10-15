<?php
// baseline_time_management.php - 基準時間管理介面
session_start();
require_once '../db.php';
require_once 'weighted_scoring_system.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$admin_name = $_SESSION['admin_name'] ?? '管理員';
$weighted_scoring = new WeightedScoringSystem($pdo);

// 處理表單提交
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'update_baseline':
                    $game_type = $_POST['game_type'];
                    $baseline_time = floatval($_POST['baseline_time']);
                    $stage = $_POST['stage'];
                    
                    if ($weighted_scoring->updateBaselineTime($game_type, $baseline_time, $stage)) {
                        $message = "成功更新 {$game_type} 的基準時間為 {$baseline_time} 秒";
                        $message_type = 'success';
                    } else {
                        $message = "更新基準時間失敗";
                        $message_type = 'error';
                    }
                    break;
                    
                    
            }
        }
    } catch (Exception $e) {
        $message = "操作失敗: " . $e->getMessage();
        $message_type = 'error';
    }
}

// 獲取所有基準時間設定
$baseline_times = $weighted_scoring->getAllBaselineTimes();

// 獲取遊戲統計資訊
$game_stats = [];
foreach ($baseline_times as $game_type => $data) {
    $stats = $weighted_scoring->getGameStatistics($game_type, 30);
    $game_stats[$game_type] = $stats;
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>基準時間管理 - 後台管理系統</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1400px; margin: 0 auto; }
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
        
        .card { background: white; padding: 20px; margin-bottom: 20px; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .card h3 { margin-top: 0; color: #444; }
        
        .message { 
            padding: 12px; 
            margin-bottom: 20px; 
            border-radius: 5px; 
            font-weight: bold;
        }
        .message.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        .table-container { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f8f9fa; font-weight: bold; }
        tr:hover { background-color: #f5f5f5; }
        
        .form-inline { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .form-inline input, .form-inline select { padding: 8px; border: 1px solid #ddd; border-radius: 3px; }
        .form-inline button { 
            padding: 8px 15px; 
            background: #007bff; 
            color: white; 
            border: none; 
            border-radius: 3px; 
            cursor: pointer; 
        }
        .form-inline button:hover { background: #0056b3; }
        
        .btn { 
            padding: 6px 12px; 
            border: none; 
            border-radius: 3px; 
            cursor: pointer; 
            text-decoration: none;
            display: inline-block;
            font-size: 12px;
        }
        .btn-primary { background: #007bff; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn-danger { background: #dc3545; color: white; }
        .btn:hover { opacity: 0.8; }
        
        .stage-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
        }
        .stage-manual { background: #ffc107; color: #212529; }
        .stage-historical { background: #17a2b8; color: white; }
        .stage-mature { background: #28a745; color: white; }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        .stat-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #007bff;
        }
        .stat-card h4 { margin: 0 0 10px 0; font-size: 14px; color: #666; }
        .stat-card .value { font-size: 24px; font-weight: bold; color: #333; }
        
        .modal { 
            display: none; 
            position: fixed; 
            z-index: 1000; 
            left: 0; 
            top: 0; 
            width: 100%; 
            height: 100%; 
            background-color: rgba(0,0,0,0.5); 
        }
        .modal-content { 
            background-color: white; 
            margin: 15% auto; 
            padding: 20px; 
            border-radius: 5px; 
            width: 400px; 
        }
        .close { 
            color: #aaa; 
            float: right; 
            font-size: 28px; 
            font-weight: bold; 
            cursor: pointer; 
        }
        .close:hover { color: black; }
        
        .chart-container {
            position: relative;
            height: 400px;
            width: 100%;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎯 動態基準時間管理系統</h1>
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
            <a href="custom_score_trend.php">📊 趨勢分析</a>
            <a href="baseline_time_management.php" class="active">📊 雷達圖分析</a>
        </div>

        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>


        <!-- 雷達圖預覽 -->
        <div class="card">
            <h3>📊 能力雷達圖預覽</h3>
            
            <!-- 公式說明框 -->
            <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px; border: 1px solid #dee2e6;">
                <div style="display: flex; align-items: center; margin-bottom: 10px;">
                    <span style="color: #007bff; margin-right: 8px;">📈</span>
                    <h4 style="margin: 0; color: #495057; font-weight: bold;">加權分數公式說明</h4>
                </div>
                <div style="font-size: 14px; color: #495057;">
                    <div style="margin-bottom: 8px;">
                        <strong>公式：</strong>
                        <span style="background: #e9ecef; padding: 2px 6px; border-radius: 3px; font-family: monospace;">
                            最終分數 = 基礎分 × 時間加權係數 × 難度係數
                        </span>
                    </div>
                    <div>
                        <strong>時間加權係數：</strong>
                        <div style="margin-top: 5px; margin-left: 15px;">
                            <div style="margin-bottom: 3px;">
                                <span style="background: #e9ecef; padding: 2px 6px; border-radius: 3px; font-family: monospace;">
                                    時間加權係數 = 1 + (基準時間 - 實際時間) / 基準時間
                                </span>
                            </div>
                            <div style="font-size: 12px; color: #6c757d;">
                                • 範圍限制：0.2 ≤ 時間加權係數 ≤ 2.0<br>
                                • 難度係數：簡單(0.5x) | 普通(1.0x) | 困難(1.5x)
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 篩選控制 -->
            <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                <div style="display: flex; gap: 20px; align-items: center; flex-wrap: wrap;">
                    <div>
                        <label style="font-weight: bold; margin-right: 8px;">使用者：</label>
                        <select id="userSelect" style="padding: 5px 10px; border: 1px solid #ddd; border-radius: 3px;">
                            <option value="all" selected>全部用戶</option>
                            <?php
                            // 參考能力分析頁面的做法，預載部分用戶供下拉選單（最多200）
                            $users = [];
                            try {
                                $stmt = $pdo->query("SELECT member_id, member_name AS display_name FROM member ORDER BY member_id DESC LIMIT 200");
                                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            } catch (Exception $e) {
                                $users = [];
                            }
                            
                            if (empty($users)) {
                                echo "<option value=\"no_users\">暫無用戶資料</option>";
                            } else {
                                foreach ($users as $u) {
                                    echo "<option value=\"{$u['member_id']}\">" . htmlspecialchars($u['display_name'] . ' (#' . $u['member_id'] . ')') . "</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div>
                        <label style="font-weight: bold; margin-right: 8px;">時間範圍：</label>
                        <select id="timeRange" style="padding: 5px 10px; border: 1px solid #ddd; border-radius: 3px;">
                            <option value="7">近7天</option>
                            <option value="30" selected>近30天</option>
                            <option value="90">近90天</option>
                            <option value="365">近1年</option>
                            <option value="all">全部時間</option>
                        </select>
                    </div>
                    
                    <button onclick="updateRadarChart()" style="padding: 8px 15px; background: #007bff; color: white; border: none; border-radius: 3px; cursor: pointer;">
                        套用
                    </button>
                </div>
            </div>
            
            <div class="chart-container" style="height: 400px;">
                <canvas id="previewRadarChart"></canvas>
            </div>
            
            <div style="margin-top: 15px; padding: 10px; background: #e9ecef; border-radius: 5px; font-size: 0.9em;">
                <span>
                    💡 <strong>圖表說明:</strong> 
                        <span id="chartDescription">顯示全部用戶近30天的最終分數</span>
                </span>
            </div>
        </div>
    </div>



    <script>
        let previewRadarChart = null;
        
        
        // 全域變數存儲原始數據
        let allGameRecordsData = <?php 
            // 從 game_records 表獲取所有數據，包含 member_id
            try {
                $stmt = $pdo->query("
                    SELECT 
                        member_id,
                        game_type,
                        AVG(play_time) as avg_time,
                        COUNT(*) as play_count,
                        AVG(score) as avg_score,
                        MIN(play_date) as first_play,
                        MAX(play_date) as last_play
                    FROM game_records 
                    WHERE play_time > 0 
                        AND play_time < 1800 
                        AND status = 'completed'
                    GROUP BY member_id, game_type
                    ORDER BY play_count DESC
                ");
                $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode($records, JSON_UNESCAPED_UNICODE);
            } catch (Exception $e) {
                echo json_encode([]);
            }
        ?>;
        
        // 用戶資料（參考能力分析頁面的做法）
        let userData = <?php
            try {
                $stmt = $pdo->query("SELECT member_id, member_name AS display_name FROM member ORDER BY member_id DESC LIMIT 200");
                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode($users, JSON_UNESCAPED_UNICODE);
            } catch (Exception $e) {
                echo json_encode([]);
            }
        ?>;
        
        // 初始化雷達圖
        function initRadarChart() {
            updateRadarChart();
        }
        
        // 更新雷達圖（支援篩選）
        function updateRadarChart() {
            const canvas = document.getElementById('previewRadarChart');
            if (!canvas) {
                console.error('找不到雷達圖 canvas 元素');
                return;
            }
            
            const ctx = canvas.getContext('2d');
            
            if (previewRadarChart) {
                previewRadarChart.destroy();
            }
            
            // 獲取篩選條件
            const userSelect = document.getElementById('userSelect').value;
            const timeRange = document.getElementById('timeRange').value;
            
            console.log('篩選條件:', { userSelect, timeRange });
            
            // 簡化版本：直接使用原始數據進行分析
            let filteredData = allGameRecordsData;
            
            // 先篩選用戶
            if (userSelect !== 'all') {
                filteredData = filteredData.filter(record => record.member_id == userSelect);
            }
            
            // 再篩選時間範圍
            if (timeRange !== 'all') {
                const days = parseInt(timeRange);
                const cutoffDate = new Date();
                cutoffDate.setDate(cutoffDate.getDate() - days);
                
                filteredData = filteredData.filter(record => {
                    const lastPlay = new Date(record.last_play);
                    return lastPlay >= cutoffDate;
                });
            }
            
            // 直接使用篩選後的數據，不再聚合
            let gameRecordsData = filteredData;
            
            console.log('遊戲記錄數據:', gameRecordsData);
            console.log('原始數據數量:', allGameRecordsData.length);
            console.log('篩選後數據數量:', gameRecordsData.length);
            
            // 調試：檢查遊戲類型分佈
            const gameTypeCount = {};
            gameRecordsData.forEach(record => {
                gameTypeCount[record.game_type] = (gameTypeCount[record.game_type] || 0) + 1;
            });
            console.log('遊戲類型分佈:', gameTypeCount);
            
            // 分類遊戲並計算各能力的平均表現
            let reactionGames = [];
            let memoryGames = [];
            let logicGames = [];
            
            
            // 使用新的最終分數公式計算各能力分數
            // 最終分數 = ((基礎分 × 時間加權係數 × 難度係數(E))/該難度次數(E)+(基礎分 × 時間加權係數 × 難度係數(N))/該難度次數(N)+(基礎分 × 時間加權係數 × 難度係數(H))/該難度次數(H))/3
            
            // 重新計算各能力的指數，按難度分組
            let reactionData = { 
                easy: { totalScore: 0, totalPlays: 0 },    // E = 簡單 (0.1)
                normal: { totalScore: 0, totalPlays: 0 },  // N = 普通 (0.2)
                hard: { totalScore: 0, totalPlays: 0 }     // H = 困難 (1.0)
            };
            let memoryData = { 
                easy: { totalScore: 0, totalPlays: 0 },
                normal: { totalScore: 0, totalPlays: 0 },
                hard: { totalScore: 0, totalPlays: 0 }
            };
            let logicData = { 
                easy: { totalScore: 0, totalPlays: 0 },
                normal: { totalScore: 0, totalPlays: 0 },
                hard: { totalScore: 0, totalPlays: 0 }
            };
            
            gameRecordsData.forEach(record => {
                const gameType = record.game_type;
                const avgTime = parseFloat(record.avg_time);
                const avgScore = parseFloat(record.avg_score);
                const playCount = parseInt(record.play_count);
                
                // 使用真正的加權分數計算
                const baseScore = Math.max(20, avgScore);
                
                let baselineTime = 60;
                const baselineData = <?php echo json_encode($baseline_times, JSON_UNESCAPED_UNICODE); ?>;
                if (baselineData[gameType]) {
                    baselineTime = baselineData[gameType].baseline_time;
                }
                
                // 公式：1 + (基準時間 - 實際時間) / 基準時間
                let timeWeight = 1 + ((baselineTime - avgTime) / baselineTime);
                
                // 限制時間加權係數範圍 (0.2 到 2.0)
                timeWeight = Math.max(0.2, Math.min(2.0, timeWeight));
                
                // 判斷難度等級
                let difficultyLevel = 'normal';
                let difficultyMultiplier = 1.0; 
                
                if (gameType.includes('困難') || gameType.includes('hard')) {
                    difficultyLevel = 'hard';
                    difficultyMultiplier = 1.5; 
                } else if (gameType.includes('簡單') || gameType.includes('easy')) {
                    difficultyLevel = 'easy';
                    difficultyMultiplier = 0.5; 
                }
                
                // 計算該遊戲的加權分數 (已移除準確率)
                const weightedScore = baseScore * timeWeight * difficultyMultiplier;
                
                // 按能力類型和難度等級分類並累計
                let abilityType = 'unknown';
                if (gameType.includes('反應') || gameType.includes('接金蛋') || gameType.includes('節奏') || gameType.includes('看字選色')) {
                    abilityType = 'reaction';
                    reactionData[difficultyLevel].totalScore += weightedScore * playCount;
                    reactionData[difficultyLevel].totalPlays += playCount;
                } else if (gameType.includes('記憶') || gameType.includes('翻牌') || gameType.includes('線索') || gameType.includes('追蹤')) {
                    abilityType = 'memory';
                    memoryData[difficultyLevel].totalScore += weightedScore * playCount;
                    memoryData[difficultyLevel].totalPlays += playCount;
                } else if (gameType.includes('算') || gameType.includes('邏輯') || gameType.includes('2048') || gameType.includes('過河')) {
                    abilityType = 'logic';
                    logicData[difficultyLevel].totalScore += weightedScore * playCount;
                    logicData[difficultyLevel].totalPlays += playCount;
                }
                
                console.log(`遊戲分類: ${gameType} -> ${abilityType}, 難度: ${difficultyLevel}, 基準: ${baselineTime}s, 實際: ${avgTime}s, 時間加權: ${timeWeight.toFixed(3)}, 加權分數: ${weightedScore.toFixed(2)}, 次數: ${playCount}`);
            });
            
            // 計算各能力的最終分數 (移除門檻限制)
            function calculateFinalScore(abilityData) {
                let sumOfDifficultyAverages = 0;
                let validDifficulties = 0; 
                let totalPlays = 0;       
                
                // 遍歷三個難度：簡單 (E)、普通 (N)、困難 (H)
                ['easy', 'normal', 'hard'].forEach(difficulty => {
                    const totalScore = abilityData[difficulty].totalScore;
                    const plays = abilityData[difficulty].totalPlays;
                    
                    totalPlays += plays; // 累計總次數
                    
                    // 如果該難度有遊玩次數 (plays > 0)
                    if (plays > 0) {
                        const averageScore = totalScore / plays;
                        sumOfDifficultyAverages += averageScore;
                        validDifficulties++; // 累計有數據的難度數量
                    } 
                });
                
                // 最終分數邏輯：直接計算，無門檻限制
                const finalScore = validDifficulties > 0 ? sumOfDifficultyAverages / validDifficulties : 0;
                
                // 將結果限制在 0-100 範圍內
                return { 
                    score: Math.max(0, Math.min(100, finalScore)), 
                    reason: 'ok', 
                    plays: totalPlays 
                };
            }
            
            // ============== 在這裡開始新的最終分數計算邏輯 ==============
            
            // 計算各能力的最終分數 (無門檻限制)
            const reactionResult = calculateFinalScore(reactionData);
            const memoryResult = calculateFinalScore(memoryData);
            const logicResult = calculateFinalScore(logicData);
            
            const reactionScore = reactionResult.score;
            const memoryScore = memoryResult.score;
            const logicScore = logicResult.score;
            
            console.log('最終分數計算結果:');
            
            function logAbilityDetails(abilityName, abilityData, result) {
                console.log(`${abilityName}:`);
                ['easy', 'normal', 'hard'].forEach(difficulty => {
                    const data = abilityData[difficulty];
                    if (data.totalPlays > 0) {
                        const average = data.totalScore / data.totalPlays;
                        console.log(`  ${difficulty}: 總分=${data.totalScore.toFixed(1)}, 次數=${data.totalPlays}, 平均=${average.toFixed(1)}`);
                    }
                });
                
                console.log(`  最終分數: ${result.score.toFixed(1)} (次數: ${result.plays})`);
            }
            
            logAbilityDetails('反應力', reactionData, reactionResult);
            logAbilityDetails('記憶力', memoryData, memoryResult);
            logAbilityDetails('邏輯力', logicData, logicResult);
            
            // 更新圖表說明
            updateChartDescription(userSelect, timeRange);
            
            const chartData = {
                labels: ['反應力', '記憶力', '邏輯思維'],
                datasets: [{
                    label: '最終分數',
                    data: [
                        Math.round(reactionScore),
                        Math.round(memoryScore), 
                        Math.round(logicScore)
                    ],
                    backgroundColor: 'rgba(102, 126, 234, 0.3)',
                    borderColor: 'rgba(102, 126, 234, 1)',
                    pointBackgroundColor: 'rgba(102, 126, 234, 1)',
                    pointBorderColor: '#fff',
                    pointHoverBackgroundColor: '#fff',
                    pointHoverBorderColor: 'rgba(102, 126, 234, 1)',
                    pointRadius: 8,
                    pointHoverRadius: 10,
                    borderWidth: 3
                }]
            };
            
            previewRadarChart = new Chart(ctx, {
                type: 'radar',
                data: chartData,
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
                                    const ability = ['反應力', '記憶力', '邏輯思維'][context.dataIndex];
                                    
                                    return `${label}: ${value} (${ability})`;
                                }
                            }
                        }
                    }
                }
            });
        }
        
        // 更新圖表說明
        function updateChartDescription(userSelect, timeRange) {
            let userText = '全部用戶';
            
            if (userSelect !== 'all') {
                const selectedUser = userData.find(user => user.member_id == userSelect);
                if (selectedUser) {
                    userText = selectedUser.display_name;
                }
            }
            
            const timeText = {
                '7': '近7天',
                '30': '近30天',
                '90': '近90天',
                '365': '近1年',
                'all': '全部時間'
            };
            
            const description = `顯示${userText}${timeText[timeRange]}的最終分數`;
            
            document.getElementById('chartDescription').textContent = description;
        }
        
        // 頁面載入時初始化雷達圖
        document.addEventListener('DOMContentLoaded', function() {
            console.log('頁面載入完成，開始初始化雷達圖');
            setTimeout(initRadarChart, 1000); // 延遲1秒確保所有元素都載入完成
        });
    </script>
</body>
</html>
