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

    // 獲取當前用戶ID（如果統一系統沒有提供）
    function getCurrentMemberId() {
        // 嘗試從統一系統獲取
        if (typeof window.getCurrentMemberId === 'function') {
            return window.getCurrentMemberId();
        }
        
        // 從 PHP session 獲取（備用方案）
        try {
            const memberIdElement = document.querySelector('meta[name="member_id"]');
            if (memberIdElement) {
                return parseInt(memberIdElement.content);
            }
        } catch (e) {
            console.warn('無法獲取用戶ID:', e);
        }
        
        return null;
    }

    // 保存游戏记录的函数 - 使用統一系統
    function saveGameRecord(pass, score, pass_bounce, isManualExit = false) {
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
        
        // 使用統一系統保存遊戲結果
        saveGameResult({
            member_id: getCurrentMemberId(),
            game_type: '記憶力', // 圖片線索問答屬於記憶力類型
            difficulty: difficulty,
            score: pass ? pass_bounce : 0,
            play_time: gameTime,
            is_manual_exit: isManualExit,
            is_passed: pass,
            game_id: 8
        }).then(result => {
            console.log('遊戲結果已儲存:', result);
            saveRequestInProgress = false;
        }).catch(error => {
            console.error('儲存遊戲結果時發生錯誤:', error);
            saveRequestInProgress = false;
        });
        
        return; // 直接返回，不再使用舊的邏輯
        
    };
});