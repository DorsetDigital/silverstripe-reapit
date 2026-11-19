<?php

declare(strict_types=1);

namespace DorsetDigital\Reapit\Task;

use DorsetDigital\Reapit\Model\Property;
use DorsetDigital\Reapit\Service\ApiClient;
use DorsetDigital\Reapit\Service\PropertyImageSynchroniser;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;

/**
 * Opt-in image import for one known property; safe for sandbox iteration.
 */
class ImportPropertyImagesTask extends BuildTask
{
    private static string $segment = 'reapit-import-images';
    protected $title = 'Import images for one Reapit property';
    protected $description = 'Download a property image collection into local Silverstripe assets';

    public function run($request): void
    {
        $id = $request->getVar('propertyId');
        if (!is_string($id) || !preg_match('/^[A-Za-z0-9_-]{1,100}$/', $id)) {
            throw new \InvalidArgumentException('A valid propertyId is required.');
        }

        $api = Injector::inst()->get(ApiClient::class);
        $property = Property::get()->filter([
            'CustomerID' => $api->customerId(),
            'ReapitID' => $id,
        ])->first();
        if (!$property) {
            throw new \RuntimeException("Property {$id} is not imported locally. Import it first.");
        }

        $response = $api->get('/propertyImages', ['propertyId' => $id, 'pageSize' => 100, 'pageNumber' => 1]);
        $items = $response['_embedded'] ?? null;
        if (!is_array($items) || !array_is_list($items)) {
            throw new \RuntimeException('Unexpected Reapit image response.');
        }
        if ((int) ($response['totalPageCount'] ?? 1) > 1) {
            throw new \RuntimeException('Image collection has multiple pages; refusing partial reconciliation.');
        }

        $count = Injector::inst()->get(PropertyImageSynchroniser::class)->sync($property, $items);
        echo "Property {$id}: " . count($items) . " images, {$count} downloaded.\n";
    }
}
