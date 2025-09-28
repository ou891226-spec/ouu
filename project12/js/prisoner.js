const holes = document.querySelectorAll('.hole');
const scoreElement = document.getElementById('score');
const timerElement = document.getElementById('timer');
const startBtn = document.getElementById('start-btn');
const difficultyModal = document.getElementById('difficulty-modal');
const difficultyOptions = document.querySelectorAll('.difficulty-option');
const messageDiv = document.getElementById('message');
const pauseBtn = document.getElementById('pause-btn');
const gameBGM = document.getElementById('game-bgm');

let score = 0;
let highScore = 0;
let timeLeft = 60;
let sequence = [];
let playerSequence = [];
let gameInterval;
let level = 3;
let passScore = 0;
let gameTime = 60;
let isPaused = false;
let gameState = 'ready'; 

const memberIdInput = document.getElementById('member-id'); 
const memberId = memberIdInput ? parseInt(memberIdInput.value) : 1;

// 載入最高分數
function loadHighScore() {
    const savedHighScore = localStorage.getItem('prisoner_highscore');
    if (savedHighScore) {
        highScore = parseInt(savedHighScore);
        document.getElementById('high-score').textContent = highScore;
    } else {
        highScore = 0;
        document.getElementById('high-score').textContent = '0';
    }
}

// 更新最高分數
function updateHighScore() {
    if (score > highScore) {
        highScore = score;
        document.getElementById('high-score').textContent = highScore;
        localStorage.setItem('prisoner_highscore', highScore.toString());
    }
}

// 頁面載入時初始化最高分數
document.addEventListener('DOMContentLoaded', function() {
    loadHighScore();
});

startBtn.addEventListener('click', startGame);

function startGame() {
  if (level === 3) {
    gameTime = 60; 
    passScore = 20;
  } else if (level === 4) {
    gameTime = 60; 
    passScore = 20;
  } else if (level === 5) {
    gameTime = 120;
    passScore = 20;
  }
  
  playBGM();

  resetGame();
  timeLeft = gameTime;
  timerElement.textContent = timeLeft;
  startBtn.style.display = 'none';
  gameState = 'sequence'; 
  nextRound();
}

function resetGame() {
  score = 0;
  sequence = [];
  playerSequence = [];
  scoreElement.textContent = score;
  holes.forEach(hole => hole.classList.remove('active'));
  messageDiv.textContent = '';
  clearInterval(gameInterval);
}

function updateTimer() {
  if (gameState !== 'player_turn' || isPaused) {
    clearInterval(gameInterval);
    return;
  }

  timeLeft--;
  timerElement.textContent = timeLeft;
  if (timeLeft <= 0) {
    clearInterval(gameInterval);
    const success = score >= passScore;
    endGame(success);
  }
}

function nextRound() {
  playerSequence = [];
  sequence = getRandomHoles(level);
  showSequence();
  holes.forEach(hole => {
    const police = hole.querySelector('.police');
    if (police) police.style.display = 'none';
  });
}

function getRandomHoles(num) {
  const holesArray = [];
  while (holesArray.length < num) {
    const randomHole = Math.floor(Math.random() * holes.length);
    if (!holesArray.includes(randomHole)) {
      holesArray.push(randomHole);
    }
  }
  return holesArray;
}

function showSequence() {
  clearInterval(gameInterval); 
  gameState = 'sequence'; 
  let i = 0;
  messageDiv.textContent = '看仔細了...';
  const sequenceInterval = setInterval(() => {
    const hole = holes[sequence[i]];
    if (hole) {
      hole.classList.add('active');
      const mole = hole.querySelector('.mole');
      mole.style.bottom = '0px';
      setTimeout(() => {
        mole.style.bottom = '-105px';
        hole.classList.remove('active');
      }, 1200);
    }
    i++;
    if (i >= sequence.length) {
      clearInterval(sequenceInterval);
      setTimeout(() => {
        playerTurn();
      }, 1000);
    }
  }, 1200);
}

function playerTurn() {
  messageDiv.textContent = '換你了！';
  gameState = 'player_turn'; 
  gameInterval = setInterval(updateTimer, 1000);
  holes.forEach(hole => {
    hole.addEventListener('click', checkPlayerHit);
  });
}

function checkPlayerHit(event) {
  const hole = event.currentTarget;
  const holeIndex = Array.from(holes).indexOf(hole);

  if (playerSequence.includes(holeIndex)) return;

  playerSequence.push(holeIndex);

  const police = hole.querySelector('.police');
  if (police) police.style.display = 'block';

  if (playerSequence.length === sequence.length) {
    holes.forEach(h => h.removeEventListener('click', checkPlayerHit));
    checkSequence();
  }
}

function checkSequence() {
  clearInterval(gameInterval);
  
  messageDiv.textContent = '';
  let isCorrect = sequence.every((val, index) => val === playerSequence[index]);
  if (isCorrect) {
    score += 2;
    scoreElement.textContent = score;
    updateHighScore(); // 更新最高分數
    messageDiv.textContent = '答對了！+2 分';
    messageDiv.className = 'success';
  } else {
    messageDiv.textContent = '答錯了，繼續努力！';
    messageDiv.className = 'error';
  }
  setTimeout(nextRound, 1000);
}

window.onload = () => {
  difficultyModal.style.display = 'flex';
};

difficultyOptions.forEach(option => {
  option.addEventListener('click', () => {
    level = parseInt(option.dataset.level);
    difficultyModal.style.display = 'none';
    startGame();
  });
});

function endGame(success, isManualExit = false) {
  clearInterval(gameInterval);
  holes.forEach(hole => hole.removeEventListener('click', checkPlayerHit));
  pauseBGM(); // 遊戲結束時停止音樂
  
  sendScoreToServer(score, level, success, isManualExit); 
  
  showEndModal(success, score, level, isManualExit);
}

function showEndModal(success, finalScore, level) {
  const modal = document.getElementById('result-modal');
  const title = document.getElementById('result-title');
  const difficulty = document.getElementById('result-difficulty');
  const message = document.getElementById('result-score');

  title.textContent = success ? '恭喜破關' : '遊戲失敗';
  difficulty.textContent = '難度：' + (level === 3 ? '簡單' : level === 4 ? '普通' : '困難');
  
  let fixedScore = 20; // 所有難度都是20分獎勵
  
  message.innerHTML = success ? '最終分數：' + finalScore + '<br>過關獎勵分數：+' + fixedScore : '最終分數：' + finalScore + '<br>未在時間內達成分數，無獎勵';

  modal.style.display = 'flex';
}

pauseBtn.addEventListener('click', togglePause);
function togglePause() {
  if (gameState !== 'player_turn' && gameState !== 'paused') {
    return;
  }

  isPaused = !isPaused; 

  if (isPaused) {
    clearInterval(gameInterval);
    holes.forEach(hole => hole.removeEventListener('click', checkPlayerHit));
    messageDiv.textContent = '已暫停，請按繼續遊戲';
    
    pauseBGM(); // 暫停時停止音樂

    pauseBtn.textContent = '繼續遊戲';
    pauseBtn.classList.remove('pause-btn');
    pauseBtn.classList.add('resume-btn');
    gameState = 'paused';

  } else {
    gameInterval = setInterval(updateTimer, 1000);
    holes.forEach(hole => {
      hole.addEventListener('click', checkPlayerHit);
    });
    messageDiv.textContent = '換你了！';
    
    playBGM(); // 繼續時播放音樂

    pauseBtn.textContent = '暫停遊戲';
    pauseBtn.classList.remove('resume-btn');
    pauseBtn.classList.add('pause-btn');
    gameState = 'player_turn';
  }
}

document.getElementById('end-btn').addEventListener('click', () => {
  if (confirm('確定要結束遊戲嗎？')) {
    const success = score >= passScore;
    endGame(success, true); // 傳遞 isManualExit = true
  }
});

document.getElementById('restart-btn').addEventListener('click', () => {
  clearInterval(gameInterval);
  resetGame();
  document.getElementById('result-modal').style.display = 'none';
  difficultyModal.style.display = 'flex';
});

// BGM 控制功能
function playBGM() {
    gameBGM.play();
}

function pauseBGM() {
    gameBGM.pause();
}


document.getElementById('info-btn').addEventListener('click', () => {
  document.getElementById('info-modal').style.display = 'flex';
  initPrisonerVideoPlayback();
});

function initPrisonerVideoPlayback() {
  const video = document.getElementById('prisoner-current-video');
  const instructionText = document.getElementById('prisoner-instruction-text');
  const stepIndicator = document.getElementById('prisoner-step-indicator');
  const nextStepBtn = document.getElementById('prisoner-next-step-btn');
  const prevStepBtn = document.getElementById('prisoner-prev-step-btn');
  
  if (!video || !instructionText || !stepIndicator || !nextStepBtn || !prevStepBtn) {
    console.error('找不到犯人遊戲說明元素');
    return;
  }
  
  video.src = 'gd/prisoner1.mp4'; // 使用追蹤犯人遊戲專用影片檔案
  instructionText.textContent = '先選擇遊戲的難度，每個難度只要得分超過20分(含)就會過關。選擇難度後遊戲會開始，畫面上有九個洞，洞會輪流出現犯人，請玩家記住犯人出現的順序，等犯人出現完畢後，玩家再依照犯人出現的順序點擊洞口，';
  stepIndicator.textContent = '步驟 1/2';
  
  video.setAttribute('data-current-video', 'prisoner1');
  
  nextStepBtn.style.display = 'block';
  prevStepBtn.style.display = 'none';
  
  video.load();
  
  const nextStepButton = document.getElementById('prisoner-next-step-button');
  if (nextStepButton) {
    nextStepButton.onclick = goToPrisonerNextStep;
  }
}

function goToPrisonerNextStep() {
  const video = document.getElementById('prisoner-current-video');
  const instructionText = document.getElementById('prisoner-instruction-text');
  const stepIndicator = document.getElementById('prisoner-step-indicator');
  const nextStepBtn = document.getElementById('prisoner-next-step-btn');
  const prevStepBtn = document.getElementById('prisoner-prev-step-btn');
  
  video.src = 'gd/prisoner2.mp4'; // 使用追蹤犯人遊戲專用影片檔案
  video.setAttribute('data-current-video', 'prisoner2');
  instructionText.innerHTML = '玩家依照犯人出現的順序點擊洞口，答對會加2分，答錯不扣分，在限制時間內得到規定的分數通過關卡。';
  stepIndicator.textContent = '步驟 2/2';
  
  nextStepBtn.style.display = 'none';
  prevStepBtn.style.display = 'block';
  
  video.load();
  video.play();
}

function goToPrisonerPrevStep() {
  const video = document.getElementById('prisoner-current-video');
  const instructionText = document.getElementById('prisoner-instruction-text');
  const stepIndicator = document.getElementById('prisoner-step-indicator');
  const nextStepBtn = document.getElementById('prisoner-next-step-btn');
  const prevStepBtn = document.getElementById('prisoner-prev-step-btn');
  
  video.src = 'gd/prisoner1.mp4'; // 使用追蹤犯人遊戲專用影片檔案
  video.setAttribute('data-current-video', 'prisoner1');
  instructionText.textContent = '先選擇遊戲的難度，每個難度只要得分超過20分(含)就會過關。選擇難度後遊戲會開始，畫面上有九個洞，洞會輪流出現犯人，請玩家記住犯人出現的順序，等犯人出現完畢後，玩家再依照犯人出現的順序點擊洞口，';
  stepIndicator.textContent = '步驟 1/2';
  
  nextStepBtn.style.display = 'block';
  prevStepBtn.style.display = 'none';
  
  video.load();
  video.play();
}

window.goToPrisonerNextStep = goToPrisonerNextStep;
window.goToPrisonerPrevStep = goToPrisonerPrevStep;

function closeInfoModal() {
  const video = document.getElementById('prisoner-current-video');
  if (video) {
    video.pause();
    video.currentTime = 0;
  }
  
  document.getElementById('info-modal').style.display = 'none';
}

document.addEventListener('DOMContentLoaded', () => {
  const closeBtn = document.querySelector('.close-btn');
  if (closeBtn) {
    closeBtn.removeEventListener('click', closeInfoModal);
    closeBtn.addEventListener('click', closeInfoModal);
  }
});

function sendScoreToServer(score, level, is_passed, isManualExit = false) {
  const difficultyText = level === 3 ? 'easy' : level === 4 ? 'normal' : 'hard';
  const data = {
    member_id: memberId,
    difficulty: difficultyText,
        score: score, 
        play_time: gameTime - timeLeft,
        is_passed: is_passed,
        is_manual_exit: isManualExit,
  };

  fetch('save_prisoner_game.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify(data)
  })
    .then(res => res.json())
    .then(result => {
      if (result.success) {
        console.log("✅ 成績已儲存");
        // 核心修正：移除這行程式碼，避免頁面自動刷新
        // location.reload(); 
      } else {
        console.error("❌ 儲存失敗：", result.message);
      }
    })
    .catch(err => {
      console.error("❌ 發送錯誤：", err);
    });
}