<?php

declare(strict_types=1);

namespace DorsetDigital\Reapit\Control;

use DorsetDigital\Reapit\Model\Property;
use SilverStripe\CMS\Controllers\ContentController;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\ORM\PaginatedList;
use SilverStripe\View\Requirements;

/**
 * Frontend display only. Never calls the Reapit API from a web request.
 */
class PropertyListingPageController extends ContentController
{
    private static array $allowed_actions = ['view'];

    private static array $url_handlers = [
        '/' => 'index',
        '$Slug!' => 'view',
    ];

    private const SORTS = [
        'newest' => ['MarketingDate' => 'DESC', 'ID' => 'DESC'],
        'oldest' => ['MarketingDate' => 'ASC', 'ID' => 'ASC'],
        'price-low' => ['Rent' => 'ASC', 'ID' => 'DESC'],
        'price-high' => ['Rent' => 'DESC', 'ID' => 'DESC'],
    ];

    protected function init()
    {
        parent::init();
        Requirements::css('dorsetdigital/silverstripe-reapit:client/css/property-listings.css');
    }

    public function getProperties(): PaginatedList
    {
        $sort = $this->getSort();
        $list = Property::get()
            ->filter('Available', true)
            ->sort(self::SORTS[$sort]);

        $paginated = PaginatedList::create($list, $this->getRequest());
        $paginated->setPageLength(12);
        return $paginated;
    }

    public function getSort(): string
    {
        $value = $this->getRequest()->getVar('sort');
        return is_string($value) && isset(self::SORTS[$value]) ? $value : 'newest';
    }

    public function getSortOptions(): array
    {
        return [
            'newest' => 'Newest first',
            'oldest' => 'Oldest first',
            'price-low' => 'Lowest rent',
            'price-high' => 'Highest rent',
        ];
    }

    public function getCurrentSortLabel(): string
    {
        return $this->getSortOptions()[$this->getSort()];
    }

    public function PropertyDetailLink(string $slug): string
    {
        return $this->Link(rawurlencode($slug));
    }

    public function view(HTTPRequest $request)
    {
        $slug = $request->param('Slug');
        if (!is_string($slug) || $slug === '' || !preg_match('/^[a-z0-9-]+$/', $slug)) {
            return $this->httpError(404);
        }

        $property = Property::get()->filter([
            'Slug' => $slug,
            'Available' => true,
        ])->first();

        if (!$property) {
            return $this->httpError(404);
        }

        // Returning customised page data preserves the normal SiteTree layout.
        // Silverstripe selects PropertyListingPage_view.ss for this action.
        return $this->customise(['Property' => $property]);
    }
}
