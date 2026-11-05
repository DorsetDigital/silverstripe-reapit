<?php

declare(strict_types=1);

namespace DorsetDigital\Reapit\Model;

use SilverStripe\ORM\DataObject;

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

    private static string $default_sort = '"MarketingDate" DESC, "ID" DESC';

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
