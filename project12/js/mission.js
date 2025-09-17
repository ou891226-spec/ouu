// 開啟每日任務視窗
function openMissionModal() {
  const modal = document.getElementById("missionModal");
  modal.style.display = "flex";
  
  // 立即顯示彈窗，然後觸發動畫
  setTimeout(() => {
    modal.classList.add('show');
  }, 10); // 很短的延遲讓 DOM 更新
  
  // 立即載入任務，不等待動畫
  console.log("打開每日任務彈窗，重新載入任務");
  loadDailyTasks();
}

// 關閉視窗
function closeMissionModal() {
  const modal = document.getElementById("missionModal");
  modal.classList.remove('show');
  
  // 等待動畫完成後隱藏
  setTimeout(() => {
    modal.style.display = "none";
  }, 150); // 與 CSS transition 時間一致
}

// 顯示獎勵領取成功彈窗
function showRewardSuccessModal() {
  // 創建彈窗元素
  const modal = document.createElement('div');
  modal.className = 'reward-success-modal';
  modal.innerHTML = `
    <div class="reward-success-content">
      <div class="reward-success-icon">🎉</div>
      <div class="reward-success-title">獎勵領取成功！</div>
      <div class="reward-success-message">恭喜您完成任務並獲得獎勵！</div>
      <button class="reward-success-btn" onclick="closeRewardSuccessModal()">確定</button>
    </div>
  `;
  
  // 添加到頁面
  document.body.appendChild(modal);
  
  // 添加動畫效果
  setTimeout(() => {
    modal.classList.add('show');
  }, 10);
}

// 關閉獎勵領取成功彈窗
function closeRewardSuccessModal() {
  const modal = document.querySelector('.reward-success-modal');
  if (modal) {
    modal.classList.remove('show');
    setTimeout(() => {
      document.body.removeChild(modal);
    }, 300);
  }
}

// 領取獎勵
function claimReward(button) {
  const missionItem = button.closest('.mission-item');
  const taskId = missionItem.getAttribute('data-task-id');
  const isCompleted = missionItem.getAttribute('data-completed') === 'true';

  if (!isCompleted) {
    alert('任務尚未完成，無法領取獎勵！');
    return;
  }

  // 呼叫後端 API 領取獎勵
  fetch('claim_reward.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `task_id=${taskId}`
  })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        showRewardSuccessModal(); // 使用自定義彈窗
        loadDailyTasks(); // 重新載入，按鈕會變成已領取
        
        // 清除成就快取，確保下次打開個人資訊時顯示最新成就
        if (typeof clearAchievementsCache === 'function') {
          clearAchievementsCache();
        }
      } else {
        alert(data.message || '領取失敗');
      }
    })
    .catch(() => {
      alert('伺服器錯誤，請稍後再試');
    });
}

// 圖片映射函數
function getTaskIcon(taskType) {
  const iconMap = {
    'memory': 'memory_game.png',
    'rhythm': 'complete_all_daily.png',
    'logic': 'Achievement.png',
    'coordination': 'complete_all_daily.png',
    'tracking': 'Achievement.png',
    'attention': 'complete_all_daily.png',
    'calculation': 'Achievement.png',
    'general': 'Achievement.png',
    'score': 'score_100.png',
    'login': 'login.png',
    'social': 'friend.png',
    'speed': 'Achievement.png',
    'endurance': 'Achievement.png',
    'reaction': 'note.png'
  };
  
  // 根據任務類型選擇更合適的圖片
  let iconFile = iconMap[taskType] || 'Achievement.png';
  
  // 如果是分數相關任務，根據任務描述選擇不同圖片
  if (taskType === 'score') {
    // 這裡可以根據具體的任務描述來選擇不同的分數圖片
    // 暫時使用 score_100.png，如果需要可以進一步優化
    iconFile = 'score_100.png';
  }
  
  console.log(`任務類型: ${taskType} -> 圖片: ${iconFile}`);
  return iconFile;
}

// ✅ 載入任務資料
function loadDailyTasks() {
  console.log("開始載入每日任務...");
  
  fetch("get_daily_tasks_fixed.php")
    .then(response => {
      console.log("收到回應:", response.status);
      return response.json();
    })
    .then(tasks => {
      console.log("任務資料:", tasks);
      
      const container = document.getElementById("daily-tasks-container");
      if (!container) {
        console.error("找不到 daily-tasks-container 元素");
        return;
      }
      
      container.innerHTML = ""; // 清空舊內容

      if (!Array.isArray(tasks) || tasks.length === 0) {
        container.innerHTML = `
          <div style="text-align: center; padding: 20px; color: #666;">
            <div style="font-size: 24px; margin-bottom: 10px;">📋</div>
            <div style="font-size: 16px; margin-bottom: 5px;">暫無每日任務</div>
            <div style="font-size: 14px; color: #999;">請稍後再試</div>
          </div>
        `;
        return;
      }

      tasks.forEach(task => {
        console.log("處理任務:", task);
        
        const item = document.createElement("div");
        item.className = "mission-item";
        item.setAttribute("data-task-id", task.task_id);
        // 判斷是否已完成
        const isCompleted = task.status === 'completed' || task.status === 'claimed';
        const isClaimed = task.status === 'claimed';
        const progressText = isCompleted ? '1/1' : '0/1';

        item.setAttribute("data-completed", isCompleted.toString());
        item.setAttribute("data-progress", progressText);

        let btnHtml = '';
        if (isClaimed) {
          btnHtml = '<button class="reward-btn" disabled>已領取</button>';
        } else if (isCompleted) {
          btnHtml = `<button class="reward-btn" onclick="claimReward(this)">領取獎勵</button>`;
        } else {
          btnHtml = '<button class="reward-btn" disabled>尚未完成</button>';
        }

        const iconFile = getTaskIcon(task.task_type);

        item.innerHTML = `
          <div class="icon-text">
            <img src="img/${iconFile}" alt="${task.task_name}" onerror="this.src='img/Achievement.png'">
            <div>
              <div class="title">${task.task_name}</div>
              <div class="desc">${task.task_description}</div>
            </div>
          </div>
          <div class="progress">${progressText}</div>
          ${btnHtml}
        `;
        container.appendChild(item);
      });
      
      console.log("任務載入完成，共載入", tasks.length, "個任務");
    })
    .catch(error => {
      console.error("載入任務失敗：", error);
      
      const container = document.getElementById("daily-tasks-container");
      if (container) {
        container.innerHTML = `
          <div style="text-align: center; padding: 20px; color: #666;">
            <div style="font-size: 24px; margin-bottom: 10px;">❌</div>
            <div style="font-size: 16px; margin-bottom: 5px;">載入任務失敗</div>
            <div style="font-size: 14px; color: #999;">請重新整理頁面</div>
          </div>
        `;
      }
    });
}

// 頁面載入初始化（如果需要）
document.addEventListener('DOMContentLoaded', function () {
  // 預留未來使用，例如：自動載入任務等
});
