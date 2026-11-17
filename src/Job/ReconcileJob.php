<?php

declare(strict_types=1);

namespace DorsetDigital\Reapit\Job;

use DorsetDigital\Reapit\Service\PropertySynchroniser;
use SilverStripe\Core\Injector\Injector;
use Symbiote\QueuedJobs\Services\QueuedJob;

class ReconcileJob extends RecurringReapitJob
{
    public function getTitle(): string
    {
        return 'Reconcile withdrawn Reapit properties';
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
        Injector::inst()->get(PropertySynchroniser::class)->reconcile();
        $this->currentStep = 1;
        $this->isComplete = true;
    }
}
