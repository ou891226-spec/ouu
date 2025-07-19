// 開啟每日任務視窗
function openMissionModal() {
  document.getElementById("missionModal").style.display = "flex";
  loadDailyTasks();
}

// 關閉視窗
function closeMissionModal() {
  document.getElementById("missionModal").style.display = "none";
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
        alert('獎勵領取成功！');
        loadDailyTasks(); // 重新載入，按鈕會變成已領取
      } else {
        alert(data.message || '領取失敗');
      }
    })
    .catch(() => {
      alert('伺服器錯誤，請稍後再試');
    });
}

// ✅ 載入任務資料
function loadDailyTasks() {
  fetch("get_daily_tasks.php")
    .then(response => response.json())
    .then(tasks => {
      const container = document.getElementById("daily-tasks-container");
      container.innerHTML = ""; // 清空舊內容

      tasks.forEach(task => {
        const item = document.createElement("div");
        item.className = "mission-item";
        item.setAttribute("data-task-id", task.task_id);
        // 判斷是否已完成
        const isCompleted = task.status === 'completed';
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

        item.innerHTML = `
          <div class="icon-text">
            <img src="img/${task.task_type}.png" alt="${task.task_name}">
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
    })
    .catch(error => {
      console.error("載入任務失敗：", error);
    });
}

// 頁面載入初始化（如果需要）
document.addEventListener('DOMContentLoaded', function () {
  // 預留未來使用，例如：自動載入任務等
});
