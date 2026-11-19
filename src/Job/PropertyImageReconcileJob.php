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

    public function getJobType(): string
    {
        return QueuedJob::QUEUED;
    }

    public function setup(): void
    {
        $this->totalSteps = 1;
    }

    public function process(): void
    {
        $started = microtime(true);
        try {
            $result = Injector::inst()->get(PropertyImageReconciler::class)->reconcile(apply: true);
            $this->addMessage('Properties checked: ' . $result['properties']);
            $this->addMessage('Remote images: ' . $result['remote']);
            $this->addMessage('Existing local images: ' . $result['existing']);
            $this->addMessage('Images downloaded: ' . $result['downloaded']);
            $this->addMessage('Obsolete images removed: ' . $result['missing']);
            $this->addMessage(sprintf('Completed in %.1f seconds.', microtime(true) - $started));
            $this->currentStep = 1;
            $this->isComplete = true;
        } catch (\Throwable $exception) {
            $this->addMessage('Failed (' . get_class($exception) . ').', 'ERROR');
            throw $exception;
        }
    }
}
