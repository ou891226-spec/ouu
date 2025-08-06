<?php
echo "<h2>資料庫同步工具</h2>";

// XAMPP 資料庫連接配置
$xampp_config = [
    'host' => 'localhost',
    'dbname' => 'myproject',
    'username' => 'root',
    'password' => '', // XAMPP 默認密碼為空
    'charset' => 'utf8mb4'
];

// Azure MySQL 資料庫連接配置
$azure_config = [
    'host' => 'smartfun-senior.mysql.database.azure.com',
    'dbname' => 'myproject',
    'username' => 's1411131021',
    'password' => 'Test12345',
    'charset' => 'utf8mb4'
];

function connectDatabase($config) {
    try {
        $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        // 如果是 Azure，添加 SSL 配置
        if (strpos($config['host'], 'azure.com') !== false) {
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
            $options[PDO::MYSQL_ATTR_SSL_CA] = false;
        }
        
        return new PDO($dsn, $config['username'], $config['password'], $options);
    } catch (PDOException $e) {
        throw new Exception("連接失敗: " . $e->getMessage());
    }
}

function getTableList($pdo) {
    $stmt = $pdo->query("SHOW TABLES");
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function getTableStructure($pdo, $table) {
    $stmt = $pdo->query("SHOW CREATE TABLE `$table`");
    $result = $stmt->fetch();
    return $result['Create Table'];
}

function getTableData($pdo, $table) {
    $stmt = $pdo->query("SELECT * FROM `$table`");
    return $stmt->fetchAll();
}

function syncTable($source_pdo, $target_pdo, $table) {
    echo "<h3>同步表: $table</h3>";
    
    try {
        // 1. 獲取表結構
        $source_structure = getTableStructure($source_pdo, $table);
        
        // 2. 在目標資料庫中創建表（如果不存在）
        $target_pdo->exec("DROP TABLE IF EXISTS `$table`");
        $target_pdo->exec($source_structure);
        echo "✅ 表結構同步完成<br>";
        
        // 3. 獲取並插入數據
        $data = getTableData($source_pdo, $table);
        if (!empty($data)) {
            $columns = array_keys($data[0]);
            $placeholders = str_repeat('?,', count($columns) - 1) . '?';
            $column_list = '`' . implode('`, `', $columns) . '`';
            
            $insert_sql = "INSERT INTO `$table` ($column_list) VALUES ($placeholders)";
            $insert_stmt = $target_pdo->prepare($insert_sql);
            
            foreach ($data as $row) {
                $insert_stmt->execute(array_values($row));
            }
            echo "✅ 數據同步完成，共 " . count($data) . " 條記錄<br>";
        } else {
            echo "ℹ️ 表為空，無數據需要同步<br>";
        }
        
        return true;
    } catch (Exception $e) {
        echo "❌ 同步失敗: " . $e->getMessage() . "<br>";
        return false;
    }
}

// 處理同步請求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'sync_all') {
        try {
            echo "<h3>開始同步所有表...</h3>";
            
            // 連接兩個資料庫
            $xampp_pdo = connectDatabase($xampp_config);
            $azure_pdo = connectDatabase($azure_config);
            
            // 獲取 XAMPP 中的所有表
            $tables = getTableList($xampp_pdo);
            
            $success_count = 0;
            $total_count = count($tables);
            
            foreach ($tables as $table) {
                if (syncTable($xampp_pdo, $azure_pdo, $table)) {
                    $success_count++;
                }
            }
            
            echo "<h3>同步完成！</h3>";
            echo "<p>成功同步: $success_count / $total_count 個表</p>";
            
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ 同步失敗: " . $e->getMessage() . "</p>";
        }
    } elseif ($action === 'sync_single') {
        $table_name = $_POST['table_name'] ?? '';
        
        if (!empty($table_name)) {
            try {
                $xampp_pdo = connectDatabase($xampp_config);
                $azure_pdo = connectDatabase($azure_config);
                
                syncTable($xampp_pdo, $azure_pdo, $table_name);
                
            } catch (Exception $e) {
                echo "<p style='color: red;'>❌ 同步失敗: " . $e->getMessage() . "</p>";
            }
        }
    }
}

// 顯示同步選項
?>
<div style="margin: 20px 0;">
    <h3>選擇同步方式：</h3>
    
    <form method="POST" style="margin: 10px 0;">
        <input type="hidden" name="action" value="sync_all">
        <button type="submit" style="padding: 10px 20px; background: #4CAF50; color: white; border: none; border-radius: 4px; cursor: pointer;">
            同步所有表
        </button>
        <p style="color: #666; font-size: 12px;">這將同步 XAMPP 中的所有表到 Azure MySQL</p>
    </form>
    
    <form method="POST" style="margin: 10px 0;">
        <input type="hidden" name="action" value="sync_single">
        <label for="table_name">同步單個表：</label>
        <input type="text" name="table_name" id="table_name" placeholder="輸入表名" 
               style="padding: 8px; margin: 0 10px;">
        <button type="submit" style="padding: 10px 20px; background: #2196F3; color: white; border: none; border-radius: 4px; cursor: pointer;">
            同步此表
        </button>
    </form>
</div>

<h3>注意事項：</h3>
<ul>
    <li>同步前請確保 XAMPP 的 MySQL 服務正在運行</li>
    <li>同步會覆蓋目標資料庫中的同名表</li>
    <li>建議在同步前備份重要數據</li>
    <li>如果表很大，同步可能需要一些時間</li>
</ul>

<h3>檢查連接狀態：</h3>
<?php
try {
    $xampp_pdo = connectDatabase($xampp_config);
    echo "<p style='color: green;'>✅ XAMPP 資料庫連接成功</p>";
    
    $tables = getTableList($xampp_pdo);
    echo "<p>XAMPP 中的表數量: " . count($tables) . "</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ XAMPP 連接失敗: " . $e->getMessage() . "</p>";
}

try {
    $azure_pdo = connectDatabase($azure_config);
    echo "<p style='color: green;'>✅ Azure MySQL 資料庫連接成功</p>";
    
    $tables = getTableList($azure_pdo);
    echo "<p>Azure 中的表數量: " . count($tables) . "</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Azure 連接失敗: " . $e->getMessage() . "</p>";
}
?> 