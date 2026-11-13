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

        $property->Address = implode(', ', $street);
        $strapline = self::nonEmptyText($remote['strapline'] ?? null);
        $property->Title = $strapline
            ?? ($property->Address !== '' ? 'Property at ' . $property->Address : 'Property ' . (string) ($remote['id'] ?? ''));
        $property->Summary = self::nonEmptyText($remote['summary'] ?? null) ?? '';
        $property->Description = self::nonEmptyText($remote['description'] ?? null) ?? '';
        $property->LongDescription = self::nonEmptyText($remote['longDescription'] ?? null) ?? '';
        $property->PropertyTypes = self::stringList($remote['type'] ?? []);
        $property->PropertyStyles = self::stringList($remote['style'] ?? []);
        $property->Postcode = (string) ($address['postcode'] ?? '');
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

    private static function nonEmptyText(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * Preserve Reapit's multi-valued type/style attributes as JSON arrays.
     * Their labels can be mapped separately once confirmed for a customer.
     */
    private static function stringList(mixed $values): string
    {
        $items = is_array($values) ? array_values(array_filter(
            $values,
            static fn (mixed $value): bool => is_string($value) && trim($value) !== ''
        )) : [];

        return json_encode($items, JSON_THROW_ON_ERROR);
    }

    private static function date(mixed $date): ?string
    {
        if (!is_string($date) || $date === '') {
            return null;
        }
        return (new \DateTimeImmutable($date))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
