// 檢查是否已經載入過任務，避免重複載入
if (!window.tasksLoaded) {
  window.tasksLoaded = true;
  
  document.addEventListener("DOMContentLoaded", () => {
    console.log("開始載入每日任務...");
    fetch("get_daily_tasks_fixed.php")
      .then(response => {
        console.log("收到回應:", response);
        return response.json();
      })
      .then(tasks => {
        console.log("解析的任務:", tasks);
        const container = document.getElementById("daily-tasks-container");
        if (!container) {
          console.error("找不到 daily-tasks-container 元素");
          return;
        }
        container.innerHTML = "";

        tasks.forEach(task => {
          console.log("處理任務:", task);
          const status = task.status || "pending";
          const isCompleted = status === "completed" || status === "claimed";
          const isClaimed = status === "claimed";
          const progress = isCompleted ? "1/1" : "0/1";

          const taskItem = document.createElement("div");
          taskItem.className = "mission-item";
          taskItem.setAttribute("data-progress", progress);
          taskItem.setAttribute("data-completed", isCompleted);
          taskItem.setAttribute("data-task-id", task.task_id);

          let btnHtml = '';
          if (isClaimed) {
            btnHtml = '<button class="reward-btn" disabled>已領取</button>';
          } else if (isCompleted) {
            btnHtml = `<button class="reward-btn" onclick="claimReward(this)">領取獎勵</button>`;
          } else {
            btnHtml = '<button class="reward-btn" disabled>未完成</button>';
          }

          taskItem.innerHTML = `
            <div class="icon-text">
              <img src="img/${task.task_type}.png" alt="${task.task_name}" onerror="this.src='img/Achievement.png'">
              <div>
                <div class="title">${task.task_name}</div>
                <div class="desc">${task.task_description}</div>
              </div>
            </div>
            <div class="progress">${progress}</div>
            ${btnHtml}
          `;
          container.appendChild(taskItem);
        });
        
        console.log("任務載入完成，共載入", tasks.length, "個任務");
      })
      .catch(error => {
        console.error("載入任務失敗:", error);
        const container = document.getElementById("daily-tasks-container");
        if (container) {
          container.innerHTML = `
            <div style="text-align: center; padding: 20px; color: #666;">
              <div style="font-size: 24px; margin-bottom: 10px;">⚠️</div>
              <div style="font-size: 14px; color: #999;">載入任務失敗</div>
            </div>
          `;
        }
      });
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
