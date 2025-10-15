// 檢查今天是否已經載入過任務，避免重複載入
document.addEventListener("DOMContentLoaded", () => {
  // 檢查今天是否已經載入過任務
  const today = new Date().toDateString();
  const lastLoadDate = localStorage.getItem('missionLoadDate');
  const hasLoadedToday = localStorage.getItem('missionLoadedToday') === 'true';
  
  // 如果是新的一天，重置狀態
  if (lastLoadDate !== today) {
    localStorage.setItem('missionLoadDate', today);
    localStorage.setItem('missionLoadedToday', 'false');
    localStorage.removeItem('missionShownToday'); // 重置今日顯示標記
  }
  
  // 如果今天還沒載入過任務，才載入
  if (!hasLoadedToday) {
    console.log("開始載入每日任務...");
    loadDailyTasks();
    localStorage.setItem('missionLoadedToday', 'true');
  } else {
    console.log("今天已經載入過任務，跳過自動載入");
  }
  
  // 檢查用戶是否選擇自動顯示每日任務彈窗，且今天還沒顯示過
  const autoShowMission = localStorage.getItem('autoShowMission') !== 'false'; // 預設為true
  const hasShownToday = localStorage.getItem('missionShownToday') === 'true';
  
  // 強制重新載入任務數據（無論是否已載入過）
  console.log("強制重新載入每日任務數據");
  loadDailyTasks();
  
  // 禁用自動彈出每日任務視窗
  console.log("已禁用自動顯示每日任務彈窗");
  /*
  if (autoShowMission && !hasShownToday) {
    // 自動顯示每日任務彈窗（延遲2秒顯示）
    setTimeout(() => {
      const missionModal = document.getElementById('missionModal');
      const modalOverlay = document.getElementById('modalOverlay');
      
      if (missionModal && modalOverlay) {
        console.log("自動顯示每日任務彈窗（首次顯示）");
        missionModal.style.display = 'flex';
        modalOverlay.style.display = 'block';
        
        // 標記今天已經顯示過
        localStorage.setItem('missionShownToday', 'true');
      }
    }, 2000); // 延遲2秒顯示
  } else if (hasShownToday) {
    console.log("今天已經顯示過每日任務彈窗，跳過自動顯示");
  } else if (!autoShowMission) {
    console.log("用戶已關閉自動顯示每日任務彈窗");
  }
  */
});

// 圖片映射函數
function getTaskIcon(taskType) {
  const iconMap = {
    'memory': 'memory_game.png',
    'rhythm': 'Achievement.png',
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

// 載入每日任務
function loadDailyTasks() {
  console.log("開始載入每日任務...");
  
  // 添加時間戳防止緩存
  const timestamp = new Date().getTime();
  console.log("請求URL:", `get_daily_tasks_fixed.php?t=${timestamp}`);
  fetch(`get_daily_tasks_fixed.php?t=${timestamp}&nocache=${Math.random()}`, {
    method: 'GET',
    credentials: 'same-origin', // 确保传递会话信息
    headers: {
      'Content-Type': 'application/json',
    },
    cache: 'no-cache' // 強制不使用緩存
  })
    .then(response => {
      console.log("收到回應:", response.status);
      console.log("回應狀態:", response.statusText);
      
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      
      return response.json();
    })
    .then(tasks => {
      console.log("任務資料:", tasks);
      console.log("任務類型:", typeof tasks);
      console.log("任務長度:", Array.isArray(tasks) ? tasks.length : '不是陣列');
      
      // 检查是否是错误响应
      if (tasks && tasks.error) {
        console.error("API返回错误:", tasks.error);
        throw new Error("API错误: " + tasks.error);
      }
      
      // 嘗試找到任務容器，支援多種容器ID
      let container = document.getElementById("daily-tasks-container") || 
                     document.getElementById("missionList") || 
                     document.getElementById("mission-list");
      
      if (!container) {
        console.error("找不到任務容器元素 (daily-tasks-container, missionList, mission-list)");
        return;
      }
      
      container.innerHTML = ""; // 清空舊內容

      if (!Array.isArray(tasks) || tasks.length === 0) {
        console.log("沒有任務或任務不是陣列");
        container.innerHTML = `
          <div style="text-align: center; padding: 20px; color: #666;">
            <div style="font-size: 24px; margin-bottom: 10px;">📋</div>
            <div style="font-size: 16px; margin-bottom: 5px;">暫無每日任務</div>
            <div style="font-size: 14px; color: #999;">請稍後再試</div>
          </div>
        `;
        return;
      }

      console.log("開始處理", tasks.length, "個任務");
      
      // 更新任務計數
      const completedCountEl = document.getElementById("completedCount");
      const totalCountEl = document.getElementById("totalCount");
      if (totalCountEl) {
        totalCountEl.textContent = tasks.length;
      }
      
      let completedCount = 0;
      
      tasks.forEach((task, index) => {
        console.log(`處理任務 ${index + 1}:`, task);
        
        const item = document.createElement("div");
        item.className = "mission-item";
        item.setAttribute("data-task-id", task.task_id);
        
        // 支援累積型任務的進度計算
        const current = parseInt(task.progress) || 0;
        const required = parseInt(task.required) || 1;
        
        // 判斷是否為遊戲時間相關任務，如果是則添加"秒"字
        // 排除"時間挑戰者"任務，因為它是關於完成局數的，不是累積時間
        const isTimeTask = (task.task_name === '持久戰士' || 
                          task.task_name === '速度之王' ||
                          task.task_name === '時間大師' ||
                          task.task_description.includes('遊玩時間') ||
                          task.task_description.includes('遊戲時間') ||
                          task.task_description.includes('累積時間') ||
                          (task.task_description.includes('總共') && task.task_description.includes('分鐘'))) &&
                          task.task_name !== '時間挑戰者';
        
        const progressText = isTimeTask ? `${current}/${required}秒` : `${current}/${required}`;
        
        // 調試信息
        console.log(`任務 ${index + 1} (${task.task_name}):`, {
          task_id: task.task_id,
          task_name: task.task_name,
          task_description: task.task_description,
          progress: task.progress,
          required: task.required,
          current: current,
          required_num: required,
          isTimeTask: isTimeTask,
          progressText: progressText
        });
        
        // 判斷是否已完成（支援累積型任務）
        const isCompleted = current >= required || task.status === 'completed' || task.status === 'claimed';
        const isClaimed = task.status === 'claimed';
        
        // 計算已完成任務數量
        if (isCompleted) {
          completedCount++;
        }

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
        
        // 進度顏色和樣式
        let progressColor = '#ddd';
        let progressStyle = '';
        
        if (isCompleted) {
          progressColor = '#4caf50'; // 綠色 - 已完成
          progressStyle = 'font-weight: bold;';
        } else if (current > 0) {
          progressColor = '#f44336'; // 紅色 - 有進度
          progressStyle = 'font-weight: bold;';
        } else {
          progressColor = '#999'; // 灰色 - 未開始
        }

        // 如果是累積型任務，添加進度條
        const progressBarHtml = required > 1 ? `
          <div style="width: 60px; height: 4px; background: #eee; border-radius: 2px; margin-top: 4px;">
            <div style="width: ${Math.min((current / required) * 100, 100)}%; height: 100%; background: ${progressColor}; border-radius: 2px; transition: width 0.3s ease;"></div>
          </div>
        ` : '';

        item.innerHTML = `
          <div class="icon-text">
            <img src="img/${iconFile}" alt="${task.task_name}" onerror="this.src='img/Achievement.png'">
            <div>
              <div class="title">${task.task_name}</div>
              <div class="desc">${task.task_description}</div>
            </div>
          </div>
          <div class="progress" style="color: ${progressColor}; ${progressStyle}">
            ${progressText}
            ${progressBarHtml}
          </div>
          ${btnHtml}
        `;
        container.appendChild(item);
        console.log(`任務 ${index + 1} 已添加到DOM`);
      });

      // 更新已完成任務計數
      if (completedCountEl) {
        completedCountEl.textContent = completedCount;
      }
      
      console.log("任務載入完成，已完成:", completedCount, "總計:", tasks.length);
    })
    .catch(error => {
      console.error("載入任務失敗：", error);
      console.error("錯誤詳情：", error.message);
      
      const container = document.getElementById("daily-tasks-container") || 
                       document.getElementById("missionList") || 
                       document.getElementById("mission-list");
      if (container) {
        container.innerHTML = `
          <div style="text-align: center; padding: 20px; color: #666;">
            <div style="font-size: 24px; margin-bottom: 10px;">❌</div>
            <div style="font-size: 16px; margin-bottom: 5px;">載入任務失敗</div>
            <div style="font-size: 14px; color: #999;">錯誤: ${error.message}</div>
            <div style="font-size: 12px; color: #999; margin-top: 10px;">請重新整理頁面</div>
          </div>
        `;
      }
    });
}

// 領取成就獎勵
function claimReward(button) {
  console.log("開始領取成就獎勵...");
  
  // 禁用按鈕防止重複點擊
  button.disabled = true;
  button.textContent = '領取中...';
  
  fetch('claim_achievement.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      action: 'claim_achievement'
    })
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      if (data.achievements && data.achievements.length > 0) {
        // 顯示獲得的成就
        let message = '🎉 恭喜獲得成就！\n\n';
        data.achievements.forEach(achievement => {
          message += `${achievement.icon} ${achievement.achievement_name}\n`;
          message += `完成任務：${achievement.task_description}\n\n`;
        });
        alert(message);
        
        // 更新按鈕狀態為已領取
        button.textContent = '已領取';
        button.style.backgroundColor = '#e0e0e0';
        button.style.color = '#999';
      } else {
        alert('沒有可領取的成就');
        // 恢復按鈕狀態
        button.disabled = false;
        button.textContent = '領取獎勵';
      }
      
      // 重新載入任務列表
      setTimeout(() => {
        window.location.reload();
      }, 1000);
      
    } else {
      alert('領取失敗：' + data.message);
      // 恢復按鈕狀態
      button.disabled = false;
      button.textContent = '領取獎勵';
    }
  })
  .catch(error => {
    console.error('領取成就時發生錯誤：', error);
    alert('領取失敗，請稍後再試');
    // 恢復按鈕狀態
    button.disabled = false;
    button.textContent = '領取獎勵';
  });
}
