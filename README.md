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

For development, limit the **total number of properties** fetched and written:

```bash
vendor/bin/sake dev/tasks/reapit-import "limit=100"
```

For browser execution, visit `/dev/tasks/reapit-import?limit=100`.
Silverstripe CMS 5 passes command-line parameters as a separate sake argument,
not as a query string embedded in the task route.
The importer still uses API pages of **at most 100** records; `limit=25`
requests just 25, while `limit=250` requests up to three pages.

A limited run does **not** advance `LastSuccessful`, because it is not a
complete sync. Recurring property and reconciliation jobs are always unlimited
and are **not suitable for scoped sandbox testing**. Do not enable them until
you are ready to synchronise the entire configured dataset. Removing the limit
restores normal import behaviour.

This limit restricts API retrieval, not the number of records already stored
locally. A test database with 4,943 imported properties will retain those
records unless they are separately cleared.


Schedule `DorsetDigital\Reapit\Job\PropertySyncJob` (e.g. hourly) and `DorsetDigital\Reapit\Job\ReconcileJob` (e.g. daily) through QueuedJobs. This module does not silently add jobs on every request.

## Inspecting image metadata (development only)

Before enabling image downloads, inspect a small API page to verify the
customer's actual property image structure:

```bash
vendor/bin/sake dev/tasks/reapit-inspect-images "limit=10"
```

To inspect a specific property known to have a featured image, run:

```bash
vendor/bin/sake dev/tasks/reapit-inspect-images "propertyId=YOUR_PROPERTY_ID"
```

The targeted inspection checks both the embedded images and the
`/propertyImages` collection for that property. It performs two read-only API
requests. The task also supports an untargeted sample using `limit=10`.

The task requests `embed=images`, prints the first image record it finds,
and makes no database or asset changes. The limit is capped at 20 properties.
Embedding images can incur additional Reapit API usage charges.

## Import images for a single property (development)

After confirming its API image payload and importing the corresponding
property locally, download its images into Silverstripe's asset store:

```bash
vendor/bin/sake dev/tasks/reapit-import-images "propertyId=MKT265922"
```

This reads the complete `/propertyImages` collection (up to 100 records),
downloads only new or changed images, writes them as public Silverstripe
`Image` assets, and updates ordering and captions. It refuses a partial
multi-page response rather than deleting images based on incomplete data.
The task is intentionally scoped to **one property**. Global scheduled image
synchronisation is not enabled yet; the import needs integration testing first.

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

## Optional Schema Manager integration

If `dorsetdigital/silverstripe-schema-manager` is installed with the
real-estate builders introduced in its `feature/real-estate-schema` branch,
`PropertyListingPage` provides:

- `CollectionPage` and a paginated `ItemList` on the listings page;
- `RealEstateListing`, accommodation `PropertySchema` and rental `Offer`
  on each property detail route;
- stable property and offer identifiers, detail-page canonical IDs, rent,
  bedrooms, bathrooms, address and locally imported images when present.

The integration uses Schema Manager's existing page-level
`updateSchemaManagerEntities` hook; no duplicate JSON-LD script is rendered.
Nothing is emitted by this extension if Schema Manager isn't installed.
Site-wide organisation/website entities remain managed by Schema Manager itself.

Only available properties are included. Rental periods are mapped for known
monthly, weekly and annual codes; unknown periods do not produce misleading
rental prices. Currently GBP is assumed for rental offers, consistent with
the UK lettings data model; a future multi-currency import will need to map
the currency from Reapit.

**The real-estate builders are currently in an unmerged Schema Manager PR.**
These pages must be verified with that branch installed before release.

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
