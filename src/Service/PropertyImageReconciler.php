<?php

declare(strict_types=1);

namespace DorsetDigital\Reapit\Service;

use DorsetDigital\Reapit\Model\Property;
use RuntimeException;

/**
 * Reconcile complete image inventories rather than interpreting incremental
 * changes as deletions. Dry-run by default in the development task.
 */
class PropertyImageReconciler
{
    public function __construct(
        private readonly ApiClient $api,
        private readonly PropertyImageSynchroniser $images
    ) {
    }

    /**
     * @return array{properties:int, existing:int, remote:int, missing:int, downloaded:int, removals:array<string, list<string>>}
     */
    public function reconcile(?string $propertyId = null, ?int $limit = null, bool $apply = false): array
    {
        if ($propertyId !== null && !preg_match('/^[A-Za-z0-9_-]{1,100}$/', $propertyId)) {
            throw new \InvalidArgumentException('Invalid Reapit property ID.');
        }
        if ($limit !== null && ($limit < 1 || $limit > 100)) {
            throw new \InvalidArgumentException('The test limit must be 1..100.');
        }
        if ($propertyId !== null && $limit !== null) {
            throw new \InvalidArgumentException('Use propertyId or limit, not both.');
        }

        $properties = Property::get()->filter([
            'CustomerID' => $this->api->customerId(),
            'Available' => true,
        ])->sort('ID ASC');
        if ($propertyId !== null) {
            $properties = $properties->filter('ReapitID', $propertyId);
        } elseif ($limit !== null) {
            $properties = $properties->limit($limit);
        }

        $result = [
            'properties' => 0,
            'existing' => 0,
            'remote' => 0,
            'missing' => 0,
            'downloaded' => 0,
            'removals' => [],
        ];

        foreach ($properties as $property) {
            // Fully fetch AND validate before calling any destructive operation.
            $remote = $this->fetchCompleteImages($property);
            $remoteIds = array_fill_keys(array_column($remote, 'id'), true);
            $missing = [];
            foreach ($property->Images() as $image) {
                $result['existing']++;
                if (!isset($remoteIds[$image->ReapitID])) {
                    $missing[] = (string) $image->ReapitID;
                }
            }

            $result['remote'] += count($remote);
            $result['missing'] += count($missing);
            if ($missing !== []) {
                $result['removals'][$property->ReapitID] = $missing;
            }

            if ($apply) {
                $result['downloaded'] += $this->images->sync($property, $remote, true);
            }
            $result['properties']++;
        }

        if ($propertyId !== null && $result['properties'] !== 1) {
            throw new RuntimeException("Property {$propertyId} is not an available local property.");
        }

        return $result;
    }

    /**
     * Fetch all image pages with a stable page size and verify the declared
     * total. Empty/malformed/inconsistent responses are never reconciled.
     *
     * @return list<array<string, mixed>>
     */
    private function fetchCompleteImages(Property $property): array
    {
        $images = [];
        $seen = [];
        $page = 1;
        $expectedPages = null;
        $expectedCount = null;

        do {
            $response = $this->api->get('/propertyImages', [
                'propertyId' => $property->ReapitID,
                'pageSize' => 100,
                'pageNumber' => $page,
            ]);
            $count = $response['totalCount'] ?? null;
            $pages = $response['totalPageCount'] ?? null;
            $items = $response['_embedded'] ?? null;

            if (!is_numeric($count) || !is_numeric($pages)
                || (int) $count < 0 || (int) $pages < 0
                || !is_array($items) || !array_is_list($items)) {
                throw new RuntimeException("Incomplete Reapit image response for {$property->ReapitID}.");
            }

            $expectedPages ??= max(1, (int) $pages);
            $expectedCount ??= (int) $count;
            if ($expectedPages !== max(1, (int) $pages) || $expectedCount !== (int) $count) {
                throw new RuntimeException("Image page totals changed for {$property->ReapitID}.");
            }

            foreach ($items as $image) {
                if (!is_array($image)
                    || !is_string($image['id'] ?? null)
                    || $image['id'] === ''
                    || ($image['propertyId'] ?? null) !== $property->ReapitID
                    || isset($seen[$image['id']])) {
                    throw new RuntimeException("Invalid or duplicate image record for {$property->ReapitID}.");
                }
                $seen[$image['id']] = true;
                $images[] = $image;
            }
            $page++;
        } while ($page <= $expectedPages);

        if (count($images) !== $expectedCount) {
            throw new RuntimeException("Image count mismatch for {$property->ReapitID}; refusing reconciliation.");
        }

        return $images;
    }
}
