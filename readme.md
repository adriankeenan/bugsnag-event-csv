# bugsnag-event-csv

[![Build Status](https://travis-ci.com/adriankeenan/bugsnag-event-csv.svg?token=QCQEsipXx9vsUfBSe9yq&branch=master)](https://travis-ci.com/adriankeenan/bugsnag-event-csv)

CLi tool for exporting Bugsnag error events to a CSV. The columns can be customised to included structured metadata.
This can be useful for creating custom reports or cleaning up after an error occurs (re-attempting operations, updating
affected database entries etc).

Example:
```
bugsnag-event-csv org project error-id \
    --api_key api-key \
    --event_count 5 \
    --column exceptions.0.message:exception_message \
    --column user.id:user_id
```

```csv
id,received_at,exception_message,user_id
60847fbf0077caffca990000,2021-04-24T20:29:51.474Z,"Test exception A",8434
60847fbe0077cb15e55b0000,2021-04-24T20:29:50.697Z,"Test exception A",4869
60847fbd0077c259a0c80000,2021-04-24T20:29:49.026Z,"Test exception A",3083
60847fbd0077c4fd05680000,2021-04-24T20:29:49.884Z,"Test exception A",7407
60847fac0077f8f0cc660000,2021-04-24T20:29:32.946Z,"Test exception A",6675
```

## Installation

You can copy the pre-built phar to a folder in your path for easy access.

```
wget https://raw.githubusercontent.com/adriankeenan/bugsnag-event-csv/master/dist/bugsnag-event-csv \
    && chmod +x bugsnag-event-csv \
    && sudo mv bugsnag-event-csv /usr/local/bin/bugsnag-event-csv
```

## Development

Run the script using `php bin/main.php`

Run tests using `composer test`

Build a distributable .phar with `composer build-phar`

## Usage

Run `bugsnag-event-csv ORG_ID_OR_SLUG PROJECT_ID_OR_SLUG ERROR_IDS --key API_KEY`

### Keys

The API key does not need to be specified in the `--key` option if it is set in an ENV var `BUGNSAG_API_KEY`.

### Multiple errors

You can fetch events from multiple errors, eg if the same error is recorded as multiple errors within bugsnag, by
supplying multiple error IDs separated by a comma.

### Event count

By default, the last 100 events are returned, however you can use `--event_count X` to increase the number of events
returned. In theory this can be infinite as the API client supports pagination, however this will take a long time if
there are a lot of events due to API rate limits.

### Adding columns

A standard set of columns will be exported by default. You can add additional columns from any field in the Bugsnag 
errors API response. To add additional columns, add column arguments `--column PATH_TO_VALUE:NAME` 
where `PATH_TO_VALUE` is the dot notation for the value in the
[event response](https://bugsnagapiv2.docs.apiary.io/#reference/errors/errors/list-the-errors-on-a-project) and `NAME`
is the name to use in the CSV header row. For example, to add a "device id" column for a custom `device_id` meta field,
use `--column "metaData.device_id:device id"`,

### Inspecting responses

You can inspect the raw response from Bugsnag in order to determine the data paths by using the `--raw`
option.

### Formatting

Structured data (such as arrays and objects) will be serialised as JSON strings. Options are available for specifying
the values of `true`, `false`, `null` and non-existent values. Use the `--help` option to see these options.

# License

MIT