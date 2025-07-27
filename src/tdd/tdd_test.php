<?php
// exercise_history_test.php
// 在VSCode中可直接运行的完整代码 25-7-28

// 简单的断言函数
function test_assert($condition, $message) {
    if (!$condition) {
        throw new Exception("Assertion failed: " . $message);
    }
}

// 运动记录实体类
class ExerciseRecord
{
    private $id;
    private $userId;
    private $type;
    private $duration;
    private $distance;
    private $calories;
    private $createdAt;

    public function __construct($id, $userId, $type, $duration, $distance, $calories, $createdAt)
    {
        $this->id = $id;
        $this->userId = $userId;
        $this->type = $type;
        $this->duration = $duration;
        $this->distance = $distance;
        $this->calories = $calories;
        $this->createdAt = $createdAt;
    }

    public function getId(): int { return $this->id; }
    public function getUserId(): int { return $this->userId; }
    public function getType(): string { return $this->type; }
    public function getDuration(): int { return $this->duration; }
    public function getDistance(): float { return $this->distance; }
    public function getCalories(): int { return $this->calories; }
    public function getCreatedAt(): string { return $this->createdAt; }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'type' => $this->type,
            'duration' => $this->duration,
            'distance' => $this->distance,
            'calories' => $this->calories,
            'date' => $this->createdAt
        ];
    }
}

// 仓储接口
interface ExerciseRepositoryInterface
{
    public function findByUserId(int $userId, array $filters = []): array;
    public function countByUserId(int $userId, array $filters = []): int;
}

// Mock仓储实现（用于测试）
class MockExerciseRepository implements ExerciseRepositoryInterface
{
    private $mockData = [];

    public function setMockData(array $data)
    {
        $this->mockData = $data;
    }

    public function findByUserId(int $userId, array $filters = []): array
    {
        $exercises = [];
        foreach ($this->mockData as $data) {
            $exercises[] = new ExerciseRecord(
                $data['id'],
                $userId,
                $data['type'],
                $data['duration'],
                $data['distance'],
                $data['calories'],
                $data['date']
            );
        }
        return $exercises;
    }

    public function countByUserId(int $userId, array $filters = []): int
    {
        return count($this->mockData);
    }
}

// 验证器类
class ExerciseHistoryValidator
{
    public function validateUserId($userId): array
    {
        $errors = [];
        if (!is_numeric($userId) || $userId <= 0) {
            $errors[] = 'Invalid user ID';
        }
        return $errors;
    }

    public function validateFilters(array $filters): array
    {
        $errors = [];
        
        if (!empty($filters['start_date']) && !$this->isValidDate($filters['start_date'])) {
            $errors[] = 'Invalid start date format';
        }
        
        if (!empty($filters['end_date']) && !$this->isValidDate($filters['end_date'])) {
            $errors[] = 'Invalid end date format';
        }
        
        return $errors;
    }

    private function isValidDate($date): bool
    {
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
}

// API服务类
class ExerciseHistoryApi
{
    private $repository;
    private $validator;

    public function __construct(ExerciseRepositoryInterface $repository)
    {
        $this->repository = $repository;
        $this->validator = new ExerciseHistoryValidator();
    }

    public function getUserExerciseHistory(int $userId, array $filters = []): array
    {
        try {
            // 验证用户ID
            $userIdErrors = $this->validator->validateUserId($userId);
            if (!empty($userIdErrors)) {
                return $this->errorResponse($userIdErrors[0]);
            }

            // 验证过滤条件
            $filterErrors = $this->validator->validateFilters($filters);
            if (!empty($filterErrors)) {
                return $this->errorResponse(implode(', ', $filterErrors));
            }

            // 获取运动记录
            $exercises = $this->repository->findByUserId($userId, $filters);
            $totalCount = $this->repository->countByUserId($userId, $filters);

            // 转换为数组格式
            $exerciseData = [];
            foreach ($exercises as $exercise) {
                $exerciseData[] = $exercise->toArray();
            }

            return $this->successResponse([
                'user_id' => $userId,
                'exercises' => $exerciseData,
                'total_count' => $totalCount,
                'filters_applied' => $filters
            ]);

        } catch (Exception $e) {
            return $this->errorResponse('Internal server error: ' . $e->getMessage());
        }
    }

    private function successResponse(array $data): array
    {
        return [
            'status' => 'success',
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }

    private function errorResponse(string $message): array
    {
        return [
            'status' => 'error',
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
}

// 测试类
class ExerciseHistoryApiTest
{
    private $api;
    private $repository;

    public function setUp()
    {
        $this->repository = new MockExerciseRepository();
        $this->api = new ExerciseHistoryApi($this->repository);
    }

    public function testGetUserExerciseHistoryReturnsCorrectFormat()
    {
        echo "🧪 测试1: 检查返回数据格式...\n";
        
        // Arrange
        $userId = 123;
        $mockData = [
            [
                'id' => 1,
                'type' => 'running',
                'duration' => 30,
                'distance' => 5.0,
                'calories' => 300,
                'date' => '2024-01-15 08:00:00'
            ]
        ];

        $this->repository->setMockData($mockData);

        // Act
        $result = $this->api->getUserExerciseHistory($userId);

        // Assert
        test_assert($result['status'] === 'success', 'Status should be success');
        test_assert($result['data']['user_id'] === $userId, 'User ID should match');
        test_assert(count($result['data']['exercises']) === 1, 'Should have 1 exercise');
        test_assert($result['data']['exercises'][0]['type'] === 'running', 'Exercise type should be running');
        test_assert($result['data']['total_count'] === 1, 'Total count should be 1');
        
        echo "✅ 测试1通过！\n\n";
    }

    public function testGetUserExerciseHistoryWithFilters()
    {
        echo "🧪 测试2: 检查过滤器功能...\n";
        
        // Arrange
        $userId = 123;
        $filters = [
            'start_date' => '2024-01-01',
            'end_date' => '2024-01-31',
            'exercise_type' => 'running'
        ];

        $mockData = [
            [
                'id' => 1,
                'type' => 'running',
                'duration' => 30,
                'distance' => 5.0,
                'calories' => 300,
                'date' => '2024-01-15 08:00:00'
            ]
        ];

        $this->repository->setMockData($mockData);

        // Act
        $result = $this->api->getUserExerciseHistory($userId, $filters);

        // Assert
        test_assert($result['status'] === 'success', 'Status should be success');
        test_assert(is_array($result['data']['exercises']), 'Exercises should be array');
        test_assert($result['data']['filters_applied'] === $filters, 'Filters should be applied');
        
        echo "✅ 测试2通过！\n\n";
    }

    public function testGetUserExerciseHistoryWithInvalidUserId()
    {
        echo "🧪 测试3: 检查无效用户ID处理...\n";
        
        // Arrange
        $invalidUserId = -1;

        // Act
        $result = $this->api->getUserExerciseHistory($invalidUserId);

        // Assert
        test_assert($result['status'] === 'error', 'Status should be error');
        test_assert($result['message'] === 'Invalid user ID', 'Should return invalid user ID message');
        
        echo "✅ 测试3通过！\n\n";
    }

    public function testExerciseRecordToArray()
    {
        echo "🧪 测试4: 检查运动记录转数组功能...\n";
        
        // Arrange & Act
        $exercise = new ExerciseRecord(1, 123, 'running', 30, 5.0, 300, '2024-01-15 08:00:00');
        $array = $exercise->toArray();

        // Assert
        test_assert($array['id'] === 1, 'ID should be 1');
        test_assert($array['user_id'] === 123, 'User ID should be 123');
        test_assert($array['type'] === 'running', 'Type should be running');
        test_assert($array['duration'] === 30, 'Duration should be 30');
        test_assert($array['distance'] === 5.0, 'Distance should be 5.0');
        test_assert($array['calories'] === 300, 'Calories should be 300');
        
        echo "✅ 测试4通过！\n\n";
    }

    public function runAllTests()
    {
        echo "🚀 开始运行所有测试...\n\n";
        
        try {
            $this->setUp();
            $this->testGetUserExerciseHistoryReturnsCorrectFormat();
            
            $this->setUp();
            $this->testGetUserExerciseHistoryWithFilters();
            
            $this->setUp();
            $this->testGetUserExerciseHistoryWithInvalidUserId();
            
            $this->testExerciseRecordToArray();
            
            echo "🎉 所有测试都通过了！\n";
            echo "📊 共运行了 4 个测试用例\n";
            
        } catch (Exception $e) {
            echo "❌ 测试失败: " . $e->getMessage() . "\n";
        }
    }
}

// 主执行部分
if (php_sapi_name() === 'cli') {
    echo "🏃‍♂️ 历史运动记录API - TDD测试\n";
    echo "=====================================\n\n";
    
    // 运行测试
    $test = new ExerciseHistoryApiTest();
    $test->runAllTests();
    
    // 演示使用
    demonstrateUsage();
    
} else {
    echo "<h1>请在命令行中运行此文件</h1>";
    echo "<p>在终端中执行: <code>php " . basename(__FILE__) . "</code></p>";
}

// 演示使用
function demonstrateUsage()
{
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "📋 API使用演示\n";
    echo str_repeat("=", 50) . "\n";
    
    // 创建依赖
    $repository = new MockExerciseRepository();
    $api = new ExerciseHistoryApi($repository);
    
    // 设置测试数据
    $mockData = [
        [
            'id' => 1,
            'type' => 'running',
            'duration' => 30,
            'distance' => 5.0,
            'calories' => 300,
            'date' => '2024-01-15 08:00:00'
        ],
        [
            'id' => 2,
            'type' => 'cycling',
            'duration' => 45,
            'distance' => 15.0,
            'calories' => 400,
            'date' => '2024-01-14 09:00:00'
        ]
    ];
    
    $repository->setMockData($mockData);
    
    // 调用API
    $result = $api->getUserExerciseHistory(123, ['start_date' => '2024-01-01']);
    
    echo "📤 API调用结果:\n";
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
}

?>