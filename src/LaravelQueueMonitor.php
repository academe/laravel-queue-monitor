<?php

namespace Academe\LaravelQueueMonitor;

use DB;
use Log;
use Throwable;
use Carbon\Carbon;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Jobs\Job;
use Illuminate\Queue\QueueManager;
use Academe\LaravelQueueMonitor\Models\QueueMonitor;

class LaravelQueueMonitor
{
    public function register()
    {
        app(QueueManager::class)->before(function (JobProcessing $event) {
            $this->handleJobProcessing($event);
        });
        app(QueueManager::class)->after(function (JobProcessed $event) {
            $this->handleJobProcessed($event);
        });
        app(QueueManager::class)->failing(function (JobFailed $event) {
            $this->handleJobFailed($event);
        });
        app(QueueManager::class)->exceptionOccurred(function (JobExceptionOccurred $event) {
            $this->handleJobExceptionOccurred($event);
        });
    }

    /**
     * Event: the job has started.
     */
    protected function handleJobProcessing(JobProcessing $event)
    {
        $this->jobStarted($event->job);
    }

    /**
     * Event: the job has finished successfully.
     */
    protected function handleJobProcessed(JobProcessed $event)
    {
        $this->jobFinished($event->job);
    }

    /**
     * Event: the job has finished and declared itself as failed.
     */
    protected function handleJobFailed(JobFailed $event)
    {
        $this->jobFinished($event->job, true);
    }

    /**
     * Event: the job threw an unhandled exception.
     */
    protected function handleJobExceptionOccurred(JobExceptionOccurred $event)
    {
        $this->jobFinished($event->job, true, $event->exception);
    }

    /**
     * Return the ID of the job.
     * If the job does not provide an ID, then derive one by hashing the body.
     */
    protected function getJobId(Job $job)
    {
        if (method_exists($job, 'getJobId') && $jobId = $job->getJobId()) {
            return $jobId;
        }

        return sha1($job->getRawBody());
    }

    /**
     * Record the job start details.
     */
    protected function jobStarted(Job $job)
    {
        try {
            QueueMonitor::create([
                'job_id'        => $this->getJobId($job),
                'name'          => $job->resolveName(),
                'queue'         => $job->getQueue(),
                'started_at'    => Carbon::now(),
                'payload'       => $job->getRawBody(),
            ]);
        } catch (Throwable $e) {
            Log::error(sprintf(
                'Failed to log start of queued job execution: %s',
                $e->getMessage()
            ));
        }
    }

    /**
     * Record the job finish details.
     */
    protected function jobFinished(Job $job, bool $failed = false, Throwable $exception = null)
    {
        try {
            $queueMonitor = QueueMonitor::forJobId($this->getJobId($job))
                ->first();

            if (! $queueMonitor) {
                return;
            }

            $now = Carbon::now();
            $timeElapsed = Carbon::parse($queueMonitor->started_at)
                ->diffInSeconds($now);

            $queueMonitor->finished_at = $now;
            $queueMonitor->time_elapsed = $timeElapsed;
            $queueMonitor->failed = $failed;
            $queueMonitor->attempt = $job->attempts();

            if ($exception) {
                $queueMonitor->exception = $exception->getMessage();
            }

            $queueMonitor->save();
        } catch (Throwable $e) {
            Log::error(sprintf(
                'Failed to log finish of queued job execution: %s',
                $e->getMessage()
            ));
        }
    }
}
