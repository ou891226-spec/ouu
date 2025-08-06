document.addEventListener('DOMContentLoaded', function() {
    // 新增 modal HTML
    const modalHtml = `
<div id="result-modal" style="display:none;position:fixed;z-index:10000;left:0;top:0;width:100vw;height:100vh;background:rgba(0,0,0,0.18);align-items:center;justify-content:center;">
  <div style="background:#fff;padding:32px 36px 32px 36px;border-radius:20px;text-align:center;box-shadow:0 8px 32px rgba(0,0,0,0.18);min-width:340px;max-width:90vw;">
    <h2 id="modal-title" style="font-size:2.2rem;font-weight:bold;margin-bottom:12px;">結果</h2>
    <div id="modal-content" style="font-size:1.2rem;margin-bottom:後;"></div>
    <button id="play-again-btn" style="background:#ff4d4f;color:#fff;font-size:1.1rem;font-weight:bold;padding:10px 32px;border:none;border-radius:10px;margin-right:18px;cursor:pointer;">再玩一次</button>
    <button id="back-home-btn" style="background:#22334a;color:#fff;font-size:1.1rem;font-weight:bold;padding:10px 32px;border:none;border-radius:10px;cursor:pointer;">返回主頁</button>
  </div>
</div>`;
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    const resultModal = document.getElementById('result-modal');
    const modalTitle = document.getElementById('modal-title');
    const modalContent = document.getElementById('modal-content');

    function showResultModal(pass, score, difficulty, pass_bounce) {
      resultModal.style.display = 'flex';
      if (pass) {
        modalTitle.textContent = '恭喜破關';
        modalContent.innerHTML = `難度：${difficulty === 'easy' ? '簡單' : difficulty === 'normal' ? '普通' : '困難'}<br>獲得分數：${pass_bounce}`;
      } else {
        modalTitle.textContent = '遊戲失敗';
        modalContent.innerHTML = `難度：${difficulty === 'easy' ? '簡單' : difficulty === 'normal' ? '普通' : '困難'}<br>未在時間內達成分數`;
      }
    }
    document.getElementById('play-again-btn').onclick = function() { location.reload(); };
    document.getElementById('back-home-btn').onclick = function() { window.location.href = 'index.php'; };

    // 主要流程重寫
    let currentQuestion = null;
    let correctAnswer = null;
    let displayTime = null;
    let timer = null;
    let paused = false;
    let timeLeft = 0;
    let questionShown = false;
    let gameStartTime = Date.now(); // 記錄遊戲開始時間
    const main = document.querySelector('.main-container');
    const difficulty = new URLSearchParams(window.location.search).get('difficulty');

    function updateTimeDisplay() {
        var timeLeftElement = document.getElementById('time-left');
        if (timeLeftElement) {
            timeLeftElement.textContent = timeLeft;
        }
    }

    function showQuestion() {
        document.getElementById('image-block').style.display = 'none';
        document.getElementById('timer-block').style.display = 'none';
        document.getElementById('question-block').style.display = 'block';
    }

    function startCountdown() {
        updateTimeDisplay();
        timer = setInterval(function() {
            if (!paused) {
                timeLeft--;
                updateTimeDisplay();
                if (timeLeft <= 0) {
                    clearInterval(timer);
                    showQuestion();
                }
            }
        }, 1000);
    }

    function loadQuestion(userAns = null) {
        // 準備 AJAX 請求
        const data = new URLSearchParams();
        data.append('ajax', '1');
        data.append('difficulty', difficulty);
        if (userAns !== null) {
            data.append('user_answer', userAns);
            data.append('correct_answer', correctAnswer);
        }
        
        // 如果是遊戲結束，傳送遊戲時間
        if (userAns !== null) {
            const gameTime = Math.floor((Date.now() - gameStartTime) / 1000); // 轉換為秒
            data.append('game_time', gameTime);
        }
        fetch('clue.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: data.toString()
        })
        .then(res => res.json())
        .then(res => {
            if (res.end) {
                showResultModal(res.pass, res.score, res.difficulty, res.pass_bounce);
                return;
            }
            // 顯示新題目
            currentQuestion = res.question;
            correctAnswer = currentQuestion.correct_answer_text;
            displayTime = currentQuestion.display_time;
            timeLeft = displayTime;
            questionShown = false;
            // 更新 DOM
            document.querySelector('.main-container h2').innerHTML = `請仔細觀察下方圖片，${displayTime}秒後將進行提問！`;
            document.getElementById('image-block').innerHTML = `<img src="${currentQuestion.image_path}" alt="題目圖片">`;
            document.querySelector('#question-block h3').textContent = currentQuestion.question_text; // 新增：同步題目文字
            document.getElementById('timer-block').style.display = '';
            document.getElementById('image-block').style.display = '';
            document.getElementById('question-block').style.display = 'none';
            document.getElementById('result-block').style.display = 'none';
            document.getElementById('time-left').textContent = displayTime;
            // 選項
            const opts = [currentQuestion.option_1, currentQuestion.option_2, currentQuestion.option_3, currentQuestion.option_4];
            const btns = document.querySelectorAll('.option-btn');
            btns.forEach((btn, i) => {
                btn.textContent = opts[i];
                btn.setAttribute('data-value', opts[i]);
            });
            startCountdown();
        });
    }

    // 初始載入不送答案
    loadQuestion();

    document.getElementById('pauseBtn').onclick = function() {
        paused = !paused;
        this.textContent = paused ? '繼續遊戲' : '暫停遊戲';
    };
    document.getElementById('endBtn').onclick = function() {
        if (confirm('確定要結束遊戲並返回主頁嗎？')) {
            window.location.href = 'index.php';
        }
    };
    document.getElementById('resetBtn').onclick = function() {
        if (confirm('確定要重新開始遊戲嗎？')) {
            location.reload();
        }
    };

    function showQuestionOnce() {
        if (!questionShown) {
            showQuestion();
            clearInterval(timer);
            questionShown = true;
        }
    }

    document.querySelectorAll('.option-btn').forEach(function(btn) {
        btn.onclick = function() {
            if (paused) return;
            var userAns = this.getAttribute('data-value');
            document.getElementById('question-block').style.display = 'none';
            document.getElementById('result-block').style.display = 'block';
            document.getElementById('correct-answer').textContent = correctAnswer;
            if (userAns === correctAnswer) {
                document.getElementById('result-msg').textContent = '答對了！';
                document.getElementById('result-msg').style.color = 'green';
            } else {
                document.getElementById('result-msg').textContent = '答錯了！';
                document.getElementById('result-msg').style.color = 'red';
            }
            showQuestionOnce();
            setTimeout(function(){
                loadQuestion(userAns);
            }, 1200);
        };
    });
}); 