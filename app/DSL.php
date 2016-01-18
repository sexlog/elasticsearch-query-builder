<?php

namespace sexlog\ElasticSearch;

abstract class DSL extends Search
{
    protected function reset()
    {
        $this->must   = [];
        $this->should = [];
        $this->nested = [];
        $this->not    = [];
        $this->fields = [];
    }

    /**
     * @param      $column
     * @param      $value
     * @param bool $nested
     *
     * @return $this
     */
    public function where($column, $value, $nested = false)
    {
        return $this->_where($column, $value, $nested, 'must');
    }

    /**
     * @param      $column
     * @param      $value
     * @param bool $nested
     *
     * @return $this
     */
    public function orWhere($column, $value, $nested = false)
    {
        return $this->_where($column, $value, $nested, 'should');
    }

    /**
     * @param     $column
     * @param     $value
     * @param int $match
     *
     * @return $this
     */
    public function whereIn($column, $value, $match = 1)
    {
        return $this->_whereIn($column, $value, $match, 'must');
    }

    /**
     * @param     $column
     * @param     $value
     * @param int $match
     *
     * @return $this
     */
    public function orWhereIn($column, $value, $match = 1)
    {
        return $this->_whereIn($column, $value, $match, 'should');
    }

    /**
     * @param        $column
     * @param        $value
     *
     * @return $this
     */
    public function wildcard($column, $value)
    {
        return $this->_wildcard($column, $value, 'must');
    }

    /**
     * @param $column
     * @param $value
     *
     * @return Filter
     */
    public function orWildcard($column, $value)
    {
        return $this->_wildcard($column, $value, 'should');
    }

    /**
     * @param        $column
     * @param        $terms
     *
     * @return $this
     */
    public function match($column, $terms)
    {
        return $this->_match($column, $terms, 'must');
    }

    /**
     * @param $column
     * @param $terms
     *
     * @return Filter
     */
    public function orMatch($column, $terms)
    {
        return $this->_match($column, $terms, 'should');
    }

    /**
     * @param        $column
     * @param        $phrase
     * @param int    $slop
     *
     * @return $this
     */
    public function matchPhrase($column, $phrase, $slop = 0)
    {
        return $this->_matchPhrase($column, $phrase, $slop, 'must');
    }

    /**
     * @param     $column
     * @param     $phrase
     * @param int $slop
     *
     * @return Filter
     */
    public function orMatchPhrase($column, $phrase, $slop = 0)
    {
        return $this->_matchPhrase($column, $phrase, $slop, 'should');
    }

    /**
     * @param        $column
     * @param        $value
     *
     * @return $this
     */
    public function startsWith($column, $value)
    {
        return $this->_startsWith($column, $value, 'must');
    }

    /**
     * @param $column
     * @param $value
     *
     * @return Filter
     */
    public function orStarsWith($column, $value)
    {
        return $this->_startsWith($column, $value, 'should');
    }

    /**
     * @param      $column
     * @param      $min
     * @param      $max
     * @param bool $nested
     *
     * @return $this
     */
    public function between($column, $min, $max, $nested = false)
    {
        return $this->_between($column, $min, $max, $nested, 'must');
    }

    /**
     * @param      $column
     * @param      $min
     * @param      $max
     * @param bool $nested
     *
     * @return $this
     */
    public function orBetween($column, $min, $max, $nested = false)
    {
        return $this->_between($column, $min, $max, $nested, 'should');
    }

    /**
     * @param        $column
     * @param        $value
     *
     * @return $this
     */
    public function gt($column, $value)
    {
        return $this->_gt($column, $value, 'must');
    }

    /**
     * @param $column
     * @param $value
     *
     * @return Query
     */
    public function orGt($column, $value)
    {
        return $this->_gt($column, $value, 'should');
    }

    /**
     * @param        $column
     * @param        $value
     *
     * @return $this
     */
    public function lt($column, $value)
    {
        return $this->_lt($column, $value, 'must');
    }

    /**
     * @param $column
     * @param $value
     *
     * @return Query
     */
    public function orLt($column, $value)
    {
        return $this->_lt($column, $value, 'should');
    }
}
