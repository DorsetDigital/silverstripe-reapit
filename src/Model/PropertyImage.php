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
        'ReapitETag' => 'Varchar(255)',
        'Caption' => 'Varchar(255)',
        'Position' => 'Int',
    ];

    private static array $has_one = [
        'Property' => Property::class,
        'Image' => Image::class,
    ];

    private static array $owns = ['Image'];

    private static string $default_sort = '"Position" ASC';

    /**
     * Imported files belong to this module. When the relationship is deleted,
     * clean up the underlying asset as well, regardless of whether deletion
     * came from reconciliation or cascading deletion of the property.
     */
    protected function onAfterDelete(): void
    {
        parent::onAfterDelete();

        if (!$this->ImageID) {
            return;
        }

        // Do not delete an asset that another imported image references.
        if (self::get()->filter('ImageID', $this->ImageID)->exists()) {
            return;
        }

        $image = Image::get()->byID($this->ImageID);
        if ($image) {
            $image->deleteFile();
            $image->delete();
        }
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
