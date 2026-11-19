<?php

declare(strict_types=1);

namespace DorsetDigital\Reapit\Service;

use DorsetDigital\Reapit\Model\Property;
use SilverStripe\View\Parsers\URLSegmentFilter;

/**
 * Create a readable, stable URL segment for newly imported properties.
 *
 * Slugs are assigned once; subsequent remote updates never change the URL.
 * The database unique index guards against accidental duplicate segments.
 */
class PropertySlugger
{
    public static function ensureSlug(Property $property): void
    {
        if (trim((string) $property->Slug) !== '') {
            return;
        }

        $filter = URLSegmentFilter::create();
        $name = trim(implode(' ', array_filter([
            (string) $property->Title,
            (string) $property->Address,
        ])));
        $base = trim(substr($filter->filter($name), 0, 170), '-');
        if ($base === '') {
            $base = 'property';
        }

        $candidate = $base;
        $number = 2;
        while (Property::get()->filter('Slug', $candidate)->exclude('ID', (int) $property->ID)->exists()) {
            $candidate = $base . '-' . $number++;
        }

        $property->Slug = $candidate;
    }
}
