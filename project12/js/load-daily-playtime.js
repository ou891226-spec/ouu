// 載入每日遊玩時間紀錄
function loadDailyPlaytimeRecords() {
  const loadingDiv = document.getElementById('dailyRecordLoading');
  const tableDiv = document.getElementById('dailyRecordTable');
  const tableBody = document.getElementById('dailyRecordTableBody');
  const emptyDiv = document.getElementById('dailyRecordEmpty');
  
  if (!loadingDiv || !tableDiv || !tableBody || !emptyDiv) {
    console.error('找不到必要的DOM元素');
    return;
  }
  
  // 顯示載入中
  loadingDiv.style.display = 'block';
  tableDiv.style.display = 'none';
  emptyDiv.style.display = 'none';
  
  fetch('get_daily_playtime.php')
    .then(response => {
      if (!response.ok) {
        throw new Error('網路回應不正常');
      }
      return response.json();
    })
    .then(data => {
      loadingDiv.style.display = 'none';
      
      if (data.success && data.records && data.records.length > 0) {
        // 清空表格內容
        tableBody.innerHTML = '';
        
        // 添加記錄到表格
        data.records.forEach(record => {
          const row = document.createElement('tr');
          row.innerHTML = `
            <td style="padding: 8px; border: 1px solid #ccc;">${record.date}</td>
            <td style="padding: 8px; border: 1px solid #ccc;">${record.playtime}</td>
          `;
          tableBody.appendChild(row);
        });
        
        tableDiv.style.display = 'table';
      } else {
        emptyDiv.style.display = 'block';
      }
    })
    .catch(error => {
      console.error('載入每日遊玩時間紀錄失敗:', error);
      loadingDiv.style.display = 'none';
      emptyDiv.textContent = '載入失敗，請稍後再試';
      emptyDiv.style.display = 'block';
    });
}

// 保存當前遊玩時間到每日紀錄
function saveCurrentPlaytime() {
  const timeElement = document.getElementById('timeValue');
  if (!timeElement) return;
  
  const timeText = timeElement.textContent;
  const timeParts = timeText.split(':');
  
  if (timeParts.length === 3) {
    const hours = parseInt(timeParts[0]);
    const minutes = parseInt(timeParts[1]);
    const seconds = parseInt(timeParts[2]);
    
    const totalSeconds = hours * 3600 + minutes * 60 + seconds;
    
    if (totalSeconds > 0) {
      const data = new URLSearchParams({
        play_time: totalSeconds,
      });

      fetch('save_daily_playtime.php', {
        method: 'POST',
        body: data,
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        }
      }).catch(error => {
        console.log('每日時間儲存失敗');
      });
    }
  }
}

// 頁面載入完成後初始化
document.addEventListener('DOMContentLoaded', function() {
  // 如果頁面有每日遊玩時間紀錄相關元素，則初始化
  if (document.getElementById('dailyRecordSection')) {
    console.log('每日遊玩時間紀錄功能已初始化');
  }
}); 