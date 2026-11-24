<?php

declare(strict_types=1);

namespace DorsetDigital\Reapit\Model;

use SilverStripe\ORM\DataObject;
use SilverStripe\Control\Director;
use SilverStripe\Versioned\Versioned;
use DorsetDigital\Reapit\Page\PropertyListingPage;

/**
 * Local read-only copy of a Reapit property.
 * All imported fields are authoritative in Reapit.
 */
class Property extends DataObject
{
    private static string $table_name = 'ReapitProperty';

    private static array $db = [
        'CustomerID' => 'Varchar(32)',
        'ReapitID' => 'Varchar(100)',
        'Title' => 'Varchar(255)',
        'Slug' => 'Varchar(255)',
        'Description' => 'HTMLText',
        'Summary' => 'Text',
        'LongDescription' => 'HTMLText',
        'PropertyTypes' => 'Text',
        'PropertyStyles' => 'Text',
        'BuildingName' => 'Varchar(255)',
        'BuildingNumber' => 'Varchar(50)',
        'AddressLine1' => 'Varchar(255)',
        'AddressLine2' => 'Varchar(255)',
        'AddressLine3' => 'Varchar(255)',
        'AddressLine4' => 'Varchar(255)',
        'Postcode' => 'Varchar(32)',
        'Latitude' => 'Decimal(10,7)',
        'Longitude' => 'Decimal(10,7)',
        'HasGeolocation' => 'Boolean',
        'ImagesInitialised' => 'Boolean',
        'Bedrooms' => 'Int',
        'Bathrooms' => 'Int',
        'Rent' => 'Decimal(12,2)',
        'RentFrequency' => 'Varchar(30)',
        'LettingStatus' => 'Varchar(60)',
        'MarketingDate' => 'Datetime',
        'RemoteModified' => 'Datetime',
        'Available' => 'Boolean',
        'RawData' => 'Text',
    ];

    private static array $indexes = [
        'SlugUnique' => ['type' => 'unique', 'columns' => ['Slug']],
        'RemoteReference' => ['type' => 'unique', 'columns' => ['CustomerID', 'ReapitID']],
        'VisibleAndDate' => ['columns' => ['Available', 'MarketingDate']],
    ];

    private static array $has_many = [
        'Images' => PropertyImage::class,
    ];

    private static array $cascade_deletes = [
        'Images',
    ];

    private static string $default_sort = '"MarketingDate" DESC, "ID" DESC';

    /** Validated Reapit status codes, independent of translated display labels. */
    public const LETTING_STATUSES = [
        'toLet',
        'toLetUnavailable',
        'underOffer',
        'underOfferUnavailable',
        'arrangingTenancy',
        'arrangingTenancyUnavailable',
        'tenancyCurrent',
        'tenancyCurrentUnavailable',
        'tenancyFinished',
        'tenancyCancelled',
        'letByOtherAgent',
        'letPrivately',
        'withdrawn',
        'marketAppraisal',
        'provisional',
        'sold',
    ];

    private static string $map_style_url = 'https://tiles.openfreemap.org/styles/liberty';

    public function getHasMapCoordinates(): bool
    {
        return (bool) $this->HasGeolocation;
    }

    public function getMapStyleURL(): string
    {
        return (string) $this->config()->get('map_style_url');
    }

    public function getPhotographs()
    {
        return $this->Images()->filter('Type', 'photograph');
    }

    public function getFloorPlans()
    {
        return $this->Images()->filter('Type', 'floorPlan');
    }

    public function getEPCImages()
    {
        return $this->Images()->filter('Type', 'epc');
    }

    /**
     * Use the first published PropertyListingPage as the canonical frontend
     * route. Sites with multiple listing pages can override this method.
     */
    public function Link(): ?string
    {
        if (!$this->Available || !$this->Slug) {
            return null;
        }

        $page = Versioned::get_by_stage(PropertyListingPage::class, Versioned::LIVE)->first();
        return $page ? $page->Link(rawurlencode((string) $this->Slug)) : null;
    }

    /**
     * Human-readable marketing status; retain the original Reapit code in LettingStatus.
     */
    public function getLettingStatusLabel(): string
    {
        $status = (string) $this->LettingStatus;

        return match ($status) {
            'toLet' => _t(__CLASS__ . '.TO_LET', 'Available to rent'),
            'toLetUnavailable' => _t(__CLASS__ . '.TO_LET_UNAVAILABLE', 'To let (unavailable)'),
            'underOffer' => _t(__CLASS__ . '.UNDER_OFFER', 'Under offer'),
            'underOfferUnavailable' => _t(__CLASS__ . '.UNDER_OFFER_UNAVAILABLE', 'Under offer (unavailable)'),
            'arrangingTenancy' => _t(__CLASS__ . '.ARRANGING_TENANCY', 'Arranging tenancy'),
            'arrangingTenancyUnavailable' => _t(__CLASS__ . '.ARRANGING_TENANCY_UNAVAILABLE', 'Arranging tenancy (unavailable)'),
            'tenancyCurrent' => _t(__CLASS__ . '.TENANCY_CURRENT', 'Currently let'),
            'tenancyCurrentUnavailable' => _t(__CLASS__ . '.TENANCY_CURRENT_UNAVAILABLE', 'Currently let (unavailable)'),
            'tenancyFinished' => _t(__CLASS__ . '.TENANCY_FINISHED', 'Tenancy finished'),
            'tenancyCancelled' => _t(__CLASS__ . '.TENANCY_CANCELLED', 'Tenancy cancelled'),
            'letByOtherAgent' => _t(__CLASS__ . '.LET_BY_OTHER_AGENT', 'Let by another agent'),
            'letPrivately' => _t(__CLASS__ . '.LET_PRIVATELY', 'Let privately'),
            'withdrawn' => _t(__CLASS__ . '.WITHDRAWN', 'Withdrawn'),
            'marketAppraisal' => _t(__CLASS__ . '.MARKET_APPRAISAL', 'Market appraisal'),
            'provisional' => _t(__CLASS__ . '.PROVISIONAL', 'Provisional'),
            'sold' => _t(__CLASS__ . '.SOLD', 'Sold'),
            default => trim((string) preg_replace('/(?<=[a-z])(?=[A-Z])/', ' ', ucfirst($status))),
        };
    }

    public function getMetaTitle(): string
    {
        return (string) $this->Title;
    }

    public function getMetaDescription(): string
    {
        foreach ([$this->Summary, $this->Description, $this->LongDescription] as $value) {
            $text = trim(preg_replace('/\\s+/u', ' ', html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
            if ($text !== '') {
                return mb_substr($text, 0, 160);
            }
        }

        return '';
    }

    public function AbsoluteLink(): ?string
    {
        $link = $this->Link();
        return $link !== null ? Director::absoluteURL($link) : null;
    }

    public function canView($member = null): bool
    {
        return $this->Link() !== null;
    }

    public function canCreate($member = null, $context = []): bool
    {
        return false;
    }

    public function canEdit($member = null): bool
    {
        return false;
    }

    public function canDelete($member = null): bool
    {
        return false;
    }
}
