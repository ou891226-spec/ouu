document.addEventListener('DOMContentLoaded', function() {
    var main = document.querySelector('.main-container');
    var displayTime = parseInt(main.getAttribute('data-display-time'), 10);
    var correctAnswer = main.getAttribute('data-correct-answer');
    var timer = null;
    var paused = false;
    var timeLeft = displayTime;
    var questionShown = false;

    var showQuestion = function() {
        document.getElementById('image-block').style.display = 'none';
        document.getElementById('question-block').style.display = 'block';
    };
    function startCountdown() {
        timer = setInterval(function() {
            if (!paused) {
                timeLeft--;
                if (timeLeft <= 0) {
                    clearInterval(timer);
                    showQuestion();
                }
            }
        }, 1000);
        setTimeout(function() {
            if (!paused) showQuestion();
        }, displayTime * 1000);
    }
    startCountdown();
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
            setTimeout(function(){ location.reload(); }, 3000);
        };
    });
}); 