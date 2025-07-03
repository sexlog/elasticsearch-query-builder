<?php

namespace sexlog\ElasticSearch;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Monolog\Logger;
use sexlog\ElasticSearch\Exceptions\InvalidIndexException;
use sexlog\ElasticSearch\Model\Highlight;
use sexlog\ElasticSearch\Model\Translator;

class ElasticSearch
{
    /**
     * @var \Elastic\Elasticsearch\Client
     */
    private $client;

    /**
     * @var string
     */
    private $index;

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
     * @var array
     */
    private $functions = [];

    /**
     * @var string|null
     */
    private $boostMode = null;

    /**
     * @var string|null
     */
    private $scoreMode = null;

    /**
     * @param        $index
     * @param Client $client
     */
    public function __construct($index, Client $client)
    {
        $this->index    = $index;
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
     *
     * @return $this
     * @throws InvalidIndexException
     */
    public function changeIndex($index)
    {
        if (empty($index)) {
            throw new InvalidIndexException;
        }

        $this->index = $index;

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

        $params['body'] = $this->body;

        $this->buildQuery($params);

        if (!is_null($this->filter)) {
            $params['body']['query']['bool']['filter'] = $this->filter;
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
                    'distance_type'             => 'arc',
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
                return $params['body']['query']['bool']['must']['match_all'] = new \stdClass;
            }

            return $params['body']['query']['bool']['must'] = $this->query;
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
        ];
        try {
            $result = $this->client->delete($params);
        } catch (ClientResponseException $e) {
            $result = null;

            if ($this->debug && $e->getResponse()->getStatusCode() === 404) {
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
        ];

        try {
            $result = $this->client->get($params);
        } catch (ClientResponseException $e) {
            $result = null;

            if ($this->debug && $e->getResponse()->getStatusCode() === 404) {
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
     * setTrackTotalHits(bool|int $trackTotalHits)
     *
     * @param bool|int $trackTotalHits
     *
     * @return $this
     */
    public function setTrackTotalHits(bool|int $trackTotalHits)
    {
        $this->body['track_total_hits'] = $trackTotalHits;

        return $this;
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
        if (is_null($fields)) {
            return null;
        }

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

    /**
     * Define o boost_mode e o score_mode para a consulta function_score.
     *
     * @param string $scoreMode 'multiply', 'sum', 'avg', 'first', 'max', 'min'
     * @param string $boostMode 'multiply', 'replace', 'sum', 'avg', 'max', 'min'
     * @return $this
     */
    public function setScoreFunctionsMode(string $scoreMode = 'sum', string $boostMode = 'multiply'): self
    {
        $this->scoreMode = $scoreMode;
        $this->boostMode = $boostMode;
        return $this;
    }

    /**
     * Adiciona uma função de decaimento 'gauss' para a consulta.
     *
     * @param string      $field   O campo (geo_point, date ou numeric).
     * @param mixed       $origin  O ponto de origem (ex: "lat,lon").
     * @param string      $scale   A distância onde o score começa a decair.
     * @param string|null $offset  Distância para começar a aplicar o decaimento.
     * @param float       $decay   Fator de decaimento (entre 0 e 1.0).
     * @param float|null  $weight  Multiplicador para o score da função.
     * @return $this
     */
    public function addGaussFunction(string $field, $origin, string $scale, ?string $offset = null, float $decay = 0.5, ?float $weight = null): self
    {
        $gauss = [
            $field => [
                'origin' => $origin,
                'scale'  => $scale,
                'decay'  => $decay,
            ],
        ];

        if (!is_null($offset)) {
            $gauss[$field]['offset'] = $offset;
        }

        $function = ['gauss' => $gauss];

        if (!is_null($weight)) {
            $function['weight'] = $weight;
        }

        $this->functions[] = $function;
        return $this;
    }

    /**
     * Executa uma busca usando function_score.
     * Este método constrói a query de forma isolada, sem afetar o método get().
     *
     * @return array|null
     */
    public function searchWithFunctions(): ?array
    {
        $body = $this->buildFunctionScoreRequestBody();

        try {
            $results = $this->client->search($body);
        } catch (\Exception $e) {
            if ($this->debug) {
                $this->logger->error('There was an error when querying ElasticSearch with functions. Query sent: ' . json_encode($body));
            }
            return null;
        }

        if (empty($results)) {
            return null;
        }

        return $this->processResults($results); // Reutiliza o processador de resultados existente
    }

    /**
     * Constrói o corpo da requisição para 'function_score'.
     *
     * @return array
     */
    private function buildFunctionScoreRequestBody(): array
    {
        $params = [
            'index' => $this->index,
            'body'  => $this->body, // Reutiliza 'from', 'size', etc.
        ];

        $mainQuery = ['match_all' => new \stdClass];
        if (!is_null($this->query)) {
            $mainQuery = $this->query;
        }

        $queryWithFilter = ['bool' => ['must' => $mainQuery]];
        if (!is_null($this->filter)) {
            $queryWithFilter['bool']['filter'] = $this->filter;
        }

        $functionScoreQuery = [
            'function_score' => [
                'query'     => $queryWithFilter,
                'functions' => $this->functions,
            ],
        ];

        if ($this->boostMode) {
            $functionScoreQuery['function_score']['boost_mode'] = $this->boostMode;
        }

        if ($this->scoreMode) {
            $functionScoreQuery['function_score']['score_mode'] = $this->scoreMode;
        }

        $params['body']['query'] = $functionScoreQuery;

        // Reutiliza outras lógicas de construção existentes
        if (!is_null($this->sort)) {
            $params['body']['sort'] = $this->buildSort();
        }

        if (!is_null($this->groupBy)) {
            $params['body']['aggs'] = $this->groupBy;
        }

        if (!is_null($this->queriedFields)) {
            $highlight = [
                'pre_tags'  => '<em>',
                'post_tags' => '</em>',
                'fields'    => [],
            ];

            foreach ($this->queriedFields as $key => $field) {
                $highlight['fields'][$key] = new \stdClass();
            }
            $params['body']['highlight'] = $highlight;
        }

        if ($this->debug) {
            $this->logger->debug('JSON (FunctionScore): ' . json_encode($params));
        }

        return $params;
    }

     /**
     * Limpa as novas propriedades. Deve ser chamado no cleanRequest.
     * @return $this
     */
    public function cleanFunctions(): self
    {
        $this->functions = [];
        $this->boostMode = null;
        $this->scoreMode = null;
        return $this;
    }
}
