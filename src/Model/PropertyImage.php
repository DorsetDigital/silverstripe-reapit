<?php

declare(strict_types=1);

namespace DorsetDigital\Reapit\Model;

use SilverStripe\Assets\Image;
use SilverStripe\ORM\DataObject;

class PropertyImage extends DataObject
{
    private static string $table_name = 'ReapitPropertyImage';

    private static array $db = [
        'ReapitID' => 'Varchar(100)',
        'RemoteURL' => 'Text',
        'Position' => 'Int',
    ];

    private static array $has_one = [
        'Property' => Property::class,
        'Image' => Image::class,
    ];

    private static array $owns = ['Image'];

    private static string $default_sort = '"Position" ASC';

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
