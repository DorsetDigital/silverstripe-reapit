<?php

declare(strict_types=1);

namespace DorsetDigital\Reapit\Page;

use DorsetDigital\Reapit\Control\PropertyListingPageController;
use DorsetDigital\Reapit\Model\Property;
use SilverStripe\Assets\Image;
use SilverStripe\LinkField\Form\LinkField;
use SilverStripe\LinkField\Models\Link;

/**
 * One CMS page owns the property listing and its property detail URLs.
 *
 * Create this page through the host site's Page hierarchy. No imported property creates
 * a SiteTree record.
 */
class PropertyListingPage extends \Page
{
    private static string $table_name = 'ReapitPropertyListingPage';

    private static string $controller_name = PropertyListingPageController::class;

    private static string $singular_name = 'Property listings';

    private static string $description = 'Displays imported Reapit properties and individual property details.';

    private static array $has_one = [
        'PropertyEnquiryLink' => Link::class,
    ];

    private static array $owns = [
        'PropertyEnquiryLink',
    ];

    private static array $cascade_deletes = [
        'PropertyEnquiryLink',
    ];

    private static array $cascade_duplicates = [
        'PropertyEnquiryLink',
    ];

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->removeByName('PropertyEnquiryLinkID');
        $fields->addFieldToTab(
            'Root.Main',
            LinkField::create('PropertyEnquiryLink', 'Property enquiry CTA')
        );

        return $fields;
    }

    private ?Property $propertyContext = null;

    /**
     * Make property metadata available to the usual SiteTree metadata pipeline,
     * without persisting any changes to the listing page.
     */
    public function setPropertyContext(Property $property): void
    {
        $this->propertyContext = $property;
        $this->setField('Title', $property->Title);
        $this->setField('MetaTitle', $property->getMetaTitle());
        $this->setField('MetaDescription', $property->getMetaDescription());
    }

    public function getPropertyContext(): ?Property
    {
        return $this->propertyContext;
    }

    public function AbsoluteLink($action = null)
    {
        if ($this->propertyContext !== null && $action === null) {
            return parent::AbsoluteLink($this->propertyContext->Slug);
        }

        return parent::AbsoluteLink($action);
    }

    public function getSocialImage(): ?Image
    {
        if ($this->propertyContext === null) {
            return null;
        }

        foreach ($this->propertyContext->Images() as $propertyImage) {
            $image = $propertyImage->Image();
            if ($image && $image->exists() && $image->isPublished()) {
                return $image;
            }
        }

        return null;
    }
}
