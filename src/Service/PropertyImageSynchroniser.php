<?php

declare(strict_types=1);

namespace DorsetDigital\Reapit\Service;

use DorsetDigital\Reapit\Model\Property;
use DorsetDigital\Reapit\Model\PropertyImage;
use GuzzleHttp\ClientInterface;
use RuntimeException;
use SilverStripe\Assets\Folder;
use SilverStripe\Assets\Image;

/**
 * Copy Reapit property photographs into Silverstripe's asset store.
 * One property's complete image collection is the unit of reconciliation.
 */
class PropertyImageSynchroniser
{
    public function __construct(private readonly ClientInterface $httpClient)
    {
    }

    /**
     * @param array<int, array<string, mixed>> $remoteImages Complete image collection for this property.
     * @param bool $pruneMissing Only when the remote collection is known complete.
     * @return int Number of downloaded images
     */
    public function sync(Property $property, array $remoteImages, bool $pruneMissing = true): int
    {
        if (!$property->isInDB()) {
            throw new RuntimeException('Save the property before synchronising its images.');
        }

        $folder = Folder::find_or_make('Reapit');

        $existing = [];
        foreach ($property->Images() as $record) {
            $existing[$record->ReapitID] = $record;
        }

        $seen = [];
        $downloaded = 0;
        foreach ($remoteImages as $metadata) {
            if (!is_array($metadata) || empty($metadata['id']) || !is_string($metadata['id'])) {
                throw new RuntimeException('Malformed Reapit image metadata; refusing to reconcile.');
            }
            $id = $metadata['id'];
            if (isset($seen[$id])) {
                throw new RuntimeException('Duplicate Reapit image ID in property response.');
            }
            $seen[$id] = true;

            $url = $metadata['url'] ?? null;
            if (!is_string($url) || !$this->allowedUrl($url)) {
                throw new RuntimeException('Invalid or untrusted Reapit image URL.');
            }
            if (($metadata['propertyId'] ?? null) !== $property->ReapitID) {
                throw new RuntimeException('Reapit image belongs to a different property.');
            }

            $record = $existing[$id] ?? PropertyImage::create();
            $etag = is_string($metadata['_eTag'] ?? null) ? $metadata['_eTag'] : '';
            $needsDownload = !$record->isInDB()
                || !$record->ImageID
                || $record->RemoteURL !== $url
                || $record->ReapitETag !== $etag;

            if ($needsDownload) {
                $bytes = $this->download($url);
                $extension = strtolower(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
                $image = $record->ImageID ? $record->Image() : Image::create();
                $image->ParentID = $folder->ID;
                $safe = preg_replace('/[^a-zA-Z0-9_-]/', '-', $property->CustomerID . '-' . $property->ReapitID . '-' . $id);
                $image->setFromString($bytes, 'reapit-' . $safe . '.' . $extension);
                $image->write();
                $image->publishSingle();
                $record->ImageID = $image->ID;
                $downloaded++;
            } else {
                $image = $record->Image();
                $moved = $image->ParentID != $folder->ID;
                if ($moved) {
                    // Move existing imports through the asset API, retaining their IDs.
                    $image->ParentID = $folder->ID;
                    $image->write();
                }
                if ($moved || !$image->isPublished()) {
                    $image->publishSingle();
                }
            }

            $record->PropertyID = $property->ID;
            $record->ReapitID = $id;
            $record->RemoteURL = $url;
            $record->ReapitETag = $etag;
            $record->Caption = is_string($metadata['caption'] ?? null) ? $metadata['caption'] : '';
            $record->Position = is_numeric($metadata['order'] ?? null) ? (int) $metadata['order'] : 0;
            $record->write();
        }

        // Only prune after the entire remote collection has been handled successfully.
        foreach ($existing as $id => $record) {
            if ($pruneMissing && !isset($seen[$id])) {
                // PropertyImage::onAfterDelete owns the asset cleanup.
                $record->delete();
            }
        }

        return $downloaded;
    }

    private function allowedUrl(string $url): bool
    {
        $parts = parse_url($url);
        if (!is_array($parts)
            || ($parts['scheme'] ?? '') !== 'https'
            || strtolower($parts['host'] ?? '') !== 'assets.reapit.net'
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['port'])
        ) {
            return false;
        }
        return in_array(strtolower(pathinfo($parts['path'] ?? '', PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp'], true);
    }

    private function download(string $url): string
    {
        $response = $this->httpClient->request('GET', $url, [
            'timeout' => 30,
            'connect_timeout' => 10,
            'http_errors' => true,
            'allow_redirects' => false,
            'stream' => true,
        ]);
        if ($response->getStatusCode() !== 200) {
            throw new RuntimeException('Image download returned a non-success status.');
        }

        $stream = $response->getBody();
        $bytes = '';
        $maxBytes = 15 * 1024 * 1024;
        while (!$stream->eof()) {
            $chunk = $stream->read(min(65536, $maxBytes + 1 - strlen($bytes)));
            if ($chunk === '' && !$stream->eof()) {
                throw new RuntimeException('Reapit image download stalled.');
            }
            $bytes .= $chunk;
            if (strlen($bytes) > $maxBytes) {
                throw new RuntimeException('Reapit image exceeds the 15 MiB limit.');
            }
        }
        if ($bytes === '' || @getimagesizefromstring($bytes) === false) {
            throw new RuntimeException('Downloaded Reapit image is not a supported image.');
        }
        return $bytes;
    }
}
