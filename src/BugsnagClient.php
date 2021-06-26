<?php

namespace Keenan;

use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;
use TiagoHillebrandt\ParseLinkHeader;

class BugsnagClient
{
    protected Client $client;
    protected string $baseUrl = 'https://api.bugsnag.com';
    protected string $apiKey;

    public function __construct(string $apiKey, ?Client $client = null)
    {
        $this->apiKey = $apiKey;
        $this->client = $client ?? new Client;
    }

    public function getOrganisations(): array
    {
        return $this->makePaginatedRequest(
            'GET',
            'user/organizations',
            [
                'per_page' => 30,
            ],
            null,
        );
    }

    public function getProjects(string $organisationId): array
    {
        return $this->makePaginatedRequest(
            'GET',
            sprintf('organizations/%s/projects', $organisationId),
            [
                'sort' => 'created_at',
                'direction' => 'desc',
                'per_page' => 30,
            ],
            null,
        );
    }

    public function getEvents(string $projectId, string $errorId, ?int $maxEvents = null): array
    {
        return $this->makePaginatedRequest(
            'GET',
            sprintf('projects/%s/errors/%s/events', $projectId, $errorId),
            [
                'sort' => 'timestamp',
                'direction' => 'desc',
                'per_page' => 30,
                'full_reports' => 'true',
            ],
            $maxEvents,
        );
    }

    /**
     * Makes requests to the Bugsnag API for a given entity, following pagination links, until the $maxRecords number
     * of items has been returned.
     */
    private function makePaginatedRequest(string $method, string $path, array $query = [], ?int $maxRecords = null): array
    {
        $results = collect();

        // Build complete URL
        $url = sprintf('%s/%s', $this->baseUrl, $path);

        // Make request until we reach the max number of records or run out of records to fetch
        while ($maxRecords === null || $results->count() < $maxRecords) {

            // Get response
            $response = $this->makeRawRequest($method, $url, $query);

            // Add results
            $data = json_decode((string)$response->getBody(), true);
            $results = $results->concat($data);

            // Get next pagination link (if more results to fetch)
            $linkHeader = $response->getHeaderLine('Link') ?? null;
            $url = null;
            if ($linkHeader != null) {
                $linkHeaderData = (new ParseLinkHeader($linkHeader))->toArray();
                $url = $linkHeaderData['next']['link'] ?? null;
                // Clear the $query data as this would overwrite the query string in the new url
                $query = null;
            }

            // Stop if no more results
            if (empty($url)) {
                break;
            }
        }

        // Trim to max results
        if ($maxRecords > 0) {
            $results = $results->take($maxRecords);
        }

        return $results->values()->toArray();
    }

    /**
     * Return a PSR7-compatible response for a Bugsnag API request.
     * Requests are retried after the requested duration if the API states that we are rate limited.
     */
    private function makeRawRequest(string $method, string $url, ?array $query = []): ResponseInterface
    {
        $options = [
            \GuzzleHttp\RequestOptions::HEADERS => [
                'Authorization' => 'token ' . $this->apiKey,
                'X-Version' => '2',
            ],
        ];
        if (empty($query) == false) {
            $options[\GuzzleHttp\RequestOptions::QUERY] = $query;
        }

        try {
            return $this->client->request(
                $method,
                $url,
                $options,
            );
        } catch (\GuzzleHttp\Exception\BadResponseException $e) {
            // If we get a bad response due to rate limit, wait the request amount of time, then retry
            if ($e->getResponse()->getStatusCode() == 429) {
                $waitSeconds = floatval($e->getResponse()->getHeaderLine('Retry-After'));
                usleep($waitSeconds * 1000 * 1000);
                return $this->makeRawRequest($method, $url, $query);
            }

            // Parse error message from response body
            $data = json_decode($e->getResponse()->getBody(), true);
            $errorMessage = implode(', ', $data['errors'] ?? []);
            throw new BugsnagClientException($errorMessage, 500, $e);
        } catch (\Exception $e) {
            throw new BugsnagClientException('Bugsnag request failed.', 500, $e);
        }
    }
}