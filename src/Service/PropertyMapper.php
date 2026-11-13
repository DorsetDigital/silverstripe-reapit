<?php

declare(strict_types=1);

namespace DorsetDigital\Reapit\Service;

use DorsetDigital\Reapit\Model\Property;
use SilverStripe\Core\Extensible;

/**
 * Keep Reapit's field shapes in one place. Projects may extend mapProperty().
 */
class PropertyMapper
{
    use Extensible;

    public function mapProperty(Property $property, array $remote): void
    {
        $address = $remote['address'] ?? [];
        $address = is_array($address) ? $address : [];
        $letting = $remote['letting'] ?? [];
        $letting = is_array($letting) ? $letting : [];

        $street = array_filter([
            $address['buildingName'] ?? null,
            $address['buildingNumber'] ?? null,
            $address['line1'] ?? null,
            $address['line2'] ?? null,
            $address['line3'] ?? null,
            $address['line4'] ?? null,
        ], static fn ($part): bool => is_scalar($part) && trim((string) $part) !== '');

        $property->Title = (string) ($remote['marketing']['headline'] ?? $remote['description'] ?? $remote['id']);
        $property->Address = implode(', ', $street);
        $property->Postcode = (string) ($address['postcode'] ?? '');
        $property->Description = (string) ($remote['marketing']['description'] ?? '');
        $property->Bedrooms = (int) ($remote['bedrooms'] ?? 0);
        $property->Bathrooms = (int) ($remote['bathrooms'] ?? 0);
        $property->Rent = is_numeric($letting['rent'] ?? null) ? (float) $letting['rent'] : 0;
        $property->RentFrequency = (string) ($letting['rentFrequency'] ?? '');
        $property->LettingStatus = (string) ($letting['status'] ?? '');
        $property->RemoteModified = self::date($remote['modified'] ?? null);
        $property->MarketingDate = self::date($remote['marketing']['date'] ?? $remote['created'] ?? null);
        $property->Available = (bool) ($remote['internetAdvertising'] ?? false);
        $property->RawData = json_encode($remote, JSON_THROW_ON_ERROR);

        $this->extend('updateMappedProperty', $property, $remote);
    }

    private static function date(mixed $date): ?string
    {
        if (!is_string($date) || $date === '') {
            return null;
        }
        return (new \DateTimeImmutable($date))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
