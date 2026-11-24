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
            $result = Injector::inst()->get(PropertyImageFeedSynchroniser::class)->sync();
            $this->addMessage('Properties checked: ' . $result['properties']);
            $this->addMessage('Image inventories initialised: ' . $result['initialised']);
            $this->addMessage('Changed image records: ' . $result['changed']);
            $this->addMessage('Images downloaded: ' . $result['downloaded']);
            $this->addMessage(sprintf('Completed in %.1f seconds.', microtime(true) - $started));
            $this->currentStep = 1;
            $this->isComplete = true;
        } catch (\Throwable $exception) {
            $this->addMessage('Failed (' . get_class($exception) . ').', 'ERROR');
            throw $exception;
        }
    }
}
