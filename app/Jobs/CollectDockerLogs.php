<?php

namespace App\Jobs;

use App\Services\DockerLogCollector;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CollectDockerLogs implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 30;

    public int $tries = 1;

    public function __construct()
    {
        $this->onQueue('docker-logs');
    }

    public function handle(DockerLogCollector $collector): void
    {
        $collector->collect();
    }
}
