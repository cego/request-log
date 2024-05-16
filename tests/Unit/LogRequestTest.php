<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Tests\Utility\SetCookieMiddleware;
use Cego\RequestLog\Middleware\LogRequest;
use Symfony\Component\HttpFoundation\Response;

class LogRequestTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $kernel = app('Illuminate\Contracts\Http\Kernel');
        $kernel->pushMiddleware(LogRequest::class);
        Config::set('request-log.enabled', true);
    }

    public function test_request_body_is_always_empty_when_not_json()
    {
        // Arrange
        $data = [
            'password' => '12345678',
            'person'   => [
                'sensitive_data' => 'secret',
            ],
        ];

        // Act
        $methods = ['get', 'post', 'put', 'patch', 'delete'];

        foreach ($methods as $method) {
            $loggerMock = Log::partialMock();
            Log::setApplication($this->app);

            // Assert debug was called on loggerMock once with {} request body
            $loggerMock->shouldReceive('debug')->once()->andReturnUsing(function ($message, $context) {
                $this->assertEquals('{}', $context['http']['request']['body']['content']);
            });

            $this->$method('/test', $data, []);
        }
    }

    public function test_it_masks_request_headers()
    {
        // Arrange
        $loggerMock = Log::partialMock();
        Log::setApplication($this->app);

        // Assert debug was called on loggerMock once with {} request body
        $loggerMock->shouldReceive('debug')->once()->andReturnUsing(function ($message, $context) {
            $loggedHeaders = $context['http']['request']['headers.raw'];
            $loggedHeaders = json_decode($loggedHeaders, true);

            $this->assertEquals('[ MASKED ]', $loggedHeaders['x-encrypt-this-header'][0]);
            $this->assertEquals('This is a non-secret header', $loggedHeaders['x-dont-encrypt-this-header'][0]);
        });

        $headers = [
            'X-SENSITIVE-REQUEST-HEADERS-JSON' => json_encode(['X-ENCRYPT-THIS-HEADER']),
            'X-ENCRYPT-THIS-HEADER'            => 'This is a secret header',
            'X-DONT-ENCRYPT-THIS-HEADER'       => 'This is a non-secret header',
        ];

        // Act
        $this->post('/test', [], $headers);
    }

    public function test_it_masks_duplicate_request_headers()
    {
        // Arrange
        $loggerMock = Log::partialMock();
        Log::setApplication($this->app);

        // Assert debug was called on loggerMock once with {} request body
        $loggerMock->shouldReceive('debug')->once()->andReturnUsing(function ($message, $context) {
            $loggedHeaders = $context['http']['request']['headers.raw'];
            $loggedHeaders = json_decode($loggedHeaders, true);
            $this->assertEquals('[ MASKED ]', $loggedHeaders['x-encrypt-this-header'][0]);
            $this->assertEquals('[ MASKED ]', $loggedHeaders['x-encrypt-this-header'][1]);
            $this->assertEquals('This is a non-secret header', $loggedHeaders['x-dont-encrypt-this-header'][0]);
        });

        $headers = [
            'X-SENSITIVE-REQUEST-HEADERS-JSON' => json_encode(['X-ENCRYPT-THIS-HEADER']),
            'X-ENCRYPT-THIS-HEADER'            => ['This is a secret header', 'And we define it twice'],
            'X-DONT-ENCRYPT-THIS-HEADER'       => 'This is a non-secret header',
        ];

        // Act
        $this->post('/test', [], $headers);
    }

    public function test_it_masks_request_body()
    {
        // Arrange
        $loggerMock = Log::partialMock();
        Log::setApplication($this->app);

        // Assert debug was called on loggerMock once with {} request body
        $loggerMock->shouldReceive('debug')->once()->andReturnUsing(function ($message, $context) {
            $loggedBody = json_decode($context['http']['request']['body']['content'], true);
            $this->assertEquals([
                'password'  => '[ MASKED ]',
                'something' => [
                    'very' => [
                        'nested' => '[ MASKED ]',
                    ],
                ],
                'person' => [
                    'sensitive_data'   => '[ MASKED ]',
                    'insensitive_data' => 'not secret',
                ],
                'secret_array' => '[ MASKED ]',
            ], $loggedBody);
        });

        $data = [
            'password'  => '12345678',
            'something' => [
                'very' => [
                    'nested' => 'should not see',
                ],
            ],
            'person' => [
                'sensitive_data'   => 'secret',
                'insensitive_data' => 'not secret',
            ],
            'secret_array' => [
                'of' => 'stuff',
            ],
        ];

        $headers = [
            'X-SENSITIVE-REQUEST-BODY-JSON' => json_encode([
                'password',
                'person.sensitive_data',
                'something.very.nested',
                'this_key.does_not.exist',
                'secret_array',
            ]),
        ];

        // Act
        $this->postJson('/test', $data, $headers);
    }

    public function test_it_tests()
    {
        // Arrange
        $loggerMock = Log::partialMock();
        Log::setApplication($this->app);

        // Assert debug was called on loggerMock once with {} request body
        $loggerMock->shouldReceive('debug')->once()->andReturnUsing(function ($message, $context) {
            $loggedHeaders = $context['http']['request']['headers.raw'];
            $loggedHeaders = json_decode($loggedHeaders, true);
            $this->assertEquals('{"token":"[ MASKED ]","cake":"not-secret"}', $context['http']['request']['query_string']);
            $this->assertEquals('[ MASKED ]', $loggedHeaders['authorization'][0]);
            $this->assertEquals('Not Secret', $loggedHeaders['something-else'][0]);
        });

        // Act
        $this->post('/test?token=very-secret&cake=not-secret', [], ['Authorization' => 'very secret', 'something-else' => 'Not Secret']);
    }

    public function test_it_masks_request_cookies()
    {
        // Arrange
        $loggerMock = Log::partialMock();
        Log::setApplication($this->app);

        $loggerMock->shouldReceive('debug')->once()->andReturnUsing(function ($message, $context) {
            $loggedCookies = $context['http']['request']['cookies.raw'];
            $loggedCookies = json_decode($loggedCookies, true);
            $this->assertEquals('[ MASKED ]', $loggedCookies['SECRET_COOKIE']);
            $this->assertEquals('efgh', $loggedCookies['NON_SECRET_COOKIE']);
        });

        $headers = [
            'X-SENSITIVE-REQUEST-COOKIES-JSON' => json_encode(['SECRET_COOKIE']),
        ];

        // Act
        $this->withUnencryptedCookies(['SECRET_COOKIE' => 'abcd', 'NON_SECRET_COOKIE' => 'efgh'])->post('/test', [], $headers);
    }

    public function test_it_masks_response_cookies(): void
    {
        // Arrange
        $loggerMock = Log::partialMock();
        Log::setApplication($this->app);

        // Assert debug was called on loggerMock once with {} request body
        $loggerMock->shouldReceive('debug')->once()->andReturnUsing(function ($message, $context) {
            $loggedCookies = $context['http']['response']['cookies.raw'];
            $loggedCookies = json_decode($loggedCookies, true);
            $this->assertEquals('[ MASKED ]', $loggedCookies['SECRET_COOKIE']['value']);
            $this->assertEquals('efgh', $loggedCookies['NON_SECRET_COOKIE']['value']);
        });

        $headers = [
            'X-SENSITIVE-REQUEST-COOKIES-JSON' => json_encode(['SECRET_COOKIE']),
        ];

        $kernel = app('Illuminate\Contracts\Http\Kernel');
        $kernel->pushMiddleware(SetCookieMiddleware::class);

        // Act
        $this->post('/test', [], $headers);
    }

    public function test_it_doesnt_crash_if_exception_on_response_doesnt_exist(): void
    {
        $loggerMock = Log::partialMock();
        Log::setApplication($this->app);

        // Assert debug was called on loggerMock once with {} request body
        $loggerMock->shouldNotReceive('error');

        $middleware = new LogRequest();

        $request = new Request();

        $response = new Response();

        $middleware->terminate($request, $response);
    }

    public function test_it_truncates_very_long_json_bodies(): void
    {
        // Set config request-log.truncateBodyLength to 100
        Config::set('request-log.truncateBodyLength', 100);

        $loggerMock = Log::partialMock();
        Log::setApplication($this->app);

        $loggerMock->shouldReceive('debug')->once()->andReturnUsing(function ($message, $context) {
            $this->assertEquals(100, strlen($context['http']['request']['body']['content']));
            $this->assertEquals(100, strlen($context['http']['response']['body']['content']));
        });

        $middleware = new LogRequest();

        $request = new Request();
        // Set request body to a very long json string
        $request->initialize([], [], [], [], [], [], json_encode(range(0, 10000)));

        $response = new Response();
        // Set response body to a very long json string
        $response->setContent(json_encode(range(0, 10000)));

        $middleware->terminate($request, $response);
    }

    public function test_it_doesnt_truncate_very_long_json_bodies_if_disabled(): void
    {
        // Set config request-log.truncateBodyLength to 100
        Config::set('request-log.truncateBodyLength', -1);

        $loggerMock = Log::partialMock();
        Log::setApplication($this->app);

        // Assert debug was called on loggerMock once with {} request body
        $loggerMock->shouldReceive('debug')->once()->andReturnUsing(function ($message, $context) {
            $this->assertEquals(48897, strlen($context['http']['request']['body']['content']));
            $this->assertEquals(48897, strlen($context['http']['response']['body']['content']));
        });

        $middleware = new LogRequest();

        $request = new Request();
        // Set request body to a very long json string
        $request->initialize([], [], [], [], [], [], json_encode(range(0, 10000)));

        $response = new Response();
        // Set response body to a very long json string
        $response->setContent(json_encode(range(0, 10000)));

        $middleware->terminate($request, $response);
    }

    public function test_it_doesnt_truncate_bodies_shorter_than_truncate_limit(): void
    {
        // Set config request-log.truncateBodyLength to 100
        Config::set('request-log.truncateBodyLength', 100);

        $loggerMock = Log::partialMock();
        Log::setApplication($this->app);

        $loggerMock->shouldReceive('debug')->once()->andReturnUsing(function ($message, $context) {
            $this->assertEquals(3, strlen($context['http']['request']['body']['content']));
            $this->assertEquals(3, strlen($context['http']['response']['body']['content']));
        });

        $middleware = new LogRequest();

        $request = new Request();
        // Set request body to a very long json string
        $request->initialize([], [], [], [], [], [], 'hej');

        $response = new Response();
        // Set response body to a very long json string
        $response->setContent('hej');

        $middleware->terminate($request, $response);
    }
}
