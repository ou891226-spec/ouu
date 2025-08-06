function previewAndUploadAvatar(event) {
  const file = event.target.files[0];
  if (!file) return;

  // 檢查檔案類型
  const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/tiff', 'image/tif', 'image/webp'];
  if (!allowedTypes.includes(file.type)) {
    alert('只允許上傳 JPG、PNG、GIF、TIFF 或 WebP 格式的圖片');
    return;
  }

  // 檢查檔案大小（5MB）
  if (file.size > 5 * 1024 * 1024) {
    alert('檔案大小不能超過 5MB');
    return;
  }

  const reader = new FileReader();
  reader.onload = function(e) {
    const avatarImages = document.querySelectorAll('.profile-avatar, #profileAvatarImg');
    avatarImages.forEach(img => {
      img.src = e.target.result;
    });
  };
  reader.readAsDataURL(file);

  // 使用 AJAX 上傳
  const formData = new FormData();
  formData.append('avatar', file);

  fetch('upload_avatar.php', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
      .then(data => {
      if (data.success) {
        // 靜默更新所有頁面的頭像，不顯示成功訊息
        // 直接更新頭像，不重新載入頁面
        const avatarImages = document.querySelectorAll('.profile-avatar, #profileAvatarImg');
        avatarImages.forEach(img => {
          // 使用新的頭像路徑
          img.src = data.avatar_url + '?t=' + new Date().getTime();
        });
      } else {
        // 只在失敗時顯示錯誤訊息
        let errorMessage = data.message || '上傳失敗';
        if (data.suggestion) {
          errorMessage += '\n\n建議：' + data.suggestion;
        }
        alert(errorMessage);
        // 恢復原來的頭像（重新載入頁面或從session獲取）
        location.reload();
      }
    })
  .catch(error => {
    console.error('上傳錯誤:', error);
    alert('上傳失敗，請重試');
    // 恢復原來的頭像
    location.reload();
  });
} 