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
 * 用户认证类
 */
class UserAuth {
    private $db;
    private $salt;
    
    public function __construct(Database $database, $salt = 'hyxb') {
        $this->db = $database;
        $this->salt = $salt;
        
        // 设置时区
        date_default_timezone_set('Asia/Shanghai');
    }
    
    /**
     * 验证输入数据
     */
    private function validateInput($username, $password) {
        if (empty($username) || empty($password)) {
            throw new Exception('用户名和密码不能为空！');
        }
    }
    
    /**
     * 加密密码
     */
    private function hashPassword($password) {
        return md5($this->salt . $password);
    }
    
    /**
     * 生成令牌
     */
    private function generateToken($userId, $loginTime) {
        return md5($userId . $loginTime . $this->salt);
    }
    
    /**
     * 更新用户最后登录时间
     */
    private function updateLastLoginTime($userId) {
        $lastLoginTime = date('Y-m-d H:i:s');
        $sql = "UPDATE users SET last_login_time = ? WHERE id = ?";
        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->bind_param("si", $lastLoginTime, $userId);
        
        if (!$stmt->execute()) {
            $stmt->close();
            throw new Exception('更新登录时间失败: ' . $stmt->error);
        }
        
        $stmt->close();
        return $lastLoginTime;
    }
    
    /**
     * 验证用户登录
     */
    public function authenticate($username, $password) {
        // 验证输入
        $this->validateInput($username, $password);
        
        // 检查用户表是否存在
        if (!$this->db->tableExists('users')) {
            throw new Exception('数据库表不存在！');
        }
        
        // 加密密码
        $hashedPassword = $this->hashPassword($password);
        
        // 查询用户
        $sql = "SELECT id, username FROM users WHERE username = ? AND password = ?";
        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->bind_param("ss", $username, $hashedPassword);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $userId = $user['id'];
            $stmt->close();
            
            // 更新最后登录时间
            $lastLoginTime = $this->updateLastLoginTime($userId);
            
            // 生成令牌
            $token = $this->generateToken($userId, $lastLoginTime);
            
            return [
                'success' => true,
                'user_id' => $userId,
                'token' => $token,
                'message' => '💐 恭喜，登录成功！💐'
            ];
        } else {
            $stmt->close();
            throw new Exception('用户名或密码错误！');
        }
    }
}

/**
 * 登录控制器类
 */
class LoginController {
    private $userAuth;
    
    public function __construct(UserAuth $userAuth) {
        $this->userAuth = $userAuth;
    }
    
    /**
     * 处理登录请求
     */
    public function handleLogin() {
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
            
            $username = trim($data['username'] ?? '');
            $password = $data['password'] ?? '';
            
            // 执行登录验证
            $result = $this->userAuth->authenticate($username, $password);
            
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
    
    // 创建用户认证对象
    $userAuth = new UserAuth($database);
    
    // 创建登录控制器
    $loginController = new LoginController($userAuth);
    
    // 处理登录请求
    $loginController->handleLogin();
    
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