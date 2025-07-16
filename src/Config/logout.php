<?php
/**
 * 数据库连接类
 */
class Database {
    private $servername;
    private $username;
    private $password;
    private $dbname;
    private $connection;
    
    public function __construct($servername = "localhost", $username = "root", $password = "", $dbname = "runner") {
        $this->servername = $servername;
        $this->username = $username;
        $this->password = $password;
        $this->dbname = $dbname;
    }
    
    /**
     * 建立数据库连接
     */
    public function connect() {
        $this->connection = new mysqli($this->servername, $this->username, $this->password, $this->dbname);
        
        if ($this->connection->connect_error) {
            throw new Exception('数据库连接失败: ' . $this->connection->connect_error);
        }
        
        return $this->connection;
    }
    
    /**
     * 获取连接对象
     */
    public function getConnection() {
        if (!$this->connection) {
            $this->connect();
        }
        return $this->connection;
    }
    
    /**
     * 关闭数据库连接
     */
    public function close() {
        if ($this->connection) {
            $this->connection->close();
        }
    }
    
    /**
     * 检查表是否存在
     */
    public function tableExists($tableName) {
        // 转义表名以防止SQL注入
        $tableName = $this->getConnection()->real_escape_string($tableName);
        $sql = "SHOW TABLES LIKE '$tableName'";
        $result = $this->getConnection()->query($sql);
        
        if ($result === false) {
            throw new Exception('检查表存在性失败: ' . $this->getConnection()->error);
        }
        
        return $result->num_rows > 0;
    }
}

/**
 * 用户退出登录类
 */
class UserLogout {
    private $db;
    private $salt;
    
    public function __construct(Database $database, $salt = 'hyxb') {
        $this->db = $database;
        $this->salt = $salt;
        
        // 设置时区
        date_default_timezone_set('Asia/Shanghai');
    }
    
    /**
     * 验证令牌
     */
    private function validateToken($userId, $token, $loginTime) {
        $expectedToken = md5($userId . $loginTime . $this->salt);
        return $expectedToken === $token;
    }
    
    /**
     * 检查字段是否存在
     */
    private function columnExists($tableName, $columnName) {
        $sql = "SHOW COLUMNS FROM `$tableName` LIKE '$columnName'";
        $result = $this->db->getConnection()->query($sql);
        return $result && $result->num_rows > 0;
    }
    
    /**
     * 更新用户最后退出时间
     */
    private function updateLastLogoutTime($userId) {
        $lastLogoutTime = date('Y-m-d H:i:s');
        
        // 检查 last_logout_time 字段是否存在
        if ($this->columnExists('users', 'last_logout_time')) {
            $sql = "UPDATE users SET last_logout_time = ? WHERE id = ?";
            $stmt = $this->db->getConnection()->prepare($sql);
            $stmt->bind_param("si", $lastLogoutTime, $userId);
            
            if (!$stmt->execute()) {
                $stmt->close();
                throw new Exception('更新退出时间失败: ' . $stmt->error);
            }
            
            $stmt->close();
        }
        // 如果字段不存在，仍然返回当前时间，但不更新数据库
        
        return $lastLogoutTime;
    }
    
    /**
     * 获取用户信息
     */
    private function getUserInfo($userId) {
        $sql = "SELECT id, username, last_login_time FROM users WHERE id = ?";
        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $stmt->close();
            return $user;
        } else {
            $stmt->close();
            throw new Exception('用户不存在！');
        }
    }
    
    /**
     * 记录退出登录日志（可选功能）
     */
    private function logLogout($userId, $username) {
        if ($this->db->tableExists('logout_logs')) {
            $logoutTime = date('Y-m-d H:i:s');
            $sql = "INSERT INTO logout_logs (user_id, username, logout_time) VALUES (?, ?, ?)";
            $stmt = $this->db->getConnection()->prepare($sql);
            $stmt->bind_param("iss", $userId, $username, $logoutTime);
            
            if (!$stmt->execute()) {
                // 日志记录失败不影响退出登录流程，只记录错误
                error_log('退出登录日志记录失败: ' . $stmt->error);
            }
            
            $stmt->close();
        }
    }
    
    /**
     * 处理用户退出登录
     */
    public function logout($userId, $token) {
        // 验证输入
        if (empty($userId) || empty($token)) {
            throw new Exception('用户ID和令牌不能为空！');
        }
        
        // 检查用户表是否存在
        if (!$this->db->tableExists('users')) {
            throw new Exception('数据库表不存在！');
        }
        
        // 获取用户信息
        $userInfo = $this->getUserInfo($userId);
        
        // 验证令牌
        if (!$this->validateToken($userId, $token, $userInfo['last_login_time'])) {
            throw new Exception('令牌验证失败！');
        }
        
        // 更新最后退出时间
        $lastLogoutTime = $this->updateLastLogoutTime($userId);
        
        // 记录退出登录日志
        $this->logLogout($userId, $userInfo['username']);
        
        return [
            'success' => true,
            'user_id' => $userId,
            'username' => $userInfo['username'],
            'logout_time' => $lastLogoutTime,
            'message' => '👋 再见，退出登录成功！👋'
        ];
    }
}

/**
 * 退出登录控制器类
 */
class LogoutController {
    private $userLogout;
    
    public function __construct(UserLogout $userLogout) {
        $this->userLogout = $userLogout;
    }
    
    /**
     * 处理退出登录请求
     */
    public function handleLogout() {
        // 开启 session
        session_start();
        
        // 设置响应头
        header('Content-Type: application/json');
        
        try {
            // 检查请求方法
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('只支持POST请求！');
            }
            
            // 获取请求数据
            $data = json_decode(file_get_contents("php://input"), true);
            
            if (!$data) {
                throw new Exception('请求数据格式错误！');
            }
            
            $userId = $data['user_id'] ?? '';
            $token = $data['token'] ?? '';
            
            // 执行退出登录
            $result = $this->userLogout->logout($userId, $token);
            
            // 清除 session
            session_unset();
            session_destroy();
            
            // 返回成功结果
            echo json_encode($result);
            
        } catch (Exception $e) {
            // 返回错误信息
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
}

// 使用示例
try {
    // 创建数据库连接
    $database = new Database();
    
    // 创建用户退出登录对象
    $userLogout = new UserLogout($database);
    
    // 创建退出登录控制器
    $logoutController = new LogoutController($userLogout);
    
    // 处理退出登录请求
    $logoutController->handleLogout();
    
} catch (Exception $e) {
    // 处理全局异常
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => '系统错误: ' . $e->getMessage()
    ]);
} finally {
    // 确保数据库连接关闭
    if (isset($database)) {
        $database->close();
    }
}
?>