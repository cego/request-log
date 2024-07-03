<?php

namespace Cego\RequestLog\Data;

use Throwable;
use Illuminate\Support\Facades\Log;

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
        public ?int             $executionTimeNs,
    ) {
    }

    public function log()
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
                    'cookies.raw' => json_encode($this->requestCookies, JSON_PRETTY_PRINT),
                    'headers.raw' => json_encode($this->requestHeaders, JSON_PRETTY_PRINT),
                    'method'      => $this->method,
                ],
                'response' => [
                    'body' => [
                        'content' => $this->responseBody,
                    ],
                    'cookies.raw' => json_encode($this->responseCookies, JSON_PRETTY_PRINT),
                    'headers.raw' => json_encode($this->responseHeaders, JSON_PRETTY_PRINT),
                    'status_code' => $this->status,
                ],
                'route' => $this->routeUri,
            ],
            'log' => [
                'type' => 'request-logs',
            ],
        ];

        if ($this->executionTimeNs !== null) {
            $context['event'] = [
                'duration' => $this->executionTimeNs, // In nanoseconds, see https://www.elastic.co/guide/en/ecs/current/ecs-event.html
            ];
        }

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

        Log::debug(
            sprintf('Timing for %s', $this->url),
            $context
        );
    }
}
