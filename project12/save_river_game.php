<?php
session_start();
require_once 'db_connect.php';

// 檢查用戶是否已登入
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => '未登入']);
    exit();
}

// 檢查請求方法
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => '方法不允許']);
    exit();
}

// 獲取POST數據
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => '無效的數據格式']);
    exit();
}

$user_id = $_SESSION['user_id'];
$difficulty = $input['difficulty'] ?? '';
$score = intval($input['score'] ?? 0);
$steps = intval($input['steps'] ?? 0);
$game_time = intval($input['gameTime'] ?? 0);
$completed = $input['completed'] ?? false;

// 驗證數據
if (!in_array($difficulty, ['easy', 'normal', 'hard'])) {
    http_response_code(400);
    echo json_encode(['error' => '無效的難度']);
    exit();
}

if ($score < 0 || $steps < 0 || $game_time < 0) {
    http_response_code(400);
    echo json_encode(['error' => '無效的分數或步數']);
    exit();
}

try {
    // 檢查是否已有river_game_records表，如果沒有則創建
    $checkTable = "SHOW TABLES LIKE 'river_game_records'";
    $tableExists = $conn->query($checkTable)->num_rows > 0;
    
    if (!$tableExists) {
        $createTable = "CREATE TABLE river_game_records (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            difficulty VARCHAR(10) NOT NULL,
            score INT NOT NULL DEFAULT 0,
            steps INT NOT NULL DEFAULT 0,
            game_time INT NOT NULL DEFAULT 0,
            completed BOOLEAN NOT NULL DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_difficulty (user_id, difficulty),
            INDEX idx_score (score DESC),
            INDEX idx_created_at (created_at DESC)
        )";
        
        if (!$conn->query($createTable)) {
            throw new Exception("創建表格失敗: " . $conn->error);
        }
    }
    
    // 插入遊戲記錄
    $stmt = $conn->prepare("INSERT INTO river_game_records (user_id, difficulty, score, steps, game_time, completed) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isiiis", $user_id, $difficulty, $score, $steps, $game_time, $completed);
    
    if (!$stmt->execute()) {
        throw new Exception("保存遊戲記錄失敗: " . $stmt->error);
    }
    
    $record_id = $conn->insert_id;
    
    // 更新用戶總分（如果存在user_scores表）
    $checkUserScores = "SHOW TABLES LIKE 'user_scores'";
    if ($conn->query($checkUserScores)->num_rows > 0) {
        // 檢查用戶是否已有river遊戲記錄
        $checkUser = $conn->prepare("SELECT id FROM user_scores WHERE user_id = ? AND game_type = 'river'");
        $checkUser->bind_param("i", $user_id);
        $checkUser->execute();
        $result = $checkUser->get_result();
        
        if ($result->num_rows > 0) {
            // 更新現有記錄
            $updateScore = $conn->prepare("UPDATE user_scores SET 
                total_score = total_score + ?, 
                games_played = games_played + 1,
                best_score = GREATEST(best_score, ?),
                last_played = CURRENT_TIMESTAMP
                WHERE user_id = ? AND game_type = 'river'");
            $updateScore->bind_param("iii", $score, $score, $user_id);
            $updateScore->execute();
        } else {
            // 插入新記錄
            $insertScore = $conn->prepare("INSERT INTO user_scores (user_id, game_type, total_score, games_played, best_score, last_played) 
                VALUES (?, 'river', ?, 1, ?, CURRENT_TIMESTAMP)");
            $insertScore->bind_param("iii", $user_id, $score, $score);
            $insertScore->execute();
        }
    }
    
    // 檢查是否為最佳記錄
    $bestScore = 0;
    $bestSteps = 0;
    
    $checkBest = $conn->prepare("SELECT MAX(score) as best_score, MIN(steps) as best_steps 
        FROM river_game_records 
        WHERE user_id = ? AND difficulty = ? AND completed = 1");
    $checkBest->bind_param("is", $user_id, $difficulty);
    $checkBest->execute();
    $bestResult = $checkBest->get_result();
    
    if ($bestResult->num_rows > 0) {
        $bestData = $bestResult->fetch_assoc();
        $bestScore = $bestData['best_score'] ?? 0;
        $bestSteps = $bestData['best_steps'] ?? 0;
    }
    
    // 返回成功響應
    echo json_encode([
        'success' => true,
        'record_id' => $record_id,
        'message' => '遊戲記錄已保存',
        'stats' => [
            'current_score' => $score,
            'current_steps' => $steps,
            'best_score' => $bestScore,
            'best_steps' => $bestSteps,
            'is_new_best_score' => $completed && $score >= $bestScore,
            'is_new_best_steps' => $completed && $steps > 0 && ($bestSteps == 0 || $steps <= $bestSteps)
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => '服務器錯誤: ' . $e->getMessage()]);
}

$conn->close();
?>
