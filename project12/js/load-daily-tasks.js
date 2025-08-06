document.addEventListener("DOMContentLoaded", () => {
  console.log("開始載入每日任務...");
  fetch("get_daily_tasks.php")
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
        const isCompleted = status === "completed";
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
          btnHtml = `<button class=\"reward-btn\" onclick=\"claimReward(this, ${task.task_id})\">領取獎勵</button>`;
        } else {
          btnHtml = '<button class="reward-btn" disabled>未完成</button>';
        }

        taskItem.innerHTML = `
          <div class="icon-text">
            <img src="img/${task.task_type}.png" alt="${task.task_name}">
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


    })
    .catch(error => {
      console.error("載入每日任務失敗：", error);
    });
});
