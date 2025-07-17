<?php
require_once 'check_login.php';
require_once 'prisoner_db.php';
 
// 獲取難度設定
$difficulties = [];

try {
    // 嘗試查詢專門的犯人遊戲難度設定表
    $stmt = $pdo->query("SELECT * FROM prisoner_game_difficulty_settings WHERE is_active = true");
    $difficulties = $stmt->fetchAll();
} catch (PDOException $e) {
    // 如果表不存在，使用預設設定
    $difficulties = [
        [
            'difficulty_level' => 'easy',
            'hole_count' => 3,
            'time_limit' => 60,
            'sequence_length' => 3,
            'pass_score' => 50,
            'is_active' => true
        ],
        [
            'difficulty_level' => 'normal',
            'hole_count' => 5,
            'time_limit' => 90,
            'sequence_length' => 5,
            'pass_score' => 100,
            'is_active' => true
        ],
        [
            'difficulty_level' => 'hard',
            'hole_count' => 7,
            'time_limit' => 120,
            'sequence_length' => 7,
            'pass_score' => 150,
            'is_active' => true
        ]
    ];
}

?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>追蹤犯人遊戲</title>
  <link rel="stylesheet" href="css/prisoner.css">
</head>
<body>
  <div class="game-container">
    <input type="hidden" id="member-id" value="8">
    <h1>追蹤犯人遊戲</h1>

    <div class="score-board">
        <h2>目前分數: <span id="score">0</span></h2>
        <h2>最高分數: <span id="high-score">0</span></h2>
        <h2>剩餘時間: <span id="timer">60</span> 秒</h2>
    </div>

    <!-- 難度選擇 Modal -->
    <div id="difficulty-modal" class="modal">
        <div class="modal-content" style="position:relative;">
            <div style="position:absolute; top:1.2rem; right:1.2rem; text-align:center; z-index:10;">
                <button class="help-btn" id="info-btn" title="說明">?</button>
                <div class="help-label">說明</div>
            </div>
            <h2>難度選擇</h2>
            <div class="difficulty-option easy" data-level="3">簡單 (3個)</div>
            <div class="difficulty-option medium" data-level="5">普通 (5個)</div>
            <div class="difficulty-option hard" data-level="7">困難 (7個)</div>
        </div>
    </div>

    <!-- 🟡 新增：遊戲說明彈窗 -->
    <div id="info-modal" class="modal" style="display:none;">
      <div class="modal-content">
        <h2 style="text-align:center;">
            <span style="font-size:2rem;vertical-align:middle;">🎮</span>
            <span style="font-weight:bold;vertical-align:middle;">遊戲說明</span>
        </h2>
        <div class="help-content" style="margin-top:1.5rem;">
            <div style="display:flex;align-items:center;margin-bottom:0.5rem;">
                <span style="color:#3b82f6;font-size:1.2rem;margin-right:0.5rem;">◆</span>
                <span style="font-weight:bold;font-size:1.1rem;">目標</span>
            </div>
            <div style="margin-left:2.2rem;margin-bottom:1.2rem;">
                時間內記住犯人出現的順序，按順序點擊洞，累積足夠分數就過關！
            </div>
            <div style="display:flex;align-items:center;margin-bottom:0.5rem;">
                <span style="color:#3b82f6;font-size:1.2rem;margin-right:0.5rem;">◆</span>
                <span style="font-weight:bold;font-size:1.1rem;">玩法</span>
            </div>
            <ul style="margin-left:2.2rem;">
                <li>選擇難度後開始遊戲</li>
                <li>記住犯人出現的順序</li>
                <li>按順序點擊洞，答對 +2 分</li>
                <li>時間內累積足夠分數過關！</li>
            </ul>
        </div>
        <span class="close-btn" onclick="closeInfoModal()" style="position:absolute; top:10px; right:15px; cursor:pointer; font-size: 30px;">×</span>
      </div>
    </div>

    <div id="result-modal" class="modal" style="display: none;">
      <div class="modal-content">
        <h2 id="result-title"></h2>
        <p id="result-difficulty"></p>
        <p id="result-score"></p>
        <div>
          <button onclick="location.reload()">再玩一次</button>
          <button onclick="window.location.href='index.php'">返回主頁</button>
        </div>
      </div>
    </div>


    <button id="start-btn" style="display: none;">開始遊戲</button>

    <div class="holes">
      <div class="hole" id="hole1">
        <img src="img/prisoner.png" class="mole" /> <!-- 這是地鼠 -->
      </div>
      <div class="hole" id="hole2">
        <img src="img/prisoner.png" class="mole" /> <!-- 這是地鼠 -->
      </div>
      <div class="hole" id="hole3">
        <img src="img/prisoner.png" class="mole" /> <!-- 這是地鼠 -->
      </div>
      <div class="hole" id="hole4">
        <img src="img/prisoner.png" class="mole" /> <!-- 這是地鼠 -->
      </div>
      <div class="hole" id="hole5">
        <img src="img/prisoner.png" class="mole" /> <!-- 這是地鼠 -->
      </div>
      <div class="hole" id="hole6">
        <img src="img/prisoner.png" class="mole" /> <!-- 這是地鼠 -->
      </div>
      <div class="hole" id="hole7">
        <img src="img/prisoner.png" class="mole" /> <!-- 這是地鼠 -->
      </div>
      <div class="hole" id="hole8">
        <img src="img/prisoner.png" class="mole" /> <!-- 這是地鼠 -->
      </div>
      <div class="hole" id="hole9">
        <img src="img/prisoner.png" class="mole" /> <!-- 這是地鼠 -->
      </div>
    </div>

    <!-- ★★★ 這一行是新增的提示文字 ★★★ -->
    <div id="message" style="font-size: 24px; font-weight: bold; color: red; margin-top: 20px;"></div>

    <div class="button-group">
        <button class="control-btn" id="pause-btn">暫停遊戲</button>
        <button class="control-btn" id="end-btn">結束遊戲</button>
        <button class="control-btn" id="restart-btn">重新開始</button>
    </div>
  </div>

  <script src="js/prisoner.js"></script>
  <script src="js/auto-save-time.js"></script>
</body>
</html>
