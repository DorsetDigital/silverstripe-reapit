# Silverstripe Reapit

A one-way, read-only property import from Reapit Foundations for Silverstripe CMS 5. The code is structured to simplify a future CMS 6 upgrade.

> **Development status:** initial backend foundation. Property images, production-grade incremental image syncing and automated schedules are not yet implemented. Frontend templates exist but are not yet integration-tested. Do not deploy as a completed website integration.

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

## Frontend page

Create a **Property listings** page in the Silverstripe SiteTree. The module supplies a
listing layout and a detail layout at `/your-page/{property-slug}`. These use local
`Property` DataObjects only; no frontend API requests are made.

Slugs are generated once on import, are unique across the local property table, and are retained when Reapit changes a listing's title. Existing records without slugs are backfilled by a subsequent import.

The listing includes pagination and a whitelist of date/rent sorts, controlled by
the `sort` query parameter. It shows only properties where `Available=true`.

The layouts in `templates/Layout/` are intentionally minimal defaults. Override
them in the host project theme to use the site's existing CSS and component patterns.
The module exposes its default stylesheet from `client/css/`. No JavaScript
is required for basic sorting.

**Important:** Image markup assumes imported `PropertyImage.Image` assets.
The local image synchronisation is not yet implemented; listings without images
will still render.

## Optional Google Sitemaps integration

If the host site uses \`wilr/silverstripe-googlesitemaps\` 3.x, the module
automatically registers imported properties with the sitemap using a **daily**
change frequency. The sitemap package is optional and is not installed by this
module.

The generated URLs use the first **published** PropertyListingPage and the
property's stable slug. Withdrawn/unavailable properties, records without slugs,
and properties without a published listing page are excluded.

The \`daily\` frequency is a crawl hint, not a guarantee of when Google will
revisit a listing. Sitemap updates are not an alternative to the import job.

For now, one published listing page is treated as the canonical owner of
property detail links. Override \`Property::Link()\` for multi-site installations.

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
