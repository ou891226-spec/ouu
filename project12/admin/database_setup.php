<?php
require_once '../db.php';

try {
    // 創建後台管理員表
    $admin_table = "
    CREATE TABLE IF NOT EXISTS admin_users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        name VARCHAR(100) NOT NULL,
        role ENUM('admin', 'super_admin') DEFAULT 'admin',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_login TIMESTAMP NULL,
        status ENUM('active', 'inactive') DEFAULT 'active'
    )";
    $pdo->exec($admin_table);
    
    // 創建用戶行為軌跡表
    $behavior_table = "
    CREATE TABLE IF NOT EXISTS user_behavior_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        member_id INT NOT NULL,
        action_type ENUM('page_view', 'game_start', 'game_complete', 'game_exit', 'login', 'logout') NOT NULL,
        page_url VARCHAR(255),
        game_type VARCHAR(100),
        session_id VARCHAR(100),
        ip_address VARCHAR(45),
        user_agent TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (member_id) REFERENCES member(member_id) ON DELETE CASCADE
    )";
    $pdo->exec($behavior_table);
    
    // 創建機構管理表
    $institution_table = "
    CREATE TABLE IF NOT EXISTS institutions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(200) NOT NULL,
        contact_person VARCHAR(100),
        contact_email VARCHAR(100),
        contact_phone VARCHAR(20),
        address TEXT,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $pdo->exec($institution_table);
    
    // 創建機構用戶關聯表
    $institution_users_table = "
    CREATE TABLE IF NOT EXISTS institution_users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        institution_id INT NOT NULL,
        member_id INT NOT NULL,
        assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        status ENUM('active', 'inactive') DEFAULT 'active',
        FOREIGN KEY (institution_id) REFERENCES institutions(id),
        FOREIGN KEY (member_id) REFERENCES member(member_id) ON DELETE CASCADE,
        UNIQUE KEY unique_institution_user (institution_id, member_id)
    )";
    $pdo->exec($institution_users_table);
    
    // 創建題目管理表
    $questions_table = "
    CREATE TABLE IF NOT EXISTS game_questions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        game_type VARCHAR(100) NOT NULL,
        question_text TEXT NOT NULL,
        correct_answer TEXT NOT NULL,
        options JSON,
        difficulty ENUM('easy', 'medium', 'hard') DEFAULT 'medium',
        category VARCHAR(100),
        points INT DEFAULT 10,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    $pdo->exec($questions_table);
    
    // 創建系統設定表
    $settings_table = "
    CREATE TABLE IF NOT EXISTS system_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) UNIQUE NOT NULL,
        setting_value TEXT,
        setting_type ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
        description TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    $pdo->exec($settings_table);
    
    // 創建用戶健康評估表
    $health_assessment_table = "
    CREATE TABLE IF NOT EXISTS health_assessments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        member_id INT NOT NULL,
        assessment_date DATE NOT NULL,
        memory_score DECIMAL(5,2),
        reaction_score DECIMAL(5,2),
        logic_score DECIMAL(5,2),
        overall_score DECIMAL(5,2),
        assessment_notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (member_id) REFERENCES member(member_id) ON DELETE CASCADE
    )";
    $pdo->exec($health_assessment_table);
    
    // 創建用戶線上狀態表
    $online_status_table = "
    CREATE TABLE IF NOT EXISTS user_online_status (
        id INT AUTO_INCREMENT PRIMARY KEY,
        member_id INT NOT NULL,
        session_id VARCHAR(100) NOT NULL,
        last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        is_online TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (member_id) REFERENCES member(member_id) ON DELETE CASCADE,
        UNIQUE KEY unique_member_session (member_id, session_id)
    )";
    $pdo->exec($online_status_table);
    
    // 插入預設管理員帳號
    $admin_check = $pdo->query("SELECT COUNT(*) FROM admin_users WHERE username = 'admin'");
    if ($admin_check->fetchColumn() == 0) {
        $admin_insert = "
        INSERT INTO admin_users (username, password, name, role) 
        VALUES ('admin', '" . password_hash('admin123', PASSWORD_DEFAULT) . "', '系統管理員', 'super_admin')
        ";
        $pdo->exec($admin_insert);
    }
    
    // 插入預設系統設定
    $default_settings = [
        ['site_name', '樂齡智趣網', 'string', '網站名稱'],
        ['max_daily_playtime', '120', 'number', '每日最大遊玩時間(分鐘)'],
        ['enable_registration', '1', 'boolean', '是否開放註冊'],
        ['maintenance_mode', '0', 'boolean', '維護模式'],
        ['game_difficulty_curve', '{"easy": 0.3, "medium": 0.5, "hard": 0.2}', 'json', '遊戲難度分布']
    ];
    
    foreach ($default_settings as $setting) {
        $check = $pdo->prepare("SELECT COUNT(*) FROM system_settings WHERE setting_key = ?");
        $check->execute([$setting[0]]);
        if ($check->fetchColumn() == 0) {
            $insert = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value, setting_type, description) VALUES (?, ?, ?, ?)");
            $insert->execute($setting);
        }
    }
    
    echo "後台管理系統資料庫設定完成！\n";
    echo "預設管理員帳號：admin\n";
    echo "預設密碼：admin123\n";
    
} catch (Exception $e) {
    echo "資料庫設定失敗：" . $e->getMessage() . "\n";
}
?> 