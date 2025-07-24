<?php

//1. 首先编写测试用例
class ExerciseHistoryApiTest {

    private $api;
    private $repository;

    public function setUp() {
        $this->repository = new MockExerciseRepository();
        $this->api = new ExerciseHistoryApi($this->repository);
    }

    public function testGetUserExerciseHistory() {
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
            $sql .= " AND create_at >= :end_date";
            $params['end_date'] = $filters['end_date'];
        }

        if(!empty($filters['exercise_type'])) {
            $sql .= " AND type >= :exercise_type";
            $params['exercise_type'] = $filters['exercise_type'];
        }

        $sql .= " ORDER BY create_at DESC";

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
}


?>