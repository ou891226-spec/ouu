<?php
require_once 'db_connect.php';

echo "<h2>添加所有難度的題目</h2>";

try {
    // 檢查現有題目數量
    $check_sql = "SELECT difficulty, COUNT(*) as count FROM questions GROUP BY difficulty";
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->execute();
    $current_counts = $check_stmt->fetchAll();
    
    echo "<h3>目前各難度題目數量：</h3>";
    foreach ($current_counts as $count) {
        echo "<p><strong>{$count['difficulty']}</strong>: {$count['count']} 題</p>";
    }
    
    // 普通難度的題目
    $normal_questions = [
        [
            'image_path' => 'two people-1.jpg',
            'question_text' => '請問圖中兩個人分別坐在什麼顏色的椅子上？',
            'option_1' => '都是粉色',
            'option_2' => '都是藍色',
            'option_3' => '左邊粉色，右邊藍色',
            'option_4' => '左邊藍色，右邊粉色',
            'correct_answer_text' => '都是粉色',
            'display_time' => 9,
            'difficulty' => '普通'
        ],
        [
            'image_path' => 'cat.jpg',
            'question_text' => '請問這隻貓的毛色是什麼？',
            'option_1' => '橘色',
            'option_2' => '黑色',
            'option_3' => '白色',
            'option_4' => '灰色',
            'correct_answer_text' => '橘色',
            'display_time' => 9,
            'difficulty' => '普通'
        ],
        [
            'image_path' => 'dog.jpg',
            'question_text' => '請問這隻狗的品種是什麼？',
            'option_1' => '拉布拉多',
            'option_2' => '金毛',
            'option_3' => '哈士奇',
            'option_4' => '柴犬',
            'correct_answer_text' => '拉布拉多',
            'display_time' => 9,
            'difficulty' => '普通'
        ],
        [
            'image_path' => 'night.jpg',
            'question_text' => '請問這張夜景照片是在什麼時候拍攝的？',
            'option_1' => '黃昏',
            'option_2' => '夜晚',
            'option_3' => '黎明',
            'option_4' => '正午',
            'correct_answer_text' => '夜晚',
            'display_time' => 9,
            'difficulty' => '普通'
        ],
        [
            'image_path' => '321.jpg',
            'question_text' => '請問圖中三個數字的中間數字是多少？',
            'option_1' => '1',
            'option_2' => '2',
            'option_3' => '3',
            'option_4' => '4',
            'correct_answer_text' => '2',
            'display_time' => 9,
            'difficulty' => '普通'
        ]
    ];
    
    // 困難難度的題目
    $hard_questions = [
        [
            'image_path' => 'two people-1.jpg',
            'question_text' => '請問左邊女生的頭髮是什麼顏色？',
            'option_1' => '黑色',
            'option_2' => '棕色',
            'option_3' => '金色',
            'option_4' => '紅色',
            'correct_answer_text' => '黑色',
            'display_time' => 8,
            'difficulty' => '困難'
        ],
        [
            'image_path' => 'two people-1.jpg',
            'question_text' => '請問右邊女生的鞋子是什麼顏色？',
            'option_1' => '黑色',
            'option_2' => '白色',
            'option_3' => '棕色',
            'option_4' => '灰色',
            'correct_answer_text' => '黑色',
            'display_time' => 8,
            'difficulty' => '困難'
        ],
        [
            'image_path' => 'cat.jpg',
            'question_text' => '請問這隻貓的眼睛是什麼顏色？',
            'option_1' => '綠色',
            'option_2' => '藍色',
            'option_3' => '黃色',
            'option_4' => '棕色',
            'correct_answer_text' => '綠色',
            'display_time' => 8,
            'difficulty' => '困難'
        ],
        [
            'image_path' => 'dog.jpg',
            'question_text' => '請問這隻狗的耳朵是什麼形狀？',
            'option_1' => '尖尖的',
            'option_2' => '圓圓的',
            'option_3' => '下垂的',
            'option_4' => '三角形的',
            'correct_answer_text' => '下垂的',
            'display_time' => 8,
            'difficulty' => '困難'
        ],
        [
            'image_path' => 'night.jpg',
            'question_text' => '請問圖中天空中有幾顆星星？',
            'option_1' => '3顆',
            'option_2' => '5顆',
            'option_3' => '7顆',
            'option_4' => '9顆',
            'correct_answer_text' => '5顆',
            'display_time' => 8,
            'difficulty' => '困難'
        ]
    ];
    
    // 插入題目
    $insert_sql = "INSERT INTO questions (image_path, question_text, option_1, option_2, option_3, option_4, correct_answer_text, display_time, difficulty) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $insert_stmt = $pdo->prepare($insert_sql);
    
    $added_normal = 0;
    $added_hard = 0;
    
    // 添加普通難度題目
    echo "<h3>添加普通難度題目：</h3>";
    foreach ($normal_questions as $question) {
        try {
            $insert_stmt->execute([
                $question['image_path'],
                $question['question_text'],
                $question['option_1'],
                $question['option_2'],
                $question['option_3'],
                $question['option_4'],
                $question['correct_answer_text'],
                $question['display_time'],
                $question['difficulty']
            ]);
            $added_normal++;
            echo "<p style='color: green;'>✅ 已添加普通題目: {$question['question_text']}</p>";
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                echo "<p style='color: orange;'>⚠️ 題目已存在: {$question['question_text']}</p>";
            } else {
                echo "<p style='color: red;'>❌ 添加失敗: {$question['question_text']}</p>";
            }
        }
    }
    
    // 添加困難難度題目
    echo "<h3>添加困難難度題目：</h3>";
    foreach ($hard_questions as $question) {
        try {
            $insert_stmt->execute([
                $question['image_path'],
                $question['question_text'],
                $question['option_1'],
                $question['option_2'],
                $question['option_3'],
                $question['option_4'],
                $question['correct_answer_text'],
                $question['display_time'],
                $question['difficulty']
            ]);
            $added_hard++;
            echo "<p style='color: green;'>✅ 已添加困難題目: {$question['question_text']}</p>";
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                echo "<p style='color: orange;'>⚠️ 題目已存在: {$question['question_text']}</p>";
            } else {
                echo "<p style='color: red;'>❌ 添加失敗: {$question['question_text']}</p>";
            }
        }
    }
    
    echo "<h3>添加完成！</h3>";
    echo "<p>成功添加了 $added_normal 個普通難度題目</p>";
    echo "<p>成功添加了 $added_hard 個困難難度題目</p>";
    
    // 檢查最終數量
    $final_check_sql = "SELECT difficulty, COUNT(*) as count FROM questions GROUP BY difficulty ORDER BY difficulty";
    $final_check_stmt = $pdo->prepare($final_check_sql);
    $final_check_stmt->execute();
    $final_counts = $final_check_stmt->fetchAll();
    
    echo "<h3>最終各難度題目數量：</h3>";
    foreach ($final_counts as $count) {
        echo "<p><strong>{$count['difficulty']}</strong>: {$count['count']} 題</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>錯誤: " . $e->getMessage() . "</p>";
}
?> 