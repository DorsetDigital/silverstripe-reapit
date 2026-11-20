<?php

declare(strict_types=1);

namespace DorsetDigital\Reapit\Task;

use DorsetDigital\Reapit\Model\Property;
use DorsetDigital\Reapit\Model\SyncState;
use DorsetDigital\Reapit\Service\ApiClient;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;

/**
 * Explicitly delete a customer's imported properties, their images and sync checkpoints.
 *
 * Delete via the ORM so PropertyImage::onAfterDelete() also removes imported assets.
 */
class ClearPropertiesTask extends BuildTask
{
    private static string $segment = 'reapit-clear-properties';

    protected $title = 'Clear local Reapit properties';
    protected $description = 'Delete local Reapit properties and images, resetting synchronisation checkpoints';

    public function run($request): void
    {
        $customer = Injector::inst()->get(ApiClient::class)->customerId();
        $properties = Property::get()->filter('CustomerID', $customer);
        $propertyCount = 0;
        $imageCount = 0;

        foreach ($properties as $property) {
            // Explicitly delete image relations to guarantee asset cleanup hooks run.
            foreach ($property->Images() as $image) {
                $image->delete();
                $imageCount++;
            }

            $property->delete();
            $propertyCount++;
        }

        $states = SyncState::get()->filter('CustomerID', $customer);
        $checkpointCount = 0;
        foreach ($states as $state) {
            $state->delete();
            $checkpointCount++;
        }

        echo sprintf(
            "Cleared customer %s: %d properties, %d image records, %d sync checkpoints.\n",
            $customer,
            $propertyCount,
            $imageCount,
            $checkpointCount
        );
    }
}
