# Silverstripe Reapit

A one-way, read-only property import from Reapit Foundations for Silverstripe CMS 5. The code is structured to simplify a future CMS 6 upgrade.

> **Development status:** initial backend foundation. Single-property and bounded embedded-image imports are available for testing. Incremental image synchronisation is implemented but not yet integration-tested; recurring jobs are managed by a required caretaker job. Frontend templates exist but are not yet integration-tested. Do not deploy as a completed website integration.

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

To import a specific property without paging the collection, use:

```bash
ddev sake dev/tasks/reapit-import "propertyId=MKT265922&images=1"
```

This fetches the single property through `/properties/{id}`; it never
updates the sync checkpoint. If `limit` is also supplied, it is explicitly
ignored in `propertyId` mode and the task reports this.

To import images alongside a bounded development import:

```bash
ddev sake dev/tasks/reapit-import "limit=20&images=1"
```

This requests `embed=images` for the limited properties and imports the embedded
collection into local assets. Image-enabled imports are intentionally capped at
100 properties, require an explicit limit and do **not** alter checkpoints.
A missing image collection is never treated as evidence that existing images
should be deleted. Do not run this on a full production data set yet.

This limit restricts API retrieval, not the number of records already stored
locally. A test database with 4,943 imported properties will retain those
records unless they are separately cleared.


Schedule `DorsetDigital\Reapit\Job\PropertySyncJob` (e.g. hourly) and `DorsetDigital\Reapit\Job\ReconcileJob` (e.g. daily) through QueuedJobs. This module does not silently add jobs on every request.

## Clearing local imported data

For a clean resynchronisation of the **configured Reapit customer**, use:

```bash
ddev sake dev/tasks/reapit-clear-properties
```

This command removes imported
`Property` records and their `PropertyImage` relations via Silverstripe's ORM,
including the module-managed image assets. It also deletes that customer's
property and image `SyncState` checkpoints so subsequent syncs start from
scratch. It does not delete site pages or QueuedJobs records.

Disable scheduled Reapit jobs while clearing and rebuilding the local copy.
After clearing, run `ddev sake dev/tasks/reapit-import` and
`ddev sake dev/tasks/reapit-sync-images "all=1"` to reimport the data.

## Recurring jobs and monitoring

QueuedJobs' default-job configuration ensures `ReapitCaretakerJob` is present. Run the standard QueuedJobs queue processor regularly (for example, each minute); the caretaker runs every 300 seconds by default.

The caretaker ensures each **enabled** sync job has a queued or active instance. **All full-feed jobs are disabled by default**, so installing or updating this module cannot unexpectedly start an unbounded sync. Enable jobs explicitly in the host project's YAML after validating the intended feed and API usage. Successful jobs schedule their successor in `afterComplete()` using the configurable `interval`. Missing jobs are requeued by the caretaker. Jobs already running or deliberately paused are not duplicated.

Configure `enabled`, `interval` and `alert_after` (seconds) in project YAML on the four job classes. Property sync defaults to hourly; property reconciliation daily; image feed hourly. Full image reconciliation should remain disabled until live data and API costs have been reviewed.

For example, enable the property sync in project configuration:

```yaml
DorsetDigital\Reapit\Job\PropertySyncJob:
  enabled: true
  interval: 3600
  alert_after: 10800
```

Set `REAPIT_SLACK_WEBHOOK` to enable Slack notifications when a job has not completed successfully within its `alert_after` threshold, followed by a recovery notification after success. Alerts are deduplicated in `ReapitJobHealth`; job health is deliberately separate from API `SyncState` checkpoints. Without a webhook, jobs still run but alerts are not sent.

A caretaker cannot detect a completely stopped queue worker: use external monitoring for the queue runner itself. On first installation, allow one full alert interval before considering jobs overdue. Scheduling a job does not make an unsafe full image reconciliation safe; enable that job only after validating its behaviour with the customer's real feed.

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
ddev sake dev/tasks/reapit-import-images "propertyId=MKT265922"
```

This reads the complete `/propertyImages` collection (up to 100 records),
downloads only new or changed images, writes them as public Silverstripe
`Image` assets, and updates ordering and captions. It refuses a partial
multi-page response rather than deleting images based on incomplete data.
The task is intentionally scoped to **one property**. Global scheduled image
synchronisation is not enabled yet; the import needs integration testing first.

## Incremental image synchronisation

Reapit's `/propertyImages` changed-image feed is queried in batches of up
to **100 locally imported property IDs**, paginated at **100 image records**.
Only new or changed images are downloaded. The image feed has its own
`SyncState` checkpoint; a failure never advances it.

To test **one known property** in DDEV without advancing the checkpoint:

```bash
ddev sake dev/tasks/reapit-sync-images "propertyId=MKT265922"
```

To test the first 20 imported properties, also without advancing the checkpoint:

```bash
ddev sake dev/tasks/reapit-sync-images "limit=20"
```

The default invocation inspects only ten properties. **Only** `all=1` scans
every available local property and advances the checkpoint:

```bash
ddev sake dev/tasks/reapit-sync-images "all=1"
```

After end-to-end testing, schedule `DorsetDigital\\Reapit\\Job\\PropertyImageSyncJob`
through QueuedJobs, ideally after the property sync job. Neither job is
automatically scheduled. An initial full run with no image checkpoint can
request all historical image records; do not enable it without first testing
on a suitable dataset.

**Deletions are not inferred from the incremental feed.** It returns changed
image records, not a complete current collection. Deleted images need a
separate, complete per-property reconciliation; this job never deletes local
assets. An image changed while the job runs will be picked up by the next
run's overlapping modification window.

## Full image reconciliation (deletions)

Incremental image updates do not reveal deletions reliably. For a complete,
per-property comparison against `/propertyImages`, run a **read-only dry run**:

```bash
ddev sake dev/tasks/reapit-reconcile-images "propertyId=MKT265922"
```

The task retrieves every image page, validates Reapit's reported total count
and page count, and lists locally stored IDs absent from the remote inventory.
It does not change the database or download/delete assets in dry-run mode.

To reconcile one property after reviewing the dry-run output:

```bash
ddev sake dev/tasks/reapit-reconcile-images "propertyId=MKT265922&apply=1"
```

An applied run downloads changed/new images and removes obsolete imported
image records and their module-managed assets. You can instead use `limit=10`
or `limit=100` for bounded testing. The default is a ten-property dry run.
`all=1` explicitly allows all local available properties; this performs
one or more API requests **per property** and may be costly. Unlike the
incremental feed, full reconciliation is intentionally not frequent.

`PropertyImageReconcileJob` is available but **not scheduled automatically**.
Only schedule it after testing against the customer dataset and assessing API
usage. It runs an applied reconciliation across every available property.


## Configuring imported letting statuses

By default, the importer requests **all** Reapit properties with
`internetAdvertising=true`, irrespective of letting status.

For projects that only want certain letting statuses, configure an explicit
allow-list in the consuming site's YAML:

```yaml
DorsetDigital\Reapit\Service\PropertySynchroniser:
  letting_statuses:
    - toLet
    - underOffer
    - arrangingTenancy
    - tenancyCurrent
```

An empty list (`letting_statuses: []`) means no letting-status restriction.
The importer and reconciliation use the same query filter. Invalid or duplicate
codes cause a configuration error rather than silently excluding properties.
The supported API codes are maintained in `Property::LETTING_STATUSES`.
Display labels are translated independently using Silverstripe i18n.

After changing the filter, run a full property import and reconciliation to
pick up newly included properties and hide locally stored properties no longer
matching the selection. A targeted `propertyId` import deliberately bypasses
collection filters.

### Translating letting-status labels

The default labels use Silverstripe i18n keys in
`DorsetDigital\\Reapit\\Model\\Property`. A consuming project can override the
English labels or supply other languages using its standard `lang/*.yml`
translation files, for example in `app/lang/en.yml`:

```yaml
en:
  'DorsetDigital\\Reapit\\Model\\Property':
    UNDER_OFFER: 'Let agreed'
    ARRANGING_TENANCY: 'Let agreed'
```

Labels are translated when rendered; Reapit status codes in the database and
configured API filters remain unchanged. Unrecognised status codes are
displayed as readable text without translation.

## Structured addresses

Reapit address components are stored separately as `BuildingName`, `BuildingNumber`,
`AddressLine1`–`AddressLine4`, and `Postcode`. The previously stored combined
`Address` field has been removed. Project-specific formatting (including suppressing
house numbers) belongs in the consuming site's extension and templates, not the
import mapper. The module's default templates render individual fields directly.

Existing installations must run `dev/build`, then a **full property import**
(`vendor/bin/sake dev/tasks/reapit-import`) to populate the new fields,
as incremental sync may not retrieve unchanged properties. Any project templates
or extensions using `$Address` must be updated. Existing slugs are preserved.

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

**Important:** Image markup uses locally imported `PropertyImage.Image` assets.
Images are currently imported by the single-property task or by explicitly
passing `images=1` in a bounded manual import; the incremental image job is available but must be explicitly scheduled after testing.
Listings without images still render.

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

## Property image lightbox

Property detail pages use PhotoSwipe 5 to open imported images in a lightbox. The gallery keeps ordinary image links as a fallback when JavaScript is disabled. Frontend assets are bundled with the Composer package; **Node is not required on deployed sites**.

To rebuild the committed frontend assets while developing this module:

```bash
npm ci
npm run build
```

For incremental development, use `npm run dev`. The small Vite build lives in `vite.config.js` and uses `client/src/gallery.js` and `client/src/photoswipe.css`. It is adapted from the frontend integration in `dorsetdigital/silverstripe-photoswipe`, but has no dependency on that module.

## Property location maps

Reapit's `address.geolocation` coordinates are imported to `Latitude`, `Longitude`
and `HasGeolocation`. A property detail page with valid coordinates displays a
MapLibre map using OpenFreeMap's Liberty style. The map module and MapLibre CSS
are included only on detail pages with coordinates, and the renderer is
dynamically imported when the map approaches the viewport. The map code and
style depend on the external jsDelivr and OpenFreeMap services.

To change the map style in the host site's YAML:

```yaml
DorsetDigital\Reapit\Model\Property:
  map_style_url: 'https://tiles.openfreemap.org/styles/liberty'
```

After deploying, run `dev/build` and a full property import to populate
coordinates for properties not subsequently modified in Reapit.
