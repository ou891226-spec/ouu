<?php
require_once "DB_open.php";
session_start();

$my_id = $_SESSION['member_id'];

// 處理 AJAX 查詢好友帳號
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['q'])) {
    $q = trim($_POST['q']);
    $res = ['found'=>false];
    if ($q !== '') {
        $sql = "SELECT member_id, member_name, account FROM member WHERE (account = ? OR member_name LIKE ?) AND member_id != ?";
        $stmt = $pdo->prepare($sql);
        $like_q = "%$q%";
        $stmt->execute([$q, $like_q, $my_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $res = [
                'found' => true,
                'member_id' => $row['member_id'],
                'name' => $row['member_name'],
                'account' => $row['account']
            ];
        }
    }
    header('Content-Type: application/json');
    echo json_encode($res);
    exit;
}

$q = isset($_GET['q']) ? trim($_GET['q']) : '';

// 查詢會員（排除自己）
if ($q !== '') {
    $sql = "SELECT member_id, member_name, account, avatar FROM member WHERE (account LIKE ? OR member_name LIKE ?) AND member_id != ?";
    $stmt = $pdo->prepare($sql);
    $like_q = "%$q%";
    $stmt->execute([$like_q, $like_q, $my_id]);
} else {
    $sql = "SELECT member_id, member_name, account, avatar FROM member WHERE member_id != ? LIMIT 10";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$my_id]);
}
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <title>加入好友</title>
    <link rel="stylesheet" href="css/add-friend.css">
</head>
<body>
    <div class="add-friend-modal">
        <div class="add-friend-title">請輸入好友姓名或帳號：</div>
        <form class="add-friend-searchbox" method="get" action="">
            <span style="font-size:1.5rem; color:#888;">&#128269;</span>
            <input type="text" name="q" placeholder="輸入帳號" value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>">
        </form>
        <div class="add-friend-list">
            <?php foreach ($members as $member): ?>
            <div class="add-friend-row">
                <div class="add-friend-avatar-block">
                    <img src="<?php echo htmlspecialchars($member['avatar'] ?? 'default.png'); ?>" class="add-friend-avatar">
                    <span class="add-friend-status-dot"></span>
                </div>
                <div class="add-friend-info">
                    <span class="add-friend-name"><?php echo htmlspecialchars($member['member_name']); ?></span>
                    <span class="add-friend-account">(<?php echo htmlspecialchars($member['account']); ?>)</span>
                </div>
                <button class="add-friend-invite-btn" data-id="<?php echo $member['member_id']; ?>">邀請</button>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <div id="invite-modal" class="invite-modal" style="display:none;">
        <div class="invite-modal-content">
            <div class="invite-modal-text" id="invite-modal-text">請問您是否要邀請加入此好友？</div>
            <div class="invite-modal-btns">
                <button class="invite-modal-yes">是</button>
                <button class="invite-modal-no">否</button>
            </div>
        </div>
    </div>
    <!-- 查無此帳號 Modal -->
    <div id="notfound-modal" class="invite-modal" style="display:none;">
        <div class="invite-modal-content">
            <div class="invite-modal-text" style="font-size:2rem;">查無此帳號，請重新輸入帳號</div>
            <button class="back-friendlist-btn">返回好友列表</button>
        </div>
    </div>
    <!-- 已送出邀請 Modal -->
    <div id="success-modal" class="invite-modal" style="display:none;">
        <div class="invite-modal-content">
            <div class="invite-modal-text" style="font-size:2rem;">您已成功送出好友邀請！</div>
            <button class="back-friendlist-btn">返回好友列表</button>
        </div>
    </div>
    <script>
    // 攔截搜尋表單送出
    document.querySelector('.add-friend-searchbox').onsubmit = function(e) {
        e.preventDefault();
        var q = this.querySelector('input[name="q"]').value.trim();
        if (!q) return;
        // AJAX 查詢帳號
        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'add-friend.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onload = function() {
            var res = JSON.parse(xhr.responseText);
            if (res.found) {
                // 有此帳號，彈出邀請 modal
                var modal = document.getElementById('invite-modal');
                document.getElementById('invite-modal-text').innerHTML = '請問您是否要邀請《' + res.name + '》加入此好友？';
                modal.style.display = 'flex';
                modal.dataset.memberId = res.member_id;
            } else {
                // 查無此帳號
                document.getElementById('notfound-modal').style.display = 'flex';
            }
        };
        xhr.send('q=' + encodeURIComponent(q));
    };

    // 關閉查無此帳號 modal
    document.querySelectorAll('.back-friendlist-btn').forEach(function(btn){
        btn.onclick = function() {
            this.closest('.invite-modal').style.display = 'none';
        };
    });

    // 原本邀請 modal 的「否」按鈕
    document.querySelector('.invite-modal-no').onclick = function() {
        document.getElementById('invite-modal').style.display = 'none';
    };

    // 點選「是」送出邀請
    document.querySelector('.invite-modal-yes').onclick = function() {
        var modal = document.getElementById('invite-modal');
        var memberId = modal.dataset.memberId;
        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'send-invite.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onload = function() {
            modal.style.display = 'none';
            document.getElementById('success-modal').style.display = 'flex';
        };
        xhr.send('friend_id=' + encodeURIComponent(memberId));
    };

    // 點下「邀請」按鈕（列表）
    document.querySelectorAll('.add-friend-invite-btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var name = this.parentElement.querySelector('.add-friend-name').textContent;
            var modal = document.getElementById('invite-modal');
            document.getElementById('invite-modal-text').innerHTML = '請問您是否要邀請《' + name + '》加入此好友？';
            modal.style.display = 'flex';
            modal.dataset.memberId = this.dataset.id;
        });
    });
    </script>
</body>
</html>