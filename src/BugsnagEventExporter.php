<?php

namespace Keenan;

use Illuminate\Support\Arr;

class BugsnagEventExporter
{
    protected BugsnagClient $client;
    protected string $organisationId;
    protected string $projectId;
    protected string $errorId;

    public function __construct(BugsnagClient $client)
    {
        $this->client = $client;
    }

    /**
     * Set the organisation from an ID or slug. Can be called without any filter if only one organisation is present.
     */
    public function setOrganisation(?string $organisationFilter = null): BugsnagEventExporter
    {
        $organisations = $this->client->getOrganisations();

        $org = null;
        if (empty($organisationFilter) == false) {
            $org = $this->getFromIdOrSlug($organisations, $organisationFilter);
        } else {
            $org = collect($organisations)->first();
        }

        if ($org == null) {
            throw new \Exception('Organisation not found.');
        }

        $this->organisationId = $org['id'];

        return $this;
    }

    /**
     * Set the project from an ID or slug.
     */
    public function setProject(string $projectFilter): BugsnagEventExporter
    {
        $projects = $this->client->getProjects($this->organisationId);
        $project = $this->getFromIdOrSlug($projects, $projectFilter);

        if ($project == null) {
            throw new \Exception('Project not found.', 500);
        }

        $this->projectId = $project['id'];

        return $this;
    }

    /**
     * Set the error ID.
     */
    public function setErrorId(string $errorId): BugsnagEventExporter
    {
        $this->errorId = $errorId;

        return $this;
    }

    /**
     * Get an array of events for this error in the Bugsnag API response format.
     */
    public function getEvents(int $maxEvents): array
    {
        if (empty($this->projectId) || empty($this->errorId)) {
            throw new \Exception('Project and Error ID must be set before events can be fetched.', 500);
        }
        
        return $this->client->getEvents(
            $this->projectId,
            $this->errorId,
            $maxEvents,
        );
    }

    /**
     * Format Bugsnag event errors as a CSV.
     */
    public function exportCsv(int $maxEvents,
                              array $columns,
                              string $valueNotSet,
                              string $valueTrue,
                              string $valueFalse,
                              string $valueNull): string
    {
        // Get parsed column names (including the path and name)
        $columnList = collect([
                'id',
                'received_at',
            ])
            ->concat($columns)
            ->toArray();
        $columnList = $this->parseColumnNames($columnList);

        // Get events to format
        $events = $this->getEvents($maxEvents);

        // Format results as csv
        $handle = fopen('php://memory', 'rw+');

        // Write header
        $header = collect($columnList)->pluck('name')->toArray();
        fputcsv($handle, $header);

        // Write rows
        foreach ($events as $event) {
            $rowData = collect($columnList)
                // Fetch values using dot syntax
                ->map(fn(array $column) => Arr::get($event, $column['path'], $valueNotSet))
                // Format values
                ->map(function($result) use ($valueTrue, $valueFalse, $valueNull) {
                    if ($result === true) {
                        return $valueTrue;
                    }

                    if ($result === false) {
                        return $valueFalse;
                    }

                    if ($result === null) {
                        return $valueNull;
                    }

                    if (is_scalar($result) == false) {
                        return json_encode($result);
                    }

                    return $result;
                })
                ->toArray();

            fputcsv($handle, $rowData);
        }

        // Output CSV
        fseek($handle, 0);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /**
     * Parse column strings in to 'path' and 'name' values. Eg:
     * 'metaData.user.id' => ['path' => 'metaData.user.id'', 'name' => 'metaData.user.id'],
     * 'metaData.user.id:user_id' => ['path' => 'metaData.user.id'', 'name' => 'user_id'],
     */
    private function parseColumnNames(array $columns): array
    {
        return collect($columns)
            ->map(function(string $columnStr) {
                $parts = explode(':', $columnStr, 2);
                return [
                    'path' => $parts[0],
                    'name' => $parts[1] ?? $parts[0],
                ];
            })
            ->toArray();
    }

    /**
     * Returns the first result from a collect where $search is exactly equal to the 'id' or 'slug' values in each
     * array value.
     */
    private function getFromIdOrSlug($array, string $search): ?array
    {
        return collect($array)
            ->filter(function(array $item) use ($search) {
                return $item['id'] == $search || $item['slug'] == $search;
            })
            ->first();
    }
}