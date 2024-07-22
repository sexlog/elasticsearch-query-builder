<?php

namespace sexlog\ElasticSearch;

class Query extends DSL
{
    /**
     * @var int
     */
    private $minimumShouldMatch;

    /**
     * @var array
     */
    protected $query = [];

    public function __construct()
    {
        $this->minimumShouldMatch = 0;
    }

    /**
     * @param mixed $minimumShouldMatch
     */
    public function setMinimumShouldMatch($minimumShouldMatch)
    {
        $this->minimumShouldMatch = $minimumShouldMatch;
    }

    /**
     * @return int
     */
    public function getMinimumShouldMatch()
    {
        return $this->minimumShouldMatch;
    }

    /**
     * Build the query array which should be sent to ElasticSearch class to perform the search
     *
     * @return array
     */
    public function getQuery()
    {
        // if it's a simple query/filter there is no need to create a bool query
        if ((count($this->must) + count($this->should)) === 1) {
            $a = array_merge($this->must, $this->should);
            return array_shift($a);
        }

        // required conditions (AND)
        if (!empty($this->must)) {
            $this->query['bool']['must'] = $this->must;
        }

        // optional conditions (OR)
        if (!empty($this->should)) {
            $this->query['bool']['should']               = $this->should;
            $this->query['bool']['minimum_should_match'] = $this->minimumShouldMatch;
        }

        return $this->query;
    }

    /**
     * @return array|null
     */
    public function getFields()
    {
        if (!empty($this->fields)) {
            return $this->fields;
        }

        return null;
    }

    /**
     *
     */
    public function reset()
    {
        parent::reset();

        $this->query              = [];
        $this->minimumShouldMatch = 0;
    }

    /**
     * @param          $column
     * @param          $phrase
     * @param int|null $maxExpansions
     *
     * @return $this
     */
    public function matchPhrasePrefix($column, $phrase, $maxExpansions = 150)
    {
        return $this->_matchPhrasePrefix($column, $phrase, $maxExpansions, 'must');
    }

    /**
     * @param          $column
     * @param          $phrase
     * @param int|null $maxExpansions
     *
     * @return $this
     */
    public function orMatchPhrasePrefix($column, $phrase, $maxExpansions = 150)
    {
        return $this->_matchPhrasePrefix($column, $phrase, $maxExpansions, 'should');
    }

    /**
     * @param        $columns
     * @param        $phrase
     *
     * @return $this
     */
    public function multiMatch($columns, $phrase)
    {
        return $this->_multiMatch($columns, $phrase, 'must');
    }

    /**
     * @param $columns
     * @param $phrase
     *
     * @return Filter
     */
    public function orMultiMatch($columns, $phrase)
    {
        return $this->_multiMatch($columns, $phrase, 'should');
    }

    /**
     * @param        $column
     * @param        $pattern
     *
     * @return $this
     */
    public function regex($column, $pattern)
    {
        return $this->_regex($column, $pattern, 'must');
    }

    /**
     * @param $column
     * @param $pattern
     *
     * @return Filter
     */
    public function orRegex($column, $pattern)
    {
        return $this->_regex($column, $pattern, 'should');
    }

    /**
     * @param                 $column
     * @param                 $value
     * @param int|string|null $fuzziness
     * @param int|null        $maxExpansions
     *
     * @return $this
     */
    public function fuzzy($column, $value, $fuzziness = null, $maxExpansions = null)
    {
        return $this->_fuzzy($column, $value, $fuzziness, $maxExpansions, 'must');
    }

    /**
     * @param                 $column
     * @param                 $value
     * @param int|string|null $fuzziness
     * @param int|null        $maxExpansions
     *
     * @return $this
     */
    public function orFuzzy($column, $value, $fuzziness = null, $maxExpansions = null)
    {
        return $this->_fuzzy($column, $value, $fuzziness, $maxExpansions, 'should');
    }

    /**
     * @param        $column
     * @param        $value
     * @param null   $params
     *
     * @return $this
     */
    public function fuzzyLike($column, $value, $params = null)
    {
        return $this->_fuzzyLike($column, $value, $params, 'must');
    }

    /**
     * @param      $column
     * @param      $value
     * @param null $params
     *
     * @return $this
     */
    public function orFuzzyLike($column, $value, $params = null)
    {
        return $this->_fuzzyLike($column, $value, $params, 'should');
    }
}
