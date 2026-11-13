<?php

declare(strict_types=1);

namespace DorsetDigital\Reapit\Control;

use DorsetDigital\Reapit\Model\Property;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\ORM\PaginatedList;
use SilverStripe\View\Requirements;

/**
 * Frontend display only. Never calls the Reapit API from a web request.
 */
class PropertyListingPageController extends \PageController
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

    /**
     * The host site may use an optional SEOTitle field in its Page.ss template.
     * On detail URLs it must not retain the listing page's SEO title.
     */
    public function getSEOTitle(): ?string
    {
        $page = $this->data();
        $property = $page->getPropertyContext();

        return $property ? $property->getMetaTitle() : $page->SEOTitle;
    }

    /**
     * The outer Page.ss template resolves AbsoluteLink() on the controller
     * before the SiteTree record. Use the detail URL only for property requests.
     */
    public function AbsoluteLink($action = '')
    {
        $page = $this->data();
        if ($action === '' && $page->getPropertyContext() !== null) {
            return $page->AbsoluteLink();
        }

        return parent::AbsoluteLink($action);
    }

    public function view(HTTPRequest $request)
    {
        $slug = $request->param('Slug');

        $property = Property::get()->filter([
            'Slug' => $slug,
            'Available' => true,
        ])->first();

        if (!$property) {
            return $this->httpError(404);
        }

        // Update only the in-memory SiteTree record for this detail request.
        // MetaTags(false) and site-level metadata extensions then see property data.
        $this->data()->setPropertyContext($property);

        // Returning customised page data preserves the normal SiteTree layout.
        // Silverstripe selects PropertyListingPage_view.ss for this action.
        return $this->customise(['Property' => $property]);
    }
}
