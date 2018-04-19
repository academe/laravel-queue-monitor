<?php

namespace Academe\LaravelQueueMonitor\Models;

/**
 * Queue monitor.
 */

use Illuminate\Database\Eloquent\Model;

class QueueMonitor extends Model
{
    protected $table = 'queue_monitor';

    /**
     * Disable Laravel timestamps (on by default).
     */
    public $timestamps = false;

    protected $fillable = [
        'job_id',
        'name',
        'queue',
        'started_at',
        'payload',
        'started_at',
        'finished_at',
        'time_elapsed',
        'failed',
        'attempt',
        'exception',
        'data',
    ];

    /**
     * Automatic casting.
     */
    protected $casts = [
        'payload' => 'json',
    ];

    /**
     * @return Illuminate\Bus\Queueable the command that was dispatched to the queue
     */
    public function getCommandAttribute()
    {
        $payload = $this->payload;

        if (isset($payload['data']) && isset($payload['data']['command'])) {
            $command = $payload['data']['command'];

            try {
                return unserialize($command);
            } catch (\Exception $e) {
                // Unable to unserialize, so just return the raw string.
                return $payload;
            }
        }
    }

    /**
     * Filter for a given job ID.
     * Order by the latest first, in case there are duplicates.
     */
    public function scopeForJobId($query, $jobId) {
        $query
            ->where('job_id', $jobId)
            ->orderBy('started_at', 'desc')
            ->orderBy('id', 'desc');
    }
}
