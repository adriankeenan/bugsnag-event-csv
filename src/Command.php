<?php

namespace Keenan;

use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Command extends SymfonyCommand
{
    const ARG_ORGANISATION = 'organisation';
    const ARG_PROJECT = 'project';
    const ARG_EVENT_ID = 'event_id';
    const OPT_COLUMN = 'column';
    const OPT_EVENT_COUNT = 'event_count';
    const OPT_API_KEY = 'api_key';
    const OPT_RAW = 'raw';
    const OPT_ENCODING_NOTSET = 'encoding_notset';
    const OPT_ENCODING_TRUE = 'encoding_true';
    const OPT_ENCODING_FALSE = 'encoding_false';
    const OPT_ENCODING_NULL = 'encoding_null';

    protected function configure()
    {
        $this->setName('bugsnag-event-csv')
            ->setDescription('Exports Bugsnag error events as a CSV including specified metadata.')
            ->addArgument(
                static::ARG_ORGANISATION,
                InputArgument::REQUIRED,
                'Organisation ID or slug.',
            )
            ->addArgument(
                static::ARG_PROJECT,
                InputArgument::REQUIRED,
                'Project ID or slug.',
            )
            ->addArgument(
                static::ARG_EVENT_ID,
                InputArgument::REQUIRED,
                'Event ID.',
            )
            ->addOption(
                static::OPT_COLUMN,
                'c',
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL,
                'Additional columns to add to the CSV. Access nested data using dot syntax. '.
                'Rename columns in the output using "path:column_name" syntax.',
            )
            ->addOption(
                static::OPT_EVENT_COUNT,
                'e',
                InputOption::VALUE_REQUIRED,
                'Maximum number of events to return for this error.',
                100,
            )
            ->addOption(
                static::OPT_API_KEY,
                'k',
                InputOption::VALUE_REQUIRED,
                'Bugsnag API key. BUGSNAG_KEY env var will be used if not specified.'
            )
            ->addOption(
                static::OPT_RAW,
                'r',
                InputOption::VALUE_NONE,
                'Output raw events response as JSON. Useful for viewing the metadata structure.',
            )
            ->addOption(
                static::OPT_ENCODING_NOTSET,
                'x',
                InputOption::VALUE_REQUIRED,
                'Value to use when the column path is not set. Uses null by default.',
                null,
            )
            ->addOption(
                static::OPT_ENCODING_NULL,
                'z',
                InputOption::VALUE_REQUIRED,
                'Value to use for null values (by default  "").',
                '',
            )
            ->addOption(
                static::OPT_ENCODING_TRUE,
                't',
                InputOption::VALUE_REQUIRED,
                'Value to use for true values (by default  "true").',
                'true',
            )
            ->addOption(
                static::OPT_ENCODING_FALSE,
                'f',
                InputOption::VALUE_REQUIRED,
                'Value to use for false values (by default "false").',
                'true',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Get API key
        $apiKey = $input->getOption(static::OPT_API_KEY) ?? getenv('BUGSNAG_API_KEY');

        if (empty($apiKey)) {
            throw new \Exception(
                'No API key specified. Please specify the key using -k (--api_key) or populate the '.
                'BUGSNAG_API_KEY env var.',
            );
        }

        // Setup client
        $client = new BugsnagClient($apiKey);

        // Configure exporter
        $exporter = (new BugsnagEventExporter($client))
            ->setOrganisation($input->getArgument(static::ARG_ORGANISATION))
            ->setProject($input->getArgument(static::ARG_PROJECT))
            ->setErrorIds(
                $this->splitStringArray(
                    $input->getArgument(static::ARG_EVENT_ID),
                ),
            );

        // Raw export
        $maxEvents = $input->getOption(static::OPT_EVENT_COUNT);
        if ($input->getOption(static::OPT_RAW)) {
            $events = $exporter->getEvents($maxEvents);
            $output->write(json_encode($events, JSON_PRETTY_PRINT));
            return SymfonyCommand::SUCCESS;
        }

        // Output CSV
        $columns = $this->splitStringArray($input->getOption(static::OPT_COLUMN));
        $valueTrue = $input->getOption(static::OPT_ENCODING_TRUE);
        $valueFalse = $input->getOption(static::OPT_ENCODING_FALSE);
        $valueNull = $input->getOption(static::OPT_ENCODING_NULL);
        $valueNotSet = $input->getOption(static::OPT_ENCODING_NOTSET) ?? $valueNull;

        $csv = $exporter->exportCsv(
            $maxEvents,
            $columns,
            $valueNotSet,
            $valueTrue,
            $valueFalse,
            $valueNull,
        );

        $output->write($csv);
        return SymfonyCommand::SUCCESS;
    }

    /**
     * Accepts a string or list of strings. Splits each string by
     * @param string|array $input
     */
    protected function splitStringArray($input): array
    {
        if (is_array($input) == false) {
            $input = [$input];
        }

        return collect($input)
            ->map(fn(string $entry) => explode(',', $entry))
            ->flatten(1)
            ->filter()
            ->unique()
            ->values()
            ->toArray();
    }
}
