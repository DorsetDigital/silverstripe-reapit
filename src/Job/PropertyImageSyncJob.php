<?php

declare(strict_types=1);

namespace DorsetDigital\Reapit\Job;

use DorsetDigital\Reapit\Service\PropertyImageFeedSynchroniser;
use SilverStripe\Core\Injector\Injector;
use Symbiote\QueuedJobs\Services\QueuedJob;

/**
 * Run after properties are synchronised so newly advertised properties
 * exist locally before their image records are requested.
 */
class PropertyImageSyncJob extends RecurringReapitJob
{
    public function getTitle(): string
    {
        return 'Synchronise changed Reapit property images';
    }

    public function getJobType(): int
    {
        return QueuedJob::QUEUED;
    }

    public function setup(): void
    {
        $this->totalSteps = 1;
    }

    public function process(): void
    {
        Injector::inst()->get(PropertyImageFeedSynchroniser::class)->sync();
        $this->currentStep = 1;
        $this->isComplete = true;
    }
}
