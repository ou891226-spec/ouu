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
let moveInterval = null;
let timerInterval = null;
let startTime = null;
let pauseTime = 0; // 新增：用於記錄暫停的時間點

// 獲取會員ID
const memberIdInput = document.getElementById('member-id');
const memberId = memberIdInput ? parseInt(memberIdInput.value) : 1;

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
  startTime = Date.now();

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
  noteTimeoutId = setTimeout(spawnNoteWithRhythm, delay);
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
    }
  });
}

function handleHit() {
  if (!gameRunning || paused) return;

  // 在這裡呼叫揮棒函式，確保只要點擊打擊區就一定會揮棒
  swingBat();

  const zoneLeft = hitZone.getBoundingClientRect().left;
  const zoneRight = zoneLeft + hitZone.offsetWidth;
  let hitResult = '';
  let scoreToAdd = 0;
  for (let i = 0; i < notes.length; i++) {
    const note = notes[i];
    const noteBox = note.getBoundingClientRect();
    const center = noteBox.left + noteBox.width / 2;
    let tolerance = currentDifficulty === 'easy' ? 40 : currentDifficulty === 'normal' ? 30 : 20;
    if (center >= zoneLeft - tolerance && center <= zoneRight + tolerance) {
      flashHitZone();
      const diff = Math.abs(center - (zoneLeft + hitZone.offsetWidth / 2));
      if (diff < 20) {
        scoreToAdd = 20;
        perfectCount++;
        hitResult = 'Perfect';
      } else if (diff < 50) {
        scoreToAdd = 10;
        goodCount++;
        hitResult = 'Good';
      } else {
        missCount++;
        hitResult = 'Miss';
        scoreToAdd = 0;
      }
      score += scoreToAdd;
      currentScoreDisplay.textContent = score;
      if (score > highScore) {
        highScore = score;
        highScoreDisplay.textContent = score;
        localStorage.setItem("rhythmHighScore", score);
      }
      showHitResult(hitResult, scoreToAdd);
      note.remove();
      notes.splice(i, 1);
      break;
    }
  }
}

function showHitResult(result, score) {
  const resultDiv = document.createElement("div");
  resultDiv.className = "hit-result";
  resultDiv.textContent = `${result} +${score}`;

  resultDiv.style.position = "absolute";
  resultDiv.style.left = "50%";
  resultDiv.style.top = "50%";
  resultDiv.style.transform = "translate(-50%, -50%)";
  resultDiv.style.fontSize = "35px";
  resultDiv.style.fontWeight = "bold";
  resultDiv.style.color = result === "Perfect" ? "#FFD700" : result === "Good" ? "#4CAF50" : "#FF5722";
  resultDiv.style.textShadow = "2px 2px 4px rgba(0,0,0,0.5)";
  resultDiv.style.zIndex = "1000";
  resultDiv.style.pointerEvents = "none";

  const gameArea = document.getElementById("gameArea");
  gameArea.appendChild(resultDiv);

  let opacity = 1;
  let y = 0;
  const fadeOut = setInterval(() => {
    opacity -= 0.05;
    y -= 2;
    resultDiv.style.opacity = opacity;
    resultDiv.style.top = `calc(50% + ${y}px)`;

    if (opacity <= 0) {
      resultDiv.remove();
      clearInterval(fadeOut);
    }
  }, 50);
}

function flashHitZone() {
  hitZone.style.backgroundColor = '#FFD700'; // 例如，改成金黃色
  setTimeout(() => hitZone.style.backgroundColor = '#B9835C', 100); // 100 毫秒後恢復為打擊區的顏色
}

function togglePause() {
  paused = !paused;
  if (paused) {
    bgm.pause();
    pauseButton.textContent = "繼續遊戲";
    pauseButton.style.background = '#4CAF50'; // 綠色
    clearTimeout(noteTimeoutId);
    clearInterval(moveInterval);
    clearInterval(timerInterval);
    // 記錄暫停時的時間
    pauseTime = Date.now();
  } else {
    // 計算暫停的持續時間
    const pauseDuration = Date.now() - pauseTime;

    // 將暫停時間加到每個音符的生成時間上，讓它們從暫停位置繼續
    notes.forEach(note => {
      const spawnTime = parseInt(note.dataset.spawnTime);
      note.dataset.spawnTime = spawnTime + pauseDuration;
    });

    // 將暫停時間加到遊戲開始時間，確保計時器正確
    startTime += pauseDuration;

    bgm.play();
    pauseButton.textContent = "暫停遊戲";
    pauseButton.style.background = '#FF8C00'; // 橘色
    spawnNoteWithRhythm();
    moveInterval = setInterval(moveNotes, 16);
    timerInterval = setInterval(() => {
      const remaining = 60 - Math.floor((Date.now() - startTime) / 1000);
      gameTime = remaining;
      timerDisplay.textContent = remaining;
      if (remaining <= 0) endGame();
    }, 500);
  }
}

function endGame(forceEnd = false) {
  console.log("endGame() called.");
  console.log("Current Score:", score);
  console.log("Pass Score:", passScore);
  console.log("Force End:", forceEnd);
  console.log("Is game passed?", score >= passScore);

  gameRunning = false;
  clearInterval(moveInterval);
  clearInterval(timerInterval);
  bgm.pause();
  bgm.currentTime = 0;
  
  clearTimeout(noteTimeoutId);

  let sendMemberId = (typeof memberId !== "undefined" && memberId) ? memberId : 1;
  let recordScore = 0;

  if (forceEnd) {
    recordScore = 0;
    console.log("Force end. Record score set to 0.");
  } else if (score >= passScore) {
    if (currentDifficulty === 'easy') recordScore = 20;
    else if (currentDifficulty === 'normal') recordScore = 50;
    else if (currentDifficulty === 'hard') recordScore = 100;
    console.log("Game passed! Calculated record score:", recordScore);
  } else {
    recordScore = 0;
    console.log("Game not passed. Record score set to 0.");
  }

  console.log("Attempting to save score...");
  fetch('save_rhythm_game.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      member_id: sendMemberId,
      difficulty: currentDifficulty,
      score: recordScore,
      play_time: 60 - gameTime,
      is_passed: score >= passScore
    })
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      console.log('成績已儲存！', data);
    } else {
      console.error('儲存失敗：', data.message);
    }
  })
  .catch(error => {
    console.error('Fetch error:', error);
  });

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

  showEndModal(forceEnd ? false : score >= passScore, recordScore, currentDifficulty);
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

function closeInfoModal() {
  infoModal.style.display = "none";
}

document.addEventListener('DOMContentLoaded', () => {
  const closeBtn = document.querySelector('.close-btn');
  if (closeBtn) {
    closeBtn.addEventListener('click', closeInfoModal);
  }
});

restartButton.addEventListener("click", () => location.reload());
endButton.addEventListener("click", () => endGame(true));
document.getElementById("gameArea").addEventListener("click", handleHit);

function showEndModal(success, score, levelStr) {
  const modal = document.getElementById('result-modal');
  const title = document.getElementById('result-title');
  const difficulty = document.getElementById('result-difficulty');
  const message = document.getElementById('result-score');

  title.textContent = success ? '恭喜破關' : '遊戲失敗';
  const levelName = levelStr === 'easy' ? '簡單' : levelStr === 'normal' ? '普通' : '困難';
  difficulty.textContent = '難度：' + levelName;
  
  let fixedScore = 0;
  if (levelStr === 'easy') fixedScore = 20;
  else if (levelStr === 'normal') fixedScore = 50;
  else if (levelStr === 'hard') fixedScore = 100;
  
  message.innerHTML = success ? '得分：' + fixedScore + '<br>過關分數：+' + fixedScore : '未在時間內達成分數';

  modal.style.display = 'flex';
}

function swingBat() {
  bat.classList.add('swing');

  setTimeout(() => {
    bat.classList.remove('swing');
  }, 300);
}