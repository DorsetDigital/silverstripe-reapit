<?php

declare(strict_types=1);

namespace DorsetDigital\Reapit\Job;

use DorsetDigital\Reapit\Service\PropertyImageReconciler;
use SilverStripe\Core\Injector\Injector;
use Symbiote\QueuedJobs\Services\QueuedJob;

/**
 * Complete reconciliation can generate many API requests; do not schedule
 * until the dry-run has been verified against the live customer.
 */
class PropertyImageReconcileJob extends RecurringReapitJob
{
    public function getTitle(): string
    {
        return 'Reconcile Reapit property image inventories';
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
        Injector::inst()->get(PropertyImageReconciler::class)->reconcile(apply: true);
        $this->currentStep = 1;
        $this->isComplete = true;
    }
}
