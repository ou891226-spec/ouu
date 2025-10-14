<?php
// 設定錯誤報告等級，確保不會輸出HTML錯誤訊息
error_reporting(E_ERROR | E_WARNING | E_PARSE);
ini_set('display_errors', 0);

// 檢查是否為AJAX請求，如果是則設定API標識
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['q'])) {
    $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
}

// 使用靜默模式的資料庫連接
require_once "db_connect.php";
session_start();

$my_id = $_SESSION['member_id'] ?? null;

// 如果沒有登入，返回錯誤
if (!$my_id) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['found' => false, 'error' => 'not_logged_in']);
    exit;
}

// 處理 AJAX 查詢好友帳號
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['q'])) {
    // 清理所有輸出緩衝，確保只輸出JSON
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // 開始新的輸出緩衝
    ob_start();
    
    $q = trim($_POST['q']);
    $res = ['found'=>false];
    if ($q !== '') {
        // 先查詢用戶是否存在
        $sql = "SELECT m.member_id, m.member_name, m.account 
                FROM member m 
                WHERE (m.account = ? OR m.member_name LIKE ?) 
                AND m.member_id != ?";
        $stmt = $pdo->prepare($sql);
        $like_q = "%$q%";
        $stmt->execute([$q, $like_q, $my_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            // 檢查是否已經是好友
            $friend_check_sql = "SELECT COUNT(*) as is_friend 
                                 FROM friends 
                                 WHERE (member_id = ? AND friend_id = ?) 
                                 OR (member_id = ? AND friend_id = ?)";
            $friend_stmt = $pdo->prepare($friend_check_sql);
            $friend_stmt->execute([$my_id, $row['member_id'], $row['member_id'], $my_id]);
            $friend_check = $friend_stmt->fetch(PDO::FETCH_ASSOC);
            
            // 檢查是否有待確認的邀請（我發出的邀請）
            $pending_check_sql = "SELECT COUNT(*) as is_pending 
                                  FROM friend_requests 
                                  WHERE sender_id = ? AND receiver_id = ? AND status = 'pending'";
            $pending_stmt = $pdo->prepare($pending_check_sql);
            $pending_stmt->execute([$my_id, $row['member_id']]);
            $pending_check = $pending_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($friend_check['is_friend'] > 0) {
                // 已經是好友 
                $res = [
                    'found' => true,
                    'already_friend' => true,
                    'is_pending' => false,
                    'member_id' => $row['member_id'],
                    'name' => $row['member_name'],
                    'account' => $row['account']
                ];
            } elseif ($pending_check['is_pending'] > 0) {
                // 已送出邀請，等待確認
                $res = [
                    'found' => true,
                    'already_friend' => false,
                    'is_pending' => true,
                    'member_id' => $row['member_id'],
                    'name' => $row['member_name'],
                    'account' => $row['account']
                ];
            } else {
                // 不是好友且沒有待確認邀請，可以邀請
                $res = [
                    'found' => true,
                    'already_friend' => false,
                    'is_pending' => false,
                    'member_id' => $row['member_id'],
                    'name' => $row['member_name'],
                    'account' => $row['account']
                ];
            }
        }
    }
    
    // 清理所有輸出緩衝並確保只輸出JSON
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // 清除任何可能的輸出
    if (headers_sent()) {
        // 如果標頭已發送，直接返回錯誤
        die(json_encode(['found' => false, 'error' => 'headers_already_sent']));
    }
    
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, must-revalidate');
    
    echo json_encode($res, JSON_UNESCAPED_UNICODE);
    exit;
}

$q = isset($_GET['q']) ? trim($_GET['q']) : '';

// 查詢會員（排除自己、已加的好友、和等待邀請的用戶）
if ($q !== '') {
    $sql = "SELECT m.member_id, m.member_name, m.account, m.avatar 
            FROM member m 
            WHERE (m.account LIKE ? OR m.member_name LIKE ?) 
            AND m.member_id != ? 
            AND m.member_id NOT IN (
                SELECT f.friend_id FROM friends f WHERE f.member_id = ?
                UNION
                SELECT f.member_id FROM friends f WHERE f.friend_id = ?
            )
            AND m.member_id NOT IN (
                SELECT fr.receiver_id FROM friend_requests fr WHERE fr.sender_id = ? AND fr.status = 'pending'
            )
            LIMIT 20";
    $stmt = $pdo->prepare($sql);
    $like_q = "%$q%";
    $stmt->execute([$like_q, $like_q, $my_id, $my_id, $my_id, $my_id]);
} else {
    $sql = "SELECT m.member_id, m.member_name, m.account, m.avatar 
            FROM member m 
            WHERE m.member_id != ? 
            AND m.member_id NOT IN (
                SELECT f.friend_id FROM friends f WHERE f.member_id = ?
                UNION
                SELECT f.member_id FROM friends f WHERE f.friend_id = ?
            )
            AND m.member_id NOT IN (
                SELECT fr.receiver_id FROM friend_requests fr WHERE fr.sender_id = ? AND fr.status = 'pending'
            )
            ORDER BY m.member_id DESC 
            LIMIT 20";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$my_id, $my_id, $my_id, $my_id]);
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
        <!-- 返回按鈕 -->
        <button class="back-button" onclick="window.location.href='friend.php'" style="position:absolute;top:1rem;left:1.2rem;z-index:10;">
            <span class="back-arrow">←</span>
            <div class="back-label">返回</div>
        </button>
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
    <!-- 已經是好友 Modal -->
    <div id="already-friend-modal" class="invite-modal" style="display:none;">
        <div class="invite-modal-content">
            <div class="invite-modal-text" style="font-size:2rem; color:#000000;">您與此用戶已經是好友了！</div>
            <button class="back-friendlist-btn">返回好友列表</button>
        </div>
    </div>
    <!-- 已送出邀請 Modal -->
    <div id="pending-invite-modal" class="invite-modal" style="display:none;">
        <div class="invite-modal-content">
            <div class="invite-modal-text" style="font-size:2rem; color:#000000;">已送出邀請，等待對方確認好友</div>
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
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.onload = function() {
            console.log('Response received:', xhr.responseText);
            try {
                var res = JSON.parse(xhr.responseText);
                if (res.found) {
                if (res.already_friend) {
                    // 已經是好友，顯示已經是好友的modal
                    document.getElementById('already-friend-modal').style.display = 'flex';
                } else if (res.is_pending) {
                    // 已送出邀請，等待確認
                    document.getElementById('pending-invite-modal').style.display = 'flex';
                } else {
                    // 有此帳號且不是好友且沒有待確認邀請，彈出邀請 modal
                    var modal = document.getElementById('invite-modal');
                    document.getElementById('invite-modal-text').innerHTML = '請問您是否要邀請《' + res.name + '》加入此好友？';
                    modal.style.display = 'flex';
                    modal.dataset.memberId = res.member_id;
                }
                } else {
                    // 查無此帳號
                    document.getElementById('notfound-modal').style.display = 'flex';
                }
            } catch (e) {
                console.error('JSON parse error:', e);
                console.error('Raw response:', xhr.responseText);
                document.getElementById('notfound-modal').style.display = 'flex';
            }
        };
        xhr.send('q=' + encodeURIComponent(q));
    };

    // 關閉查無此帳號
    document.querySelectorAll('.back-friendlist-btn').forEach(function(btn){
        btn.onclick = function() {
            window.location.href = 'add-friend.php';
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
            if (xhr.status === 200 && xhr.responseText.trim() === 'success') {
                document.getElementById('success-modal').style.display = 'flex';
            } else if (xhr.responseText.includes('已經是好友')) {
                document.getElementById('already-friend-modal').style.display = 'flex';
            } else if (xhr.responseText.includes('已送出邀請')) {
                document.getElementById('pending-invite-modal').style.display = 'flex';
            } else {
                alert('邀請失敗，請稍後再試');
            }
        };
        xhr.onerror = function() {
            modal.style.display = 'none';
            alert('網路錯誤，請稍後再試');
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