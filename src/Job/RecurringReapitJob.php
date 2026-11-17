<?php

declare(strict_types=1);

namespace DorsetDigital\Reapit\Job;

use DorsetDigital\Reapit\Service\ReapitJobManager;
use SilverStripe\Core\Injector\Injector;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;

/**
 * A successful run records its completion and schedules its successor.
 * Failures do neither; the caretaker is responsible for recovery.
 */
abstract class RecurringReapitJob extends AbstractQueuedJob
{
    public function afterComplete(): void
    {
        Injector::inst()->get(ReapitJobManager::class)->completed(static::class);
    }

    public function getRunAsMemberID()
    {
        return 0;
    }
}
