<?php

declare(strict_types=1);

namespace DorsetDigital\Reapit\Service;

use DorsetDigital\Reapit\Model\Property;
use DorsetDigital\Reapit\Model\SyncState;
use InvalidArgumentException;
use RuntimeException;

/**
 * Incrementally synchronise property images using Reapit's modifiedFrom feed.
 *
 * The feed contains changed images, NOT complete property image collections.
 * It must never remove local images absent from a delta response.
 */
class PropertyImageFeedSynchroniser
{
    public function __construct(
        private readonly ApiClient $api,
        private readonly PropertyImageSynchroniser $images,
        private readonly PropertyImageReconciler $reconciler
    ) {
    }

    /**
     * @return array{properties:int, changed:int, downloaded:int, initialised:int}
     */
    public function sync(?int $maxProperties = null, ?string $propertyId = null): array
    {
        if ($maxProperties !== null && ($maxProperties < 1 || $maxProperties > 100)) {
            throw new InvalidArgumentException('Test property limit must be between 1 and 100.');
        }
        if ($propertyId !== null && !preg_match('/^[A-Za-z0-9_-]{1,100}$/', $propertyId)) {
            throw new InvalidArgumentException('Invalid Reapit propertyId.');
        }
        if ($propertyId !== null && $maxProperties !== null) {
            throw new InvalidArgumentException('Use either propertyId or limit, not both.');
        }

        $customer = $this->api->customerId();
        $scoped = $propertyId !== null || $maxProperties !== null;
        $state = SyncState::get()->filter(['CustomerID' => $customer, 'Channel' => 'images'])->first();
        $started = gmdate('Y-m-d H:i:s');

        $query = [];
        if ($state && $state->LastSuccessful) {
            // Overlap to tolerate timestamp precision and eventual consistency.
            $query['modifiedFrom'] = gmdate('Y-m-d\TH:i:s\Z', strtotime($state->LastSuccessful) - 120);
        }

        $properties = Property::get()->filter([
            'CustomerID' => $customer,
            'Available' => true,
        ])->sort('ID ASC');
        if ($propertyId !== null) {
            $properties = $properties->filter('ReapitID', $propertyId);
        } elseif ($maxProperties !== null) {
            $properties = $properties->limit($maxProperties);
        }

        $batch = [];
        $processed = 0;
        $changed = 0;
        $downloaded = 0;
        $initialised = 0;

        foreach ($properties as $property) {
            // A property's images can predate its first inclusion in the
            // property feed. The modified-image feed cannot bootstrap them.
            // Also initialise previously imported properties after upgrading.
            if (!$property->ImagesInitialised) {
                $initial = $this->reconciler->reconcile($property->ReapitID, apply: true);
                $downloaded += $initial['downloaded'];
                $property->ImagesInitialised = true;
                $property->write();
                $initialised++;
            }

            $batch[$property->ReapitID] = $property;
            if (count($batch) === 100) {
                [$delta, $new] = $this->syncBatch($batch, $query);
                $changed += $delta;
                $downloaded += $new;
                $processed += count($batch);
                $batch = [];
            }
        }
        if ($batch !== []) {
            [$delta, $new] = $this->syncBatch($batch, $query);
            $changed += $delta;
            $downloaded += $new;
            $processed += count($batch);
        }

        if ($propertyId !== null && $processed !== 1) {
            throw new RuntimeException("Property {$propertyId} is not an available imported property.");
        }

        // A bounded run is for testing only and must not advance the feed
        // checkpoint. A full run advances only after every batch succeeds.
        if (!$scoped) {
            if (!$state) {
                $state = SyncState::create();
                $state->CustomerID = $customer;
                $state->Channel = 'images';
            }
            $state->LastSuccessful = $started;
            $state->write();
        }

        return ['properties' => $processed, 'changed' => $changed, 'downloaded' => $downloaded, 'initialised' => $initialised];
    }

    /**
     * @param array<string, Property> $properties
     * @param array<string, string> $query
     * @return array{int, int}
     */
    private function syncBatch(array $properties, array $query): array
    {
        $query['propertyId'] = array_keys($properties);
        $changed = 0;
        $downloaded = 0;
        foreach ($this->api->pages('/propertyImages', $query) as $page) {
            $grouped = [];
            foreach ($page as $image) {
                if (!is_array($image)
                    || !is_string($image['propertyId'] ?? null)
                    || !isset($properties[$image['propertyId']])
                ) {
                    throw new RuntimeException('Unexpected property image in incremental API response.');
                }
                $grouped[$image['propertyId']][] = $image;
                $changed++;
            }
            foreach ($grouped as $id => $images) {
                // Pages can contain only a subset of a property's images.
                $downloaded += $this->images->sync($properties[$id], $images, false);
            }
        }

        return [$changed, $downloaded];
    }
}
