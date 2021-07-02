<?php

namespace sexlog\ElasticSearch;

use Elasticsearch\Client;
use Elasticsearch\Common\Exceptions\Missing404Exception;
use Monolog\Logger;
use sexlog\ElasticSearch\Exceptions\InvalidDocumentException;
use sexlog\ElasticSearch\Exceptions\InvalidIndexException;
use sexlog\ElasticSearch\Model\Highlight;
use sexlog\ElasticSearch\Model\Translator;

class ElasticSearch
{
    /**
     * @var \Elasticsearch\Client
     */
    private $client;

    /**
     * @var string
     */
    private $index;

    /**
     * @var string
     */
    private $document;

    /**
     * @var \Monolog\Logger
     */
    private $logger;

    /**
     * @var bool
     */
    private $debug = false;

    /**
     * @var array
     */
    private $body = [];

    /**
     * @var null
     */
    private $query = null;

    /**
     * @var null
     */
    private $postQuery = null;

    /**
     * @var null
     */
    private $queriedFields = null;

    /**
     * @var null
     */
    private $filter = null;

    /**
     * @var null
     */
    private $postFilter = null;

    /**
     * @var array
     */
    private $sort = null;

    /**
     * @var null
     */
    private $groupBy = null;

    /**
     * @var \sexlog\ElasticSearch\Model\Translator;
     */
    private $translator;

    /**
     * @var string
     */
    private $geoDistanceAttribute = 'lat_lon';

    const DEFAULT_PAGE_SIZE = 10;

    /**
     * @param        $index
     * @param        $document
     * @param Client $client
     */
    public function __construct($index, $document, Client $client)
    {
        $this->index    = $index;
        $this->document = $document;
        $this->client   = $client;

        $this->translator = null;
    }

    /**
     * @param Model\Translator $translator
     */
    public function setTranslator(Translator $translator)
    {
        $this->translator = $translator;
    }

    /**
     * @return Translator
     */
    public function getTranslator()
    {
        if (!isset($this->translator)) {
            $this->translator = new Translator();
        }

        return $this->translator;
    }

    /**
     * @param \Monolog\Logger $logger
     */
    public function setLogger(Logger $logger)
    {
        $this->debug = true;

        $this->logger = $logger;
    }

    /**
     * @param bool $debug
     */
    public function setDebug($debug)
    {
        $this->debug = $debug;
    }

    /**
     * @param $attribute
     *
     * @throws \InvalidArgumentException
     */
    public function setGeoDistanceAttribute($attribute)
    {
        if (empty($attribute))
        {
            throw new \InvalidArgumentException;
        }

        $this->geoDistanceAttribute = $attribute;
    }

    /**
     * Changes the index on which ElasticSearch will run queries.
     *
     * @param      $index
     * @param null $document
     *
     * @return $this
     * @throws InvalidIndexException
     */
    public function changeIndex($index, $document = null)
    {
        if (empty($index)) {
            throw new InvalidIndexException;
        }

        $this->index = $index;

        // Change the current document only if supplied
        if (!is_null($document)) {
            $this->document = $document;
        }

        return $this;
    }

    /**
     * @param      $document
     * @param null $index
     *
     * @return $this
     * @throws InvalidDocumentException
     */
    public function changeDocument($document, $index = null)
    {
        if (empty($document)) {
            throw new InvalidDocumentException;
        }

        $this->document = $document;

        if (!is_null($index)) {
            $this->index = $index;
        }

        return $this;
    }

    /**
     * @param Highlight $highlight
     *
     * @return mixed
     */
    private function buildRequestBody(Highlight $highlight)
    {
        $params['index'] = $this->index;
        $params['type']  = $this->document;

        $params['body'] = $this->body;

        $this->buildQuery($params);

        if (!is_null($this->filter)) {
            $params['body']['query']['filtered']['filter'] = $this->filter;
        }

        if (!is_null($this->postFilter)) {
            $params['body']['post_filter'] = (count($this->postFilter) == 1 ? array_shift($this->postFilter) : $this->postFilter);
        }

        if (!is_null($this->sort)) {
            $params['body']['sort'] = $this->buildSort();
        }

        if (!is_null($this->groupBy)) {
            $params['body']['aggs'] = $this->groupBy;
        }

        if ($highlight->shouldHighlight() && !is_null($this->queriedFields)) {
            $params['body']['highlight']['pre_tags']  = '<em>';
            $params['body']['highlight']['post_tags'] = '</em>';

            foreach ($this->queriedFields as $key => $field) {
                $params['body']['highlight']['fields'][$key] = [
                    'fragment_size'       => 2000,
                    'number_of_fragments' => 1,
                ];
            }
        }

        if ($this->debug) {
            $this->logger->debug('JSON: ' . json_encode($params));
        }

        return $params;
    }

    /**
     * buildSort()
     *
     * @return array|mixed
     */
    private function buildSort()
    {
        if (count($this->sort) == 1 && !isset($this->sort[0]['proximity'])) {
            return array_shift($this->sort);
        }

        $sort = [];

        while ($condition = array_shift($this->sort)) {
            $column = key($condition);

            if ($column === 'proximity') {
                if (is_null($condition[$column])) {
                    continue;
                }

                $sort[]['_geo_distance'] = [
                    $this->geoDistanceAttribute => $condition[$column],
                    'order'                     => 'asc',
                    'unit'                      => 'km',
                    'mode'                      => 'min',
                    'distance_type'             => 'sloppy_arc',
                ];

                continue;
            }

            $sort[][$column] = $condition[$column];
        }

        return $sort;
    }

    /**
     * buildQuery(&$params)
     *
     * @param $params
     *
     * @return null|\stdClass
     */
    private function buildQuery(&$params)
    {
        if (!is_null($this->filter)) {
            if (empty($this->query)) {
                return $params['body']['query']['filtered']['query']['match_all'] = new \stdClass;
            }

            return $params['body']['query']['filtered']['query'] = $this->query;
        }

        if (is_null($this->query)) {
            return $params['body']['query']['match_all'] = new \stdClass;
        }

        return $params['body']['query'] = $this->query;
    }

    /**
     * get()
     *
     * @return null|array
     */
    public function get()
    {
        $body = $this->buildRequestBody(new Highlight());

        try {
            $results = $this->client->search($body);
        } catch (\Exception $e) {
            if ($this->debug) {
                $this->logger->error('There was an error when querying ElasticSearch. Query sent: ' . json_encode($body));
            }

            $results = null;
        }

        if (empty($results)) {
            return null;
        }

        return $this->processResults($results);
    }

    public function delete()
    {
        $body = $this->buildRequestBody(new Highlight(false));

        try {
            $result = $this->client->deleteByQuery($body);
        } catch (\Exception $e) {
            if ($this->debug) {
                $this->logger->debug($this->translator->get('query_error') . json_encode($body));
            }

            $result = null;
        }

        return $result;
    }


    public function deleteById($id)
    {
        $params = [
            'id'    => $id,
            'index' => $this->index,
            'type'  => $this->document,
        ];
        try {
            $result = $this->client->delete($params);
        } catch (Missing404Exception $e) {
            $result = null;
            if ($this->debug) {
                $this->logger->debug('Object not found #' . $id . $e->getMessage());
            }

        } catch (\Exception $e) {
            $result = null;
            $this->logger->error('There was an error on deleteById ' . $e->getMessage());
        }
        return $result;
    }

    public function getById($id)
    {
        $params = [
            'id'    => $id,
            'index' => $this->index,
            'type'  => $this->document,
        ];

        try {
            $result = $this->client->get($params);
        } catch (Missing404Exception $e) {
            $result = null;
            if ($this->debug) {
                $this->logger->debug('Object not found #' . $id . $e->getMessage());
            }

        } catch (\Exception $e) {
            $result = null;
            $this->logger->error('There was an error on getById ' . $e->getMessage());
        }

        if (empty($result['_source'])) {
            return null;
        }

        return $result['_source'];
    }

    /**
     * processResults($results)
     *
     * @param $results
     *
     * @return array|null
     */
    private function processResults($results)
    {
        if (!isset($results['hits']['hits'])) {
            return null;
        }

        $hits = $this->processHits($results['hits']['hits']);

        $data = [
            'documents' => $hits,
            'total'     => $results['hits']['total'],
            'max_score' => $results['hits']['max_score'],
        ];

        return $data;
    }

    /**
     * processHits($hits)
     *
     * @param $hits
     *
     * @return array
     */
    private function processHits($hits)
    {
        $count   = count($hits);
        $results = [];

        for ($i = 0; $i < $count; $i++) {
            $results[] = $this->processHit($hits[$i]);
        }

        return $results;
    }

    /**
     * @param $hit
     *
     * @return \stdClass
     */
    private function processHit($hit)
    {
        $result        = new \stdClass;
        $result->id    = $hit['_id'];
        $result->score = $hit['_score'];

        // Highlight has been required and available
        if (isset($hit['highlight'])) {
            $result->highlight = new \stdClass;

            foreach ($hit['highlight'] as $key => $value) {
                $result->highlight->{$key} = is_array($value) ? $value[0] : $value;
            }
        }

        if (isset($hit['sort'])) {
            $result->sort = $hit['sort'];
        }

        // Set properties from the 'field' array
        if (isset($hit['fields'])) {
            foreach ($hit['fields'] as $key => $value) {
                $result->{$key} = is_array($value) ? array_shift($value) : $value;
            }

            return $result;
        }

        // Set properties from the 'source' array
        if (isset($hit['_source'])) {
            foreach ($hit['_source'] as $key => $value) {
                $result->{$key} = is_array($value) ? array_shift($value) : $value;
            }
        }

        return $result;
    }

    /**
     * page($page)
     *
     * @param $page
     *
     * @return $this
     */
    public function page($page)
    {
        $from = 0;

        if (isset($this->body['size'])) {
            $from = $page * $this->body['size'];
        }

        $this->body['from'] = $from;

        return $this;
    }

    /**
     * take($records)
     *
     * @param $records
     *
     * @return $this
     */
    public function take($records)
    {
        $this->body['size'] = $records;

        return $this;
    }

    /**
     * @return int
     */
    public function getPageSize()
    {
        if(isset($this->body['size'])) {
            return $this->body['size'];
        }

        return static::DEFAULT_PAGE_SIZE;
    }

    /**
     * score($score, $type = 'gt')
     *
     * @param        $score
     * @param string $type
     *
     * @return $this
     */
    public function score($score, $type = 'gt')
    {
        if ($type == 'gt') {
            $this->body['min_score'] = $score;
        }

        if ($type == 'lt') {
            $this->body['max_score'] = $score;
        }

        return $this;
    }

    /**
     * select($fields)
     *
     * @param $fields
     *
     * @return $this
     */
    public function select($fields)
    {
        if (!is_array($fields)) {
            $fields = $this->prepareFields($fields);
        }

        if (!isset($this->body['fields'])) {
            $this->body['fields'] = $fields;

            return $this;
        }

        $this->body['fields'] = array_merge($this->body['fields'], $fields);

        return $this;
    }

    /**
     * prepareFields($fields)
     *
     * @param $fields
     *
     * @return array
     */
    private function prepareFields($fields)
    {
        if (strpos($fields, '*') !== false) {
            return $fields;
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
     * @param        $column
     * @param string $order
     *
     * @return $this
     */
    public function orderBy($column, $order = 'asc')
    {
        if (is_array($column)) {
            $key                = key($column);
            $this->sort[][$key] = $column[$key];

            return $this;
        }

        $this->sort[][$column] = $order;

        return $this;
    }

    /**
     * @param $column
     *
     * @return $this
     */
    public function groupBy($column)
    {
        $this->groupBy["{$column}s"]['terms']['field'] = $column;

        return $this;
    }

    /**
     * @param Query $query
     *
     * @return $this
     */
    public function setQuery(Query $query)
    {
        $this->query         = $query->getQuery();
        $this->queriedFields = $query->getFields();

        return $this;
    }

    /**
     * @param Query $query
     *
     * @return $this
     */
    public function setPostQuery(Query $query)
    {
        $this->postQuery = $query->getQuery();

        return $this;
    }

    /**
     * @param Filter $filter
     *
     * @return $this
     */
    public function setFilter(Filter $filter)
    {
        $this->filter = $filter->getFilters();

        return $this;
    }

    /**
     * @param Filter $filter
     *
     * @return $this
     */
    public function setPostFilter(Filter $filter)
    {
        $this->postFilter = $filter->getFilters();

        return $this;
    }

    public function cleanRequest()
    {
        $this->cleanOrder()
             ->cleanFilters()
             ->cleanQuery()
             ->cleanGroup();

        return $this;
    }

    /**
     * @return $this
     */
    public function cleanOrder()
    {
        $this->sort = null;

        return $this;
    }

    /**
     * @return $this
     */
    public function cleanFilters()
    {
        $this->filter     = null;
        $this->postFilter = null;

        return $this;
    }

    /**
     * @return $this
     */
    public function cleanQuery()
    {
        $this->query     = null;
        $this->postQuery = null;

        return $this;
    }

    /**
     * @return $this
     */
    public function cleanGroup()
    {
        $this->groupBy = null;

        return $this;
    }
}
