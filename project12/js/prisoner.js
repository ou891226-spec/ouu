const holes = document.querySelectorAll('.hole');
const scoreElement = document.getElementById('score');
const timerElement = document.getElementById('timer');
const startBtn = document.getElementById('start-btn');
const difficultyModal = document.getElementById('difficulty-modal');
const difficultyOptions = document.querySelectorAll('.difficulty-option');
const messageDiv = document.getElementById('message');
const pauseBtn = document.getElementById('pause-btn');

let score = 0;
let timeLeft = 60;
let sequence = [];
let playerSequence = [];
let gameInterval;
let level = 3;
let passScore = 0;
let gameTime = 60;
let isPaused = false;

// 👉 如果有登入功能，請確認這個 input 存在並含有會員 ID
const memberIdInput = document.getElementById('member-id'); 
const memberId = memberIdInput ? parseInt(memberIdInput.value) : 1;

document.getElementById('back-btn').addEventListener('click', () => {
  window.location.href = 'index.php';
});


startBtn.addEventListener('click', startGame);

function startGame() {
  if (level === 3) {
    gameTime = 80;
    passScore = 20;
  } else if (level === 4) {
    gameTime = 80;
    passScore = 10;
  } else if (level === 5) {
    gameTime = 120;
    passScore = 20;
  }

  resetGame();
  timeLeft = gameTime;
  timerElement.textContent = timeLeft;
  startBtn.style.display = 'none';
  gameInterval = setInterval(updateTimer, 1000);
  nextRound();
}

function resetGame() {
  score = 0;
  sequence = [];
  playerSequence = [];
  scoreElement.textContent = score;
  holes.forEach(hole => hole.classList.remove('active'));
  messageDiv.textContent = '';
}

function updateTimer() {
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
  let i = 0;
  messageDiv.textContent = '';
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
  holes.forEach(hole => {
    hole.addEventListener('click', checkPlayerHit);
  });
}

function checkPlayerHit(event) {
  const hole = event.currentTarget;
  const holeIndex = Array.from(holes).indexOf(hole);

  // 如果已經點過就跳過
  if (playerSequence.includes(holeIndex)) return;

  playerSequence.push(holeIndex);

  // 顯示警察圖片
  const police = hole.querySelector('.police');
  if (police) police.style.display = 'block';

  if (playerSequence.length === sequence.length) {
    holes.forEach(h => h.removeEventListener('click', checkPlayerHit));
    checkSequence();
  }
}



function checkSequence() {
  messageDiv.textContent = '';
  let isCorrect = sequence.every((val, index) => val === playerSequence[index]);
  if (isCorrect) {
    score += 2;
    scoreElement.textContent = score;
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

function endGame(success) {
  holes.forEach(hole => hole.removeEventListener('click', checkPlayerHit));
  clearInterval(gameInterval);
  
  // 根據是否過關和難度計算固定獎勵分數
  let finalScore = 0;
  if (success) {
    if (level === 3) finalScore = 20; // 簡單
    else if (level === 4) finalScore = 50; // 普通
    else if (level === 5) finalScore = 100; // 困難
  }
  // 如果沒過關，finalScore 保持為 0
  
  sendScoreToServer(finalScore, level);
  showEndModal(success, finalScore, level);
}

function showEndModal(success, score, level) {
  const modal = document.getElementById('result-modal');
  const title = document.getElementById('result-title');
  const difficulty = document.getElementById('result-difficulty');
  const message = document.getElementById('result-score');

  title.textContent = success ? '恭喜破關' : '遊戲失敗';
  difficulty.textContent = '難度：' + (level === 3 ? '簡單' : level === 4 ? '普通' : '困難');
  
  // 根據難度顯示固定分數
  let fixedScore = 0;
  if (level === 3) fixedScore = 20; // 簡單
  else if (level === 4) fixedScore = 50; // 普通
  else if (level === 5) fixedScore = 100; // 困難
  
  message.innerHTML = success ? '得分：' + fixedScore + '<br>過關分數：+' + fixedScore : '未在時間內達成分數';

  modal.style.display = 'flex';
}

pauseBtn.addEventListener('click', togglePause);
function togglePause() {
  isPaused = !isPaused; // 切換暫停狀態

  if (isPaused) {
    // 遊戲暫停時的邏輯
    clearInterval(gameInterval);
    holes.forEach(hole => hole.removeEventListener('click', checkPlayerHit));
    messageDiv.textContent = '已暫停，請按繼續遊戲';

    // 暫停按鈕的樣式切換
    pauseBtn.textContent = '繼續遊戲';
    pauseBtn.classList.remove('pause-btn');
    pauseBtn.classList.add('resume-btn');

  } else {
    // 遊戲恢復時的邏輯
    gameInterval = setInterval(updateTimer, 1000);
    playerTurn();
    messageDiv.textContent = '';

    // 繼續按鈕的樣式切換
    pauseBtn.textContent = '暫停遊戲';
    pauseBtn.classList.remove('resume-btn');
    pauseBtn.classList.add('pause-btn');
  }
}

document.getElementById('end-btn').addEventListener('click', () => {
  clearInterval(gameInterval);
  holes.forEach(hole => hole.removeEventListener('click', checkPlayerHit));
  endGame(false);
});

document.getElementById('restart-btn').addEventListener('click', () => {
  clearInterval(gameInterval);         // 停止計時
  resetGame();                         // 重設遊戲內容
  document.getElementById('result-modal').style.display = 'none'; // 關掉結果彈窗
  difficultyModal.style.display = 'flex'; // 再次顯示難度選擇彈窗
});


document.getElementById('info-btn').addEventListener('click', () => {
  document.getElementById('info-modal').style.display = 'flex';
  // 初始化犯人視頻播放邏輯
  initPrisonerVideoPlayback();
});

// 初始化犯人視頻播放邏輯
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
  
  // 設置第一個視頻
  video.src = 'gd/prisoner1.mp4';
  instructionText.textContent = '記住犯人出現的順序，按順序點擊洞';
  stepIndicator.textContent = '步驟 1/2';
  
  // 設置當前視頻標記
  video.setAttribute('data-current-video', 'prisoner1');
  
  // 顯示下一步按鈕，隱藏上一步按鈕
  nextStepBtn.style.display = 'block';
  prevStepBtn.style.display = 'none';
  
  // 強制加載視頻
  video.load();
  
  // 添加下一步按鈕點擊事件
  const nextStepButton = document.getElementById('prisoner-next-step-button');
  if (nextStepButton) {
    nextStepButton.onclick = goToPrisonerNextStep;
    console.log('犯人下一步按鈕事件已綁定');
  }
}

// 前往犯人下一步
function goToPrisonerNextStep() {
  const video = document.getElementById('prisoner-current-video');
  const instructionText = document.getElementById('prisoner-instruction-text');
  const stepIndicator = document.getElementById('prisoner-step-indicator');
  const nextStepBtn = document.getElementById('prisoner-next-step-btn');
  const prevStepBtn = document.getElementById('prisoner-prev-step-btn');
  
  // 切換到第二個視頻
  video.src = 'gd/prisoner2.mp4';
  video.setAttribute('data-current-video', 'prisoner2');
  instructionText.innerHTML = '按順序點擊洞，答對 +2 分<br>時間內累積足夠分數過關！';
  stepIndicator.textContent = '步驟 2/2';
  
  // 隱藏下一步按鈕，顯示上一步按鈕
  nextStepBtn.style.display = 'none';
  prevStepBtn.style.display = 'block';
  
  // 加載並播放視頻
  video.load();
  video.play();
}

// 回到犯人上一步
function goToPrisonerPrevStep() {
  const video = document.getElementById('prisoner-current-video');
  const instructionText = document.getElementById('prisoner-instruction-text');
  const stepIndicator = document.getElementById('prisoner-step-indicator');
  const nextStepBtn = document.getElementById('prisoner-next-step-btn');
  const prevStepBtn = document.getElementById('prisoner-prev-step-btn');
  
  // 切換到第一個視頻
  video.src = 'gd/prisoner1.mp4';
  video.setAttribute('data-current-video', 'prisoner1');
  instructionText.textContent = '記住犯人出現的順序，按順序點擊洞';
  stepIndicator.textContent = '步驟 1/2';
  
  // 顯示下一步按鈕，隱藏上一步按鈕
  nextStepBtn.style.display = 'block';
  prevStepBtn.style.display = 'none';
  
  // 加載並播放視頻
  video.load();
  video.play();
}

// 設為全局可訪問
window.goToPrisonerNextStep = goToPrisonerNextStep;
window.goToPrisonerPrevStep = goToPrisonerPrevStep;

// 關閉說明視窗的函數
function closeInfoModal() {
  document.getElementById('info-modal').style.display = 'none';
}

// 綁定關閉按鈕事件
document.addEventListener('DOMContentLoaded', () => {
  const closeBtn = document.querySelector('.close-btn');
  if (closeBtn) {
    closeBtn.addEventListener('click', closeInfoModal);
  }
});

function sendScoreToServer(score, level) {
  // 將 level 轉換為英文難度名稱
  const difficultyText = level === 3 ? 'easy' : level === 4 ? 'normal' : 'hard';

  const data = {
    member_id: memberId,
    difficulty: difficultyText,
    score: score,
    play_time: gameTime - timeLeft,
    is_passed: score > 0, // 如果分數大於0表示過關
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
      } else {
        console.error("❌ 儲存失敗：", result.message);
      }
    })
    .catch(err => {
      console.error("❌ 發送錯誤：", err);
    });
}
