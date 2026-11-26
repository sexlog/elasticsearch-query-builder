<?php

namespace sexlog\ElasticSearch\Log;

use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\ServerResponseException;
use Psr\Http\Message\ResponseInterface;

final class QueryErrorMessage
{
    const DEFAULT_PREFIX = 'There was an error when querying ElasticSearch.';

    const ERROR_TYPE_PATTERN = '/^[a-z0-9_]+$/';

    /**
     * @param string|null $index
     * @param \Throwable  $exception
     * @param string      $prefix
     *
     * @return string
     */
    public static function create($index, \Throwable $exception, $prefix = self::DEFAULT_PREFIX)
    {
        return sprintf('%s Index: %s | Error: %s', $prefix, $index, self::describe($exception));
    }

    /**
     * @param \Throwable $exception
     *
     * @return string
     */
    public static function describe(\Throwable $exception)
    {
        if ($exception instanceof ClientResponseException || $exception instanceof ServerResponseException) {
            return self::describeResponseException($exception);
        }

        return self::shortClassName($exception) . ': ' . $exception->getMessage();
    }

    /**
     * @param ClientResponseException|ServerResponseException $exception
     *
     * @return string
     */
    private static function describeResponseException($exception)
    {
        try {
            $response = $exception->getResponse();
        } catch (\Error $e) {
            // The response is only unset when the exception is not thrown by the ElasticSearch client
            return self::shortClassName($exception) . ': HTTP ' . $exception->getCode();
        }

        $description = trim($response->getStatusCode() . ' ' . $response->getReasonPhrase());
        $errorTypes  = self::errorTypes($response);

        if (empty($errorTypes)) {
            return $description;
        }

        return $description . ' [' . implode(' > ', $errorTypes) . ']';
    }

    /**
     * @param ResponseInterface $response
     *
     * @return string[]
     */
    private static function errorTypes(ResponseInterface $response)
    {
        $body = json_decode((string) $response->getBody(), true);

        if (!is_array($body) || !isset($body['error']) || !is_array($body['error'])) {
            return [];
        }

        $error = $body['error'];
        $types = [$error['type'] ?? null];

        if (isset($error['root_cause']) && is_array($error['root_cause'])) {
            foreach ($error['root_cause'] as $rootCause) {
                $types[] = is_array($rootCause) ? ($rootCause['type'] ?? null) : null;
            }
        }

        $types = array_filter($types, static function ($type) {
            return is_string($type) && preg_match(self::ERROR_TYPE_PATTERN, $type) === 1;
        });

        return array_values(array_unique($types));
    }

    /**
     * @param \Throwable $exception
     *
     * @return string
     */
    private static function shortClassName(\Throwable $exception)
    {
        $className = get_class($exception);
        $position  = strrpos($className, '\\');

        return $position === false ? $className : substr($className, $position + 1);
    }
}
