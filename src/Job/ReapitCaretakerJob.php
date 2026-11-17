<?php

declare(strict_types=1);

namespace DorsetDigital\Reapit\Job;

use DorsetDigital\Reapit\Service\ReapitJobManager;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Config\Config;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJobService;

/** Required job: repairs missing schedules and monitors successful completions. */
class ReapitCaretakerJob extends AbstractQueuedJob
{
    private static int $interval = 300;

    public function getTitle(): string
    {
        return 'Reapit job caretaker';
    }

    public function getJobType(): string
    {
        return QueuedJob::QUEUED;
    }

    public function getRunAsMemberID()
    {
        return 0;
    }

    public function setup(): void
    {
        $this->totalSteps = 1;
    }

    public function process(): void
    {
        Injector::inst()->get(ReapitJobManager::class)->check();
        $this->currentStep = 1;
        $this->isComplete = true;
    }

    public function afterComplete(): void
    {
        $interval = max(60, (int) Config::inst()->get(self::class, 'interval'));
        QueuedJobService::singleton()->queueJob(
            new self(),
            gmdate('Y-m-d H:i:s', time() + $interval),
            0
        );
    }
}
