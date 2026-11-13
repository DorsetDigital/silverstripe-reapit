<?php

declare(strict_types=1);

namespace DorsetDigital\Reapit\Task;

use DorsetDigital\Reapit\Service\PropertyImageReconciler;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;

class ReconcilePropertyImagesTask extends BuildTask
{
    private static string $segment = 'reapit-reconcile-images';
    protected $title = 'Reconcile Reapit property images';
    protected $description = 'Compare complete image collections; dry-run unless apply=1';

    public function run($request): void
    {
        $id = $request->getVar('propertyId');
        if ($id !== null && (!is_string($id) || !preg_match('/^[A-Za-z0-9_-]{1,100}$/', $id))) {
            throw new \InvalidArgumentException('Invalid propertyId.');
        }

        $value = $request->getVar('limit');
        $limit = null;
        if ($value !== null && $value !== '') {
            if (!is_scalar($value) || !ctype_digit((string) $value) || (int) $value < 1 || (int) $value > 100) {
                throw new \InvalidArgumentException('limit must be between 1 and 100.');
            }
            $limit = (int) $value;
        }

        $all = $request->getVar('all');
        $apply = $request->getVar('apply');
        if (($all !== null && $all !== '1') || ($apply !== null && $apply !== '1')) {
            throw new \InvalidArgumentException('all and apply must equal 1 when supplied.');
        }
        if ($all === '1' && ($limit !== null || $id !== null)) {
            throw new \InvalidArgumentException('all=1 cannot be combined with propertyId or limit.');
        }
        if ($id === null && $limit === null && $all !== '1') {
            $limit = 10;
        }

        $result = Injector::inst()->get(PropertyImageReconciler::class)->reconcile(
            $id,
            $limit,
            $apply === '1'
        );

        echo sprintf(
            "%s: %d properties, %d local images, %d remote images, %d missing locally held images, %d downloaded.\n",
            $apply === '1' ? 'Applied' : 'Dry run',
            $result['properties'],
            $result['existing'],
            $result['remote'],
            $result['missing'],
            $result['downloaded']
        );
        foreach ($result['removals'] as $propertyId => $ids) {
            echo $propertyId . ': would remove ' . implode(', ', $ids) . "\n";
        }
    }
}
