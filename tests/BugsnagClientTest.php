<?php

use Keenan\BugsnagClient;
use Keenan\BugsnagClientException;
use PHPUnit\Framework\TestCase;

class BugsnagClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testErrors()
    {
        $testErrorMessage = 'Bugsnag API error!';

        // Stub HTTP client - only return error responses.
        // @see error format: https://bugsnagapiv2.docs.apiary.io/#introduction/response-codes/error-messages
        $bugsnagHttpClient = $this->createStub(\GuzzleHttp\Client::class);
        $bugsnagHttpClient->method('request')
             ->willThrowException(
                 new \GuzzleHttp\Exception\BadResponseException(
                     '',
                     new \GuzzleHttp\Psr7\Request('GET', '/test'),
                     new \GuzzleHttp\Psr7\Response(
                         401,
                         [],
                         json_encode(['errors' => [$testErrorMessage]]),
                     ),
                 ),
             );

        // Assert that any API call returns the hardcoded error response, rethrown as BugsnagClientException
        $this->expectException(BugsnagClientException::class);
        $this->expectExceptionMessage($testErrorMessage);

        $bugsnagClient = new BugsnagClient('', $bugsnagHttpClient);
        $bugsnagClient->getOrganisations();
    }

    public function testPaginationSingleRequest()
    {
        // Stub HTTP client - return a series of responses for a paginated request
        // @see pagination info: https://bugsnagapiv2.docs.apiary.io/#introduction/pagination
        $bugsnagHttpClient = $this->createStub(\GuzzleHttp\Client::class);
        $bugsnagHttpClient->method('request')
            ->will(
                $this->onConsecutiveCalls(
                    new \GuzzleHttp\Psr7\Response(
                        200,
                        [],
                        json_encode([1, 2]),
                    ),
                )
            );

        // Make a paginated request and ensure that link headers are followed and results combined
        $bugsnagClient = new BugsnagClient('', $bugsnagHttpClient);
        $data = $bugsnagClient->getEvents('', '', null);

        $this->assertSame([1, 2], $data);
    }

    public function testPaginationAllRequests()
    {
        // Stub HTTP client - return a series of responses for a paginated request
        // @see pagination info: https://bugsnagapiv2.docs.apiary.io/#introduction/pagination
        $bugsnagHttpClient = $this->createStub(\GuzzleHttp\Client::class);
        $bugsnagHttpClient->method('request')
            ->will(
                $this->onConsecutiveCalls(
                    new \GuzzleHttp\Psr7\Response(
                        200,
                        ['Link' => '<https://api.bugsnag.com/...>; rel="next"'],
                        json_encode([1, 2]),
                    ),
                    new \GuzzleHttp\Psr7\Response(
                        200,
                        [],
                        json_encode([3, 4]),
                    )
                )
            );

        // Make a paginated request and ensure that link headers are followed and results combined
        $bugsnagClient = new BugsnagClient('', $bugsnagHttpClient);
        $data = $bugsnagClient->getEvents('', '', null);

        $this->assertSame([1, 2, 3, 4], $data);
    }

    public function testPaginationMaxResults()
    {
        // Stub HTTP client - return a series of responses for a paginated request
        // @see pagination info: https://bugsnagapiv2.docs.apiary.io/#introduction/pagination
        $bugsnagHttpClient = $this->createStub(\GuzzleHttp\Client::class);
        $bugsnagHttpClient->method('request')
            ->will(
                $this->onConsecutiveCalls(
                    new \GuzzleHttp\Psr7\Response(
                        200,
                        ['Link' => '<https://api.bugsnag.com/...>; rel="next"'],
                        json_encode([1, 2]),
                    ),
                    new \GuzzleHttp\Psr7\Response(
                        200,
                        [],
                        json_encode([3, 4]),
                    )
                )
            );

        // Make a paginated request and ensure that link headers are followed and results combined
        $bugsnagClient = new BugsnagClient('', $bugsnagHttpClient);
        $data = $bugsnagClient->getEvents('', '', 3);

        $this->assertSame([1, 2, 3], $data);
    }
}