// DOM 取得
const pauseButton = document.getElementById("pause-btn");
const endButton = document.getElementById("end-btn");
const restartButton = document.getElementById("restart-btn");
const noteTrack = document.getElementById("noteTrack");
const hitZone = document.getElementById("hitZone");
const timerDisplay = document.getElementById("timer");
const currentScoreDisplay = document.getElementById("score");
const highScoreDisplay = document.getElementById("high-score");
const successSfx = document.getElementById("success-sfx");
const failSfx = document.getElementById("fail-sfx");
const tapSfx = document.getElementById("tap-sfx");

// 音訊播放函數，包含錯誤處理
function playAudio(audioElement) {
  if (!audioElement) return;
  
  try {
    // 重置音訊到開始位置
    audioElement.currentTime = 0;
    // 播放音訊
    const playPromise = audioElement.play();
    
    // 處理 Promise 返回的播放結果
    if (playPromise !== undefined) {
      playPromise.catch(error => {
        console.log("音訊播放被阻止或失敗:", error);
        // 不顯示錯誤，因為這通常是用戶互動政策導致的
      });
    }
  } catch (error) {
    console.log("音訊播放錯誤:", error);
  }
}
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

// 返回按鈕已在HTML中直接綁定onclick事件

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
}

function startGame() {
  resetGame();
  setDifficulty(currentDifficulty);
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
      // ★★★ 在這裡新增這行，確保棒球在最上層 ★★★
    note.style.zIndex = "20"; 
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
      showHitResult('Miss', 0);
      playAudio(failSfx); // 播放失敗音效
      note.remove();
      notes.splice(index, 1);
      missCount++;
    }
  });
}

function handleHit() {
  if (!gameRunning || paused) return;
  playAudio(tapSfx);
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
        playAudio(successSfx); // 播放成功音效
      } else if (diff < 50) {
        scoreToAdd = 10;
        goodCount++;
        hitResult = 'Good';
        playAudio(successSfx); // 播放成功音效
      } else {
        missCount++;
        hitResult = 'Miss';
        scoreToAdd = 0;
        playAudio(failSfx); // 播放失敗音效
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
  setTimeout(() => hitZone.style.backgroundColor = '#B9835C', 100); // 100 毫秒後恢復為點擊區的顏色
}

function togglePause() {
  paused = !paused;
  if (paused) {
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

// 在 rhythm_game.js 檔案中，找到 endGame 函式並替換成以下內容

function endGame() {
  console.log("endGame() called.");
  console.log("Current Score:", score);
  console.log("Pass Score:", passScore);
  console.log("Is game passed?", score >= passScore);

  const gameDuration = 60 - gameTime;
  const isPassed = score >= passScore;

  gameRunning = false;
  clearInterval(moveInterval);
  clearInterval(timerInterval);
  clearTimeout(noteTimeoutId);

  let sendMemberId = (typeof memberId !== "undefined" && memberId) ? memberId : 1;
  let recordScore = 0;

  if (isPassed) {
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
      play_time: gameDuration,
      is_passed: isPassed
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

  if (isPassed && currentDifficulty === 'normal') {
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

  showEndModal(isPassed, score, recordScore, currentDifficulty, gameDuration);
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
infoBtn.addEventListener("click", () => {
  infoModal.style.display = "flex";
  // 初始化節奏影片播放邏輯
  initRhythmVideoPlayback();
});

// 初始化節奏影片播放邏輯
function initRhythmVideoPlayback() {
  const video = document.getElementById('rhythm-current-video');
  const instructionText = document.getElementById('rhythm-instruction-text');
  const stepIndicator = document.getElementById('rhythm-step-indicator');
  const nextStepBtn = document.getElementById('rhythm-next-step-btn');
  const prevStepBtn = document.getElementById('rhythm-prev-step-btn');
  
  if (!video || !instructionText || !stepIndicator || !nextStepBtn || !prevStepBtn) {
    console.error('找不到節奏遊戲說明元素');
    return;
  }
  
  // 設置第一個影片
  video.src = 'gd/rhythm1.mp4';
  instructionText.textContent = '一進去先選擇遊戲難度，遊戲開始後玩家跟隨音符出現頻率點擊螢幕上的音符。成功點擊音符（Perfect+20分 / Good+10分 / Miss+0分）';
  stepIndicator.textContent = '步驟 1/2';
  
  // 設置當前影片標記
  video.setAttribute('data-current-video', 'rhythm1');
  
  // 顯示下一步按鈕，隱藏上一步按鈕
  nextStepBtn.style.display = 'block';
  prevStepBtn.style.display = 'none';
  
  // 強制加載影片
  video.load();
  
  // 添加下一步按鈕點擊事件
  const nextStepButton = document.getElementById('rhythm-next-step-button');
  if (nextStepButton) {
    nextStepButton.onclick = goToRhythmNextStep;
    console.log('節奏下一步按鈕事件已綁定');
  }
}

// 前往節奏下一步
function goToRhythmNextStep() {
  const video = document.getElementById('rhythm-current-video');
  const instructionText = document.getElementById('rhythm-instruction-text');
  const stepIndicator = document.getElementById('rhythm-step-indicator');
  const nextStepBtn = document.getElementById('rhythm-next-step-btn');
  const prevStepBtn = document.getElementById('rhythm-prev-step-btn');
  
  // 切換到第二個影片
  video.src = 'gd/rhythm2.mp4';
  video.setAttribute('data-current-video', 'rhythm2');
  instructionText.innerHTML = '隨著難度選擇的不同，音符出現的速度與數量會增加，使挑戰更具挑戰性，時間內達到遊戲指定過關分數即可過關 。';
  stepIndicator.textContent = '步驟 2/2';
  
  // 隱藏下一步按鈕，顯示上一步按鈕
  nextStepBtn.style.display = 'none';
  prevStepBtn.style.display = 'block';
  
  // 加載並播放影片
  video.load();
  video.play();
}

// 回到節奏上一步
function goToRhythmPrevStep() {
  const video = document.getElementById('rhythm-current-video');
  const instructionText = document.getElementById('rhythm-instruction-text');
  const stepIndicator = document.getElementById('rhythm-step-indicator');
  const nextStepBtn = document.getElementById('rhythm-next-step-btn');
  const prevStepBtn = document.getElementById('rhythm-prev-step-btn');
  
  // 切換到第一個影片
  video.src = 'gd/rhythm1.mp4';
  video.setAttribute('data-current-video', 'rhythm1');
  instructionText.textContent = '一進去先選擇遊戲難度，遊戲開始後玩家跟隨音符出現頻率點擊螢幕上的音符。成功點擊音符（Perfect+20分 / Good+10分 / Miss+0分）';
  stepIndicator.textContent = '步驟 1/2';
  
  // 顯示下一步按鈕，隱藏上一步按鈕
  nextStepBtn.style.display = 'block';
  prevStepBtn.style.display = 'none';
  
  // 加載並播放影片
  video.load();
  video.play();
}

// 設為全局可訪問
window.goToRhythmNextStep = goToRhythmNextStep;
window.goToRhythmPrevStep = goToRhythmPrevStep;

function closeInfoModal() {
  // 停止影片播放
  const video = document.getElementById('rhythm-current-video');
  if (video) {
    video.pause();
    video.currentTime = 0; // 重置到開始位置
  }
  
  infoModal.style.display = "none";
}

document.addEventListener('DOMContentLoaded', () => {
  const closeBtn = document.querySelector('.close-btn');
  if (closeBtn) {
    // 移除舊的事件監聽器，避免重複綁定
    closeBtn.removeEventListener('click', closeInfoModal);
    closeBtn.addEventListener('click', closeInfoModal);
    console.log('節拍遊戲關閉按鈕事件已綁定');
  }
});

restartButton.addEventListener("click", () => location.reload());
endButton.addEventListener("click", () => endGame());
document.getElementById("gameArea").addEventListener("click", handleHit);

function showEndModal(success, finalGameScore, recordScore, levelStr, gameDuration) {
    const modal = document.getElementById('result-modal');
    const title = document.getElementById('result-title');
    const difficulty = document.getElementById('result-difficulty');
    const scoreDisplay = document.getElementById('result-score');
    const timeDisplay = document.getElementById('result-time');
    const finalScoreDisplay = document.getElementById('result-final-score');

    title.textContent = success ? '🎉恭喜破關' : '⏰遊戲失敗';
    const levelName = levelStr === 'easy' ? '簡單' : levelStr === 'normal' ? '普通' : '困難';
    difficulty.textContent = '難度：' + levelName;
    scoreDisplay.textContent = '遊戲分數：' + finalGameScore;
    timeDisplay.textContent = '遊戲時間：' + gameDuration + ' 秒';
    finalScoreDisplay.textContent = '獲得分數：+' + recordScore;
    
    modal.style.display = 'flex';
}