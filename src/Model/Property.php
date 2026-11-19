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
        'Address' => 'Varchar(500)',
        'Postcode' => 'Varchar(32)',
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
        return match ((string) $this->LettingStatus) {
            'toLet' => 'Available to rent',
            'tenancyCurrent' => 'Currently let',
            default => (string) $this->LettingStatus,
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
