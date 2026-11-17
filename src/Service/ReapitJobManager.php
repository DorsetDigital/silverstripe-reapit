<?php

declare(strict_types=1);

namespace DorsetDigital\Reapit\Service;

use DorsetDigital\Reapit\Job\PropertyImageReconcileJob;
use DorsetDigital\Reapit\Job\PropertyImageSyncJob;
use DorsetDigital\Reapit\Job\PropertySyncJob;
use DorsetDigital\Reapit\Job\ReconcileJob;
use DorsetDigital\Reapit\Model\JobHealth;
use GuzzleHttp\Client;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Environment;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;
use Symbiote\QueuedJobs\Services\QueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJobService;

/**
 * Maintains one scheduled/running instance of each enabled job and sends
 * transition-based alerts when successful completion becomes overdue.
 */
class ReapitJobManager
{
    private const JOBS = [
        PropertySyncJob::class,
        ReconcileJob::class,
        PropertyImageSyncJob::class,
        PropertyImageReconcileJob::class,
    ];

    private const ACTIVE = [
        QueuedJob::STATUS_NEW,
        QueuedJob::STATUS_INIT,
        QueuedJob::STATUS_RUN,
        QueuedJob::STATUS_WAIT,
        QueuedJob::STATUS_PAUSED,
    ];

    public function check(): void
    {
        foreach (self::JOBS as $class) {
            if (!$this->enabled($class)) {
                continue;
            }

            $state = $this->state($class);
            $active = QueuedJobDescriptor::get()->filter([
                'Implementation' => $class,
                'JobStatus' => self::ACTIVE,
            ])->exists();

            // Do not replace an in-flight or deliberately paused job.
            if (!$active) {
                $this->queue($class, 0);
            }

            $threshold = $this->seconds($class, 'alert_after');
            if ($threshold <= 0) {
                continue;
            }

            $last = $state->LastSuccessful ?: $state->FirstSeen;
            $overdue = $last && time() - strtotime((string) $last) > $threshold;

            if ($overdue && !$state->AlertActive) {
                if ($this->notify('Reapit ' . (new $class())->getTitle() . ' has not completed successfully within ' . $threshold . ' seconds.')) {
                    $state->AlertActive = true;
                    $state->write();
                }
            } elseif (!$overdue && $state->AlertActive) {
                if ($this->notify('Recovered: Reapit ' . (new $class())->getTitle() . ' is completing successfully again.')) {
                    $state->AlertActive = false;
                    $state->write();
                }
            }
        }
    }

    /** Called only after the job has completed without throwing. */
    public function completed(string $class): void
    {
        if (!in_array($class, self::JOBS, true)) {
            throw new \InvalidArgumentException('Not a managed Reapit job.');
        }

        $state = $this->state($class);
        $state->LastSuccessful = gmdate('Y-m-d H:i:s');
        $state->write();

        if ($this->enabled($class)) {
            $this->queue($class, $this->seconds($class, 'interval'));
        }
    }

    private function enabled(string $class): bool
    {
        return (bool) Config::inst()->get($class, 'enabled');
    }

    private function seconds(string $class, string $setting): int
    {
        return max(0, (int) Config::inst()->get($class, $setting));
    }

    private function state(string $class): JobHealth
    {
        $state = JobHealth::get()->filter('JobClass', $class)->first();
        if (!$state) {
            $state = JobHealth::create();
            $state->JobClass = $class;
            $state->FirstSeen = gmdate('Y-m-d H:i:s');
            $state->write();
        }
        return $state;
    }

    private function queue(string $class, int $delay): void
    {
        QueuedJobService::singleton()->queueJob(
            new $class(),
            gmdate('Y-m-d H:i:s', time() + $delay),
            0
        );
    }

    private function notify(string $message): bool
    {
        $url = Environment::getEnv('REAPIT_SLACK_WEBHOOK');
        if (!is_string($url) || $url === '') {
            return false;
        }

        try {
            (new Client())->post($url, [
                'json' => ['text' => $message],
                'timeout' => 5,
                'connect_timeout' => 3,
                'http_errors' => true,
            ]);
            return true;
        } catch (\Throwable $exception) {
            error_log('Reapit Slack alert failed: ' . $exception->getMessage());
            return false;
        }
    }
}
