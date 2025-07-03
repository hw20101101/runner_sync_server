<?php

// 开启 session 用于获取数据
session_start();

header('Content-Type: application/json');

// 注册新用户
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
 
    $data = json_decode(file_get_contents("php://input"), true);
    $username = trim($data['username']);
    $password = $data['password'];

    // 验证输入数据
    if (empty($username) || empty($password)) {
        echo json_encode(['success' => false, 'message' => '用户名和密码不能为空！']);
        exit();
    }

    // 数据库连接 
    $db_servername = "localhost";
    $db_username = "root";
    $db_password = "";
    $db_name = "runner";
    $conn = new mysqli($db_servername, $db_username, $db_password, $db_name);

    if ($conn->connect_error) {
        echo json_encode(['success' => false, 'message' => '数据库连接失败: ' . $conn->connect_error]);
        exit();
    }
 
    // 创建 users 表
    mysqli_select_db($conn, $db_name);
    $sql = "CREATE TABLE IF NOT EXISTS users (
            id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(30) NOT NULL,
            password VARCHAR(255) NOT NULL,
            reg_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )";
    if (!mysqli_query($conn, $sql)) {
        echo json_encode(['success' => false, 'message' => 'users 表创建失败：' . mysqli_error($conn)]);
        mysqli_close($conn);
        exit();
    }

    // 检查并添加 last_login_time 字段（如果不存在）
    $check_column_sql = "SHOW COLUMNS FROM users LIKE 'last_login_time'";
    $column_result = mysqli_query($conn, $check_column_sql);
    
    if (mysqli_num_rows($column_result) == 0) {
        // 字段不存在，添加它
        $alter_sql = "ALTER TABLE users ADD COLUMN last_login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
        if (!mysqli_query($conn, $alter_sql)) {
            echo json_encode(['success' => false, 'message' => 'users 表更新失败：' . mysqli_error($conn)]);
            mysqli_close($conn);
            exit();
        }
    }

    // 查询数据库中是否已有该用户名（使用预处理语句防止 SQL 注入）
    $check_sql = "SELECT * FROM users WHERE username = ?";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => '该用户名已被注册！']);
        $stmt->close();
        mysqli_close($conn);
        exit();
    }
    $stmt->close();

    // 使用 Salts and md5 加密密码
    $salt = 'hyxb'; 
    $hashed_password = md5($salt.$password);

    // 插入数据到 users 表，并设置用户最后登录时间
    // 时区问题，需要设置时区为 UTC+8
    date_default_timezone_set('Asia/Shanghai');
    $last_login_time = date('Y-m-d H:i:s');
    $insert_sql = "INSERT INTO users (username, password, last_login_time) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($insert_sql);
    $stmt->bind_param("sss", $username, $hashed_password, $last_login_time);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => '💐 恭喜，新用户注册成功！💐']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error inserting data: ' . $stmt->error]);
    }
    
    $stmt->close();
    // 关闭数据库连接 
    mysqli_close($conn); 
    exit();
}

/**

用终端测试注册用户成功：

curl -X POST http://localhost/runner/register.php \
-H "Content-Type: application/json" \
-d '{"username": "runner123", "password": "mypassword"}'

 */