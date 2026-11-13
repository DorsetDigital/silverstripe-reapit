<?php

declare(strict_types=1);

namespace DorsetDigital\Reapit\Page;

use SilverStripe\CMS\Model\SiteTree;
use DorsetDigital\Reapit\Control\PropertyListingPageController;

/**
 * One CMS page owns the property listing and its property detail URLs.
 *
 * Create this page through the SiteTree as usual. No imported property creates
 * a SiteTree record.
 */
class PropertyListingPage extends SiteTree
{
    private static string $table_name = 'ReapitPropertyListingPage';

    private static string $controller_name = PropertyListingPageController::class;

    private static string $singular_name = 'Property listings';

    private static string $description = 'Displays imported Reapit properties and individual property details.';
}
