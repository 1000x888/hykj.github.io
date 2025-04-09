<?php
// 配置数据库连接参数
define('DB_SERVER', '127.0.0.1,7246'); // 数据库服务器地址
define('DB_USERNAME', 'sa'); // 用户名
define('DB_PASSWORD', 'Bing7524'); // 密码
define('DB_DATABASE', 'GWSYS_test1'); // 数据库名称

// 日志文件路径
define('LOG_FILE', 'server_log.txt');
define('MAX_LOG_SIZE', 1024 * 1024); // 日志文件最大大小为 1MB

// 日志记录函数
function logMessage($message, $level = 'INFO') {
    if (file_exists(LOG_FILE) && filesize(LOG_FILE) > MAX_LOG_SIZE) {
        unlink(LOG_FILE); // 删除超出大小的日志文件
    }
    file_put_contents(LOG_FILE, "[" . date('Y-m-d H:i:s') . "][$level] $message" . PHP_EOL, FILE_APPEND);
}

// 数据库连接函数
function connectToDatabase() {
    $connectionInfo = [
        "UID" => DB_USERNAME,
        "PWD" => DB_PASSWORD,
        "Database" => DB_DATABASE,
        "CharacterSet" => "UTF-8",
        "TrustServerCertificate" => true,
        "Encrypt" => true,
    ];
    $conn = sqlsrv_connect(DB_SERVER, $connectionInfo);
    if (!$conn) {
        //logMessage("Database connection failed: " . print_r(sqlsrv_errors(), true), 'ERROR');
        throw new Exception("Database connection failed");
    }
    return $conn;
}

// 主程序入口
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    //logMessage("Received a POST request.");
    $postData = $_POST;
    //logMessage("POST data: " . json_encode($postData));

    if (isset($postData['post']) && $postData['post'] == '11' && isset($postData['pcid'])) {
        $pcid = $postData['pcid'];
        try {
            $data = handlePost11($pcid);
            header('Content-Type: application/json');
            echo json_encode($data);
        } catch (Exception $e) {
            //logMessage("Error processing Post=11: " . $e->getMessage(), 'ERROR');
            http_response_code(500);
            echo json_encode(["error" => "Internal server error"]);
        }
    } else {
        //logMessage("Invalid POST request or missing parameters.", 'WARNING');
        http_response_code(400);
        echo json_encode(["error" => "Invalid POST request or missing parameters."]);
    }
}

// 处理 Post=11
function handlePost11($pcid) {
    //logMessage("Processing Post=11 for pcid: $pcid");
    $conn = connectToDatabase();
    
    // 修改查询，加入 xy 字段
    $query = "SELECT TOP 10 deviceid, hex, xy FROM gwid WHERE pcid = ? AND zt = 1 AND syszt = 2 ORDER BY id ASC";
    $stmt = sqlsrv_query($conn, $query, [$pcid]);

    if ($stmt === false) {
        //logMessage("Database query failed: " . print_r(sqlsrv_errors(), true), 'ERROR');
        throw new Exception("Database query failed");
    }

    $results = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $results[] = [
            'headercaption' => $row['deviceid'],
            'text' => $row['hex'],
            'xy' => $row['xy']  // 添加 xy 字段
        ];
    }

    sqlsrv_free_stmt($stmt);
    sqlsrv_close($conn);

    // 填充控件数据，最多10个
    $gwidControls = [];
    for ($i = 0; $i < 10; $i++) {
        if (isset($results[$i])) {
            $gwidControls[] = $results[$i];
        } else {
            $gwidControls[] = [
                'headercaption' => '',
                'text' => '',
                'xy' => ''  // 空值默认返回
            ];
        }
    }

    //logMessage("Generated response for Post=11: " . json_encode($gwidControls));
    return $gwidControls;
}
