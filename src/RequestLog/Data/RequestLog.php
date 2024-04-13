<?php

namespace Cego\RequestLog\Data;

use Throwable;
use Psr\Log\LoggerInterface;

class RequestLog
{
    public function __construct(
        public readonly string  $method,
        public readonly string  $url,
        public readonly ?string $routeUri,
        public readonly string  $root,
        public readonly string  $path,
        public readonly string  $queryString,
        public readonly array   $requestHeaders,
        public readonly array   $requestCookies,
        public readonly string  $requestBody,
        public readonly int     $status,
        public readonly array   $responseHeaders,
        public readonly array   $responseCookies,
        public readonly string  $responseBody,
        public ?Throwable       $responseException,
        public int              $executionTimeNs,
    ) {
    }

    public static function associativeArrayToMultiline(?array $arr): string
    {
        $res = '';

        if ( ! is_array($arr)) {
            return $res;
        }

        foreach ($arr as $name => $value) {
            $res .= $name . '=' . $value . PHP_EOL;
        }

        return $res;
    }

    public function log(LoggerInterface $logger)
    {
        $context = [
            'http' => [
                'request' => [
                    'url'          => $this->url,
                    'root'         => $this->root,
                    'path'         => $this->path,
                    'query_string' => $this->queryString,
                    'body'         => [
                        'content' => $this->requestBody,
                    ],
                    'cookies.raw' => RequestLog::associativeArrayToMultiline($this->requestCookies),
                    'headers.raw' => RequestLog::associativeArrayToMultiline($this->requestHeaders),
                    'method'      => $this->method,
                ],
                'response' => [
                    'body' => [
                        'content' => $this->responseBody,
                    ],
                    'cookies.raw' => RequestLog::associativeArrayToMultiline($this->responseCookies),
                    'headers.raw' => RequestLog::associativeArrayToMultiline($this->responseHeaders),
                    'status_code' => $this->status,
                ],
            ],
            'event' => [
                'duration' => $this->executionTimeNs, // In nanoseconds, see https://www.elastic.co/guide/en/ecs/current/ecs-event.html
            ],
            'log' => [
                'type' => 'request-logs',
            ],
        ];

        if($this->responseException !== null) {
            $message = $this->responseException->getMessage();
            $message = empty($message) ? get_class($this->responseException) . ' thrown with empty message' : $message;

            $context['error'] = [
                'type'        => get_class($this->responseException),
                'stack_trace' => $this->responseException->getTraceAsString(),
                // error.code is type keyword, therefore always cast to string
                'code'    => (string) $this->responseException->getCode(),
                'message' => $message,
            ];
        }

        $logger->debug(
            sprintf('Timing for %s', $this->url),
            $context
        );
    }
}
