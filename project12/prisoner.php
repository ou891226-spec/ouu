<?php
require_once 'check_login.php';
require_once 'db_connect.php';
require_once 'game_entry_tracker.php';
 
// 獲取難度設定
$difficulties = [];
$stmt = $pdo->prepare("SELECT * FROM difficulty_settings WHERE game_id = 6 ORDER BY difficulty");
$stmt->execute();
$settings = $stmt->fetchAll();

foreach ($settings as $setting) {
    $difficulties[$setting['difficulty']] = [
        'difficulty_level' => $setting['difficulty'],
        'hole_count' => $setting['difficulty'] === 'easy' ? 3 : ($setting['difficulty'] === 'normal' ? 4 : 5),
        'time_limit' => $setting['time_limit'],
        'sequence_length' => $setting['difficulty'] === 'easy' ? 3 : ($setting['difficulty'] === 'normal' ? 4 : 5),
        'pass_score' => $setting['pass_score'],
        'is_active' => true
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
  <link rel="stylesheet" href="css/prisoner.css?v=<?php echo time(); ?>">
    <script src="js/unified-game-tracker.js"></script>
    <script src="js/game-exit-handler.js"></script>
    <script>
        // 從資料庫獲取的難度設定
        const difficultySettings = <?php echo json_encode($difficulties); ?>;
        
        // 初始化遊戲追蹤器
        document.addEventListener("DOMContentLoaded", function() {
            gameTracker.init("記憶力", 6);
            
            // 配置遊戲退出處理器
            gameExitHandler.updateConfig({
                memberId: <?= $_SESSION['member_id'] ?? 'null' ?>,
                gameType: '記憶力',
                gameId: 6,
                difficulty: 'easy'
            });
            
            // 設置獲取玩家操作時間的函數
            gameExitHandler.setPlayerPlayTimeFunction(function() {
                return typeof playerPlayTime !== 'undefined' ? Math.round(playerPlayTime) : 0;
            });
        });
        
        // 在遊戲開始時啟動退出追蹤
        function startGameTracking() {
            if (typeof gameExitHandler !== 'undefined') {
                // 遊戲追蹤將在真正開始遊戲時啟動
            }
        }
        
        // 在遊戲結束時停止退出追蹤
        function endGameTracking() {
            if (typeof gameExitHandler !== 'undefined') {
                gameExitHandler.endGame();
            }
        }
    </script>
</head>
<body>
  <div class="game-container">
    <input type="hidden" id="member-id" value="<?php echo $_SESSION['member_id'] ?? 1; ?>">
    <h1>追蹤犯人遊戲</h1>

    <div class="score-board">
        <h2>目前分數: <span id="score">0</span></h2>
        <h2>目標分數: <span id="target-score">20</span></h2>
        <h2>剩餘時間: <span id="timer">30</span> 秒</h2>
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
          <div class="difficulty-option easy" data-level="3">簡單 (<?php echo isset($difficulties['easy']['pass_score']) ? $difficulties['easy']['pass_score'] : 20; ?>分過關)</div>
          <div class="difficulty-option medium" data-level="4">普通 (<?php echo isset($difficulties['normal']['pass_score']) ? $difficulties['normal']['pass_score'] : 20; ?>分過關)</div>
          <div class="difficulty-option hard" data-level="5">困難 (<?php echo isset($difficulties['hard']['pass_score']) ? $difficulties['hard']['pass_score'] : 20; ?>分過關)</div>
      </div>
  </div>


    <div id="info-modal" class="modal" style="display:none;">
      <div class="modal-content">
        <span class="close-btn" onclick="closeInfoModal()" style="position:absolute; top:10px; right:15px; cursor:pointer; font-size: 30px;">×</span>
        <h2 style="text-align:center;">
            <span style="font-size:2rem;vertical-align:middle;">🎮</span>
            <span style="font-weight:bold;vertical-align:middle;">遊戲說明</span>
        </h2>
        <div class="help-content" style="margin-top:1.5rem;padding:0 1.5rem;">
            <div id="prisoner-video-container" style="text-align:center;margin-bottom:1.5rem;">
                <video id="prisoner-current-video" width="100%" height="auto" controls style="max-width:400px;width:60%;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,0.15);">
                    <source src="gd/prisoner1.mp4" type="video/mp4">
                    您的瀏覽器不支援影片播放。
                </video>
            </div>
            
            <!-- 說明文字區域 -->
            <div style="text-align:center;margin:0 1rem;margin-bottom:2rem;">
                <div id="prisoner-instruction-text" class="game-instruction-text">
                    先選擇遊戲的難度，每個難度只要得分超過20分(含)就會過關。選擇難度後遊戲會開始，畫面上有九個洞，洞會輪流出現犯人，請玩家記住犯人出現的順序，等犯人出現完畢後，玩家再依照犯人出現的順序點擊洞口，答對會加2分，答錯不扣分，在限制時間內得到規定的分數通過關卡。
                </div>
            </div>
            
            <!-- 按鈕和步驟指示器區域 -->
            <div class="help-modal-footer" style="display: flex !important; justify-content: space-between !important; align-items: center !important; position: relative !important;">
                <!-- 上一步按鈕 -->
                <div id="prisoner-prev-step-btn" style="display:none; margin-right: auto !important; order: 1 !important;">
                    <button id="prisoner-prev-step-button" onclick="goToPrisonerPrevStep()" class="game-step-button prev-step">
                        上一步
                    </button>
                </div>
                
                <!-- 進度指示器 -->
                <span id="prisoner-step-indicator" class="game-step-indicator" style="position: absolute !important; left: 50% !important; transform: translateX(-50%) !important; order: 2 !important;">步驟 1/2</span>
                
                <!-- 下一步按鈕 -->
                <div id="prisoner-next-step-btn" style="margin-left: auto !important; order: 3 !important;">
                    <button id="prisoner-next-step-button" class="game-step-button next-step">
                        下一步
                    </button>
                </div>
            </div>
        </div>
      </div>
    </div>

    <div id="result-modal" class="modal" style="display: none;">
      <div class="modal-content" id="custom-end-modal-content">
        <h2 id="result-title"></h2>
        <div class="result-details">
            <p id="result-difficulty"></p>
            <p id="result-game-score"></p>
            <p id="result-play-time"></p>
            <p id="result-gained-score"></p>
            <p id="result-final-message"></p>
        </div>
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

  <script src="js/auto-save-time-fixed.js"></script>
  <script src="js/game-common.js"></script>
  <script src="js/get-score.js"></script>
  <script src="js/prisoner.js?v=<?php echo time(); ?>"></script>
</body>
</html>