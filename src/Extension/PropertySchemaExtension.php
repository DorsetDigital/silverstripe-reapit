<?php

declare(strict_types=1);

namespace DorsetDigital\Reapit\Extension;

use DorsetDigital\Reapit\Model\Property;
use DorsetDigital\Reapit\Page\PropertyListingPage;
use DorsetDigital\SchemaManager\Control\SchemaIDs;
use DorsetDigital\SchemaManager\Model\Schema\ItemListSchema;
use DorsetDigital\SchemaManager\Model\Schema\PropertyCollectionSchema;
use DorsetDigital\SchemaManager\Model\Schema\PropertySchema;
use DorsetDigital\SchemaManager\Model\Schema\RealEstateListingSchema;
use DorsetDigital\SchemaManager\Model\Schema\RealEstateOfferSchema;
use DorsetDigital\SchemaManager\Model\Schema\WebPageSchema;
use DorsetDigital\SchemaManager\Control\SchemaRegistry;
use SilverStripe\ORM\PaginatedList;
use SilverStripe\Core\Extension;

/**
 * Optional bridge to DorsetDigital Schema Manager's real-estate builders.
 *
 * Uses Reapit's controller extension hooks to add schema once the listing
 * or individual property has been resolved. No changes to Schema Manager
 * or parsing of incoming URLs are required.
 */
class PropertySchemaExtension extends Extension
{
    /**
     * Controller hooks run after the listing or detail record has been resolved.
     * The schema module remains entirely optional.
     */
    public function updatePropertyListing(PaginatedList $properties): void
    {
        if (!class_exists(PropertyCollectionSchema::class)) {
            return;
        }

        $page = $this->owner->data();
        if (!$page instanceof PropertyListingPage) {
            return;
        }

        $itemList = ItemListSchema::create(
            'reapit-properties-' . $page->ID,
            (string) $page->Title
        );

        foreach ($properties as $property) {
            $url = $property->AbsoluteLink();
            if ($url) {
                $itemList->addItem($url, (string) $property->Title);
            }
        }

        $pageURL = $page->AbsoluteLink();
        $webPage = WebPageSchema::create((string) $page->ID, (string) $page->Title)
            ->setURL($pageURL);
        $webPage->update(['@id' => SchemaIDs::webPage($page)]);

        SchemaRegistry::add(PropertyCollectionSchema::fromWebPage(
            $webPage,
            $itemList->getEntityID()
        ));
        SchemaRegistry::add($itemList);
    }

    public function updatePropertyDetail(Property $record): void
    {
        if (!class_exists(RealEstateListingSchema::class)) {
            return;
        }

        $this->detail($record);
    }

    private function detail(Property $record): void
    {
        $url = $record->AbsoluteLink();
        if (!is_string($url) || $url === '') {
            return;
        }

        $identifier = (string) $record->ID;
        $property = PropertySchema::create($identifier, (string) $record->Title)
            ->setURL($url)
            ->setDescription(trim(strip_tags((string) $record->Description)) ?: null)
            ->setIdentifier((string) $record->ReapitID);

        $address = array_filter([
            'streetAddress' => (string) $record->Address,
            'postalCode' => (string) $record->Postcode,
        ], static fn (string $value): bool => $value !== '');
        if ($address) {
            $property->setAddress($address);
        }

        if ((int) $record->Bedrooms > 0) {
            $property->setBedrooms((int) $record->Bedrooms);
        }
        if ((int) $record->Bathrooms > 0) {
            $property->setBathrooms((int) $record->Bathrooms);
        }

        $images = [];
        foreach ($record->Images() as $imageRelation) {
            $image = $imageRelation->Image();
            if ($image && $image->exists()) {
                $images[] = $image->getAbsoluteURL();
            }
        }
        if ($images) {
            $property->setImages($images);
        }

        $offer = RealEstateOfferSchema::create($identifier, $property->getEntityID())
            ->setURL($url)
            ->setBusinessFunction('LeaseOut');

        $frequency = strtolower(trim((string) $record->RentFrequency));
        $period = match ($frequency) {
            'monthly', 'month', 'pcm', 'permonth' => 'MON',
            'weekly', 'week', 'pw', 'perweek' => 'WEE',
            'yearly', 'annually', 'annual', 'peryear' => 'ANN',
            default => null,
        };
        if ($period && (float) $record->Rent > 0) {
            $offer->setRentalPrice((float) $record->Rent, 'GBP', $period);
        }

        $webPage = WebPageSchema::create('reapit-property-' . $identifier, (string) $record->Title)
            ->setURL($url);
        $listing = RealEstateListingSchema::fromWebPage($webPage)
            ->setOffer($offer->getEntityID());
        $listing->update(['@id' => SchemaIDs::forPageURL($url, 'webpage')]);

        if ($record->MarketingDate) {
            $listing->setDatePosted((string) $record->MarketingDate);
        }
        if ($record->RemoteModified) {
            $listing->setDateModified((string) $record->RemoteModified);
        }

        SchemaRegistry::add($listing);
        SchemaRegistry::add($property);
        SchemaRegistry::add($offer);
    }
}
