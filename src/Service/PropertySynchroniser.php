<?php

declare(strict_types=1);

namespace DorsetDigital\Reapit\Service;

use DorsetDigital\Reapit\Model\Property;
use DorsetDigital\Reapit\Model\SyncState;
use SilverStripe\Core\Injector\Injectable;

/**
 * Idempotent, resumable import; the checkpoint advances only after all pages succeed.
 */
class PropertySynchroniser
{
    use Injectable;

    public function __construct(
        private readonly ApiClient $api,
        private readonly PropertyMapper $mapper,
        private readonly PropertyImageSynchroniser $imageSynchroniser
    ) {
    }

    public function import(bool $full = false, ?int $maxProperties = null, bool $withImages = false): int
    {
        if ($maxProperties !== null && $maxProperties < 1) {
            throw new \InvalidArgumentException('Property import limit must be a positive integer.');
        }

        // Image import is deliberately opt-in and bounded until the full
        // incremental image feed has been tested in a real Silverstripe site.
        if ($withImages && ($maxProperties === null || $maxProperties > 100)) {
            throw new \InvalidArgumentException('Image-enabled imports require limit=1..100 during development.');
        }

        $customer = $this->api->customerId();
        $state = SyncState::get()->filter(['CustomerID' => $customer, 'Channel' => 'properties'])->first();
        if (!$state) {
            $state = SyncState::create();
            $state->CustomerID = $customer;
            $state->Channel = 'properties';
        }
        $started = gmdate('Y-m-d H:i:s');
        $query = [
            'lettingStatus' => ['toLet', 'tenancyCurrent'],
            'internetAdvertising' => 'true',
        ];
        if ($withImages) {
            $query['embed'] = 'images';
        }
        if (!$full && $state->LastSuccessful) {
            $query['modifiedFrom'] = gmdate('Y-m-d\TH:i:s\Z', strtotime($state->LastSuccessful) - 120);
        }

        $count = 0;
        foreach ($this->api->pages('/properties', $query, $maxProperties) as $page) {
            foreach ($page as $remote) {
                if (!is_array($remote) || empty($remote['id'])) {
                    continue;
                }
                $property = Property::get()->filter([
                    'CustomerID' => $customer,
                    'ReapitID' => (string) $remote['id'],
                ])->first() ?: Property::create();

                $property->CustomerID = $customer;
                $property->ReapitID = (string) $remote['id'];
                $this->mapper->mapProperty($property, $remote);
                PropertySlugger::ensureSlug($property);
                $property->write();

                if ($withImages) {
                    $embedded = $remote['_embedded'] ?? null;
                    // A missing image collection is not proof that there are
                    // zero images. Never prune locally stored images on that basis.
                    if (is_array($embedded) && array_key_exists('images', $embedded)) {
                        $images = $embedded['images'];
                        if (!is_array($images) || !array_is_list($images)) {
                            throw new \RuntimeException('Malformed embedded Reapit image collection.');
                        }
                        $this->imageSynchroniser->sync($property, $images, false);
                    } elseif (!empty($remote['featuredImageUrl'])) {
                        throw new \RuntimeException(
                            'Reapit omitted embedded images for property ' . $property->ReapitID
                        );
                    }
                }

                $count++;
            }
        }
        // A limited run may not have seen every record. It must not advance
        // the incremental checkpoint or interfere with subsequent full syncs.
        if ($maxProperties === null) {
            $state->LastSuccessful = $started;
            $state->write();
        }

        return $count;
    }

    /**
     * Re-fetch all currently marketable property IDs. Only remove stale local
     * records after the complete remote listing has been fetched successfully.
     */
    public function reconcile(): int
    {
        $customer = $this->api->customerId();
        $seen = [];
        foreach ($this->api->pages('/properties', [
            'lettingStatus' => ['toLet', 'tenancyCurrent'],
            'internetAdvertising' => 'true',
        ]) as $page) {
            foreach ($page as $remote) {
                if (is_array($remote) && isset($remote['id'])) {
                    $seen[(string) $remote['id']] = true;
                }
            }
        }
        $removed = 0;
        foreach (Property::get()->filter('CustomerID', $customer) as $property) {
            if (!isset($seen[$property->ReapitID])) {
                $property->Available = false;
                $property->write();
                $removed++;
            }
        }
        return $removed;
    }
}
