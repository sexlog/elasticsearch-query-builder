<?php

use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\ServerResponseException;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use sexlog\ElasticSearch\Log\QueryErrorMessage;

class QueryErrorMessageTest extends TestCase
{
    const INDEX = 'sexloglocationdois';

    public function testCreateLogsIndexAndErrorTypesWithoutTheReasonEchoingTheQuery()
    {
        $exception = $this->clientResponseException(400, [
            'error'  => [
                'root_cause' => [
                    ['type' => 'query_shard_exception', 'reason' => 'failed to create query: {"lat_lon":"-22.32,-49.07"}'],
                ],
                'type'       => 'search_phase_execution_exception',
                'reason'     => 'all shards failed',
            ],
            'status' => 400,
        ]);

        $message = QueryErrorMessage::create(self::INDEX, $exception);

        $this->assertSame(
            'There was an error when querying ElasticSearch. Index: sexloglocationdois | Error: '
            . '400 Bad Request [search_phase_execution_exception > query_shard_exception]',
            $message
        );
        $this->assertStringNotContainsString('-22.32', $message);
        $this->assertStringNotContainsString('lat_lon', $message);
    }

    public function testCreateUsesTheGivenPrefix()
    {
        $exception = $this->clientResponseException(404, [
            'error' => [
                'root_cause' => [['type' => 'index_not_found_exception', 'reason' => 'no such index [sexloglocationdois]']],
                'type'       => 'index_not_found_exception',
            ],
        ]);

        $this->assertSame(
            'Prefix. Index: sexloglocationdois | Error: 404 Not Found [index_not_found_exception]',
            QueryErrorMessage::create(self::INDEX, $exception, 'Prefix.')
        );
    }

    public function testDescribeServerResponseException()
    {
        $exception = (new ServerResponseException('500 Internal Server Error: {"lat_lon":"-22.32,-49.07"}', 500))
            ->setResponse(new Response(500, [], json_encode(['error' => ['type' => 'exception', 'reason' => '-22.32']])));

        $this->assertSame('500 Internal Server Error [exception]', QueryErrorMessage::describe($exception));
    }

    public function testDescribeDiscardsErrorTypesThatAreNotIdentifiers()
    {
        $exception = $this->clientResponseException(400, [
            'error' => [
                'type'       => 'illegal latitude value [-22.32]',
                'root_cause' => [['type' => 'parse_exception'], 'not an array', ['reason' => 'without type']],
            ],
        ]);

        $this->assertSame('400 Bad Request [parse_exception]', QueryErrorMessage::describe($exception));
    }

    /**
     * @dataProvider bodiesWithoutErrorTypesProvider
     */
    public function testDescribeLogsOnlyTheStatusWhenTheBodyHasNoErrorTypes($body)
    {
        $exception = (new ClientResponseException('400 Bad Request: ' . $body, 400))
            ->setResponse(new Response(400, [], $body));

        $this->assertSame('400 Bad Request', QueryErrorMessage::describe($exception));
    }

    public function bodiesWithoutErrorTypesProvider()
    {
        return [
            'not json'          => ['{"lat_lon":"-22.32,-49.07"'],
            'empty'             => [''],
            'error as a string' => ['{"error":"illegal latitude value [-22.32]"}'],
            'without error'     => ['{"status":400}'],
        ];
    }

    public function testDescribeResponseExceptionWithoutResponse()
    {
        $exception = new ClientResponseException('400 Bad Request: {"lat_lon":"-22.32,-49.07"}', 400);

        $this->assertSame('ClientResponseException: HTTP 400', QueryErrorMessage::describe($exception));
    }

    public function testDescribeExceptionsWithoutResponseUseTheirMessage()
    {
        $exception = new \RuntimeException('No alive nodes. All the 1 nodes seem to be down.');

        $this->assertSame(
            'RuntimeException: No alive nodes. All the 1 nodes seem to be down.',
            QueryErrorMessage::describe($exception)
        );
    }

    private function clientResponseException($status, array $body)
    {
        $response = new Response($status, ['Content-Type' => 'application/json'], json_encode($body));

        return (new ClientResponseException('raw message with the whole body', $status))->setResponse($response);
    }
}
