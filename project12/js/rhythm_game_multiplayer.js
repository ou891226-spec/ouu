// == DOM Elements ==
const startBtn = document.getElementById("start-btn");
const restartBtn = document.getElementById("restart-btn");
const endBtn = document.getElementById("end-btn");
const difficultySelect = document.getElementById("difficulty");
const infoBtn = document.getElementById('info-btn');
const infoModal = document.getElementById('info-modal');
const resultModal = document.getElementById('result-modal');
const resultTitle = document.getElementById('result-title');
const resultDifficulty = document.getElementById('result-difficulty');
const resultScore = document.getElementById('result-score');
const difficultyModal = document.getElementById('difficulty-modal');

// 邀請相關元素
const gameContainer = document.getElementById('game-container');
const friendInviteModal = document.getElementById('friend-invite-modal');
const waitingModal = document.getElementById('waiting-modal');
const receivedInvitationModal = document.getElementById('received-invitation-modal');
const invitationExpiredModal = document.getElementById('invitation-expired-modal');
const quitGameModal = document.getElementById('quit-game-modal');
const friendRejectModal = document.getElementById('friend-reject-modal');

const player1 = {
  track: document.getElementById("noteTrack-top"),
  hitZone: document.getElementById("hitZone-top"),
  scoreDisplay: document.getElementById("score-top"),
  highScoreDisplay: document.getElementById("highScore-top"),
  timerDisplay: document.getElementById("timer-top"),
  bat: document.getElementById("bat-top"),
  score: 0,
  highScore: 0,
  interval: null
};

const player2 = {
  track: document.getElementById("noteTrack-bottom"),
  hitZone: document.getElementById("hitZone-bottom"),
  scoreDisplay: document.getElementById("score-bottom"),
  highScoreDisplay: document.getElementById("highScore-bottom"),
  timerDisplay: document.getElementById("timer-bottom"),
  bat: document.getElementById("bat-bottom"),
  score: 0,
  highScore: 0,
  interval: null
};

let gameTime = 60;
let timeLeft = gameTime;
let gameInterval = null;
let difficulty = "easy";
let gameStarted = false;
let gamePaused = false;

// 邀請相關變數
let currentInvitation = null;
let invitationCheckInterval = null;
let isInviter = false; // 追蹤是否為邀請人

// 頁面載入時檢查是否有待處理的邀請
window.addEventListener("DOMContentLoaded", () => {
  // 檢查URL參數是否有邀請ID
  const urlParams = new URLSearchParams(window.location.search);
  const invitationId = urlParams.get('invitation');
  
  if (invitationId) {
    // 如果有邀請參數，直接進入對戰模式
    console.log('檢測到邀請參數:', invitationId);
    enterBattleMode(invitationId);
  } else {
    // 沒有邀請參數，顯示邀請畫面
    checkPendingInvitations();
    showFriendInviteModal();
  }
});

// 進入對戰模式
function enterBattleMode(invitationId) {
  console.log('進入對戰模式，邀請ID:', invitationId);
  
  // 隱藏所有邀請相關視窗
  if (friendInviteModal) {
    friendInviteModal.style.setProperty('display', 'none', 'important');
    friendInviteModal.classList.remove('show');
    console.log('已隱藏邀請視窗');
  }
  
  if (receivedInvitationModal) {
    receivedInvitationModal.style.setProperty('display', 'none', 'important');
    receivedInvitationModal.classList.remove('show');
    console.log('已隱藏收到邀請視窗');
  }
  
  // 保存邀請信息
  currentInvitation = {
    invitationId: invitationId
  };
  
  // 檢查是否為邀請人
  if (isInviter) {
    // 邀請人顯示難度選擇
    if (difficultyModal) {
      difficultyModal.style.setProperty('display', 'flex', 'important');
      difficultyModal.classList.add('show');
      console.log('邀請人：顯示難度選擇視窗');
    }
  } else {
    // 被邀請人顯示等待畫面
    if (waitingModal) {
      waitingModal.style.setProperty('display', 'flex', 'important');
      waitingModal.classList.add('show');
      console.log('被邀請人：顯示等待視窗');
    }
    
    // 被邀請人開始檢查遊戲狀態
    if (window.gameStateInterval) {
      clearInterval(window.gameStateInterval);
    }
    window.gameStateInterval = setInterval(checkGameState, 2000);
    console.log('被邀請人：開始檢查遊戲狀態');
  }
  
  console.log('對戰模式設置完成');
}

// 檢查待處理的邀請
function checkPendingInvitations() {
  fetch('game-invitation-api.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      action: 'get_pending_invitations'
    })
  })
  .then(response => response.json())
  .then(data => {
    if (data.success && data.invitations && data.invitations.length > 0) {
      // 找到最新的待處理邀請
      const latestInvitation = data.invitations[0];
      if (latestInvitation.status === 'pending') {
        showReceivedInvitationModal(latestInvitation);
      }
    }
  })
  .catch(error => {
    console.error('檢查待處理邀請錯誤:', error);
  });
}

// 顯示收到邀請視窗
function showReceivedInvitationModal(invitation) {
  const inviterName = document.getElementById('inviter-name');
  if (inviterName) {
    inviterName.textContent = invitation.from_user_name;
  }
  
  if (receivedInvitationModal) {
    receivedInvitationModal.style.display = 'flex';
    receivedInvitationModal.classList.add('show');
  }
  
  // 保存當前邀請信息
  currentInvitation = {
    invitationId: invitation.invitation_id,
    friendId: invitation.from_user_id,
    friendName: invitation.from_user_name
  };
}

// 顯示好友邀請視窗
function showFriendInviteModal() {
  if (friendInviteModal) {
    friendInviteModal.style.display = 'flex';
    friendInviteModal.classList.add('show');
  }
}

// 從難度選擇視窗返回
function backFromDifficultyModal() {
  // 顯示確認返回視窗
  showReturnConfirmModal();
}

// 處理返回按鈕
function handleBackButton() {
  // 如果正在進行對戰，顯示確認返回視窗
  if (currentInvitation || gameStarted) {
    showReturnConfirmModal();
  } else {
    window.location.href = 'game-category.php';
  }
}

// 顯示返回確認視窗
function showReturnConfirmModal() {
  const returnConfirmModal = document.getElementById('return-confirm-modal');
  if (returnConfirmModal) {
    returnConfirmModal.style.setProperty('display', 'flex', 'important');
    returnConfirmModal.classList.add('show');
  }
}

// 隱藏返回確認視窗
function hideReturnConfirmModal() {
  const returnConfirmModal = document.getElementById('return-confirm-modal');
  if (returnConfirmModal) {
    returnConfirmModal.style.setProperty('display', 'none', 'important');
    returnConfirmModal.classList.remove('show');
  }
}

// 確認返回
function confirmReturn() {
  // 如果有進行中的邀請，取消邀請
  if (currentInvitation) {
    cancelInvitation();
  }
  
  // 如果有進行中的遊戲，通知對方您已退出
  if (gameStarted && currentInvitation) {
    fetch('game-sync-api.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        action: 'player_quit',
        invitation_id: currentInvitation.invitationId,
        player_id: document.getElementById('member-id').value
      })
    })
    .then(response => response.json())
    .then(data => {
      console.log('退出通知結果:', data);
    })
    .catch(error => {
      console.error('退出通知錯誤:', error);
    });
  }
  
  // 隱藏難度選擇視窗
  if (difficultyModal) {
    difficultyModal.style.display = 'none';
    difficultyModal.classList.remove('show');
  }
  
  hideReturnConfirmModal();
  window.location.href = 'game-category.php';
}

// 取消返回
function cancelReturn() {
  hideReturnConfirmModal();
}

// 顯示說明
function showHelp() {
  if (infoModal) {
    infoModal.style.setProperty('display', 'flex', 'important');
    infoModal.classList.add('show');
    console.log('顯示說明視窗');
  } else {
    console.error('找不到 infoModal 元素');
  }
}

// 關閉說明視窗
function closeInfoModal() {
  if (infoModal) {
    infoModal.style.display = 'none';
    infoModal.classList.remove('show');
  }
}

// 邀請好友
function inviteFriend(friendId, friendName) {
  // 設置為邀請人
  isInviter = true;
  
  // 發送邀請API請求
  fetch('game-invitation-api.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      action: 'send_invitation',
      to_user_id: friendId,
      game_type: 'rhythm_game_multiplayer'
    })
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      currentInvitation = {
        invitationId: data.invitation_id,
        friendId: friendId,
        friendName: friendName,
        timestamp: Date.now()
      };

      // 隱藏好友邀請視窗
      if (friendInviteModal) {
        friendInviteModal.style.display = 'none';
        friendInviteModal.classList.remove('show');
      }

              // 顯示等待視窗
        if (waitingModal) {
          waitingModal.style.display = 'flex';
          waitingModal.classList.add('show');
          
          // 更新等待視窗的內容（邀請人發送邀請時）
          const waitingTitle = document.getElementById('waiting-title');
          const waitingMessage = document.getElementById('waiting-message');
          if (waitingTitle) {
            waitingTitle.textContent = '等待好友回應';
          }
          if (waitingMessage) {
            waitingMessage.textContent = `正在等待 ${friendName} 接受邀請...`;
          }
        }

      // 開始檢查邀請狀態
      checkInvitationStatus();
    } else {
      alert('邀請發送失敗: ' + data.message);
    }
  })
  .catch(error => {
    console.error('邀請發送錯誤:', error);
    alert('邀請發送錯誤，請稍後再試');
  });
}

// 檢查邀請狀態
function checkInvitationStatus() {
  invitationCheckInterval = setInterval(() => {
    if (currentInvitation && currentInvitation.invitationId) {
      // 發送API請求檢查邀請狀態
      fetch('game-invitation-api.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          action: 'check_invitation',
          invitation_id: currentInvitation.invitationId
        })
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          switch (data.status) {
            case 'accepted':
              clearInterval(invitationCheckInterval);
              console.log('邀請被接受');
              
              if (isInviter) {
                // 邀請人被接受，顯示難度選擇
                if (difficultyModal) {
                  difficultyModal.style.setProperty('display', 'flex', 'important');
                  difficultyModal.classList.add('show');
                  console.log('邀請人：顯示難度選擇視窗');
                }
                if (waitingModal) {
                  waitingModal.style.display = 'none';
                  waitingModal.classList.remove('show');
                  console.log('邀請人：隱藏等待視窗');
                }
              } else {
                // 被邀請人被接受，顯示等待視窗
                if (waitingModal) {
                  waitingModal.style.setProperty('display', 'flex', 'important');
                  waitingModal.classList.add('show');
                  
                  // 更新等待視窗的內容
                  const waitingTitle = document.getElementById('waiting-title');
                  const waitingMessage = document.getElementById('waiting-message');
                  if (waitingTitle) {
                    waitingTitle.textContent = '等待遊戲設定';
                  }
                  if (waitingMessage) {
                    waitingMessage.textContent = '正在等待邀請者設定遊戲...';
                  }
                  
                  console.log('被邀請人：顯示等待視窗');
                }
                if (receivedInvitationModal) {
                  receivedInvitationModal.style.display = 'none';
                  receivedInvitationModal.classList.remove('show');
                  console.log('被邀請人：隱藏邀請視窗');
                }
              }
              break;
              
            case 'rejected':
              clearInterval(invitationCheckInterval);
              console.log('邀請被拒絕，顯示拒絕視窗');
              if (waitingModal) {
                waitingModal.style.display = 'none';
                waitingModal.classList.remove('show');
              }
              showFriendRejectModal();
              break;
              
            case 'expired':
              clearInterval(invitationCheckInterval);
              showInvitationExpired();
              break;
              
            case 'cancelled':
              clearInterval(invitationCheckInterval);
              showFriendInviteModal();
              break;
              
            case 'pending':
              // 繼續等待
              break;
          }
        }
      })
      .catch(error => {
        console.error('檢查邀請狀態錯誤:', error);
      });
    }
  }, 3000); // 每3秒檢查一次
}

// 取消邀請
function cancelInvitation() {
  if (currentInvitation && currentInvitation.invitationId) {
    // 發送取消邀請API請求
    fetch('game-invitation-api.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        action: 'cancel_invitation',
        invitation_id: currentInvitation.invitationId
      })
    })
    .then(response => response.json())
    .then(data => {
      console.log('取消邀請結果:', data);
    })
    .catch(error => {
      console.error('取消邀請錯誤:', error);
    });
  }
  
  if (invitationCheckInterval) {
    clearInterval(invitationCheckInterval);
  }
  
  if (waitingModal) {
    waitingModal.style.display = 'none';
    waitingModal.classList.remove('show');
  }
  
  showFriendInviteModal();
}

// 顯示邀請過期
function showInvitationExpired() {
  if (waitingModal) {
    waitingModal.style.display = 'none';
    waitingModal.classList.remove('show');
  }
  
  if (invitationExpiredModal) {
    invitationExpiredModal.style.display = 'flex';
    invitationExpiredModal.classList.add('show');
  }
}

// 隱藏邀請過期視窗
function hideExpiredModal() {
  if (invitationExpiredModal) {
    invitationExpiredModal.style.display = 'none';
    invitationExpiredModal.classList.remove('show');
  }
  showFriendInviteModal();
}

// 接受邀請
function acceptInvitation() {
  if (currentInvitation && currentInvitation.invitationId) {
    // 設置為被邀請人
    isInviter = false;
    
    fetch('game-invitation-api.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        action: 'accept_invitation',
        invitation_id: currentInvitation.invitationId
      })
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        console.log('邀請已接受');
        
        if (receivedInvitationModal) {
          receivedInvitationModal.style.display = 'none';
          receivedInvitationModal.classList.remove('show');
        }
        
        // 顯示等待視窗（等待邀請人選擇難度）
        if (waitingModal) {
          waitingModal.style.setProperty('display', 'flex', 'important');
          waitingModal.classList.add('show');
          
          // 更新等待視窗的內容
          const waitingTitle = document.getElementById('waiting-title');
          const waitingMessage = document.getElementById('waiting-message');
          if (waitingTitle) {
            waitingTitle.textContent = '等待遊戲設定';
          }
          if (waitingMessage) {
            waitingMessage.textContent = '正在等待邀請者設定遊戲...';
          }
        }
        
        // 開始檢查遊戲狀態
        if (window.gameStateInterval) {
          clearInterval(window.gameStateInterval);
        }
        window.gameStateInterval = setInterval(checkGameState, 2000); // 每2秒檢查一次
      } else {
        console.error('接受邀請失敗:', data.message);
        alert('接受邀請失敗: ' + data.message);
      }
    })
    .catch(error => {
      console.error('接受邀請錯誤:', error);
      alert('接受邀請錯誤，請稍後再試');
    });
  }
}

// 拒絕邀請
function rejectInvitation() {
  if (currentInvitation && currentInvitation.invitationId) {
    fetch('game-invitation-api.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        action: 'reject_invitation',
        invitation_id: currentInvitation.invitationId
      })
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        console.log('邀請已拒絕');
        
        if (receivedInvitationModal) {
          receivedInvitationModal.style.display = 'none';
          receivedInvitationModal.classList.remove('show');
        }
        showFriendInviteModal();
      } else {
        console.error('拒絕邀請失敗:', data.message);
        alert('拒絕邀請失敗: ' + data.message);
      }
    })
    .catch(error => {
      console.error('拒絕邀請錯誤:', error);
      alert('拒絕邀請錯誤，請稍後再試');
    });
  }
}

// 顯示退出對戰確認視窗
function showQuitModal() {
  if (quitGameModal) {
    quitGameModal.style.display = 'flex';
    quitGameModal.classList.add('show');
  }
}

// 隱藏退出對戰視窗
function hideQuitModal() {
  if (quitGameModal) {
    quitGameModal.style.display = 'none';
    quitGameModal.classList.remove('show');
  }
}

// 確認退出對戰
function confirmQuitGame() {
  endGame();
  hideQuitModal();
  showFriendInviteModal();
}

// 顯示好友拒絕視窗
function showFriendRejectModal() {
  console.log('showFriendRejectModal 被調用');
  if (friendRejectModal) {
    console.log('找到 friendRejectModal 元素');
    friendRejectModal.style.display = 'flex';
    friendRejectModal.classList.add('show');
    console.log('拒絕視窗已顯示');
  } else {
    console.log('找不到 friendRejectModal 元素');
  }
}

// 隱藏好友拒絕視窗
function hideRejectModal() {
  if (friendRejectModal) {
    friendRejectModal.style.display = 'none';
    friendRejectModal.classList.remove('show');
  }
  showFriendInviteModal();
}

function resetPlayer(player) {
  player.score = 0;
  player.scoreDisplay.textContent = "0";
  player.timerDisplay.textContent = gameTime;
  player.track.innerHTML = "";
}

function startGame() {
  if (gameStarted) return;
  gameStarted = true;

  console.log('開始遊戲，隱藏所有模態視窗');
  
  // 隱藏所有模態視窗
  if (friendInviteModal) {
    friendInviteModal.style.setProperty('display', 'none', 'important');
    friendInviteModal.classList.remove('show');
    console.log('已隱藏邀請視窗');
  }
  if (difficultyModal) {
    difficultyModal.style.setProperty('display', 'none', 'important');
    difficultyModal.classList.remove('show');
    console.log('已隱藏難度選擇視窗');
  }
  if (waitingModal) {
    waitingModal.style.setProperty('display', 'none', 'important');
    waitingModal.classList.remove('show');
    console.log('已隱藏等待視窗');
  }
  if (receivedInvitationModal) {
    receivedInvitationModal.style.setProperty('display', 'none', 'important');
    receivedInvitationModal.classList.remove('show');
    console.log('已隱藏收到邀請視窗');
  }
  
  gameContainer.style.display = 'block';
  console.log('遊戲容器已顯示');

  // 設置玩家名字
  const player1Name = document.getElementById('player1-name');
  const player2Name = document.getElementById('player2-name');
  
  // 上面是玩家一的名字，下面是玩家二的名字
  if (player1Name) player1Name.textContent = currentInvitation?.friendName || '玩家一';
  if (player2Name) player2Name.textContent = currentUserName;

  timeLeft = gameTime;
  player1.timerDisplay.textContent = timeLeft;
  player2.timerDisplay.textContent = timeLeft;
  resetPlayer(player1);
  resetPlayer(player2);

  generateNotes(player1);
  generateNotes(player2);

  const bgm = document.getElementById('bgm');
  if (bgm) {
    const levelStr = difficulty;
    bgm.src = levelStr === 'easy' ? "audio/music2.mp3" : levelStr === 'normal' ? "audio/music3.mp3" : "audio/hard.mp3";
    bgm.volume = 0.5;
    bgm.play().catch(e => console.log('音樂播放失敗:', e));
  }

  gameInterval = setInterval(() => {
    timeLeft--;
    player1.timerDisplay.textContent = timeLeft;
    player2.timerDisplay.textContent = timeLeft;
    if (timeLeft <= 0) {
      endGame();
    }
  }, 1000);
}

const rhythmPatterns = {
  easy: [1000, 1200, 1000, 1500, 800],
  normal: [800, 600, 1000, 700, 900],
  hard: [400, 300, 600, 200, 500, 300, 700]
};

function generateNotes(player) {
  const pattern = rhythmPatterns[difficulty] || rhythmPatterns.easy;
  let index = 0;
  let noteCount = 0;

  function spawnNote() {
    if (gamePaused || timeLeft <= 0) return;

    const note = document.createElement("div");
    note.classList.add("note");

    const ballImg = document.createElement("img");
    ballImg.src = "img/note.png";
    ballImg.style.width = "100px";
    ballImg.style.height = "100px";
    ballImg.style.position = "absolute";
    ballImg.style.left = "10%";
    ballImg.style.transform = "translateX(-50%)";
    note.appendChild(ballImg);

    note.style.position = "absolute";
    note.style.left = "100%";
    note.style.top = "10%";
    note.style.transform = "translateY(-50%)";

    player.track.appendChild(note);

    let left = 100;
    const isEven = noteCount % 2 === 0;
    const moveStep = isEven ? 0.4 : 1.25;
    const moveInterval = isEven ? 60 : 40;

    function move() {
      if (gamePaused) {
        requestAnimationFrame(move);
        return;
      }

      left -= moveStep;
      note.style.left = `${left}%`;

      if (left < -10) {
        note.remove();
        return;
      }

      requestAnimationFrame(move);
    }

    requestAnimationFrame(move);

    noteCount++;
    index = (index + 1) % pattern.length;
    player.interval = setTimeout(spawnNote, pattern[index]);
  }

  spawnNote();
}

function hit(player) {
  const notes = player.track.querySelectorAll(".note");
  notes.forEach(note => {
    const noteRect = note.getBoundingClientRect();
    const hitZoneRect = player.hitZone.getBoundingClientRect();

    const noteCenter = noteRect.left + noteRect.width / 2;
    const hitZoneCenter = hitZoneRect.left + hitZoneRect.width / 2;
    const distance = Math.abs(noteCenter - hitZoneCenter);

    let score = 0;
    let result = "";

    if (distance <= 20) {
      score = 20;
      result = "Perfect";
    } else if (distance <= 50) {
      score = 10;
      result = "Good";
    } else if (distance <= 100) {
      score = 0;
      result = "Miss";
    }

    if (result) {
      player.score += score;
      if (player.scoreDisplay) {
        player.scoreDisplay.textContent = player.score;
      }
      if (player.highScoreDisplay) {
        if (player.score > player.highScore) {
          player.highScore = player.score;
          player.highScoreDisplay.textContent = player.highScore;
        }
      }
      showHitResult(player, result, score);
      note.remove();
    }
  });
}

function showHitResult(player, result, score) {
  const resultDiv = document.createElement("div");
  resultDiv.className = "hit-result";
  resultDiv.textContent = `${result} +${score}`;

  resultDiv.style.position = "absolute";
  resultDiv.style.left = "50%";
  resultDiv.style.top = "50%";
  resultDiv.style.transform = "translate(-50%, -50%)";
  resultDiv.style.fontSize = "24px";
  resultDiv.style.fontWeight = "bold";
  resultDiv.style.color = result === "Perfect" ? "#FFD700" : result === "Good" ? "#4CAF50" : "#FF5722";
  resultDiv.style.textShadow = "2px 2px 4px rgba(0,0,0,0.5)";
  resultDiv.style.zIndex = "1000";
  resultDiv.style.pointerEvents = "none";

  const playerArea = player.hitZone.parentElement;
  playerArea.appendChild(resultDiv);

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

function endGame() {
  clearInterval(gameInterval);
  clearInterval(player1.interval);
  clearInterval(player2.interval);
  gameStarted = false;

  const bgm = document.getElementById('bgm');
  if (bgm) {
    bgm.pause();
    bgm.currentTime = 0;
  }

  const p1 = player1.score;
  const p2 = player2.score;
  const success = p1 !== p2;

  if (resultTitle) resultTitle.textContent = p1 > p2 ? '🎉 玩家1勝利！' : p2 > p1 ? '🎉 玩家2勝利！' : '🤝 平手！';
  if (resultDifficulty) resultDifficulty.textContent = '難度：' + (difficulty === 'easy' ? '簡單' : difficulty === 'normal' ? '普通' : '困難');
  if (resultScore) resultScore.textContent = `玩家1：${p1} 分 ｜ 玩家2：${p2} 分`;
  if (resultModal) {
    resultModal.style.display = 'flex';
    resultModal.classList.add("show");
  }

  // 保存遊戲結果到數據庫
  saveGameResult(p1, p2);
}

function saveGameResult(player1Score, player2Score) {
  const memberId = document.getElementById('member-id').value;
  
  // 暫時禁用保存功能，避免 SQL 錯誤
  console.log('遊戲結果（暫時不保存）:', {
    player1_score: player1Score,
    player2_score: player2Score,
    difficulty: difficulty,
    play_time: gameTime - timeLeft
  });
  
  // 如果需要保存，可以稍後實現
  /*
  fetch('save_rhythm_game.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      member_id: memberId,
      player1_score: player1Score,
      player2_score: player2Score,
      difficulty: difficulty,
      play_time: gameTime - timeLeft
    })
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      console.log('遊戲結果已保存');
    } else {
      console.error('保存失敗:', data.message);
    }
  })
  .catch(error => {
    console.error('保存錯誤:', error);
  });
  */
}

function swingBat(player) {
  if (player.bat) {
    player.bat.classList.add('swing');
    setTimeout(() => player.bat.classList.remove('swing'), 300);
  }
}

if (startBtn) startBtn.addEventListener("click", startGame);
if (restartBtn) restartBtn.addEventListener("click", () => {
  endGame();
  startGame();
});
if (endBtn) endBtn.addEventListener("click", endGame);

const backBtn = document.getElementById('back-btn');
if (backBtn) {
  backBtn.addEventListener("click", () => {
    window.location.href = 'index.php';
  });
}
if (infoBtn) infoBtn.addEventListener("click", () => {
  if (infoModal) {
    infoModal.style.display = "flex";
    infoModal.classList.add("show");
  }
});

const closeBtn = document.querySelector('.close-btn');
if (closeBtn) {
  closeBtn.addEventListener("click", () => {
    if (infoModal) {
      infoModal.style.display = "none";
      infoModal.classList.remove("show");
    }
  });
}

const difficultyOptions = document.querySelectorAll(".difficulty-option");
difficultyOptions.forEach(option => {
  option.addEventListener("click", () => {
    difficulty = option.dataset.difficulty;

    if (difficultyModal) {
      difficultyModal.style.display = "none";
      difficultyModal.classList.remove("show");
    }
    
    // 如果是邀請人，通知對方難度已選擇
    if (isInviter && currentInvitation) {
      notifyDifficultySelected();
    } else {
      startGame();
    }
  });
});

// 通知對方難度已選擇
function notifyDifficultySelected() {
  if (!currentInvitation) return;
  
  console.log('通知難度選擇:', difficulty);
  
  fetch('game-sync-api.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      action: 'update_game_state',
      invitation_id: currentInvitation.invitationId,
      game_state: {
        difficulty: difficulty,
        status: 'ready_to_start'
      }
    })
  })
  .then(response => response.json())
  .then(data => {
    console.log('通知難度結果:', data);
    if (data.success) {
      console.log('難度已通知對方，開始遊戲');
      startGame();
    } else {
      console.error('通知難度失敗:', data.message);
      startGame(); // 即使通知失敗也開始遊戲
    }
  })
  .catch(error => {
    console.error('通知難度錯誤:', error);
    startGame(); // 即使通知失敗也開始遊戲
  });
}

// 檢查遊戲狀態（被邀請人使用）
function checkGameState() {
  if (!currentInvitation || isInviter || gameStarted) return;
  
  console.log('檢查遊戲狀態...', {
    currentInvitation: currentInvitation,
    isInviter: isInviter,
    gameStarted: gameStarted
  });
  
  fetch('game-sync-api.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      action: 'get_game_state',
      invitation_id: currentInvitation.invitationId
    })
  })
  .then(response => response.json())
  .then(data => {
    console.log('遊戲狀態檢查結果:', data);
    
    // 檢查是否有玩家退出
    if (data.success && data.player_quit) {
      console.log('對方已退出遊戲');
      
      // 停止檢查遊戲狀態
      if (window.gameStateInterval) {
        clearInterval(window.gameStateInterval);
        window.gameStateInterval = null;
      }
      
      // 隱藏等待視窗
      if (waitingModal) {
        waitingModal.style.setProperty('display', 'none', 'important');
        waitingModal.classList.remove('show');
      }
      
      // 顯示對方退出訊息並返回
      alert('對方已退出遊戲');
      window.location.href = 'game-category.php';
      return;
    }
    
    if (data.success && data.game_state && data.game_state.status === 'ready_to_start') {
      console.log('收到難度選擇通知，開始遊戲');
      difficulty = data.game_state.difficulty;
      
      // 停止檢查遊戲狀態
      if (window.gameStateInterval) {
        clearInterval(window.gameStateInterval);
        window.gameStateInterval = null;
      }
      
      // 隱藏等待視窗
      if (waitingModal) {
        waitingModal.style.setProperty('display', 'none', 'important');
        waitingModal.classList.remove('show');
        console.log('已隱藏等待視窗');
      }
      
      startGame();
    } else {
      console.log('遊戲狀態未就緒:', data.game_state);
    }
  })
  .catch(error => {
    console.error('檢查遊戲狀態錯誤:', error);
  });
}

if (player1.hitZone) {
  player1.hitZone.addEventListener("click", () => {
    hit(player1);
    swingBat(player1);
  });
}

if (player2.hitZone) {
  player2.hitZone.addEventListener("click", () => {
    hit(player2);
    swingBat(player2);
  });
}

document.addEventListener("keydown", e => {
  if (!gameStarted) return;
  if (e.key === "a") {
    hit(player1);
    swingBat(player1);
  }
  if (e.key === "l") {
    hit(player2);
    swingBat(player2);
  }
});



const pauseBtn = document.getElementById("pause-btn");

if (pauseBtn) {
  pauseBtn.addEventListener("click", togglePause);
}

if (endBtn) {
  endBtn.addEventListener("click", endGame);
}

if (restartBtn) {
  restartBtn.addEventListener("click", () => {
    if (gameStarted) {
      console.log('重新開始遊戲');
      
      // 停止當前遊戲
      if (gameInterval) {
        clearInterval(gameInterval);
      }
      
      // 重置遊戲狀態
      gameStarted = false;
      gamePaused = false;
      timeLeft = gameTime;
      
      // 重置玩家狀態
      resetPlayer(player1);
      resetPlayer(player2);
      
      // 重新開始遊戲
      startGame();
    }
  });
}

function togglePause() {
  const bgm = document.getElementById('bgm');

  if (!gamePaused) {
    gamePaused = true;
    clearInterval(gameInterval);
    clearTimeout(player1.interval);
    clearTimeout(player2.interval);
    if (bgm) bgm.pause();
    pauseBtn.textContent = "繼續遊戲";
  } else {
    gamePaused = false;

    gameInterval = setInterval(() => {
      timeLeft--;
      player1.timerDisplay.textContent = timeLeft;
      player2.timerDisplay.textContent = timeLeft;
      if (timeLeft <= 0) {
        endGame();
      }
    }, 1000);

    if (bgm) bgm.play();
     // 重新啟動音符移動（從原本 left 位置繼續）
    [player1.track, player2.track].forEach(track => {
      const notes = track.querySelectorAll(".note");
      notes.forEach(note => {
        if (!note.dataset.moveIntervalId) {
          let left = parseFloat(note.dataset.left) || 100;

          // 判斷快慢
          const noteIndex = Array.from(track.children).indexOf(note);
          const isEven = noteIndex % 2 === 0;
          const moveStep = isEven ? 0.8 : 2.5;
          const moveInterval = isEven ? 40 : 20;

          function moveNote() {
            if (!gamePaused) {
              left -= moveStep;
              note.style.left = `${left}%`;
              note.dataset.left = left;

              if (left < -10) {
                note.remove();
                clearInterval(note.dataset.moveIntervalId);
              }
            }
          }

          moveNote();
          const intervalId = setInterval(moveNote, moveInterval);
          note.dataset.moveIntervalId = intervalId;
        }
      });
    });

    // 重新啟動音符生成
    generateNotes(player1);
    generateNotes(player2);
    pauseBtn.textContent = "暫停遊戲";
  }
}
