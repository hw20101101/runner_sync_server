<?php
// 简单的登录逻辑

// 开启 session 用于获取数据
session_start();

header('Content-Type: application/json');
 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
 
    $data = json_decode(file_get_contents("php://input"), true);
    $username = trim($data['username']);
    $password = $data['password'];

    // 验证输入数据
    if (empty($username) || empty($password)) {
        echo json_encode(['success' => false, 'message' => '用户名和密码不能为空！']);
        exit();
    }

    //从数据库中获取用户名和密码
    $db_servername = "localhost";
    $db_username = "root";
    $db_password = "";
    $db_dbname = "runner";
    $conn = new mysqli($db_servername, $db_username, $db_password, $db_dbname);

    if ($conn->connect_error) {
        echo json_encode(['success' => false, 'message' => '数据库连接失败: ' . $conn->connect_error]);
        exit();
    }

    mysqli_select_db($conn, $db_dbname);
    //判断是否存在 user 表
    $sql = "SHOW TABLES LIKE 'users'";
    $result = $conn->query($sql);

    if ($result->num_rows == 0) {
        echo json_encode(['success' => false, 'message' => '数据库表不存在！']);
        mysqli_close($conn);
        exit();  
    }
    
    // 使用与注册时相同的盐值和 md5 加密密码
    $salt = 'hyxb';  // 修复：使用与注册时相同的盐值
    $hashed_password = md5($salt.$password);

    // 使用预处理语句防止 SQL 注入
    $sql = "SELECT id, username FROM users WHERE username = ? AND password = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $username, $hashed_password);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $user_id = $user['id'];
        
        // 更新用户最后登录时间，格式为 Y-m-d H:i:s
        // 时区问题，需要设置时区为 UTC+8
        date_default_timezone_set('Asia/Shanghai');
        $last_login_time = date('Y-m-d H:i:s');
        
        // 修复：使用 UPDATE 而不是 INSERT 来更新登录时间
        $update_sql = "UPDATE users SET last_login_time = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("si", $last_login_time, $user_id);

        if ($update_stmt->execute()) {
            //返回 user_id 和 token 给 APP
            $token = md5($user_id. $last_login_time. $salt); 
            echo json_encode(['success' => true, 'user_id' => $user_id, 'token' => $token, 'message' => '💐 恭喜，登录成功！💐']);
        } else {
            echo json_encode(['success' => false, 'message' => '登录失败: ' . $update_stmt->error]);
        }
        
        $update_stmt->close();

    } else { 
        echo json_encode(['success' => false, 'message' => '用户名或密码错误！']);
    }

    $stmt->close();
    // 关闭数据库连接 
    mysqli_close($conn); 
    exit();
}
?>