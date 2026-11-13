<?php

declare(strict_types=1);

namespace DorsetDigital\Reapit\Task;

use DorsetDigital\Reapit\Service\PropertySynchroniser;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;
use SilverStripe\Control\HTTPRequest;

class ImportPropertiesTask extends BuildTask
{
    private static string $segment = 'reapit-import';
    protected $title = 'Initial Reapit property import';
    protected $description = 'Import all currently advertised lettings properties';

    public function run(HTTPRequest $request): void
    {
        $count = Injector::inst()->get(PropertySynchroniser::class)->import(true);
        echo "Imported {$count} Reapit properties\n";
    }
}
