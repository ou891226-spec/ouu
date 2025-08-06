<?php
session_start();
require_once '../db.php';

// 檢查管理員權限
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$admin_name = $_SESSION['admin_name'] ?? '管理員';

// 處理添加/編輯題目
if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' || $action === 'edit') {
        $question_text = trim($_POST['question_text']);
        $correct_answer = trim($_POST['correct_answer']);
        $game_type = $_POST['game_type'];
        $difficulty = $_POST['difficulty'];
        $points = intval($_POST['points']);
        $options = $_POST['options'] ?? [];
        
        if ($question_text && $correct_answer && $game_type) {
            try {
                if ($action === 'add') {
                    $sql = "INSERT INTO game_questions (game_type, question_text, correct_answer, options, difficulty, points) VALUES (?, ?, ?, ?, ?, ?)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$game_type, $question_text, $correct_answer, json_encode($options), $difficulty, $points]);
                    $success_message = "題目添加成功！";
                } else {
                    $question_id = intval($_POST['question_id']);
                    $sql = "UPDATE game_questions SET game_type=?, question_text=?, correct_answer=?, options=?, difficulty=?, points=? WHERE id=?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$game_type, $question_text, $correct_answer, json_encode($options), $difficulty, $points, $question_id]);
                    $success_message = "題目更新成功！";
                }
            } catch (Exception $e) {
                $error_message = "操作失敗：" . $e->getMessage();
            }
        } else {
            $error_message = "請填寫所有必填欄位！";
        }
    } elseif ($action === 'delete') {
        $question_id = intval($_POST['question_id']);
        try {
            $sql = "DELETE FROM game_questions WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$question_id]);
            $success_message = "題目刪除成功！";
        } catch (Exception $e) {
            $error_message = "刪除失敗：" . $e->getMessage();
        }
    }
}

// 獲取題目列表
$game_type_filter = $_GET['game_type'] ?? '';
$difficulty_filter = $_GET['difficulty'] ?? '';
$search = $_GET['search'] ?? '';

$where_conditions = [];
$params = [];

if ($game_type_filter) {
    $where_conditions[] = "game_type = ?";
    $params[] = $game_type_filter;
}

if ($difficulty_filter) {
    $where_conditions[] = "difficulty = ?";
    $params[] = $difficulty_filter;
}

if ($search) {
    $where_conditions[] = "(question_text LIKE ? OR correct_answer LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// 分頁
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// 獲取總記錄數
$count_sql = "SELECT COUNT(*) FROM game_questions $where_clause";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_records = $count_stmt->fetchColumn();
$total_pages = ceil($total_records / $per_page);

// 獲取題目列表
$sql = "SELECT * FROM game_questions $where_clause ORDER BY created_at DESC LIMIT $per_page OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$questions = $stmt->fetchAll();

// 獲取統計數據
$stats_sql = "SELECT 
    COUNT(*) as total_questions,
    COUNT(DISTINCT game_type) as game_types,
    AVG(points) as avg_points
FROM game_questions $where_clause";
$stats_stmt = $pdo->prepare($stats_sql);
$stats_stmt->execute($params);
$stats = $stats_stmt->fetch();
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>題目管理</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; }
        .header { background: white; padding: 20px; margin-bottom: 20px; border-radius: 5px; }
        .nav { background: white; padding: 15px; margin-bottom: 20px; border-radius: 5px; }
        .nav a { margin-right: 20px; text-decoration: none; color: #007bff; }
        .nav a:hover { text-decoration: underline; }
        .filters { background: white; padding: 20px; margin-bottom: 20px; border-radius: 5px; }
        .filters form { display: flex; gap: 15px; align-items: end; flex-wrap: wrap; }
        .filters select, .filters input { padding: 8px; border: 1px solid #ddd; border-radius: 3px; }
        .filters button { 
            padding: 8px 15px; 
            background: #007bff; 
            color: white; 
            border: none; 
            border-radius: 3px; 
            cursor: pointer;
            font-size: 14px;
            min-width: 80px;
        }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 20px; }
        .stat-card { background: white; padding: 20px; border-radius: 5px; text-align: center; }
        .stat-card h3 { margin: 0; color: #007bff; font-size: 24px; }
        .stat-card p { margin: 5px 0 0 0; color: #666; }
        .questions { background: white; padding: 20px; border-radius: 5px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; font-weight: bold; }
        .pagination { margin-top: 20px; text-align: center; }
        .pagination a { margin: 0 5px; padding: 8px 12px; text-decoration: none; background: #007bff; color: white; border-radius: 3px; }
        .pagination a:hover { background: #0056b3; }
        .logout { 
            float: right; 
            background: #dc3545; 
            color: white; 
            padding: 8px 16px; 
            text-decoration: none; 
            border-radius: 5px; 
            font-weight: bold;
            transition: background-color 0.3s ease;
            margin-top: -85px;
        }
        .logout:hover { 
            background: #c82333; 
            text-decoration: none;
        }
        .btn { padding: 5px 10px; text-decoration: none; border-radius: 3px; color: white; font-size: 12px; }
        .btn-edit { background: #ffc107; }
        .btn-delete { background: #dc3545; }
        .btn-add { background: #28a745; padding: 10px 20px; font-size: 14px; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); }
        .modal-content { background-color: white; margin: 2% auto; padding: 20px; border-radius: 5px; width: 80%; max-width: 600px; max-height: 85vh; overflow-y: auto; }
        .close { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
        .close:hover { color: #000; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 3px; }
        .form-group textarea { height: 100px; resize: vertical; }
        .alert { padding: 10px; margin-bottom: 15px; border-radius: 3px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .options-container { margin-top: 10px; }
        .option-item { display: flex; gap: 10px; margin-bottom: 5px; }
        .option-item input { flex: 1; }
        .option-item button { padding: 5px 10px; background: #dc3545; color: white; border: none; border-radius: 3px; cursor: pointer; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>題目管理</h1>
            <p>歡迎，<?php echo htmlspecialchars($admin_name); ?></p>
            <a href="logout.php" class="logout">登出</a>
        </div>
        
        <div class="nav">
            <a href="index.php">首頁</a>
            <a href="game_records.php">遊戲紀錄</a>
            <a href="user_behavior.php">行為軌跡</a>
            <a href="question_management.php">題目管理</a>
            <a href="user_management.php">用戶管理</a>
        </div>
        
        <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        
        <div class="filters">
            <form method="GET">
                <div>
                    <label>搜尋：</label>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="題目內容或答案">
                </div>
                <div>
                    <label>遊戲類型：</label>
                    <select name="game_type">
                        <option value="">全部</option>
                        <option value="記憶力" <?php echo $game_type_filter === '記憶力' ? 'selected' : ''; ?>>記憶力</option>
                        <option value="反應力" <?php echo $game_type_filter === '反應力' ? 'selected' : ''; ?>>反應力</option>
                        <option value="邏輯力" <?php echo $game_type_filter === '邏輯力' ? 'selected' : ''; ?>>邏輯力</option>
                    </select>
                </div>
                <div>
                    <label>難度：</label>
                    <select name="difficulty">
                        <option value="">全部</option>
                        <option value="easy" <?php echo $difficulty_filter === 'easy' ? 'selected' : ''; ?>>簡單</option>
                        <option value="medium" <?php echo $difficulty_filter === 'medium' ? 'selected' : ''; ?>>中等</option>
                        <option value="hard" <?php echo $difficulty_filter === 'hard' ? 'selected' : ''; ?>>困難</option>
                    </select>
                </div>
                <button type="submit">篩選</button>
                <a href="question_management.php" class="btn btn-add">重置</a>
                <button type="button" class="btn btn-add" onclick="openAddModal()">添加題目</button>
            </form>
        </div>
        
        <div class="stats">
            <div class="stat-card">
                <h3><?php echo number_format($stats['total_questions']); ?></h3>
                <p>總題目數</p>
            </div>
            <div class="stat-card">
                <h3><?php echo number_format($stats['game_types']); ?></h3>
                <p>遊戲類型</p>
            </div>
            <div class="stat-card">
                <h3><?php echo round($stats['avg_points']); ?></h3>
                <p>平均分數</p>
            </div>
        </div>
        
        <div class="questions">
            <h2>題目列表 (共 <?php echo number_format($total_records); ?> 題)</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>遊戲類型</th>
                        <th>題目內容</th>
                        <th>正確答案</th>
                        <th>難度</th>
                        <th>分數</th>
                        <th>創建時間</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($questions as $question): ?>
                    <tr>
                        <td><?php echo $question['id']; ?></td>
                        <td><?php echo htmlspecialchars($question['game_type']); ?></td>
                        <td><?php echo htmlspecialchars(substr($question['question_text'], 0, 50)) . (strlen($question['question_text']) > 50 ? '...' : ''); ?></td>
                        <td><?php echo htmlspecialchars(substr($question['correct_answer'], 0, 30)) . (strlen($question['correct_answer']) > 30 ? '...' : ''); ?></td>
                        <td>
                            <?php 
                            $difficulty_labels = ['easy' => '簡單', 'medium' => '中等', 'hard' => '困難'];
                            echo $difficulty_labels[$question['difficulty']] ?? $question['difficulty'];
                            ?>
                        </td>
                        <td><?php echo $question['points']; ?></td>
                        <td><?php echo date('d日 H:i', strtotime($question['created_at'])); ?></td>
                        <td>
                            <a href="#" class="btn btn-edit" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($question)); ?>)">編輯</a>
                            <a href="#" class="btn btn-delete" onclick="deleteQuestion(<?php echo $question['id']; ?>)">刪除</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page-1; ?>&game_type=<?php echo urlencode($game_type_filter); ?>&difficulty=<?php echo urlencode($difficulty_filter); ?>&search=<?php echo urlencode($search); ?>">上一頁</a>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
                    <a href="?page=<?php echo $i; ?>&game_type=<?php echo urlencode($game_type_filter); ?>&difficulty=<?php echo urlencode($difficulty_filter); ?>&search=<?php echo urlencode($search); ?>" <?php echo $i === $page ? 'style="background: #0056b3;"' : ''; ?>><?php echo $i; ?></a>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page+1; ?>&game_type=<?php echo urlencode($game_type_filter); ?>&difficulty=<?php echo urlencode($difficulty_filter); ?>&search=<?php echo urlencode($search); ?>">下一頁</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- 添加/編輯題目模態框 -->
    <div id="questionModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2 id="modalTitle">添加題目</h2>
            <form method="POST" id="questionForm">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="question_id" id="questionId" value="">
                
                <div class="form-group">
                    <label>遊戲類型：</label>
                    <select name="game_type" required>
                        <option value="">請選擇遊戲類型</option>
                        <option value="算菜錢遊戲">算菜錢遊戲</option>
                        <option value="記憶力">記憶力</option>
                        <option value="反應力">反應力</option>
                        <option value="邏輯力">邏輯力</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>題目內容：</label>
                    <textarea name="question_text" required placeholder="請輸入題目內容"></textarea>
                </div>
                
                <div class="form-group">
                    <label>正確答案：</label>
                    <input type="text" name="correct_answer" required placeholder="請輸入正確答案">
                </div>
                
                <div class="form-group">
                    <label>選項（可選）：</label>
                    <div id="optionsContainer" class="options-container">
                        <div class="option-item">
                            <input type="text" name="options[]" placeholder="選項1">
                            <button type="button" onclick="removeOption(this)">刪除</button>
                        </div>
                    </div>
                    <button type="button" onclick="addOption()">添加選項</button>
                </div>
                
                <div class="form-group">
                    <label>難度：</label>
                    <select name="difficulty" required>
                        <option value="easy">簡單</option>
                        <option value="medium">中等</option>
                        <option value="hard">困難</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>分數：</label>
                    <input type="number" name="points" value="10" min="1" max="100" required>
                </div>
                
                <button type="submit" class="btn btn-add">保存</button>
                <button type="button" class="btn btn-delete" onclick="closeModal()">取消</button>
            </form>
        </div>
    </div>
    
    <!-- 刪除確認表單 -->
    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="question_id" id="deleteQuestionId">
    </form>
    
    <script>
        function openAddModal() {
            document.getElementById('modalTitle').textContent = '添加題目';
            document.getElementById('formAction').value = 'add';
            document.getElementById('questionId').value = '';
            document.getElementById('questionForm').reset();
            document.getElementById('questionModal').style.display = 'block';
        }
        
        function openEditModal(question) {
            document.getElementById('modalTitle').textContent = '編輯題目';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('questionId').value = question.id;
            
            const form = document.getElementById('questionForm');
            form.querySelector('select[name="game_type"]').value = question.game_type;
            form.querySelector('textarea[name="question_text"]').value = question.question_text;
            form.querySelector('input[name="correct_answer"]').value = question.correct_answer;
            form.querySelector('select[name="difficulty"]').value = question.difficulty;
            form.querySelector('input[name="points"]').value = question.points;
            
            // 處理選項
            const optionsContainer = document.getElementById('optionsContainer');
            optionsContainer.innerHTML = '';
            
            if (question.options) {
                try {
                    const options = JSON.parse(question.options);
                    options.forEach(option => {
                        addOption(option);
                    });
                } catch (e) {
                    addOption();
                }
            } else {
                addOption();
            }
            
            document.getElementById('questionModal').style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('questionModal').style.display = 'none';
        }
        
        function addOption(value = '') {
            const container = document.getElementById('optionsContainer');
            const div = document.createElement('div');
            div.className = 'option-item';
            div.innerHTML = `
                <input type="text" name="options[]" placeholder="選項" value="${value}">
                <button type="button" onclick="removeOption(this)">刪除</button>
            `;
            container.appendChild(div);
        }
        
        function removeOption(button) {
            button.parentElement.remove();
        }
        
        function deleteQuestion(questionId) {
            if (confirm('確定要刪除這個題目嗎？')) {
                document.getElementById('deleteQuestionId').value = questionId;
                document.getElementById('deleteForm').submit();
            }
        }
        
        // 點擊模態框外部關閉
        window.onclick = function(event) {
            const modal = document.getElementById('questionModal');
            if (event.target === modal) {
                closeModal();
            }
        }
    </script>
</body>
</html> 