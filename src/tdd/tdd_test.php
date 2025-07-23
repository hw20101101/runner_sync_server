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

?>