function fetchUserScore() {
  fetch('get_score.php')
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        document.getElementById('scoreValue').textContent = data.score;
      } else {
        console.warn('未登入或無法取得分數');
      }
    })
    .catch(error => {
      console.error('取得分數失敗:', error);
    });
}

document.addEventListener('DOMContentLoaded', fetchUserScore);
