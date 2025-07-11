<?php
// 开启错误显示 - 调试用
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 开启 session 用于获取数据
session_start();

header('Content-Type: application/json');

// 查询运动数据
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

    $data = json_decode(file_get_contents("php://input"), true);

    // 检查用户是否已登录
    if (!isset($data['user_id'])) {
        echo json_encode(['success' => false, 'message' => '用户未登录']);
        mysqli_close($conn);
        exit();
    }
  
    // 查询所有的历史运动数据
    $sql = "SELECT * FROM running_records WHERE user_id = ? ORDER BY created_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $data['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $history_data = array();
    while ($row = $result->fetch_assoc()) {
        $history_data[] = $row;
    }

    echo json_encode([
        'success' => true, 
        'message' => '💐 查询历史运动数据成功！💐', 
        'data' => $history_data,
        'count' => count($history_data)
    ]);
 
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