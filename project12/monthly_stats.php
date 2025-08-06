<?php
require_once 'check_login.php';
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>本月遊玩統計</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
            line-height: 1.6;
        }
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .stat-card {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
        }
        .stat-number {
            font-size: 2.5em;
            font-weight: bold;
            color: #007bff;
            margin: 10px 0;
        }
        .stat-label {
            color: #6c757d;
            font-size: 1.1em;
        }
        .records-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .records-table th,
        .records-table td {
            border: 1px solid #dee2e6;
            padding: 12px;
            text-align: center;
        }
        .records-table th {
            background-color: #007bff;
            color: white;
        }
        .records-table tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        .loading {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }
        .error {
            color: #dc3545;
            text-align: center;
            padding: 20px;
        }
        .back-button {
            background-color: #6c757d;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin: 20px 0;
        }
        .back-button:hover {
            background-color: #5a6268;
        }
    </style>
</head>
<body>
    <h1>📊 本月遊玩統計</h1>
    
    <a href="history.php" class="back-button">← 返回歷史紀錄</a>
    
    <div id="loading" class="loading">
        <h2>載入中...</h2>
        <p>正在獲取本月遊玩數據</p>
    </div>
    
    <div id="stats" style="display: none;">
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-label">本月遊玩次數</div>
                <div class="stat-number" id="playCount">0</div>
                <div class="stat-label">次</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">本月總遊玩時間</div>
                <div class="stat-number" id="totalTime">00:00</div>
                <div class="stat-label">小時:分鐘</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">本月遊玩天數</div>
                <div class="stat-number" id="playDays">0</div>
                <div class="stat-label">天</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">平均每日遊玩時間</div>
                <div class="stat-number" id="avgTime">00:00</div>
                <div class="stat-label">小時:分鐘</div>
            </div>
        </div>
        
        <h2>📅 本月每日遊玩記錄</h2>
        
        <!-- 類別篩選下拉選單 -->
        <div style="text-align: center; margin-bottom: 20px;">
            <label for="categoryFilter" style="font-weight: bold; color: #333; margin-right: 10px;">選擇類別：</label>
            <select id="categoryFilter" style="padding: 8px 12px; border: 2px solid #007bff; border-radius: 5px; font-size: 14px; background: white; cursor: pointer;" onchange="loadMonthlyStats()">
                <option value="all">全部類別</option>
                <option value="reaction">反應力</option>
                <option value="memory">記憶力</option>
                <option value="logic">邏輯力</option>
            </select>
        </div>
        
        <div id="recordsTable">
            <table class="records-table">
                <thead>
                    <tr>
                        <th>日期</th>
                        <th>遊玩時間</th>
                        <th>遊戲次數</th>
                        <th>遊戲</th>
                    </tr>
                </thead>
                <tbody id="recordsBody">
                    <!-- 動態載入 -->
                </tbody>
            </table>
        </div>
    </div>
    
    <div id="error" class="error" style="display: none;">
        <h2>❌ 載入失敗</h2>
        <p id="errorMessage">無法獲取本月遊玩數據</p>
    </div>
    
    <script>
        // 載入本月統計數據
        function loadMonthlyStats() {
            // 獲取選擇的類別
            const categoryFilter = document.getElementById('categoryFilter');
            const selectedCategory = categoryFilter ? categoryFilter.value : 'all';
            
            // 構建請求URL，包含類別參數
            const url = `get_monthly_stats.php?category=${selectedCategory}`;
            
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('loading').style.display = 'none';
                    
                    if (data.success) {
                        // 顯示統計數據
                        document.getElementById('stats').style.display = 'block';
                        document.getElementById('playCount').textContent = data.play_count;
                        document.getElementById('totalTime').textContent = data.total_playtime;
                        document.getElementById('playDays').textContent = data.daily_records.length;
                        
                        // 計算平均時間
                        if (data.daily_records.length > 0) {
                            const avgSeconds = Math.floor(data.total_seconds / data.daily_records.length);
                            const avgHours = Math.floor(avgSeconds / 3600);
                            const avgMinutes = Math.floor((avgSeconds % 3600) / 60);
                            document.getElementById('avgTime').textContent = 
                                `${String(avgHours).padStart(2, '0')}:${String(avgMinutes).padStart(2, '0')}`;
                        }
                        
                        // 顯示每日記錄
                        const recordsBody = document.getElementById('recordsBody');
                        recordsBody.innerHTML = '';
                        
                        if (data.daily_records.length > 0) {
                            data.daily_records.forEach(record => {
                                const row = document.createElement('tr');
                                
                                // 處理遊戲類型顯示
                                let gamesDisplay = '❌ 無遊玩';
                                if (record.seconds > 0) {
                                  // 獲取選擇的類別
                                  const categoryFilter = document.getElementById('categoryFilter');
                                  const selectedCategory = categoryFilter ? categoryFilter.value : 'all';
                                  
                                  if (selectedCategory === 'all') {
                                    // 全部類別：顯示遊戲類型（記憶力、反應力等）
                                    if (record.game_types && record.game_types.length > 0) {
                                      gamesDisplay = record.game_types.map(gameType => {
                                        const gameIcons = {
                                          '記憶力': '🧠',
                                          '反應力': '⚡',
                                          '邏輯力': '🧩',
                                          '算術邏輯': '🧮'
                                        };
                                        const icon = gameIcons[gameType] || '🎮';
                                        return `${icon} ${gameType}`;
                                      }).join('<br>');
                                    }
                                  } else {
                                    // 特定類別：顯示具體遊戲名稱
                                    if (record.game_names && record.game_names.length > 0) {
                                      gamesDisplay = record.game_names.map(gameName => {
                                        const gameIcons = {
                                          '看字選色遊戲': '🎨',
                                          '接金蛋': '🥚',
                                          '算菜錢': '🧮',
                                          '2048': '🔢',
                                          '翻牌對對樂': '🃏',
                                          '記憶力': '🧠',
                                          '節奏遊戲': '🎵',
                                          '追蹤犯人遊戲': '🔍',
                                          '圖片線索問答': '❓',
                                          '算菜錢遊戲': '🥬',
                                          '邏輯力': '🧩'
                                        };
                                        const icon = gameIcons[gameName] || '🎮';
                                        return `${icon} ${gameName}`;
                                      }).join('<br>');
                                    }
                                  }
                                }
                                
                                row.innerHTML = `
                                    <td>${record.date}</td>
                                    <td>${record.playtime}</td>
                                    <td>${record.game_count || 0}</td>
                                    <td>${gamesDisplay}</td>
                                `;
                                recordsBody.appendChild(row);
                            });
                        } else {
                            recordsBody.innerHTML = '<tr><td colspan="4">本月尚無遊玩記錄</td></tr>';
                        }
                    } else {
                        // 顯示錯誤
                        document.getElementById('error').style.display = 'block';
                        document.getElementById('errorMessage').textContent = data.message || '載入失敗';
                    }
                })
                .catch(error => {
                    document.getElementById('loading').style.display = 'none';
                    document.getElementById('error').style.display = 'block';
                    document.getElementById('errorMessage').textContent = '網路錯誤：' + error.message;
                });
        }
        
        // 頁面載入時執行
        document.addEventListener('DOMContentLoaded', loadMonthlyStats);
    </script>
</body>
</html> 