<?php

class DslTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var \Sexlog\ElasticSearch\Query
     */
    private $queryBuilder;

    protected function setUp()
    {
        $this->queryBuilder = new \Sexlog\ElasticSearch\Query();
    }

    public function testWhere()
    {
        $login = uniqid();

        $expectedDsl = [
            'term' => [
                'login' => $login,
            ],
        ];

        $this->queryBuilder->where('login', $login);

        $this->assertExpectedDsl($expectedDsl);
    }

    public function testWhereIn()
    {
        $terms = [uniqid('first_'), uniqid('second_')];

        $expectedDsl = [
            'terms' => [
                'login'     => $terms,
                'execution' => 'bool',
            ],
        ];

        $this->queryBuilder->whereIn('login', $terms);

        $this->assertExpectedDsl($expectedDsl);
    }

    public function testWildcard()
    {
        $login = uniqid();

        $expectedDsl = [
            'wildcard' => [
                'login' => $login,
            ],
        ];

        $this->queryBuilder->wildcard('login', $login);

        $this->assertExpectedDsl($expectedDsl);
    }

    public function testMatch()
    {
        $login = uniqid();

        $expectedDsl = [
            'match' => [
                'login' => $login,
            ],
        ];

        $this->queryBuilder->match('login', $login);

        $this->assertExpectedDsl($expectedDsl);
    }

    public function testComplexMatch()
    {
        $login = uniqid();

        $expectedDsl = [
            'match' => [
                'login' => [
                    'query' => $login,
                    'type'  => 'phrase',
                ],
            ],
        ];

        $this->queryBuilder->match('login', ['query' => $login, 'type' => 'phrase']);

        $this->assertExpectedDsl($expectedDsl);
    }

    public function testMatchPhrase()
    {
        $phrase = str_repeat(uniqid(' '), 3);

        $expectedDsl = [
            'match_phrase' => [
                'description' => [
                    'query' => $phrase,
                    'slop'  => 0,
                ],
            ],
        ];

        $this->queryBuilder->matchPhrase('description', $phrase);

        $this->assertExpectedDsl($expectedDsl);
    }

    public function testStartsWith()
    {
        $startWith = uniqid();

        $expectedDsl = [
            'prefix' => [
                'login' => $startWith
            ]
        ];

        $this->queryBuilder->startsWith('login', $startWith);

        $this->assertExpectedDsl($expectedDsl);
    }

    public function testBetween()
    {
        $min = rand(0, 10);
        $max = rand(90, 100);

        $expectedDsl = [
            'range' => [
                'age' => [
                    'gte' => $min,
                    'lte' => $max
                ]
            ]
        ];

        $this->queryBuilder->between('age', $min, $max);

        $this->assertExpectedDsl($expectedDsl);
    }

    public function testGreaterThan()
    {
        $value = rand(0, 100);

        $expectedDsl = [
            'range' => [
                'age' => [
                    'gte' => $value,
                ],
            ],
        ];

        $this->queryBuilder->gt('age', $value);

        $this->assertExpectedDsl($expectedDsl);
    }

    public function testLessThan()
    {
        $value = rand(0, 100);

        $expectedDsl = [
            'range' => [
                'age' => [
                    'lte' => $value,
                ],
            ],
        ];

        $this->queryBuilder->lt('age', $value);

        $this->assertExpectedDsl($expectedDsl);
    }

    private function assertExpectedDsl($expectedDsl)
    {
        $dsl = $this->queryBuilder->getQuery();

        $this->assertEquals($expectedDsl, $dsl);
    }
}
