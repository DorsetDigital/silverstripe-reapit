# Silverstripe Reapit

A one-way, read-only property import from Reapit Foundations for Silverstripe CMS 5. The code is structured to simplify a future CMS 6 upgrade.

> **Development status:** initial backend foundation. Property images, production-grade incremental image syncing, frontend routes/templates and automated schedules are not yet implemented. Do not deploy as a completed website integration.

## Requirements

- PHP 8.3+
- Silverstripe framework 5
- Guzzle 7
- QueuedJobs 5.3

## Installation

```bash
composer require dorsetdigital/silverstripe-reapit
vendor/bin/sake dev/build flush=1
```

Set the following server-side environment variables (never commit credentials):

```dotenv
REAPIT_CLIENT_ID=
REAPIT_CLIENT_SECRET=
REAPIT_CUSTOMER_ID=SBOX
```

The client must install/authorise the application for access to its Reapit data. Use `SBOX` only for sandbox testing.

Run the initial import:

```bash
vendor/bin/sake dev/tasks/reapit-import
```

Schedule `DorsetDigital\Reapit\Job\PropertySyncJob` (e.g. hourly) and `DorsetDigital\Reapit\Job\ReconcileJob` (e.g. daily) through QueuedJobs. This module does not silently add jobs on every request.

## Design

- Reapit records use a composite customer ID and property ID key.
- The HTTP client handles OAuth 2.0 client credentials, headers, timeouts and paged requests.
- Failed imports do not advance the successful sync checkpoint.
- The mapper is a standalone extensible service, not hard-coded into the API client.
- A reconciliation pass hides properties that are no longer advertised.
- This integration does not write any information back to Reapit.

## Reapit references

- [REST API](https://foundations-documentation.reapit.cloud/api/api-documentation)
- [Website development guidance](https://foundations-documentation.reapit.cloud/app-development/website-development)

## License

BSD-3-Clause
