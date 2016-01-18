<?php

namespace sexlog\ElasticSearch;

class Filter extends DSL
{
    /**
     * @var array
     */
    private $filters = [];

    public function reset()
    {
        parent::reset();

        $this->filters = [];
    }

    /**
     * @return bool
     */
    public function hasFilters()
    {
        if (empty($this->must) && empty($this->should) && empty($this->not) && empty($this->nested)) {
            return false;
        }

        return true;
    }

    /**
     * getFilters()
     *
     * @return array
     */
    public function getFilters()
    {
        if ((count($this->must) + count($this->should) + count($this->nested)) == 1 && empty($this->not)) {
            return array_merge($this->must, $this->should, $this->buildNested());
        }

        if (!empty($this->must)) {
            $this->filters['bool']['must'] = $this->must;
        }

        if (!empty($this->should)) {
            $this->filters['bool']['should'] = $this->should;
        }

        if (!empty($this->not)) {
            $this->filters['bool']['must_not'] = $this->not;
        }

        if (!empty($this->nested)) {
            $this->filters['bool']['must'] = isset($this->filters['bool']['must']) ? array_merge($this->filters['bool']['must'], $this->buildNested()) : $this->buildNested();
        }

        return $this->filters;
    }

    /**
     * @param            $column
     * @param            $value
     * @param bool|false $nested
     *
     * @return $this
     */
    public function notWhere($column, $value, $nested = false)
    {
        return $this->_where($column, $value, $nested, 'not');
    }

    /**
     * @param     $column
     * @param     $value
     * @param int $match
     *
     * @return $this
     */
    public function notWhereIn($column, $value, $match = 1)
    {
        return $this->_whereIn($column, $value, $match, 'not');
    }

    /**
     * @param        $column
     * @param string $operand
     *
     * @return $this
     */
    public function exists($column, $operand = 'must')
    {
        $this->{$operand}[]['exists']['field'] = $column;

        return $this;
    }

    /**
     * @param $column
     *
     * @return Filter
     */
    public function notExists($column)
    {
        return $this->exists($column, 'should');
    }

    /**
     * @param     $latitude
     * @param     $longitude
     * @param int $distance
     *
     * @return $this
     */
    public function location($latitude, $longitude, $distance = 100)
    {
        $pos = count($this->must);

        $this->must[$pos]['geo_distance']['distance'] = "{$distance}km";
        $this->must[$pos]['geo_distance']['lat_lon']  = "{$latitude},{$longitude}";

        return $this;
    }
}
