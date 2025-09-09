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

    // 防重复保存标志
    let hasSavedRecord = false;
    let saveRequestInProgress = false; // 请求进行中标志
    let gameSessionId = Date.now(); // 游戏会话ID，用于标识单次游戏

    // 保存游戏记录的函数
    function saveGameRecord(pass, score, pass_bounce) {
        // 防止重复保存
        if (hasSavedRecord) {
            console.log('游戏记录已经保存过，跳过重复保存', {gameSessionId});
            return;
        }
        
        // 防止请求进行中
        if (saveRequestInProgress) {
            console.log('保存请求正在进行中，跳过重复请求', {gameSessionId});
            return;
        }
        
        hasSavedRecord = true;
        saveRequestInProgress = true;
        console.log('开始保存游戏记录...', {pass, score, pass_bounce, gameSessionId});
        
        const gameTime = Math.floor((Date.now() - gameStartTime) / 1000);
        const data = new URLSearchParams();
        data.append('ajax', '1');
        data.append('difficulty', difficulty);
        data.append('game_time', gameTime);
        data.append('force_end', '1');
        data.append('final_score', score);
        data.append('session_id', gameSessionId); // 添加会话ID
        
        // 添加时间戳防止缓存
        data.append('timestamp', Date.now());
        
        fetch('clue.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: data.toString()
        })
        .then(response => {
            console.log('服务器响应状态:', response.status, {gameSessionId});
            return response.json();
        })
        .then(result => {
            console.log('游戏记录保存结果:', result, {gameSessionId});
            saveRequestInProgress = false; // 请求完成
        })
        .catch(err => {
            console.error('儲存遊戲記錄失敗:', err, {gameSessionId});
            // 保存失败时重置标志，允许重试
            hasSavedRecord = false;
            saveRequestInProgress = false;
        });
    }

    function showResultModal(pass, score, difficulty, pass_bounce) {
      resultModal.style.display = 'flex';
      if (pass) {
        modalTitle.textContent = '恭喜破關';
        modalContent.innerHTML = `難度：${difficulty === 'easy' ? '簡單' : difficulty === 'normal' ? '普通' : '困難'}<br>過關分數：+${pass_bounce}`;
      } else {
        modalTitle.textContent = '遊戲失敗';
        modalContent.innerHTML = `難度：${difficulty === 'easy' ? '簡單' : difficulty === 'normal' ? '普通' : '困難'}<br>未在時間內達成分數`;
      }
      
      // 保存游戏记录（防重复机制在saveGameRecord内部）
      saveGameRecord(pass, score, pass_bounce);
    }
    document.getElementById('play-again-btn').onclick = function() { location.reload(); };
    document.getElementById('back-home-btn').onclick = function() { 
        // 智能返回：回到上一頁，如果沒有上一頁則回到首頁
        if (history.length > 1 && document.referrer && document.referrer !== window.location.href) {
            history.back();
        } else {
            window.location.href = 'index.php';
        }
    };

    // 主要流程重寫
    let currentQuestion = null;
    let correctAnswer = null;
    let displayTime = null;
    let timer = null;
    let paused = false;
    let timeLeft = 0;
    let questionShown = false;
    let gameStartTime = Date.now(); // 記錄遊戲開始時間
    let clueCorrect = 0; // 追蹤答對的題數
    let clueTotal = 0; // 追蹤總題數
    let questionTimeout = null; // 新增：問題超時計時器
    const main = document.querySelector('.main-container');
    const difficulty = new URLSearchParams(window.location.search).get('difficulty');
    
    // 將變數設為全局，供結束遊戲按鈕使用
    window.clueCorrect = clueCorrect;
    window.clueTotal = clueTotal;

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
                    
                    // 新增：設置問題超時計時器（30秒後自動結束）
                    questionTimeout = setTimeout(function() {
                        if (!questionShown) {
                            // 超時未回答，記錄為答錯
                            handleTimeoutAnswer();
                        }
                    }, 30000); // 30秒超時
                }
            }
        }, 1000);
    }

    // 新增：處理超時未回答的情況
    function handleTimeoutAnswer() {
        if (questionShown) return; // 已經回答過了
        
        questionShown = true;
        clearTimeout(questionTimeout);
        
        // 顯示超時結果
        document.getElementById('question-block').style.display = 'none';
        document.getElementById('result-block').style.display = 'block';
        document.getElementById('correct-answer').textContent = correctAnswer;
        document.getElementById('result-msg').textContent = '時間到！';
        document.getElementById('result-msg').style.color = 'red';
        
        // 不增加答對數，只增加總題數
        clueTotal++;
        
        // 更新全局變數
        window.clueCorrect = clueCorrect;
        window.clueTotal = clueTotal;
        
        // 延遲後載入下一題
        setTimeout(function(){
            loadQuestion('timeout'); // 傳送特殊標記表示超時
        }, 1200);
    }

    function loadQuestion(userAns = null) {
        // 準備 AJAX 請求
        const data = new URLSearchParams();
        data.append('ajax', '1');
        data.append('difficulty', difficulty);
        if (userAns !== null && userAns !== 'timeout') {
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
            
            // 檢查是否有錯誤
            if (res.error) {
                console.error('服務器錯誤:', res.error);
                return;
            }
            
            // 檢查題目是否存在
            if (!res.question) {
                console.error('沒有收到題目數據');
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
        if (paused) {
            this.textContent = '繼續遊戲';
            this.style.setProperty('background', '#4CAF50', 'important'); // 綠色
        } else {
            this.textContent = '暫停遊戲';
            this.style.setProperty('background', '#FF8C00', 'important'); // 橘色
        }
    };
    document.getElementById('endBtn').onclick = function() {
        // 直接結束遊戲，不顯示確認視窗
        // 計算當前進度和分數
        const currentScore = window.clueCorrect || 0;
        const currentTotal = window.clueTotal || 0;
        
        // 判斷是否過關（至少答對3題）
        const pass = currentScore >= 3;
        
        // 根據難度設定獎勵分數
        let pass_bounce = 0;
        if (pass) {
            if (difficulty === 'easy') pass_bounce = 20;
            else if (difficulty === 'normal') pass_bounce = 50;
            else if (difficulty === 'hard') pass_bounce = 100;
        }
        
        // 顯示結果（saveGameRecord會在showResultModal中自動調用）
        showResultModal(pass, currentScore, difficulty, pass_bounce);
    };
    document.getElementById('resetBtn').onclick = function() {
        // 智能返回：回到上一頁，如果沒有上一頁則回到線索遊戲難度選擇頁面
        if (history.length > 1 && document.referrer && document.referrer !== window.location.href) {
            history.back();
        } else {
            window.location.href = 'clue.php';
        }
    };

    function showQuestionOnce() {
        if (!questionShown) {
            showQuestion();
            clearInterval(timer);
            clearTimeout(questionTimeout); // 清除超時計時器
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
                clueCorrect++; // 答對題數加1
            } else {
                document.getElementById('result-msg').textContent = '答錯了！';
                document.getElementById('result-msg').style.color = 'red';
            }
            clueTotal++; // 總題數加1
            
            // 更新全局變數
            window.clueCorrect = clueCorrect;
            window.clueTotal = clueTotal;
            
            showQuestionOnce();
            setTimeout(function(){
                loadQuestion(userAns);
            }, 1200);
        };
    });
}); 