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

        $imagesArg = $request->getVar('images');
        if ($imagesArg !== null && !in_array($imagesArg, ['0', '1', 0, 1], true)) {
            throw new \InvalidArgumentException('images must be 0 or 1.');
        }
        $withImages = $imagesArg === '1' || $imagesArg === 1;

        $count = Injector::inst()->get(PropertySynchroniser::class)->import(true, $limit, $withImages);
        echo "Imported {$count} Reapit properties" . ($limit === null ? "" : " (test limit: {$limit}; checkpoint unchanged)") . ($withImages ? " (embedded images synchronised)" : "") . "\n";
    }
}
