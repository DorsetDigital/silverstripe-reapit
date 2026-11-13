<?php

declare(strict_types=1);

namespace DorsetDigital\Reapit\Task;

use DorsetDigital\Reapit\Service\ApiClient;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;

/**
 * Inspect actual sandbox image metadata before implementing asset import.
 * Read-only: no images are downloaded and no ORM records are modified.
 */
class InspectPropertyImagesTask extends BuildTask
{
    private static string $segment = 'reapit-inspect-images';
    protected $title = 'Inspect Reapit property image payload';
    protected $description = 'Inspect an embedded image record from a small page of advertised lettings';

    public function run($request): void
    {
        $propertyId = $request->getVar('propertyId');
        if ($propertyId !== null && $propertyId !== '') {
            if (!is_string($propertyId) || !preg_match('/^[A-Za-z0-9_-]{1,100}$/', $propertyId)) {
                throw new \InvalidArgumentException('propertyId must be a Reapit property reference.');
            }

            $api = Injector::inst()->get(ApiClient::class);
            $property = $api->get('/properties/' . rawurlencode($propertyId), ['embed' => 'images']);
            $embedded = $property['_embedded'] ?? [];
            $images = is_array($embedded) ? ($embedded['images'] ?? null) : null;

            echo 'Property ID: ' . $propertyId . "\n";
            echo 'Featured image URL: ' . (string) ($property['featuredImageUrl'] ?? '(none)') . "\n";
            echo 'Embedded keys: ' . json_encode(is_array($embedded) ? array_keys($embedded) : [], JSON_THROW_ON_ERROR) . "\n";
            echo 'Embedded images: ' . (is_array($images) ? count($images) : 0) . "\n";
            if (is_array($images) && isset($images[0]) && is_array($images[0])) {
                echo 'First embedded image: ' . json_encode($images[0], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
            }

            $response = $api->get('/propertyImages', ['propertyId' => $propertyId, 'pageSize' => 20, 'pageNumber' => 1]);
            $collection = $response['_embedded'] ?? null;
            echo 'Direct image collection type: ' . get_debug_type($collection) . "\n";
            echo 'Direct image count (page one): ' . (is_array($collection) ? count($collection) : 0) . "\n";
            if (is_array($collection) && isset($collection[0]) && is_array($collection[0])) {
                echo 'First direct image: ' . json_encode($collection[0], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
            }
            return;
        }

        $rawLimit = $request->getVar('limit');
        $limit = $rawLimit === null || $rawLimit === '' ? 10 : $rawLimit;
        if (!is_scalar($limit) || !ctype_digit((string) $limit) || (int) $limit < 1 || (int) $limit > 20) {
            throw new \InvalidArgumentException('limit must be an integer between 1 and 20.');
        }

        $api = Injector::inst()->get(ApiClient::class);
        $result = $api->get('/properties', [
            'lettingStatus' => ['toLet', 'tenancyCurrent'],
            'internetAdvertising' => 'true',
            'embed' => 'images',
            'pageSize' => (int) $limit,
            'pageNumber' => 1,
        ]);

        $properties = $result['_embedded'] ?? [];
        if (!is_array($properties)) {
            throw new \RuntimeException('Unexpected Reapit properties response.');
        }

        echo 'Examining ' . count($properties) . " properties.\n";
        foreach ($properties as $property) {
            if (!is_array($property)) {
                continue;
            }
            $embedded = $property['_embedded'] ?? [];
            $images = is_array($embedded) ? ($embedded['images'] ?? []) : [];
            if (!is_array($images) || $images === []) {
                continue;
            }
            $first = reset($images);
            if (!is_array($first)) {
                continue;
            }

            echo 'Property ID: ' . (string) ($property['id'] ?? '') . "\n";
            echo 'Embedded images: ' . count($images) . "\n";
            echo json_encode($first, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
            return;
        }

        echo "No embedded images found in the sampled properties. Try a different page or property.\n";
    }
}
