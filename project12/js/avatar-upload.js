function previewAndUploadAvatar(event) {
  const file = event.target.files[0];
  if (!file) return;
  
  // 檢測是否為手機設備
  const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
  
  // 記錄調試信息
  console.log('Avatar upload attempt:', {
    fileName: file.name,
    fileSize: file.size,
    fileType: file.type,
    isMobile: isMobile
  });

  // 檢查檔案類型
  const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/tiff', 'image/tif', 'image/webp'];
  if (!allowedTypes.includes(file.type)) {
    showAvatarUploadError({
      message: '⚠️ 檔案格式不支援！',
      suggestion: '請選擇 JPG、PNG、GIF、TIFF 或 WebP 格式的圖片檔案。',
      allowed_formats: 'JPG、PNG、GIF、TIFF、WebP'
    });
    return;
  }

  // 檢查檔案大小（手機使用 20MB 限制）
  const maxSize = 20 * 1024 * 1024; // 統一使用 20MB
  if (file.size > maxSize) {
    const fileSizeMB = (file.size / (1024 * 1024)).toFixed(2);
    showAvatarUploadError({
      message: '⚠️ 檔案太大！',
      suggestion: `您的檔案大小為 ${fileSizeMB}MB，請選擇小於 20MB 的圖片。`,
      max_size: '20MB',
      current_size: fileSizeMB + 'MB'
    });
    return;
  }
  
  // 檢查檔案大小是否為 0（手機常見問題）
  if (file.size === 0) {
    showAvatarUploadError({
      message: '⚠️ 檔案讀取失敗！',
      suggestion: '請重新選擇圖片檔案，或嘗試使用其他圖片。',
      tip: '如果問題持續發生，請嘗試重新拍照或選擇其他圖片。'
    });
    return;
  }

  const reader = new FileReader();
  reader.onload = function(e) {
    const avatarImages = document.querySelectorAll('.profile-avatar, #profileAvatarImg, .profile');
    avatarImages.forEach(img => {
      img.src = e.target.result;
    });
  };
  reader.readAsDataURL(file);

  // 使用 AJAX 上傳
  const formData = new FormData();
  formData.append('avatar', file);

  // 手機使用更長的超時時間
  const timeout = isMobile ? 60000 : 30000; // 手機 60 秒，桌面 30 秒
  
  // 記錄上傳開始
  console.log('開始上傳頭貼:', {
    fileName: file.name,
    fileSize: file.size,
    fileType: file.type,
    isMobile: isMobile,
    timeout: timeout
  });
  
  const controller = new AbortController();
  const timeoutId = setTimeout(() => {
    console.log('上傳超時');
    controller.abort();
  }, timeout);

  fetch('upload_avatar.php', {
    method: 'POST',
    body: formData,
    signal: controller.signal
  })
  .then(response => {
    clearTimeout(timeoutId);
    return response.json();
  })
  .then(data => {
      if (data.success) {
        // 靜默更新所有頁面的頭像，不顯示成功訊息
        // 直接更新頭像，不重新載入頁面
        const avatarImages = document.querySelectorAll('.profile-avatar, #profileAvatarImg, .profile');
        avatarImages.forEach(img => {
          // 使用新的頭像路徑
          img.src = data.avatar_url + '?t=' + new Date().getTime();
        });
      } else {
        // 使用 CSS 樣式顯示錯誤訊息，而不是 alert
        showAvatarUploadError(data);
      }
    })
  .catch(error => {
    console.error('上傳錯誤:', error);
    
    // 根據錯誤類型提供不同的錯誤訊息
    let errorMessage = '上傳失敗，請重試';
    let suggestion = '請檢查網路連接後再試一次';
    
    if (error.name === 'AbortError') {
      errorMessage = '上傳超時';
      suggestion = isMobile ? 
        '手機上傳可能需要更長時間，請確保網路穩定後重試' : 
        '上傳超時，請選擇較小的圖片重試';
    } else if (error.name === 'TypeError' && error.message.includes('fetch')) {
      errorMessage = '網路連接失敗';
      suggestion = '請檢查網路連接後再試一次';
    } else if (error.message.includes('timeout')) {
      errorMessage = '上傳超時';
      suggestion = '請選擇較小的圖片或檢查網路連接';
    }
    
    // 使用 CSS 樣式顯示錯誤
    showAvatarUploadError({
      message: errorMessage,
      suggestion: suggestion,
      tip: isMobile ? '手機上傳建議：選擇較小的圖片檔案，確保網路穩定' : ''
    });
  });
}

// 顯示頭貼上傳錯誤的 CSS 樣式提示 (v2.0)
function showAvatarUploadError(data) {
  // 移除現有的錯誤提示
  const existingError = document.getElementById('avatar-upload-error');
  if (existingError) {
    existingError.remove();
  }
  
  // 創建錯誤提示容器
  const errorContainer = document.createElement('div');
  errorContainer.id = 'avatar-upload-error';
  errorContainer.className = 'avatar-upload-error';
  
  // 構建錯誤訊息內容 - 更美觀的設計
  let errorContent = `
    <div class="error-header">
      <div class="error-icon-container">
        <svg class="error-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <circle cx="12" cy="12" r="10" fill="#fee2e2" stroke="#fca5a5" stroke-width="2"/>
          <path d="M12 8v4m0 4h.01" stroke="#dc2626" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </div>
      <div class="error-title-container">
        <h3 class="error-title">${data.message || '上傳失敗'}</h3>
      </div>
    </div>
  `;
  
  // 添加建議訊息
  if (data.suggestion) {
    errorContent += `<div class="error-suggestion">${data.suggestion}</div>`;
  }
  
  // 添加額外提示
  if (data.tip) {
    errorContent += `<div class="error-tip">${data.tip}</div>`;
  }
  
  // 添加支援的格式信息
  if (data.allowed_formats) {
    errorContent += `<div class="error-formats">支援的格式：${data.allowed_formats}</div>`;
  }
  
  // 添加檔案大小信息
  if (data.max_size && data.current_size) {
    errorContent += `<div class="error-size">檔案大小限制：${data.max_size}（目前：${data.current_size}）</div>`;
  }
  
  // 添加關閉按鈕
  errorContent += `
    <div class="error-actions">
      <button class="error-close-btn" onclick="closeAvatarUploadError()">
        <span>確定</span>
      </button>
    </div>
  `;
  
  errorContainer.innerHTML = errorContent;
  
  // 添加到頁面
  document.body.appendChild(errorContainer);
  
  // 添加 CSS 樣式（如果還沒有）
  if (!document.getElementById('avatar-upload-error-styles')) {
    const style = document.createElement('style');
    style.id = 'avatar-upload-error-styles';
    style.textContent = `
      .avatar-upload-error {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 20px;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        padding: 32px;
        max-width: 420px;
        width: 90%;
        z-index: 10000;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        animation: errorSlideIn 0.4s cubic-bezier(0.16, 1, 0.3, 1);
      }
      
      /* 確保沒有其他關閉按鈕影響我們的錯誤提示 */
      .avatar-upload-error .close-btn,
      .avatar-upload-error .close,
      .avatar-upload-error [class*="close"] {
        display: none !important;
      }
      
      .error-header {
        display: flex;
        align-items: flex-start;
        margin-bottom: 24px;
        gap: 16px;
      }
      
      .error-icon-container {
        flex-shrink: 0;
        margin-top: 2px;
      }
      
      .error-icon {
        width: 24px;
        height: 24px;
      }
      
      .error-title-container {
        flex: 1;
      }
      
      .error-title {
        font-size: 20px;
        font-weight: 600;
        color: #1f2937;
        margin: 0;
        line-height: 1.3;
      }
      
      .error-suggestion,
      .error-tip,
      .error-formats,
      .error-size {
        margin: 12px 0;
        padding: 16px;
        background: #f9fafb;
        border: 1px solid #f3f4f6;
        border-radius: 12px;
        font-size: 15px;
        color: #4b5563;
        line-height: 1.5;
      }
      
      .error-actions {
        margin-top: 28px;
        text-align: center;
      }
      
      .error-close-btn {
        background: #3b82f6;
        color: white;
        border: none;
        padding: 14px 32px;
        border-radius: 12px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        box-shadow: 0 4px 14px 0 rgba(59, 130, 246, 0.3);
        min-width: 120px;
      }
      
      .error-close-btn:hover {
        background: #2563eb;
        transform: translateY(-1px);
        box-shadow: 0 6px 20px 0 rgba(59, 130, 246, 0.4);
      }
      
      .error-close-btn:active {
        transform: translateY(0);
        box-shadow: 0 2px 8px 0 rgba(59, 130, 246, 0.3);
      }
      
      @keyframes errorSlideIn {
        from {
          opacity: 0;
          transform: translate(-50%, -60%) scale(0.95);
        }
        to {
          opacity: 1;
          transform: translate(-50%, -50%) scale(1);
        }
      }
      
      /* 手機版適配 */
      @media (max-width: 768px) {
        .avatar-upload-error {
          max-width: 360px;
          padding: 24px;
          margin: 20px;
        }
        
        .error-title {
          font-size: 18px;
        }
        
        .error-suggestion,
        .error-tip,
        .error-formats,
        .error-size {
          font-size: 14px;
          padding: 14px;
        }
        
        .error-close-btn {
          padding: 12px 28px;
          font-size: 15px;
        }
      }
    `;
    document.head.appendChild(style);
  }
  
  // 3秒後自動關閉（可選）
  setTimeout(() => {
    if (document.getElementById('avatar-upload-error')) {
      closeAvatarUploadError();
    }
  }, 5000);
}

// 關閉頭貼上傳錯誤提示
function closeAvatarUploadError() {
  const errorElement = document.getElementById('avatar-upload-error');
  if (errorElement) {
    errorElement.style.animation = 'errorSlideOut 0.3s ease-in';
    setTimeout(() => {
      errorElement.remove();
    }, 300);
  }
}

// 添加關閉動畫
if (!document.getElementById('avatar-upload-error-animations')) {
  const style = document.createElement('style');
  style.id = 'avatar-upload-error-animations';
  style.textContent = `
    @keyframes errorSlideOut {
      from {
        opacity: 1;
        transform: translate(-50%, -50%);
      }
      to {
        opacity: 0;
        transform: translate(-50%, -60%);
      }
    }
  `;
  document.head.appendChild(style);
} 