<?php

declare(strict_types=1);

namespace DorsetDigital\Reapit\Task;

use DorsetDigital\Reapit\Service\PropertySynchroniser;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;

class ImportPropertiesTask extends BuildTask
{
    private static string $segment = 'reapit-import';
    protected $title = 'Initial Reapit property import';
    protected $description = 'Import all currently advertised lettings properties';

    public function run($request): void
    {
        $value = $request->getVar('limit');
        $limit = null;
        if ($value !== null && $value !== '') {
            if (!is_scalar($value) || !ctype_digit((string) $value) || (int) $value < 1) {
                throw new \InvalidArgumentException('limit must be a positive integer.');
            }
            $limit = (int) $value;
        }

        $count = Injector::inst()->get(PropertySynchroniser::class)->import(true, $limit);
        echo "Imported {$count} Reapit properties" . ($limit === null ? "" : " (test limit: {$limit}; checkpoint unchanged)") . "\n";
    }
}
