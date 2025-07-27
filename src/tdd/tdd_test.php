<?php

//1. 首先编写测试用例
class ExerciseHistoryApiTest {

    private $api;
    private $repository;

    public function setUp() {
        $this->repository = new MockExerciseRepository();
        $this->api = new ExerciseHistoryApi($this->repository);
    }

    //驱动设计：测试定义了API应该如何工作，而不是代码写完后再想测试
    public function testGetUserExerciseHistoryReturnsCorrectFormat() {
        $userId = 123;
        $expectedData = [
            'user_id' => 123,
            'exercises' => [
                [
                    'id' => 1,
                    'type' => 'running',
                    'duration' => 30,
                    'distance' => 10,
                    'calories' => 100,
                    'date' => '2021-01-01'
                ]
            ],
            'total_count' => 1
        ];

        $this->repository->setMockData($expectedData['exercises']);

        // act
        $result = $this->api->getUserExerciseHistory($userId);

        // assert
        assert($result['status'] === 'success');
        assert($result['data']['user_id']) === $userId;
        assert(count($result['data']['exercises']) === 1);
        assert($result['data']['exercises'][0]['type'] === 'running');
    }

    public function testGetUserExerciseHistoryWithFilters() {
        
        // arrange
        $userId = 123;
        $filters = [
            'start_date' => '2021-01-01',
            'end_date' => '2021-01-31',
            'exercise_type' => 'running'
        ];

        // act
        $result = $this->api->getUserExerciseHistory($userId, $filters);

        // assert
        assert($result['status'] === 'success');
        assert(is_array($result['data']['exercises']));
    }

    public function testGetUserExerciseHistoryWithInvalidUserId() { 

        // arrange
        $invalidUserId = -1;

        // act
        $result = $this->api->getUserExerciseHistory($invalidUserId);

        // assert
        assert($result['status'] === 'error');
        assert($result['message'] === 'Invalid user id');
    }
}

//2. 运动记录实体类（Entity） 
// 数据模型
class ExerciseRecord { 
    private $id;
    private $userId;
    private $type;
    private $duration; //分钟
    private $distance; //公里
    private $calories;
    private $createdAt;

    public function __construct($id, $userId, $type, $duration, $distance, $calories, $createdAt) {
        $this->id = $id;
        $this->userId = $userId;
        $this->type = $type;
        $this->duration = $duration;
        $this->distance = $distance;
        $this->calories = $calories;
        $this->createAt = $createdAt;
    }

    public function getId() : int {
        return $this->id;
    }

    public function getUserId() : int {
        return $this->userId;
    }

    public function getType() : string {
        return $this->type;
    }

    public function getDuration() : int {
        return $this->duration;
    }

    public function getDistance() : float {
        return $this->distance;
    }

    public function getCalories() : int {
        return $this->calories;
    }

    public function getCreateAt() : string {
        return $this->createAt;
    }

    public function toArray() : array {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'type' => $this->type,
            'duration' => $this->duration,
            'distance' => $this->distance,
            'calories' => $this->calories,
            'date' => $this->createAt
        ];
    }
}

// 3. 仓储接口 (Repository Interface)
interface ExerciseRepositoryInterface {

    public function findByUserId(int $userId, array $filters = []): array;
    public function countByUserId(int $userId, array $filters =[]): int;
}

// 4. 仓储实现 (Repository Implementation) 
// 具体实现 - 单一职责：每个类只做一件事
class ExerciseRepository implements ExerciseRepositoryInterface {

    private $database;

    public function __constuuct($database){
        $this->database = $database;
    }

    public function findByUserId(int $userId, array $filters = []): array {

        $sql = "SELECT * FROM exercise_records WHERE user_id = :user_id";
        $params = ['user_id' => $userId];

        //添加过滤条件
        if(!empty($filters['start_date'])) {
            $sql .= " AND create_at >= :start_date";
            $params['start_date'] = $filters['start_date'];
        }

        if(!empty($filters['end_date'])) {
            $sql .= " AND create_at <= :end_date";
            $params['end_date'] = $filters['end_date'];
        }

        if(!empty($filters['exercise_type'])) {
            $sql .= " AND type = :exercise_type";
            $params['exercise_type'] = $filters['exercise_type'];
        }

        $sql .= " ORDER BY create_at DESC";

        if(!empty($filters['limit'])) {
            $sql .= " LIMIT :limit";
            $params['limit'] = $filters['limit'];
        }

        if(!empty($filters['offset'])) {
            $sql .= " OFFSET :offset";
            $params['offset'] = $filters['offset'];
        }

        $stmt = $this->database->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetchAll();

        $exercises = [];
        foreach($results as $row) {
            $exercises[] = new ExerciseRecord(
                $row['id'],
                $row['user_id'],
                $row['type'],
                $row['duration'],
                $row['distance'],
                $row['calories'],
                $row['create_at']
            );
        }

        return $exercises;
    }

    public function countByUserId(int $userId, array $filters = []): int {
        $sql = "SELECT COUNT(*) FROM exercise_records WHERE user_id = :user_id";
        $params = ['user_id' => $userId];

        //添加过滤条件
        if (!empty($filters['start_date'])) {
            $sql .= " AND create_at >= :start_date";
            $params['start_date'] = $filters['start_date'];
        }

        if (!emapty($filters['end_date'])) {
            $sql .= " AND create_at <= :end_date";
            $params['end_date'] = $filters['end_date'];            
        }

        if (!empty($filters['exercise_type'])) {
            $sql .= " AND type = :exercise_type";
            $params['exercise_type'] = $filters['exercise_type'];
        }

        $stmt = $this->database->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchColumn();
    }
}

// 5. mock 仓储类 用于测试
// 快速测试：不需要真实数据库
class MockExerciseRepository implements ExerciseRepositoryInterface {
    private $mockData = [];

    public function setMockData(array $data) {
        $this->mockData = $data;
    }

    public function findByUserId(int $userId, array $filters =[]): array {
        $exercises = [];
        foreach($this->mockData as $data) {
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

    public function countByUserId(int $userId, array $filters = []): int {
        return count($this->mockData);
    }
}

// 6. 验证器类
// 复用性：验证逻辑可以在多处使用
class ExerciseHistoryValidator {
    public function validateUserId($userId): array {
        $errors = [];

        if (!is_numeric($userId) || $userId <= 0) {
            $errors[] = 'Invalid user id';
        }

        return $errors;
    }

    public function validateFilters(array $filters): array {
        $errors = [];

        if(!empty($filters['start_date'] && !$this->isValidDate($filters['start_date']))) {
            $errors[] = 'Invalid start date format';
        }
    
        if(!empty($filters['end_date'] && !$this->isValidDate($filters['end_date']))) {
            $errors[] = 'Invalid end date format';
        }

        if(!empty($filters['limit'] && (!is_numeric($filters['limit'])) || $filters['limit'] <= 0)) {
            $errors[] = 'Invalid limit value';
        }

        return $errors;
    }

    private function isValidDate($date): bool {
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
}

// 7. 主要 API 类
class ExerciseHistoryApi {
    private $repository;
    private $validator;

    public function __construct(ExerciseRepositoryInterface $repository) {
        $this->repository = $repository;
        $this->validator = new ExerciseHistoryValidator();
    }

    public function getUserExerciseHistory(int $userId, array $filters =[]) : array {
        try {

            // 验证用户 ID
            $userIdErrors = $this->validator->validateUserId($userId);
            if (!empty($userIdErrors)) {
                return $this->errorResponse($userIdErrors[0]);
            }

            // 验证过滤条件
            $filterErrors = $this->validator->validateFilters($filters);
            if (!empty($filterErrors)){
                return $this->errorResponse(implode(', ', $filterErrors));
            } 

            // 获取运动记录
            $exercises = $this->repository->findByUserId($userId, $filters);
            $totalCount = $this->repository->countByUserId($userId, $filters);

            // 转换为数组格式
            $exerciseData = [];
            foreach($exercises as $exercise) {
                $exerciseData[] = $exercise->toArray();
            }

            return $this->successResponse([
                'user_id' => $userId,
                'exercises' => $exerciseData,
                'total_count' => $totalCount,
                'filters_applied' => $filters
            ]);

        } catch(Exception $e) {
            return $this->errorResponse('Internal server error');
        }
    }

    private function successResponse(array $data): array {
        return [
            'status' => 'success',
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }

    private function errorResponse(string $message): array {
        return [
            'status' => 'error',
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
}

?>