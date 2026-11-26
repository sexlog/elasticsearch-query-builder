<?php

use Elastic\Elasticsearch\ClientBuilder;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use sexlog\ElasticSearch\ElasticSearch;
use sexlog\ElasticSearch\Filter;

/**
 * The error logs reach the APM and Slack: they must have the index and the error,
 * but never the query sent (which has the user's latitude/longitude).
 */
class ErrorLogTest extends TestCase
{
    const INDEX = 'sexloglocationdois';

    const LATITUDE = -22.32;

    const LONGITUDE = -49.07;

    /**
     * @var TestHandler
     */
    private $logHandler;

    protected function setUp(): void
    {
        $this->logHandler = new TestHandler();
    }

    public function testGetLogsIndexAndErrorWithoutTheQuery()
    {
        $elasticSearch = $this->elasticSearch($this->queryShardErrorResponse());
        $elasticSearch->setFilter($this->geoFilter());

        $this->assertNull($elasticSearch->get());

        $this->assertErrorLogged(
            'There was an error when querying ElasticSearch. Index: sexloglocationdois | Error: '
            . '400 Bad Request [search_phase_execution_exception > query_shard_exception]'
        );
    }

    public function testSearchWithFunctionsLogsIndexAndErrorWithoutTheQuery()
    {
        $elasticSearch = $this->elasticSearch($this->queryShardErrorResponse());
        $elasticSearch->setFilter($this->geoFilter());
        $elasticSearch->addGaussFunction('lat_lon', self::LATITUDE . ',' . self::LONGITUDE, '10km');

        $this->assertNull($elasticSearch->searchWithFunctions());

        $this->assertErrorLogged(
            'There was an error when querying ElasticSearch with functions. Index: sexloglocationdois | Error: '
            . '400 Bad Request [search_phase_execution_exception > query_shard_exception]'
        );
    }

    public function testGetLogsTransportErrors()
    {
        $elasticSearch = $this->elasticSearch(
            new ConnectException('Connection refused', new Request('POST', '/' . self::INDEX . '/_search'))
        );
        $elasticSearch->setFilter($this->geoFilter());

        $this->assertNull($elasticSearch->get());

        $records = $this->errorRecords();
        $this->assertCount(1, $records);
        $this->assertStringStartsWith(
            'There was an error when querying ElasticSearch. Index: sexloglocationdois | Error: ',
            $records[0]['message']
        );
        $this->assertQueryNotIn($records[0]['message']);
    }

    public function testDeleteLogsIndexAndErrorWithoutTheQueryEvenWithoutTranslator()
    {
        $elasticSearch = $this->elasticSearch($this->queryShardErrorResponse());
        $elasticSearch->setFilter($this->geoFilter());

        $this->assertNull($elasticSearch->delete());

        $message = 'There was an error when querying ElasticSearch. Index: sexloglocationdois | Error: '
            . '400 Bad Request [search_phase_execution_exception > query_shard_exception]';

        $this->assertTrue($this->logHandler->hasDebugThatContains($message));
        $this->assertEmpty($this->errorRecords());
    }

    /**
     * @param Response|\Throwable $response
     *
     * @return ElasticSearch
     */
    private function elasticSearch($response)
    {
        $httpClient = new GuzzleClient(['handler' => HandlerStack::create(new MockHandler([$response]))]);

        $client = ClientBuilder::create()
            ->setHosts(['http://localhost:9200'])
            ->setHttpClient($httpClient)
            ->setRetries(0)
            ->build();

        $logger = new Logger('test');
        $logger->pushHandler($this->logHandler);

        $elasticSearch = new ElasticSearch(self::INDEX, $client);
        $elasticSearch->setLogger($logger);

        return $elasticSearch;
    }

    /**
     * Error in which ElasticSearch echoes the query (and the coordinates) in the "reason".
     *
     * @return Response
     */
    private function queryShardErrorResponse()
    {
        $reason = sprintf('failed to create query: {"geo_distance":{"lat_lon":"%s,%s"}}', self::LATITUDE, self::LONGITUDE);

        return new Response(400, ['Content-Type' => 'application/json'], json_encode([
            'error'  => [
                'root_cause' => [['type' => 'query_shard_exception', 'reason' => $reason, 'index' => self::INDEX]],
                'type'       => 'search_phase_execution_exception',
                'reason'     => 'all shards failed',
            ],
            'status' => 400,
        ]));
    }

    /**
     * @return Filter
     */
    private function geoFilter()
    {
        $filter = new Filter();
        $filter->location(self::LATITUDE, self::LONGITUDE, 10);

        return $filter;
    }

    /**
     * @param string $expectedMessage
     */
    private function assertErrorLogged($expectedMessage)
    {
        $records = $this->errorRecords();

        $this->assertCount(1, $records);
        $this->assertSame($expectedMessage, $records[0]['message']);
        $this->assertQueryNotIn($records[0]['message']);
    }

    /**
     * @param string $message
     */
    private function assertQueryNotIn($message)
    {
        $this->assertStringNotContainsString((string) self::LATITUDE, $message);
        $this->assertStringNotContainsString((string) self::LONGITUDE, $message);
        $this->assertStringNotContainsString('lat_lon', $message);
        $this->assertStringNotContainsString('Query sent', $message);
    }

    /**
     * @return array
     */
    private function errorRecords()
    {
        return array_values(array_filter($this->logHandler->getRecords(), static function ($record) {
            return $record['level'] >= Logger::ERROR;
        }));
    }
}
