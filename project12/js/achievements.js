// 成就快取
let achievementsCache = null;
let lastLoadTime = 0;
const CACHE_DURATION = 30000; // 30秒快取

// 載入用戶成就（帶快取）
function loadUserAchievements() {
  const now = Date.now();
  
  // 檢查快取是否有效
  if (achievementsCache && (now - lastLoadTime) < CACHE_DURATION) {
    displayAchievements(achievementsCache);
    return;
  }
  
  // 顯示載入狀態
  const container = document.getElementById('achievementCards');
  if (container) {
    container.innerHTML = `
      <div style="text-align: center; padding: 20px; color: #666;">
        <div style="font-size: 24px; margin-bottom: 10px;">⏳</div>
        <div style="font-size: 14px; color: #999;">載入成就中...</div>
      </div>
    `;
  }
  
  fetch('get_user_achievements.php')
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        // 更新快取
        achievementsCache = data.achievements;
        lastLoadTime = now;
        displayAchievements(data.achievements, data.today_status);
      } else {
        console.error('載入成就失敗：', data.message);
        displayEmptyAchievements();
      }
    })
    .catch(error => {
      console.error('載入成就時發生錯誤：', error);
      displayEmptyAchievements();
    });
}

// 清除成就快取（當用戶完成任務時調用）
function clearAchievementsCache() {
  achievementsCache = null;
  lastLoadTime = 0;
}

// 顯示成就卡片
function displayAchievements(achievements, todayStatus = null) {
  const container = document.getElementById('achievementCards');
  
  if (!container) {
    console.error('找不到成就容器');
    return;
  }
  
  if (!achievements || achievements.length === 0) {
    // 如果沒有成就，顯示空狀態
    displayEmptyAchievements();
    return;
  }

  container.innerHTML = '';
  
  // 添加今日成就狀態
  if (todayStatus) {
    const statusDiv = document.createElement('div');
    statusDiv.style.cssText = 'margin-bottom: 15px; padding: 10px; background: #f0f8ff; border-radius: 5px; border: 1px solid #d0e7ff; text-align: center;';
    
    const remaining = todayStatus.remaining;
    const todayCount = todayStatus.today_count;
    
    if (remaining > 0) {
      statusDiv.innerHTML = `
        <div style="font-size: 14px; color: #0066cc; margin-bottom: 5px;">
          📅 今日已獲得 ${todayCount}/3 個成就
        </div>
        <div style="font-size: 12px; color: #0066cc;">
          還可獲得 ${remaining} 個成就 • 凌晨12點重置
        </div>
      `;
    } else {
      statusDiv.innerHTML = `
        <div style="font-size: 14px; color: #ff6b6b; margin-bottom: 5px;">
          📅 今日成就已達上限 (3/3)
        </div>
        <div style="font-size: 12px; color: #ff6b6b;">
          凌晨12點重置後可繼續獲得成就
        </div>
      `;
    }
    
    container.appendChild(statusDiv);
  }
  
  // 最多顯示4個成就
  const displayAchievements = achievements.slice(0, 4);
  
  displayAchievements.forEach((achievement, index) => {
    const card = document.createElement('div');
    card.className = 'profile-card';
    card.style.cursor = 'pointer';
    card.onclick = () => showAchievementDetail(achievement);
    
    card.innerHTML = `
      <div class="emoji-icon" style="background:#97f55c;display:flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:10px;font-weight:bold;font-size:20px;color:#333;text-shadow:1px 1px 2px rgba(0,0,0,0.1);">${achievement.icon || '🏆'}</div>
      <div class="profile-card-label" style="font-size:12px;margin-top:5px;">${achievement.achievement_name}</div>
    `;
    
    container.appendChild(card);
  });
}

// 顯示空成就狀態
function displayEmptyAchievements() {
  const container = document.getElementById('achievementCards');
  
  if (!container) {
    console.error('找不到成就容器');
    return;
  }
  
  container.innerHTML = `
    <div style="text-align: center; padding: 20px; color: #666;">
      <div style="font-size: 48px; margin-bottom: 10px;">🎯</div>
      <div style="font-size: 16px; margin-bottom: 5px;">尚未獲得成就</div>
      <div style="font-size: 14px; color: #999;">完成遊戲來獲得成就稱號！</div>
      <div style="margin-top: 15px; padding: 10px; background: #f0f8ff; border-radius: 5px; border: 1px solid #d0e7ff;">
        <div style="font-size: 12px; color: #0066cc;">📅 每日限制：3個成就</div>
        <div style="font-size: 12px; color: #0066cc;">🕛 凌晨12點重置</div>
      </div>
    </div>
  `;
}

// 顯示成就詳情
function showAchievementDetail(achievement) {
  const date = new Date(achievement.earned_date).toLocaleDateString('zh-TW');
  alert(`${achievement.icon} ${achievement.achievement_name}\n\n📝 ${achievement.achievement_description}\n\n📅 獲得時間：${date}`);
}

// 重寫 openProfileModal 函數以載入成就
function openProfileModalWithAchievements() {
  document.getElementById('profileModal').style.display = 'flex';
  document.getElementById('modalOverlay').style.display = 'block';
  loadUserAchievements(); // 載入成就
} 