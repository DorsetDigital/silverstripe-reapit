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
            $synchroniser = Injector::inst()->get(PropertySynchroniser::class);
            $withdrawn = $synchroniser->reconcile();
            $this->addMessage('Marketable properties returned: ' . $synchroniser->getLastReconciliationCount());
            $this->addMessage('Properties marked unavailable: ' . $withdrawn);
            $this->addMessage(sprintf('Completed in %.1f seconds.', microtime(true) - $started));
            $this->currentStep = 1;
            $this->isComplete = true;
        } catch (\Throwable $exception) {
            $this->addMessage('Failed (' . get_class($exception) . ').', 'ERROR');
            throw $exception;
        }
    }
}
