<?php

declare(strict_types=1);

use DorsetDigital\Reapit\Model\Property;
use Wilr\GoogleSitemaps\GoogleSitemap;

// This module is optional. Do not force sitemap dependencies on sites which
// do not use wilr/silverstripe-googlesitemaps.
if (class_exists(GoogleSitemap::class)) {
    GoogleSitemap::register_dataobject(Property::class, 'daily');
}
