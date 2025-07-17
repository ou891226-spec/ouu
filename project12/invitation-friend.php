<?php
require_once "DB_open.php";
session_start();
$my_id = $_SESSION['member_id'];
// 查詢尚未處理的好友邀請
$sql = "SELECT fr.request_id, m.member_id, m.member_name, m.account, m.avatar FROM friend_requests fr JOIN member m ON fr.sender_id = m.member_id WHERE fr.receiver_id = ? AND fr.status = 'pending' ORDER BY fr.request_id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$my_id]);
$invites = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <title>邀請通知</title>
    <link rel="stylesheet" href="css/invitation-friend.css">
</head>
<body>
    <div class="invitation-modal">
        <div class="invitation-title">邀請通知：</div>
        <div class="invitation-list">
            <?php foreach ($invites as $invite): ?>
            <div class="invitation-row" data-request-id="<?php echo $invite['request_id']; ?>">
                <div class="invitation-avatar-block">
                    <img src="<?php echo htmlspecialchars($invite['avatar'] ?? 'default.png'); ?>" class="invitation-avatar">
                    <span class="invitation-status-dot"></span>
                </div>
                <div class="invitation-info">
                    <span class="invitation-name"><?php echo htmlspecialchars($invite['member_name']); ?></span>
                    <span class="invitation-account">(<?php echo htmlspecialchars($invite['account']); ?>)</span>
                </div>
                <div class="invitation-actions">
                    <button class="invitation-accept-btn">&#10004;</button>
                    <button class="invitation-reject-btn">&#10006;</button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <!-- 成功成為好友 Modal -->
    <div id="accept-modal" class="invitation-result-modal" style="display:none;">
        <div class="invitation-result-content">
            <div class="invitation-result-text">您已成功成為好友！</div>
            <button class="back-friendlist-btn" onclick="window.location.href='friend.php'">返回好友列表</button>
        </div>
    </div>
    <!-- 取消成為好友 Modal -->
    <div id="reject-modal" class="invitation-result-modal" style="display:none;">
        <div class="invitation-result-content">
            <div class="invitation-result-text">您已取消成為好友。</div>
            <button class="back-friendlist-btn" onclick="window.location.href='friend.php'">返回好友列表</button>
        </div>
    </div>
    <script>
    document.querySelectorAll('.invitation-accept-btn').forEach(function(btn) {
        btn.onclick = function() {
            var row = this.closest('.invitation-row');
            var requestId = row.dataset.requestId;
            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'invitation-action.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function() {
                if (xhr.status === 200) {
                    row.remove();
                    document.getElementById('accept-modal').style.display = 'flex';
                } else {
                    alert('操作失敗，請稍後再試');
                }
            };
            xhr.send('action=accept&request_id=' + encodeURIComponent(requestId));
        };
    });
    document.querySelectorAll('.invitation-reject-btn').forEach(function(btn) {
        btn.onclick = function() {
            var row = this.closest('.invitation-row');
            var requestId = row.dataset.requestId;
            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'invitation-action.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function() {
                if (xhr.status === 200) {
                    row.remove();
                    document.getElementById('reject-modal').style.display = 'flex';
                } else {
                    alert('操作失敗，請稍後再試');
                }
            };
            xhr.send('action=reject&request_id=' + encodeURIComponent(requestId));
        };
    });
    </script>
</body>
</html> 