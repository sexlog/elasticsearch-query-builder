<?php

use PHPUnit\Framework\TestCase;

class QueryTest extends TestCase
{
    /**
     * @var \sexlog\ElasticSearch\Query
     */
    private $queryBuilder;

    protected function setUp(): void
    {
        $this->queryBuilder = new \sexlog\ElasticSearch\Query();
    }

    public function testMatchPhrasePrefix()
    {
        $phrase        = str_repeat(uniqid(' '), 4);
        $maxExpansions = rand(1, 5);

        $expectedQuery = [
            'match_phrase_prefix' => [
                'description' => [
                    'query'          => $phrase,
                    'max_expansions' => $maxExpansions,
                ],
            ],
        ];

        $this->queryBuilder->matchPhrasePrefix('description', $phrase, $maxExpansions);

        $this->assertExpectedQuery($expectedQuery);
    }

    public function testMultiMatch()
    {
        $term    = uniqid('Phrase ');
        $columns = ['login', 'password'];

        // Basic Multi Match query
        $expectedQuery = [
            'multi_match' => [
                'query'  => $term,
                'fields' => $columns,
            ],
        ];

        $this->queryBuilder->multiMatch($columns, $term);

        $this->assertExpectedQuery($expectedQuery);

        // Multi Match query with additional parameters
        $type = 'most_fields';

        $expectedQuery = [
            'multi_match' => [
                'query'  => $term,
                'type'   => $type,
                'fields' => $columns,
            ],
        ];

        $this->queryBuilder->multiMatch($columns, ['query' => $term, 'type' => $type]);

        $this->assertExpectedQuery($expectedQuery);
    }

    public function testRegex()
    {
        $regex = 'wom(a|e)n';

        $expectedQuery = [
            'regexp' => [
                'gender' => $regex,
            ],
        ];

        $this->queryBuilder->regex('gender', $regex);

        $this->assertExpectedQuery($expectedQuery);

        $boost = rand(1, 5);

        $expectedQuery = [
            'regexp' => [
                'gender' => [
                    'value' => $regex,
                    'boost' => $boost,
                ],
            ],
        ];

        $this->queryBuilder->regex('gender', ['value' => $regex, 'boost' => $boost]);

        $this->assertExpectedQuery($expectedQuery);
    }

    public function testFuzzy()
    {
        $term = uniqid('fuzzy');

        $expectedQuery = [
            'fuzzy' => [
                'login' => [
                    'value' => $term
                ],
            ],
        ];

        $this->queryBuilder->fuzzy('login', $term);

        $this->assertExpectedQuery($expectedQuery);

        $fuzziness     = rand(1, 5);
        $maxExpansions = rand(1, 5);

        $expectedQuery = [
            'fuzzy' => [
                'login' => [
                    'value'     => $term,
                    'fuzziness' => $fuzziness,
                    'max_expansions' => $maxExpansions
                ],
            ],
        ];

        $this->queryBuilder->fuzzy('login', $expectedQuery['fuzzy']['login'], $fuzziness);

        $this->assertExpectedQuery($expectedQuery);
    }

    public function testFuzzyLike()
    {
        $term   = uniqid('fuzzy');
        $fields = ['login', 'description'];

        $expectedQuery = [
            'fuzzy_like_this' => [
                'fields'    => $fields,
                'like_text' => $term,
            ],
        ];

        $this->queryBuilder->fuzzyLike($fields, $term);

        $this->assertExpectedQuery($expectedQuery);
    }

    public function testAndComposition()
    {
        $first  = uniqid();
        $second = uniqid();

        $this->queryBuilder->where('login', $first)
                           ->where('description', $second);

        $expectedQuery = [
            'bool' => [
                'must' => [
                    [
                        'term' => [
                            'login' => $first,
                        ],
                    ],
                    [
                        'term' => [
                            'description' => $second,
                        ],
                    ],
                ],
            ],
        ];

        $this->assertExpectedQuery($expectedQuery);
    }

    public function testOrComposition()
    {
        $first  = uniqid();
        $second = uniqid();

        $this->queryBuilder->orWhere('login', $first)
                           ->orWhere('description', $second);

        $expectedQuery = [
            'bool' => [
                'should'               => [
                    [
                        'term' => [
                            'login' => $first,
                        ],
                    ],
                    [
                        'term' => [
                            'description' => $second,
                        ],
                    ],
                ],
                'minimum_should_match' => $this->queryBuilder->getMinimumShouldMatch(),
            ],
        ];

        $this->assertExpectedQuery($expectedQuery);
    }

    public function testComplexComposition()
    {
        $first  = uniqid();
        $second = uniqid();
        $third  = uniqid();

        $this->queryBuilder->setMinimumShouldMatch(1);

        $this->queryBuilder->fuzzy('login', $first)
                           ->orWhere('login', $second)
                           ->orMatch('description', $third);

        $expectedQuery = [
            'bool' => [
                'must'                 => [
                    [
                        'fuzzy' => [
                            'login' => [
                                'value' => $first
                            ],
                        ],
                    ],
                ],
                'should'               => [
                    [
                        'term' => [
                            'login' => $second,
                        ],
                    ],
                    [
                        'match' => [
                            'description' => $third,
                        ],
                    ],
                ],
                'minimum_should_match' => $this->queryBuilder->getMinimumShouldMatch(),
            ],
        ];

        $this->assertExpectedQuery($expectedQuery);
    }

    public function testMinimumShouldMatch()
    {
        $this->markTestIncomplete('This test hasn\'t been implemented yet');
    }

    public function testNestedAndQuery()
    {
        $this->markTestIncomplete('This test hasn\'t been implemented yet');
    }

    public function testNestedOrQuery()
    {
        $this->markTestIncomplete('This test hasn\'t been implemented yet');
    }

    private function assertExpectedQuery($expectedQuery)
    {
        $query = $this->queryBuilder->getQuery();

        $this->assertEquals($expectedQuery, $query);

        $this->queryBuilder->reset();
    }
}
