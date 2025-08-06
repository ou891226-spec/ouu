const questionElement = document.getElementById('question');
const optionsContainer = document.getElementById('options-container');
const playerScoreDisplay = document.getElementById('player-score');
const opponentScoreDisplay = document.getElementById('opponent-score');
const popup = document.getElementById('popup');
const popupTitle = document.getElementById('popup-title');
const popupMessage = document.getElementById('popup-message');

let questions = [];
let currentQuestionIndex = 0;
let playerScore = 0;
let opponentScore = 0;
let gameTimer;
let timeLimit = 80;

const sampleQuestions = {
  easy: [
    {
      question: '阿嬤買了 2 顆高麗菜（每顆 20 元），總共多少錢？',
      options: ['30 元', '40 元', '50 元'],
      answer: '40 元'
    },
    {
      question: '1 斤青江菜 15 元，買 3 斤是多少？',
      options: ['30 元', '45 元', '60 元'],
      answer: '45 元'
    }
  ],
  normal: [
    {
      question: '阿嬤買了紅蘿蔔 2 條（每條 12 元）和洋蔥 1 顆（15 元），總共多少錢？',
      options: ['30 元', '39 元', '40 元'],
      answer: '39 元'
    }
  ],
  hard: [
    {
      question: '阿嬤有 100 元，買了大白菜（35 元）和玉米（28 元），還剩多少錢？',
      options: ['47 元', '37 元', '45 元'],
      answer: '37 元'
    }
  ]
};

function startGame(difficulty) {
  questions = sampleQuestions[difficulty];
  currentQuestionIndex = 0;
  playerScore = 0;
  opponentScore = 0;
  updateScores();

  if (difficulty === 'easy') timeLimit = 80;
  else if (difficulty === 'normal') timeLimit = 150;
  else timeLimit = 200;

  countdown(3, () => {
    showQuestion();
    gameTimer = setTimeout(endGame, timeLimit * 1000);
  });
}

function countdown(seconds, callback) {
  let counter = seconds;
  questionElement.textContent = `遊戲將在 ${counter} 秒後開始...`;
  const interval = setInterval(() => {
    counter--;
    if (counter <= 0) {
      clearInterval(interval);
      callback();
    } else {
      questionElement.textContent = `遊戲將在 ${counter} 秒後開始...`;
    }
  }, 1000);
}

function showQuestion() {
  const question = questions[currentQuestionIndex];
  if (!question) {
    endGame();
    return;
  }
  questionElement.textContent = question.question;
  optionsContainer.innerHTML = '';
  question.options.forEach(option => {
    const btn = document.createElement('button');
    btn.textContent = option;
    btn.onclick = () => checkAnswer(option);
    optionsContainer.appendChild(btn);
  });
}

function checkAnswer(selected) {
  const question = questions[currentQuestionIndex];
  if (selected === question.answer) {
    playerScore += 3;
    updateScores();
  }
  currentQuestionIndex++;
  showQuestion();
}

function updateScores() {
  playerScoreDisplay.textContent = playerScore;
  opponentScoreDisplay.textContent = opponentScore;
}

function endGame() {
  questionElement.textContent = '';
  optionsContainer.innerHTML = '';
  clearTimeout(gameTimer);

  let result = '';
  if (playerScore > opponentScore) result = '你贏了！';
  else if (playerScore < opponentScore) result = '你輸了！';
  else result = '平手！';

  popup.classList.remove('hidden');
  popupTitle.textContent = '遊戲結束';
  popupMessage.textContent = `你的分數：${playerScore} 分，對手分數：${opponentScore} 分\n${result}`;
}

function restartGame() {
  popup.classList.add('hidden');
  questionElement.textContent = '請選擇難度開始遊戲';
  optionsContainer.innerHTML = '';
  playerScoreDisplay.textContent = '0';
  opponentScoreDisplay.textContent = '0';
}

window.addEventListener('load', () => {
  const difficultyModal = document.getElementById('difficulty-modal');
  const helpModal = document.getElementById('help-modal');
  const helpBtn = document.querySelector('.help-btn');
  const closeBtn = document.querySelector('.close-btn');

  // 顯示難度選擇視窗
  difficultyModal.classList.remove('hidden');

  // 綁定難度按鈕
  document.querySelectorAll('.difficulty-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const difficulty = btn.dataset.difficulty;
      if (difficulty) {
        difficultyModal.classList.add('hidden');
        startGame(difficulty);
      }
    });
  });

  // 說明視窗打開
  if (helpBtn && helpModal) {
    helpBtn.addEventListener('click', () => {
      helpModal.classList.remove('hidden');
    });
  }

  // 說明視窗關閉
  if (closeBtn && helpModal) {
    closeBtn.addEventListener('click', () => {
      helpModal.classList.add('hidden');
    });
  }
});


