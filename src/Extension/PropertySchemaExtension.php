<?php

declare(strict_types=1);

namespace DorsetDigital\Reapit\Extension;

use DorsetDigital\Reapit\Model\Property;
use DorsetDigital\Reapit\Control\PropertyListingPageController;
use DorsetDigital\SchemaManager\Control\SchemaIDs;
use DorsetDigital\SchemaManager\Model\Schema\ItemListSchema;
use DorsetDigital\SchemaManager\Model\Schema\PropertyCollectionSchema;
use DorsetDigital\SchemaManager\Model\Schema\PropertySchema;
use DorsetDigital\SchemaManager\Model\Schema\RealEstateListingSchema;
use DorsetDigital\SchemaManager\Model\Schema\RealEstateOfferSchema;
use DorsetDigital\SchemaManager\Model\Schema\WebPageSchema;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Extension;

/**
 * Optional bridge to DorsetDigital Schema Manager's real-estate builders.
 *
 * Schema Manager invokes updateSchemaManagerEntities on the SiteTree page while
 * preparing the graph. Replacing its WebPage entity preserves the existing
 * canonical ID for the index; detail routes receive their own canonical ID.
 */
class PropertySchemaExtension extends Extension
{
    public function updateSchemaManagerEntities(array &$entities): void
    {
        // Do not require Schema Manager merely to use the Reapit importer.
        if (!class_exists(PropertyCollectionSchema::class)
            || !class_exists(RealEstateListingSchema::class)) {
            return;
        }

        $controller = Controller::curr();
        if (!$controller instanceof PropertyListingPageController) {
            return;
        }

        $request = $controller->getRequest();
        $slug = $request->param('Slug');

        if (is_string($slug) && $slug !== '') {
            $property = Property::get()->filter([
                'Slug' => $slug,
                'Available' => true,
            ])->first();

            if ($property) {
                $this->detail($entities, $property);
            }
            return;
        }

        $this->index($entities, $controller);
    }

    private function index(array &$entities, PropertyListingPageController $controller): void
    {
        $key = 'reapit-properties-' . $this->owner->ID;

        $itemList = ItemListSchema::create($key, (string) $this->owner->Title);
        foreach ($controller->getProperties() as $property) {
            $url = $property->AbsoluteLink();
            if (is_string($url) && $url !== '') {
                $itemList->addItem($url, (string) $property->Title);
            }
        }

        foreach ($entities as $index => $entity) {
            if (!$entity instanceof WebPageSchema) {
                continue;
            }

            $entities[$index] = PropertyCollectionSchema::fromWebPage(
                $entity,
                $itemList->getEntityID()
            );
            $entities[] = $itemList;
            break;
        }
    }

    private function detail(array &$entities, Property $record): void
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

        foreach ($entities as $index => $entity) {
            if (!$entity instanceof WebPageSchema) {
                continue;
            }

            $listing = RealEstateListingSchema::fromWebPage($entity)
                ->setURL($url)
                ->setOffer($offer->getEntityID());
            $listing->update([
                '@id' => SchemaIDs::forPageURL($url, 'webpage'),
                'name' => (string) $record->Title,
            ]);
            if ($record->MarketingDate) {
                $listing->setDatePosted((string) $record->MarketingDate);
            }
            if ($record->RemoteModified) {
                $listing->setDateModified((string) $record->RemoteModified);
            }

            $entities[$index] = $listing;
            $entities[] = $property;
            $entities[] = $offer;
            break;
        }
    }
}
