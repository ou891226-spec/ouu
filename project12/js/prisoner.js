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
  const finalScore = score; // 總是保存實際獲得的分數
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
  message.textContent = success ? '得分：' + score : '未在時間內達成分數';

  modal.style.display = 'flex';
}

pauseBtn.addEventListener('click', () => {
  if (!isPaused) {
    clearInterval(gameInterval);
    holes.forEach(hole => hole.removeEventListener('click', checkPlayerHit));
    messageDiv.textContent = '已暫停，請按繼續遊戲';
    pauseBtn.textContent = '繼續遊戲';
    isPaused = true;
  } else {
    gameInterval = setInterval(updateTimer, 1000);
    playerTurn();
    messageDiv.textContent = '';
    pauseBtn.textContent = '暫停遊戲';
    isPaused = false;
  }
});

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
});

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
  const difficultyText = level === 3 ? '簡單' : level === 4 ? '普通' : '困難';

  const data = {
    member_id: memberId,
    difficulty: difficultyText,
    score: score,
    play_time: gameTime - timeLeft,
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
        // 清除成就快取
        if (typeof clearAchievementsCache === 'function') {
          clearAchievementsCache();
        }
        // 立即刷新分數顯示
        if (typeof fetchUserScore === 'function') {
          fetchUserScore();
        }
      } else {
        console.error("❌ 儲存失敗：", result.message);
      }
    })
    .catch(err => {
      console.error("❌ 發送錯誤：", err);
    });
}