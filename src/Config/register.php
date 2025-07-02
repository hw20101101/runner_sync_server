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
        die("用户名和密码不能为空！");
    }

    // 数据库连接 
    $db_servername = "localhost";
    $db_username = "root";
    $db_password = "";
    $db_name = "runner";
    $conn = new mysqli($db_servername, $db_username, $db_password, $db_name);

    if ($conn->connect_error) {
        die("数据库连接失败: " . $conn->connect_error);
    } else {
        // echo "数据库连接成功！";
    }
 
    //创建 users 表
    mysqli_select_db($conn, $db_name);
    $sql = "CREATE TABLE IF NOT EXISTS users (
            id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(30) NOT NULL,
            password VARCHAR(255) NOT NULL,
            reg_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )";
    if (mysqli_query($conn, $sql)) {
        // 如果 users 表已经存在，它只会输出 “users 表创建成功！” 而不会尝试重新创建表
        echo "users 表创建成功！";
    } else {
        echo "users 表创建失败：" . mysqli_error($conn);
    }

    //查询数据库中是否已有该用户名
    $sql = "SELECT * FROM users WHERE username='$username'";
    $result = mysqli_query($conn, $sql);
    if (mysqli_num_rows($result) > 0) {
        echo "该用户名已被注册！";
        mysqli_close($conn);
        exit();
    }

    // 使用 Salts and md5 加密密码
    $salt = 'hyxb'; 
    $hashed_password = md5($salt.$password);

    // 插入数据到 users 表
    $sql = "INSERT INTO users (username, password)
            VALUES ('$username', '$hashed_password')";
    if (mysqli_query($conn, $sql)) {
        echo "💐 恭喜，新用户注册成功！💐 ";
    } else {
        echo "Error inserting data: " . mysqli_error($conn);
    }
 
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