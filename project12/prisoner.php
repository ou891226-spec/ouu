<?php
require_once 'check_login.php';
require_once 'db_connect.php';
 
// 獲取難度設定
$difficulties = [];

try {
    // 使用統一的 difficulty_settings 表查詢犯人遊戲設定 (game_id = 6)
    $stmt = $pdo->query("SELECT * FROM difficulty_settings WHERE game_id = 6 ORDER BY difficulty");
    $db_difficulties = $stmt->fetchAll();
    
    // 轉換為犯人遊戲需要的格式
    $difficulties = [];
    foreach ($db_difficulties as $setting) {
        $difficulties[] = [
            'difficulty_level' => $setting['difficulty'],
            'hole_count' => $setting['difficulty'] === 'easy' ? 3 : ($setting['difficulty'] === 'normal' ? 4 : 5),
            'time_limit' => $setting['time_limit'] ?? 60,
            'sequence_length' => $setting['difficulty'] === 'easy' ? 3 : ($setting['difficulty'] === 'normal' ? 4 : 5),
            'pass_score' => $setting['pass_score'] ?? 20,
            'is_active' => true
        ];
    }
} catch (PDOException $e) {
    // 如果查詢失敗，使用預設設定
    $difficulties = [
        [
            'difficulty_level' => 'easy',
            'hole_count' => 3,
            'time_limit' => 60,
            'sequence_length' => 3,
            'pass_score' => 20,
            'is_active' => true
        ],
        [
            'difficulty_level' => 'normal',
            'hole_count' => 4,
            'time_limit' => 60,
            'sequence_length' => 4,
            'pass_score' => 20,
            'is_active' => true
        ],
        [
            'difficulty_level' => 'hard',
            'hole_count' => 5,
            'time_limit' => 120,
            'sequence_length' => 5,
            'pass_score' => 20,
            'is_active' => true
        ]
    ];
}

// 最高分數由JavaScript的localStorage管理，PHP不再查詢資料庫
$highScore = 0;

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
    <input type="hidden" id="member-id" value="<?php echo $_SESSION['member_id'] ?? 1; ?>">
    <h1>追蹤犯人遊戲</h1>

    <div class="score-board">
        <h2>目前分數: <span id="score">0</span></h2>
        <h2>最高分數: <span id="high-score"><?php echo $highScore; ?></span></h2>
        <h2>剩餘時間: <span id="timer">60</span> 秒</h2>
    </div>

  <div id="difficulty-modal" class="modal">
      <div class="modal-content" style="position:relative;">
          <button class="back-button" id="back-btn" onclick="window.history.back(); return false;" style="position:absolute;top:1.0rem;left:2.2rem;z-index:10;">
            <span class="back-arrow">←</span>
            <div class="back-label">返回</div>
          </button>

          <div style="position:absolute; top:1.2rem; right:1.2rem; text-align:center; z-index:10;">
              <button class="help-btn" id="info-btn" title="說明">?</button>
              <div class="help-label">說明</div>
          </div>

          <h2>選擇難度</h2>
          <div class="difficulty-option easy" data-level="3">簡單 (20分過關)</div>
          <div class="difficulty-option medium" data-level="4">普通 (20分過關)</div>
          <div class="difficulty-option hard" data-level="5">困難 (20分過關)</div>
      </div>
  </div>


    <div id="info-modal" class="modal" style="display:none;">
      <div class="modal-content">
        <span class="close-btn" onclick="closeInfoModal()" style="position:absolute; top:10px; right:15px; cursor:pointer; font-size: 30px;">×</span>
        <h2 style="text-align:center;">
            <span style="font-size:2rem;vertical-align:middle;">🎮</span>
            <span style="font-weight:bold;vertical-align:middle;">遊戲說明</span>
        </h2>
        <div class="help-content" style="margin-top:2.5rem;padding:0 2rem;">
            <div id="prisoner-video-container" style="text-align:center;margin-bottom:2.5rem;">
                <video id="prisoner-current-video" width="100%" height="auto" controls style="max-width:700px;width:80%;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,0.15);">
                    <source src="gd/prisoner1.mp4" type="video/mp4">
                    您的瀏覽器不支援影片播放。
                </video>
            </div>
            
            <div style="display:flex;justify-content:center;align-items:center;margin:0 1rem;margin-bottom:2rem; gap: 20px;">
                <div id="prisoner-prev-step-btn" style="display:none;">
                    <button id="prisoner-prev-step-button" onclick="goToPrisonerPrevStep()" class="game-step-button prev-step" style="padding:14px 28px;font-size:20px;">
                        上一步
                    </button>
                </div>
                
                <div id="prisoner-instruction-text" class="game-instruction-text" style="font-size:24px;flex:3;text-align:center;min-width:300px;">
                    先選擇遊戲的難度，每個難度只要得分超過20分(含)就會過關。選擇難度後遊戲會開始，畫面上有九個洞，洞會輪流出現犯人，請玩家記住犯人出現的順序，等犯人出現完畢後，玩家再依照犯人出現的順序點擊洞口，答對會加2分，答錯不扣分，在限制時間內得到規定的分數通過關卡。
                </div>
                
                <div id="prisoner-next-step-btn" style="margin-left:2rem;">
                    <button id="prisoner-next-step-button" class="game-step-button next-step" style="padding:14px 28px;font-size:20px;">
                        下一步
                    </button>
                </div>
            </div>
            
            <div style="text-align:center;margin-top:1.5rem;margin-bottom:1.5rem;">
                <span id="prisoner-step-indicator" class="game-step-indicator" style="font-size:18px;">步驟 1/2</span>
            </div>
        </div>
      </div>
    </div>

    <div id="result-modal" class="modal" style="display: none;">
      <div class="modal-content">
        <h2 id="result-title"></h2>
        <p id="result-difficulty"></p>
        <p id="result-score"></p>
        <div>
          <button onclick="location.reload()">再玩一次</button>
          <button onclick="history.back()">返回主頁</button>
        </div>
      </div>
    </div>


    <button id="start-btn" style="display: none;">開始遊戲</button>

    <div class="holes">
      <div class="hole" id="hole1">
        <img src="img/prisoner.png" class="mole" />
        <img class="police" src="img/police.png" alt="警察">
      </div>
      <div class="hole" id="hole2">
        <img src="img/prisoner.png" class="mole" />
        <img class="police" src="img/police.png" alt="警察">
      </div>
      <div class="hole" id="hole3">
        <img src="img/prisoner.png" class="mole" />
        <img class="police" src="img/police.png" alt="警察">
      </div>
      <div class="hole" id="hole4">
        <img src="img/prisoner.png" class="mole" />
        <img class="police" src="img/police.png" alt="警察">
      </div>
      <div class="hole" id="hole5">
        <img src="img/prisoner.png" class="mole" />
        <img class="police" src="img/police.png" alt="警察">
      </div>
      <div class="hole" id="hole6">
        <img src="img/prisoner.png" class="mole" />
        <img class="police" src="img/police.png" alt="警察">
      </div>
      <div class="hole" id="hole7">
        <img src="img/prisoner.png" class="mole" />
        <img class="police" src="img/police.png" alt="警察">
      </div>
      <div class="hole" id="hole8">
        <img src="img/prisoner.png" class="mole" />
        <img class="police" src="img/police.png" alt="警察">
      </div>
      <div class="hole" id="hole9">
        <img src="img/prisoner.png" class="mole" />
        <img class="police" src="img/police.png" alt="警察">
      </div>
    </div>

    <div id="message" style="font-size: 24px; font-weight: bold; color: red; margin-top: 20px;"></div>

    <div class="button-group">
        <button class="control-btn" id="pause-btn">暫停遊戲</button>
        <button class="control-btn" id="end-btn">結束遊戲</button>
        <button class="control-btn" id="restart-btn">重新開始</button>
    </div>
  </div>
  <audio id="game-bgm" src="audio/prisoner.mp3" loop></audio>

  <script src="js/prisoner.js"></script>
</body>
</html>