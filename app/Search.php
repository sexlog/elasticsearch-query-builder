<?php

namespace sexlog\ElasticSearch;

/**
 * Class Search
 *
 * @package sexlog\ElasticSearch
 *
 * TODO - Develop a method to validate accepted DSL parameters
 */
abstract class Search
{
    /**
     * @var array
     */
    protected $must = [];

    /**
     * @var array
     */
    protected $should = [];

    /**
     * @var array
     */
    protected $not = [];

    /**
     * @var array
     */
    protected $nested = [];

    /**
     * @var array
     */
    protected $fields = [];

    /**
     * @param $field
     */
    private function registerQueriedFields($field)
    {
        if (!is_array($field)) {
            $this->fields[$field] = new \stdClass;

            return;
        }

        foreach ($field as $key => $f) {
            if (strpos($f, '^') !== false) {
                $f = substr($f, 0, strpos($f, '^'));
            }

            $this->fields[$f] = new \stdClass();
        }
    }

    /**
     * @param $column
     * @param $parameters
     * @param $queryType
     * @param $operand
     *
     * @return $this
     */
    protected function bindParameters($column, $parameters, $queryType, $operand)
    {
        $pos = count($this->{$operand});

        foreach ($parameters as $key => $parameter) {
            if (empty($column)) {
                $this->{$operand}[($pos - 1)][$queryType][$key] = $parameter;
                continue;
            }

            $this->{$operand}[$pos][$queryType][$column][$key] = $parameter;
        }

        return $this;
    }

    /**
     * @param $fields
     *
     * @return array
     */
    protected function prepareFields($fields)
    {
        if (is_null($fields)) {
            return ['null'];
        }

        $fields = str_replace(',', ';', $fields);

        if (strpos($fields, ';') === false) {
            return $fields;
        }

        $fieldsArray = explode(';', $fields);

        foreach ($fieldsArray as $key => $value) {
            $fieldsArray[$key] = trim($value);
        }

        return $fieldsArray;
    }

    /**
     * @param $column
     * @param $value
     * @param $nested
     * @param $operand
     *
     * @return $this
     */
    protected function _where($column, $value, $nested, $operand)
    {
        $this->registerQueriedFields($column);

        if ($nested) {
            $this->nestColumn('term', $column, $value);

            return $this;
        }

        if (is_array($value)) {
            return $this->bindParameters($column, $value, 'term', $operand);
        }

        $this->{$operand}[]['term'][$column] = $value;

        return $this;
    }

    /**
     * @param $column
     * @param $value
     * @param $match
     * @param $operand
     *
     * @return $this
     */
    protected function _whereIn($column, $value, $match, $operand)
    {
        $this->registerQueriedFields($column);

        if (!is_array($value)) {
            $value = $this->prepareFields($value);
        }

        $pos = count($this->{$operand});

        $this->{$operand}[$pos]['terms'][$column]     = $value;
        $this->{$operand}[$pos]['terms']['execution'] = 'bool';

        // $this->{$operand}[$pos]['terms']['minimum_match'] = $operand === 'not' ? 0 : (is_numeric($match) ? $match : 1);

        return $this;
    }

    /**
     * @param $column
     * @param $value
     * @param $operand
     *
     * @return $this
     */
    protected function _wildcard($column, $value, $operand)
    {
        $this->registerQueriedFields($column);

        if (is_array($value)) {
            return $this->bindParameters($column, $value, 'wildcard', $operand);
        }

        $this->{$operand}[]['wildcard'][$column] = $value;

        return $this;
    }

    /**
     * @param $column
     * @param $terms
     * @param $operand
     *
     * @return $this
     */
    protected function _match($column, $terms, $operand)
    {
        $this->registerQueriedFields($column);

        if (is_array($terms)) {
            return $this->bindParameters($column, $terms, 'match', $operand);
        }

        $this->{$operand}[]['match'][$column] = $terms;

        return $this;
    }

    /**
     * @param $column
     * @param $phrase
     * @param $slop
     * @param $operand
     *
     * @return $this
     */
    protected function _matchPhrase($column, $phrase, $slop, $operand)
    {
        $this->registerQueriedFields($column);

        $pos = count($this->{$operand});

        $this->{$operand}[$pos]['match_phrase'][$column]['query'] = $phrase;
        $this->{$operand}[$pos]['match_phrase'][$column]['slop']  = $slop;

        return $this;
    }

    /**
     * @param $column
     * @param $phrase
     * @param $expansions
     * @param $operand
     *
     * @return $this
     */
    protected function _matchPhrasePrefix($column, $phrase, $expansions, $operand)
    {
        $this->registerQueriedFields($column);

        $pos = count($this->{$operand});

        $this->{$operand}[$pos]['match_phrase_prefix'][$column]['query']          = $phrase;
        $this->{$operand}[$pos]['match_phrase_prefix'][$column]['max_expansions'] = $expansions;

        return $this;
    }

    /**
     * @param $columns
     * @param $phrase
     * @param $operand
     *
     * @return $this
     */
    protected function _multiMatch($columns, $phrase, $operand)
    {
        if (!is_array($columns)) {
            $columns = $this->prepareFields($columns);
        }

        $this->registerQueriedFields($columns);

        $pos = count($this->{$operand});

        $this->{$operand}[$pos]['multi_match']['fields'] = $columns;

        if (is_array($phrase)) {
            return $this->bindParameters('', $phrase, 'multi_match', $operand);
        }

        $this->{$operand}[$pos]['multi_match']['query'] = $phrase;

        return $this;
    }

    /**
     * @param $column
     * @param $value
     * @param $operand
     *
     * @return $this
     */
    protected function _startsWith($column, $value, $operand)
    {
        $this->registerQueriedFields($column);

        if (is_array($value)) {
            return $this->bindParameters($column, $value, 'prefix', $operand);
        }

        $this->{$operand}[]['prefix'][$column] = $value;

        return $this;
    }

    /**
     * @param $column
     * @param $min
     * @param $max
     * @param $nested
     * @param $operand
     *
     * @return $this
     */
    protected function _between($column, $min, $max, $nested, $operand)
    {
        $this->registerQueriedFields($column);

        if ($nested) {
            $this->nestColumn('range', $column, ['gte' => $min, 'lte' => $max]);

            return $this;
        }

        $pos = count($this->{$operand});

        $this->{$operand}[$pos]['range'][$column]['gte'] = $min;
        $this->{$operand}[$pos]['range'][$column]['lte'] = $max;

        return $this;
    }

    /**
     * @param $column
     * @param $value
     * @param $operand
     *
     * @return $this
     */
    protected function _gt($column, $value, $operand)
    {
        $this->registerQueriedFields($column);

        $this->{$operand}[]['range'][$column]['gte'] = $value;

        return $this;
    }

    /**
     * @param $column
     * @param $value
     * @param $operand
     *
     * @return $this
     */
    protected function _lt($column, $value, $operand)
    {
        $this->registerQueriedFields($column);

        $this->{$operand}[]['range'][$column]['lte'] = $value;

        return $this;
    }

    /**
     * @param $column
     * @param $pattern
     * @param $operand
     *
     * @return $this
     */
    protected function _regex($column, $pattern, $operand)
    {
        $this->registerQueriedFields($column);

        if (is_array($pattern)) {
            return $this->bindParameters($column, $pattern, 'regexp', $operand);
        }

        $this->{$operand}[]['regexp'][$column] = $pattern;

        return $this;
    }

    /**
     * @param $column
     * @param $value
     * @param $operand
     *
     * @return $this
     */
    protected function _fuzzy($column, $value, $operand)
    {
        $this->registerQueriedFields($column);

        if (is_array($value)) {
            return $this->bindParameters($column, $value, 'fuzzy', $operand);
        }

        $this->{$operand}[]['fuzzy'][$column] = $value;

        return $this;
    }

    /**
     * @param $column
     * @param $value
     * @param $params
     * @param $operand
     *
     * @return $this
     */
    protected function _fuzzyLike($column, $value, $params, $operand)
    {
        $this->registerQueriedFields($column);

        $pos = count($this->{$operand});

        if (is_array($params)) {
            return $this->bindParameters($column, $value, 'fuzzy_like_this', $operand);
        }

        $this->{$operand}[$pos]['fuzzy_like_this']['fields']    = is_array($column) ? $column : $this->prepareFields($column);
        $this->{$operand}[$pos]['fuzzy_like_this']['like_text'] = $value;

        return $this;
    }

    /**
     * @return array
     */
    protected function buildNested()
    {
        $nestedArray = [];

        foreach ($this->nested as $condition => $field) {
            $pos = count($nestedArray);

            foreach ($field as $column => $value) {
                if ($condition === 'range') {
                    $nestedArray[$pos]['bool']['should'][] = $this->nest($condition, $column, $value);
                    continue;
                }

                $nestedArray[]['bool']['should'] = $this->nest($condition, $column, $value);
            }
        }

        return $nestedArray;
    }

    /**
     * @param $condition
     * @param $column
     * @param $value
     *
     * @return array
     */
    private function nest($condition, $column, $value)
    {
        $nest = [];

        foreach ($value as $v) {
            if ($condition === 'range') {
                $nest[$condition][$column] = $value;
                break;
            }

            $nest[][$condition][$column] = $v;
        }

        return $nest;
    }

    /**
     * @param $condition
     * @param $column
     * @param $value
     */
    private function nestColumn($condition, $column, $value)
    {
        if (is_array($value)) {
            $this->nested[$condition][$column] = $value;

            return;
        }

        $this->nested[$condition][$column][] = $value;
    }
}
