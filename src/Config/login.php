<?php
// 简单的登录逻辑

// 开启 session 用于获取数据
session_start();
 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
 
    $data = json_decode(file_get_contents("php://input"), true);
    $username = trim($data['username']);
    $password = $data['password'];

    // 使用 Salts and md5 加密密码
    $salt = 'hwacdx'; 
    $hashed_password = md5($salt.$password);

    //从数据库中获取用户名和密码
    $db_servername = "localhost";
    $db_username = "root";
    $db_password = "";
    $db_dbname = "runner";
    $conn = new mysqli($db_servername, $db_username, $db_password, $db_dbname);

    if ($conn->connect_error) {
        echo "mysqli 连接失败";
        die("连接失败: ". $conn->connect_error);
    }

    mysqli_select_db($conn, $db_dbname);
    //判断是否存在 user 表
    $sql = "SHOW TABLES LIKE 'users'";
    $result = $conn->query($sql);

    if ($result->num_rows == 0) {
        echo "用户不存在, 请先注册"; 
        mysqli_close($conn);
        exit();  
    }
    
    $sql = "SELECT * FROM users WHERE username='$username' AND password='$hashed_password'";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        echo "💐 恭喜，登录成功！💐 ";        
    } else { 
        echo "用户名或密码错误！";
    }

    // 关闭数据库连接 
    mysqli_close($conn); 
    exit();
}
?>
