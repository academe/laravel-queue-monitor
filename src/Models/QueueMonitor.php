<?php

namespace Gilbitron\LaravelQueueMonitor\Models;

/**
 * Queue monitor.
 */

use Illuminate\Database\Eloquent\Model;

class QueueMonitor extends Model
{
    protected $table = 'queue_monitor';

    protected $fillable = [
        'job_id',
        'name',
        'queue',
        'started_at',
        'payload',
        'finished_at',
        'time_elapsed',
        'failed',
        'attempt',
        'exception',
    ];

    protected $casts = [
        'payload' => 'json',
    ];

    public function getCommandAttribute()
    {
        $payload = $this->payload;

        if (isset($payload['data']) && isset($payload['data']['command'])) {
            $command = $payload['data']['command'];

            try {
                return unserialize($command);
            } catch (\Exception $e) {
            }
        }
    }
}
