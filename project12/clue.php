<?php
require_once 'db_connect.php';
// 隨機取一題
$stmt = $pdo->query('SELECT * FROM questions ORDER BY RAND() LIMIT 1');
$question = $stmt->fetch();
if (!$question) {
    die('找不到題目');
}
// 圖片路徑
$image_path = 'img/two people-1_0.jpg';
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <title>圖片線索問答遊戲</title>
    <link rel="stylesheet" href="css/clue.css">
    <script src="js/clue.js"></script>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; margin-top: 40px; }
        #question-block, #result-block { display: none; }
        .option-btn { margin: 8px; padding: 10px 30px; font-size: 18px; cursor: pointer; }
        #image-block img { max-width: 400px; max-height: 300px; }
    </style>
</head>
<body>
    <div class="main-container"
         data-display-time="<?= (int)$question['display_time'] ?>"
         data-correct-answer="<?= htmlspecialchars($question['correct_answer_text']) ?>">
        <h2>請仔細觀察下方圖片，10秒後將進行提問！</h2>
        <div id="image-block">
            <img src="<?= htmlspecialchars($image_path) ?>" alt="題目圖片">
        </div>
        <div id="question-block">
            <h3><?= htmlspecialchars($question['question_text']) ?></h3>
            <form id="answer-form">
                <button type="button" class="option-btn" data-value="<?= htmlspecialchars($question['option_1']) ?>"> <?= htmlspecialchars($question['option_1']) ?> </button>
                <button type="button" class="option-btn" data-value="<?= htmlspecialchars($question['option_2']) ?>"> <?= htmlspecialchars($question['option_2']) ?> </button>
                <button type="button" class="option-btn" data-value="<?= htmlspecialchars($question['option_3']) ?>"> <?= htmlspecialchars($question['option_3']) ?> </button>
                <button type="button" class="option-btn" data-value="<?= htmlspecialchars($question['option_4']) ?>"> <?= htmlspecialchars($question['option_4']) ?> </button>
            </form>
        </div>
        <div id="result-block">
            <h3 id="result-msg"></h3>
            <p>正確答案：<span id="correct-answer"></span></p>
        </div>
        <div class="control-btns">
            <button id="pauseBtn" class="orange-btn">暫停遊戲</button>
            <button id="endBtn" class="red-btn">結束遊戲</button>
            <button id="resetBtn" class="blue-btn">重新開始</button>
        </div>
    </div>
</body>
</html> 