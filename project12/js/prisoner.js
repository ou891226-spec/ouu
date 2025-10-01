const holes = document.querySelectorAll('.hole');
const scoreElement = document.getElementById('score');
const timerElement = document.getElementById('timer');
const startBtn = document.getElementById('start-btn');
const difficultyModal = document.getElementById('difficulty-modal');
const difficultyOptions = document.querySelectorAll('.difficulty-option');
const messageDiv = document.getElementById('message');
const pauseBtn = document.getElementById('pause-btn');
const gameBGM = document.getElementById('game-bgm');
const targetScoreElement = document.getElementById('target-score'); 

let score = 0;
let timeLeft = 60;
let sequence = [];
let playerSequence = [];
let gameInterval;
let level = 3;
let passScore = 20; 
let gameTime = 60;
let isPaused = false;
let gameState = 'ready';
let gameStartTime = 0; // 記錄遊戲開始時間 

const memberIdInput = document.getElementById('member-id'); 
const memberId = memberIdInput ? parseInt(memberIdInput.value) : 1;

startBtn.addEventListener('click', startGame);

function startGame() {
  // 根據難度等級獲取對應的難度名稱
  let difficultyName;
  if (level === 3) {
    difficultyName = 'easy';
  } else if (level === 4) {
    difficultyName = 'normal';
  } else if (level === 5) {
    difficultyName = 'hard';
  }
  
  // 從資料庫設定中獲取時間和分數
  if (typeof difficultySettings !== 'undefined' && difficultySettings[difficultyName]) {
    gameTime = difficultySettings[difficultyName].time_limit;
    passScore = difficultySettings[difficultyName].pass_score;
  } else {
    // 如果資料庫設定不可用，使用預設值
    gameTime = 30;
    passScore = 20;
  }
  
  targetScoreElement.textContent = passScore; 
  
  playBGM();

  resetGame();
  timeLeft = gameTime;
  timerElement.textContent = timeLeft;
  gameStartTime = Date.now(); // 記錄遊戲開始時間
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
  messageDiv.textContent = '看仔細了...犯人準備出現囉';
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
  messageDiv.textContent = '換你出馬抓住犯人囉！';
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
    messageDiv.textContent = '答對了！抓到犯人囉!+2 分';
    messageDiv.className = 'success';

    if (score >= passScore) {
      setTimeout(() => {
        endGame(true);
      }, 500); 
      return; 
    }

  } else {
    messageDiv.textContent = '答錯了，犯人跑走了！';
    messageDiv.className = 'error';
  }

  setTimeout(nextRound, 1000);
}


window.onload = () => {
  difficultyModal.style.display = 'flex';
  // 設置初始時間為資料庫中簡單模式的時間
  if (typeof difficultySettings !== 'undefined' && difficultySettings['easy']) {
    timerElement.textContent = difficultySettings['easy'].time_limit;
  } else {
    timerElement.textContent = 30; // 預設值
  }
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
  pauseBGM(); 
  
  sendScoreToServer(score, level, success, isManualExit); 
  
  showEndModal(success, score, level);
}

// **核心修改：更新結束畫面的顯示方式**
function showEndModal(success, finalScore, level) {
  const modal = document.getElementById('result-modal');
  const title = document.getElementById('result-title');
  const difficultyText = document.getElementById('result-difficulty');
  const gameScoreText = document.getElementById('result-game-score');
  const playTimeText = document.getElementById('result-play-time');
  const gainedScoreText = document.getElementById('result-gained-score');
  const finalMessageText = document.getElementById('result-final-message');

  // 根據成功或失敗設定標題和圖示
  if (success) {
    title.innerHTML = '🎉 恭喜破關';
  } else {
    title.innerHTML = '⏰ 遊戲失敗';
  }

  difficultyText.textContent = '難度：' + (level === 3 ? '簡單' : level === 4 ? '普通' : '困難');
  gameScoreText.textContent = '遊戲分數：' + finalScore;
  
  // 計算總遊戲時間（秒）- 從遊戲開始到結束
  const totalPlayTime = gameStartTime > 0 ? Math.round((Date.now() - gameStartTime) / 1000) : 0;
  playTimeText.textContent = '遊戲時間：' + totalPlayTime + '秒';
  
  const rewardScore = success ? 20 : 0;
  gainedScoreText.textContent = '獲得分數：+' + rewardScore;
  
  finalMessageText.textContent = success ? '成功達成目標！' : '未達成目標分數！';

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
    messageDiv.textContent = '已暫停，犯人躲起來囉，請按繼續遊戲抓住他!';
    
    pauseBGM();

    pauseBtn.textContent = '繼續遊戲';
    pauseBtn.classList.remove('pause-btn');
    pauseBtn.classList.add('resume-btn');
    gameState = 'paused';

  } else {
    gameInterval = setInterval(updateTimer, 1000);
    holes.forEach(hole => {
      hole.addEventListener('click', checkPlayerHit);
    });
    messageDiv.textContent = '換你出馬抓住犯人囉！';
    
    playBGM(); 

    pauseBtn.textContent = '暫停遊戲';
    pauseBtn.classList.remove('resume-btn');
    pauseBtn.classList.add('pause-btn');
    gameState = 'player_turn';
  }
}

// **核心修改：移除結束遊戲前的確認步驟**
document.getElementById('end-btn').addEventListener('click', () => {
  const success = score >= passScore;
  endGame(success, true); // 直接結束遊戲
});

document.getElementById('restart-btn').addEventListener('click', () => {
  clearInterval(gameInterval);
  resetGame();
  document.getElementById('result-modal').style.display = 'none';
  difficultyModal.style.display = 'flex';
});

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
  
  video.src = 'gd/prisoner1.mp4'; 
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
  
  video.src = 'gd/prisoner2.mp4'; 
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
  
  video.src = 'gd/prisoner1.mp4'; 
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
  
  // 計算總遊戲時間（秒）- 從遊戲開始到結束
  const actualPlayTime = gameStartTime > 0 ? Math.round((Date.now() - gameStartTime) / 1000) : 0;
  console.log('遊戲時間計算:', { gameStartTime, currentTime: Date.now(), actualPlayTime });
  
  const data = {
    member_id: memberId,
    game_type: "記憶力",
    game_id: 6,
    difficulty: difficultyText,
        score: score, 
        play_time: actualPlayTime,
        is_passed: is_passed,
        is_manual_exit: isManualExit,
  };

  fetch('api/game_result.php', {
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
      } else {
        console.error("❌ 儲存失敗：", result.message);
      }
    })
    .catch(err => {
      console.error("❌ 發送錯誤：", err);
    });
}
