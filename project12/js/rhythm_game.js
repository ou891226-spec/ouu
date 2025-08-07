// DOM 取得
const pauseButton = document.getElementById("pause-btn");
const endButton = document.getElementById("end-btn");
const restartButton = document.getElementById("restart-btn");
const noteTrack = document.getElementById("noteTrack");
const hitZone = document.getElementById("hitZone");
const timerDisplay = document.getElementById("timer");
const currentScoreDisplay = document.getElementById("score");
const highScoreDisplay = document.getElementById("high-score");
const bgm = document.getElementById("bgm");
const statusText = document.createElement("div"); // 顯示狀態訊息
const finalResult = document.createElement("div"); // 顯示結束結果

document.querySelector(".score-board").appendChild(statusText);
document.querySelector(".score-board").appendChild(finalResult);

// Modal
const difficultyModal = document.getElementById('difficulty-modal');
const difficultyOptions = document.querySelectorAll('.difficulty-option');
const infoBtn = document.getElementById('info-btn');
const infoModal = document.getElementById('info-modal');
const closeInfo = document.getElementById('close-info');

let notes = [];
let score = 0;
let highScore = localStorage.getItem("rhythmHighScore") || 0;
let gameTime = 60;
let gameRunning = false;
let paused = false;
let rhythmPattern = [];
let rhythmIndex = 0;
let passScore = 200;
let perfectCount = 0, goodCount = 0, missCount = 0;
let currentDifficulty = 'easy';
let noteTimeoutId = null;

document.getElementById('back-btn').addEventListener('click', () => {
  window.location.href = 'index.php';
});

highScoreDisplay.textContent = highScore;

// 音樂與節奏設定
const rhythmPatterns = {
  easy: [1000, 1200, 1000, 1500, 800],
  normal: [800, 600, 1000, 700, 900],
  hard: [400, 300, 600, 200, 500, 300, 700]
};

function setDifficulty(levelStr) {
  currentDifficulty = levelStr;
  rhythmPattern = rhythmPatterns[levelStr];
  rhythmIndex = 0;
  passScore = levelStr === 'easy' ? 300 : levelStr === 'normal' ? 800 : 1200;
  bgm.src = levelStr === 'easy' ? "audio/music2.mp3" : levelStr === 'normal' ? "audio/music3.mp3" : "audio/hard.mp3";
}

function startGame() {
  resetGame();
  setDifficulty(currentDifficulty);
  bgm.currentTime = 0;
  bgm.play();
  gameRunning = true;
  spawnNoteWithRhythm();
  moveInterval = setInterval(moveNotes, 16);
  const startTime = Date.now();

  timerInterval = setInterval(() => {
    if (paused) return;
    const remaining = 60 - Math.floor((Date.now() - startTime) / 1000);
    gameTime = remaining;
    timerDisplay.textContent = remaining;
    if (remaining <= 0) endGame();
  }, 500);
}

function spawnNoteWithRhythm() {
  if (!gameRunning || paused) return;
  spawnNote();
  const delay = rhythmPattern[rhythmIndex];
  rhythmIndex = (rhythmIndex + 1) % rhythmPattern.length;
  noteTimeoutId = setTimeout(spawnNoteWithRhythm, delay); // ← 儲存 ID
}


function spawnNote() {
  const note = document.createElement("div");
  note.classList.add("note");
  note.style.left = "600px";
  note.dataset.spawnTime = Date.now();

  const img = document.createElement("img");
  img.src = "img/note.png";
  note.appendChild(img);
  noteTrack.appendChild(note);
  notes.push(note);
}

function moveNotes() {
  if (!gameRunning || paused) return;
  const currentTime = Date.now();
  notes.forEach((note, index) => {
    const spawnTime = parseInt(note.dataset.spawnTime);
    const elapsed = currentTime - spawnTime;
    let moveDuration = currentDifficulty === 'easy' ? 3000 : currentDifficulty === 'normal' ? 2500 : 2000;
    const progress = elapsed / moveDuration;
    const x = 600 - progress * 560;
    note.style.left = `${x}px`;
    if (x < -50) {
      note.remove();
      notes.splice(index, 1);
      missCount++;
      statusText.textContent = `❌ Miss！（目前：${score}）`;
    }
  });
}

function handleHit() {
  if (!gameRunning || paused) return;
  const zoneLeft = hitZone.getBoundingClientRect().left;
  const zoneRight = zoneLeft + hitZone.offsetWidth;
  for (let i = 0; i < notes.length; i++) {
    const note = notes[i];
    const noteBox = note.getBoundingClientRect();
    const center = noteBox.left + noteBox.width / 2;
    let tolerance = currentDifficulty === 'easy' ? 30 : currentDifficulty === 'normal' ? 20 : 10;
    if (center >= zoneLeft - tolerance && center <= zoneRight + tolerance) {
      flashHitZone();
      const diff = Math.abs(center - (zoneLeft + hitZone.offsetWidth / 2));
      if (diff < 20) {
        score += 20; perfectCount++;
        statusText.textContent = `🎯 Perfect！+20（目前：${score}）`;
      } else if (diff < 50) {
        score += 10; goodCount++;
        statusText.textContent = `👍 Good！+10（目前：${score}）`;
      } else {
        missCount++; statusText.textContent = `❌ Miss！（目前：${score}）`;
      }
      note.remove(); notes.splice(i, 1);
      currentScoreDisplay.textContent = score;
      if (score > highScore) {
        highScore = score;
        highScoreDisplay.textContent = score;
        localStorage.setItem("rhythmHighScore", score);
      }
      break;
    }
  }
}

function flashHitZone() {
  hitZone.style.backgroundColor = 'rgba(0, 255, 0, 0.3)';
  setTimeout(() => hitZone.style.backgroundColor = 'rgba(255, 0, 0, 0.05)', 100);
}

function togglePause() {
  paused = !paused;
  if (paused) {
    bgm.pause();
    pauseButton.textContent = "繼續遊戲";
    pauseButton.classList.add('resume-btn');
    clearTimeout(noteTimeoutId); // ← 清除節奏出現的 setTimeout
  } else {
    bgm.play();
    pauseButton.textContent = "暫停遊戲";
    pauseButton.classList.remove('resume-btn');
    spawnNoteWithRhythm(); // ← 重新開始節奏
  }
}


function endGame() {
  console.log("endGame() called."); // 新增偵錯訊息
  console.log("Current Score:", score); // 新增偵錯訊息
  console.log("Pass Score:", passScore); // 新增偵錯訊息
  console.log("Is game passed?", score >= passScore); // 新增偵錯訊息

  gameRunning = false;
  clearInterval(moveInterval);
  clearInterval(timerInterval);
  bgm.pause();
  bgm.currentTime = 0;
  finalResult.innerHTML = `
    ${score >= passScore ? '🎉 <b>過關！</b>' : '😢 <b>未過關</b>'}<br>
    🔢 分數：${score}<br>
    🎯 Perfect：${perfectCount}<br>
    👍 Good：${goodCount}<br>
    ❌ Miss：${missCount}
  `;
  clearTimeout(noteTimeoutId); // ← 清除節奏出現的 setTimeout

  let sendMemberId = document.getElementById('member-id') ? parseInt(document.getElementById('member-id').value) : 8;
  let recordScore = 0; // 初始化 recordScore

  // 計算 recordScore，無論是否過關
  if (score >= passScore) { // 判斷是否過關
    if (currentDifficulty === 'easy') recordScore = 20;
    else if (currentDifficulty === 'normal') recordScore = 50;
    else if (currentDifficulty === 'hard') recordScore = 100;
    console.log("Game passed! Calculated record score:", recordScore); // 新增偵錯訊息
  } else {
    recordScore = 0; // 未過關則記錄 0 分
    console.log("Game not passed. Record score set to 0."); // 新增偵錯訊息
  }

  // 總是嘗試送分數到後端
  console.log("Attempting to save score..."); // 新增偵錯訊息
  fetch('save_rhythm_game.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      member_id: sendMemberId,
      difficulty: currentDifficulty === 'easy' ? '簡單' : currentDifficulty === 'normal' ? '普通' : '困難',
      score: recordScore, // 使用計算後的 recordScore
      play_time: 60 - gameTime // 遊玩秒數
    })
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      console.log('成績已儲存！', data); // 新增偵錯訊息
    } else {
      console.error('儲存失敗：', data.message); // 新增偵錯訊息
    }
  })
  .catch(error => {
    console.error('Fetch error:', error); // 新增錯誤捕獲
  });

  // 檢查並更新任務狀態
  if (score >= passScore && currentDifficulty === 'normal') {
    fetch("update_task_status.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json"
      },
      body: JSON.stringify({
        task_type: "achievement",
        difficulty: currentDifficulty,
        game_type: "節奏遊戲"
      })
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        console.log('任務狀態已更新');
      } else {
        console.error('更新任務狀態失敗:', data.message);
      }
    })
    .catch(error => {
      console.error('更新任務狀態時發生錯誤:', error);
    });
  }

  // 修正 showEndModal 的分數參數，確保顯示的是 recordScore
  showEndModal(score >= passScore, recordScore, currentDifficulty);
}

function resetGame() {
  notes.forEach(note => note.remove());
  notes = [];
  score = 0;
  gameTime = 60;
  perfectCount = 0; goodCount = 0; missCount = 0;
  paused = false;
  currentScoreDisplay.textContent = "0";
  statusText.textContent = "";
  finalResult.textContent = "";
  timerDisplay.textContent = "60";
}

// Modal 操作
window.onload = () => difficultyModal.style.display = 'flex';
difficultyOptions.forEach(option => {
  option.addEventListener('click', () => {
    const levelText = option.classList.contains("easy") ? "easy"
                    : option.classList.contains("medium") ? "normal"
                    : "hard";
    difficultyModal.style.display = 'none';
    setDifficulty(levelText);
    startGame();
  });
});

pauseButton.addEventListener("click", togglePause);
infoBtn.addEventListener("click", () => infoModal.style.display = "flex");
// 關閉說明視窗的函數
function closeInfoModal() {
  infoModal.style.display = "none";
}

// 綁定關閉按鈕事件
document.addEventListener('DOMContentLoaded', () => {
  const closeBtn = document.querySelector('.close-btn');
  if (closeBtn) {
    closeBtn.addEventListener('click', closeInfoModal);
  }
});

restartButton.addEventListener("click", () => location.reload());
endButton.addEventListener("click", () => endGame());
document.getElementById("gameArea").addEventListener("click", handleHit);

// 結束後顯示結果 modal
function showEndModal(success, score, levelStr) {
  const modal = document.getElementById('result-modal');
  const title = document.getElementById('result-title');
  const difficulty = document.getElementById('result-difficulty');
  const message = document.getElementById('result-score');

  title.textContent = success ? '恭喜破關' : '遊戲失敗';
  const levelName = levelStr === 'easy' ? '簡單' : levelStr === 'normal' ? '普通' : '困難';
  difficulty.textContent = '難度：' + levelName;
  message.textContent = success ? '得分：' + score : '未在時間內達成分數';

  modal.style.display = 'flex';
}


document.addEventListener("DOMContentLoaded", function () {
  const hitZone = document.getElementById("hitZone");
  const bat = document.getElementById("bat");

  hitZone.addEventListener("click", function () {
    bat.classList.add("swing");

    // 揮棒完移除 class，恢復原狀
    setTimeout(() => {
      bat.classList.remove("swing");
    }, 150); // 跟 CSS transition 時間一致
  });
});

const bat = document.getElementById('bat');

function swingBat() {
  bat.classList.add('swing');

  // 動畫結束後移除 class 以便下次可以再觸發
  setTimeout(() => {
    bat.classList.remove('swing');
  }, 300); // 要跟 animation 的 duration 對應（0.3 秒）
}

// 例如你想在按下按鈕或點某區塊揮棒
document.addEventListener('click', swingBat);
