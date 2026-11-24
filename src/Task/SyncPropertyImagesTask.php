<?php

declare(strict_types=1);

namespace DorsetDigital\Reapit\Task;

use DorsetDigital\Reapit\Service\PropertyImageFeedSynchroniser;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;

class SyncPropertyImagesTask extends BuildTask
{
    private static string $segment = 'reapit-sync-images';
    protected $title = 'Synchronise modified Reapit property images';
    protected $description = 'Read-only API delta sync with opt-in bounded testing';

    public function run($request): void
    {
        $propertyId = $request->getVar('propertyId');
        if ($propertyId !== null && (!is_string($propertyId) || !preg_match('/^[A-Za-z0-9_-]{1,100}$/', $propertyId))) {
            throw new \InvalidArgumentException('propertyId must be a valid Reapit property ID.');
        }

        $raw = $request->getVar('limit');
        $limit = null;
        if ($raw !== null && $raw !== '') {
            if (!is_scalar($raw) || !ctype_digit((string) $raw) || (int) $raw < 1 || (int) $raw > 100) {
                throw new \InvalidArgumentException('limit must be between 1 and 100.');
            }
            $limit = (int) $raw;
        }

        $all = $request->getVar('all');
        if ($all !== null && $all !== '1') {
            throw new \InvalidArgumentException('all must be 1 when provided.');
        }
        if ($all === '1' && ($limit !== null || $propertyId !== null)) {
            throw new \InvalidArgumentException('all=1 cannot be combined with limit or propertyId.');
        }

        // Safe default: only ten properties, no checkpoint update.
        if ($all !== '1' && $limit === null && $propertyId === null) {
            $limit = 10;
        }

        $result = Injector::inst()->get(PropertyImageFeedSynchroniser::class)->sync($limit, $propertyId);
        echo sprintf(
            "Checked %d properties; %d image inventories initialised; %d changed image records; %d downloaded%s.\n",
            $result['properties'],
            $result['initialised'],
            $result['changed'],
            $result['downloaded'],
            $all === '1' ? ' (full run, checkpoint advanced)' : ' (test run, checkpoint unchanged)'
        );
    }
}
