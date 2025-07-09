<?php
// 开启错误显示 - 调试用
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 开启 session 用于获取数据
session_start();

header('Content-Type: application/json');

// 添加运动数据
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  
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
 
    // 创建 running_records 表
    mysqli_select_db($conn, $db_name);
    $sql = "CREATE TABLE IF NOT EXISTS running_records (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        distance DECIMAL(10,2) NOT NULL COMMENT '距离(千米)',
        duration INT NOT NULL COMMENT '时长(秒)',
        calories DECIMAL(8,2) COMMENT '消耗卡路里',
        avg_speed DECIMAL(8,2) COMMENT '平均速度(km/h)',
        start_time DATETIME NOT NULL,
        end_time DATETIME NOT NULL,
        route_data JSON COMMENT '路线GPS数据',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    
    if (!mysqli_query($conn, $sql)) {
        echo json_encode(['success' => false, 'message' => 'running_records 表创建失败：' . mysqli_error($conn)]);
        mysqli_close($conn);
        exit();
    }

    // 检查用户是否已登录
    // if (!isset($_SESSION['user_id'])) {
    //     echo json_encode(['success' => false, 'message' => '用户未登录']);
    //     mysqli_close($conn);
    //     exit();
    // }

    // 设置时区
    date_default_timezone_set('Asia/Shanghai');
    $now_time = date('Y-m-d H:i:s');

    // 硬编码测试数据
    $user_id = 8;   //$_SESSION['user_id'];
    $distance = 3.5;                    // 距离(千米)
    $duration = 1800;                   // 时长(秒) - 30分钟
    $calories = 250.50;                 // 消耗卡路里
    $avg_speed = 7.0;                   // 平均速度(km/h)
    $start_time = $now_time;            // 开始时间
    $end_time = $now_time;              // 结束时间
    $route_data = '{"coordinates": [{"lat": 31.2304, "lng": 121.4737}, {"lat": 31.2305, "lng": 121.4738}]}'; // GPS路线数据

    // 插入数据到 running_records 表
    $insert_sql = "INSERT INTO running_records (user_id, distance, duration, calories, avg_speed, start_time, end_time, route_data) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($insert_sql);
    
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'SQL准备失败: ' . $conn->error]);
        mysqli_close($conn);
        exit();
    }

    // 正确的参数绑定：i=integer, d=double/decimal, s=string
    $stmt->bind_param("ididdsss", $user_id, $distance, $duration, $calories, $avg_speed, $start_time, $end_time, $route_data);

    if ($stmt->execute()) {
        echo json_encode([
            'success' => true, 
            'message' => '💐 新增运动数据成功！💐',
            'data' => [
                'user_id' => $user_id,
                'distance' => $distance,
                'duration' => $duration,
                'calories' => $calories,
                'avg_speed' => $avg_speed,
                'start_time' => $start_time,
                'end_time' => $end_time
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error inserting data: ' . $stmt->error]);
    }
    
    $stmt->close();
    // 关闭数据库连接 
    mysqli_close($conn); 
    exit();
    
} else {
    // 非POST请求
    echo json_encode(['success' => false, 'message' => '请求方法错误，需要POST请求']);
    exit();
}
?>